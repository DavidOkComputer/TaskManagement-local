// user_roles_manager.js - Gestión de múltiples roles por usuario 
(function() {
	'use strict';
	// Estado de la aplicación 
	const rolesManager = {
		currentUserId: null,
		currentUserName: '',
		userRoles: [],
		availableDepartments: [],
		allRoles: []
	};
	// Inicialización cuando se abre el modal 
	window.openRolesManager = function(userId, userName) {
		rolesManager.currentUserId = userId;
		rolesManager.currentUserName = userName;
		// Actualizar título del modal 
		const modalTitle = document.getElementById('rolesManagerTitle');
		if (modalTitle) {
			modalTitle.textContent = `Gestionar Roles - ${userName}`;
		}
		// Cargar datos 
		loadUserRoles(userId);
		loadAllRoles();
		// Mostrar modal 
		const modal = new bootstrap.Modal(document.getElementById('rolesManagerModal'));
		modal.show();
	};
	// Cargar roles actuales del usuario 
	function loadUserRoles(userId) {
		const container = document.getElementById('currentRolesList');
		if (!container) return;
		container.innerHTML = ` 
            <div class="text-center py-3"> 
                <div class="spinner-border spinner-border-sm text-primary" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <p class="mt-2 mb-0 text-muted small">Cargando roles...</p> 
            </div> 

        `;
		fetch(`../php/get_user_roles.php?id_usuario=${userId}`)
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					rolesManager.userRoles = data.roles;
					renderUserRoles(data.roles);
					loadAvailableDepartments(userId);
				} else {
					container.innerHTML = ` 
                        <div class="alert alert-danger"> 
                            <i class="mdi mdi-alert-circle me-2"></i>${data.message} 
                        </div> 
                    `;
				}
			})
			.catch(error => {
				console.error('Error:', error);
				container.innerHTML = ` 
                    <div class="alert alert-danger"> 
                        <i class="mdi mdi-alert-circle me-2"></i>Error de conexión 
                    </div> 
                `;
			});
	}
	// Renderizar lista de roles del usuario 
	function renderUserRoles(roles) {
		const container = document.getElementById('currentRolesList');
		if (!container) return;
		if (roles.length === 0) {
			container.innerHTML = ` 
                <div class="alert alert-warning"> 
                    <i class="mdi mdi-alert me-2"></i>El usuario no tiene roles asignados 
                </div> 
            `;
			return;
		}
		let html = '<div class="table-responsive"><table class="table table-hover mb-0">';
		html += ` 
            <thead> 
                <tr> 
                    <th>Departamento</th> 
                    <th>Rol</th> 
                    <th class="text-center">Principal</th> 
                    <th class="text-center">Acciones</th> 
                </tr> 
            </thead> 
            <tbody> 
        `;
		roles.forEach(role => {
			const isPrincipal = role.es_principal;
			const badgeClass = isPrincipal ? 'bg-success' : 'bg-secondary';
			const roleBadge = getRoleBadgeClass(role.id_rol);
			html += ` 
                <tr> 
                    <td> 
                        <i class="mdi mdi-domain me-2 text-primary"></i> 
                        ${escapeHtml(role.departamento)} 
                    </td> 
                    <td> 
                        <span class="badge ${roleBadge}">${escapeHtml(role.rol)}</span> 
                    </td> 
                    <td class="text-center"> 
                        ${isPrincipal  
                            ? '<span class="badge bg-success"><i class="mdi mdi-star"></i> Principal</span>'  
                            : `<button class="btn btn-sm btn-outline-primary" onclick="setPrincipalRole(${role.id_departamento})" title="Establecer como principal"> 
                                <i class="mdi mdi-star-outline"></i> 
                               </button>` 
                        } 
                    </td> 
                    <td class="text-center"> 
                        ${!isPrincipal  
                            ? `<button class="btn btn-sm btn-outline-danger" onclick="removeRole(${role.id_departamento}, '${escapeHtml(role.departamento)}')" title="Eliminar rol"> 
                                <i class="mdi mdi-delete"></i> 
                               </button>` 
                            : `<span class="text-muted small">-</span>` 
                        } 
                    </td> 
                </tr> 
            `;
		});
		html += '</tbody></table></div>';
		container.innerHTML = html;
	}
	// Cargar departamentos disponibles (donde el usuario no tiene rol) 
	function loadAvailableDepartments(userId) {
		fetch(`../php/manage_user_roles.php?action=get_available&id_usuario=${userId}`)
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					rolesManager.availableDepartments = data.departamentos;
					populateDepartmentSelect(data.departamentos);
				}
			})
			.catch(error => {
				console.error('Error al cargar departamentos:', error);
			});
	}
	// Cargar todos los roles disponibles 
	function loadAllRoles() {
		fetch('../php/get_roles.php')
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					rolesManager.allRoles = data.roles;
					populateRoleSelect(data.roles);
				}
			})
			.catch(error => {
				console.error('Error al cargar roles:', error);
			});
	}
	// Popular select de departamentos 
	function populateDepartmentSelect(departamentos) {
		const select = document.getElementById('newRoleDepartamento');
		if (!select) return;
		select.innerHTML = '<option value="">Seleccione un departamento</option>';
		if (departamentos.length === 0) {
			select.innerHTML = '<option value="">No hay departamentos disponibles</option>';
			select.disabled = true;
			return;
		}
		select.disabled = false;
		departamentos.forEach(dept => {
			const option = document.createElement('option');
			option.value = dept.id_departamento;
			option.textContent = dept.nombre;
			select.appendChild(option);
		});
	}
	// Popular select de roles 
	function populateRoleSelect(roles) {
		const select = document.getElementById('newRoleRol');
		if (!select) return;
		select.innerHTML = '<option value="">Seleccione un rol</option>';
		roles.forEach(rol => {
			const option = document.createElement('option');
			option.value = rol.id_rol;
			option.textContent = rol.nombre;
			select.appendChild(option);
		});
	}
	// Agregar nuevo rol 
	window.addNewRole = function() {
		const departamentoSelect = document.getElementById('newRoleDepartamento');
		const rolSelect = document.getElementById('newRoleRol');
		const esPrincipalCheck = document.getElementById('newRoleEsPrincipal');
		const id_departamento = departamentoSelect ? parseInt(departamentoSelect.value) : 0;
		const id_rol = rolSelect ? parseInt(rolSelect.value) : 0;
		const es_principal = esPrincipalCheck ? (esPrincipalCheck.checked ? 1 : 0) : 0;
		if (!id_departamento) {
			showRolesAlert('error', 'Seleccione un departamento');
			return;
		}
		if (!id_rol) {
			showRolesAlert('error', 'Seleccione un rol');
			return;
		}
		const btn = document.getElementById('btnAddRole');
		if (btn) {
			btn.disabled = true;
			btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Agregando...';
		}
		const formData = new FormData();
		formData.append('action', 'add');
		formData.append('id_usuario', rolesManager.currentUserId);
		formData.append('id_departamento', id_departamento);
		formData.append('id_rol', id_rol);
		formData.append('es_principal', es_principal);
		fetch('../php/manage_user_roles.php', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					showRolesAlert('success', data.message);
					// Recargar roles 
					loadUserRoles(rolesManager.currentUserId);
					// Limpiar formulario 
					if (departamentoSelect) departamentoSelect.value = '';
					if (rolSelect) rolSelect.value = '';
					if (esPrincipalCheck) esPrincipalCheck.checked = false;
				} else {
					showRolesAlert('error', data.message);
				}
			})
			.catch(error => {
				console.error('Error:', error);
				showRolesAlert('error', 'Error de conexión');
			})
			.finally(() => {
				if (btn) {
					btn.disabled = false;
					btn.innerHTML = '<i class="mdi mdi-plus"></i> Agregar Rol';
				}
			});
	};
	// Eliminar rol 
	window.removeRole = function(id_departamento, departamentoName) {
		if (!confirm(`¿Está seguro de eliminar el rol en "${departamentoName}"?`)) {
			return;
		}
		const formData = new FormData();
		formData.append('action', 'remove');
		formData.append('id_usuario', rolesManager.currentUserId);
		formData.append('id_departamento', id_departamento);
		fetch('../php/manage_user_roles.php', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					showRolesAlert('success', data.message);
					loadUserRoles(rolesManager.currentUserId);
				} else {
					showRolesAlert('error', data.message);
				}
			})
			.catch(error => {
				console.error('Error:', error);
				showRolesAlert('error', 'Error de conexión');
			});
	};
	// Establecer rol como principal 
	window.setPrincipalRole = function(id_departamento) {
		const formData = new FormData();
		formData.append('action', 'set_principal');
		formData.append('id_usuario', rolesManager.currentUserId);
		formData.append('id_departamento', id_departamento);
		fetch('../php/manage_user_roles.php', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					showRolesAlert('success', data.message);
					loadUserRoles(rolesManager.currentUserId);
				} else {
					showRolesAlert('error', data.message);
				}
			})
			.catch(error => {
				console.error('Error:', error);
				showRolesAlert('error', 'Error de conexión');
			});
	};
	// Mostrar alerta en el modal 
	function showRolesAlert(type, message) {
		const alertDiv = document.getElementById('rolesManagerAlert');
		if (!alertDiv) return;
		const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
		const iconClass = type === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle';
		alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
		alertDiv.innerHTML = ` 
            <i class="mdi ${iconClass} me-2"></i>${message} 
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button> 
        `;
		alertDiv.style.display = 'block';
		if (type === 'success') {
			setTimeout(() => {
				alertDiv.style.display = 'none';
			}, 3000);
		}
	}
	// Obtener clase de badge según el rol 
	function getRoleBadgeClass(id_rol) {
		switch (parseInt(id_rol)) {
			case 1:
				return 'bg-danger'; // Administrador 
			case 2:
				return 'bg-warning'; // Gerente 
			case 3:
				return 'bg-info'; // Usuario 
			default:
				return 'bg-secondary';
		}
	}
	// Escapar HTML para prevenir XSS 
	function escapeHtml(text) {
		if (!text) return '';
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
})();