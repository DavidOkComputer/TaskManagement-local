/*manager_create_project.js para crear proyectos como gerente*/
const editMode = {
	isEditing: false,
	projectId: null
};
// Estado para proyecto grupal 
const grupalState = {
	selectedUsers: [],
	usuariosModal: null
};
// Estado del usuario y sus departamentos 
const userState = {
	userId: null,
	departmentId: null,
	departmentName: null,
	userDepartments: [], // Todos los departamentos del usuario 
	hasMultipleDepartments: false,
	isAdmin: false,
	isManager: false,
	canChooseDepartment: false,
	includeAllDepartments: false 
};
const app = {
	usuarios: [],
	allDepartmentUsers: [] 
};
// Inicializar página al cargar 
document.addEventListener('DOMContentLoaded', function() {
	// Detectar si estamos en modo edición 
	const params = new URLSearchParams(window.location.search);
	editMode.projectId = params.get('edit');
	editMode.isEditing = !!editMode.projectId;
	// Cambiar título y botón si estamos editando 
	if (editMode.isEditing) {
		const titleEl = document.querySelector('h4.card-title');
		const subtitleEl = document.querySelector('p.card-subtitle');
		const btnCrear = document.getElementById('btnCrear');
		if (titleEl) titleEl.textContent = 'Editar Proyecto';
		if (subtitleEl) subtitleEl.textContent = 'Actualiza la información del proyecto';
		if (btnCrear) btnCrear.textContent = 'Actualizar';
	}
	// Cargar departamentos del usuario 
	cargarMisDepartamentos();
	setupFormHandlers();
	setupGrupalHandlers();
	// Si es edición, cargar datos del proyecto después de cargar departamentos 
	if (editMode.isEditing) {
		setTimeout(() => {
			cargarProyectoParaEditar(editMode.projectId);
		}, 500);
	}
});

function cargarMisDepartamentos() {
	fetch('../php/user_get_my_departments.php')
		.then(response => {
			if (!response.ok) {
				throw new Error('La respuesta de red no fue ok');
			}
			return response.json();
		})
		.then(data => {
			if (data.success && data.departamentos && data.departamentos.length > 0) {
				// Guardar estado del usuario 
				userState.userDepartments = data.departamentos;
				userState.hasMultipleDepartments = data.tiene_multiples_departamentos;
				userState.isAdmin = data.permisos?.is_admin || false;
				userState.isManager = data.permisos?.is_manager || false;
				userState.canChooseDepartment = data.permisos?.puede_elegir_departamento || false;
				// Configurar el selector de departamento 
				setupDepartmentSelector(data);
				// Agregar toggle para mostrar usuarios de todos los departamentos 
				if (userState.hasMultipleDepartments || userState.isAdmin) {
					
				}
			} else {
				showAlert('Error: No se pudieron cargar tus departamentos. ' + (data.message || ''), 'danger');
			}
		})
		.catch(error => {
			console.error('Error al cargar departamentos:', error);
			showAlert('Error al cargar departamentos: ' + error.message, 'danger');
		});
}

function setupDepartmentSelector(data) {
	const select = document.getElementById('id_departamento');
	const hiddenInput = document.getElementById('id_departamento_hidden');
	const helpText = select?.parentElement?.querySelector('.form-text');
	if (!select) {
		console.error('No se encontró el selector de departamento');
		return;
	}
	// Limpiar el select 
	select.innerHTML = '';
	// Determinar qué departamentos mostrar 
	let departamentosParaMostrar = data.departamentos;
	// Si es admin, puede elegir entre todos los departamentos 
	if (userState.isAdmin && data.todos_departamentos && data.todos_departamentos.length > 0) {
		departamentosParaMostrar = data.todos_departamentos.map(d => ({
			...d,
			id_rol: 1,
			nombre_rol: 'Administrador',
			es_principal: 0,
			puede_gestionar: true
		}));
		// Marcar el departamento principal del admin 
		if (data.departamento_principal) {
			const idx = departamentosParaMostrar.findIndex(
				d => d.id_departamento === data.departamento_principal.id_departamento
			);
			if (idx >= 0) {
				departamentosParaMostrar[idx].es_principal = 1;
			}
		}
	}
	if (departamentosParaMostrar.length === 1) {
		// Solo un departamento - deshabilitar selector 
		const dept = departamentosParaMostrar[0];
		userState.departmentId = dept.id_departamento;
		userState.departmentName = dept.nombre;
		const option = document.createElement('option');
		option.value = dept.id_departamento;
		option.textContent = dept.nombre;
		option.selected = true;
		select.appendChild(option);
		if (hiddenInput) hiddenInput.value = dept.id_departamento;
		select.disabled = true;
		select.style.cursor = 'not-allowed';
		select.style.backgroundColor = '#e9ecef';
		if (helpText) {
			helpText.innerHTML = '<i class="mdi mdi-information-outline"></i> Tu departamento está asignado automáticamente';
		}
		// Cargar usuarios del departamento 
		loadUsuariosDepartamento(dept.id_departamento);
	} else {
		// Múltiples departamentos - habilitar selección 
		select.disabled = false;
		select.style.cursor = 'pointer';
		select.style.backgroundColor = '#ffffff';
		// Agregar opción por defecto 
		const defaultOption = document.createElement('option');
		defaultOption.value = '';
		defaultOption.textContent = 'Seleccione un departamento';
		defaultOption.disabled = true;
		defaultOption.selected = true;
		select.appendChild(defaultOption);
		// Agregar departamentos 
		departamentosParaMostrar.forEach(dept => {
			const option = document.createElement('option');
			option.value = dept.id_departamento;
			// Construir texto de la opción 
			let optionText = dept.nombre;
			// Agregar indicador de rol 
			if (dept.nombre_rol) {
				optionText += ` (${dept.nombre_rol})`;
			}
			// Agregar indicador de principal 
			if (dept.es_principal === 1) {
				optionText += ' ★';
			}
			option.textContent = optionText;
			option.setAttribute('data-rol', dept.id_rol || '');
			option.setAttribute('data-es-principal', dept.es_principal || 0);
			select.appendChild(option);
		});
		// Seleccionar el departamento principal por defecto 
		const deptPrincipal = departamentosParaMostrar.find(d => d.es_principal === 1);
		if (deptPrincipal) {
			select.value = deptPrincipal.id_departamento;
			userState.departmentId = deptPrincipal.id_departamento;
			userState.departmentName = deptPrincipal.nombre;
			if (hiddenInput) hiddenInput.value = deptPrincipal.id_departamento;
			loadUsuariosDepartamento(deptPrincipal.id_departamento);
		}
		if (helpText) {
			helpText.innerHTML = '<i class="mdi mdi-information-outline"></i> Selecciona el departamento para el proyecto';
		}
		setupDepartmentChangeHandler();
	}
}

function setupAllDepartmentsToggle() {
	const participanteContainer = document.getElementById('id_participante')?.closest('.col-sm-9');
	if (!participanteContainer) return;
	// Verificar si ya existe el toggle 
	if (document.getElementById('toggleAllDepartments')) return;
	// Crear el toggle 
	const toggleContainer = document.createElement('div');
	toggleContainer.className = 'form-check mt-2';
	toggleContainer.innerHTML = ` 

    `;

	const existingHelpText = participanteContainer.querySelector('.form-text');
	if (existingHelpText) {
		existingHelpText.after(toggleContainer);
	} else {
		participanteContainer.appendChild(toggleContainer);
	}

	const toggle = document.getElementById('toggleAllDepartments');
	toggle.addEventListener('change', function() {
		userState.includeAllDepartments = this.checked;
		if (this.checked) {
			// Cargar usuarios de todos los departamentos 
			loadUsuariosAllDepartments();
			showAlert('Mostrando usuarios de todos tus departamentos', 'info');
		} else {
			// Volver a cargar solo del departamento actual 
			if (userState.departmentId) {
				loadUsuariosDepartamento(userState.departmentId);
			}
		}
	});
}

function setupDepartmentChangeHandler() {
	const select = document.getElementById('id_departamento');
	const hiddenInput = document.getElementById('id_departamento_hidden');
	if (!select) return;
	// Remover listener anterior si existe 
	const newSelect = select.cloneNode(true);
	select.parentNode.replaceChild(newSelect, select);
	newSelect.addEventListener('change', function() {
		const selectedDeptId = parseInt(this.value);
		if (selectedDeptId > 0) {
			// Actualizar estado 
			userState.departmentId = selectedDeptId;
			if (hiddenInput) hiddenInput.value = selectedDeptId;
			// Encontrar el nombre del departamento 
			const selectedDept = userState.userDepartments.find(d => d.id_departamento === selectedDeptId);
			if (selectedDept) {
				userState.departmentName = selectedDept.nombre;
			}
			// Limpiar selección de usuarios para proyecto grupal 
			grupalState.selectedUsers = [];
			updateSelectedCount();
			// Cargar usuarios según el toggle 
			const toggle = document.getElementById('toggleAllDepartments');
			if (toggle && toggle.checked) {
				loadUsuariosAllDepartments();
			} else {
				loadUsuariosDepartamento(selectedDeptId);
			}
			
		}
	});
}

function loadUsuariosAllDepartments() {
	const participanteSelect = document.getElementById('id_participante');
	if (participanteSelect) {
		participanteSelect.innerHTML = '<option value="">Cargando usuarios de todos los departamentos...</option>';
		participanteSelect.disabled = true;
	}
	fetch(`../php/manager_get_users.php?include_all_departments=1`)
		.then(response => response.json())
		.then(data => {
			if (data.success && data.usuarios) {
				app.usuarios = data.usuarios;
				app.allDepartmentUsers = data.usuarios;
				populateUsuariosSelectMultiDept(data.usuarios);
				populateGrupalModalMultiDept(data.usuarios);
				
				if (data.usuarios.length === 0) {
					showAlert('No hay usuarios disponibles en tus departamentos', 'info');
				}
			} else {
				console.error('Error al cargar usuarios:', data.message);
				showAlert('Error al cargar usuarios: ' + (data.message || 'Error desconocido'), 'warning');
				if (participanteSelect) {
					participanteSelect.innerHTML = '<option value="">No hay usuarios disponibles</option>';
				}
			}
		})
		.catch(error => {
			console.error('Error en fetch de usuarios:', error);
			showAlert('Error al cargar usuarios: ' + error.message, 'danger');
			if (participanteSelect) {
				participanteSelect.innerHTML = '<option value="">Error al cargar usuarios</option>';
			}
		})
		.finally(() => {
			if (participanteSelect) {
				participanteSelect.disabled = false;
			}
		});
}

function loadUsuariosDepartamento(departmentId) {
	if (!departmentId) {
		console.error('No se ha proporcionado ID de departamento');
		return;
	}
	// Mostrar indicador de carga 
	const participanteSelect = document.getElementById('id_participante');
	if (participanteSelect) {
		participanteSelect.innerHTML = '<option value="">Cargando usuarios...</option>';
		participanteSelect.disabled = true;
	}
	// Usar el endpoint actualizado que soporta usuarios con roles secundarios 
	fetch(`../php/manager_get_users.php?id_departamento=${departmentId}`)
		.then(response => response.json())
		.then(data => {
			if (data.success && data.usuarios) {
				app.usuarios = data.usuarios;
				populateUsuariosSelect(data.usuarios);
				populateGrupalModal(data.usuarios);
				
				if (data.usuarios.length === 0) {
					showAlert('No hay usuarios disponibles en este departamento', 'info');
				}
			} else {
				console.error('Error al cargar usuarios:', data.message);
				showAlert('Error al cargar usuarios: ' + (data.message || 'Error desconocido'), 'warning');
				if (participanteSelect) {
					participanteSelect.innerHTML = '<option value="">No hay usuarios disponibles</option>';
				}
			}
		})
		.catch(error => {
			console.error('Error en fetch de usuarios:', error);
			showAlert('Error al cargar usuarios: ' + error.message, 'danger');
			if (participanteSelect) {
				participanteSelect.innerHTML = '<option value="">Error al cargar usuarios</option>';
			}
		})
		.finally(() => {
			if (participanteSelect) {
				participanteSelect.disabled = false;
			}
		});
}

function populateUsuariosSelect(usuarios) {
	const select = document.getElementById('id_participante');
	if (!select) return;
	select.innerHTML = '<option value="0">Sin usuario asignado</option>';
	usuarios.forEach(usuario => {
		const option = document.createElement('option');
		option.value = usuario.id_usuario;
		// Mostrar indicador si es rol secundario 
		let displayText = `${usuario.nombre_completo} (ID: ${usuario.num_empleado})`;
		if (usuario.es_rol_secundario) {
			displayText += ' [Rol secundario]';
		}
		option.textContent = displayText;
		select.appendChild(option);
	});
}

function populateUsuariosSelectMultiDept(usuarios) {
	const select = document.getElementById('id_participante');
	if (!select) return;
	select.innerHTML = '<option value="0">Sin usuario asignado</option>';
	// Agrupar usuarios por departamento 
	const usuariosPorDepto = {};
	usuarios.forEach(usuario => {
		const deptName = usuario.area || 'Sin departamento';
		if (!usuariosPorDepto[deptName]) {
			usuariosPorDepto[deptName] = [];
		}
		usuariosPorDepto[deptName].push(usuario);
	});
	// Crear optgroups por departamento 
	Object.keys(usuariosPorDepto).sort().forEach(deptName => {
		const optgroup = document.createElement('optgroup');
		optgroup.label = `📁 ${deptName}`;
		usuariosPorDepto[deptName].forEach(usuario => {
			const option = document.createElement('option');
			option.value = usuario.id_usuario;
			let displayText = `${usuario.nombre_completo} (#${usuario.num_empleado})`;
			if (usuario.es_rol_secundario) {
				displayText += ' [Sec]';
			}
			option.textContent = displayText;
			optgroup.appendChild(option);
		});
		select.appendChild(optgroup);
	});
}

function populateGrupalModal(usuarios) {
	const container = document.getElementById('usuariosListContainer');
	if (!container) return;
	container.innerHTML = '';
	if (usuarios.length === 0) {
		container.innerHTML = ` 
            <div class="text-center py-4 text-muted"> 
                <i class="mdi mdi-account-off" style="font-size: 48px;"></i> 
                <p class="mt-2">No hay usuarios disponibles en este departamento</p> 
            </div> 
        `;
		return;
	}
	usuarios.forEach(usuario => {
		const userItem = createUserItem(usuario);
		container.appendChild(userItem);
	});
	setupUsuarioItemHandlers();
}

function populateGrupalModalMultiDept(usuarios) {
	const container = document.getElementById('usuariosListContainer');
	if (!container) return;
	container.innerHTML = '';
	if (usuarios.length === 0) {
		container.innerHTML = ` 
            <div class="text-center py-4 text-muted"> 
                <i class="mdi mdi-account-off" style="font-size: 48px;"></i> 
                <p class="mt-2">No hay usuarios disponibles en tus departamentos</p> 
            </div> 
        `;
		return;
	}
	// Agrupar usuarios por departamento 
	const usuariosPorDepto = {};
	usuarios.forEach(usuario => {
		const deptName = usuario.area || 'Sin departamento';
		if (!usuariosPorDepto[deptName]) {
			usuariosPorDepto[deptName] = [];
		}
		usuariosPorDepto[deptName].push(usuario);
	});
	// Crear secciones por departamento 
	Object.keys(usuariosPorDepto).sort().forEach(deptName => {
		// Header del departamento 
		const deptHeader = document.createElement('div');
		deptHeader.className = 'department-header bg-light p-2 mb-2 rounded';
		deptHeader.innerHTML = ` 
            <strong class="text-success"> 
                <i class="mdi mdi-folder-outline me-1"></i>${deptName} 
            </strong> 
            <span class="badge bg-secondary ms-2">${usuariosPorDepto[deptName].length} usuarios</span> 
        `;
		container.appendChild(deptHeader);
		// Usuarios del departamento 
		usuariosPorDepto[deptName].forEach(usuario => {
			const userItem = createUserItem(usuario, true);
			container.appendChild(userItem);
		});
		// Separador 
		const separator = document.createElement('hr');
		separator.className = 'my-3';
		container.appendChild(separator);
	});
	setupUsuarioItemHandlers();
}

function createUserItem(usuario, showDepartment = false) {
	const userItem = document.createElement('div');
	userItem.className = 'usuario-item mb-3 p-3 border-bottom';
	userItem.setAttribute('data-user-id', usuario.id_usuario);
	userItem.style.cursor = 'pointer';
	userItem.style.transition = 'background-color 0.3s ease, box-shadow 0.3s ease';
	userItem.style.borderRadius = '4px';
	const isSelected = grupalState.selectedUsers.includes(usuario.id_usuario);
	const iconClass = isSelected ? 'mdi-checkbox-marked-circle-outline' : 'mdi-checkbox-blank-circle-outline';
	const iconColor = isSelected ? '#009b4a' : '#999';
	const bgColor = isSelected ? '#ffffff' : 'transparent';
	// Badge para rol secundario 
	const rolSecundarioBadge = usuario.es_rol_secundario ?
		'<span class="badge bg-info ms-2" style="font-size: 10px;">Rol secundario</span>' :
		'';
	const rolInfo = usuario.nombre_rol ? usuario.nombre_rol : 'Sin rol asignado';
	// Mostrar departamento si se solicita 
	const deptInfo = showDepartment && usuario.area ?
		`<small class="text-muted d-block"><i class="mdi mdi-folder"></i> ${usuario.area}</small>` :
		'';
	userItem.innerHTML = ` 
        <div class="d-flex align-items-start justify-content-between"> 
            <div class="flex-grow-1"> 
                <strong class="d-block mb-1"> 
                    ${usuario.nombre_completo} 
                    ${rolSecundarioBadge} 
                </strong> 
                <small class="text-muted d-block">Empleado #${usuario.num_empleado}</small> 
                <small class="text-muted d-block">${usuario.e_mail}</small> 
                ${deptInfo} 
                <small class="text-muted d-block"> 
                    <span class="ms-0"><i class="mdi mdi-account-key"></i> ${rolInfo}</span> 
                </small> 
            </div> 
            <div class="ms-3 d-flex align-items-center"> 
                <i class="mdi ${iconClass} usuario-selection-icon" style="font-size: 28px; color: ${iconColor}; transition: all 0.3s ease; cursor: pointer;"></i> 
            </div> 
        </div> 
        <input type="hidden" class="usuario-id" value="${usuario.id_usuario}"> 
    `;
	if (isSelected) {
		userItem.classList.add('selected');
		userItem.style.backgroundColor = bgColor;
	}
	userItem.addEventListener('mouseenter', function() {
		if (!this.classList.contains('selected')) {
			this.style.backgroundColor = '#f8f9fa';
		}
	});
	userItem.addEventListener('mouseleave', function() {
		if (!this.classList.contains('selected')) {
			this.style.backgroundColor = 'transparent';
		}
	});
	userItem.addEventListener('click', function(e) {
		e.stopPropagation();
		toggleUsuarioSelection(this);
	});
	return userItem;
}

function toggleUsuarioSelection(userItem) {
	const userId = parseInt(userItem.getAttribute('data-user-id'));
	const icon = userItem.querySelector('.usuario-selection-icon');
	const isSelected = icon.classList.contains('mdi-checkbox-marked-circle-outline');
	if (isSelected) {
		icon.classList.remove('mdi-checkbox-marked-circle-outline');
		icon.classList.add('mdi-checkbox-blank-circle-outline');
		icon.style.color = '#999';
		userItem.style.backgroundColor = 'transparent';
		userItem.classList.remove('selected');
		grupalState.selectedUsers = grupalState.selectedUsers.filter(id => id !== userId);
	} else {
		icon.classList.remove('mdi-checkbox-blank-circle-outline');
		icon.classList.add('mdi-checkbox-marked-circle-outline');
		icon.style.color = '#009b4a';
		userItem.style.backgroundColor = '#ffffff';
		userItem.classList.add('selected');
		if (!grupalState.selectedUsers.includes(userId)) {
			grupalState.selectedUsers.push(userId);
		}
	}
	updateSelectedCount();
}

function setupUsuarioItemHandlers() {
	const searchInput = document.getElementById('searchUsuarios');
	if (searchInput && !searchInput.hasEventListener) {
		searchInput.addEventListener('keyup', function() {
			const searchTerm = this.value.toLowerCase();
			const usuarioItems = document.querySelectorAll('.usuario-item');
			usuarioItems.forEach(item => {
				const text = item.textContent.toLowerCase();
				item.style.display = text.includes(searchTerm) ? 'block' : 'none';
			});
			// También ocultar/mostrar headers de departamento si están vacíos 
			const deptHeaders = document.querySelectorAll('.department-header');
			deptHeaders.forEach(header => {
				const nextItems = [];
				let sibling = header.nextElementSibling;
				while (sibling && !sibling.classList.contains('department-header') && sibling.tagName !== 'HR') {
					if (sibling.classList.contains('usuario-item')) {
						nextItems.push(sibling);
					}
					sibling = sibling.nextElementSibling;
				}
				const visibleItems = nextItems.filter(item => item.style.display !== 'none');
				header.style.display = visibleItems.length > 0 ? 'block' : 'none';
			});
		});
		searchInput.hasEventListener = true;
	}
}

function updateSelectedCount() {
	const countEl = document.getElementById('countSelected');
	if (countEl) {
		countEl.textContent = grupalState.selectedUsers.length;
	}
}

function setupGrupalHandlers() {
	const tipoProyectoRadios = document.querySelectorAll('input[name="id_tipo_proyecto"]');
	const participanteField = document.getElementById('id_participante');
	const btnSeleccionarGrupo = document.getElementById('btnSeleccionarGrupo');
	// Handler para el botón "Grupo" 
	if (btnSeleccionarGrupo && !btnSeleccionarGrupo.hasListener) {
		btnSeleccionarGrupo.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			if (!userState.departmentId) {
				showAlert('Por favor, selecciona un departamento primero', 'warning');
				return;
			}
			// Cambiar a proyecto grupal 
			const grupalRadio = document.querySelector('input[name="id_tipo_proyecto"][value="1"]');
			if (grupalRadio) grupalRadio.checked = true;
			// Mostrar modal 
			if (!grupalState.usuariosModal) {
				const modalEl = document.getElementById('grupalUsuariosModal');
				if (modalEl) {
					grupalState.usuariosModal = new bootstrap.Modal(modalEl);
				}
			}
			if (grupalState.usuariosModal) {
				grupalState.usuariosModal.show();
			}
			if (participanteField) {
				participanteField.disabled = true;
				participanteField.value = '';
			}
			showAlert('Cambiado a proyecto grupal. Selecciona los integrantes del equipo.', 'info');
		});
		btnSeleccionarGrupo.hasListener = true;
	}
	tipoProyectoRadios.forEach(radio => {
		radio.addEventListener('change', function() {
			if (this.value == '1') { // Grupal 
				if (!userState.departmentId) {
					showAlert('Por favor, selecciona un departamento primero', 'warning');
					const indivRadio = document.querySelector('input[name="id_tipo_proyecto"][value="2"]');
					if (indivRadio) indivRadio.checked = true;
					return;
				}
				if (!grupalState.usuariosModal) {
					const modalEl = document.getElementById('grupalUsuariosModal');
					if (modalEl) {
						grupalState.usuariosModal = new bootstrap.Modal(modalEl);
					}
				}
				if (grupalState.usuariosModal) {
					grupalState.usuariosModal.show();
				}
				if (participanteField) {
					participanteField.disabled = true;
					participanteField.value = '';
				}
			} else { // Individual 
				grupalState.selectedUsers = [];
				document.querySelectorAll('.usuario-item').forEach(item => {
					const icon = item.querySelector('.usuario-selection-icon');
					if (icon) {
						icon.classList.remove('mdi-checkbox-marked-circle-outline');
						icon.classList.add('mdi-checkbox-blank-circle-outline');
						icon.style.color = '#999';
						item.style.backgroundColor = 'transparent';
						item.classList.remove('selected');
					}
				});
				updateSelectedCount();
				if (participanteField) {
					participanteField.disabled = false;
				}
			}
		});
	});
	const btnConfirmar = document.getElementById('btnConfirmarGrupal');
	if (btnConfirmar && !btnConfirmar.hasListener) {
		btnConfirmar.addEventListener('click', function() {
			if (grupalState.selectedUsers.length === 0) {
				showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'warning');
				return;
			}
			if (grupalState.usuariosModal) {
				grupalState.usuariosModal.hide();
			}
			showAlert(`${grupalState.selectedUsers.length} usuario(s) seleccionado(s) para el proyecto grupal`, 'success');
		});
		btnConfirmar.hasListener = true;
	}
}

function cargarProyectoParaEditar(projectId) {
	fetch(`../php/get_project_by_id.php?id=${projectId}`)
		.then(response => {
			if (!response.ok) {
				throw new Error('La respuesta de red no fue ok');
			}
			return response.json();
		})
		.then(data => {
			if (data.success && data.proyecto) {
				const proyecto = data.proyecto;
				const nombreEl = document.getElementById('nombre');
				const descripcionEl = document.getElementById('descripcion');
				if (nombreEl) nombreEl.value = proyecto.nombre || '';
				if (descripcionEl) descripcionEl.value = proyecto.descripcion || '';
				// Establecer el departamento del proyecto 
				if (proyecto.id_departamento) {
					const deptSelect = document.getElementById('id_departamento');
					const hiddenInput = document.getElementById('id_departamento_hidden');
					// Verificar si el usuario tiene acceso a este departamento 
					const deptPermitido = userState.userDepartments.find(
						d => d.id_departamento === proyecto.id_departamento
					);
					if (deptPermitido || userState.isAdmin) {
						if (deptSelect) deptSelect.value = proyecto.id_departamento;
						if (hiddenInput) hiddenInput.value = proyecto.id_departamento;
						userState.departmentId = proyecto.id_departamento;
						loadUsuariosDepartamento(proyecto.id_departamento);
					} else {
						showAlert('Advertencia: Este proyecto pertenece a un departamento al que no tienes acceso', 'warning');
					}
				}
				// Fechas 
				const fechaCreacionEl = document.getElementById('fecha_creacion');
				const fechaCumplimientoEl = document.getElementById('fecha_cumplimiento');
				if (proyecto.fecha_inicio && fechaCreacionEl) {
					const fechaInicio = proyecto.fecha_inicio.replace(' ', 'T').substring(0, 16);
					fechaCreacionEl.value = fechaInicio;
				}
				if (proyecto.fecha_cumplimiento && fechaCumplimientoEl) {
					const fechaCumplimiento = proyecto.fecha_cumplimiento.split(' ')[0];
					fechaCumplimientoEl.value = fechaCumplimiento;
				}
				const progresoEl = document.getElementById('progreso');
				const arEl = document.getElementById('ar');
				const estadoEl = document.getElementById('estado');
				if (progresoEl) progresoEl.value = proyecto.progreso || 0;
				if (arEl) arEl.value = proyecto.ar || '';
				if (estadoEl) estadoEl.value = proyecto.estado || 'pendiente';
				// Tipo de proyecto 
				const tipoValue = proyecto.id_tipo_proyecto == 1 ? '1' : '2';
				const tipoRadio = document.querySelector(`input[name="id_tipo_proyecto"][value="${tipoValue}"]`);
				if (tipoRadio) tipoRadio.checked = true;
				// Participante individual 
				setTimeout(() => {
					const participanteEl = document.getElementById('id_participante');
					if (proyecto.id_participante && participanteEl) {
						participanteEl.value = proyecto.id_participante;
					}
				}, 600);
				// Si es grupal, cargar los usuarios asignados 
				if (tipoValue == '1' && proyecto.usuarios_asignados) {
					grupalState.selectedUsers = proyecto.usuarios_asignados.map(u => u.id_usuario);
					setTimeout(() => {
						grupalState.selectedUsers.forEach(userId => {
							const userItem = document.querySelector(`[data-user-id="${userId}"]`);
							if (userItem) {
								const icon = userItem.querySelector('.usuario-selection-icon');
								if (icon) {
									icon.classList.remove('mdi-checkbox-blank-circle-outline');
									icon.classList.add('mdi-checkbox-marked-circle-outline');
									icon.style.color = '#009B4A';
									userItem.style.backgroundColor = '#FFFFFF';
									userItem.classList.add('selected');
								}
							}
						});
						updateSelectedCount();
					}, 700);
				}
				// Permisos de edición 
				const puedeEditarOtros = proyecto.puede_editar_otros == 1 ? '1' : '0';
				const permisoRadio = document.querySelector(`input[name="puede_editar_otros"][value="${puedeEditarOtros}"]`);
				if (permisoRadio) permisoRadio.checked = true;
				const nombreArchivoEl = document.getElementById('nombreArchivo');
				if (proyecto.archivo_adjunto && nombreArchivoEl) {
					nombreArchivoEl.value = proyecto.archivo_adjunto.split('/').pop();
				}
				showAlert('Proyecto cargado correctamente', 'success');
			} else {
				showAlert('Error al cargar el proyecto: ' + data.message, 'danger');
			}
		})
		.catch(error => {
			console.error('Error al cargar proyecto:', error);
			showAlert('Error al cargar el proyecto: ' + error.message, 'danger');
		});
}

function setupFormHandlers() {
	const btnSubirArchivo = document.getElementById('btnSubirArchivo');
	const archivoInput = document.getElementById('archivoInput');
	const btnCancelar = document.getElementById('btnCancelar');
	const proyectoForm = document.getElementById('proyectoForm');
	if (btnSubirArchivo && archivoInput) {
		btnSubirArchivo.addEventListener('click', function() {
			archivoInput.click();
		});
		archivoInput.addEventListener('change', function(e) {
			const nombreArchivoEl = document.getElementById('nombreArchivo');
			if (e.target.files.length > 0 && nombreArchivoEl) {
				nombreArchivoEl.value = e.target.files[0].name;
			}
		});
	}
	if (btnCancelar) {
		btnCancelar.addEventListener('click', function() {
			if (typeof showConfirm === 'function') {
				showConfirm(
					'¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.',
					function() {
						window.history.back();
					},
					'Cancelar cambios', {
						type: 'warning',
						confirmText: 'Sí, cancelar',
						cancelText: 'Volver al formulario'
					}
				);
			} else {
				if (confirm('¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.')) {
					window.history.back();
				}
			}
		});
	}
	if (proyectoForm) {
		proyectoForm.addEventListener('submit', function(e) {
			e.preventDefault();
			if (editMode.isEditing) {
				editarProyecto();
			} else {
				crearProyecto();
			}
		});
	}
}

function crearProyecto() {
	const form = document.getElementById('proyectoForm');
	const formData = new FormData(form);
	const archivoInput = document.getElementById('archivoInput');
	const tipoProyectoEl = document.querySelector('input[name="id_tipo_proyecto"]:checked');
	const tipoProyecto = tipoProyectoEl ? tipoProyectoEl.value : '2';
	if (!form.checkValidity()) {
		showAlert('Por favor, completa todos los campos requeridos', 'danger');
		form.classList.add('was-validated');
		return;
	}
	if (!userState.departmentId) {
		showAlert('Error: Debes seleccionar un departamento', 'danger');
		return;
	}
	if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
		showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
		return;
	}
	const btnCrear = document.getElementById('btnCrear');
	if (btnCrear) {
		btnCrear.disabled = true;
		btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...';
	}
	// Asegurar que el departamento correcto esté en el formData 
	formData.set('id_departamento', userState.departmentId);
	if (archivoInput && archivoInput.files.length > 0) {
		uploadFile(archivoInput.files[0], function(filePath) {
			if (filePath) {
				formData.set('archivo_adjunto', filePath);
				if (tipoProyecto == '1') {
					formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
				}
				const permisoEl = document.querySelector('input[name="puede_editar_otros"]:checked');
				formData.set('puede_editar_otros', permisoEl ? permisoEl.value : '0');
				submitForm(formData, btnCrear, 'create');
			} else {
				if (btnCrear) {
					btnCrear.disabled = false;
					btnCrear.innerHTML = 'Crear';
				}
			}
		});
	} else {
		formData.set('archivo_adjunto', '');
		if (tipoProyecto == '1') {
			formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
		}
		const permisoEl = document.querySelector('input[name="puede_editar_otros"]:checked');
		formData.set('puede_editar_otros', permisoEl ? permisoEl.value : '0');
		submitForm(formData, btnCrear, 'create');
	}
}

function editarProyecto() {
	const form = document.getElementById('proyectoForm');
	const formData = new FormData(form);
	const archivoInput = document.getElementById('archivoInput');
	const tipoProyectoEl = document.querySelector('input[name="id_tipo_proyecto"]:checked');
	const tipoProyecto = tipoProyectoEl ? tipoProyectoEl.value : '2';
	if (!form.checkValidity()) {
		showAlert('Por favor, completa todos los campos requeridos', 'danger');
		form.classList.add('was-validated');
		return;
	}
	if (!userState.departmentId) {
		showAlert('Error: Debes seleccionar un departamento', 'danger');
		return;
	}
	if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
		showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
		return;
	}
	const btnCrear = document.getElementById('btnCrear');
	if (btnCrear) {
		btnCrear.disabled = true;
		btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Actualizando...';
	}
	formData.set('id_departamento', userState.departmentId);
	if (archivoInput && archivoInput.files.length > 0) {
		uploadFile(archivoInput.files[0], function(filePath) {
			if (filePath) {
				formData.set('archivo_adjunto', filePath);
				if (tipoProyecto == '1') {
					formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
				}
				const permisoEl = document.querySelector('input[name="puede_editar_otros"]:checked');
				formData.set('puede_editar_otros', permisoEl ? permisoEl.value : '0');
				submitForm(formData, btnCrear, 'edit');
			} else {
				if (btnCrear) {
					btnCrear.disabled = false;
					btnCrear.innerHTML = 'Actualizar';
				}
			}
		});
	} else {
		const nombreArchivoEl = document.getElementById('nombreArchivo');
		if (nombreArchivoEl && nombreArchivoEl.value) {
			formData.set('archivo_adjunto', nombreArchivoEl.value);
		}
		if (tipoProyecto == '1') {
			formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
		}
		const permisoEl = document.querySelector('input[name="puede_editar_otros"]:checked');
		formData.set('puede_editar_otros', permisoEl ? permisoEl.value : '0');
		submitForm(formData, btnCrear, 'edit');
	}
}

function uploadFile(file, callback) {
	const fileFormData = new FormData();
	fileFormData.append('archivo', file);
	fetch('../php/upload_file.php', {
			method: 'POST',
			body: fileFormData
		})
		.then(response => {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(data => {
			if (data.success) {
				callback(data.filePath);
			} else {
				showAlert('Error al subir el archivo: ' + data.message, 'danger');
				callback(null);
			}
		})
		.catch(error => {
			console.error('Error uploading file:', error);
			showAlert('Error al subir el archivo: ' + error.message, 'danger');
			callback(null);
		});
}

function submitForm(formData, btnCrear, action) {
	const endpoint = action === 'edit' ? '../php/update_project.php' : '../php/create_project.php';
	if (editMode.isEditing) {
		formData.append('id_proyecto', editMode.projectId);
	}
	fetch(endpoint, {
			method: 'POST',
			body: formData
		})
		.then(response => {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(data => {
			if (data.success) {
				const successMessage = action === 'edit' ?
					'¡Proyecto actualizado exitosamente!' :
					'¡Proyecto creado exitosamente!';
				showAlert(successMessage, 'success');
				setTimeout(function() {
					window.history.back();
				}, 1500);
			} else {
				showAlert('Error: ' + data.message, 'danger');
				if (btnCrear) {
					btnCrear.disabled = false;
					btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear';
				}
			}
		})
		.catch(error => {
			console.error('Error:', error);
			const errorMsg = action === 'edit' ?
				'Error al actualizar el proyecto: ' :
				'Error al crear el proyecto: ';
			showAlert(errorMsg + error.message, 'danger');
			if (btnCrear) {
				btnCrear.disabled = false;
				btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear';
			}
		});
}

function showAlert(message, type) {
	const alertContainer = document.getElementById('alertContainer');
	if (!alertContainer) {
		console.warn('Alert container not found');
		alert(message);
		return;
	}
	const alertDiv = document.createElement('div');
	alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
	alertDiv.setAttribute('role', 'alert');
	alertDiv.innerHTML = ` 
        ${message} 
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> 
    `;
	alertContainer.innerHTML = '';
	alertContainer.appendChild(alertDiv);
	setTimeout(function() {
		if (alertDiv.parentNode) {
			alertDiv.remove();
		}
	}, 5000);
}

function setupCharacterCounters() {
	const nombreInput = document.getElementById('nombre');
	const descripcionInput = document.getElementById('descripcion');
	if (nombreInput) {
		addCharacterCounter(nombreInput, 100);
	}
	if (descripcionInput) {
		addCharacterCounter(descripcionInput, 200);
	}
}

function addCharacterCounter(input, maxLength) {
	const counter = document.createElement('small');
	counter.className = 'form-text text-muted';
	counter.textContent = `0/${maxLength} caracteres`;
	input.parentElement.appendChild(counter);
	input.addEventListener('input', function() {
		const length = this.value.length;
		counter.textContent = `${length}/${maxLength} caracteres`;
		if (length > maxLength) {
			counter.classList.add('text-danger');
			counter.classList.remove('text-muted');
		} else {
			counter.classList.add('text-muted');
			counter.classList.remove('text-danger');
		}
	});
}
document.addEventListener('DOMContentLoaded', setupCharacterCounters);