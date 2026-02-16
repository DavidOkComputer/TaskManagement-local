/*manager_dashboard_overview.js Dashboard principal para gerentes*/

//configuracion y constantes
const ManagerDashboardConfig = {
	API_ENDPOINTS: {
		GET_PROJECTS: '../php/manager_get_projects.php',
		GET_DASHBOARD_STATS: '../php/manager_get_dashboard_stats.php',
		GET_OBJECTIVES_DASHBOARD: '../php/manager_get_objectives_dashboard.php',
		GET_TOP_EMPLOYEES: '../php/manager_get_top_employees_progress.php',
		GET_TOP_PROJECTS: '../php/manager_get_top_projects_progress.php'
	},
	COLORS: {
		delay: '#dc3545',
		notStarted: '#ffc107',
		completed: '#009b4a',
		onGoing: '#495057'
	},
	REFRESH_INTERVAL: 60000 // 60 segundos 
};
const STATUS_MAP = {
	'pendiente': 'Pendiente',
	'en proceso': 'En Proceso',
	'completado': 'Completado',
	'vencido': 'Vencido'
};

//variables globales
let allProjectsData = [];
let allObjectivesData = [];
let responsibleChartInstance = null;
let objectivesChartInstance = null;
let doughnutChartInstance = null;
let autoRefreshInterval = null;
let isAutoRefreshActive = true;

document.addEventListener('DOMContentLoaded', function() {
	initializeDashboard();
});

function initializeDashboard() {
	initializeCharts();
	loadDashboardStats();
	loadProjectsData();
	loadObjectivesData();
	setupFilterListeners();
	startAutoRefresh();
	setupVisibilityDetection();
}

function initializeCharts() {
	initResponsibleChart();
	initObjectivesChart();
	initDoughnutChart();
}

function initResponsibleChart() {
	const ctx = document.getElementById('responsibleBarChart');
	if (!ctx) return;
	responsibleChartInstance = new Chart(ctx.getContext('2d'), {
		type: 'horizontalBar',
		data: {
			labels: [],
			datasets: [{
				label: 'Progreso %',
				data: [],
				backgroundColor: ManagerDashboardConfig.COLORS.completed,
				borderRadius: 4,
				barThickness: 18
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			legend: {
				display: false
			},
			scales: {
				xAxes: [{
					ticks: {
						beginAtZero: true,
						max: 100,
						fontSize: 10
					},
					gridLines: {
						color: '#e9e9e9'
					}
				}],
				yAxes: [{
					ticks: {
						fontSize: 10
					},
					gridLines: {
						display: false
					}
				}]
			},
			tooltips: {
				backgroundColor: '#000000',
				titleFontSize: 11,
				bodyFontSize: 11,
				callbacks: {
					label: function(tooltipItem) {
						return tooltipItem.value + '% completado';
					}
				}
			}
		}
	});
}

function initObjectivesChart() {
	const ctx = document.getElementById('objectivesBarChart');
	if (!ctx) return;
	objectivesChartInstance = new Chart(ctx.getContext('2d'), {
		type: 'horizontalBar',
		data: {
			labels: [],
			datasets: [{
				label: 'Progreso %',
				data: [],
				backgroundColor: [],
				borderRadius: 4,
				barThickness: 16
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			legend: {
				display: false
			},
			scales: {
				xAxes: [{
					ticks: {
						beginAtZero: true,
						max: 100,
						fontSize: 10
					},
					gridLines: {
						color: '#e9e9e9'
					}
				}],
				yAxes: [{
					ticks: {
						fontSize: 10
					},
					gridLines: {
						display: false
					}
				}]
			},
			tooltips: {
				backgroundColor: '#000000',
				titleFontSize: 11,
				bodyFontSize: 11,
				callbacks: {
					label: function(tooltipItem) {
						return tooltipItem.value + '% completado';
					}
				}
			}
		}
	});
}

function initDoughnutChart() {
	const ctx = document.getElementById('doughnutChart');
	if (!ctx) return;
	doughnutChartInstance = new Chart(ctx.getContext('2d'), {
		type: 'doughnut',
		data: {
			labels: ['Pendientes', 'Completados', 'Vencidos', 'En Proceso'],
			datasets: [{
				data: [0, 0, 0, 0],
				backgroundColor: [
					ManagerDashboardConfig.COLORS.notStarted,
					ManagerDashboardConfig.COLORS.completed,
					ManagerDashboardConfig.COLORS.delay,
					ManagerDashboardConfig.COLORS.onGoing
				],
				borderColor: '#ffffff',
				borderWidth: 2
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			cutoutPercentage: 55,
			legend: {
				display: false
			},
			tooltips: {
				backgroundColor: '#000000',
				titleFontSize: 11,
				bodyFontSize: 11,
				callbacks: {
					label: function(tooltipItem, data) {
						const dataset = data.datasets[tooltipItem.datasetIndex];
						const total = dataset.data.reduce((a, b) => a + b, 0);
						const value = dataset.data[tooltipItem.index];
						const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
						return data.labels[tooltipItem.index] + ': ' + value + ' (' + percentage + '%)';
					}
				}
			}
		}
	});
	// Guardar referencia global para ppe_chart_click_manager.js 
	window.doughnutChart = doughnutChartInstance;
	updateDoughnutLegend();
}

function loadDashboardStats() {
	fetch(ManagerDashboardConfig.API_ENDPOINTS.GET_DASHBOARD_STATS).then(response => response.json()).then(data => {
		if (data.success && data.stats) {
			updateStatsDisplay(data.stats);
			updateTotalProgressCircle(data.stats);
		}
	}).catch(error => {
		console.error('Error cargando estadísticas:', error);
	});
}

function loadProjectsData() {
	fetch(ManagerDashboardConfig.API_ENDPOINTS.GET_PROJECTS).then(response => response.json()).then(data => {
		if (data.success && data.proyectos) {
			allProjectsData = data.proyectos;
			// Actualizar tabla de tareas (proyectos) 
			updateProjectsTable(allProjectsData);
			// Actualizar gráfico de dona 
			updateDoughnutFromProjects(allProjectsData);
			// Actualizar cajas de estado 
			updateStatusBoxesFromProjects(allProjectsData);
			// Poblar filtros 
			populateFilterDropdowns(allProjectsData);
			// Actualizar gráfico de responsables 
			updateResponsibleChart(allProjectsData);
		}
	}).catch(error => {
		console.error('Error cargando proyectos:', error);
		showTableError('proyectosTableBody', 'Error al cargar proyectos');
	});
}

function loadObjectivesData() {
	fetch(ManagerDashboardConfig.API_ENDPOINTS.GET_OBJECTIVES_DASHBOARD).then(response => response.json()).then(data => {
		if (data.success && data.objetivos) {
			allObjectivesData = data.objetivos;
			// Actualizar tabla de objetivos/proyectos 
			updateObjectivesTable(allObjectivesData);
			// Actualizar gráfico de progreso de proyectos 
			updateObjectivesChart(allObjectivesData);
		}
	}).catch(error => {
		console.error('Error cargando objetivos:', error);
		showTableError('objectivesTableBody', 'Error al cargar proyectos');
	});
}

function updateStatsDisplay(stats) {
	// Total Proyectos 
	setText('statTotalProyectos', stats.total_proyectos || 0);
	// % Proyectos Vencidos 
	setText('statPctVencidos', Math.round(parseFloat(stats.porcentaje_vencidos) || 0) + '%');
	// Total Objetivos 
	setText('statTotalObjetivos', stats.total_objetivos || 0);
	// % Objetivos Completados 
	setText('statPctObjetivos', Math.round(parseFloat(stats.porcentaje_objetivos) || 0) + '%');
	// Objetivos Completados 
	setText('statObjetivosCompletados', stats.objetivos_completados || 0);
	// Total Tareas 
	setText('statTotalTareas', stats.total_tareas || 0);
	// % Tareas Completadas 
	setText('statPctTareas', Math.round(parseFloat(stats.porcentaje_tareas) || 0) + '%');
	// Tareas Completadas 
	setText('statTareasCompletadas', stats.tareas_completadas || 0);
	// Tareas Pendientes 
	setText('statTareasPendientes', stats.tareas_pendientes || 0);
}

function updateTotalProgressCircle(stats) {
	const progressValue = Math.round(parseFloat(stats.porcentaje_tareas) || 0);
	const progressFill = document.querySelector('.db-progress-fill');
	const progressText = document.getElementById('totalProgressValue');
	if (progressFill) {
		const circumference = 2 * Math.PI * 34; // r=34 
		const offset = circumference - (progressValue / 100) * circumference;
		progressFill.style.strokeDashoffset = offset;
	}
	if (progressText) {
		progressText.textContent = progressValue + '%';
	}
}

function updateProjectsTable(projects) {
	const tbody = document.getElementById('proyectosTableBody');
	if (!tbody) return;
	if (!projects || projects.length === 0) {
		tbody.innerHTML = ` 
            <tr> 
                <td colspan="7" class="text-center" style="padding:30px;"> 
                    <i class="mdi mdi-folder-open" style="font-size:32px;color:#ccc;"></i> 
                    <p class="mt-2 mb-0" style="font-size:0.8rem;">No hay proyectos disponibles</p> 
                </td> 
            </tr>`;
		return;
	}
	let html = '';
	projects.forEach((project, index) => {
		const statusClass = getStatusClass(project.estado);
		const statusText = STATUS_MAP[project.estado?.toLowerCase()] || project.estado;
		const progreso = Math.round(parseFloat(project.progreso) || 0);
		const progressTier = getProgressTier(progreso);
		const responsable = project.participante || 'Grupo';
		html += ` 
            <tr style="cursor:pointer;" onclick="viewProjectDetails(${project.id_proyecto})"> 
                <td><span class="db-collapse-toggle"><i class="mdi mdi-chevron-right"></i></span></td> 
                <td><strong>${escapeHtml(truncateText(project.descripcion || project.nombre, 40))}</strong></td> 
                <td><span class="db-status-badge ${statusClass}">${statusText}</span></td> 
                <td>${formatDate(project.fecha_cumplimiento)}</td> 
                <td>${escapeHtml(truncateText(responsable, 20))}</td> 
                <td> 
                    <div class="db-progress-badge"> 
                        <span class="db-progress-text ${progressTier}">${progreso}%</span> 
                        <div class="db-progress-bar-mini"> 
                            <div class="db-progress-bar-mini-fill ${progressTier}" style="width:${progreso}%;"></div> 
                        </div> 
                    </div> 
                </td> 
            </tr>`;
	});
	tbody.innerHTML = html;
}

function updateObjectivesTable(objectives) {
	const tbody = document.getElementById('objectivesTableBody');
	if (!tbody) return;
	if (!objectives || objectives.length === 0) {
		tbody.innerHTML = ` 
            <tr> 
                <td colspan="4" class="text-center" style="padding:30px;"> 
                    <i class="mdi mdi-target" style="font-size:32px;color:#ccc;"></i> 
                    <p class="mt-2 mb-0" style="font-size:0.8rem;">No hay proyectos disponibles</p> 
                </td> 
            </tr>`;
		return;
	}
	let html = '';
	objectives.forEach((obj, index) => {
		const progreso = Math.round(parseFloat(obj.progreso) || 0);
		const progressTier = getProgressTier(progreso);
		const typeClass = obj.tipo === 'Global' ? 'type-global' : 'type-regional';
		const responsable = obj.participante || 'Grupo';
		html += ` 
            <tr style="cursor:pointer;" onclick="viewProjectDetails(${obj.id_proyecto})"> 
                <td><strong>${escapeHtml(truncateText(obj.nombre, 30))}</strong></td> 
                <td>${escapeHtml(truncateText(responsable, 15))}</td> 
                <td> 
                    <div class="db-progress-badge"> 
                        <span class="db-progress-text ${progressTier}">${progreso}%</span> 
                        <div class="db-progress-bar-mini">
                            <div class="db-progress-bar-mini-fill ${progressTier}" style="width:${progreso}%;"></div> 
                        </div> 
                    </div> 
                </td> 
            </tr>`;
	});
	tbody.innerHTML = html;
}

function updateDoughnutFromProjects(projects) {
	if (!doughnutChartInstance) return;
	const counts = {
		pendiente: 0,
		completado: 0,
		vencido: 0,
		'en proceso': 0
	};
	projects.forEach(p => {
		const estado = (p.estado || '').toLowerCase();
		if (counts.hasOwnProperty(estado)) {
			counts[estado]++;
		}
	});
	// Orden: Pendientes, Completados, Vencidos, En Proceso 
	doughnutChartInstance.data.datasets[0].data = [
		counts.pendiente,
		counts.completado,
		counts.vencido,
		counts['en proceso']
	];
	doughnutChartInstance.update();
	updateDoughnutLegend();
}

function updateDoughnutLegend() {
	const legendContainer = document.getElementById('doughnut-chart-legend');
	if (!legendContainer || !doughnutChartInstance) return;
	const data = doughnutChartInstance.data.datasets[0].data;
	const labels = doughnutChartInstance.data.labels;
	const colors = doughnutChartInstance.data.datasets[0].backgroundColor;
	const total = data.reduce((a, b) => a + b, 0);
	let html = '<div style="display:flex;flex-wrap:wrap;justify-content:center;gap:8px;">';
	labels.forEach((label, i) => {
		const pct = total > 0 ? ((data[i] / total) * 100).toFixed(0) : 0;
		html += `<span style="display:inline-flex;align-items:center;gap:4px;font-size:0.68rem;"> 
            <span style="width:10px;height:10px;background:${colors[i]};border-radius:2px;"></span> 
            ${label}: ${data[i]} 
        </span>`;
	});
	html += '</div>';
	legendContainer.innerHTML = html;
}

function updateStatusBoxesFromProjects(projects) {
	const counts = {
		completado: 0,
		pendiente: 0,
		vencido: 0
	};
	projects.forEach(p => {
		const estado = (p.estado || '').toLowerCase();
		if (estado === 'completado') counts.completado++;
		else if (estado === 'pendiente') counts.pendiente++;
		else if (estado === 'vencido') counts.vencido++;
	});
	setText('boxCompleted', counts.completado);
	setText('boxNotStarted', counts.pendiente);
	setText('boxDelay', counts.vencido);
}

function updateResponsibleChart(projects) {
	if (!responsibleChartInstance) return;
	// Agrupar por responsable 
	const responsibleMap = {};
	projects.forEach(p => {
		const responsable = p.participante || 'Grupo';
		if (!responsibleMap[responsable]) {
			responsibleMap[responsable] = {
				total: 0,
				sumProgress: 0
			};
		}
		responsibleMap[responsable].total++;
		responsibleMap[responsable].sumProgress += (parseFloat(p.progreso) || 0);
	});
	// Calcular promedio y ordenar 
	const responsibles = Object.entries(responsibleMap).map(([name, data]) => ({
		name: name,
		progress: Math.round(data.sumProgress / data.total)
	})).sort((a, b) => b.progress - a.progress).slice(0, 8); // Top 8 
	responsibleChartInstance.data.labels = responsibles.map(r => truncateText(r.name, 15));
	responsibleChartInstance.data.datasets[0].data = responsibles.map(r => r.progress);
	responsibleChartInstance.data.datasets[0].backgroundColor = responsibles.map(r => getProgressColor(r.progress));
	responsibleChartInstance.update();
}

function updateObjectivesChart(objectives) {
	if (!objectivesChartInstance) return;
	// Ordenar por progreso y tomar top 8 
	const sorted = [...objectives].sort((a, b) => (parseFloat(b.progreso) || 0) - (parseFloat(a.progreso) || 0)).slice(0, 8);
	objectivesChartInstance.data.labels = sorted.map(o => truncateText(o.nombre, 20));
	objectivesChartInstance.data.datasets[0].data = sorted.map(o => Math.round(parseFloat(o.progreso) || 0));
	objectivesChartInstance.data.datasets[0].backgroundColor = sorted.map(o => getProgressColor(Math.round(parseFloat(o.progreso) || 0)));
	objectivesChartInstance.update();
}

function setupFilterListeners() {
	const filterObjective = document.getElementById('filterObjective');
	const filterStatus = document.getElementById('filterStatus');
	const filterResponsible = document.getElementById('filterResponsible');
	if (filterObjective) filterObjective.addEventListener('change', applyFilters);
	if (filterStatus) filterStatus.addEventListener('change', applyFilters);
	if (filterResponsible) filterResponsible.addEventListener('change', applyFilters);
}

function populateFilterDropdowns(projects) {
	const filterObjective = document.getElementById('filterObjective');
	const filterResponsible = document.getElementById('filterResponsible');
	// Poblar proyectos 
	if (filterObjective) {
		const currentValue = filterObjective.value;
		filterObjective.innerHTML = '<option value="all">Todos</option>';
		const uniqueProjects = [...new Set(projects.map(p => p.nombre))];
		uniqueProjects.forEach(name => {
			const option = document.createElement('option');
			option.value = name;
			option.textContent = truncateText(name, 30);
			filterObjective.appendChild(option);
		});
		filterObjective.value = currentValue || 'all';
	}
	// Poblar responsables 
	if (filterResponsible) {
		const currentValue = filterResponsible.value;
		filterResponsible.innerHTML = '<option value="all">Todos</option>';
		const uniqueResponsibles = [...new Set(projects.map(p => p.participante || 'Grupo'))];
		uniqueResponsibles.forEach(name => {
			const option = document.createElement('option');
			option.value = name;
			option.textContent = truncateText(name, 25);
			filterResponsible.appendChild(option);
		});
		filterResponsible.value = currentValue || 'all';
	}
}

function applyFilters() {
	const filterObjective = document.getElementById('filterObjective')?.value || 'all';
	const filterStatus = document.getElementById('filterStatus')?.value || 'all';
	const filterResponsible = document.getElementById('filterResponsible')?.value || 'all';
	let filteredProjects = [...allProjectsData];
	let filteredObjectives = [...allObjectivesData];
	// Filtrar por proyecto/objetivo 
	if (filterObjective !== 'all') {
		filteredProjects = filteredProjects.filter(p => p.nombre === filterObjective);
		filteredObjectives = filteredObjectives.filter(o => o.nombre === filterObjective);
	}
	// Filtrar por estado 
	if (filterStatus !== 'all') {
		const statusLower = filterStatus.toLowerCase();
		filteredProjects = filteredProjects.filter(p => (p.estado || '').toLowerCase() === statusLower);
		filteredObjectives = filteredObjectives.filter(o => (o.estado || '').toLowerCase() === statusLower);
	}
	// Filtrar por responsable 
	if (filterResponsible !== 'all') {
		filteredProjects = filteredProjects.filter(p => (p.participante || 'Grupo') === filterResponsible);
		filteredObjectives = filteredObjectives.filter(o => (o.participante || 'Grupo') === filterResponsible);
	}
	// Actualizar UI 
	updateProjectsTable(filteredProjects);
	updateObjectivesTable(filteredObjectives);
	updateDoughnutFromProjects(filteredProjects);
	updateStatusBoxesFromProjects(filteredProjects);
	updateResponsibleChart(filteredProjects);
	updateObjectivesChart(filteredObjectives);
}

function startAutoRefresh() {
	stopAutoRefresh();
	autoRefreshInterval = setInterval(() => {
		if (isAutoRefreshActive) {
			refreshAllData();
		}
	}, ManagerDashboardConfig.REFRESH_INTERVAL);
}

function stopAutoRefresh() {
	if (autoRefreshInterval) {
		clearInterval(autoRefreshInterval);
		autoRefreshInterval = null;
	}
}

function refreshAllData() {
	loadDashboardStats();
	loadProjectsData();
	loadObjectivesData();
}

function setupVisibilityDetection() {
	document.addEventListener('visibilitychange', function() {
		if (document.hidden) {
			stopAutoRefresh();
		} else {
			if (isAutoRefreshActive) {
				refreshAllData();
				startAutoRefresh();
			}
		}
	});
}

function setText(elementId, value) {
	const el = document.getElementById(elementId);
	if (el) {
		el.textContent = value;
	}
}

function getStatusClass(status) {
	const statusLower = (status || '').toLowerCase();
	const classMap = {
		'completado': 'status-completed',
		'pendiente': 'status-notstarted',
		'vencido': 'status-delay',
		'en proceso': 'status-ongoing'
	};
	return classMap[statusLower] || 'status-notstarted';
}

function getProgressTier(progress) {
	const p = parseFloat(progress) || 0;
	if (p >= 75) return 'tier-great';
	if (p >= 50) return 'tier-good';
	if (p >= 25) return 'tier-medium';
	return 'tier-low';
}

function getProgressColor(progress) {
	const p = parseFloat(progress) || 0;
	if (p >= 75) return ManagerDashboardConfig.COLORS.completed;
	if (p >= 50) return '#28a745';
	if (p >= 25) return ManagerDashboardConfig.COLORS.notStarted;
	return ManagerDashboardConfig.COLORS.delay;
}

function formatDate(dateString) {
	if (!dateString) return '-';
	const parts = dateString.split('-');
	if (parts.length === 3) {
		const date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
		return date.toLocaleDateString('es-MX', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}
	const date = new Date(dateString);
	return date.toLocaleDateString('es-MX', {
		year: 'numeric',
		month: 'short',
		day: 'numeric'
	});
}

function truncateText(text, maxLength) {
	if (!text) return '-';
	return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
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

function showTableError(tableId, message) {
	const tbody = document.getElementById(tableId);
	if (tbody) {
		tbody.innerHTML = ` 
            <tr> 
                <td colspan="7" class="text-center text-danger" style="padding:30px;"> 
                    <i class="mdi mdi-alert-circle" style="font-size:32px;"></i> 
                    <p class="mt-2 mb-0">${message}</p> 
                </td> 
            </tr>`;
	}
}

window.viewProjectDetails = function(projectId) {
	// Esta función está definida en manager_project_details.js 
	if (typeof window.openProjectDetails === 'function') {
		window.openProjectDetails(projectId);
	} else {
		// Fallback si manager_project_details.js usa viewProjectDetails directamente 
		const modal = document.getElementById('projectDetailsModal');
		if (modal) {
			document.getElementById('projectDetailsLoading').style.display = 'block';
			document.getElementById('projectDetailsContent').style.display = 'none';
			const bsModal = new bootstrap.Modal(modal);
			bsModal.show();
			fetchProjectDetailsForModal(projectId);
		}
	}
};

function fetchProjectDetailsForModal(projectId) {
	fetch(`../php/get_project_details.php?id=${projectId}`).then(response => response.json()).then(data => {
		if (data.success && data.proyecto) {
			displayProjectDetailsInModal(data.proyecto);
		} else {
			showModalError(data.message || 'Error al cargar proyecto');
		}
	}).catch(error => {
		console.error('Error:', error);
		showModalError('Error de conexión');
	});
}

function displayProjectDetailsInModal(proyecto) {
	document.getElementById('projectDetailsLoading').style.display = 'none';
	document.getElementById('projectDetailsContent').style.display = 'block';
	// Título y descripción 
	document.getElementById('projectDetailTitle').textContent = proyecto.nombre;
	document.getElementById('detailProjectName').textContent = proyecto.nombre;
	document.getElementById('detailProjectDescription').textContent = proyecto.descripcion || 'Sin descripción';
	// Estado 
	const statusBadge = document.getElementById('detailProjectStatus');
	const statusText = STATUS_MAP[proyecto.estado?.toLowerCase()] || proyecto.estado;
	statusBadge.textContent = statusText;
	statusBadge.className = 'badge fs-6 badge-' + getStatusBadgeColor(proyecto.estado);
	// Tipo 
	const typeBadge = document.getElementById('detailProjectType');
	typeBadge.textContent = proyecto.tipo_proyecto?.nombre || (proyecto.tipo_proyecto?.id === 1 ? 'Grupal' : 'Individual');
	// Progreso 
	const progressBar = document.getElementById('detailProgressBar');
	const progreso = Math.round(parseFloat(proyecto.progreso) || 0);
	progressBar.style.width = progreso + '%';
	progressBar.textContent = progreso + '%';
	progressBar.className = 'progress-bar ' + getProgressBarColor(progreso);
	// Estadísticas 
	const stats = proyecto.estadisticas || {};
	document.getElementById('modalStatTotalTareas').textContent = stats.total_tareas || 0;
	document.getElementById('modalStatTareasCompletadas').textContent = stats.tareas_completadas || 0;
	document.getElementById('modalStatTareasEnProceso').textContent = stats.tareas_en_proceso || 0;
	document.getElementById('modalStatTareasVencidas').textContent = stats.tareas_vencidas || 0;
	// Información general 
	document.getElementById('detailDepartamento').textContent = proyecto.departamento?.nombre || '-';
	document.getElementById('detailCreador').textContent = proyecto.creador?.nombre || '-';
	document.getElementById('detailFechaCreacion').textContent = formatDate(proyecto.fecha_creacion);
	document.getElementById('detailFechaLimite').textContent = formatDate(proyecto.fecha_cumplimiento);
	// Participante (solo individual) 
	const participanteRow = document.getElementById('detailParticipanteRow');
	if (proyecto.tipo_proyecto?.id === 1) {
		participanteRow.style.display = 'none';
	} else {
		participanteRow.style.display = '';
		document.getElementById('detailParticipante').textContent = proyecto.participante?.nombre || 'Sin asignar';
	}
	// Usuarios asignados (solo grupal) 
	const usuariosSection = document.getElementById('detailUsuariosSection');
	if (proyecto.tipo_proyecto?.id === 1 && proyecto.usuarios_asignados?.length > 0) {
		usuariosSection.style.display = '';
		displayModalUsers(proyecto.usuarios_asignados);
	} else {
		usuariosSection.style.display = 'none';
	}
	// Tareas 
	displayModalTasks(proyecto.tareas || []);
	// Botón editar 
	const btnEdit = document.getElementById('btnEditProject');
	if (btnEdit) {
		btnEdit.onclick = function() {
			window.location.href = '../nuevoProyectoGerente/?edit=' + proyecto.id_proyecto;
		};
	}
}

function displayModalUsers(usuarios) {
	const tbody = document.getElementById('detailUsuariosTableBody');
	const countEl = document.getElementById('detailUsuariosCount');
	if (countEl) countEl.textContent = usuarios.length;
	if (!usuarios || usuarios.length === 0) {
		tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin usuarios asignados</td></tr>';
		return;
	}
	let html = '';
	usuarios.forEach(u => {
		const progreso = Math.round(parseFloat(u.progreso) || 0);
		const progressClass = getProgressBarColor(progreso);
		html += ` 
            <tr> 
                <td><strong>${escapeHtml(u.nombre_completo)}</strong></td> 
                <td>${u.num_empleado || '-'}</td> 
                <td><small>${escapeHtml(u.e_mail || '-')}</small></td> 
                <td><span class="badge bg-secondary">${u.tareas_completadas || 0}/${u.tareas_asignadas || 0}</span></td> 
                <td style="min-width:120px;"> 
                    <div class="progress" style="height:18px;"> 
                        <div class="progress-bar ${progressClass}" style="width:${progreso}%;">${progreso}%</div> 
                    </div> 
                </td> 
            </tr>`;
	});
	tbody.innerHTML = html;
}

function displayModalTasks(tareas) {
	const tbody = document.getElementById('detailTareasTableBody');
	const noTareasDiv = document.getElementById('detailNoTareas');
	if (!tareas || tareas.length === 0) {
		tbody.innerHTML = '';
		noTareasDiv.style.display = 'block';
		return;
	}
	noTareasDiv.style.display = 'none';
	let html = '';
	tareas.forEach(t => {
		const estadoClass = getStatusBadgeColor(t.estado);
		const estadoText = STATUS_MAP[t.estado?.toLowerCase()] || t.estado;
		html += ` 
            <tr> 
                <td> 
                    <strong>${escapeHtml(t.nombre)}</strong> 
                    ${t.descripcion ? '<small class="text-muted d-block">' + truncateText(t.descripcion, 50) + '</small>' : ''} 
                </td> 
                <td>${escapeHtml(t.asignado_a || 'Sin asignar')}</td> 
                <td>${formatDate(t.fecha_cumplimiento)}</td> 
                <td><span class="badge badge-${estadoClass}">${estadoText}</span></td> 
            </tr>`;
	});
	tbody.innerHTML = html;
}

function getStatusBadgeColor(status) {
	const statusLower = (status || '').toLowerCase();
	const colorMap = {
		'completado': 'success',
		'pendiente': 'warning',
		'vencido': 'danger',
		'en proceso': 'primary'
	};
	return colorMap[statusLower] || 'secondary';
}

function getProgressBarColor(progress) {
	const p = parseFloat(progress) || 0;
	if (p >= 70) return 'bg-success';
	if (p >= 40) return 'bg-warning';
	return 'bg-danger';
}

function showModalError(message) {
	const loading = document.getElementById('projectDetailsLoading');
	if (loading) {
		loading.innerHTML = ` 
            <div class="text-center py-5"> 
                <i class="mdi mdi-alert-circle-outline text-danger" style="font-size:3rem;"></i> 
                <p class="mt-3 text-danger">${message}</p> 
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> 
            </div>`;
	}
}
// Exponer funciones globales 
window.refreshAllData = refreshAllData;
window.loadDashboardStats = loadDashboardStats;
window.loadProjectsData = loadProjectsData;
window.loadObjectivesData = loadObjectivesData;