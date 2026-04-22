
/**create_project.js creación y edición de proyectos con filtrado por departamento y soporte de Proyecto Libre*/

const editMode = {
    isEditing: false,
    projectId: null,
    originalEsLibre: null
};

// Estado para proyecto grupal
const grupalState = {
    selectedUsers: [],
    usuariosModal: null
};

//estado de la aplicacion con usuarios filtrados por departamento
const app = {
    usuarios: [],
    usuariosPorDepartamento: [],
    departamentoSeleccionado: null,
    isLibre: false
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

    cargarDepartamentos();
    loadUsuarios();
    setupFormHandlers();
    setupGrupalHandlers();
    setupDepartmentChangeHandler();
    setupLibreToggleHandler();

    // Si es edición, cargar datos del proyecto
    if (editMode.isEditing) {
        cargarProyectoParaEditar(editMode.projectId);
    }
});

function cargarDepartamentos() {
    fetch('../php/get_departments.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('La respuesta de red no fue ok');
            }
            return response.json();
        })

        .then(data => {
            if (data.success && data.departamentos) {
                const select = document.getElementById('id_departamento');
                data.departamentos.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.id_departamento;
                    option.textContent = dept.nombre;
                    select.appendChild(option);
                });

            } else {
                showAlert('Error al cargar departamentos', 'warning');
            }
        })

        .catch(error => {
            console.error('Error al cargar los departamentos:', error);
            showAlert('Error al cargar departamentos', 'danger');
        });
}

function loadUsuarios() {
    fetch('../php/get_users.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.usuarios) {
                app.usuarios = data.usuarios;
                populateUsuariosSelect(data.usuarios);
                populateGrupalModal(data.usuarios);
            } else {
                console.error('Error al cargar usuarios:', data.message);
            }
        })

        .catch(error => {
            console.error('Error en fetch de usuarios:', error);
        });
}

function loadUsuariosByDepartamento(idDepartamento) {

    // Si el proyecto es libre, siempre cargar todos los usuarios sin filtrar
    if (app.isLibre) {
        app.usuariosPorDepartamento = app.usuarios;
        populateUsuariosSelect(app.usuarios);
        populateGrupalModal(app.usuarios);
        return;
    }

    if (!idDepartamento || idDepartamento === '') {
        app.usuariosPorDepartamento = app.usuarios;
        populateUsuariosSelect(app.usuarios);
        populateGrupalModal(app.usuarios);
        return;
    }

    fetch(`../php/get_users_by_department.php?id_departamento=${idDepartamento}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.usuarios) {
                app.usuariosPorDepartamento = data.usuarios;
                app.departamentoSeleccionado = idDepartamento;

                populateUsuariosSelect(data.usuarios);
                populateGrupalModal(data.usuarios);

                if (data.usuarios.length === 0) {
                    showAlert('No hay usuarios disponibles en este departamento', 'info');
                }

            } else {
                console.error('Error al cargar usuarios por departamento:', data.message);
                showAlert('Error al cargar usuarios del departamento', 'warning');
            }
        })

        .catch(error => {
            console.error('Error en fetch de usuarios por departamento:', error);
            showAlert('Error al cargar usuarios del departamento', 'danger');
        });
}

function usuarioPerteneceADepartamento(idUsuario, idDepartamento) {
    // Si es proyecto libre, todos son válidos
    if (app.isLibre) {
        return true;
    }

    if (!idDepartamento || idDepartamento === '') {
        return true;
    }

    const usuario = app.usuarios.find(u => u.id_usuario == idUsuario);

    if (!usuario) {
        return false;
    }

    return usuario.id_departamento == idDepartamento;
}

function setupLibreToggleHandler() {
    const checkbox = document.getElementById('esLibre');
    const departamentoSelect = document.getElementById('id_departamento');
    const libreNotice = document.getElementById('libreNotice');
    const badgePreview = document.getElementById('libreBadgePreview');

    if (!checkbox) return;

    checkbox.addEventListener('change', function() {
        // En modo edición el checkbox está bloqueado no debería cambiar
        // pero si cambia revertir inmediatamente
        if (editMode.isEditing && editMode.originalEsLibre !== null) {
            const attempted = this.checked ? 1 : 0;
            if (attempted !== editMode.originalEsLibre) {
                this.checked = (editMode.originalEsLibre === 1);
                showAlert('No se puede cambiar el tipo de alcance de un proyecto existente', 'warning');
                return;
            }
        }

        applyLibreState(this.checked);
    });
}

function applyLibreState(isLibre) {
    const departamentoSelect = document.getElementById('id_departamento');
    const libreNotice = document.getElementById('libreNotice');
    const badgePreview = document.getElementById('libreBadgePreview');

    app.isLibre = isLibre;

    if (isLibre) {
        // Deshabilitar el dropdown de departamento y marcarlo como libre
        departamentoSelect.disabled = true;
        departamentoSelect.required = false;

        // Agregar opción proyecto libre al inicio si no existe
        let libreOption = departamentoSelect.querySelector('option[value="__libre__"]');
        if (!libreOption) {
            libreOption = document.createElement('option');
            libreOption.value = '__libre__';
            libreOption.textContent = '— Proyecto Libre —';
            departamentoSelect.insertBefore(libreOption, departamentoSelect.firstChild);
        }
        departamentoSelect.value = '__libre__';

        if (libreNotice) libreNotice.style.display = 'block';
        if (badgePreview) badgePreview.style.display = 'inline-flex';

        // Cargar todos los usuarios
        populateUsuariosSelect(app.usuarios);
        populateGrupalModal(app.usuarios);

    } else {
        // Rehabilitar dropdown
        departamentoSelect.disabled = false;
        departamentoSelect.required = true;

        // Quitar la opción proyecto libre
        const libreOption = departamentoSelect.querySelector('option[value="__libre__"]');
        if (libreOption) {
            libreOption.remove();
        }
        departamentoSelect.value = '';

        if (libreNotice) libreNotice.style.display = 'none';
        if (badgePreview) badgePreview.style.display = 'none';

        // Recargar usuarios según el departamento
        if (departamentoSelect.value) {
            loadUsuariosByDepartamento(departamentoSelect.value);
        } else {
            populateUsuariosSelect(app.usuarios);
            populateGrupalModal(app.usuarios);
        }
    }
}

function setupDepartmentChangeHandler() {
    const departamentoSelect = document.getElementById('id_departamento');
    departamentoSelect.addEventListener('change', function() {

        //si es proyecto libre se ignora cambios al departamento
        if (app.isLibre) return;

        const idDepartamento = this.value;
        const participanteSelect = document.getElementById('id_participante');
        const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked')?.value;

        // Validar usuario individual seleccionado
        if (tipoProyecto == '2' && participanteSelect.value && participanteSelect.value !== '0') {
            const usuarioSeleccionado = parseInt(participanteSelect.value);

            if (!usuarioPerteneceADepartamento(usuarioSeleccionado, idDepartamento)) {
                showConfirm(
                    'El usuario seleccionado no pertenece al departamento elegido. ¿Deseas continuar y cambiar el departamento? El usuario actual será deseleccionado.',
                    function() {
                        participanteSelect.value = '0';
                        loadUsuariosByDepartamento(idDepartamento);
                        showAlert('Usuario deseleccionado. Selecciona un usuario del nuevo departamento.', 'info');
                    },
                    'Cambiar departamento',
                    {
                        type: 'warning',
                        confirmText: 'Sí, cambiar',
                        cancelText: 'Cancelar',
                        onCancel: function() {
                            const usuarioData = app.usuarios.find(u => u.id_usuario == usuarioSeleccionado);
                            if (usuarioData && usuarioData.id_departamento) {
                                departamentoSelect.value = usuarioData.id_departamento;
                                showAlert('Cambio de departamento cancelado', 'info');
                            }
                        }
                    }
                );
                return;
            }
        }

        // Validar usuarios grupales seleccionados
        if (tipoProyecto == '1' && grupalState.selectedUsers.length > 0) {
            const usuariosInvalidos = grupalState.selectedUsers.filter(id =>
                !usuarioPerteneceADepartamento(id, idDepartamento)
            );

            if (usuariosInvalidos.length > 0) {
                const usuariosValidosCount = grupalState.selectedUsers.length - usuariosInvalidos.length;

                showConfirm(
                    `${usuariosInvalidos.length} usuario(s) seleccionado(s) no pertenece(n) al nuevo departamento. ¿Deseas continuar? Los usuarios que no pertenezcan al departamento serán deseleccionados.`,
                    function() {
                        grupalState.selectedUsers = grupalState.selectedUsers.filter(id =>
                            usuarioPerteneceADepartamento(id, idDepartamento)
                        );

                        loadUsuariosByDepartamento(idDepartamento);
                        updateSelectedCount();

                        if (usuariosValidosCount > 0) {
                            showAlert(`${usuariosInvalidos.length} usuario(s) deseleccionado(s). ${usuariosValidosCount} usuario(s) aún seleccionado(s).`, 'info');
                        } else {
                            showAlert('Todos los usuarios han sido deseleccionados. Selecciona usuarios del nuevo departamento.', 'info');
                        }

                        setTimeout(() => {
                            actualizarVisualizacionGrupal();
                        }, 100);
                    },

                    'Cambiar departamento',
                    {
                        type: 'warning',
                        confirmText: 'Sí, cambiar',
                        cancelText: 'Cancelar',
                        onCancel: function() {
                            if (app.departamentoSeleccionado) {
                                departamentoSelect.value = app.departamentoSeleccionado;
                                showAlert('Cambio de departamento cancelado', 'info');
                            }
                        }
                    }
                );
                return;
            }
        }

        loadUsuariosByDepartamento(idDepartamento);
    });
}

function actualizarVisualizacionGrupal() {
    document.querySelectorAll('.usuario-item').forEach(item => {
        const userId = parseInt(item.getAttribute('data-user-id'));
        const icon = item.querySelector('.usuario-selection-icon');

        if (grupalState.selectedUsers.includes(userId)) {
            icon.classList.remove('mdi-checkbox-blank-circle-outline');
            icon.classList.add('mdi-checkbox-marked-circle-outline');
            icon.style.color = '#009b4a';
            item.style.backgroundColor = '#ffffff';
            item.classList.add('selected');
        } else {
            icon.classList.remove('mdi-checkbox-marked-circle-outline');
            icon.classList.add('mdi-checkbox-blank-circle-outline');
            icon.style.color = '#999';
            item.style.backgroundColor = 'transparent';
            item.classList.remove('selected');
        }
    });
}

function populateUsuariosSelect(usuarios) {
    const select = document.getElementById('id_participante');
    if (!select) return;

    const currentValue = select.value;
    select.innerHTML = '<option value="0">Sin usuario asignado</option>';

    usuarios.forEach(usuario => {
        const option = document.createElement('option');
        option.value = usuario.id_usuario;
        //en modo libre incluir el departamento en la etiqueta para claridad
        if (app.isLibre) {
            const deptName = usuario.area || usuario.nombre_departamento || 'Sin depto.';
            option.textContent = `${usuario.nombre_completo} (#${usuario.num_empleado}) — ${deptName}`;
        } else {
            option.textContent = usuario.nombre_completo + ' (ID: ' + usuario.num_empleado + ')';
        }
        select.appendChild(option);
    });

    if (currentValue && usuarios.some(u => u.id_usuario == currentValue)) {
        select.value = currentValue;
    }
}

function populateGrupalModal(usuarios) {
    const container = document.getElementById('usuariosListContainer');
    if (!container) return;
    container.innerHTML = '';

    if (usuarios.length === 0) {
        const msg = app.isLibre
            ? 'No hay usuarios disponibles en el sistema'
            : 'No hay usuarios disponibles en este departamento';
        container.innerHTML = `<div class="text-center text-muted p-4">${msg}</div>`;
        return;
    }

    usuarios.forEach(usuario => {
        const userItem = document.createElement('div');
        userItem.className = 'usuario-item mb-3 p-3 border-bottom';
        userItem.setAttribute('data-user-id', usuario.id_usuario);
        userItem.style.cursor = 'pointer';
        userItem.style.transition = 'background-color 0.3s ease, box-shadow 0.3s ease';
        userItem.style.borderRadius = '4px';

        //en modo libre mostrar departamento del usuario
        const deptLine = app.isLibre
            ? `<small class="text-info d-block"><i class="mdi mdi-domain"></i> ${usuario.area || usuario.nombre_departamento || 'Sin departamento'}</small>`
            : '';

        userItem.innerHTML = `
              <div class="d-flex align-items-start justify-content-between">
                  <div class="flex-grow-1">
                      <strong class="d-block mb-1">${usuario.nombre_completo}</strong>
                      <small class="text-muted d-block">Empleado #${usuario.num_empleado}</small>
                      <small class="text-muted d-block">${usuario.e_mail}</small>
                      ${deptLine}
                  </div>

                  <div class="ms-3 d-flex align-items-center">
                      <i class="mdi mdi-checkbox-blank-circle-outline usuario-selection-icon"
                         style="font-size: 28px; color: #999; transition: all 0.3s ease; cursor: pointer;"></i>
                  </div>
              </div>
              <input type="hidden" class="usuario-id" value="${usuario.id_usuario}">
          `;

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

        if (grupalState.selectedUsers.includes(usuario.id_usuario)) {
            const icon = userItem.querySelector('.usuario-selection-icon');
            icon.classList.remove('mdi-checkbox-blank-circle-outline');
            icon.classList.add('mdi-checkbox-marked-circle-outline');
            icon.style.color = '#009b4a';
            userItem.style.backgroundColor = '#ffffff';
            userItem.classList.add('selected');
        }
    });
    setupUsuarioItemHandlers();
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

    if (btnSeleccionarGrupo && !btnSeleccionarGrupo.hasListener) {
        btnSeleccionarGrupo.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const departamentoSelect = document.getElementById('id_departamento');

            // En modo libre no se requiere departamento
            if (!app.isLibre && (!departamentoSelect.value || departamentoSelect.value === '')) {
                showAlert('Por favor, selecciona un departamento o marca Proyecto Libre antes de elegir usuarios para el proyecto grupal', 'warning');
                return;
            }

            document.querySelector('input[name="id_tipo_proyecto"][value="1"]').checked = true;

            if (!grupalState.usuariosModal) {
                grupalState.usuariosModal = new bootstrap.Modal(document.getElementById('grupalUsuariosModal'));
            }

            grupalState.usuariosModal.show();
            participanteField.disabled = true;
            participanteField.value = '';
            showAlert('Cambiado a proyecto grupal. Selecciona los integrantes del equipo.', 'info');
        });
        btnSeleccionarGrupo.hasListener = true;
    }

    tipoProyectoRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const departamentoSelect = document.getElementById('id_departamento');
            if (this.value == '1') { // Grupal
                if (!app.isLibre && (!departamentoSelect.value || departamentoSelect.value === '')) {
                    showAlert('Por favor, selecciona un departamento o marca Proyecto Libre antes de cambiar a proyecto grupal', 'warning');
                    document.querySelector('input[name="id_tipo_proyecto"][value="2"]').checked = true;
                    return;
                }

                if (!grupalState.usuariosModal) {
                    grupalState.usuariosModal = new bootstrap.Modal(document.getElementById('grupalUsuariosModal'));
                }

                grupalState.usuariosModal.show();
                participanteField.disabled = true;
                participanteField.value = '';
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

                // Determinar si es Libre y aplicar el estado
                const esLibre = parseInt(proyecto.es_libre) === 1;
                editMode.originalEsLibre = esLibre ? 1 : 0;

                const esLibreCheckbox = document.getElementById('esLibre');
                if (esLibreCheckbox) {
                    esLibreCheckbox.checked = esLibre;
                    // Bloquear el checkbox no se puede cambiar entre libre o no libre en edición
                    esLibreCheckbox.disabled = true;
                    esLibreCheckbox.title = 'No se puede cambiar el tipo de alcance de un proyecto existente';
                }

                applyLibreState(esLibre);

                if (!esLibre) {
                    document.getElementById('id_departamento').value = proyecto.id_departamento || '';
                    if (proyecto.id_departamento) {
                        loadUsuariosByDepartamento(proyecto.id_departamento);
                    }
                }

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
                document.getElementById('id_participante').value = proyecto.id_participante || 0;

                const tipoValue = proyecto.id_tipo_proyecto == 1 ? '1' : '2';
                document.querySelector(`input[name="id_tipo_proyecto"][value="${tipoValue}"]`).checked = true;

                if (tipoValue == '1' && proyecto.usuarios_asignados) {
                    grupalState.selectedUsers = proyecto.usuarios_asignados.map(u => u.id_usuario);
                    setTimeout(() => {
                        actualizarVisualizacionGrupal();
                        updateSelectedCount();
                    }, 500);
                }

                const puedeEditarOtros = proyecto.puede_editar_otros == 1 ? '1' : '0';
                document.querySelector(`input[name="puede_editar_otros"][value="${puedeEditarOtros}"]`).checked = true;
                if (proyecto.archivo_adjunto) {
                    document.getElementById('nombreArchivo').value = proyecto.archivo_adjunto.split('/').pop();
                }

                showAlert('Proyecto cargado correctamente', 'success');
            } else {
                showAlert('Error al cargar el proyecto: ' + data.message, 'danger');
                window.location.href = '../revisarProyectos/';
            }
        })

        .catch(error => {
            console.error('Error al cargar proyecto:', error);
            showAlert('Error al cargar el proyecto: ' + error.message, 'danger');
            window.location.href = '../revisarProyectos/';
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
                window.location.href = '../revisarProyectos/';
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

        const departamentoSelect = document.getElementById('id_departamento');

        // Si NO es libre requerir departamento
        if (!app.isLibre) {
            if (!departamentoSelect.value || departamentoSelect.value === '' || departamentoSelect.value === '__libre__') {
                showAlert('Por favor, selecciona un departamento para el proyecto', 'danger');
                return;
            }
        }

        if (editMode.isEditing) {
            editarProyecto();
        } else {
            crearProyecto();
        }
    });
}

function buildFormData(form) {
    const formData = new FormData(form);
    const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked').value;

    // Sobreescribir id_departamento si es libre
    if (app.isLibre) {
        formData.set('id_departamento', ''); // Será NULL en el backend
        formData.set('es_libre', '1');
    } else {
        formData.set('es_libre', '0');
    }

    // Usuarios grupales
    if (tipoProyecto == '1') {
        formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
    }

    // Permisos de edición
    formData.set('puede_editar_otros', document.querySelector('input[name="puede_editar_otros"]:checked').value);

    return formData;
}

function crearProyecto() {
    const form = document.getElementById('proyectoForm');
    const archivoInput = document.getElementById('archivoInput');
    const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked').value;

    if (!form.checkValidity()) {
        showAlert('Por favor, completa todos los campos requeridos', 'danger');
        form.classList.add('was-validated');
        return;
    }

    if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
        showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
        return;
    }

    const btnCrear = document.getElementById('btnCrear');
    btnCrear.disabled = true;
    btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...';

    if (archivoInput.files.length > 0) {
        uploadFile(archivoInput.files[0], function(filePath) {
            if (filePath) {
                const formData = buildFormData(form);
                formData.set('archivo_adjunto', filePath);
                submitForm(formData, btnCrear, 'create');
            } else {
                btnCrear.disabled = false;
                btnCrear.innerHTML = 'Crear';
            }
        });
    } else {
        const formData = buildFormData(form);
        formData.set('archivo_adjunto', '');
        submitForm(formData, btnCrear, 'create');
    }
}

function editarProyecto() {
    const form = document.getElementById('proyectoForm');
    const archivoInput = document.getElementById('archivoInput');
    const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked').value;

    if (!form.checkValidity()) {
        showAlert('Por favor, completa todos los campos requeridos', 'danger');
        form.classList.add('was-validated');
        return;
    }

    if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
        showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
        return;
    }

    const btnCrear = document.getElementById('btnCrear');
    btnCrear.disabled = true;
    btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Actualizando...';

    if (archivoInput.files.length > 0) {
        uploadFile(archivoInput.files[0], function(filePath) {
            if (filePath) {
                const formData = buildFormData(form);
                formData.set('archivo_adjunto', filePath);
                submitForm(formData, btnCrear, 'edit');
            } else {
                btnCrear.disabled = false;
                btnCrear.innerHTML = 'Actualizar';
            }
        });
    } else {
        const nombreArchivoField = document.getElementById('nombreArchivo').value;
        const formData = buildFormData(form);
        if (nombreArchivoField) {
            formData.set('archivo_adjunto', nombreArchivoField);
        }
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
                    window.location.href = '../revisarProyectos/';
                }, 1500);
            } else {
                showAlert('Error: ' + data.message, 'danger');
                btnCrear.disabled = false;
                btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorMsg = action === 'edit' ?
                'Error al actualizar el proyecto: ' :
                'Error al crear el proyecto: ';


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