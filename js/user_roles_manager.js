// user_roles_manager.js para la gestión de múltiples roles por usuario 
(function() {
	'use strict';
	// Estado de la aplicación 
	const rolesManager = {
		currentUserId: null,
		currentUserName: '',
		userRoles: [],
		availableDepartments: [],
		allRoles: [],
		modalInstance: null
	};
	// Inicialización cuando se abre el modal 
	window.openRolesManager = function(userId, userName) {
		console.log('openRolesManager called with:', userId, userName);
		rolesManager.currentUserId = userId;
		rolesManager.currentUserName = userName;
		// Actualizar título del modal 
		const modalTitle = document.getElementById('rolesManagerTitle');
		if (modalTitle) {
			modalTitle.textContent = `Gestionar Roles - ${userName}`;
		}
		// Obtener el elemento del modal 
		const modalElement = document.getElementById('rolesManagerModal');
		if (!modalElement) {
			console.error('Modal element not found: rolesManagerModal');
			alert('Error: No se encontró el modal de roles');
			return;
		}
		// Cerrar cualquier modal existente primero 
		const existingBackdrops = document.querySelectorAll('.modal-backdrop');
		existingBackdrops.forEach(backdrop => backdrop.remove());
		// Remover clases de modal abierto del body 
		document.body.classList.remove('modal-open');
		document.body.style.overflow = '';
		document.body.style.paddingRight = '';
		// Resetear el estado del modal 
		modalElement.classList.remove('show');
		modalElement.style.display = 'none';
		modalElement.removeAttribute('aria-hidden');
		loadUserRoles(userId);
		loadAllRoles();
		// Pequeño delay para asegurar que los datos se carguen 
		setTimeout(() => {
			try {
				// Destruir instancia anterior si existe 
				if (rolesManager.modalInstance) {
					try {
						rolesManager.modalInstance.dispose();
					} catch (e) {
						console.log('Could not dispose previous modal instance');
					}
				}
				// Crear nueva instancia del modal 
				rolesManager.modalInstance = new bootstrap.Modal(modalElement, {
					backdrop: true,
					keyboard: true,
					focus: true
				});
				// Mostrar el modal 
				rolesManager.modalInstance.show();
				// Forzar z-index alto después de mostrar 
				setTimeout(() => {
					modalElement.style.zIndex = '1060';
					modalElement.style.display = 'block';
					const backdrop = document.querySelector('.modal-backdrop');
					if (backdrop) {
						backdrop.style.zIndex = '1055';
					}
				}, 50);
				console.log('Modal should be visible now');
			} catch (error) {
				console.error('Error showing modal:', error);
				// Fallback: mostrar modal manualmente 
				showModalManually(modalElement);
			}
		}, 100);
	};

	// Fallback para mostrar modal manualmente si Bootstrap falla 
	function showModalManually(modalElement) {
		console.log('Using manual modal display');
		// Crear backdrop 
		let backdrop = document.querySelector('.modal-backdrop');
		if (!backdrop) {
			backdrop = document.createElement('div');
			backdrop.className = 'modal-backdrop fade show';
			backdrop.style.zIndex = '1055';
			document.body.appendChild(backdrop);
		}
		// Mostrar modal 
		modalElement.style.display = 'block';
		modalElement.style.zIndex = '1060';
		modalElement.classList.add('show');
		modalElement.setAttribute('aria-modal', 'true');
		modalElement.setAttribute('role', 'dialog');
		modalElement.removeAttribute('aria-hidden');
		// Agregar clase al body 
		document.body.classList.add('modal-open');
		document.body.style.overflow = 'hidden';
		// Agregar event listener para cerrar 
		const closeButtons = modalElement.querySelectorAll('[data-bs-dismiss="modal"]');
		closeButtons.forEach(btn => {
			btn.addEventListener('click', () => closeModalManually(modalElement), {
				once: true
			});
		});
		// Cerrar al hacer clic en backdrop 
		backdrop.addEventListener('click', () => closeModalManually(modalElement), {
			once: true
		});
	}

	function closeModalManually(modalElement) {
		modalElement.classList.remove('show');
		modalElement.style.display = 'none';
		modalElement.setAttribute('aria-hidden', 'true');
		const backdrop = document.querySelector('.modal-backdrop');
		if (backdrop) {
			backdrop.remove();
		}
		document.body.classList.remove('modal-open');
		document.body.style.overflow = '';
		document.body.style.paddingRight = '';
	}

	function loadUserRoles(userId) {
		const container = document.getElementById('currentRolesList');
		if (!container) {
			console.error('Container currentRolesList not found');
			return;
		}
		container.innerHTML = ` 
            <div class="text-center py-3"> 
                <div class="spinner-border spinner-border-sm text-primary" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <p class="mt-2 mb-0 text-muted small">Cargando roles...</p> 
            </div> 
        `;
		fetch(`../php/get_user_roles.php?id_usuario=${userId}`)
			.then(response => {
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				return response.json();
			})
			.then(data => {
				console.log('User roles loaded:', data);
				if (data.success) {
					rolesManager.userRoles = data.roles;
					renderUserRoles(data.roles);
					loadAvailableDepartments(userId);
				} else {
					container.innerHTML = ` 
                        <div class="alert alert-warning m-3"> 
                            <i class="mdi mdi-alert me-2"></i>${data.message || 'No se encontraron roles'} 
                        </div> 
                    `;
				}
			})
			.catch(error => {
				console.error('Error loading user roles:', error);
				container.innerHTML = ` 
                    <div class="alert alert-danger m-3"> 
                        <i class="mdi mdi-alert-circle me-2"></i>Error al cargar roles: ${error.message} 
                    </div> 
                `;
			});
	}

	function renderUserRoles(roles) {
		const container = document.getElementById('currentRolesList');
		if (!container) return;
		if (!roles || roles.length === 0) {
			container.innerHTML = ` 
                <div class="alert alert-warning m-3"> 
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

	function loadAvailableDepartments(userId) {
		fetch(`../php/manage_user_roles.php?action=get_available&id_usuario=${userId}`)
			.then(response => response.json())
			.then(data => {
				console.log('Available departments:', data);
				if (data.success) {
					rolesManager.availableDepartments = data.departamentos;
					populateDepartmentSelect(data.departamentos);
				}
			})
			.catch(error => {
				console.error('Error al cargar departamentos:', error);
			});
	}

	function loadAllRoles() {
		fetch('../php/get_roles.php')
			.then(response => response.json())
			.then(data => {
				console.log('All roles loaded:', data);
				if (data.success) {
					rolesManager.allRoles = data.roles;
					populateRoleSelect(data.roles);
				}
			})
			.catch(error => {
				console.error('Error al cargar roles:', error);
			});
	}

	function populateDepartmentSelect(departamentos) {
		const select = document.getElementById('newRoleDepartamento');
		if (!select) return;
		select.innerHTML = '<option value="">Seleccione un departamento</option>';
		if (!departamentos || departamentos.length === 0) {
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

	function populateRoleSelect(roles) {
		const select = document.getElementById('newRoleRol');
		if (!select) return;
		select.innerHTML = '<option value="">Seleccione un rol</option>';
		if (!roles) return;
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
				showRolesAlert('error', 'Error de conexión: ' + error.message);
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
	// Limpiar al cerrar el modal 
	document.addEventListener('DOMContentLoaded', function() {
		const modalElement = document.getElementById('rolesManagerModal');
		if (modalElement) {
			modalElement.addEventListener('hidden.bs.modal', function() {
				rolesManager.currentUserId = null;
				rolesManager.currentUserName = '';
				rolesManager.userRoles = [];
				// Limpiar formulario 
				const deptSelect = document.getElementById('newRoleDepartamento');
				const rolSelect = document.getElementById('newRoleRol');
				const principalCheck = document.getElementById('newRoleEsPrincipal');
				if (deptSelect) deptSelect.value = '';
				if (rolSelect) rolSelect.value = '';
				if (principalCheck) principalCheck.checked = false;
				// Ocultar alerta 
				const alertDiv = document.getElementById('rolesManagerAlert');
				if (alertDiv) alertDiv.style.display = 'none';
			});
		}
	});
})();