<<<<<<< HEAD
<<<<<<< HEAD
/*list_projects_manager.js JavaScript para cargar y mostrar datos del dashboard de gerente*/

const ManagerConfig = { 
    API_ENDPOINTS: { 
        GET_DASHBOARD_STATS: '../php/manager_get_dashboard_stats.php',
        GET_PROJECTS: '../php/manager_get_projects.php',
        GET_TOP_EMPLOYEES: '../php/manager_get_top_employees_progress.php',
        GET_TOP_PROJECTS: '../php/manager_get_top_projects_progress\.php',
=======
=======
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
/*
 * list_projects_manager.js
 * JavaScript para cargar y mostrar datos del dashboard de gerente
 * Maneja proyectos, empleados y estadísticas del departamento
 */

const ManagerConfig = { 
    API_ENDPOINTS: { 
        GET_DASHBOARD_STATS: '../php/get_dashboard_stats_manager.php',
        GET_PROJECTS: '../php/get_projects_manager.php',
        GET_TOP_EMPLOYEES: '../php/get_top_employees_progress_manager.php',
        GET_TOP_PROJECTS: '../php/get_top_projects_progress_manager.php',
<<<<<<< HEAD
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
=======
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
        GET_PROJECT_USERS: '../php/get_project_user.php'
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

// Variable para el auto-refresh 
let autoRefreshInterval = null; 
let currentProjectIdForUsers = null;

document.addEventListener('DOMContentLoaded', function() { 
    initializeCustomDialogs();
    setupSorting(); 
    setupPagination();
    setupSearch();
    loadManagerDashboardStats();
    loadTopEmployeesProgress();
    loadTopProjectsProgress();
    cargarProyectos(); 
    startAutoRefresh();
}); 

function initializeCustomDialogs() {
    // Inicializar modales si existen
    console.log('Custom dialogs initialized');
}

function startAutoRefresh() { 
    if (autoRefreshInterval) { 
        clearInterval(autoRefreshInterval); 
    } 
    autoRefreshInterval = setInterval(() => { 
        console.log('Auto-refresh: Actualizando datos del departamento...'); 
        loadManagerDashboardStats();
        loadTopEmployeesProgress();
        loadTopProjectsProgress();
        refreshProjectsData();
        
        if (currentProjectIdForUsers) {
            refreshProjectUsersData();
        }
    }, 60000); // 60000 ms = 1 minuto 
} 

function stopAutoRefresh() { 
    if (autoRefreshInterval) { 
        clearInterval(autoRefreshInterval); 
        autoRefreshInterval = null; 
        console.log('Auto-refresh detenido'); 
    } 
} 

// ==========================================
// CARGAR ESTADÍSTICAS DEL DEPARTAMENTO
// ==========================================

function loadManagerDashboardStats() { 
    fetch(ManagerConfig.API_ENDPOINTS.GET_DASHBOARD_STATS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('Error en la respuesta de red'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.stats) { 
                updateManagerDashboardStats(data.stats); 
                console.log('Estadísticas del departamento actualizadas:', data.stats); 
            } else { 
                console.error('Error al cargar estadísticas:', data.message); 
            } 
        }) 
        .catch(error => { 
            console.error('Error al cargar estadísticas del dashboard:', error); 
        }); 
} 

function updateManagerDashboardStats(stats) { 
    // Proyectos del departamento
    const proyectosDeptEl = document.getElementById('stat-proyectos-dept');
    if (proyectosDeptEl) {
        proyectosDeptEl.textContent = stats.proyectos_departamento || 0;
    }
    
    // Empleados
    const empleadosEl = document.getElementById('stat-empleados');
    if (empleadosEl) {
        empleadosEl.textContent = stats.total_empleados || 0;
    }
    
    // Tareas del departamento
    const tareasDeptEl = document.getElementById('stat-tareas-dept');
    if (tareasDeptEl) {
        tareasDeptEl.textContent = stats.tareas_departamento || 0;
    }
    
    // Completados
    const completadosEl = document.getElementById('stat-completados');
    if (completadosEl) {
        completadosEl.textContent = stats.proyectos_completados || 0;
    }
    const completadosPctEl = document.getElementById('stat-completados-pct');
    if (completadosPctEl) {
        completadosPctEl.textContent = (stats.porcentaje_completados || 0) + '%';
    }
    
    // En proceso
    const enProcesoEl = document.getElementById('stat-en-proceso');
    if (enProcesoEl) {
        enProcesoEl.textContent = stats.proyectos_en_proceso || 0;
    }
    const progresoPromEl = document.getElementById('stat-progreso-prom');
    if (progresoPromEl) {
        progresoPromEl.textContent = (stats.progreso_promedio || 0) + '% prom.';
    }
    
    // Pendientes
    const pendientesEl = document.getElementById('stat-pendientes');
    if (pendientesEl) {
        pendientesEl.textContent = stats.proyectos_pendientes || 0;
    }
    
    // Vencidos
    const vencidosEl = document.getElementById('stat-vencidos');
    if (vencidosEl) {
        vencidosEl.textContent = stats.proyectos_vencidos || 0;
    }

    // Actualizar gráfico de dona
    if (typeof updateDepartmentProjectsChart === 'function') {
        updateDepartmentProjectsChart(
            stats.proyectos_pendientes || 0,
            stats.proyectos_completados || 0,
            stats.proyectos_vencidos || 0,
            stats.proyectos_en_proceso || 0
        );
    }
}

// ==========================================
// CARGAR TOP EMPLEADOS DEL DEPARTAMENTO
// ==========================================

function loadTopEmployeesProgress() {
    fetch(ManagerConfig.API_ENDPOINTS.GET_TOP_EMPLOYEES)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayTopEmployeesProgress(data.empleados);
                console.log('Top empleados del departamento cargados:', data.empleados);
            } else {
                console.warn('Aviso al cargar empleados:', data.message);
                displayEmptyEmployeesState();
            }
        })
        .catch(error => {
            console.error('Error al cargar empleados top:', error);
            displayEmptyEmployeesState();
        });
}

function displayTopEmployeesProgress(empleados) {
    const tableBody = document.getElementById('topEmployeesTableBody');
    
    if (!tableBody) {
        console.warn('Elemento #topEmployeesTableBody no encontrado');
        return;
    }

    tableBody.innerHTML = '';

    if (!empleados || empleados.length === 0) {
        displayEmptyEmployeesState();
        return;
    }

    empleados.forEach((empleado, index) => {
        const row = document.createElement('tr');
        const progressBar = createProgressBar(empleado.progreso);
        
        row.innerHTML = `
            <td>
                <strong>${index + 1}</strong>
            </td>
            <td>
                <strong>${escapeHtml(empleado.nombre_completo)}</strong>
                <br>
                <small class="text-muted">#${empleado.num_empleado}</small>
            </td>
            <td>
                ${progressBar}
                <small class="text-muted d-block mt-1">
                    ${empleado.tareas_completadas}/${empleado.total_tareas} tareas
                </small>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

function displayEmptyEmployeesState() {
    const tableBody = document.getElementById('topEmployeesTableBody');
    
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="3" class="text-center text-muted py-4">
                <i class="mdi mdi-account-off" style="font-size: 32px; opacity: 0.5;"></i>
                <p class="mt-2">No hay empleados con tareas asignadas</p>
            </td>
        </tr>
    `;
}

// ==========================================
// CARGAR TOP PROYECTOS DEL DEPARTAMENTO
// ==========================================

function loadTopProjectsProgress() {
    fetch(ManagerConfig.API_ENDPOINTS.GET_TOP_PROJECTS)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayTopProjectsProgress(data.proyectos);
                console.log('Top proyectos del departamento cargados:', data.proyectos);
            } else {
                console.warn('Aviso al cargar proyectos:', data.message);
                displayEmptyTopProjectsState();
            }
        })
        .catch(error => {
            console.error('Error al cargar proyectos top:', error);
            displayEmptyTopProjectsState();
        });
}

function displayTopProjectsProgress(proyectos) {
    const tableBody = document.getElementById('topProjectsTableBody');
    
    if (!tableBody) {
        console.warn('Elemento #topProjectsTableBody no encontrado');
        return;
    }

    tableBody.innerHTML = '';

    if (!proyectos || proyectos.length === 0) {
        displayEmptyTopProjectsState();
        return;
    }

    proyectos.forEach((proyecto, index) => {
        const row = document.createElement('tr');
        const progressBar = createProgressBar(proyecto.progreso);
        const statusBadge = getStatusBadge(proyecto.estado);
        
        row.innerHTML = `
            <td>
                <strong>${index + 1}</strong>
            </td>
            <td>
                <strong>${escapeHtml(truncateText(proyecto.nombre, 20))}</strong>
                <br>
                ${statusBadge}
            </td>
            <td>
                ${progressBar}
                <small class="text-muted d-block mt-1">
                    ${proyecto.tareas_completadas}/${proyecto.total_tareas} tareas
                </small>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

function displayEmptyTopProjectsState() {
    const tableBody = document.getElementById('topProjectsTableBody');
    
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="3" class="text-center text-muted py-4">
                <i class="mdi mdi-folder-off" style="font-size: 32px; opacity: 0.5;"></i>
                <p class="mt-2">No hay proyectos en progreso</p>
            </td>
        </tr>
    `;
}

// ==========================================
// CARGAR PROYECTOS DEL DEPARTAMENTO
// ==========================================

function cargarProyectos() { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    if(!tableBody) { 
        console.error('El elemento de cuerpo de tabla no fue encontrado'); 
        return; 
    } 

    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="8" class="text-center"> 
                <div class="spinner-border text-primary" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <p class="mt-2">Cargando proyectos del departamento...</p> 
            </td> 
        </tr> 
    `; 

    fetch(ManagerConfig.API_ENDPOINTS.GET_PROJECTS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json();
        }) 
        .then(data => { 
            console.log('Información recibida:', data); 
            if (data.success && data.proyectos) { 
                allProjects = data.proyectos; 
                filteredProjects = [...allProjects]; 
                currentPage = 1;
                displayProjects(data.proyectos);
                
                // Actualizar gráfico con los proyectos
                if (typeof updateProyectoStatusChart === 'function') {
                    updateProyectoStatusChart(data.proyectos);
                }
            } else { 
                tableBody.innerHTML = ` 
                    <tr> 
                        <td colspan="8" class="text-center text-danger"> 
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
                    <td colspan="8" class="text-center text-danger"> 
                        <p class="mt-3">Error al cargar los proyectos: ${error.message}</p> 
                    </td> 
                </tr> 
            `; 
        }); 
} 

function refreshProjectsData() { 
    fetch(ManagerConfig.API_ENDPOINTS.GET_PROJECTS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.proyectos) { 
                const searchInput = document.getElementById('searchInput'); 
                const currentSearchQuery = searchInput ? searchInput.value : ''; 
                
                allProjects = data.proyectos;
                
                if (currentSearchQuery.trim() !== '') { 
                    performSearch(currentSearchQuery); 
                } else { 
                    filteredProjects = [...allProjects]; 
                } 
                
                if (currentSortColumn) { 
                    filteredProjects = sortProjects(filteredProjects, currentSortColumn, sortDirection); 
                } 
                
                const newTotalPages = calculatePages(filteredProjects);
                if (currentPage > newTotalPages && newTotalPages > 0) { 
                    currentPage = newTotalPages; 
                } 
                
                displayProjects(filteredProjects);
                
                // Actualizar gráfico
                if (typeof updateProyectoStatusChart === 'function') {
                    updateProyectoStatusChart(allProjects);
                }
                
                console.log('Datos de proyectos actualizados exitosamente'); 
            } 
        }) 
        .catch(error => { 
            console.error('Error al refrescar proyectos:', error); 
        }); 
}

function refreshProjectUsersData() {
    if (!currentProjectIdForUsers) return;
    
    fetch(`${ManagerConfig.API_ENDPOINTS.GET_PROJECT_USERS}?id=${currentProjectIdForUsers}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.usuarios) {
                displayProjectUsersInModal(data.usuarios);
            }
        })
        .catch(error => {
            console.error('Error al refrescar usuarios del proyecto:', error);
        });
}

// ==========================================
// BÚSQUEDA
// ==========================================

function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            performSearch(this.value);
        });
    }
}

function performSearch(query) { 
    const normalizedQuery = query.toLowerCase().trim(); 

    if (normalizedQuery === '') { 
        filteredProjects = [...allProjects]; 
        currentPage = 1;
        const sorted = currentSortColumn ? 
            sortProjects(filteredProjects, currentSortColumn, sortDirection) : 
            filteredProjects; 
        displayProjects(sorted); 
        return; 
    } 

    const filtered = allProjects.filter(project => { 
        return project.nombre.toLowerCase().includes(normalizedQuery) || 
            (project.descripcion && project.descripcion.toLowerCase().includes(normalizedQuery)) || 
            (project.participante && project.participante.toLowerCase().includes(normalizedQuery)) ||
            (project.estado && project.estado.toLowerCase().includes(normalizedQuery)); 
    }); 

    filteredProjects = filtered; 
    currentPage = 1;

    const sorted = currentSortColumn ? 
        sortProjects(filteredProjects, currentSortColumn, sortDirection) : 
        filteredProjects;
    displayProjects(sorted); 
}

// ==========================================
// ORDENAMIENTO
// ==========================================

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
            icon.className = sortDirection === 'asc' ? 'mdi mdi-sort-ascending' : 'mdi mdi-sort-descending'; 
            header.style.fontWeight = 'bold'; 
            header.style.color = '#0094ba'; 
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

// ==========================================
// PAGINACIÓN
// ==========================================

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

    if (filteredProjects.length === 0) return;

    const infoText = document.createElement('div'); 
    infoText.className = 'pagination-info'; 
    const startItem = ((currentPage - 1) * rowsPerPage) + 1; 
    const endItem = Math.min(currentPage * rowsPerPage, filteredProjects.length); 
    infoText.innerHTML = ` 
        <p>Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${filteredProjects.length}</strong> proyectos</p> 
    `; 

    paginationContainer.appendChild(infoText); 
    
    if (totalPages <= 1) return;
    
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

// ==========================================
// MOSTRAR PROYECTOS
// ==========================================

function displayProjects(proyectos) { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    if(!tableBody) return; 
    
    totalPages = calculatePages(proyectos);
    if (currentPage > totalPages && totalPages > 0) { 
        currentPage = totalPages; 
    } 
    
    const paginatedProjects = getPaginatedProjects(proyectos);
    tableBody.innerHTML = ''; 
 
    if(!proyectos || proyectos.length === 0) { 
        displayEmptyState(); 
        updatePaginationControls(); 
        return; 
    } 
 
    if (paginatedProjects.length === 0) { 
        tableBody.innerHTML = ` 
            <tr> 
                <td colspan="8" class="text-center empty-state"> 
                    <i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
                    <h5 class="mt-3">No se encontraron resultados</h5> 
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
    
    const statusBadge = getStatusBadge(proyecto.estado);
    const progressBar = createProgressBar(proyecto.progreso || 0); 
    
    // Botón para ver usuarios (solo para proyectos grupales)
    const viewUsersButton = proyecto.id_tipo_proyecto === 1 
        ? `<button class="btn btn-sm btn-info btn-action" onclick="viewProjectUsers(${proyecto.id_proyecto}, '${escapeHtml(proyecto.nombre)}')" title="Ver usuarios asignados">
              <i class="mdi mdi-account-multiple"></i>
           </button>` 
        : '';

    const actionsButtons = `
        <div class="action-buttons d-flex gap-1">
            <button class="btn btn-sm btn-success btn-action" onclick="editarProyecto(${proyecto.id_proyecto})" title="Editar">
                <i class="mdi mdi-pencil"></i>
            </button>
            ${viewUsersButton}
            <button class="btn btn-sm btn-primary btn-action" onclick="verTareas(${proyecto.id_proyecto})" title="Ver tareas">
                <i class="mdi mdi-clipboard-list"></i>
            </button>
        </div>
    `;

    row.innerHTML = ` 
        <td>${index}</td> 
        <td> 
            <strong>${truncateText(proyecto.nombre, 25)}</strong>
        </td> 
        <td>${truncateText(proyecto.descripcion, 35)}</td> 
        <td>${formatDate(proyecto.fecha_cumplimiento)}</td> 
        <td> 
            ${progressBar} 
        </td> 
        <td> 
            ${statusBadge} 
        </td> 
<<<<<<< HEAD
<<<<<<< HEAD
=======
        <td>${proyecto.participante || '-'}</td>
        <td>${actionsButtons}</td>
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
=======
        <td>${proyecto.participante || '-'}</td>
        <td>${actionsButtons}</td>
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
    `; 
    return row; 
} 

function displayEmptyState() { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="8" class="text-center empty-state"> 
                <i class="mdi mdi-folder-open" style="font-size: 48px; color: #e9e9e9;"></i> 
                <h5 class="mt-3">No hay proyectos en el departamento</h5> 
                <p>Comienza creando un nuevo proyecto</p> 
                <a href="../nuevoProyectoManager/" class="btn btn-success mt-3"> 
                    <i class="mdi mdi-plus-circle-outline"></i> Crear proyecto 
                </a> 
            </td> 
        </tr> 
    `; 
} 

<<<<<<< HEAD
<<<<<<< HEAD
=======
=======
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
// ==========================================
// ACCIONES DE PROYECTOS
// ==========================================

<<<<<<< HEAD
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
=======
>>>>>>> 4119a0f (update of 26/11/25 at 18:08)
function editarProyecto(idProyecto) {
    window.location.href = `../editarProyecto/?id=${idProyecto}`;
}

function verTareas(idProyecto) {
    window.location.href = `../revisarTareasManager/?proyecto=${idProyecto}`;
}

function viewProjectUsers(idProyecto, nombreProyecto) {
    currentProjectIdForUsers = idProyecto;
    
    const modal = document.getElementById('projectUsersModal');
    const modalTitle = document.getElementById('projectUsersModalLabel');
    const contentDiv = document.getElementById('projectUsersContent');
    
    if (modalTitle) {
        modalTitle.innerHTML = `<i class="mdi mdi-account-multiple me-2"></i>Usuarios: ${escapeHtml(nombreProyecto)}`;
    }
    
    if (contentDiv) {
        contentDiv.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando usuarios...</p>
            </div>
        `;
    }
    
    // Mostrar modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Cargar usuarios
    fetch(`${ManagerConfig.API_ENDPOINTS.GET_PROJECT_USERS}?id=${idProyecto}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.usuarios) {
                displayProjectUsersInModal(data.usuarios);
            } else {
                contentDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert me-2"></i>
                        ${data.message || 'No se pudieron cargar los usuarios'}
                    </div>
                `;
            }
        })
        .catch(error => {
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="mdi mdi-alert-circle me-2"></i>
                    Error al cargar usuarios: ${error.message}
                </div>
            `;
        });
}

function displayProjectUsersInModal(usuarios) {
    const contentDiv = document.getElementById('projectUsersContent');
    
    if (!usuarios || usuarios.length === 0) {
        contentDiv.innerHTML = `
            <div class="alert alert-info">
                <i class="mdi mdi-information me-2"></i>
                No hay usuarios asignados a este proyecto
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Email</th>
                        <th>Progreso</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    usuarios.forEach(user => {
        const progressBar = createProgressBar(user.progreso_porcentaje || 0);
        html += `
            <tr>
                <td>
                    <strong>${escapeHtml(user.nombre_completo)}</strong>
                    <br>
                    <small class="text-muted">#${user.num_empleado}</small>
                </td>
                <td><small>${escapeHtml(user.e_mail)}</small></td>
                <td style="min-width: 150px;">${progressBar}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    contentDiv.innerHTML = html;
}

// ==========================================
// FUNCIONES DE UTILIDAD
// ==========================================

function createProgressBar(progress) { 
    const progressValue = parseFloat(progress) || 0; 
    const progressClass = progressValue >= 75 ? 'bg-success' : 
        progressValue >= 50 ? 'bg-info' : 
        progressValue >= 25 ? 'bg-warning' : 'bg-danger'; 
    return ` 
        <div class="progress" style="height: 20px;"> 
            <div class="progress-bar ${progressClass}" role="progressbar" style="width: ${progressValue}%;"  
                 aria-valuenow="${progressValue}" aria-valuemin="0" aria-valuemax="100"> 
                ${progressValue.toFixed(1)}% 
            </div> 
        </div> 
    `; 
} 

function getStatusBadge(estado) {
    const colorMap = { 
        'pendiente': 'warning', 
        'en proceso': 'primary', 
        'vencido': 'danger', 
        'completado': 'success' 
    }; 
    const color = colorMap[estado?.toLowerCase()] || 'warning';
    return `<span class="badge badge-${color}">${estado || 'N/A'}</span>`;
}

function truncateText(text, length) { 
    if (!text) return '-'; 
    return text.length > length ? text.substring(0, length) + '...' : text; 
} 

function formatDate(dateString) { 
    if (!dateString) return '-'; 
    const options = { year: 'numeric', month: 'short', day: 'numeric' }; 
    const date = new Date(dateString); 
    return date.toLocaleDateString('es-MX', options); 
} 

function escapeHtml(text) { 
    const map = { 
        '&': '&amp;', 
        '<': '&lt;', 
        '>': '&gt;', 
        '"': '&quot;', 
        "'": '&#039;' 
    }; 
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; }); 
}

function showAlert(message, type = 'info') {
    const alertDiv = document.getElementById('alertMessage');
    if (!alertDiv) return;
    
    const alertClass = type === 'success' ? 'alert-success' : 
                       type === 'danger' ? 'alert-danger' : 
                       type === 'warning' ? 'alert-warning' : 'alert-info';
    const icon = type === 'success' ? 'mdi-check-circle' : 
                 type === 'danger' ? 'mdi-alert-circle' : 
                 type === 'warning' ? 'mdi-alert' : 'mdi-information';
    
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="mdi ${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button>
    `;
    alertDiv.style.display = 'block';
    
    setTimeout(() => {
        alertDiv.style.display = 'none';
    }, 5000);
}

// Hacer funciones globalmente disponibles 
window.changePage = changePage; 
window.stopAutoRefresh = stopAutoRefresh;
window.startAutoRefresh = startAutoRefresh;
window.editarProyecto = editarProyecto;
window.verTareas = verTareas;
window.viewProjectUsers = viewProjectUsers;