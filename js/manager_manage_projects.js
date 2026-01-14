/*manager_manage_projects.js - Gestión de proyectos para gerentes con filtrado por URL*/

const Config = {
	API_ENDPOINTS: {
		DELETE: '../php/delete_project.php',
		GET_PROJECT_USERS: '../php/get_project_users.php',
		UPDATE_STATUS: '../php/update_project_status.php'
	}
};

let allProjects = [];
let currentSortColumn = null;
let sortDirection = 'asc';
let filteredProjects = [];

// Variables de paginación 
let currentPage = 1;
let rowsPerPage = 10;
let totalPages = 0;

// Variables para modal de usuarios 
let projectUsersData = [];
let currentUsersPage = 1;
let usersRowsPerPage = 10;
let totalUsersPages = 0;

// Variable para el auto-refresh 
let autoRefreshInterval = null;
let currentProjectIdForUsers = null;

// Variable para filtro de empleado desde URL 
let employeeFilterFromUrl = null;

// Variable para filtro de estado desde URL
let activeStatusFilter = null;

document.addEventListener('DOMContentLoaded', function() {
	initializeCustomDialogs();
	checkUrlParameters();
	setupSearch();
	setupSorting();
	setupPagination();
	setupStatusFilter();
	createProjectUsersModal();
	cargarProyectos();
	startAutoRefresh();
});

function checkUrlParameters() {
	const urlParams = new URLSearchParams(window.location.search);
	
	// Verificar filtro de empleado
	const filterEmployee = urlParams.get('filterEmployee');
	if (filterEmployee) {
		employeeFilterFromUrl = decodeURIComponent(filterEmployee);
		const searchInput = document.getElementById('searchInput');
		if (searchInput) {
			searchInput.value = employeeFilterFromUrl;
		}
		showFilterIndicator(employeeFilterFromUrl);
	}
	
	// Verificar filtro de estado
	const estadoParam = urlParams.get('estado');
	if (estadoParam) {
		activeStatusFilter = estadoParam.toLowerCase();
		showActiveStatusFilterIndicator(activeStatusFilter);
	}
}

/**
 * Configura el selector de filtro por estado
 */
function setupStatusFilter() {
	const rowsPerPageControl = document.querySelector('.rows-per-page-control');
	if (rowsPerPageControl && !document.getElementById('statusFilterSelect')) {
		const filterContainer = document.createElement('div');
		filterContainer.className = 'd-flex align-items-center gap-2 ms-4';
		filterContainer.innerHTML = `
			<label for="statusFilterSelect" class="form-label mb-0">Filtrar por estado:</label>
			<select id="statusFilterSelect" class="form-select form-select-sm" style="width: auto;">
				<option value="">Estados</option>
				<option value="pendiente">Pendiente</option>
				<option value="completado">Completado</option>
				<option value="vencido">Vencido</option>
			</select>
			<button id="clearStatusFilterBtn" class="btn btn-sm btn-secondary" style="display: none;" title="Limpiar filtro de estado">
				<i class="mdi mdi-close"></i> Limpiar
			</button>
		`;
		rowsPerPageControl.appendChild(filterContainer);
		
		// Event listener para el selector
		const filterSelect = document.getElementById('statusFilterSelect');
		filterSelect.addEventListener('change', function() {
			activeStatusFilter = this.value || null;
			applyAllFilters();
			updateURL();
		});
		
		// Event listener para el botón de limpiar
		const clearBtn = document.getElementById('clearStatusFilterBtn');
		clearBtn.addEventListener('click', function() {
			clearStatusFilter();
		});
		
		// Si hay un filtro activo desde URL, seleccionarlo
		if (activeStatusFilter) {
			filterSelect.value = activeStatusFilter;
		}
	}
}

/**
 * Muestra indicador de filtro de estado activo
 */
function showActiveStatusFilterIndicator(estado) {
	const clearBtn = document.getElementById('clearStatusFilterBtn');
	if (clearBtn) {
		clearBtn.style.display = estado ? 'inline-block' : 'none';
	}
	
	const filterSelect = document.getElementById('statusFilterSelect');
	if (filterSelect && estado) {
		filterSelect.value = estado;
	}
}

/**
 * Aplica todos los filtros activos (estado + búsqueda/empleado)
 */
function applyAllFilters() {
	let result = [...allProjects];
	
	// Aplicar filtro de estado
	if (activeStatusFilter) {
		result = result.filter(project => 
			project.estado && project.estado.toLowerCase() === activeStatusFilter
		);
	}
	
	// Aplicar filtro de búsqueda/empleado
	const searchInput = document.getElementById('searchInput');
	const searchQuery = searchInput ? searchInput.value.toLowerCase().trim() : '';
	
	if (searchQuery) {
		result = result.filter(project => 
			project.nombre.toLowerCase().includes(searchQuery) ||
			(project.descripcion && project.descripcion.toLowerCase().includes(searchQuery)) ||
			(project.area && project.area.toLowerCase().includes(searchQuery)) ||
			(project.participante && project.participante.toLowerCase().includes(searchQuery))
		);
	}
	
	filteredProjects = result;
	
	// Aplicar ordenamiento si existe
	if (currentSortColumn) {
		filteredProjects = sortProjects(filteredProjects, currentSortColumn, sortDirection);
	}
	
	currentPage = 1;
	displayProjects(filteredProjects);
	showActiveStatusFilterIndicator(activeStatusFilter);
}

/**
 * Limpia el filtro de estado
 */
function clearStatusFilter() {
	activeStatusFilter = null;
	const filterSelect = document.getElementById('statusFilterSelect');
	if (filterSelect) {
		filterSelect.value = '';
	}
	
	// Actualizar URL
	const url = new URL(window.location);
	url.searchParams.delete('estado');
	window.history.replaceState({}, '', url);
	
	applyAllFilters();
}

/**
 * Actualiza la URL con los filtros actuales
 */
function updateURL() {
	const url = new URL(window.location);
	
	if (activeStatusFilter) {
		url.searchParams.set('estado', activeStatusFilter);
	} else {
		url.searchParams.delete('estado');
	}
	
	window.history.replaceState({}, '', url);
}

/**
 * Función para filtrar por estado desde la URL (llamada desde el dashboard)
 */
function filterByStatus(estado) {
	activeStatusFilter = estado ? estado.toLowerCase() : null;
	
	const filterSelect = document.getElementById('statusFilterSelect');
	if (filterSelect) {
		filterSelect.value = activeStatusFilter || '';
	}
	
	applyAllFilters();
	updateURL();
}

function showFilterIndicator(employeeName) {
	let filterIndicator = document.getElementById('activeFilterIndicator');
	if (!filterIndicator) {
		filterIndicator = document.createElement('div');
		filterIndicator.id = 'activeFilterIndicator';
		filterIndicator.className = 'alert alert-info alert-dismissible fade show d-flex align-items-center';
		filterIndicator.style.cssText = 'margin-bottom: 15px;';
		const tableContainer = document.querySelector('.table-responsive');
		if (tableContainer) {
			tableContainer.parentNode.insertBefore(filterIndicator, tableContainer);
		}
	}
	filterIndicator.innerHTML = ` 
		<i class="mdi mdi-filter me-2"></i> 
		<span>Mostrando proyectos de: <strong>${escapeHtml(employeeName)}</strong></span> 
		<button type="button" class="btn btn-sm btn-primary ms-3" onclick="clearEmployeeFilter()"> 
			<i class="mdi mdi-close me-1"></i>Mostrar todos 
		</button> 
		<button type="button" class="btn-close ms-auto" onclick="clearEmployeeFilter()" aria-label="Close"></button> 
	`;
}

function clearEmployeeFilter() {
	employeeFilterFromUrl = null;
	const searchInput = document.getElementById('searchInput');
	if (searchInput) {
		searchInput.value = '';
	}
	const filterIndicator = document.getElementById('activeFilterIndicator');
	if (filterIndicator) {
		filterIndicator.remove();
	}
	const url = new URL(window.location);
	url.searchParams.delete('filterEmployee');
	window.history.replaceState({}, '', url);
	
	applyAllFilters();
}

function startAutoRefresh() {
	if (autoRefreshInterval) {
		clearInterval(autoRefreshInterval);
	}
	autoRefreshInterval = setInterval(() => {
		refreshProjectsData();
		if (currentProjectIdForUsers) {
			refreshProjectUsersData();
		}
	}, 60000);
}

function stopAutoRefresh() {
	if (autoRefreshInterval) {
		clearInterval(autoRefreshInterval);
		autoRefreshInterval = null;
	}
}

function refreshProjectsData() {
	fetch('../php/manager_get_projects.php')
		.then(response => {
			if (!response.ok) {
				throw new Error('La respuesta de red no fue ok');
			}
			return response.json();
		})
		.then(data => {
			if (data.success && data.proyectos) {
				allProjects = data.proyectos;
				applyAllFilters();
			}
		})
		.catch(error => {
			console.error('Error al refrescar proyectos:', error);
		});
}

function refreshProjectUsersData() {
	if (!currentProjectIdForUsers) return;
	const tableBody = document.getElementById('projectUsersTableBody');
	if (!tableBody) return;
	
	fetch(`${Config.API_ENDPOINTS.GET_PROJECT_USERS}?id=${currentProjectIdForUsers}`)
		.then(response => {
			if (!response.ok) {
				throw new Error('Error en la respuesta de red');
			}
			return response.json();
		})
		.then(data => {
			if (data.success && data.usuarios) {
				const searchInput = document.getElementById('projectUsersSearch');
				const currentSearchQuery = searchInput ? searchInput.value : '';
				projectUsersData = data.usuarios;
				if (currentSearchQuery.trim() !== '') {
					const filtered = projectUsersData.filter(user => {
						return user.nombre_completo.toLowerCase().includes(currentSearchQuery.toLowerCase()) ||
							user.e_mail.toLowerCase().includes(currentSearchQuery.toLowerCase()) ||
							user.num_empleado.toString().includes(currentSearchQuery);
					});
					displayProjectUsers(filtered);
				} else {
					displayProjectUsers(projectUsersData);
				}
			}
		})
		.catch(error => {
			console.error('Error al refrescar usuarios del proyecto:', error);
		});
}

function cargarProyectos() {
	const tableBody = document.querySelector('#proyectosTableBody');
	if (!tableBody) {
		console.error('El elemento de cuerpo de tabla no fue encontrado');
		return;
	}
	tableBody.innerHTML = ` 
		<tr> 
			<td colspan="9" class="text-center"> 
				<div class="spinner-border text-primary" role="status"> 
					<span class="visually-hidden">Cargando...</span> 
				</div> 
				<p class="mt-2">Cargando proyectos...</p> 
			</td> 
		</tr> 
	`;
	fetch('../php/manager_get_projects.php')
		.then(response => {
			if (!response.ok) {
				throw new Error('La respuesta de red no fue ok');
			}
			return response.json();
		})
		.then(data => {
			if (data.success && data.proyectos) {
				allProjects = data.proyectos;
				currentPage = 1;
				
				// Aplicar todos los filtros activos
				applyAllFilters();
			} else {
				tableBody.innerHTML = ` 
					<tr> 
						<td colspan="9" class="text-center text-danger"> 
							<p class="mt-3">Error al cargar proyectos: ${data.message || 'Error desconocido'}</p> 
						</td> 
					</tr> 
				`;
			}
		})
		.catch(error => {
			console.error('Error cargando proyectos:', error);
			tableBody.innerHTML = ` 
				<tr> 
					<td colspan="9" class="text-center text-danger"> 
						<p class="mt-3">Error al cargar los proyectos: ${error.message}</p> 
					</td> 
				</tr> 
			`;
		});
}

function setupSorting() {
	const headers = document.querySelectorAll('th.sortable-header');
	headers.forEach(header => {
		header.addEventListener('click', function() {
			const column = this.dataset.sort;
			if (currentSortColumn === column) {
				sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
			} else {
				currentSortColumn = column;
				sortDirection = 'asc';
			}
			updateSortIndicators();
			currentPage = 1;
			const sorted = sortProjects(filteredProjects, column, sortDirection);
			displayProjects(sorted);
		});
	});
}

function updateSortIndicators() {
	const headers = document.querySelectorAll('th.sortable-header');
	headers.forEach(header => {
		const icon = header.querySelector('i');
		if (header.dataset.sort === currentSortColumn) {
			icon.className = sortDirection === 'asc' ?
				'mdi mdi-sort-ascending' :
				'mdi mdi-sort-descending';
			header.style.fontWeight = 'bold';
			header.style.color = '#009b4a';
		} else {
			icon.className = 'mdi mdi-sort-variant';
			header.style.fontWeight = 'normal';
			header.style.color = 'inherit';
		}
	});
}

function sortProjects(projects, column, direction) {
	const sorted = [...projects];
	sorted.sort((a, b) => {
		let valueA = a[column];
		let valueB = b[column];
		if (valueA === null || valueA === undefined) valueA = '';
		if (valueB === null || valueB === undefined) valueB = '';
		if (column === 'progreso' || column === 'id_proyecto') {
			valueA = parseInt(valueA) || 0;
			valueB = parseInt(valueB) || 0;
		} else if (column === 'fecha_cumplimiento') {
			valueA = new Date(valueA).getTime() || 0;
			valueB = new Date(valueB).getTime() || 0;
		} else {
			valueA = String(valueA).toLowerCase();
			valueB = String(valueB).toLowerCase();
		}
		if (valueA < valueB) return direction === 'asc' ? -1 : 1;
		if (valueA > valueB) return direction === 'asc' ? 1 : -1;
		return 0;
	});
	return sorted;
}

function setupPagination() {
	const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
	if (rowsPerPageSelect) {
		rowsPerPageSelect.addEventListener('change', function() {
			rowsPerPage = parseInt(this.value);
			currentPage = 1;
			displayProjects(filteredProjects);
		});
	}
}

function calculatePages(projects) {
	return Math.ceil(projects.length / rowsPerPage);
}

function getPaginatedProjects(projects) {
	const startIndex = (currentPage - 1) * rowsPerPage;
	const endIndex = startIndex + rowsPerPage;
	return projects.slice(startIndex, endIndex);
}

function changePage(pageNumber) {
	if (pageNumber >= 1 && pageNumber <= totalPages) {
		currentPage = pageNumber;
		displayProjects(filteredProjects);
	}
}

function updatePaginationControls() {
	const paginationContainer = document.querySelector('.pagination-container');
	if (!paginationContainer) return;
	paginationContainer.innerHTML = '';
	
	const infoText = document.createElement('div');
	infoText.className = 'pagination-info';
	const startItem = ((currentPage - 1) * rowsPerPage) + 1;
	const endItem = Math.min(currentPage * rowsPerPage, filteredProjects.length);
	
	// Mostrar información del filtro activo
	let filterInfo = '';
	if (activeStatusFilter) {
		const statusLabels = {
			'pendiente': 'Pendientes',
			'en proceso': 'En Proceso',
			'completado': 'Completados',
			'vencido': 'Vencidos'
		};
		filterInfo = ` <span class="badge bg-info ms-2">Filtro: ${statusLabels[activeStatusFilter] || activeStatusFilter}</span>`;
	}
	
	infoText.innerHTML = ` 
		<p>Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${filteredProjects.length}</strong> proyectos${filterInfo}</p> 
	`;
	paginationContainer.appendChild(infoText);
	
	const buttonContainer = document.createElement('div');
	buttonContainer.className = 'pagination-buttons';
	const prevBtn = document.createElement('button');
	prevBtn.className = 'btn btn-sm btn-outline-primary';
	prevBtn.innerHTML = '<i class="mdi mdi-chevron-left"></i> Anterior';
	prevBtn.disabled = currentPage === 1;
	prevBtn.addEventListener('click', () => changePage(currentPage - 1));
	buttonContainer.appendChild(prevBtn);
	
	const pageButtonsContainer = document.createElement('div');
	pageButtonsContainer.className = 'page-buttons';
	let startPage = Math.max(1, currentPage - 2);
	let endPage = Math.min(totalPages, currentPage + 2);
	
	if (currentPage <= 3) {
		endPage = Math.min(totalPages, 5);
	}
	if (currentPage > totalPages - 3) {
		startPage = Math.max(1, totalPages - 4);
	}
	if (startPage > 1) {
		const firstBtn = document.createElement('button');
		firstBtn.className = 'btn btn-sm btn-outline-secondary page-btn';
		firstBtn.textContent = '1';
		firstBtn.addEventListener('click', () => changePage(1));
		pageButtonsContainer.appendChild(firstBtn);
		if (startPage > 2) {
			const ellipsis = document.createElement('span');
			ellipsis.className = 'pagination-ellipsis';
			ellipsis.textContent = '...';
			pageButtonsContainer.appendChild(ellipsis);
		}
	}
	for (let i = startPage; i <= endPage; i++) {
		const pageBtn = document.createElement('button');
		pageBtn.className = `btn btn-sm page-btn ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'}`;
		pageBtn.textContent = i;
		pageBtn.addEventListener('click', () => changePage(i));
		pageButtonsContainer.appendChild(pageBtn);
	}
	if (endPage < totalPages) {
		if (endPage < totalPages - 1) {
			const ellipsis = document.createElement('span');
			ellipsis.className = 'pagination-ellipsis';
			ellipsis.textContent = '...';
			pageButtonsContainer.appendChild(ellipsis);
		}
		const lastBtn = document.createElement('button');
		lastBtn.className = 'btn btn-sm btn-outline-secondary page-btn';
		lastBtn.textContent = totalPages;
		lastBtn.addEventListener('click', () => changePage(totalPages));
		pageButtonsContainer.appendChild(lastBtn);
	}
	buttonContainer.appendChild(pageButtonsContainer);
	
	const nextBtn = document.createElement('button');
	nextBtn.className = 'btn btn-sm btn-outline-primary';
	nextBtn.innerHTML = 'Siguiente <i class="mdi mdi-chevron-right"></i>';
	nextBtn.disabled = currentPage === totalPages;
	nextBtn.addEventListener('click', () => changePage(currentPage + 1));
	buttonContainer.appendChild(nextBtn);
	paginationContainer.appendChild(buttonContainer);
}

function displayProjects(proyectos) {
	const tableBody = document.querySelector('#proyectosTableBody');
	if (!tableBody) return;
	totalPages = calculatePages(proyectos);
	if (currentPage > totalPages && totalPages > 0) {
		currentPage = totalPages;
	}
	const paginatedProjects = getPaginatedProjects(proyectos);
	tableBody.innerHTML = '';
	if (!proyectos || proyectos.length === 0) {
		displayEmptyState();
		updatePaginationControls();
		return;
	}
	if (paginatedProjects.length === 0) {
		tableBody.innerHTML = ` 
			<tr> 
				<td colspan="9" class="text-center empty-state"> 
					<i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
					<h5 class="mt-3">No se encontraron resultados en esta página</h5> 
				</td> 
			</tr> 
		`;
		updatePaginationControls();
		return;
	}
	paginatedProjects.forEach((project, index) => {
		const actualIndex = ((currentPage - 1) * rowsPerPage) + index + 1;
		const row = createProjectRow(project, actualIndex);
		tableBody.appendChild(row);
	});
	updatePaginationControls();
}

function createProjectRow(proyecto, index) {
	const row = document.createElement('tr');
	const statusColor = getStatusColor(proyecto.estado);
	const statusBadge = `<span class="badge badge-${statusColor}">${proyecto.estado || 'N/A'}</span>`;
	const progressBar = createProgressBar(proyecto.progreso || 0);
	
	let toggleCompletionButton = '';
	const tieneTareas = proyecto.total_tareas && proyecto.total_tareas > 0;
	if (!tieneTareas) {
		const isCompleted = proyecto.estado === 'completado';
		const nextState = isCompleted ? 'pendiente' : 'completado';
		const toggleButtonClass = isCompleted ? 'btn-secondary' : 'btn-info';
		const toggleButtonIcon = isCompleted ? 'mdi-undo-variant' : 'mdi-check-circle-outline';
		const toggleButtonTitle = isCompleted ? 'Marcar como pendiente' : 'Marcar como completado';
		toggleCompletionButton = `<button class="btn btn-sm ${toggleButtonClass} btn-action"  
			onclick="toggleProjectCompletion(${proyecto.id_proyecto}, '${nextState}')"  
			title="${toggleButtonTitle}"> 
			<i class="mdi ${toggleButtonIcon}"></i> 
		</button>`;
	}
	const viewUsersButton = proyecto.id_tipo_proyecto === 1 ?
		`<button class="btn btn-sm btn-primary btn-action"  
			onclick="viewProjectUsers(${proyecto.id_proyecto}, '${escapeHtml(proyecto.nombre)}')"  
			title="Ver usuarios asignados"> 
			<i class="mdi mdi-account-multiple"></i> 
		   </button>` :
		'';
	const actionsButtons = ` 
		<div class="action-buttons"> 
			<button class="btn btn-sm btn-success btn-action"  
				onclick="editarProyecto(${proyecto.id_proyecto})"  
				title="Editar"> 
				<i class="mdi mdi-pencil"></i> 
			</button> 
			<button class="btn btn-sm btn-danger btn-action"  
				onclick="confirmDelete(${proyecto.id_proyecto}, '${escapeHtml(proyecto.nombre)}')"  
				title="Eliminar"> 
				<i class="mdi mdi-delete"></i> 
			</button> 
			${viewUsersButton} 
			${toggleCompletionButton} 
		</div> 
	`;
	row.innerHTML = ` 
		<td>${index}</td> 
		<td> 
			<strong>${truncateText(proyecto.nombre, 30)}</strong> 
		</td> 
		<td>${truncateText(proyecto.descripcion, 40)}</td> 
		<td>${proyecto.area || '-'}</td> 
		<td>${formatDate(proyecto.fecha_cumplimiento)}</td> 
		<td> 
			${progressBar} 
		</td> 
		<td> 
			${statusBadge} 
		</td> 
		<td>${proyecto.participante || '-'}</td> 
		<td> 
			${actionsButtons} 
		</td> 
	`;
	return row;
}

function createProgressBar(progress) {
	const progressValue = parseInt(progress) || 0;
	const progressClass = progressValue >= 75 ? 'bg-success' :
		progressValue >= 50 ? 'bg-info' :
		progressValue >= 25 ? 'bg-warning' : 'bg-danger';
	return ` 
		<div class="progress" style="height: 20px;"> 
			<div class="progress-bar ${progressClass}" role="progressbar"  
				 style="width: ${progressValue}%;"  
				 aria-valuenow="${progressValue}" aria-valuemin="0" aria-valuemax="100"> 
				${progressValue}% 
			</div> 
		</div> 
	`;
}

function getStatusColor(estado) {
	const colorMap = {
		'pendiente': 'warning',
		'en proceso': 'primary',
		'vencido': 'danger',
		'completado': 'success'
	};
	return colorMap[estado?.toLowerCase()] || 'warning';
}

function displayEmptyState() {
	const tableBody = document.querySelector('#proyectosTableBody');
	
	// Verificar si hay un filtro de estado activo
	if (activeStatusFilter) {
		const statusLabels = {
			'pendiente': 'pendientes',
			'en proceso': 'en proceso',
			'completado': 'completados',
			'vencido': 'vencidos'
		};
		tableBody.innerHTML = ` 
			<tr> 
				<td colspan="9" class="text-center empty-state"> 
					<i class="mdi mdi-filter-off" style="font-size: 48px; color: #e9e9e9;"></i> 
					<h5 class="mt-3">No hay proyectos ${statusLabels[activeStatusFilter] || activeStatusFilter}</h5> 
					<p>No se encontraron proyectos con el estado seleccionado</p> 
					<button class="btn btn-outline-primary mt-3" onclick="clearStatusFilter()"> 
						<i class="mdi mdi-filter-remove"></i> Mostrar todos los proyectos 
					</button> 
				</td> 
			</tr> 
		`;
	} else if (employeeFilterFromUrl) {
		tableBody.innerHTML = ` 
			<tr> 
				<td colspan="9" class="text-center empty-state"> 
					<i class="mdi mdi-folder-search-outline" style="font-size: 48px; color: #e9e9e9;"></i> 
					<h5 class="mt-3">No hay proyectos asignados a este empleado</h5> 
					<p>El empleado "${escapeHtml(employeeFilterFromUrl)}" no tiene proyectos asignados</p> 
					<button class="btn btn-primary mt-3" onclick="clearEmployeeFilter()"> 
						<i class="mdi mdi-view-list"></i> Ver todos los proyectos 
					</button> 
				</td> 
			</tr> 
		`;
	} else {
		tableBody.innerHTML = ` 
			<tr> 
				<td colspan="9" class="text-center empty-state"> 
					<i class="mdi mdi-folder-open" style="font-size: 48px; color: #e9e9e9;"></i> 
					<h5 class="mt-3">No hay proyectos registrados</h5> 
					<p>Comienza creando un nuevo proyecto</p> 
					<a href="../nuevoProyectoGerente/" class="btn btn-success mt-3"> 
						<i class="mdi mdi-plus-circle-outline"></i> Crear proyecto 
					</a> 
				</td> 
			</tr> 
		`;
	}
}

function setupSearch() {
	const searchInput = document.getElementById('searchInput');
	const searchForm = document.getElementById('search-form');
	if (!searchInput) {
		console.warn('Search input not found');
		return;
	}
	if (searchForm) {
		searchForm.addEventListener('submit', function(e) {
			e.preventDefault();
		});
	}
	let searchTimeout;
	searchInput.addEventListener('input', function() {
		clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => {
			const query = this.value;
			if (query.trim() === '' && employeeFilterFromUrl) {
				clearEmployeeFilter();
			} else {
				applyAllFilters();
			}
		}, 300);
	});
}

function performSearch(query) {
	const normalizedQuery = query.toLowerCase().trim();
	
	// Empezar con todos los proyectos o filtrados por estado
	let baseProjects = activeStatusFilter
		? allProjects.filter(p => p.estado && p.estado.toLowerCase() === activeStatusFilter)
		: [...allProjects];
	
	if (normalizedQuery === '') {
		filteredProjects = baseProjects;
		currentPage = 1;
		const sorted = currentSortColumn ?
			sortProjects(filteredProjects, currentSortColumn, sortDirection) :
			filteredProjects;
		displayProjects(sorted);
		return;
	}
	const filtered = baseProjects.filter(project => {
		return project.nombre.toLowerCase().includes(normalizedQuery) ||
			(project.descripcion && project.descripcion.toLowerCase().includes(normalizedQuery)) ||
			(project.area && project.area.toLowerCase().includes(normalizedQuery)) ||
			(project.participante && project.participante.toLowerCase().includes(normalizedQuery));
	});
	filteredProjects = filtered;
	currentPage = 1;
	const sorted = currentSortColumn ?
		sortProjects(filteredProjects, currentSortColumn, sortDirection) :
		filteredProjects;
	displayProjects(sorted);
	if (sorted.length === 0) {
		const tableBody = document.querySelector('#proyectosTableBody');
		if (employeeFilterFromUrl && query === employeeFilterFromUrl) {
			tableBody.innerHTML = ` 
				<tr> 
					<td colspan="9" class="text-center empty-state"> 
						<i class="mdi mdi-account-search" style="font-size: 48px; color: #ccc;"></i> 
						<h5 class="mt-3">Sin proyectos asignados</h5> 
						<p>"${escapeHtml(query)}" no tiene proyectos asignados actualmente</p> 
						<button class="btn btn-outline-primary mt-2" onclick="clearEmployeeFilter()"> 
							<i class="mdi mdi-view-list me-1"></i>Ver todos los proyectos 
						</button> 
					</td> 
				</tr> 
			`;
		} else {
			tableBody.innerHTML = ` 
				<tr> 
					<td colspan="9" class="text-center empty-state"> 
						<i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
						<h5 class="mt-3">No se encontraron resultados</h5> 
						<p>No hay proyectos que coincidan con "${escapeHtml(query)}"</p> 
					</td> 
				</tr> 
			`;
		}
	}
}

function truncateText(text, length) {
	if (!text) return '-';
	return text.length > length ? text.substring(0, length) + '...' : text;
}

function formatDate(dateString) {
	if (!dateString) return '-';
	const options = {
		year: 'numeric',
		month: 'short',
		day: 'numeric'
	};
	const date = new Date(dateString);
	return date.toLocaleDateString('es-MX', options);
}

function editarProyecto(idProyecto) {
	window.location.href = `../nuevoProyectoGerente/?edit=${idProyecto}`;
}

function toggleProjectCompletion(idProyecto, nuevoEstado) {
	const proyecto = allProjects.find(proj => proj.id_proyecto === idProyecto);
	if (!proyecto) return;
	let confirmMessage;
	let titleText;
	if (nuevoEstado === 'completado') {
		confirmMessage = `¿Marcar el proyecto "${escapeHtml(proyecto.nombre)}" como completado?\n\nEl progreso se establecerá en 100%.`;
		titleText = 'Cambiar estado a completado';
	} else {
		const fechaCumplimiento = new Date(proyecto.fecha_cumplimiento + 'T00:00:00');
		const hoy = new Date();
		hoy.setHours(0, 0, 0, 0);
		const estaVencido = fechaCumplimiento < hoy;
		if (estaVencido) {
			confirmMessage = `¿Revertir el proyecto "${escapeHtml(proyecto.nombre)}"?\n\nComo la fecha de entrega (${formatDate(proyecto.fecha_cumplimiento)}) ya pasó, el proyecto se marcará como VENCIDO.\n\nEl progreso se recalculará basado en las tareas completadas.`;
			titleText = 'Revertir proyecto (vencido)';
		} else {
			confirmMessage = `¿Marcar el proyecto "${escapeHtml(proyecto.nombre)}" como pendiente?\n\nEl progreso se recalculará basado en las tareas completadas.`;
			titleText = 'Cambiar estado a pendiente';
		}
	}
	showConfirm(
		confirmMessage,
		function() {
			updateProjectStatus(idProyecto, nuevoEstado);
		},
		titleText, {
			type: nuevoEstado === 'completado' ? 'success' : 'warning',
			confirmText: 'Confirmar',
			cancelText: 'Cancelar'
		}
	);
}

function updateProjectStatus(idProyecto, nuevoEstado) {
	fetch(Config.API_ENDPOINTS.UPDATE_STATUS, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				id_proyecto: idProyecto,
				estado: nuevoEstado
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				const project = allProjects.find(proj => proj.id_proyecto === idProyecto);
				if (project) {
					project.estado = data.nuevo_estado;
					project.progreso = data.nuevo_progreso;
				}
				let statusText;
				switch (data.nuevo_estado) {
					case 'completado':
						statusText = 'completado';
						break;
					case 'vencido':
						statusText = 'vencido (fecha de entrega superada)';
						break;
					case 'pendiente':
						statusText = 'pendiente';
						break;
					default:
						statusText = data.nuevo_estado;
				}
				showSuccessAlert(`Proyecto marcado como ${statusText}`);
				applyAllFilters();
			} else {
				showErrorAlert(data.message || 'Error al actualizar el estado del proyecto');
			}
		})
		.catch(error => {
			console.error('Error:', error);
			showErrorAlert('Error al conectar con el servidor');
		});
}

function confirmDelete(id, nombre) {
	showConfirm(
		`¿Está seguro de que desea eliminar el proyecto "${escapeHtml(nombre)}"?\n\nEsta acción no se puede deshacer.`,
		function() {
			deleteProject(id);
		},
		'Confirmar eliminación', {
			type: 'danger',
			confirmText: 'Eliminar',
			cancelText: 'Cancelar'
		}
	);
}

function deleteProject(id) {
	fetch(Config.API_ENDPOINTS.DELETE, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				id_proyecto: id
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				showSuccessAlert(data.message || 'Proyecto eliminado exitosamente');
				allProjects = allProjects.filter(u => u.id_proyecto != id);
				applyAllFilters();
			} else {
				showErrorAlert(data.message || 'Error al eliminar el proyecto');
			}
		})
		.catch(error => {
			console.error('Error:', error);
			showErrorAlert('Error al conectar con el servidor');
		});
}

function createProjectUsersModal() {
	const modalHTML = ` 
		<div class="modal fade" id="projectUsersModal" tabindex="-1" role="dialog" aria-labelledby="projectUsersModalLabel" aria-hidden="true"> 
			<div class="modal-dialog modal-xl" role="document"> 
				<div class="modal-content"> 
					<div class="modal-header"> 
						<h5 class="modal-title" id="projectUsersModalLabel"> 
							<i class="mdi mdi-account-multiple me-2"></i> 
							Usuarios asignados al proyecto 
						</h5> 
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> 
					</div> 
					<div class="modal-body"> 
						<div class="mb-3"> 
							<input type="text" class="form-control" id="projectUsersSearch"  
								   placeholder="Buscar usuario por nombre, email o empleado..."> 
						</div> 
						<div class="table-responsive"> 
							<table class="table table-hover"> 
								<thead> 
									<tr> 
										<th>#</th> 
										<th>Nombre Completo</th> 
										<th>Email</th> 
										<th>Número de Empleado</th> 
										<th>Progreso en Proyecto</th> 
									</tr> 
								</thead> 
								<tbody id="projectUsersTableBody"> 
									<tr> 
										<td colspan="5" class="text-center"> 
											<div class="spinner-border text-primary" role="status"> 
												<span class="visually-hidden">Cargando...</span> 
											</div> 
										</td> 
									</tr> 
								</tbody> 
							</table> 
						</div> 
						<div class="pagination-container mt-3"></div> 
					</div> 
					<div class="modal-footer"> 
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> 
					</div> 
				</div> 
			</div> 
		</div> 
	`;
	document.body.insertAdjacentHTML('beforeend', modalHTML);
	const modalElement = document.getElementById('projectUsersModal');
	if (modalElement) {
		modalElement.addEventListener('hidden.bs.modal', function() {
			currentProjectIdForUsers = null;
		});
	}
}

function createUserProgressBar(progress) {
	const progressValue = parseInt(progress) || 0;
	const progressClass = progressValue >= 75 ? 'bg-success' :
		progressValue >= 50 ? 'bg-info' :
		progressValue >= 25 ? 'bg-warning' : 'bg-danger';
	return ` 
		<div class="d-flex align-items-center gap-2"> 
			<div class="progress flex-grow-1" style="height: 20px; min-width: 100px;"> 
				<div class="progress-bar ${progressClass}" role="progressbar"  
					 style="width: ${progressValue}%;"  
					 aria-valuenow="${progressValue}" aria-valuemin="0" aria-valuemax="100"> 
					${progressValue.toFixed(1)}% 
				</div> 
			</div> 
		</div> 
	`;
}

function viewProjectUsers(projectId, projectName) {
	currentProjectIdForUsers = projectId;
	const modal = new bootstrap.Modal(document.getElementById('projectUsersModal'));
	document.getElementById('projectUsersModalLabel').textContent = `Usuarios asignados a: ${projectName}`;
	projectUsersData = [];
	currentUsersPage = 1;
	document.getElementById('projectUsersSearch').value = '';
	loadProjectUsers(projectId);
	modal.show();
}

function loadProjectUsers(projectId) {
	const tableBody = document.getElementById('projectUsersTableBody');
	fetch(`${Config.API_ENDPOINTS.GET_PROJECT_USERS}?id=${projectId}`)
		.then(response => {
			if (!response.ok) {
				throw new Error('Error en la respuesta de red');
			}
			return response.json();
		})
		.then(data => {
			if (data.success && data.usuarios) {
				projectUsersData = data.usuarios;
				displayProjectUsers(projectUsersData);
				const searchInput = document.getElementById('projectUsersSearch');
				if (searchInput) {
					searchInput.removeEventListener('input', handleProjectUsersSearch);
					searchInput.addEventListener('input', handleProjectUsersSearch);
				}
			} else {
				tableBody.innerHTML = ` 
					<tr> 
						<td colspan="5" class="text-center text-muted"> 
							<i class="mdi mdi-account-off" style="font-size: 48px; color: #E9E9E9;"></i> 
							<h5 class="mt-3">No hay usuarios asignados a este proyecto</h5> 
						</td> 
					</tr> 
				`;
			}
		})
		.catch(error => {
			console.error('Error cargando usuarios del proyecto:', error);
			tableBody.innerHTML = ` 
				<tr> 
					<td colspan="5" class="text-center text-danger"> 
						Error al cargar usuarios: ${error.message} 
					</td> 
				</tr> 
			`;
		});
}

function handleProjectUsersSearch(event) {
	const query = event.target.value.toLowerCase().trim();
	if (query === '') {
		displayProjectUsers(projectUsersData);
		return;
	}
	const filtered = projectUsersData.filter(user => {
		return user.nombre_completo.toLowerCase().includes(query) ||
			user.e_mail.toLowerCase().includes(query) ||
			user.num_empleado.toString().includes(query);
	});
	displayProjectUsers(filtered);
}

function displayProjectUsers(users) {
	const tableBody = document.getElementById('projectUsersTableBody');
	if (!users || users.length === 0) {
		tableBody.innerHTML = ` 
			<tr> 
				<td colspan="5" class="text-center text-muted"> 
					<i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
					<h5 class="mt-3">No se encontraron usuarios</h5> 
				</td> 
			</tr> 
		`;
		return;
	}
	totalUsersPages = Math.ceil(users.length / usersRowsPerPage);
	if (currentUsersPage > totalUsersPages && totalUsersPages > 0) {
		currentUsersPage = totalUsersPages;
	}
	const startIndex = (currentUsersPage - 1) * usersRowsPerPage;
	const endIndex = startIndex + usersRowsPerPage;
	const paginatedUsers = users.slice(startIndex, endIndex);
	tableBody.innerHTML = '';
	paginatedUsers.forEach((user, index) => {
		const rowNumber = startIndex + index + 1;
		const row = document.createElement('tr');
		const progressBar = createUserProgressBar(user.progreso_porcentaje || 0);
		row.innerHTML = ` 
			<td>${rowNumber}</td> 
			<td> 
				<div class="d-flex align-items-center"> 
					<div class="avatar avatar-sm me-2"> 
						<img src="../images/faces/face1.jpg" alt="avatar" class="rounded-circle"> 
					</div> 
					<div> 
						<strong>${escapeHtml(user.nombre_completo)}</strong> 
					</div> 
				</div> 
			</td> 
			<td>${escapeHtml(user.e_mail)}</td> 
			<td>${user.num_empleado}</td> 
			<td> 
				${progressBar} 
			</td> 
		`;
		tableBody.appendChild(row);
	});
	updateProjectUsersPagination(users.length);
}

function updateProjectUsersPagination(totalUsers) {
	const paginationContainer = document.querySelector('#projectUsersModal .pagination-container');
	if (!paginationContainer) return;
	paginationContainer.innerHTML = '';
	const infoText = document.createElement('div');
	infoText.className = 'pagination-info text-center mb-3';
	const startItem = ((currentUsersPage - 1) * usersRowsPerPage) + 1;
	const endItem = Math.min(currentUsersPage * usersRowsPerPage, totalUsers);
	infoText.innerHTML = ` 
		<p class="mb-0">Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${totalUsers}</strong> usuarios</p> 
	`;
	paginationContainer.appendChild(infoText);
	if (totalUsersPages <= 1) {
		return;
	}
	const buttonContainer = document.createElement('div');
	buttonContainer.className = 'pagination-buttons d-flex justify-content-center gap-2';
	const prevBtn = document.createElement('button');
	prevBtn.className = 'btn btn-sm btn-outline-primary';
	prevBtn.innerHTML = '<i class="mdi mdi-chevron-left"></i> Anterior';
	prevBtn.disabled = currentUsersPage === 1;
	prevBtn.addEventListener('click', () => {
		if (currentUsersPage > 1) {
			currentUsersPage--;
			const filtered = document.getElementById('projectUsersSearch').value.toLowerCase().trim();
			if (filtered) {
				const filteredUsers = projectUsersData.filter(user =>
					user.nombre_completo.toLowerCase().includes(filtered) ||
					user.e_mail.toLowerCase().includes(filtered) ||
					user.num_empleado.toString().includes(filtered)
				);
				displayProjectUsers(filteredUsers);
			} else {
				displayProjectUsers(projectUsersData);
			}
		}
	});
	buttonContainer.appendChild(prevBtn);
	const nextBtn = document.createElement('button');
	nextBtn.className = 'btn btn-sm btn-outline-primary';
	nextBtn.innerHTML = 'Siguiente <i class="mdi mdi-chevron-right"></i>';
	nextBtn.disabled = currentUsersPage === totalUsersPages;
	nextBtn.addEventListener('click', () => {
		if (currentUsersPage < totalUsersPages) {
			currentUsersPage++;
			const filtered = document.getElementById('projectUsersSearch').value.toLowerCase().trim();
			if (filtered) {
				const filteredUsers = projectUsersData.filter(user =>
					user.nombre_completo.toLowerCase().includes(filtered) ||
					user.e_mail.toLowerCase().includes(filtered) ||
					user.num_empleado.toString().includes(filtered)
				);
				displayProjectUsers(filteredUsers);
			} else {
				displayProjectUsers(projectUsersData);
			}
		}
	});
	buttonContainer.appendChild(nextBtn);
	paginationContainer.appendChild(buttonContainer);
}

function showSuccessAlert(message) {
	showAlert(message, 'success');
}

function showErrorAlert(message) {
	showAlert(message, 'danger');
}

function showAlert(message, type) {
	const alertDiv = document.getElementById('alertMessage');
	if (!alertDiv) {
		console.warn('Alert div not found');
		return;
	}
	const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
	const icon = type === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle';
	alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
	alertDiv.innerHTML = ` 
		<i class="mdi ${icon} me-2"></i> 
		${message} 
		<button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button> 
	`;
	alertDiv.style.display = 'block';
	alertDiv.scrollIntoView({
		behavior: 'smooth',
		block: 'nearest'
	});
	setTimeout(() => {
		if (alertDiv.style.display !== 'none') {
			alertDiv.style.display = 'none';
		}
	}, 5000);
}

function escapeHtml(text) {
	const map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;'
	};
	return String(text).replace(/[&<>"']/g, function(m) {
		return map[m];
	});
}

function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
	const modal = document.getElementById('customConfirmModal');
	if (!modal) {
		console.error('Modal #customConfirmModal not found in DOM');
		return;
	}
	const titleElement = modal.querySelector('#confirmTitle');
	const messageElement = modal.querySelector('#confirmMessage');
	const headerElement = modal.querySelector('.modal-header');
	const confirmBtn = modal.querySelector('#confirmOkBtn');
	const cancelBtn = modal.querySelector('#confirmCancelBtn');
	if (!titleElement || !messageElement || !headerElement || !confirmBtn) {
		console.error('Critical modal elements not found');
		return;
	}
	const config = {
		confirmText: 'Aceptar',
		cancelText: 'Cancelar',
		type: 'warning',
		...options
	};
	titleElement.textContent = title;
	messageElement.innerHTML = message.replace(/\n/g, '<br>');
	confirmBtn.textContent = config.confirmText;
	if (cancelBtn) {
		cancelBtn.textContent = config.cancelText;
	}
	headerElement.className = 'modal-header';
	const iconMap = {
		'info': {
			icon: 'mdi-information-outline',
			class: 'bg-info text-white',
			btnClass: 'btn-info'
		},
		'warning': {
			icon: 'mdi-alert-outline',
			class: 'bg-warning text-white',
			btnClass: 'btn-warning'
		},
		'danger': {
			icon: 'mdi-alert-octagon-outline',
			class: 'bg-danger text-white',
			btnClass: 'btn-danger'
		},
		'success': {
			icon: 'mdi-check-circle-outline',
			class: 'bg-success text-white',
			btnClass: 'btn-success'
		}
	};
	const typeConfig = iconMap[config.type] || iconMap['warning'];
	let iconElement = modal.querySelector('.modal-title i');
	if (!iconElement) {
		iconElement = document.createElement('i');
		titleElement.insertBefore(iconElement, titleElement.firstChild);
	}
	iconElement.className = `mdi ${typeConfig.icon} me-2`;
	headerElement.classList.remove('bg-info', 'bg-warning', 'bg-danger', 'bg-success', 'text-white');
	headerElement.classList.add(...typeConfig.class.split(' '));
	confirmBtn.className = `btn ${typeConfig.btnClass}`;
	const newConfirmBtn = confirmBtn.cloneNode(true);
	confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
	newConfirmBtn.addEventListener('click', function(e) {
		e.preventDefault();
		e.stopPropagation();
		try {
			const modalInstance = bootstrap.Modal.getInstance(modal);
			if (modalInstance) {
				modalInstance.hide();
			}
		} catch (err) {
			console.error('Error hiding modal:', err);
		}
		if (onConfirm && typeof onConfirm === 'function') {
			onConfirm();
		}
	}, {
		once: true
	});
	let modalInstance = bootstrap.Modal.getInstance(modal);
	if (modalInstance) {
		modalInstance.dispose();
	}
	try {
		const confirmModal = new bootstrap.Modal(modal, {
			backdrop: 'static',
			keyboard: false
		});
		confirmModal.show();
	} catch (err) {
		console.error('Error showing modal:', err);
	}
}

// Hacer funciones globalmente disponibles
window.confirmDelete = confirmDelete;
window.editarProyecto = editarProyecto;
window.changePage = changePage;
window.showConfirm = showConfirm;
window.viewProjectUsers = viewProjectUsers;
window.stopAutoRefresh = stopAutoRefresh;
window.startAutoRefresh = startAutoRefresh;
window.toggleProjectCompletion = toggleProjectCompletion;
window.updateProjectStatus = updateProjectStatus;
window.clearEmployeeFilter = clearEmployeeFilter;
window.filterByStatus = filterByStatus;
window.clearStatusFilter = clearStatusFilter;