/**manage projects maneja la carga y muestra todos los proyectos de la tabla con botones de accion*/ 

const Config = { 

    API_ENDPOINTS: { 

        DELETE: '../php/delete_project.php' 

    } 

}; 

let allProjects = []; 

document.addEventListener('DOMContentLoaded', function() { 
    createCustomDialogSystem(); 
    setupSearch(); 
    cargarProyectos(); // cargar proyectos cuando carga la pagina
}); 

function cargarProyectos() { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    if(!tableBody) { 
        console.error('El elemento de cuerpo de tabla no fue encontrado'); 
        return; 
    } 

    //mostrar estado de carga 
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
            console.log('Informacion recivida:', data); //debug
            if (data.success && data.proyectos) { 
                allProjects = data.proyectos; //almacenar para funcion de buscar
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

function displayProjects(proyectos) { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    if(!tableBody) return; 
    tableBody.innerHTML = ''; 
    if(!proyectos || proyectos.length === 0) { 
        displayEmptyState(); 
        return; 
    } 
    proyectos.forEach((project, index) => { 
        const row = createProjectRow(project, index + 1); 
        tableBody.appendChild(row); 
    }); 
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
    const searchForm = document.getElementById('searchForm'); 
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
        displayProjects(allProjects); 
        return; 
    } 
    const filtered = allProjects.filter(project => { 
        return project.nombre.toLowerCase().includes(normalizedQuery) ||  
               (project.descripcion && project.descripcion.toLowerCase().includes(normalizedQuery)) ||  
               (project.area && project.area.toLowerCase().includes(normalizedQuery)) || 
               (project.participante && project.participante.toLowerCase().includes(normalizedQuery)); 
    }); 
    displayProjects(filtered); 
    if (filtered.length === 0) { 
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

            displayProjects(allProjects); 

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

 

function createCustomDialogSystem() { 

    const dialogHTML = ` 

        <!-- Custom Confirm Dialog --> 

        <div class="modal fade" id="customConfirmModal" tabindex="-1" role="dialog" aria-labelledby="customConfirmLabel" aria-hidden="true"> 

            <div class="modal-dialog modal-dialog-centered" role="document"> 

                <div class="modal-content"> 

                    <div class="modal-header"> 

                        <h5 class="modal-title" id="customConfirmLabel"> 

                            <i class="mdi mdi-help-circle-outline me-2"></i> 

                            <span id="confirmTitle">Confirmar acción</span> 

                        </h5> 

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> 

                    </div> 

                    <div class="modal-body"> 

                        <p id="confirmMessage" class="mb-0"></p> 

                    </div> 

                    <div class="modal-footer"> 

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmCancelBtn">Cancelar</button> 

                        <button type="button" class="btn btn-primary" id="confirmOkBtn">Aceptar</button> 

                    </div> 

                </div> 

            </div> 

        </div> 

    `; 

     

    document.body.insertAdjacentHTML('beforeend', dialogHTML); 

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
// hacer las funciones globalmente disponibles
window.confirmDelete = confirmDelete; 
window.editarProyecto = editarProyecto; 