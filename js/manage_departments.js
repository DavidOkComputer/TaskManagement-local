// configuracion, js para manejo de info de departamentos 
const Config = { 
    API_ENDPOINTS: { 
        LIST: '../php/get_departments.php', 
        LISTUSERS: '../php/get_users.php',
        UPDATE: '../php/update_departments.php', 
        DELETE: '../php/delete_department.php' 
    } 
}; 
//estadi 
let allDepartments = []; 
let editModal = null; 


document.addEventListener('DOMContentLoaded', function() { 
    initializePage();
}); 

createCustomDialogSystem();

function initializePage() { 
    loadDepartments(); 
    setupSearch(); 
    setupEditModal(); 
    setupEditForm(); 
    console.log('Department Management initialized'); 
} 

function loadDepartments() { 
    const tableBody = document.getElementById('departamentosTableBody'); 
    const loadingRow = document.getElementById('loadingRow'); 

    fetch(Config.API_ENDPOINTS.LIST) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('Error al cargar departamentos'); 
            } 
            return response.json(); 
        }) 

        .then(data => { 
            if (data.success) { 
                allDepartments = data.departamentos; 
                displayDepartments(allDepartments); 
            } else { 
                showErrorAlert(data.message || 'Error al cargar departamentos'); 
                displayEmptyState(); 
            } 
        }) 

        .catch(error => { 
            console.error('Error:', error); 
            showErrorAlert('Error al conectar con el servidor'); 
            displayEmptyState(); 
        }) 

        .finally(() => { 
            if (loadingRow) { 
                loadingRow.remove(); 
            } 
        }); 
} 

function loadusers() { 
    const tableBody = document.getElementById('departamentosTableBody'); 
    const loadingRow = document.getElementById('loadingRow'); 

    fetch(Config.API_ENDPOINTS.LISTUSERS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('Error al cargar usuarios'); 
            } 
            return response.json(); 
        }) 

        .then(data => { 
            if (data.success) { 
                allDepartments = data.departamentos; 
                displayDepartments(allDepartments); 
            } else { 
                showErrorAlert(data.message || 'Error al cargar usurios'); 
                displayEmptyState(); 
            } 
        }) 

        .catch(error => { 
            console.error('Error:', error); 
            showErrorAlert('Error al conectar con el servidor'); 
            displayEmptyState(); 
        }) 

        .finally(() => { 
            if (loadingRow) { 
                loadingRow.remove(); 
            } 
        }); 
} 

function displayDepartments(departamentos) { 
    const tableBody = document.getElementById('departamentosTableBody'); 
    if (!tableBody) return; 
    tableBody.innerHTML = ''; 

    if (departamentos.length === 0) { 
        displayEmptyState(); 
        return; 
    } 

    departamentos.forEach((dept, index) => { 
        const row = createDepartmentRow(dept, index + 1); 
        tableBody.appendChild(row); 
    }); 
} 

function createDepartmentRow(dept, rowNumber) { 
    const tr = document.createElement('tr'); 
    tr.dataset.id = dept.id_departamento; 

    //mostar el nombre del creador
    const nombreCreador = dept.nombre_creador || 'N/A';

    tr.innerHTML = ` 
        <td><h6>${rowNumber}</h6></td> 
        <td><h6>${escapeHtml(dept.nombre)}</h6></td> 
        <td><h6>${escapeHtml(dept.descripcion)}</h6></td> 
        <td><h6>${escapeHtml(dept.nombre_creador || 'N/A')}</h6></td> 
        <td class="text-center action-buttons"> 
            <button class="btn btn-sm btn-success btn-action" onclick="editDepartment(${dept.id_departamento})" title="Editar"> 
                <i class="mdi mdi-pencil"></i> Editar 
            </button> 
            <button class="btn btn-sm btn-danger btn-action" onclick="confirmDelete(${dept.id_departamento}, '${escapeHtml(dept.nombre)}')" title="Eliminar"> 
                <i class="mdi mdi-delete"></i> Eliminar 
            </button> 
        </td> 
    `; 
    return tr; 
} 

function displayEmptyState() { 
    const tableBody = document.getElementById('departamentosTableBody'); 
    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="5" class="empty-state"> 
                <i class="mdi mdi-folder-open"></i> 
                <h5>No hay departamentos registrados</h5> 
                <p>Comienza creando un nuevo departamento</p> 
                <a href="../registroDeDepartamentos" class="btn btn-success mt-3"> 
                    <i class="mdi mdi-plus-circle-outline"></i> Crear Departamento 
                </a> 
            </td> 
        </tr> 
    `; 
} 
function setupSearch() { 
    const searchInput = document.getElementById('searchInput'); 
    const searchForm = document.getElementById('searchForm'); 
     
    if (!searchInput) return; 
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
        displayDepartments(allDepartments); 
        return; 
    } 

    const filtered = allDepartments.filter(dept => { 
        return dept.nombre.toLowerCase().includes(normalizedQuery) || 
               dept.descripcion.toLowerCase().includes(normalizedQuery) ||
               (dept.nombre_creador && dept.nombre_creador.toLowerCase().includes(normalizedQuery));
    });
    displayDepartments(filtered); 

    if (filtered.length === 0) { 
        const tableBody = document.getElementById('departamentosTableBody'); 
        tableBody.innerHTML = ` 
            <tr> 
                <td colspan="5" class="empty-state"> 
                    <i class="mdi mdi-magnify"></i> 
                    <h5>No se encontraron resultados</h5> 
                    <p>No hay departamentos que coincidan con "${escapeHtml(query)}"</p> 
                </td> 
            </tr> 
        `; 
    } 
} 

function setupEditModal() { 
    const modalElement = document.getElementById('editModal'); 
    if (modalElement && typeof bootstrap !== 'undefined') { 
        editModal = new bootstrap.Modal(modalElement); 
    } 
} 

function editDepartment(id) { 
    const dept = allDepartments.find(d => d.id_departamento == id);
    if (!dept) { 
        showErrorAlert('Departamento no encontrado'); 
        return; 
    } 

    document.getElementById('edit_id_departamento').value = dept.id_departamento; 
    document.getElementById('edit_nombre').value = dept.nombre; 
    document.getElementById('edit_descripcion').value = dept.descripcion; 

    if (editModal) { 
        editModal.show(); 
    } 
} 

function setupEditForm() { 
    const form = document.getElementById('editForm'); 
    if (!form) return; 
    form.addEventListener('submit', function(e) { 
        e.preventDefault(); 
        updateDepartment(); 
    }); 
} 

function updateDepartment() { 
    const form = document.getElementById('editForm'); 
    const formData = new FormData(form); 
    const btnUpdate = document.getElementById('btnUpdateDepartment'); 

    btnUpdate.disabled = true; 
    btnUpdate.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Guardando...'; 

    fetch(Config.API_ENDPOINTS.UPDATE, { 
        method: 'POST', 
        body: formData 
    }) 

    .then(response => response.json()) 
    .then(data => { 
        if (data.success) { 
            showSuccessAlert(data.message || 'Departamento actualizado exitosamente'); 
            if (editModal) { 
                editModal.hide(); 
            } 
            loadDepartments(); 
        } else { 
            showErrorAlert(data.message || 'Error al actualizar departamento'); 
        } 
    }) 

    .catch(error => { 
        console.error('Error:', error); 
        showErrorAlert('Error al conectar con el servidor'); 
    }) 

    .finally(() => { 
        btnUpdate.disabled = false; 
        btnUpdate.innerHTML = '<i class="mdi mdi-content-save"></i> Guardar Cambios'; 
    }); 
} 

function confirmDelete(id, nombre) { 
    showConfirm(
        `¿Está seguro de que desea eliminar el departamento "${escapeHtml(nombre)}"?\n\nEsta acción no se puede deshacer.`,
        function() {
            deleteDepartment(id);
        },
        'Confirmar eliminación',
        {
            type: 'danger',
            confirmText: 'Eliminar',
            cancelText: 'Cancelar'
        }
    );
} 

function deleteDepartment(id) { 
    const formData = new FormData(); 
    formData.append('id_departamento', id); 

    fetch(Config.API_ENDPOINTS.DELETE, { 
        method: 'POST', 
        body: formData 
    }) 

    .then(response => response.json()) 
    .then(data => { 
        if (data.success) { 
            showSuccessAlert(data.message || 'Departamento eliminado exitosamente'); 
            allDepartments = allDepartments.filter(d => d.id_departamento != id);
            displayDepartments(allDepartments); 
        } else { 
            showErrorAlert(data.message || 'Error al eliminar departamento'); 
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
    if (!alertDiv) return; 
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
        <!-- Custom Alert Dialog -->
        <div class="modal fade" id="customAlertModal" tabindex="-1" role="dialog" aria-labelledby="customAlertLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="customAlertLabel">
                            <i class="mdi mdi-information-outline me-2"></i>
                            <span id="alertTitle">Información</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="alertMessage" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
        
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

// mostrar dialogo de confirmacion de la app y no navegador
function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
    const modal = document.getElementById('customConfirmModal');
    const titleElement = document.getElementById('confirmTitle');
    const messageElement = document.getElementById('confirmMessage');
    const headerElement = modal.querySelector('.modal-header');
    const iconElement = modal.querySelector('.modal-title i');
    const confirmBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    
    //opciones default
    const config = {
        confirmText: 'Aceptar',
        cancelText: 'Cancelar',
        type: 'warning',
        ...options
    };
    
    //titulo y mensaje
    titleElement.textContent = title;
    messageElement.innerHTML = message.replace(/\n/g, '<br>'); // Preserve line breaks
    
    //cambiar el texto de los botones
    confirmBtn.textContent = config.confirmText;
    cancelBtn.textContent = config.cancelText;
    
    //clases del header
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
    iconElement.className = `mdi ${typeConfig.icon} me-2`;
    headerElement.classList.add(...typeConfig.class.split(' '));
    
    //actualizar el estilo del boton confirmar
    confirmBtn.className = `btn ${typeConfig.btnClass}`;
    
    //eliminar listeners anteriores clonando y remplazando
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    const newCancelBtn = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    
    //agregar nuevo event listener
    newConfirmBtn.addEventListener('click', function() {
        const confirmModal = bootstrap.Modal.getInstance(modal);
        confirmModal.hide();
        if (onConfirm && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
    
    //mostrar modal
    const confirmModal = new bootstrap.Modal(modal);
    confirmModal.show();
}

window.editDepartment = editDepartment; 
window.confirmDelete = confirmDelete;