/*manager_create_project.js Creación y edición de proyectos para gerentes*/

const editMode = {
    isEditing: false,
    projectId: null
};

// Estado para proyecto grupal
const grupalState = {
    selectedUsers: [],
    usuariosModal: null
};

// Estado del gerente y sus departamentos
const managerState = {
    departmentId: null,
    departmentName: null,
    managedDepartments: [], // Array de departamentos que gestiona
    isAdmin: false
};

const app = {
    usuarios: []
};

// Inicializar página al cargar
document.addEventListener('DOMContentLoaded', function() {
    // Detectar si estamos en modo edición
    const params = new URLSearchParams(window.location.search);
    editMode.projectId = params.get('edit');
    editMode.isEditing = !!editMode.projectId;

    // Cambiar título y botón si estamos editando
    if (editMode.isEditing) {
        document.querySelector('h4.card-title').textContent = 'Editar Proyecto';
        document.querySelector('p.card-subtitle').textContent = 'Actualiza la información del proyecto';
        document.getElementById('btnCrear').textContent = 'Actualizar';
    }

    cargarDepartamentosGerente(); // Cargar departamentos del gerente
    setupFormHandlers();
    setupGrupalHandlers();
    setupDepartmentChangeHandler();

    // Si es edición, cargar datos del proyecto después de cargar departamentos
    if (editMode.isEditing) {
        // Esperar a que se carguen los departamentos antes de cargar el proyecto
        setTimeout(() => {
            cargarProyectoParaEditar(editMode.projectId);
        }, 500);
    }
});

function cargarDepartamentosGerente() {
    fetch('../php/manager_get_departments.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('La respuesta de red no fue ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Respuesta de departamentos:', data);
            
            if (data.success && data.departamentos && data.departamentos.length > 0) {
                const select = document.getElementById('id_departamento');
                const hiddenInput = document.getElementById('id_departamento_hidden');
                const helpText = select.parentElement.querySelector('.form-text');
                
                // Guardar información de departamentos gestionados
                managerState.managedDepartments = data.departamentos;
                managerState.isAdmin = data.debug?.is_admin || false;
                
                // Limpiar el select
                select.innerHTML = '';
                
                if (data.departamentos.length === 1) {
                    // Solo un departamento - mantener deshabilitado
                    const dept = data.departamentos[0];
                    managerState.departmentId = dept.id_departamento;
                    managerState.departmentName = dept.nombre;
                    
                    const option = document.createElement('option');
                    option.value = dept.id_departamento;
                    option.textContent = dept.nombre;
                    option.selected = true;
                    select.appendChild(option);
                    
                    hiddenInput.value = dept.id_departamento;
                    select.disabled = true;
                    select.style.cursor = 'not-allowed';
                    
                    if (helpText) {
                        helpText.innerHTML = '<i class="mdi mdi-information-outline"></i> Tu departamento está asignado automáticamente';
                    }
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
                    data.departamentos.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id_departamento;
                        option.textContent = dept.nombre;
                        // Indicar si es departamento gestionado como gerente
                        if (dept.is_managed) {
                            option.textContent += ' (Gerente)';
                        }
                        select.appendChild(option);
                    });
                    
                    // Seleccionar el primer departamento por defecto
                    if (data.departamentos.length > 0) {
                        select.value = data.departamentos[0].id_departamento;
                        managerState.departmentId = data.departamentos[0].id_departamento;
                        managerState.departmentName = data.departamentos[0].nombre;
                        hiddenInput.value = data.departamentos[0].id_departamento;
                    }
                    
                    if (helpText) {
                        helpText.innerHTML = '<i class="mdi mdi-information-outline"></i> Selecciona el departamento para el proyecto';
                    }
                }
                
                // Cargar usuarios del departamento seleccionado
                loadUsuariosDepartamento(managerState.departmentId);
                
            } else {
                showAlert('Error: No se pudieron cargar tus departamentos. ' + (data.message || ''), 'danger');
            }
        })
        .catch(error => {
            console.error('Error al cargar departamentos:', error);
            showAlert('Error al cargar departamentos: ' + error.message, 'danger');
        });
}

function setupDepartmentChangeHandler() {
    const select = document.getElementById('id_departamento');
    const hiddenInput = document.getElementById('id_departamento_hidden');
    
    select.addEventListener('change', function() {
        const selectedDeptId = parseInt(this.value);
        
        if (selectedDeptId > 0) {
            // Actualizar estado
            managerState.departmentId = selectedDeptId;
            hiddenInput.value = selectedDeptId;
            
            // Encontrar el nombre del departamento
            const selectedDept = managerState.managedDepartments.find(d => d.id_departamento === selectedDeptId);
            if (selectedDept) {
                managerState.departmentName = selectedDept.nombre;
            }
            
            // Limpiar selección de usuarios para proyecto grupal
            grupalState.selectedUsers = [];
            updateSelectedCount();
            
            // Recargar usuarios del nuevo departamento
            loadUsuariosDepartamento(selectedDeptId);
            
            console.log(`Departamento cambiado a: ${managerState.departmentName} (ID: ${selectedDeptId})`);
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

    fetch(`../php/manager_get_users.php?id_departamento=${departmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.usuarios) {
                app.usuarios = data.usuarios;
                populateUsuariosSelect(data.usuarios);
                populateGrupalModal(data.usuarios);
                
                console.log(`${data.usuarios.length} usuarios cargados del departamento ${departmentId}`);
                
                if (data.usuarios.length === 0) {
                    showAlert('No hay usuarios disponibles en este departamento', 'info');
                }
            } else {
                console.error('Error al cargar usuarios:', data.message);
                showAlert('Error al cargar usuarios: ' + (data.message || 'Error desconocido'), 'warning');
                
                // Limpiar selects
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
        option.textContent = `${usuario.nombre_completo} (ID: ${usuario.num_empleado})`;
        select.appendChild(option);
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
        const userItem = document.createElement('div');
        userItem.className = 'usuario-item mb-3 p-3 border-bottom';
        userItem.setAttribute('data-user-id', usuario.id_usuario);
        userItem.style.cursor = 'pointer';
        userItem.style.transition = 'background-color 0.3s ease, box-shadow 0.3s ease';
        userItem.style.borderRadius = '4px';

        // Verificar si este usuario ya estaba seleccionado
        const isSelected = grupalState.selectedUsers.includes(usuario.id_usuario);
        const iconClass = isSelected ? 'mdi-checkbox-marked-circle-outline' : 'mdi-checkbox-blank-circle-outline';
        const iconColor = isSelected ? '#009b4a' : '#999';
        const bgColor = isSelected ? '#ffffff' : 'transparent';

        userItem.innerHTML = `
            <div class="d-flex align-items-start justify-content-between">
                <div class="flex-grow-1">
                    <strong class="d-block mb-1">${usuario.nombre_completo}</strong>
                    <small class="text-muted d-block">Empleado #${usuario.num_empleado}</small>
                    <small class="text-muted d-block">${usuario.e_mail}</small>
                    <small class="text-muted d-block"><i class="mdi mdi-domain"></i> ${usuario.area || 'Sin área'}</small>
                </div>
                <div class="ms-3 d-flex align-items-center">
                    <i class="mdi ${iconClass} usuario-selection-icon" 
                       style="font-size: 28px; color: ${iconColor}; transition: all 0.3s ease; cursor: pointer;"></i>
                </div>
            </div>
            <input type="hidden" class="usuario-id" value="${usuario.id_usuario}">
        `;

        if (isSelected) {
            userItem.classList.add('selected');
            userItem.style.backgroundColor = bgColor;
        }

        container.appendChild(userItem);

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
    });

    setupUsuarioItemHandlers();
}

function toggleUsuarioSelection(userItem) {
    const userId = parseInt(userItem.getAttribute('data-user-id'));
    const icon = userItem.querySelector('.usuario-selection-icon');

    const isSelected = icon.classList.contains('mdi-checkbox-marked-circle-outline');

    if (isSelected) {
        // Deseleccionar
        icon.classList.remove('mdi-checkbox-marked-circle-outline');
        icon.classList.add('mdi-checkbox-blank-circle-outline');
        icon.style.color = '#999';
        userItem.style.backgroundColor = 'transparent';
        userItem.classList.remove('selected');

        grupalState.selectedUsers = grupalState.selectedUsers.filter(id => id !== userId);
    } else {
        // Seleccionar
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
                if (text.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        searchInput.hasEventListener = true;
    }
}

function updateSelectedCount() {
    const checkedCount = grupalState.selectedUsers.length;
    document.getElementById('countSelected').textContent = checkedCount;
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

            // Verificar que hay un departamento seleccionado
            if (!managerState.departmentId) {
                showAlert('Por favor, selecciona un departamento primero', 'warning');
                return;
            }

            // Cambiar a proyecto grupal
            document.querySelector('input[name="id_tipo_proyecto"][value="1"]').checked = true;

            // Mostrar modal
            if (!grupalState.usuariosModal) {
                grupalState.usuariosModal = new bootstrap.Modal(document.getElementById('grupalUsuariosModal'));
            }
            grupalState.usuariosModal.show();

            // Desactivar campo de participante individual
            participanteField.disabled = true;
            participanteField.value = '';

            showAlert('Cambiado a proyecto grupal. Selecciona los integrantes del equipo.', 'info');
        });
        btnSeleccionarGrupo.hasListener = true;
    }

    tipoProyectoRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value == '1') { // Grupal
                // Verificar departamento
                if (!managerState.departmentId) {
                    showAlert('Por favor, selecciona un departamento primero', 'warning');
                    document.querySelector('input[name="id_tipo_proyecto"][value="2"]').checked = true;
                    return;
                }

                // Mostrar modal de selección grupal
                if (!grupalState.usuariosModal) {
                    grupalState.usuariosModal = new bootstrap.Modal(document.getElementById('grupalUsuariosModal'));
                }
                grupalState.usuariosModal.show();
                participanteField.disabled = true;
                participanteField.value = '';
            } else { // Individual
                grupalState.selectedUsers = [];

                // Limpiar estado visual
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
                participanteField.disabled = false;
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
            grupalState.usuariosModal.hide();
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

                document.getElementById('nombre').value = proyecto.nombre || '';
                document.getElementById('descripcion').value = proyecto.descripcion || '';

                // Establecer el departamento del proyecto
                if (proyecto.id_departamento) {
                    const deptSelect = document.getElementById('id_departamento');
                    const hiddenInput = document.getElementById('id_departamento_hidden');
                    
                    // Verificar si el departamento del proyecto está en los departamentos gestionados
                    const deptPermitido = managerState.managedDepartments.find(
                        d => d.id_departamento === proyecto.id_departamento
                    );
                    
                    if (deptPermitido || managerState.isAdmin) {
                        deptSelect.value = proyecto.id_departamento;
                        hiddenInput.value = proyecto.id_departamento;
                        managerState.departmentId = proyecto.id_departamento;
                        
                        // Recargar usuarios del departamento del proyecto
                        loadUsuariosDepartamento(proyecto.id_departamento);
                    } else {
                        showAlert('Advertencia: Este proyecto pertenece a un departamento que no gestionas', 'warning');
                    }
                }

                // Fechas
                if (proyecto.fecha_inicio) {
                    const fechaInicio = proyecto.fecha_inicio.replace(' ', 'T').substring(0, 16);
                    document.getElementById('fecha_creacion').value = fechaInicio;
                }

                if (proyecto.fecha_cumplimiento) {
                    const fechaCumplimiento = proyecto.fecha_cumplimiento.split(' ')[0];
                    document.getElementById('fecha_cumplimiento').value = fechaCumplimiento;
                }

                document.getElementById('progreso').value = proyecto.progreso || 0;
                document.getElementById('ar').value = proyecto.ar || '';
                document.getElementById('estado').value = proyecto.estado || 'pendiente';

                // Tipo de proyecto
                const tipoValue = proyecto.id_tipo_proyecto == 1 ? '1' : '2';
                document.querySelector(`input[name="id_tipo_proyecto"][value="${tipoValue}"]`).checked = true;

                // Participante individual
                setTimeout(() => {
                    if (proyecto.id_participante) {
                        document.getElementById('id_participante').value = proyecto.id_participante;
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
                document.querySelector(`input[name="puede_editar_otros"][value="${puedeEditarOtros}"]`).checked = true;

                if (proyecto.archivo_adjunto) {
                    document.getElementById('nombreArchivo').value = proyecto.archivo_adjunto.split('/').pop();
                }

                showAlert('Proyecto cargado correctamente', 'success');
            } else {
                showAlert('Error al cargar el proyecto: ' + data.message, 'danger');
                window.location.href = '../revisarProyectosGerente/';
            }
        })
        .catch(error => {
            console.error('Error al cargar proyecto:', error);
            showAlert('Error al cargar el proyecto: ' + error.message, 'danger');
            window.location.href = '../revisarProyectosGerente/';
        });
}

function setupFormHandlers() {
    document.getElementById('btnSubirArchivo').addEventListener('click', function() {
        document.getElementById('archivoInput').click();
    });

    document.getElementById('archivoInput').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            document.getElementById('nombreArchivo').value = e.target.files[0].name;
        }
    });

    document.getElementById('btnCancelar').addEventListener('click', function() {
        showConfirm(
            '¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.',
            function() {
                window.location.href = '../revisarProyectosGerente/';
            },
            'Cancelar cambios',
            {
                type: 'warning',
                confirmText: 'Sí, cancelar',
                cancelText: 'Volver al formulario'
            }
        );
    });

    document.getElementById('proyectoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (editMode.isEditing) {
            editarProyecto();
        } else {
            crearProyecto();
        }
    });
}

function crearProyecto() {
    const form = document.getElementById('proyectoForm');
    const formData = new FormData(form);
    const archivoInput = document.getElementById('archivoInput');
    const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked').value;

    if (!form.checkValidity()) {
        showAlert('Por favor, completa todos los campos requeridos', 'danger');
        form.classList.add('was-validated');
        return;
    }

    // Validar que el departamento esté establecido
    if (!managerState.departmentId) {
        showAlert('Error: Debes seleccionar un departamento', 'danger');
        return;
    }

    // Revisar que se seleccionen usuarios para el proyecto grupal
    if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
        showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
        return;
    }

    const btnCrear = document.getElementById('btnCrear');
    btnCrear.disabled = true;
    btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...';

    // Asegurar que el departamento correcto esté en el formData
    formData.set('id_departamento', managerState.departmentId);

    if (archivoInput.files.length > 0) {
        uploadFile(archivoInput.files[0], function(filePath) {
            if (filePath) {
                formData.set('archivo_adjunto', filePath);
                if (tipoProyecto == '1') {
                    formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
                }
                formData.set('puede_editar_otros', document.querySelector('input[name="puede_editar_otros"]:checked').value);
                submitForm(formData, btnCrear, 'create');
            } else {
                btnCrear.disabled = false;
                btnCrear.innerHTML = 'Crear';
            }
        });
    } else {
        formData.set('archivo_adjunto', '');
        if (tipoProyecto == '1') {
            formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
        }
        formData.set('puede_editar_otros', document.querySelector('input[name="puede_editar_otros"]:checked').value);
        submitForm(formData, btnCrear, 'create');
    }
}

function editarProyecto() {
    const form = document.getElementById('proyectoForm');
    const formData = new FormData(form);
    const archivoInput = document.getElementById('archivoInput');
    const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked').value;

    if (!form.checkValidity()) {
        showAlert('Por favor, completa todos los campos requeridos', 'danger');
        form.classList.add('was-validated');
        return;
    }

    // Validar que el departamento esté establecido
    if (!managerState.departmentId) {
        showAlert('Error: Debes seleccionar un departamento', 'danger');
        return;
    }

    if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
        showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
        return;
    }

    const btnCrear = document.getElementById('btnCrear');
    btnCrear.disabled = true;
    btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Actualizando...';

    // Asegurar que el departamento correcto esté en el formData
    formData.set('id_departamento', managerState.departmentId);

    if (archivoInput.files.length > 0) {
        uploadFile(archivoInput.files[0], function(filePath) {
            if (filePath) {
                formData.set('archivo_adjunto', filePath);
                if (tipoProyecto == '1') {
                    formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
                }
                formData.set('puede_editar_otros', document.querySelector('input[name="puede_editar_otros"]:checked').value);
                submitForm(formData, btnCrear, 'edit');
            } else {
                btnCrear.disabled = false;
                btnCrear.innerHTML = 'Actualizar';
            }
        });
    } else {
        const nombreArchivoField = document.getElementById('nombreArchivo').value;
        if (nombreArchivoField) {
            formData.set('archivo_adjunto', nombreArchivoField);
        }
        if (tipoProyecto == '1') {
            formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
        }
        formData.set('puede_editar_otros', document.querySelector('input[name="puede_editar_otros"]:checked').value);
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
    const endpoint = action === 'edit'
        ? '../php/update_project.php'
        : '../php/create_project.php';

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
            const successMessage = action === 'edit'
                ? '¡Proyecto actualizado exitosamente!'
                : '¡Proyecto creado exitosamente!';

            showAlert(successMessage, 'success');

            setTimeout(function() {
                window.location.href = '../revisarProyectosGerente/';
            }, 1500);
        } else {
            showAlert('Error: ' + data.message, 'danger');
            btnCrear.disabled = false;
            btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const errorMsg = action === 'edit'
            ? 'Error al actualizar el proyecto: '
            : 'Error al crear el proyecto: ';

        showAlert(errorMsg + error.message, 'danger');
        btnCrear.disabled = false;
        btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear';
    });
}

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
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