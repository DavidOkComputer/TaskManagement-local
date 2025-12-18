/*list_projects_user.js JavaScript para cargar y mostrar datos del dashboard de usuario*/

const UserConfig = { 
    API_ENDPOINTS: { 
        GET_DASHBOARD_STATS: '../php/user_get_dashboard_stats.php',
        GET_PROJECTS: '../php/user_get_projects.php',
        GET_MY_TASKS: '../php/user_get_tasks.php',
        GET_MY_PROJECTS_PROGRESS: '../php/user_get_project_progress.php'
    } 
}; 

let allProjects = []; 
let currentSortColumn = null; 
let sortDirection = 'asc'; 
let filteredProjects = []; 

// Variables de paginación 
let currentPage = 1; 
let rowsPerPage = 5; 
let totalPages = 0; 

// Variable para el auto-refresh 
let autoRefreshInterval = null; 

// Variable para el gráfico
let userTasksChart = null;

document.addEventListener('DOMContentLoaded', function() { 
    setupSorting(); 
    setupPagination(); 
    loadUserDashboardStats();
    loadMyTasks();
    loadMyProjectsProgress();
    loadTopProjects();
    cargarProyectos(); 
    startAutoRefresh();
}); 

function startAutoRefresh() { 
    if (autoRefreshInterval) { 
        clearInterval(autoRefreshInterval); 
    } 
    autoRefreshInterval = setInterval(() => { 
        loadUserDashboardStats();
        loadMyTasks();
        loadMyProjectsProgress();
        loadTopProjects();
        refreshProjectsData(); 
    }, 60000); // 60000 ms = 1 minuto 
} 

function stopAutoRefresh() { 
    if (autoRefreshInterval) { 
        clearInterval(autoRefreshInterval); 
        autoRefreshInterval = null; 
    } 
} 

function loadUserDashboardStats() { 
    fetch(UserConfig.API_ENDPOINTS.GET_DASHBOARD_STATS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('Error en la respuesta de red'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.stats) { 
                updateUserDashboardStats(data.stats); 
            } else { 
                console.error('Error al cargar estadísticas:', data.message); 
            } 
        }) 
        .catch(error => { 
            console.error('Error al cargar estadísticas del dashboard:', error); 
        }); 
} 

function updateUserDashboardStats(stats) { 
    // Mis Proyectos
    const misProyectosEl = document.getElementById('stat-mis-proyectos');
    if (misProyectosEl) {
        misProyectosEl.textContent = stats.mis_proyectos || 0;
    }
    
    // Mis Tareas
    const misTareasEl = document.getElementById('stat-mis-tareas');
    if (misTareasEl) {
        misTareasEl.textContent = stats.mis_tareas || 0;
    }
    
    // Porcentaje de Tareas Completadas
    const tareasCompletadasEl = document.getElementById('stat-tareas-completadas');
    if (tareasCompletadasEl) {
        tareasCompletadasEl.textContent = (stats.porcentaje_tareas_completadas || 0) + '%';
    }
    
    // Tareas Pendientes
    const tareasPendientesEl = document.getElementById('stat-tareas-pendientes');
    if (tareasPendientesEl) {
        tareasPendientesEl.textContent = stats.tareas_pendientes || 0;
    }
    
    // Tareas Vencidas
    const tareasVencidasEl = document.getElementById('stat-tareas-vencidas');
    if (tareasVencidasEl) {
        tareasVencidasEl.textContent = stats.tareas_vencidas || 0;
    }

    // Actualizar gráfico de dona
    updateUserTasksChart(
        stats.tareas_pendientes || 0,
        stats.tareas_completadas || 0,
        stats.tareas_vencidas || 0
    );
}

function updateUserTasksChart(pendientes, completadas, vencidas) {
    const chartElement = document.getElementById('doughnutChart');
    
    if (!chartElement) {
        console.warn('Elemento doughnutChart no encontrado');
        return;
    }

    const ctx = chartElement.getContext('2d');
    
    // Destruir gráfico existente si existe
    if (userTasksChart !== null) {
        userTasksChart.destroy();
    }

    // Si no hay datos, mostrar gráfico vacío
    const total = pendientes + completadas + vencidas;
    let data, labels;
    
    if (total === 0) {
        // Mostrar un gráfico con mensaje "Sin datos"
        data = [1];
        labels = ['Sin tareas asignadas'];
    } else {
        data = [pendientes, completadas, vencidas];
        labels = ['Pendientes', 'Completadas', 'Vencidas'];
    }

    const chartData = {
        labels: labels,
        datasets: [{
            data: data,
            backgroundColor: total === 0 ? ['#F2994A'] : ['#F2C94C', '#009b4a', '#C62828'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };

    const options = {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '70%',
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    },
                    usePointStyle: true
                }
            },
            tooltip: {
                enabled: total > 0,
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += context.parsed;
                        return label;
                    }
                }
            }
        }
    };

    userTasksChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
}

function loadMyTasks() {
    fetch(UserConfig.API_ENDPOINTS.GET_MY_TASKS + '?limit=5')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayMyTasks(data.tareas);
            } else {
                console.warn('Aviso al cargar tareas:', data.message);
                displayEmptyTasksState();
            }
        })
        .catch(error => {
            console.error('Error al cargar mis tareas:', error);
            displayEmptyTasksState();
        });
}

function displayMyTasks(tareas) {
    const tableBody = document.getElementById('misTareasTableBody');
    
    if (!tableBody) {
        console.warn('Elemento #misTareasTableBody no encontrado');
        return;
    }

    tableBody.innerHTML = '';

    if (!tareas || tareas.length === 0) {
        displayEmptyTasksState();
        return;
    }

    tareas.forEach(tarea => {
        const row = document.createElement('tr');
        const statusBadge = getStatusBadge(tarea.estado);
        
        row.innerHTML = `
            <td>
                <strong>${escapeHtml(truncateText(tarea.nombre, 25))}</strong>
                <br>
                <small class="text-muted">${escapeHtml(truncateText(tarea.nombre_proyecto, 20))}</small>
            </td>
            <td>
                ${statusBadge}
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

function displayEmptyTasksState() {
    const tableBody = document.getElementById('misTareasTableBody');
    
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="2" class="text-center text-muted py-4">
                <i class="mdi mdi-clipboard-check-outline" style="font-size: 32px; opacity: 0.5;"></i>
                <p class="mt-2">No tienes tareas asignadas</p>
            </td>
        </tr>
    `;
}

function loadMyProjectsProgress() {
    fetch(UserConfig.API_ENDPOINTS.GET_MY_PROJECTS_PROGRESS)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayMyProjectsProgress(data.proyectos.slice(0, 5));
            } else {
                console.warn('Aviso al cargar progreso:', data.message);
                displayEmptyProjectsProgressState();
            }
        })
        .catch(error => {
            console.error('Error al cargar progreso de proyectos:', error);
            displayEmptyProjectsProgressState();
        });
}

function displayMyProjectsProgress(proyectos) {
    const tableBody = document.getElementById('misProyectosProgresoTableBody');
    
    if (!tableBody) {
        console.warn('Elemento #misProyectosProgresoTableBody no encontrado');
        return;
    }

    tableBody.innerHTML = '';

    if (!proyectos || proyectos.length === 0) {
        displayEmptyProjectsProgressState();
        return;
    }

    proyectos.forEach(proyecto => {
        const row = document.createElement('tr');
        const progressBar = createProgressBar(proyecto.mi_progreso);
        
        row.innerHTML = `
            <td>
                <strong>${escapeHtml(truncateText(proyecto.nombre, 20))}</strong>
                <br>
                <small class="text-muted">${proyecto.estado}</small>
            </td>
            <td>
                ${progressBar}
                <small class="text-muted d-block mt-1">
                    ${proyecto.mis_tareas_completadas}/${proyecto.mis_tareas_total} tareas
                </small>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

function displayEmptyProjectsProgressState() {
    const tableBody = document.getElementById('misProyectosProgresoTableBody');
    
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="2" class="text-center text-muted py-4">
                <i class="mdi mdi-folder-open-outline" style="font-size: 32px; opacity: 0.5;"></i>
                <p class="mt-2">No participas en proyectos activos</p>
            </td>
        </tr>
    `;
}

function loadTopProjects() {
    fetch(UserConfig.API_ENDPOINTS.GET_PROJECTS)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.proyectos) {
                displayTopProjects(data.proyectos.slice(0, 3));
            } else {
                console.warn('Aviso al cargar top proyectos:', data.message);
                displayEmptyTopProjectsState();
            }
        })
        .catch(error => {
            console.error('Error al cargar top proyectos:', error);
            displayEmptyTopProjectsState();
        });
}

function displayTopProjects(proyectos) {
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

    proyectos.forEach(proyecto => {
        const row = document.createElement('tr');
        const statusBadge = getStatusBadge(proyecto.estado);
        const progressBar = createProgressBar(proyecto.progreso || 0);
        
        row.innerHTML = `
            <td>
                <strong>${escapeHtml(truncateText(proyecto.nombre, 30))}</strong>
                <br>
                <small class="text-muted">${escapeHtml(proyecto.participante || 'Sin asignar')}</small>
            </td>
            <td>
                ${statusBadge}
            </td>
            <td>
                ${progressBar}
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
                <i class="mdi mdi-folder-open-outline" style="font-size: 32px; opacity: 0.5;"></i>
                <p class="mt-2">No hay proyectos disponibles</p>
            </td>
        </tr>
    `;
}

function cargarProyectos() { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    if(!tableBody) { 
        console.error('El elemento de cuerpo de tabla no fue encontrado'); 
        return; 
    } 

    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="7" class="text-center"> 
                <div class="spinner-border text-primary" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <p class="mt-2">Cargando proyectos...</p> 
            </td> 
        </tr> 
    `; 

    fetch(UserConfig.API_ENDPOINTS.GET_PROJECTS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json();
        }) 
        .then(data => { 
            if (data.success && data.proyectos) { 
                allProjects = data.proyectos; 
                filteredProjects = [...allProjects]; 
                currentPage = 1;
                displayProjects(data.proyectos);
            } else { 
                tableBody.innerHTML = ` 
                    <tr> 
                        <td colspan="7" class="text-center text-danger"> 
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
                    <td colspan="7" class="text-center text-danger"> 
                        <p class="mt-3">Error al cargar los proyectos: ${error.message}</p> 
                    </td> 
                </tr> 
            `; 
        }); 
} 

function refreshProjectsData() { 
    fetch(UserConfig.API_ENDPOINTS.GET_PROJECTS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.proyectos) { 
                allProjects = data.proyectos;
                filteredProjects = [...allProjects]; 
                
                if (currentSortColumn) { 
                    filteredProjects = sortProjects(filteredProjects, currentSortColumn, sortDirection); 
                } 
                
                const newTotalPages = calculatePages(filteredProjects);
                if (currentPage > newTotalPages && newTotalPages > 0) { 
                    currentPage = newTotalPages; 
                } 
                
                displayProjects(filteredProjects); 
            } 
        }) 
        .catch(error => { 
            console.error('Error al refrescar proyectos:', error); 
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
            icon.className = sortDirection === 'asc' ? 'mdi mdi-sort-ascending' : 'mdi mdi-sort-descending'; 
            header.style.fontWeight = 'bold'; 
            header.style.color = '#666666'; 
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
                <td colspan="7" class="text-center empty-state"> 
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
    
    // Resaltar si es un proyecto donde participo
    if (proyecto.es_mi_proyecto) {
        row.classList.add('table-info');
    }
    
    const statusBadge = getStatusBadge(proyecto.estado);
    const progressBar = createProgressBar(proyecto.progreso || 0); 

    row.innerHTML = ` 
        <td>${index}</td> 
        <td> 
            <strong>${truncateText(proyecto.nombre, 30)}</strong>
            ${proyecto.es_mi_proyecto ? '<br><small class="badge badge-info">Mi proyecto</small>' : ''}
        </td> 
        <td>${truncateText(proyecto.descripcion, 40)}</td> 
        <td>${formatDate(proyecto.fecha_cumplimiento)}</td> 
        <td> 
            ${progressBar} 
        </td> 
        <td> 
            ${statusBadge} 
        </td> 
        <td>${proyecto.participante || '-'}</td> 
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
            <div class="progress-bar ${progressClass}" role="progressbar" style="width: ${progressValue}%;"  
                 aria-valuenow="${progressValue}" aria-valuemin="0" aria-valuemax="100"> 
                ${progressValue}% 
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
    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="7" class="text-center empty-state"> 
                <i class="mdi mdi-folder-open" style="font-size: 48px; color: #e9e9e9;"></i> 
                <h5 class="mt-3">No hay proyectos en tu departamento</h5> 
                <p>Contacta a tu supervisor para más información</p> 
            </td> 
        </tr> 
    `; 
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


// Hacer funciones globalmente disponibles 
window.changePage = changePage; 
window.stopAutoRefresh = stopAutoRefresh;
window.startAutoRefresh = startAutoRefresh;
