/**manage projects maneja la carga y muestra todos los proyectos de la tabla con botones de accion*/ 

const Config = { 
    API_ENDPOINTS: { 
        DELETE: '../php/delete_project.php' 
    } 
}; 

let allProjects = [];
let currentSortColumn = null;
let sortDirection = 'asc';
let filteredProjects = [];

//variables de paginacion
let currentPage = 1;
let rowsPerPage = 10;
let totalPages = 0;

document.addEventListener('DOMContentLoaded', function() { 
    setupSearch(); 
    setupSorting();
    setupPagination(); // inicializar paginacion
    cargarProyectos();
}); 

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
                <p class="mt-2">Cargando proyectos...</p> 
            </td> 
        </tr> 
    `; 

    fetch('../php/get_projects.php') 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            console.log('Informacion recivida:', data);
            if (data.success && data.proyectos) { 
                allProjects = data.proyectos;
                filteredProjects = [...allProjects];
                currentPage = 1; // Reiniciar a la primera pagina al cargar
                displayProjects(data.proyectos); 
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
            currentPage = 1; //reiniciar a la primera pagina al hacer sort
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
            icon.className = sortDirection === 'asc' 
                ? 'mdi mdi-sort-ascending' 
                : 'mdi mdi-sort-descending';
            header.style.fontWeight = 'bold';
            header.style.color = '#007bff';
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
            currentPage = 1; //reiniciar a primera pagina cuano cambien registros por pagina
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

    //limpiar paginacion existente
    paginationContainer.innerHTML = '';

    //crear texto de info de paginacion
    const infoText = document.createElement('div');
    infoText.className = 'pagination-info';
    const startItem = ((currentPage - 1) * rowsPerPage) + 1;
    const endItem = Math.min(currentPage * rowsPerPage, filteredProjects.length);
    infoText.innerHTML = `
        <p>Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${filteredProjects.length}</strong> proyectos</p>
    `;
    paginationContainer.appendChild(infoText);

    //crear contenedores de botones de paginacion 
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'pagination-buttons';

    //boton anterior
    const prevBtn = document.createElement('button');
    prevBtn.className = 'btn btn-sm btn-outline-primary';
    prevBtn.innerHTML = '<i class="mdi mdi-chevron-left"></i> Anterior';
    prevBtn.disabled = currentPage === 1;
    prevBtn.addEventListener('click', () => changePage(currentPage - 1));
    buttonContainer.appendChild(prevBtn);

    //numero de paginas
    const pageButtonsContainer = document.createElement('div');
    pageButtonsContainer.className = 'page-buttons';

    //calcular que paginas mostrar
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    //ajustar si esta cerca del principio o final
    if (currentPage <= 3) {
        endPage = Math.min(totalPages, 5);
    }
    if (currentPage > totalPages - 3) {
        startPage = Math.max(1, totalPages - 4);
    }

    if (startPage > 1) {//boton de primera pagina
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

    //numero de paginas
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `btn btn-sm page-btn ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => changePage(i));
        pageButtonsContainer.appendChild(pageBtn);
    }

    if (endPage < totalPages) {//boton de ultima pagina
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

    //boton siguiente
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

    //calcular paginacion
    totalPages = calculatePages(proyectos);
    if (currentPage > totalPages && totalPages > 0) {
        currentPage = totalPages;
    }

    //obtener proyectos paginados
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

    //actualizar controles de paginacion
    updatePaginationControls();
} 

function createProjectRow(proyecto, index) { 
    const row = document.createElement('tr'); 
    const statusColor = getStatusColor(proyecto.estado); 
    const statusBadge = `<span class="badge badge-${statusColor}">${proyecto.estado || 'N/A'}</span>`; 
    const progressBar = createProgressBar(proyecto.progreso || 0); 
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
                <i class="mdi mdi-folder-open" style="font-size: 48px; color: #ccc;"></i> 
                <h5 class="mt-3">No hay proyectos registrados</h5> 
                <p>Comienza creando un nuevo proyecto</p> 
                <a href="../nuevoProyecto/" class="btn btn-success mt-3"> 
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
        currentPage = 1; //reiniciar a la primer pagina cuando se limpie la busqueda
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
    currentPage = 1; //reiniciar a primer pagina cuando se busca
    
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
    window.location.href = `../nuevoProyecto/?edit=${idProyecto}`; 
} 

function confirmDelete(id, nombre) { 
    showConfirm( 
        `¿Está seguro de que desea eliminar el proyecto "${escapeHtml(nombre)}"?\n\nEsta acción no se puede deshacer.`, 
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
            //recalcular paginas despues de eliminar
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
    const titleElement = document.getElementById('confirmTitle'); 
    const messageElement = document.getElementById('confirmMessage'); 
    const headerElement = modal.querySelector('.modal-header'); 
    const iconElement = modal.querySelector('.modal-title i'); 
    const confirmBtn = document.getElementById('confirmOkBtn'); 
    const cancelBtn = document.getElementById('confirmCancelBtn'); 

    const config = { 
        confirmText: 'Aceptar', 
        cancelText: 'Cancelar', 
        type: 'warning', 
        ...options 
    }; 

    titleElement.textContent = title; 
    messageElement.innerHTML = message.replace(/\n/g, '<br>'); 
    confirmBtn.textContent = config.confirmText; 
    cancelBtn.textContent = config.cancelText; 
    headerElement.className = 'modal-header'; 

    const iconMap = { 
        'info': { icon: 'mdi-information-outline', class: 'bg-info text-white', btnClass: 'btn-info' }, 
        'warning': { icon: 'mdi-alert-outline', class: 'bg-warning text-white', btnClass: 'btn-warning' }, 
        'danger': { icon: 'mdi-alert-octagon-outline', class: 'bg-danger text-white', btnClass: 'btn-danger' }, 
        'success': { icon: 'mdi-check-circle-outline', class: 'bg-success text-white', btnClass: 'btn-success' } 
    }; 

    const typeConfig = iconMap[config.type] || iconMap['warning']; 
    iconElement.className = `mdi ${typeConfig.icon} me-2`; 
    headerElement.classList.add(...typeConfig.class.split(' ')); 
    confirmBtn.className = `btn ${typeConfig.btnClass}`; 
    const newConfirmBtn = confirmBtn.cloneNode(true); 
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn); 
    const newCancelBtn = cancelBtn.cloneNode(true); 
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn); 
    newConfirmBtn.addEventListener('click', function() { 
        const confirmModal = bootstrap.Modal.getInstance(modal); 
        confirmModal.hide(); 
        if (onConfirm && typeof onConfirm === 'function') { 
            onConfirm(); 
        } 
    }); 
    const confirmModal = new bootstrap.Modal(modal); 
    confirmModal.show(); 

} 

//hacer funciones globalmente disponibles
window.confirmDelete = confirmDelete; 
window.editarProyecto = editarProyecto;
window.changePage = changePage;
window.showConfirm = showConfirm;