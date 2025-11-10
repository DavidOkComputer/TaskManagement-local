// gestionDeEmpleados.js
// Employee management functionality with CRUD operations

let allUsuarios = []; // Store all users for filtering

document.addEventListener('DOMContentLoaded', function() {
    // Initialize logging
    console.clear();
    console.log('%c👋 Sistema de Gestión de Empleados v2.0', 'font-size: 16px; font-weight: bold; color: #28a745;');
    console.log('%c=====================================', 'color: #28a745;');
    console.log('📅 Fecha/Hora:', new Date().toLocaleString());
    console.log('🌐 URL:', window.location.href);
    console.log('📱 User Agent:', navigator.userAgent);
    console.log('%c=====================================', 'color: #28a745;');
    
    logAction('Página cargada - Inicializando sistema');
    
    // Load users on page load
    loadUsuarios();
    
    // Search functionality
    const searchInput = document.getElementById('searchUser');
    if (searchInput) {
        searchInput.addEventListener('input', filterUsuarios);
        console.log('✓ Búsqueda inicializada');
    }
    
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
        console.log('✓ Checkbox "Seleccionar todos" inicializado');
    }
    
    // Edit form submission
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', handleSaveUserChanges);
        console.log('✓ Formulario de edición inicializado');
    }
    
    // Save changes button
    const saveUserChanges = document.getElementById('saveUserChanges');
    if (saveUserChanges) {
        saveUserChanges.addEventListener('click', handleSaveUserChanges);
        console.log('✓ Botón "Guardar Cambios" inicializado');
    }
    
    console.log('%c✅ Sistema inicializado correctamente', 'color: #28a745; font-weight: bold;');
    console.log('%c💡 Consola abierta: Presiona F12 para ver logs detallados', 'color: #17a2b8; font-style: italic;');
});

/**
 * Load all users from the API
 */
function loadUsuarios() {
    const tableBody = document.getElementById('usuariosTableBody');
    
    logAction('Cargando usuarios del servidor');
    
    fetch('../api/get_users.php', {
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
            console.table(data.usuarios); // Display in table format
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
        console.error('❌ Error de conexión en loadUsuarios:', error);
        showError('Error de conexión: ' + error.message, error);
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión</td></tr>';
    });
}

/**
 * Render the users table with data
 * @param {Array} usuarios - Array of user objects to display
 */
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
                    <p>${escapeHtml(usuario.e_mail)}</p>
                </td>
                <td>
                    ${rolBadge}
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-warning me-2 btn-edit" data-user-id="${usuario.id_usuario}" data-nombre="${escapeHtml(usuario.nombre)}" data-apellido="${escapeHtml(usuario.apellido)}" data-usuario="${escapeHtml(usuario.usuario)}" data-email="${escapeHtml(usuario.e_mail)}" data-depart="${usuario.id_departamento}">
                            <i class="mdi mdi-pencil"></i> Editar
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-delete" data-user-id="${usuario.id_usuario}" data-nombre="${escapeHtml(nombreCompleto)}">
                            <i class="mdi mdi-delete"></i> Eliminar
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    
    // Re-attach event listeners to checkboxes and buttons
    attachCheckboxListeners();
    attachButtonListeners();
}

/**
 * Get the appropriate role badge HTML
 * @param {number} roleId - The role ID
 * @returns {string} HTML for the badge
 */
function getRolBadge(roleId) {
    const rolMap = {
        1: { class: 'badge-opacity-danger', text: 'Administrador' },
        2: { class: 'badge-opacity-success', text: 'Usuario' },
        3: { class: 'badge-opacity-warning', text: 'Supervisor' },
        4: { class: 'badge-opacity-info', text: 'Practicante' }
    };
    
    const rol = rolMap[roleId] || { class: 'badge-opacity-secondary', text: 'Sin rol' };
    return `<div class="badge ${rol.class}">${rol.text}</div>`;
}

/**
 * Get department name (placeholder - can be replaced with actual department lookup)
 * @param {number} deptId - The department ID
 * @returns {string} Department name
 */
function getDepartamentoName(deptId) {
    const deptMap = {
        1: 'Departamento de TI',
        2: 'Recursos Humanos',
        3: 'Ventas',
        4: 'Operaciones'
    };
    return deptMap[deptId] || 'Departamento ' + deptId;
}

/**
 * Get supervisor name (placeholder - can be replaced with actual supervisor lookup)
 * @param {number} superiorId - The superior/supervisor ID
 * @returns {string} Superior name
 */
function getSuperiorName(superiorId) {
    if (!superiorId || superiorId === 0) return 'N/A';
    
    const superior = allUsuarios.find(u => u.id_usuario === superiorId);
    return superior ? `${superior.nombre} ${superior.apellido}` : 'N/A';
}

/**
 * Filter users based on search input
 */
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
    
    console.log(`🔍 Búsqueda: "${searchInput}" - ${filtered.length} resultados de ${allUsuarios.length}`);
    
    renderUsuariosTable(filtered);
}

/**
 * Attach event listeners to checkboxes
 */
function attachCheckboxListeners() {
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllCheckbox);
    });
}

/**
 * Attach event listeners to edit and delete buttons
 */
function attachButtonListeners() {
    // Edit button listeners
    const editButtons = document.querySelectorAll('.btn-edit');
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
    
    // Delete button listeners
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const nombre = this.getAttribute('data-nombre');
            
            deleteUsuario(userId, nombre);
        });
    });
}

/**
 * Toggle select all checkboxes
 */
function toggleSelectAll(event) {
    const isChecked = event.target.checked;
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
    });
}

/**
 * Update the select all checkbox based on individual checkboxes
 */
function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    const someChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
    
    selectAllCheckbox.checked = allChecked;
    selectAllCheckbox.indeterminate = someChecked && !allChecked;
}

/**
 * Open the edit user modal and populate with user data
 * @param {number} userId - User ID
 * @param {string} nombre - User first name
 * @param {string} apellido - User last name
 * @param {string} usuario - Username
 * @param {string} email - User email
 * @param {number} departId - Department ID
 */
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

/**
 * Save user changes
 */
function handleSaveUserChanges(event) {
    event.preventDefault();
    
    logAction('Guardando cambios de usuario');

    // Validate form
    const validation = validateEditForm();
    if (!validation.isValid) {
        console.error('❌ Validación fallida. Errores:', validation.errors);
        
        // Show each error
        validation.errors.forEach(error => {
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
    
    fetch('../api/update_user.php', {
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
            loadUsuarios(); // Reload the table
        } else {
            const errorMsg = responseData.message || responseData.error || 'Error desconocido';
            logAction('Error en actualización', { error: errorMsg });
            showError('Error al actualizar usuario: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('❌ Error de conexión:', error);
        showError('Error de conexión: ' + error.message, error);
    });
}

/**
 * Delete a user with confirmation
 * @param {number} userId - User ID to delete
 * @param {string} userName - User name for confirmation message
 */
function deleteUsuario(userId, userName) {
    logAction('Iniciando proceso de eliminación', { userId: userId, userName: userName });
    
    if (confirm(`¿Estás seguro de que deseas eliminar a ${userName}? Esta acción no se puede deshacer.`)) {
        logAction('Eliminación confirmada por usuario', { userId: userId, userName: userName });
        
        const data = {
            id_usuario: parseInt(userId)
        };
        
        showInfo('Eliminando usuario...');
        
        fetch('../api/delete_user.php', {
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
                logAction('Usuario eliminado exitosamente', { userId: userId, userName: userName });
                showSuccess(`Usuario "${userName}" eliminado exitosamente`);
                loadUsuarios(); // Reload the table
            } else {
                const errorMsg = responseData.message || responseData.error || 'Error desconocido';
                logAction('Error en eliminación', { userId: userId, error: errorMsg });
                showError('Error al eliminar usuario: ' + errorMsg);
            }
        })
        .catch(error => {
            console.error('❌ Error de conexión en deleteUsuario:', error);
            showError('Error de conexión: ' + error.message, error);
        });
    } else {
        logAction('Eliminación cancelada por usuario', { userId: userId, userName: userName });
        console.log('ℹ️ Usuario canceló la eliminación');
    }
}

/**
 * Show success message to user and log to console
 * @param {string} message - Success message
 * @param {object} data - Additional data to log
 */
function showSuccess(message, data = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ✅ SUCCESS: ${message}`;
    
    // Log to console
    console.log(logMessage);
    if (data) {
        console.log('Data:', data);
    }
    
    // Show toast notification to user
    displayNotification(message, 'success');
}

/**
 * Show error message to user and log to console
 * @param {string} message - Error message
 * @param {object} error - Error object or additional details
 */
function showError(message, error = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ❌ ERROR: ${message}`;
    
    // Log to console
    console.error(logMessage);
    if (error) {
        console.error('Error Details:', error);
    }
    
    // Show notification to user
    displayNotification(message, 'error');
}

/**
 * Show info message to user and log to console
 * @param {string} message - Info message
 */
function showInfo(message) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ℹ️ INFO: ${message}`;
    
    console.info(logMessage);
    displayNotification(message, 'info');
}

/**
 * Show warning message to user and log to console
 * @param {string} message - Warning message
 */
function showWarning(message) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ⚠️ WARNING: ${message}`;
    
    console.warn(logMessage);
    displayNotification(message, 'warning');
}

/**
 * Display notification to user using Bootstrap toast
 * @param {string} message - Message to display
 * @param {string} type - Type: success, error, info, warning
 */
function displayNotification(message, type = 'info') {
    // Create toast container if it doesn't exist
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

    // Create toast element
    const toastId = 'toast-' + Date.now();
    const bgColor = {
        'success': '#28a745',
        'error': '#dc3545',
        'info': '#17a2b8',
        'warning': '#ffc107'
    }[type] || '#17a2b8';

    const textColor = type === 'warning' ? '#000' : '#fff';
    const icon = {
        'success': '✓',
        'error': '✕',
        'info': 'ℹ',
        'warning': '⚠'
    }[type] || 'ℹ';

    const toastHTML = `
        <div id="${toastId}" style="
            background-color: ${bgColor};
            color: ${textColor};
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <span style="font-weight: bold; font-size: 18px;">${icon}</span>
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

    // Auto-remove toast after 5 seconds
    setTimeout(() => {
        const toastElement = document.getElementById(toastId);
        if (toastElement) {
            toastElement.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toastElement.remove(), 300);
        }
    }, 5000);
}

/**
 * Escape HTML characters to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
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

/**
 * Log user action to console with timestamp
 * @param {string} action - Action performed
 * @param {object} details - Additional details
 */
function logAction(action, details = {}) {
    const timestamp = new Date().toLocaleTimeString();
    console.group(`[${timestamp}] 📋 ${action}`);
    console.log('Timestamp:', new Date().toISOString());
    console.log('Action:', action);
    if (Object.keys(details).length > 0) {
        console.log('Details:', details);
    }
    console.groupEnd();
}

/**
 * Validate email format
 * @param {string} email - Email to validate
 * @returns {object} {isValid: boolean, message: string}
 */
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

/**
 * Validate text field
 * @param {string} value - Value to validate
 * @param {string} fieldName - Field name for error message
 * @param {number} minLength - Minimum length
 * @returns {object} {isValid: boolean, message: string}
 */
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

/**
 * Validate edit form
 * @returns {object} {isValid: boolean, errors: array}
 */
function validateEditForm() {
    const errors = [];
    const nombre = document.getElementById('editNombre').value;
    const apellido = document.getElementById('editApellido').value;
    const usuario = document.getElementById('editUsuario').value;
    const email = document.getElementById('editEmail').value;

    console.group('🔍 Validando formulario de edición');

    // Validate nombre
    const nombreValid = validateTextField(nombre, 'Nombre', 2);
    if (!nombreValid.isValid) {
        errors.push(nombreValid.message);
        console.warn('❌ Nombre inválido:', nombreValid.message);
    } else {
        console.log('✓ Nombre válido:', nombre);
    }

    // Validate apellido
    const apellidoValid = validateTextField(apellido, 'Apellido', 2);
    if (!apellidoValid.isValid) {
        errors.push(apellidoValid.message);
        console.warn('❌ Apellido inválido:', apellidoValid.message);
    } else {
        console.log('✓ Apellido válido:', apellido);
    }

    // Validate usuario
    const usuarioValid = validateTextField(usuario, 'Usuario', 3);
    if (!usuarioValid.isValid) {
        errors.push(usuarioValid.message);
        console.warn('❌ Usuario inválido:', usuarioValid.message);
    } else {
        console.log('✓ Usuario válido:', usuario);
    }

    // Validate email
    const emailValid = validateEmail(email);
    if (!emailValid.isValid) {
        errors.push(emailValid.message);
        console.warn('❌ Email inválido:', emailValid.message);
    } else {
        console.log('✓ Email válido:', email);
    }

    console.groupEnd();

    return {
        isValid: errors.length === 0,
        errors: errors
    };
}