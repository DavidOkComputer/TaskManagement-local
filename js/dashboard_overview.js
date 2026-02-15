(function() {
	'use strict';
	const API = {
		PROJECTS: '../php/get_projects.php',
		STATS: '../php/get_dashboard_stats.php',
		OBJECTIVES: '../php/get_objectives_dashboard.php',
		TOP_EMPLOYEES: '../php/get_top_employees_progress.php',
		TOP_PROJECTS: '../php/get_top_projects_progress.php'
	};

	const COLORS = {
		delay: '#C62828',
		notStarted: '#F2C94C',
		completed: '#009b4a',
		onGoing: '#5bbfb5',
		onHold: '#90a4ae',
		barDark: '#2c4a62',
		barMedium: '#3d6d8e',
		barLight: '#5bbfb5'
	};
	
	const STATUS_MAP = {
	'pendiente': 'Not Started',
    'en proceso': 'On Going',
    'completado': 'Completed',
    'vencido': 'Delay',
    'en espera': 'On Hold',
	};

	let allProjects = [];
	let allObjectives = [];
	let autoRefreshInterval = null;
	let doughnutChartInstance = null;
	let responsibleChartInstance = null;
	let objectivesChartInstance = null;
	let currentProjectIdForUsers = null;
	
	//inicializacion
	document.addEventListener('DOMContentLoaded', function() {
		initDoughnutChart();
		initFilterListeners();
		loadAllData();
		startAutoRefresh();
	});

	function loadAllData() {
		loadDashboardStats();
		loadProjectsData();
		loadObjectivesData();
	}

	function startAutoRefresh() {
		if (autoRefreshInterval) clearInterval(autoRefreshInterval);
		autoRefreshInterval = setInterval(() => {
			loadAllData();
		}, 60000);
	}

	//ESTADISTICAS DE DASHBOARD resumen y cajas de estatus 
	function loadDashboardStats() {
		fetch(API.STATS).then(r => r.json()).then(data => {
			if (data.success && data.stats) {
				updateStatsRow(data.stats);
				updateStatusBoxes(data.stats);
				updateTotalProgress(data.stats);
			}
		}).catch(err => console.error('Error loading stats:', err));
	}

	function updateStatsRow(stats) {
		setText('#statTotalObjectives', stats.total_objetivos || 0);
		
		//calcular porcentajes de proyectos si la info esta disponible
		if (stats.porcentaje_global !== undefined) {
			setText('#statGlobalPct', stats.porcentaje_global + '%');
		}
		if (stats.porcentaje_regional !== undefined) {
			setText('#statRegionalPct', stats.porcentaje_regional + '%');
		}
		if (stats.porcentaje_local !== undefined) {
			setText('#statLocalPct', stats.porcentaje_local + '%');
		}
	}

	function updateStatusBoxes(stats) {
		//calcular todos los tareas desde proyectos o estadisticas
		if (stats.total_tareas !== undefined) {
			setText('#boxTotalTask', stats.total_tareas);
		}
		if (stats.tareas_completadas !== undefined) {
			setText('#boxCompleted', stats.tareas_completadas);
		}
		if (stats.tareas_en_proceso !== undefined) {
			setText('#boxOnGoing', stats.tareas_en_proceso);
		}
		if (stats.tareas_pendientes !== undefined) {
			setText('#boxNotStarted', stats.tareas_pendientes);
		}
		if (stats.tareas_en_espera !== undefined) {
			setText('#boxOnHold', stats.tareas_en_espera);
		}
		if (stats.tareas_retrasadas !== undefined) {
			setText('#boxDelay', stats.tareas_retrasadas);
		}
	}

	function updateTotalProgress(stats) {
		const pct = parseFloat(stats.porcentaje_tareas) || 0;
		const circle = document.querySelector('.db-progress-fill');
		const label = document.getElementById('totalProgressValue');
		if (circle) {
			const circumference = 2 * Math.PI * 34; // r=34 
			const offset = circumference - (pct / 100) * circumference;
			circle.setAttribute('stroke-dasharray', circumference.toFixed(2));
			circle.setAttribute('stroke-dashoffset', offset.toFixed(2));
		}
		if (label) {
			label.textContent = Math.round(pct) + '%';
		}
	}

	//informacion de los datos, tabla de detalles de tarea, grafica de dona, cajas de estado
	function loadProjectsData() {
		fetch(API.PROJECTS).then(r => r.json()).then(data => {
			if (data.success && data.proyectos) {
				allProjects = data.proyectos;
				displayTaskDetailsTable(allProjects);
				updateDoughnutFromProjects(allProjects);
				updateStatusBoxesFromProjects(allProjects);
				populateFilterDropdowns(allProjects);
				updateResponsibleChart(allProjects);
			}
		}).catch(err => console.error('Error loading projects:', err));
	}

	function displayTaskDetailsTable(projects) {
		const tbody = document.getElementById('proyectosTableBody');
		if (!tbody) return;
		tbody.innerHTML = '';
		if (!projects || projects.length === 0) {
			tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:30px;"> 
                <i class="mdi mdi-folder-open" style="font-size:32px;color:#ccc;"></i> 
                <p style="margin-top:8px;font-size:0.8rem;color:#999;">No hay tareas registradas</p> 
            </td></tr>`;
			return;
		}
		projects.forEach(project => {
			const row = document.createElement('tr');
			row.setAttribute('data-project-id', project.id_proyecto);
			row.style.cursor = 'pointer';
			const statusEN = mapStatus(project.estado);
			const statusClass = getStatusCSSClass(statusEN);
			const progressVal = parseInt(project.progreso) || 0;
			const tier = getTier(progressVal);
			const type = project.tipo_objetivo || project.tipo || 'Global Objectives';
			const typeClass = type.toLowerCase().includes('regional') ? 'type-regional' : type.toLowerCase().includes('local') ? 'type-local' : 'type-global';
			row.innerHTML = ` 
                <td><span class="db-collapse-toggle"><i class="mdi mdi-plus-box-outline"></i></span></td> 
                <td>${escapeHtml(project.descripcion || project.nombre || '-')}</td> 
                <td><span class="db-status-badge ${statusClass}">${statusEN}</span></td> 
                <td style="white-space:nowrap;">${formatDate(project.fecha_cumplimiento)}</td> 
                <td>${escapeHtml(project.participante || '-')}</td> 
                <td> 
                    <div class="db-progress-badge"> 
                        <span class="db-progress-text ${tier}">${progressVal} %</span> 
                        <div class="db-progress-bar-mini"> 
                            <div class="db-progress-bar-mini-fill ${tier}" style="width:${progressVal}%"></div> 
                        </div> 
                    </div> 
                </td> 
            `;
			row.addEventListener('click', function(e) {
				if (e.target.closest('button')) return;
				if (typeof window.viewProjectDetails === 'function') {
					window.viewProjectDetails(project.id_proyecto);
				}
			});
			tbody.appendChild(row);
		});
	}

	// 3.informacion de proyectos, tabla proyecto y grafica de barras 
	function loadObjectivesData() {
		fetch(API.OBJECTIVES).then(r => {
			if (!r.ok) throw new Error('Endpoint not available');
			return r.json();
		}).then(data => {
			if (data.success && data.objetivos) {
				allObjectives = data.objetivos;
				displayObjectivesTable(allObjectives);
				updateObjectivesChart(allObjectives);
				updateStatsFromObjectives(allObjectives);
			}
		}).catch(() => {
			//sino hay infop, obtener de otra tabla
			console.info('get_objectives_dashboard.php not available, deriving from projects');
			deriveObjectivesFromProjects();
		});
	}

	function deriveObjectivesFromProjects() {
		// agrupar proyectos por responsable 
		if (!allProjects || allProjects.length === 0) {
			setTimeout(deriveObjectivesFromProjects, 1000);
			return;
		}
		// usar proyectos como objetivos
		const objectives = allProjects.map(p => ({
			nombre: p.nombre || p.descripcion || 'Sin nombre',
			responsable: p.participante || 'Sin asignar',
			progreso: parseInt(p.progreso) || 0,
			tipo: p.tipo_objetivo || 'Global Objectives',
			total_tareas: p.total_tareas || 1,
			tareas_completadas: p.tareas_completadas || 0
		}));
		allObjectives = objectives;
		displayObjectivesTable(objectives);
		updateObjectivesChart(objectives);
		updateStatsFromObjectives(objectives);
	}

	function displayObjectivesTable(objectives) {
		const tbody = document.getElementById('objectivesTableBody');
		if (!tbody) return;
		tbody.innerHTML = '';
		if (!objectives || objectives.length === 0) {
			tbody.innerHTML = `<tr><td colspan="4" class="text-center" style="padding:30px;"> 
                <p style="font-size:0.8rem;color:#999;">No hay objetivos registrados</p> 
            </td></tr>`;
			return;
		}
		objectives.forEach(obj => {
			const row = document.createElement('tr');
			const progressVal = parseInt(obj.progreso) || 0;
			const tier = getTier(progressVal);
			row.innerHTML = ` 
                <td><span class="db-collapse-toggle"><i class="mdi mdi-plus-box-outline"></i></span></td> 
                <td style="max-width:200px;">${escapeHtml(truncateText(obj.nombre, 80))}</td> 
                <td>${escapeHtml(obj.responsable || '-')}</td> 
                <td> 
                    <div class="db-progress-badge"> 
                        <span class="db-progress-text ${tier}">${progressVal} %</span> 
                        <div class="db-progress-bar-mini"> 
                            <div class="db-progress-bar-mini-fill ${tier}" style="width:${progressVal}%"></div> 
                        </div> 
                    </div> 
                </td> 
            `;
			tbody.appendChild(row);
		});
	}

	function updateStatsFromObjectives(objectives) {
		if (!objectives || objectives.length === 0) return;
		setText('#statTotalObjectives', objectives.length);

		//calcular porcentages
		const globals = objectives.filter(o => (o.tipo || '').toLowerCase().includes('global'));
		const regionals = objectives.filter(o => (o.tipo || '').toLowerCase().includes('regional'));
		const locals = objectives.filter(o => (o.tipo || '').toLowerCase().includes('local'));
		if (globals.length > 0) {
			const avgGlobal = Math.round(globals.reduce((s, o) => s + (parseInt(o.progreso) || 0), 0) / globals.length);
			setText('#statGlobalPct', avgGlobal + '%');
		}
		if (regionals.length > 0) {
			const avgRegional = Math.round(regionals.reduce((s, o) => s + (parseInt(o.progreso) || 0), 0) / regionals.length);
			setText('#statRegionalPct', avgRegional + '%');
		}
		if (locals.length > 0) {
			const avgLocal = Math.round(locals.reduce((s, o) => s + (parseInt(o.progreso) || 0), 0) / locals.length);
			setText('#statLocalPct', avgLocal + '%');
		} else {
			setText('#statLocalPct', '--');
		}
		//calcular el total del progreso de proyectos
		const avgTotal = Math.round(objectives.reduce((s, o) => s + (parseInt(o.progreso) || 0), 0) / objectives.length);
		updateTotalProgressDirect(avgTotal);
	}

	function updateTotalProgressDirect(pct) {
		const circle = document.querySelector('.db-progress-fill');
		const label = document.getElementById('totalProgressValue');
		if (circle) {
			const circumference = 2 * Math.PI * 34;
			const offset = circumference - (pct / 100) * circumference;
			circle.setAttribute('stroke-dasharray', circumference.toFixed(2));
			circle.setAttribute('stroke-dashoffset', offset.toFixed(2));
		}
		if (label) label.textContent = Math.round(pct) + '%';
	}

	// 4. grafica de dona de proyectos por estado
	function initDoughnutChart() {
		const canvas = document.getElementById('doughnutChart');
		if (!canvas) return;
		const ctx = canvas.getContext('2d');
		doughnutChartInstance = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['Vencido', 'Pendiente', 'Completado', 'En proceso'],
				datasets: [{
					data: [0, 0, 0, 0],
					backgroundColor: [COLORS.delay, COLORS.notStarted, COLORS.completed, COLORS.onGoing],
					borderColor: ['#fff', '#fff', '#fff', '#fff'],
					borderWidth: 2
				}]
			},
			options: {
				cutoutPercentage: 60,
				responsive: true,
				maintainAspectRatio: true,
				animation: {
					animateRotate: true,
					duration: 800
				},
				legend: false,
				legendCallback: function(chart) {
					let html = '<div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;">';
					const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
					html += '<div style="font-size:0.68rem;font-weight:600;color:#6b7b8d;margin-bottom:2px;">Status</div>';
					for (let i = 0; i < chart.data.datasets[0].data.length; i++) {
						const val = chart.data.datasets[0].data[i];
						const pct = total > 0 ? ((val / total) * 100).toFixed(2) : '0.00';
						html += `<div style="display:flex;align-items:center;gap:6px;font-size:0.7rem;"> 
                            <span style="width:8px;height:8px;border-radius:50%;background:${chart.data.datasets[0].backgroundColor[i]};display:inline-block;"></span> 
                            <span style="color:#4a5568;">${chart.data.labels[i]}</span> 
                        </div>`;
					}
					html += '</div>';
					return html;
				},
				tooltips: {
					backgroundColor: '#1a2a3a',
					titleFontSize: 12,
					bodyFontSize: 11,
					callbacks: {
						label: function(tooltipItem, data) {
							const val = data.datasets[0].data[tooltipItem.index];
							const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
							const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
							return ` ${data.labels[tooltipItem.index]}: ${val} (${pct}%)`;
						}
					}
				}
			}
		});

		//almacenar globalmente para usar en el script ppe_chart_click.js
		window.doughnutChart = doughnutChartInstance;
		const legendEl = document.getElementById('doughnut-chart-legend');
		if (legendEl) {
			legendEl.innerHTML = doughnutChartInstance.generateLegend();
		}
	}

	function updateDoughnutFromProjects(projects) {
		if (!doughnutChartInstance) return;
		const counts = {
			'Delay': 0,
			'Not Started': 0,
			'Completed': 0,
			'On Going': 0
		};
		projects.forEach(p => {
			const statusEN = mapStatus(p.estado);
			if (counts.hasOwnProperty(statusEN)) {
				counts[statusEN]++;
			} else {
				//estado desconocido-contar como atrasado
				counts['Delay']++;
			}
		});
		doughnutChartInstance.data.datasets[0].data = [
			counts['Delay'],
			counts['Not Started'],
			counts['Completed'],
			counts['On Going']
		];
		doughnutChartInstance.update();
		const legendEl = document.getElementById('doughnut-chart-legend');
		if (legendEl) {
			legendEl.innerHTML = doughnutChartInstance.generateLegend();
		}
	}
	// cajas de estados
	function updateStatusBoxesFromProjects(projects) {
		const counts = {
			total: projects.length,
			completed: 0,
			ongoing: 0,
			notStarted: 0,
			onHold: 0,
			delay: 0
		};
		projects.forEach(p => {
			const s = mapStatus(p.estado);
			if (s === 'Completed') counts.completed++;
			else if (s === 'On Going') counts.ongoing++;
			else if (s === 'Not Started') counts.notStarted++;
			else if (s === 'On Hold') counts.onHold++;
			else if (s === 'Delay') counts.delay++;
		});
		//actualizar solamente si no esta ya puesto por endpoints
		setTextIfEmpty('#boxTotalTask', counts.total);
		setTextIfEmpty('#boxCompleted', counts.completed);
		setTextIfEmpty('#boxOnGoing', counts.ongoing);
		setTextIfEmpty('#boxNotStarted', counts.notStarted);
		setTextIfEmpty('#boxOnHold', counts.onHold);
		setTextIfEmpty('#boxDelay', counts.delay);
	}

	// 6. grafica de barra de responsable
	function updateResponsibleChart(projects) {
		//agrupar por participantes o responsables
		const byResponsible = {};
		projects.forEach(p => {
			const resp = p.participante || 'Sin asignar';
			if (!byResponsible[resp]) {
				byResponsible[resp] = {
					total: 0,
					sumProgress: 0
				};
			}
			byResponsible[resp].total++;
			byResponsible[resp].sumProgress += (parseInt(p.progreso) || 0);
		});
		const labels = [];
		const values = [];
		Object.keys(byResponsible).forEach(name => {
			labels.push(name);
			values.push(Math.round(byResponsible[name].sumProgress / byResponsible[name].total));
		});
		const canvas = document.getElementById('responsibleBarChart');
		if (!canvas) return;
		if (responsibleChartInstance) {
			responsibleChartInstance.destroy();
		}
		responsibleChartInstance = new Chart(canvas.getContext('2d'), {
			type: 'horizontalBar',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: COLORS.barDark,
					borderRadius: 3,
					barThickness: 22,
					maxBarThickness: 28
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
							fontSize: 10,
							callback: v => v + '%'
						},
						gridLines: {
							color: '#edf0f4',
							zeroLineColor: '#edf0f4'
						}
					}],
					yAxes: [{
						ticks: {
							fontSize: 10,
							fontColor: '#3a4a5c'
						},
						gridLines: {
							display: false
						}
					}]
				},
				tooltips: {
					backgroundColor: '#1a2a3a',
					callbacks: {
						label: (t) => ` ${t.value}%`
					}
				},
				plugins: {
					datalabels: false
				}
			}
		});
	}

	// 7. grafica de progreso de proyectos
	function updateObjectivesChart(objectives) {
		if (!objectives || objectives.length === 0) return;
		const canvas = document.getElementById('objectivesBarChart');
		if (!canvas) return;
		// filtrar por progreso de descendente y tomar items
		const sorted = [...objectives].sort((a, b) => (parseInt(b.progreso) || 0) - (parseInt(a.progreso) || 0));
		const top = sorted.slice(0, 8);
		const labels = top.map(o => truncateText(o.nombre, 25));
		const values = top.map(o => parseInt(o.progreso) || 0);
		const bgColors = values.map(v => {
			if (v >= 75) return COLORS.completed;
			if (v >= 50) return COLORS.barDark;
			if (v >= 25) return COLORS.barMedium;
			return COLORS.barDark;
		});
		if (objectivesChartInstance) {
			objectivesChartInstance.destroy();
		}
		objectivesChartInstance = new Chart(canvas.getContext('2d'), {
			type: 'horizontalBar',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: bgColors,
					borderRadius: 3,
					barThickness: 18,
					maxBarThickness: 24
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
							fontSize: 10,
							callback: v => v + '%'
						},
						gridLines: {
							color: '#edf0f4',
							zeroLineColor: '#edf0f4'
						}
					}],
					yAxes: [{
						ticks: {
							fontSize: 9,
							fontColor: '#3a4a5c'
						},
						gridLines: {
							display: false
						}
					}]
				},
				tooltips: {
					backgroundColor: '#1a2a3a',
					callbacks: {
						label: (t) => ` ${t.value}%`
					}
				}
			}
		});
	}

	// 8.listas de filtros
	function initFilterListeners() {
		const filterIds = ['filterObjective', 'filterStatus', 'filterResponsible', 'filterFiscalYear'];
		filterIds.forEach(id => {
			const el = document.getElementById(id);
			if (el) {
				el.addEventListener('change', applyFilters);
			}
		});

		//botones de pestanias 
		const tabGlobal = document.getElementById('tabGlobalObj');
		const tabRegional = document.getElementById('tabRegionalObj');
		if (tabGlobal) {
			tabGlobal.addEventListener('click', function() {
				this.classList.add('active');
				if (tabRegional) tabRegional.classList.remove('active');
				filterObjectivesByType('global');
			});
		}
		if (tabRegional) {
			tabRegional.addEventListener('click', function() {
				this.classList.add('active');
				if (tabGlobal) tabGlobal.classList.remove('active');
				filterObjectivesByType('regional');
			});
		}
	}

	function populateFilterDropdowns(projects) {
		//llenar el dropdown
		const respSelect = document.getElementById('filterResponsible');
		if (respSelect) {
			const existing = new Set();
			respSelect.querySelectorAll('option').forEach(o => existing.add(o.value));
			const responsibles = [...new Set(projects.map(p => p.participante).filter(Boolean))];
			responsibles.forEach(name => {
				if (!existing.has(name)) {
					const opt = document.createElement('option');
					opt.value = name;
					opt.textContent = name;
					respSelect.appendChild(opt);
				}
			});
		}

		//llenar el dropdown de proyectos
		const objSelect = document.getElementById('filterObjective');
		if (objSelect && allObjectives.length > 0) {
			const existing = new Set();
			objSelect.querySelectorAll('option').forEach(o => existing.add(o.value));
			allObjectives.forEach(obj => {
				const name = obj.nombre || '';
				if (name && !existing.has(name)) {
					const opt = document.createElement('option');
					opt.value = name;
					opt.textContent = truncateText(name, 40);
					objSelect.appendChild(opt);
				}
			});
		}
	}

	function applyFilters() {
		const statusFilter = document.getElementById('filterStatus')?.value || 'all';
		const respFilter = document.getElementById('filterResponsible')?.value || 'all';
		const objFilter = document.getElementById('filterObjective')?.value || 'all';
		let filtered = [...allProjects];
		if (statusFilter !== 'all') {
			filtered = filtered.filter(p => {
				const mappedStatus = mapStatus(p.estado);
				return mappedStatus === statusFilter;
			});
		}
		if (respFilter !== 'all') {
			filtered = filtered.filter(p => p.participante === respFilter);
		}
		displayTaskDetailsTable(filtered);
		updateDoughnutFromProjects(filtered);
		updateStatusBoxesFromProjects(filtered);
		updateResponsibleChart(filtered);
		//filtrar objetivos tambien
		let filteredObj = [...allObjectives];
		if (respFilter !== 'all') {
			filteredObj = filteredObj.filter(o => o.responsable === respFilter);
		}
		displayObjectivesTable(filteredObj);
		updateObjectivesChart(filteredObj);
	}

	function filterObjectivesByType(type) {
		let filtered = [...allObjectives];
		if (type === 'global') {
			filtered = filtered.filter(o => (o.tipo || '').toLowerCase().includes('global'));
		} else if (type === 'regional') {
			filtered = filtered.filter(o => (o.tipo || '').toLowerCase().includes('regional'));
		}
		//si el filtro devuelve nada mostrar todo
		if (filtered.length === 0) filtered = allObjectives;
		displayObjectivesTable(filtered);
		updateObjectivesChart(filtered);
		// tambien filtrar tareas
		let filteredTasks = [...allProjects];
		if (type === 'global') {
			filteredTasks = filteredTasks.filter(p => (p.tipo_objetivo || p.tipo || '').toLowerCase().includes('global'));
		} else if (type === 'regional') {
			filteredTasks = filteredTasks.filter(p => (p.tipo_objetivo || p.tipo || '').toLowerCase().includes('regional'));
		}
		if (filteredTasks.length === 0) filteredTasks = allProjects;
		displayTaskDetailsTable(filteredTasks);
	}

	//funciones de utilidad
	function mapStatus(estado) {
		if (!estado) return 'Not Started';
		const key = estado.toLowerCase().trim();
		return STATUS_MAP[key] || estado;
	}

	function getStatusCSSClass(statusEN) {
		const map = {
			'Delay': 'status-delay',
			'Not Started': 'status-notstarted',
			'Completed': 'status-completed',
			'On Going': 'status-ongoing',
			'On Hold': 'status-onhold'
		};
		return map[statusEN] || 'status-delay';
	}

	function getTier(val) {
		if (val >= 75) return 'tier-great';
		if (val >= 50) return 'tier-good';
		if (val >= 25) return 'tier-medium';
		return 'tier-low';
	}

	function setText(selector, text) {
		const el = document.querySelector(selector);
		if (el) el.textContent = text;
	}

	function setTextIfEmpty(selector, text) {
		const el = document.querySelector(selector);
		if (el) {
			const current = el.textContent.trim();
			//poner solamente si aun no se muestra el valor default
			if (current === '0' || current === '-' || current === '' || current === '46' || current === '8' || current === '2' || current === '10' || current === '26') {
				el.textContent = text;
			}
		}
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

	function truncateText(text, length) {
		if (!text) return '-';
		return text.length > length ? text.substring(0, length) + '...' : text;
	}

	function formatDate(dateString) {
		if (!dateString) return '-';
		try {
			const date = new Date(dateString);
			return date.toLocaleDateString('en-US', {
				year: 'numeric',
				month: 'numeric',
				day: 'numeric',
				hour: 'numeric',
				minute: '2-digit',
				hour12: true
			});
		} catch (e) {
			return dateString;
		}
	}
	//hacer funciones globales
	window.loadAllDashboardData = loadAllData;
	window.allProjects = allProjects;
	window.applyDashboardFilters = applyFilters;
})();