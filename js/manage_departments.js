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
                showError(data.message || 'Error al cargar departamentos'); 
                displayEmptyState(); 
            } 
        }) 

        .catch(error => { 
            console.error('Error:', error); 
            showError('Error al conectar con el servidor'); 
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
                showError(data.message || 'Error al cargar usurios'); 
                displayEmptyState(); 
            } 
        }) 

        .catch(error => { 
            console.error('Error:', error); 
            showError('Error al conectar con el servidor'); 
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

    // Display creator's full name instead of ID
    const nombreCreador = dept.nombre_creador || 'N/A';

    tr.innerHTML = ` 
        <td><h6>${rowNumber}</h6></td> 
        <td><h6>${escapeHtml(dept.nombre)}</h6></td> 
        <td><h6>${escapeHtml(dept.descripcion)}</h6></td> 
        <td><h6>${escapeHtml(dept.id_creador || 'N/A')}</h6></td> 
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
        showError('Departamento no encontrado'); 
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
            showSuccess(data.message || 'Departamento actualizado exitosamente'); 
            if (editModal) { 
                editModal.hide(); 
            } 
            loadDepartments(); 
        } else { 
            showError(data.message || 'Error al actualizar departamento'); 
        } 
    }) 

    .catch(error => { 
        console.error('Error:', error); 
        showError('Error al conectar con el servidor'); 
    }) 

    .finally(() => { 
        btnUpdate.disabled = false; 
        btnUpdate.innerHTML = '<i class="mdi mdi-content-save"></i> Guardar Cambios'; 
    }); 
} 

function confirmDelete(id, nombre) { 
    if (confirm(`¿Está seguro de que desea eliminar el departamento "${nombre}"?\n\nEsta acción no se puede deshacer.`)) { 
        deleteDepartment(id); 
    } 
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
            showSuccess(data.message || 'Departamento eliminado exitosamente'); 
            allDepartments = allDepartments.filter(d => d.id_departamento != id);
            displayDepartments(allDepartments); 
        } else { 
            showError(data.message || 'Error al eliminar departamento'); 
        } 
    }) 

    .catch(error => { 
        console.error('Error:', error); 
        showError('Error al conectar con el servidor'); 
    }); 
} 
function showSuccess(message) { 
    showAlert('success', message); 
} 

function showError(message) { 
    showAlert('danger', message); 
} 

function showAlert(type, message) { 
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
window.editDepartment = editDepartment; 
window.confirmDelete = confirmDelete;