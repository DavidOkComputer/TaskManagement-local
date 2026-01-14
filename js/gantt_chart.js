/*gantt_chart.js diagrama de Gantt para visualización de tareas */
document.addEventListener('DOMContentLoaded', function() {
	// Elementos del DOM 
	const projectSelect = document.getElementById('id_proyecto');
	const ganttChart = document.getElementById('ganttChart');
	const ganttWrapper = document.getElementById('ganttChartWrapper');
	const ganttLoading = document.getElementById('ganttLoading');
	const ganttDefaultMessage = document.getElementById('ganttDefaultMessage');
	const ganttNoTasks = document.getElementById('ganttNoTasks');
	const viewModeSelect = document.getElementById('ganttViewMode');
	const groupBySelect = document.getElementById('ganttGroupBy');
	const btnToday = document.getElementById('btnTodayGantt');
	// Elementos del resumen 
	const projectInfoSummary = document.getElementById('projectInfoSummary');
	const summaryProjectName = document.getElementById('summaryProjectName');
	const summaryDateRange = document.getElementById('summaryDateRange');
	const summaryTaskCount = document.getElementById('summaryTaskCount');
	const summaryProgress = document.getElementById('summaryProgress');
	// Estado de la aplicación 
	let currentProjectId = null;
	let currentProjectData = null;
	let currentTasks = [];
	let currentViewMode = 'week';
	let currentGroupBy = 'user';
	let tooltipElement = null;
	// Configuración 
	const CONFIG = {
		cellWidths: {
			day: 60,
			week: 40,
			month: 80
		},
		barHeight: 28,
		rowHeight: 50,
		labelWidth: 250,
		daysToShow: {
			day: 14,
			week: 42,
			month: 90
		}
	};
	// Inicialización 
	init();

	function init() {
		loadProjects();
		attachEventListeners();
		createTooltipElement();
	}

	function loadProjects() {
		fetch('../php/get_projects.php')
			.then(response => response.json())
			.then(data => {
				if (data.success && data.proyectos) {
					populateProjectSelect(data.proyectos);
				} else {
					showAlert('Error al cargar proyectos', 'warning');
				}
			})
			.catch(error => {
				console.error('Error loading projects:', error);
				showAlert('Error al cargar proyectos', 'danger');
			});
	}
    
	function populateProjectSelect(projects) {
		projectSelect.innerHTML = '<option value="">Seleccione un proyecto</option>';
		projects.forEach(project => {
			const option = document.createElement('option');
			option.value = project.id_proyecto;
			option.textContent = project.nombre;
			option.dataset.progress = project.progreso;
			projectSelect.appendChild(option);
		});
	}
    
	function attachEventListeners() {
		// Cambio de proyecto 
		projectSelect.addEventListener('change', function() {
			if (this.value) {
				currentProjectId = this.value;
				loadProjectAndTasks(this.value);
			} else {
				currentProjectId = null;
				showDefaultState();
			}
		});
		// Cambio de modo de vista 
		viewModeSelect.addEventListener('change', function() {
			currentViewMode = this.value;
			if (currentTasks.length > 0) {
				renderGanttChart(currentTasks);
			}
		});
		// Cambio de agrupación 
		groupBySelect.addEventListener('change', function() {
			currentGroupBy = this.value;
			if (currentTasks.length > 0) {
				renderGanttChart(currentTasks);
			}
		});
		// Botón ir a hoy 
		btnToday.addEventListener('click', scrollToToday);
	}
    
	function loadProjectAndTasks(projectId) {
		showLoading();
		// Cargar detalles del proyecto 
		fetch(`../php/get_project_by_id.php?id=${projectId}`)
			.then(response => response.json())
			.then(data => {
				if (data.success && data.proyecto) {
					currentProjectData = data.proyecto;
					// Cargar tareas del proyecto 
					return fetch(`../php/get_tasks_by_project.php?id_proyecto=${projectId}`);
				} else {
					throw new Error('Error al cargar proyecto');
				}
			})
			.then(response => response.json())
			.then(data => {
				hideLoading();
				if (data.success && data.tasks) {
					currentTasks = data.tasks;
					if (currentTasks.length > 0) {
						updateProjectSummary();
						renderGanttChart(currentTasks);
					} else {
						showNoTasksState();
					}
				} else {
					showNoTasksState();
				}
			})
			.catch(error => {
				hideLoading();
				console.error('Error:', error);
				showAlert('Error al cargar datos del proyecto', 'danger');
			});
	}
    
	function updateProjectSummary() {
		if (!currentProjectData) return;
		summaryProjectName.textContent = currentProjectData.nombre;
		// Rango de fechas 
		const startDate = currentProjectData.fecha_inicio ?
			formatDateDisplay(currentProjectData.fecha_inicio) : 'Sin definir';
		const endDate = currentProjectData.fecha_cumplimiento ?
			formatDateDisplay(currentProjectData.fecha_cumplimiento) : 'Sin definir';
		summaryDateRange.textContent = `${startDate} - ${endDate}`;
		// Conteo de tareas 
		summaryTaskCount.textContent = currentTasks.length;
		// Progreso 
		const completedTasks = currentTasks.filter(t => t.estado === 'completado').length;
		const progress = currentTasks.length > 0 ?
			Math.round((completedTasks / currentTasks.length) * 100) : 0;
		summaryProgress.textContent = progress;
		projectInfoSummary.style.display = 'block';
	}
    
	function renderGanttChart(tasks) {
		// Calcular rango de fechas 
		const dateRange = calculateDateRange(tasks);
		const dates = generateDateArray(dateRange.start, dateRange.end);
		// Limpiar contenedor 
		ganttChart.innerHTML = '';
		ganttChart.className = `gantt-container view-${currentViewMode}`;
		// Crear header 
		const header = createGanttHeader(dates);
		ganttChart.appendChild(header);
		// Crear cuerpo con las tareas 
		const body = createGanttBody(tasks, dates);
		ganttChart.appendChild(body);
		addTodayLine(dates);
		showGanttChart();
		setTimeout(scrollToToday, 100);
	}

	function calculateDateRange(tasks) {
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		let minDate = new Date(today);
		let maxDate = new Date(today);
		// Considerar fecha de inicio del proyecto 
		if (currentProjectData && currentProjectData.fecha_inicio) {
			const projectStart = parseDateString(currentProjectData.fecha_inicio);
			if (projectStart && projectStart < minDate) {
				minDate = new Date(projectStart);
			}
		}
		// Considerar fecha de cumplimiento del proyecto 
		if (currentProjectData && currentProjectData.fecha_cumplimiento) {
			const projectEnd = parseDateString(currentProjectData.fecha_cumplimiento);
			if (projectEnd && projectEnd > maxDate) {
				maxDate = new Date(projectEnd);
			}
		}
		// Ajustar basado en las tareas 
		tasks.forEach(task => {
			if (task.fecha_cumplimiento) {
				const taskDate = parseDateString(task.fecha_cumplimiento);
				if (taskDate) {
					if (taskDate < minDate) minDate = new Date(taskDate);
					if (taskDate > maxDate) maxDate = new Date(taskDate);
				}
			}
		});
		// Agregar padding de días según el modo de vista 
		const padding = CONFIG.daysToShow[currentViewMode] / 3;
		minDate.setDate(minDate.getDate() - padding);
		maxDate.setDate(maxDate.getDate() + padding);
		return {
			start: minDate,
			end: maxDate
		};
	}
    
	function generateDateArray(startDate, endDate) {
		const dates = [];
		const current = new Date(startDate);
		while (current <= endDate) {
			dates.push(new Date(current));
			current.setDate(current.getDate() + 1);
		}
		return dates;
	}
    
	function createGanttHeader(dates) {
		const header = document.createElement('div');
		header.className = 'gantt-header';
		//columna de etiqueta 
		const labelCol = document.createElement('div');
		labelCol.className = 'gantt-header-labels';
		labelCol.textContent = 'Tarea / Usuario';
		header.appendChild(labelCol);
		//columna de linea de tiempo
		const timeline = document.createElement('div');
		timeline.className = 'gantt-header-timeline';
		dates.forEach(date => {
			const cell = document.createElement('div');
			cell.className = 'gantt-header-cell';
			cell.style.minWidth = `${CONFIG.cellWidths[currentViewMode]}px`;
			const today = new Date();
			today.setHours(0, 0, 0, 0);
			if (date.getTime() === today.getTime()) {
				cell.classList.add('today');
			}
			if (date.getDay() === 0 || date.getDay() === 6) {
				cell.classList.add('weekend');
			}
			// Formato según modo de vista 
			if (currentViewMode === 'day') {
				cell.innerHTML = ` 
                    <span class="day-name">${getDayName(date)}</span> 
                    <span class="day-number">${date.getDate()}</span> 
                `;
			} else if (currentViewMode === 'week') {
				// Mostrar mes solo en el primer día o cambio de mes 
				const prevDate = new Date(date);
				prevDate.setDate(prevDate.getDate() - 1);
				const showMonth = date.getDate() === 1 || dates.indexOf(date) === 0;
				cell.innerHTML = ` 
                    <span class="day-name">${getDayNameShort(date)}</span> 
                    <span class="day-number">${date.getDate()}</span> 
                    ${showMonth ? `<span class="month-name">${getMonthNameShort(date)}</span>` : ''} 
                `;
			} else if (currentViewMode === 'month') {
				// Mostrar solo días significativos 
				if (date.getDate() === 1 || date.getDate() === 15) {
					cell.innerHTML = ` 
                        <span class="day-number">${date.getDate()}</span> 
                        <span class="month-name">${getMonthNameShort(date)}</span> 
                    `;
				} else {
					cell.innerHTML = `<span class="day-number">${date.getDate()}</span>`;
				}
			}
			timeline.appendChild(cell);
		});
		header.appendChild(timeline);
		return header;
	}

	function createGanttBody(tasks, dates) {
		const body = document.createElement('div');
		body.className = 'gantt-body';
		if (currentGroupBy === 'none') {
			// Sin agrupación - mostrar todas las tareas 
			tasks.forEach(task => {
				const row = createTaskRow(task, dates);
				body.appendChild(row);
			});
		} else if (currentGroupBy === 'user') {
			// Agrupar por usuario 
			const grouped = groupTasksByUser(tasks);
			Object.keys(grouped).forEach(userName => {
				const group = createTaskGroup(userName, grouped[userName], dates, 'user');
				body.appendChild(group);
			});
		} else if (currentGroupBy === 'status') {
			// Agrupar por estado 
			const grouped = groupTasksByStatus(tasks);
			const statusOrder = ['pendiente', 'en proceso', 'completado'];
			statusOrder.forEach(status => {
				if (grouped[status] && grouped[status].length > 0) {
					const group = createTaskGroup(status, grouped[status], dates, 'status');
					body.appendChild(group);
				}
			});
		}
		return body;
	}

	function groupTasksByUser(tasks) {
		const grouped = {};
		tasks.forEach(task => {
			const userName = task.participante || 'Sin asignar';
			if (!grouped[userName]) {
				grouped[userName] = [];
			}
			grouped[userName].push(task);
		});
		return grouped;
	}
    
	function groupTasksByStatus(tasks) {
		const grouped = {};
		tasks.forEach(task => {
			const status = task.estado || 'pendiente';
			if (!grouped[status]) {
				grouped[status] = [];
			}
			grouped[status].push(task);
		});
		return grouped;
	}
    
	function createTaskGroup(groupName, tasks, dates, groupType) {
		const group = document.createElement('div');
		group.className = 'gantt-group';
		// Header del grupo 
		const groupHeader = document.createElement('div');
		groupHeader.className = 'gantt-row gantt-group-header';
		const labelDiv = document.createElement('div');
		labelDiv.className = 'gantt-row-label';
		if (groupType === 'user') {
			labelDiv.innerHTML = ` 
                <i class="mdi mdi-account"></i> 
                <span>${escapeHtml(groupName)} (${tasks.length})</span> 
            `;
		} else if (groupType === 'status') {
			const statusInfo = getStatusInfo(groupName);
			labelDiv.innerHTML = ` 
                <i class="mdi ${statusInfo.icon}"></i> 
                <span>${statusInfo.text} (${tasks.length})</span> 
            `;
		}
		groupHeader.appendChild(labelDiv);
		// Timeline vacío para el header 
		const timelineDiv = document.createElement('div');
		timelineDiv.className = 'gantt-row-timeline';
		dates.forEach(date => {
			const cell = document.createElement('div');
			cell.className = 'gantt-cell';
			cell.style.minWidth = `${CONFIG.cellWidths[currentViewMode]}px`;
			timelineDiv.appendChild(cell);
		});
		groupHeader.appendChild(timelineDiv);
		group.appendChild(groupHeader);
		// Filas de tareas 
		tasks.forEach(task => {
			const row = createTaskRow(task, dates);
			group.appendChild(row);
		});
		return group;
	}
    
	function createTaskRow(task, dates) {
		const row = document.createElement('div');
		row.className = 'gantt-row';
		row.dataset.taskId = task.id_tarea;
		// Label de la tarea 
		const labelDiv = document.createElement('div');
		labelDiv.className = 'gantt-row-label';
		labelDiv.innerHTML = ` 
            <div class="gantt-row-label-content"> 
                <div class="gantt-row-label-name" title="${escapeHtml(task.nombre)}"> 
                    ${escapeHtml(task.nombre)} 
                </div> 
                ${currentGroupBy !== 'user' && task.participante ? ` 
                    <div class="gantt-row-label-assignee"> 
                        <i class="mdi mdi-account-outline"></i> ${escapeHtml(task.participante)} 
                    </div> 
                ` : ''} 
            </div> 
        `;
		row.appendChild(labelDiv);
		// Timeline con la barra de tarea 
		const timelineDiv = document.createElement('div');
		timelineDiv.className = 'gantt-row-timeline';
		// Crear celdas de fondo 
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		dates.forEach(date => {
			const cell = document.createElement('div');
			cell.className = 'gantt-cell';
			cell.style.minWidth = `${CONFIG.cellWidths[currentViewMode]}px`;
			if (date.getTime() === today.getTime()) {
				cell.classList.add('today');
			}
			if (date.getDay() === 0 || date.getDay() === 6) {
				cell.classList.add('weekend');
			}
			timelineDiv.appendChild(cell);
		});
		// Crear barra de la tarea 
		if (task.fecha_cumplimiento) {
			const bar = createTaskBar(task, dates);
			if (bar) {
				timelineDiv.appendChild(bar);
			}
		}
		row.appendChild(timelineDiv);
		return row;
	}
    
	function createTaskBar(task, dates) {
		const taskDate = parseDateString(task.fecha_cumplimiento);
		if (!taskDate) return null;
		const startDate = dates[0];
		const cellWidth = CONFIG.cellWidths[currentViewMode];
		// Calcular posición 
		const daysDiff = Math.floor((taskDate - startDate) / (1000 * 60 * 60 * 24));
		// Verificar que está dentro del rango visible 
		if (daysDiff < 0 || daysDiff >= dates.length) return null;
		// Calcular posición left 
		const left = daysDiff * cellWidth + (cellWidth / 2) - 50; // Centrar la barra en el día 
		// Determinar estado y si está vencido 
		let status = task.estado || 'pendiente';
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		const isOverdue = taskDate < today && status !== 'completado';
		if (isOverdue) {
			status = 'vencido';
		}
		// Crear elemento de la barra 
		const bar = document.createElement('div');
		bar.className = `gantt-bar status-${status.replace(' ', '-')}`;
		bar.style.left = `${Math.max(0, left)}px`;
		bar.style.width = '100px'; // Ancho fijo para la barra 
		bar.dataset.taskId = task.id_tarea;
		bar.innerHTML = `<span class="gantt-bar-text">${escapeHtml(task.nombre)}</span>`;
		// Event listeners para tooltip y click 
		bar.addEventListener('mouseenter', (e) => showTooltip(e, task));
		bar.addEventListener('mousemove', (e) => moveTooltip(e));
		bar.addEventListener('mouseleave', hideTooltip);
		bar.addEventListener('click', () => showTaskDetail(task));
		return bar;
	}
    
	function addTodayLine(dates) {
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		const startDate = dates[0];
		const daysDiff = Math.floor((today - startDate) / (1000 * 60 * 60 * 24));
		if (daysDiff < 0 || daysDiff >= dates.length) return;
		const cellWidth = CONFIG.cellWidths[currentViewMode];
		const left = (daysDiff * cellWidth) + (cellWidth / 2) + CONFIG.labelWidth;
		const todayLine = document.createElement('div');
		todayLine.className = 'gantt-today-line';
		todayLine.style.left = `${left}px`;
		todayLine.id = 'ganttTodayLine';
		ganttChart.appendChild(todayLine);
	}
    
	function scrollToToday() {
		const todayLine = document.getElementById('ganttTodayLine');
		if (todayLine && ganttWrapper) {
			const lineLeft = parseInt(todayLine.style.left);
			const wrapperWidth = ganttWrapper.offsetWidth;
			const scrollLeft = lineLeft - (wrapperWidth / 2);
			ganttWrapper.scrollTo({
				left: Math.max(0, scrollLeft),
				behavior: 'smooth'
			});
		}
	}
    
	function createTooltipElement() {
		tooltipElement = document.createElement('div');
		tooltipElement.className = 'gantt-tooltip';
		tooltipElement.style.display = 'none';
		document.body.appendChild(tooltipElement);
	}
    
	function showTooltip(event, task) {
		const statusInfo = getStatusInfo(task.estado);
		const dateDisplay = task.fecha_cumplimiento ?
			formatDateDisplay(task.fecha_cumplimiento) : 'Sin fecha';
		const assignee = task.participante || 'Sin asignar';
		// Verificar si está vencido 
		let statusText = statusInfo.text;
		if (task.fecha_cumplimiento && task.estado !== 'completado') {
			const taskDate = parseDateString(task.fecha_cumplimiento);
			const today = new Date();
			today.setHours(0, 0, 0, 0);
			if (taskDate < today) {
				statusText = 'Vencido';
			}
		}
		tooltipElement.innerHTML = ` 
            <div class="gantt-tooltip-title">${escapeHtml(task.nombre)}</div> 
            <div class="gantt-tooltip-row"> 
                <span class="gantt-tooltip-label">Fecha:</span> 
                <span class="gantt-tooltip-value">${dateDisplay}</span> 
            </div> 
            <div class="gantt-tooltip-row"> 
                <span class="gantt-tooltip-label">Estado:</span> 
                <span class="gantt-tooltip-value">${statusText}</span> 
            </div> 
            <div class="gantt-tooltip-row"> 
                <span class="gantt-tooltip-label">Asignado:</span> 
                <span class="gantt-tooltip-value">${escapeHtml(assignee)}</span> 
            </div> 
        `;
		tooltipElement.style.display = 'block';
		moveTooltip(event);
	}
    
	function moveTooltip(event) {
		const x = event.clientX + 10;
		const y = event.clientY - tooltipElement.offsetHeight - 10;
		tooltipElement.style.left = `${x}px`;
		tooltipElement.style.top = `${Math.max(10, y)}px`;
	}
    
	function hideTooltip() {
		tooltipElement.style.display = 'none';
	}
    
	function showTaskDetail(task) {
		document.getElementById('modalTaskName').textContent = task.nombre;
		document.getElementById('modalTaskDescription').textContent = task.descripcion || 'Sin descripción';
		document.getElementById('modalTaskDate').textContent = task.fecha_cumplimiento ?
			formatDateDisplay(task.fecha_cumplimiento) : 'Sin fecha';
		document.getElementById('modalTaskAssignee').textContent = task.participante || 'Sin asignar';
		document.getElementById('modalTaskProject').textContent = currentProjectData ?
			currentProjectData.nombre : '-';
		// Estado con badge 
		const statusInfo = getStatusInfo(task.estado);
		const statusBadge = document.getElementById('modalTaskStatus');
		statusBadge.textContent = statusInfo.text;
		statusBadge.className = `badge ${statusInfo.badgeClass}`;
		// Link para editar 
		document.getElementById('modalEditTaskBtn').href = `../revisarTareas/?task_id=${task.id_tarea}`;
		// Mostrar modal 
		const modal = new bootstrap.Modal(document.getElementById('taskDetailModal'));
		modal.show();
	}
    
	function getStatusInfo(status) {
		const statusMap = {
			'pendiente': {
				text: 'Pendiente',
				icon: 'mdi-clock-outline',
				badgeClass: 'bg-warning text-dark'
			},
			'en proceso': {
				text: 'En Proceso',
				icon: 'mdi-progress-clock',
				badgeClass: 'bg-info'
			},
			'en-proceso': {
				text: 'En Proceso',
				icon: 'mdi-progress-clock',
				badgeClass: 'bg-info'
			},
			'completado': {
				text: 'Completado',
				icon: 'mdi-check-circle-outline',
				badgeClass: 'bg-success'
			},
			'vencido': {
				text: 'Vencido',
				icon: 'mdi-alert-circle-outline',
				badgeClass: 'bg-danger'
			}
		};
		return statusMap[status] || statusMap['pendiente'];
	}
    
	function parseDateString(dateString) {
		if (!dateString) return null;
		const parts = dateString.split('-');
		if (parts.length !== 3) return null;
		const year = parseInt(parts[0], 10);
		const month = parseInt(parts[1], 10) - 1;
		const day = parseInt(parts[2], 10);
		return new Date(year, month, day);
	}
    
	function formatDateDisplay(dateString) {
		const date = parseDateString(dateString);
		if (!date) return 'Sin fecha';
		return date.toLocaleDateString('es-MX', {
			day: '2-digit',
			month: 'short',
			year: 'numeric'
		});
	}
    
	function getDayName(date) {
		return date.toLocaleDateString('es-MX', {
			weekday: 'short'
		});
	}
    
	function getDayNameShort(date) {
		const days = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];
		return days[date.getDay()];
	}
    
	function getMonthNameShort(date) {
		return date.toLocaleDateString('es-MX', {
			month: 'short'
		});
	}
    
	function escapeHtml(text) {
		if (!text) return '';
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, m => map[m]);
	}
    
	function showLoading() {
		ganttDefaultMessage.style.display = 'none';
		ganttNoTasks.style.display = 'none';
		ganttWrapper.style.display = 'none';
		ganttLoading.style.display = 'block';
		projectInfoSummary.style.display = 'none';
	}
    
	function hideLoading() {
		ganttLoading.style.display = 'none';
	}
    
	function showDefaultState() {
		ganttLoading.style.display = 'none';
		ganttNoTasks.style.display = 'none';
		ganttWrapper.style.display = 'none';
		ganttDefaultMessage.style.display = 'block';
		projectInfoSummary.style.display = 'none';
		currentTasks = [];
	}
    
	function showNoTasksState() {
		ganttLoading.style.display = 'none';
		ganttDefaultMessage.style.display = 'none';
		ganttWrapper.style.display = 'none';
		ganttNoTasks.style.display = 'block';
		projectInfoSummary.style.display = 'none';
		currentTasks = [];
	}
    
	function showGanttChart() {
		ganttLoading.style.display = 'none';
		ganttDefaultMessage.style.display = 'none';
		ganttNoTasks.style.display = 'none';
		ganttWrapper.style.display = 'block';
	}
    
	function showAlert(message, type) {
		const alertContainer = document.getElementById('alertContainer');
		const alertDiv = document.createElement('div');
		alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
		alertDiv.setAttribute('role', 'alert');
		alertDiv.innerHTML = ` 
            ${message} 
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> 
        `;
		alertContainer.innerHTML = '';
		alertContainer.appendChild(alertDiv);
		setTimeout(() => {
			if (alertDiv.parentNode) {
				alertDiv.remove();
			}
		}, 5000);
	}
});