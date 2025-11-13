const Config = { 
    API_ENDPOINTS: {  
        DELETE: '../php/delete_users.php' 
    } 
}; 

let allUsuarios = []; //guardar todos los usuarios para filtrar posteriormente

document.addEventListener('DOMContentLoaded', function() {
    // inicializar
    console.clear();
    console.log('%cSistema de Gestión de Empleados v2.0', 'font-size: 16px; font-weight: bold; color: #28a745;');
    console.log('%c=====================================', 'color: #28a745;');
    console.log('Fecha/Hora:', new Date().toLocaleString());
    console.log('URL:', window.location.href);
    console.log('User Agent:', navigator.userAgent);
    console.log('%c=====================================', 'color: #28a745;');
    
    logAction('Página cargada - Inicializando sistema');
    
    loadUsuarios();//cargar usuarios al cargar la pagina
    
    const searchInput = document.getElementById('searchUser');//funcionalidad de buscar
    if (searchInput) {
        searchInput.addEventListener('input', filterUsuarios);
        console.log('Búsqueda inicializada');
    }
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');//marcar todas las checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
        console.log('Checkbox "Seleccionar todos" inicializado');
    }
    
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', handleSaveUserChanges);
        console.log('Formulario de edición inicializado');
    }
    
    const saveUserChanges = document.getElementById('saveUserChanges');//guardar boton d eguardar
    if (saveUserChanges) {
        saveUserChanges.addEventListener('click', handleSaveUserChanges);
        console.log('Botón "Guardar Cambios" inicializado');
    }
    
    console.log('%cSistema inicializado correctamente', 'color: #34b0aa; font-weight: bold;');
    console.log('%cConsola abierta: Presiona F12 para ver logs detallados', 'color: #17a2b8; font-style: italic;');
});

createCustomDialogSystem();

function loadUsuarios() {
    const tableBody = document.getElementById('usuariosTableBody');
    
    logAction('Cargando usuarios del servidor');
    
    fetch('../php/get_users.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.usuarios) {
            allUsuarios = data.usuarios;
            logAction('Usuarios cargados exitosamente', { 
                cantidad: data.usuarios.length,
                usuarios: data.usuarios.map(u => ({ id: u.id_usuario, nombre: u.nombre + ' ' + u.apellido }))
            });
            console.table(data.usuarios); //mostrar formato d etabla
            renderUsuariosTable(allUsuarios);
            showSuccess(`Se cargaron ${data.usuarios.length} usuarios`);
        } else {
            const errorMsg = data.message || 'Error desconocido';
            logAction('Error al cargar usuarios', { error: errorMsg });
            showError('Error al cargar usuarios: ' + errorMsg);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar usuarios</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error de conexión en loadUsuarios:', error);
        showError('Error de conexión: ' + error.message, error);
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión</td></tr>';
    });
}

function renderUsuariosTable(usuarios) {
    const tableBody = document.getElementById('usuariosTableBody');
    
    if (!usuarios || usuarios.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No hay usuarios registrados</td></tr>';
        return;
    }
    
    let html = '';
    
    usuarios.forEach(usuario => {
        const rolBadge = getRolBadge(usuario.id_rol);
        const nombreCompleto = `${usuario.nombre} ${usuario.apellido}`;
        
        html += `
            <tr>
                <td>
                    <div class="form-check form-check-flat mt-0">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input usuario-checkbox" data-user-id="${usuario.id_usuario}" aria-checked="false">
                            <i class="input-helper"></i>
                        </label>
                    </div>
                </td>
                <td>
                    <div class="d-flex">
                        <img src="../images/faces/face1.jpg" alt="" class="me-2">
                        <div>
                            <h6>${escapeHtml(nombreCompleto)}</h6>
                            <p>${usuario.num_empleado}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <h6>${getDepartamentoName(usuario.id_departamento)}</h6>
                    <p>${escapeHtml(usuario.usuario)}</p>
                </td>
                <td>
                    <h6>${getSuperiorName(usuario.id_superior)}</h6>
                </td>
                <td>
                    ${rolBadge}
                </td>
                <td class="action-buttons">
                    <button type="button" class="btn btn-sm btn-success btn-edit" data-user-id="${usuario.id_usuario}" data-nombre="${escapeHtml(usuario.nombre)}" data-apellido="${escapeHtml(usuario.apellido)}" data-usuario="${escapeHtml(usuario.usuario)}" data-email="${escapeHtml(usuario.e_mail)}" data-depart="${usuario.id_departamento}">
                        <i class="mdi mdi-pencil"></i> Editar
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-delete" data-user-id="${usuario.id_usuario}" data-nombre="${escapeHtml(nombreCompleto)}" onclick="confirmDelete(${usuario.id_usuario}, '${escapeHtml(usuario.nombre)}')">
                        <i class="mdi mdi-delete"></i> Eliminar
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    
    attachCheckboxListeners();//volver a unir listeners de eventos a los checkboxes y botones
    attachButtonListeners();
}

function getRolBadge(roleId) {
    const rolMap = {
        1: { class: 'badge-opacity-success', text: 'Administrador' },
        2: { class: 'badge-opacity-success', text: 'Gerente' },
        3: { class: 'badge-opacity-success', text: 'Usuario' },
        4: { class: 'badge-opacity-success', text: 'Practicante' }
    };
    
    const rol = rolMap[roleId] || { class: 'badge-opacity-secondary', text: 'Sin rol' };
    return `<div class="badge ${rol.class}">${rol.text}</div>`;
}

function getDepartamentoName(deptId) {
    const deptMap = {
        1: 'Departamento de TI',
        2: 'Recursos Humanos',
        3: 'Ventas',
        4: 'Operaciones'
    };
    return deptMap[deptId] || 'Departamento ' + deptId;
}

function getSuperiorName(superiorId) {
    if (!superiorId || superiorId === 0) return 'N/A';
    
    const superior = allUsuarios.find(u => u.id_usuario === superiorId);
    return superior ? `${superior.nombre} ${superior.apellido}` : 'N/A';
}

function filterUsuarios() {
    const searchInput = document.getElementById('searchUser').value.toLowerCase();
    
    if (!searchInput.trim()) {
        renderUsuariosTable(allUsuarios);
        logAction('Búsqueda cancelada', { resultados: allUsuarios.length });
        return;
    }
    
    const filtered = allUsuarios.filter(usuario => {
        const fullName = `${usuario.nombre} ${usuario.apellido}`.toLowerCase();
        const email = usuario.e_mail.toLowerCase();
        const numEmpleado = usuario.num_empleado.toString();
        const username = usuario.usuario.toLowerCase();
        
        return fullName.includes(searchInput) || 
               email.includes(searchInput) || 
               numEmpleado.includes(searchInput) ||
               username.includes(searchInput);
    });
    
    logAction('Búsqueda realizada', { 
        termino: searchInput,
        total_usuarios: allUsuarios.length,
        resultados_encontrados: filtered.length,
        usuarios: filtered.map(u => u.nombre + ' ' + u.apellido)
    });
    
    console.log(`Búsqueda: "${searchInput}" - ${filtered.length} resultados de ${allUsuarios.length}`);
    
    renderUsuariosTable(filtered);
}

function attachCheckboxListeners() {
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllCheckbox);
    });
}

function attachButtonListeners() {
    const editButtons = document.querySelectorAll('.btn-edit');//editar listeners de botones
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const nombre = this.getAttribute('data-nombre');
            const apellido = this.getAttribute('data-apellido');
            const usuario = this.getAttribute('data-usuario');
            const email = this.getAttribute('data-email');
            const depart = this.getAttribute('data-depart');
            openEditModal(userId, nombre, apellido, usuario, email, depart);
        });
    });
}

function toggleSelectAll(event) {
    const isChecked = event.target.checked;
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
    });
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    const someChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
    
    selectAllCheckbox.checked = allChecked;
    selectAllCheckbox.indeterminate = someChecked && !allChecked;
}


function openEditModal(userId, nombre, apellido, usuario, email, departId) {
    logAction('Abriendo modal de edición', { 
        userId: userId,
        nombre: nombre,
        apellido: apellido,
        usuario: usuario,
        email: email
    });
    document.getElementById('editUserId').value = userId;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editApellido').value = apellido;
    document.getElementById('editUsuario').value = usuario;
    document.getElementById('editEmail').value = email;
    document.getElementById('editDepartamento').value = getDepartamentoName(departId);    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function handleSaveUserChanges(event) {
    event.preventDefault();
    
    logAction('Guardando cambios de usuario');

    const validation = validateEditForm();//validar form
    if (!validation.isValid) {
        console.error('Validación fallida. Errores:', validation.errors);
        
        validation.errors.forEach(error => {//mostrar cada error
            showError(error);
        });
        return;
    }
    
    const userId = document.getElementById('editUserId').value;
    const nombre = document.getElementById('editNombre').value.trim();
    const apellido = document.getElementById('editApellido').value.trim();
    const usuario = document.getElementById('editUsuario').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    
    const data = {
        id_usuario: parseInt(userId),
        nombre: nombre,
        apellido: apellido,
        usuario: usuario,
        e_mail: email
    };

    logAction('Enviando datos al servidor', data);
    showInfo('Guardando cambios...');
    
    fetch('../php/update_users.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(responseData => {
        if (responseData.success) {
            logAction('Usuario actualizado exitosamente', responseData.usuario);
            showSuccess('Usuario actualizado exitosamente', responseData.usuario);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
            modal.hide();
            loadUsuarios(); //recargar la tabla
        } else {
            const errorMsg = responseData.message || responseData.error || 'Error desconocido';
            logAction('Error en actualización', { error: errorMsg });
            showError('Error al actualizar usuario: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('Error de conexión:', error);
        showError('Error de conexión: ' + error.message, error);
    });
}

function confirmDelete(id, nombre) { 
    showConfirm(
        `¿Está seguro de que desea eliminar el usuario "${escapeHtml(nombre)}"?\n\nEsta acción no se puede deshacer.`,
        function() {
            deleteUser(id);
        },
        'Confirmar eliminación',
        {
            type: 'danger',
            confirmText: 'Eliminar',
            cancelText: 'Cancelar'
        }
    );
} 

function deleteUser(id) {
    //se envia json en ves de data
    fetch(Config.API_ENDPOINTS.DELETE, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json' 
        },
        body: JSON.stringify({ id_usuario: id }) 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message || 'Usuario eliminado exitosamente');
            allUsuarios = allUsuarios.filter(u => u.id_usuario != id); 
            renderUsuariosTable(allUsuarios); 
        } else {
            showErrorAlert(data.message || 'Error al eliminar el usuario');
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



function showSuccess(message, data = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] hecho: ${message}`;
    
    console.log(logMessage);//logear en consola
    if (data) {
        console.log('Data:', data);
    }
    
    displayNotification(message, 'success');//mostrar notificacion en estilo de tostador
}

function showError(message, error = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ERROR: ${message}`;
    
    console.error(logMessage);//detalles de error en log
    if (error) {
        console.error('Error Details:', error);
    }
    displayNotification(message, 'error');
}

function showInfo(message) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}]INFO: ${message}`;
    
    console.info(logMessage);
    displayNotification(message, 'info');
}

function showWarning(message) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}]Advertencia: ${message}`;
    
    console.warn(logMessage);
    displayNotification(message, 'advertencia');
}

function displayNotification(message, type = 'info') {
    //crear contenedor para la notificacin si no existe
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(toastContainer);
    }

    const toastId = 'toast-' + Date.now();
    const bgColor = {//elementos de la notificacion
        'success': '#34b0aa',
        'error': '#f85e53',
        'info': '#17a2b8',
        'warning': '#ffc107'
    }[type] || '#17a2b8';

    const toastHTML = `
        <div id="${toastId}" style="
            background-color: ${bgColor};
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <span>${message}</span>
            <button style="
                background: none;
                border: none;
                color: inherit;
                cursor: pointer;
                font-size: 18px;
                margin-left: auto;
                padding: 0;
            " onclick="document.getElementById('${toastId}').remove()">×</button>
        </div>
        <style>
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        </style>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);

    setTimeout(() => {//esconder notificacion despues de 5seg
        const toastElement = document.getElementById(toastId);
        if (toastElement) {
            toastElement.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toastElement.remove(), 300);
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
    return text.replace(/[&<>"']/g, m => map[m]);
}

function logAction(action, details = {}) {
    const timestamp = new Date().toLocaleTimeString();
    console.group(`[${timestamp}] ${action}`);
    console.log('Timestamp:', new Date().toISOString());
    console.log('Action:', action);
    if (Object.keys(details).length > 0) {
        console.log('Details:', details);
    }
    console.groupEnd();
}

function validateEmail(email) {
    if (!email || email.trim() === '') {
        return { isValid: false, message: 'El email es requerido' };
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        return { isValid: false, message: 'Formato de email inválido (ej: usuario@ejemplo.com)' };
    }

    return { isValid: true, message: '' };
}

function validateTextField(value, fieldName = 'Campo', minLength = 1) {
    if (!value || value.trim() === '') {
        return { isValid: false, message: `${fieldName} es requerido` };
    }

    if (value.trim().length < minLength) {
        return { isValid: false, message: `${fieldName} debe tener al menos ${minLength} caracteres` };
    }

    if (value.trim().length > 100) {
        return { isValid: false, message: `${fieldName} no puede exceder 100 caracteres` };
    }

    return { isValid: true, message: '' };
}

function validateEditForm() {
    const errors = [];
    const nombre = document.getElementById('editNombre').value;
    const apellido = document.getElementById('editApellido').value;
    const usuario = document.getElementById('editUsuario').value;
    const email = document.getElementById('editEmail').value;

    console.group('Validando formulario de edición');

    const nombreValid = validateTextField(nombre, 'Nombre', 2);//validar nombre 
    if (!nombreValid.isValid) {
        errors.push(nombreValid.message);
        console.warn('Nombre inválido:', nombreValid.message);
    } else {
        console.log('Nombre válido:', nombre);
    }

    const apellidoValid = validateTextField(apellido, 'Apellido', 2);//validar apllido
    if (!apellidoValid.isValid) {
        errors.push(apellidoValid.message);
        console.warn('Apellido inválido:', apellidoValid.message);
    } else {
        console.log('Apellido válido:', apellido);
    }

    const usuarioValid = validateTextField(usuario, 'Usuario', 3);//validar suario
    if (!usuarioValid.isValid) {
        errors.push(usuarioValid.message);
        console.warn('Usuario inválido:', usuarioValid.message);
    } else {
        console.log('Usuario válido:', usuario);
    }

    const emailValid = validateEmail(email);//validar email
    if (!emailValid.isValid) {
        errors.push(emailValid.message);
        console.warn('Email inválido:', emailValid.message);
    } else {
        console.log('Email válido:', email);
    }

    console.groupEnd();

    return {
        isValid: errors.length === 0,
        errors: errors
    };
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

window.confirmDelete = confirmDelete;