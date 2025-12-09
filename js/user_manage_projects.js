/*user_manage_projects.js para manejar proyectos para usuarios normales*/ 

const Config = { 
    API_ENDPOINTS: { 
        DELETE: '../php/delete_project.php', 
        GET_PROJECT_USERS: '../php/get_project_user.php' 
    } 
}; 

let allProjects = []; 
let currentSortColumn = null; 
let sortDirection = 'asc'; 
let filteredProjects = []; 
let currentUserId = null; // ID del usuario actual 

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

document.addEventListener('DOMContentLoaded', function() { 
    initializeCustomDialogs(); 
    setupSearch(); 
    setupSorting(); 
    setupPagination(); 
    createProjectUsersModal(); 
    cargarProyectos(); 
    startAutoRefresh(); // Iniciar refresco cada minuto 
}); 

function startAutoRefresh() { 
    if (autoRefreshInterval) { 
        clearInterval(autoRefreshInterval); 
    } 

     

    autoRefreshInterval = setInterval(() => { 
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
    } 
} 

function refreshProjectsData() { 
    fetch('../php/user_get_projects.php') 
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

    if(!tableBody) { 
        console.error('El elemento de cuerpo de tabla no fue encontrado'); 
        return; 
    } 

    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="9" class="text-center"> 
                <div class="spinner-border text-primary" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <p class="mt-2">Cargando tus proyectos...</p> 
            </td> 
        </tr> 
    `;

    fetch('../php/user_get_projects.php') 
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

                // Guardar el ID del usuario para verificaciones de permisos 
                if (data.id_usuario) { 
                    currentUserId = data.id_usuario; 
                } 

                if (allProjects.length === 0) { 
                    displayEmptyState(); 
                } else { 
                    displayProjects(allProjects); 
                } 
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
                'mdi mdi-sort-ascending' : 'mdi mdi-sort-descending'; 
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

    infoText.innerHTML = ` 
        <p>Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${filteredProjects.length}</strong> proyectos</p> 
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

    // Verificar si el usuario actual es el creador del proyecto 
    const esCreador = currentUserId && proyecto.id_creador === currentUserId; 

    // Botón de ver usuarios solo para proyectos grupales 
    const viewUsersButton = proyecto.id_tipo_proyecto === 1 
        ? `<button class="btn btn-sm btn-info btn-action"  
                   onclick="viewProjectUsers(${proyecto.id_proyecto}, '${escapeHtml(proyecto.nombre)}')"  
                   title="Ver usuarios asignados"> 
               <i class="mdi mdi-account-multiple"></i> 
           </button>` 
        : ''; 

    // Solo mostrar botones de editar y eliminar si el usuario es el creador 
    const actionButtons = esCreador 
        ? `<div class="action-buttons"> 
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
           </div>` 
        : `<div class="action-buttons"> 
               <small class="text-muted d-block mt-1">Solo lectura</small> 
           </div>`; 

    row.innerHTML = ` 
        <td>${index}</td> 
        <td> 
            <strong>${truncateText(proyecto.nombre, 30)}</strong> 
            ${esCreador ? '<span class="badge badge-success ms-2" style="font-size: 0.7rem;">Creador</span>' : ''} 
        </td> 
        <td>${truncateText(proyecto.descripcion, 40)}</td> 
        <td>${proyecto.area || '-'}</td> 
        <td>${formatDate(proyecto.fecha_cumplimiento)}</td> 
        <td>${progressBar}</td> 
        <td>${statusBadge}</td> 
        <td>${proyecto.participante || '-'}</td> 
        <td>${actionButtons}</td> 
    `; 
    return row; 
} 

function createProgressBar(progress) { 
    const progressValue = parseInt(progress) || 0; 
    const progressClass =  
        progressValue >= 75 ? 'bg-success' : 
        progressValue >= 50 ? 'bg-info' : 
        progressValue >= 25 ? 'bg-warning' : 'bg-danger'; 
    
    return ` 
        <div class="progress" style="height: 20px;"> 
            <div class="progress-bar ${progressClass}"  
                 role="progressbar"  
                 style="width: ${progressValue}%;"  
                 aria-valuenow="${progressValue}"  
                 aria-valuemin="0"  
                 aria-valuemax="100"> 
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
    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="9" class="text-center empty-state"> 
                <i class="mdi mdi-folder-open" style="font-size: 48px; color: #e9e9e9;"></i> 
                <h5 class="mt-3">No tienes proyectos asignados</h5> 
                <p>Los proyectos que crees o en los que participes aparecerán aquí</p> 
                <a href="../nuevoProyectoUser/" class="btn btn-success mt-3"> 
                    <i class="mdi mdi-plus-circle-outline"></i> Crear proyecto 
                </a> 
            </td> 
        </tr> 
    `; 
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
            performSearch(this.value); 
        }, 300); 
    }); 
} 

function performSearch(query) { 
    const normalizedQuery = query.toLowerCase().trim(); 
    
    if (normalizedQuery === '') { 
        filteredProjects = [...allProjects]; 
        currentPage = 1; 
        const sorted = currentSortColumn 
            ? sortProjects(filteredProjects, currentSortColumn, sortDirection) 
            : filteredProjects; 
        displayProjects(sorted); 
        return; 
    } 

    const filtered = allProjects.filter(project => { 
        return project.nombre.toLowerCase().includes(normalizedQuery) || 
               (project.descripcion && project.descripcion.toLowerCase().includes(normalizedQuery)) || 
               (project.area && project.area.toLowerCase().includes(normalizedQuery)) || 
               (project.participante && project.participante.toLowerCase().includes(normalizedQuery)); 
    }); 

    filteredProjects = filtered; 
    currentPage = 1; 

    const sorted = currentSortColumn 
        ? sortProjects(filteredProjects, currentSortColumn, sortDirection) 
        : filteredProjects; 
    displayProjects(sorted); 

    if (sorted.length === 0) { 
        const tableBody = document.querySelector('#proyectosTableBody'); 
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

function editarProyecto(idProyecto) { 
    window.location.href = `../nuevoProyectoUser/?edit=${idProyecto}`; 
} 

function verDetallesProyecto(idProyecto) { 
    // Redirigir a una página de detalles (solo lectura) 
    showAlert('Esta funcionalidad estará disponible próximamente', 'info'); 
} 

function confirmDelete(id, nombre) { 
    showConfirm( 
        `¿Está seguro de que desea eliminar el proyecto "${escapeHtml(nombre)}"?\n\nEsta acción no se puede deshacer y eliminará todas las tareas asociadas.`, 
        function() { 
            deleteProject(id); 
        }, 
        'Confirmar eliminación', 
        { 
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
        body: JSON.stringify({ id_proyecto: id }) 
    }) 
    .then(response => response.json()) 
    .then(data => { 
        if (data.success) { 
            showSuccessAlert(data.message || 'Proyecto eliminado exitosamente'); 
            allProjects = allProjects.filter(u => u.id_proyecto != id); 
            filteredProjects = filteredProjects.filter(u => u.id_proyecto != id); 
            totalPages = calculatePages(filteredProjects); 

            if (currentPage > totalPages && totalPages > 0) { 
                currentPage = totalPages; 
            } 

            const sorted = currentSortColumn 
                ? sortProjects(filteredProjects, currentSortColumn, sortDirection) 
                : filteredProjects; 
            displayProjects(sorted); 
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
        <div class="modal fade" id="projectUsersModal" tabindex="-1" role="dialog"  
             aria-labelledby="projectUsersModalLabel" aria-hidden="true"> 
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
        modalElement.addEventListener('hidden.bs.modal', function () { 
            currentProjectIdForUsers = null; 
        }); 
    } 
} 

function createUserProgressBar(progress) { 
    const progressValue = parseInt(progress) || 0; 
    const progressClass =  
        progressValue >= 75 ? 'bg-success' : 
        progressValue >= 50 ? 'bg-info' : 
        progressValue >= 25 ? 'bg-warning' : 'bg-danger'; 
    return ` 
        <div class="d-flex align-items-center gap-2"> 
            <div class="progress flex-grow-1" style="height: 20px; min-width: 100px;"> 
                <div class="progress-bar ${progressClass}"  
                     role="progressbar"  
                     style="width: ${progressValue}%;"  
                     aria-valuenow="${progressValue}"  
                     aria-valuemin="0"  
                     aria-valuemax="100"> 
                    ${progressValue.toFixed(1)}% 
                </div> 
            </div> 
        </div> 
    `; 
} 

function viewProjectUsers(projectId, projectName) { 
    currentProjectIdForUsers = projectId; 
    const modal = new bootstrap.Modal(document.getElementById('projectUsersModal')); 
    document.getElementById('projectUsersModalLabel').textContent =  
        `Usuarios asignados a: ${projectName}`; 
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
            <td>${progressBar}</td> 
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
        <p class="mb-0">Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong>  
        de <strong>${totalUsers}</strong> usuarios</p> 
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

    const alertClass = type === 'success' ? 'alert-success' :  
                       type === 'info' ? 'alert-info' : 'alert-danger'; 
    const icon = type === 'success' ? 'mdi-check-circle' :  
                 type === 'info' ? 'mdi-information' : 'mdi-alert-circle'; 
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show`; 
    alertDiv.innerHTML = ` 
        <i class="mdi ${icon} me-2"></i> 
        ${message} 
        <button type="button" class="btn-close"  
                onclick="this.parentElement.style.display='none'"></button> 
    `; 

    alertDiv.style.display = 'block'; 
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); 

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
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; }); 
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
        'info': { icon: 'mdi-information-outline', class: 'bg-info text-white', btnClass: 'btn-info' }, 
        'warning': { icon: 'mdi-alert-outline', class: 'bg-warning text-white', btnClass: 'btn-warning' }, 
        'danger': { icon: 'mdi-alert-octagon-outline', class: 'bg-danger text-white', btnClass: 'btn-danger' }, 
        'success': { icon: 'mdi-check-circle-outline', class: 'bg-success text-white', btnClass: 'btn-success' } 
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

    }, { once: true }); 

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
window.verDetallesProyecto = verDetallesProyecto; 
window.changePage = changePage; 
window.showConfirm = showConfirm; 
window.viewProjectUsers = viewProjectUsers; 
window.stopAutoRefresh = stopAutoRefresh; 
window.startAutoRefresh = startAutoRefresh; 