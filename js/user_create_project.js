/*
 * user_create_project.js
 * Creación y edición de proyectos como usuario normal.
 * Soporta Proyecto Libre (multidepartamental) y proyecto grupal.
 */

/* ── edit mode ─────────────────────────────────────── */
const editMode = {
    isEditing: false,
    projectId: null,
    originalEsLibre: null
};

/* ── grupo selection state ──────────────────────────── */
const grupalState = {
    selectedUsers: [],
    usuariosModal: null
};

/* ── app-level data cache ───────────────────────────── */
const app = {
    usuarios: [],          // all users (unfiltered)
    isLibre: false,
    userDeptId: null       // current user's own department id
};

/* ═══════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function () {

    const params = new URLSearchParams(window.location.search);
    editMode.projectId = params.get('edit');
    editMode.isEditing = !!editMode.projectId;

    if (editMode.isEditing) {
        const titleEl    = document.querySelector('h4.card-title');
        const subtitleEl = document.querySelector('p.card-subtitle');
        const btnCrear   = document.getElementById('btnCrear');
        if (titleEl)    titleEl.textContent    = 'Editar Proyecto';
        if (subtitleEl) subtitleEl.textContent = 'Actualiza la información de tu proyecto';
        if (btnCrear)   btnCrear.textContent   = 'Actualizar';
    }

    cargarDepartamentoUsuario();
    loadAllUsuarios();
    setupFormHandlers();
    setupGrupalHandlers();
    setupLibreToggleHandler();
    setupCharacterCounters();

    if (editMode.isEditing) {
        cargarProyectoParaEditar(editMode.projectId);
    }
});

/* ═══════════════════════════════════════════════════════
   DEPARTMENT LOADER
   Loads user's own dept from get_user_department.php
   and stores it in app.userDeptId + fills the
   read-only display field.
═══════════════════════════════════════════════════════ */
function cargarDepartamentoUsuario() {
    fetch('../php/get_user_department.php')
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(data => {
            if (data.success && data.department) {
                const dept = data.department;

                // visible read-only field
                const deptDisplay = document.getElementById('departamento_display');
                if (deptDisplay) deptDisplay.value = dept.nombre;

                // hidden field used by backend for non-libre projects
                const deptHidden = document.getElementById('id_departamento');
                if (deptHidden) deptHidden.value = dept.id_departamento;

                app.userDeptId = dept.id_departamento;

                // pre-filter users to own dept once they're loaded
                // (called again after loadAllUsuarios resolves)
                if (!app.isLibre && app.usuarios.length > 0) {
                    filterAndPopulateUsers();
                }
            } else {
                showAlert('Error: ' + (data.message || 'No se pudo cargar el departamento'), 'warning');
                const deptDisplay = document.getElementById('departamento_display');
                if (deptDisplay) deptDisplay.value = 'No asignado';
            }
        })
        .catch(err => {
            console.error('Error al cargar departamento:', err);
            showAlert('Error al cargar tu departamento. Recarga la página.', 'danger');
        });
}

/* ═══════════════════════════════════════════════════════
   USER LOADERS
═══════════════════════════════════════════════════════ */

/** Load ALL users once; then filter client-side */
function loadAllUsuarios() {
    fetch('../php/get_users.php')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.usuarios) {
                app.usuarios = data.usuarios;
                // If dept is already known, filter immediately
                filterAndPopulateUsers();
            } else {
                console.error('Error al cargar usuarios:', data.message);
            }
        })
        .catch(err => console.error('Error fetch usuarios:', err));
}

/**
 * Filter app.usuarios by the current context:
 *  - isLibre  → show everyone
 *  - !isLibre → show only users whose primary dept = app.userDeptId
 * Then repopulate both the select and the modal list.
 */
function filterAndPopulateUsers() {
    let visible;

    if (app.isLibre) {
        visible = app.usuarios;
    } else {
        visible = app.userDeptId
            ? app.usuarios.filter(
                u => u.id_departamento == app.userDeptId
              )
            : app.usuarios;
    }

    populateUsuariosSelect(visible);
    populateGrupalModal(visible);
}

/* ═══════════════════════════════════════════════════════
   LIBRE TOGGLE
═══════════════════════════════════════════════════════ */
function setupLibreToggleHandler() {
    const checkbox = document.getElementById('esLibre');
    if (!checkbox) return;

    checkbox.addEventListener('change', function () {
        // In edit mode, revert immediately if someone tries to change
        if (editMode.isEditing && editMode.originalEsLibre !== null) {
            const attempted = this.checked ? 1 : 0;
            if (attempted !== editMode.originalEsLibre) {
                this.checked = (editMode.originalEsLibre === 1);
                showAlert(
                    'No se puede cambiar el tipo de alcance de un proyecto existente.',
                    'warning'
                );
                return;
            }
        }
        applyLibreState(this.checked);
    });
}

function applyLibreState(isLibre) {
    app.isLibre = isLibre;

    const libreNotice  = document.getElementById('libreNotice');
    const badgePreview = document.getElementById('libreBadgePreview');

    if (isLibre) {
        if (libreNotice)  libreNotice.style.display  = 'block';
        if (badgePreview) badgePreview.style.display = 'inline-flex';
    } else {
        if (libreNotice)  libreNotice.style.display  = 'none';
        if (badgePreview) badgePreview.style.display = 'none';
    }

    // Re-filter user lists
    filterAndPopulateUsers();

    // Reset any existing grupo selection that may now be invalid
    if (!isLibre && grupalState.selectedUsers.length > 0) {
        const deptId = app.userDeptId;
        if (deptId) {
            grupalState.selectedUsers = grupalState.selectedUsers.filter(id => {
                const u = app.usuarios.find(u => u.id_usuario == id);
                return u && u.id_departamento == deptId;
            });
            updateSelectedCount();
        }
    }
}

/* ═══════════════════════════════════════════════════════
   SELECT + MODAL POPULATION
═══════════════════════════════════════════════════════ */
function populateUsuariosSelect(usuarios) {
    const select = document.getElementById('id_participante');
    if (!select) return;

    const currentVal = select.value;
    select.innerHTML = '<option value="0">Sin usuario asignado</option>';

    usuarios.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id_usuario;
        if (app.isLibre) {
            const dept = u.area || u.nombre_departamento || 'Sin depto.';
            opt.textContent =
                `${u.nombre_completo} (#${u.num_empleado}) — ${dept}`;
        } else {
            opt.textContent =
                `${u.nombre_completo} (#${u.num_empleado})`;
        }
        select.appendChild(opt);
    });

    // Restore selection if still valid
    if (currentVal &&
        usuarios.some(u => u.id_usuario == currentVal)) {
        select.value = currentVal;
    }
}

function populateGrupalModal(usuarios) {
    const container = document.getElementById('usuariosListContainer');
    if (!container) return;
    container.innerHTML = '';

    if (!usuarios || usuarios.length === 0) {
        container.innerHTML =
            '<div class="text-center text-muted p-4">No hay usuarios disponibles</div>';
        return;
    }

    usuarios.forEach(u => {
        const item = document.createElement('div');
        item.className = 'usuario-item mb-3 p-3 border-bottom';
        item.setAttribute('data-user-id', u.id_usuario);
        item.style.cursor      = 'pointer';
        item.style.borderRadius = '4px';
        item.style.transition  = 'background-color 0.2s';

        const deptLine = app.isLibre
            ? `<small class="text-info d-block">
                   <i class="mdi mdi-domain"></i>
                   ${u.area || u.nombre_departamento || 'Sin departamento'}
               </small>`
            : '';

        item.innerHTML = `
            <div class="d-flex align-items-start justify-content-between">
                <div class="flex-grow-1">
                    <strong class="d-block mb-1">${u.nombre_completo}</strong>
                    <small class="text-muted d-block">Empleado #${u.num_empleado}</small>
                    <small class="text-muted d-block">${u.e_mail}</small>
                    ${deptLine}
                </div>
                <div class="ms-3 d-flex align-items-center">
                    <i class="mdi mdi-checkbox-blank-circle-outline usuario-selection-icon"
                       style="font-size:28px; color:#999; cursor:pointer;"></i>
                </div>
            </div>
        `;

        container.appendChild(item);

        // Hover effect
        item.addEventListener('mouseenter', function () {
            if (!this.classList.contains('selected')) {
                this.style.backgroundColor = '#f8f9fa';
            }
        });
        item.addEventListener('mouseleave', function () {
            if (!this.classList.contains('selected')) {
                this.style.backgroundColor = 'transparent';
            }
        });

        // Click to toggle
        item.addEventListener('click', function () {
            toggleUsuarioSelection(this);
        });

        // Restore checked state
        if (grupalState.selectedUsers.includes(u.id_usuario)) {
            const icon = item.querySelector('.usuario-selection-icon');
            icon.classList.replace(
                'mdi-checkbox-blank-circle-outline',
                'mdi-checkbox-marked-circle-outline'
            );
            icon.style.color = '#009b4a';
            item.style.backgroundColor = '#f0fff4';
            item.classList.add('selected');
        }
    });

    setupSearchHandler();
}

function toggleUsuarioSelection(item) {
    const userId = parseInt(item.getAttribute('data-user-id'));
    const icon   = item.querySelector('.usuario-selection-icon');
    const isSelected = icon.classList.contains('mdi-checkbox-marked-circle-outline');

    if (isSelected) {
        icon.classList.replace(
            'mdi-checkbox-marked-circle-outline',
            'mdi-checkbox-blank-circle-outline'
        );
        icon.style.color = '#999';
        item.style.backgroundColor = 'transparent';
        item.classList.remove('selected');
        grupalState.selectedUsers =
            grupalState.selectedUsers.filter(id => id !== userId);
    } else {
        icon.classList.replace(
            'mdi-checkbox-blank-circle-outline',
            'mdi-checkbox-marked-circle-outline'
        );
        icon.style.color = '#009b4a';
        item.style.backgroundColor = '#f0fff4';
        item.classList.add('selected');
        if (!grupalState.selectedUsers.includes(userId)) {
            grupalState.selectedUsers.push(userId);
        }
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    const el = document.getElementById('countSelected');
    if (el) el.textContent = grupalState.selectedUsers.length;
}

function setupSearchHandler() {
    const searchInput = document.getElementById('searchUsuarios');
    if (!searchInput || searchInput._hasListener) return;
    searchInput._hasListener = true;

    searchInput.addEventListener('keyup', function () {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.usuario-item').forEach(item => {
            item.style.display =
                item.textContent.toLowerCase().includes(term)
                    ? 'block'
                    : 'none';
        });
    });
}

function actualizarVisualizacionGrupal() {
    document.querySelectorAll('.usuario-item').forEach(item => {
        const userId = parseInt(item.getAttribute('data-user-id'));
        const icon   = item.querySelector('.usuario-selection-icon');
        if (!icon) return;

        const sel = grupalState.selectedUsers.includes(userId);
        icon.classList.toggle('mdi-checkbox-marked-circle-outline', sel);
        icon.classList.toggle('mdi-checkbox-blank-circle-outline', !sel);
        icon.style.color = sel ? '#009b4a' : '#999';
        item.style.backgroundColor = sel ? '#f0fff4' : 'transparent';
        item.classList.toggle('selected', sel);
    });
}

/* ═══════════════════════════════════════════════════════
   GRUPO HANDLERS
═══════════════════════════════════════════════════════ */
function setupGrupalHandlers() {
    const participanteField   = document.getElementById('id_participante');
    const btnSeleccionarGrupo = document.getElementById('btnSeleccionarGrupo');
    const tipoProyectoRadios  = document.querySelectorAll('input[name="id_tipo_proyecto"]');

    // "Grupo" button
    if (btnSeleccionarGrupo) {
        btnSeleccionarGrupo.addEventListener('click', function (e) {
            e.preventDefault();
            // Switch to grupal radio
            const grupalRadio = document.querySelector(
                'input[name="id_tipo_proyecto"][value="1"]'
            );
            if (grupalRadio) grupalRadio.checked = true;

            openGrupalModal();
            if (participanteField) {
                participanteField.disabled = true;
                participanteField.value    = '0';
            }
        });
    }

    // Tipo proyecto radios
    tipoProyectoRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            if (this.value === '1') { // Grupal
                openGrupalModal();
                if (participanteField) {
                    participanteField.disabled = true;
                    participanteField.value    = '0';
                }
            } else { // Individual
                // clear grupo selection
                grupalState.selectedUsers = [];
                actualizarVisualizacionGrupal();
                updateSelectedCount();
                if (participanteField) participanteField.disabled = false;
            }
        });
    });

    // Confirm button inside modal
    const btnConfirmar = document.getElementById('btnConfirmarGrupal');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function () {
            if (grupalState.selectedUsers.length === 0) {
                showAlert('Debes seleccionar al menos un usuario.', 'warning');
                return;
            }
            grupalState.usuariosModal.hide();
            showAlert(
                `${grupalState.selectedUsers.length} usuario(s) seleccionado(s) para el proyecto grupal.`,
                'success'
            );
        });
    }
}

function openGrupalModal() {
    if (!grupalState.usuariosModal) {
        grupalState.usuariosModal = new bootstrap.Modal(
            document.getElementById('grupalUsuariosModal')
        );
    }
    grupalState.usuariosModal.show();
}

/* ═══════════════════════════════════════════════════════
   FORM HANDLERS
═══════════════════════════════════════════════════════ */
function setupFormHandlers() {
    // File upload button
    const btnSubirArchivo = document.getElementById('btnSubirArchivo');
    if (btnSubirArchivo) {
        btnSubirArchivo.addEventListener('click', () => {
            document.getElementById('archivoInput').click();
        });
    }

    // File name display
    const archivoInput = document.getElementById('archivoInput');
    if (archivoInput) {
        archivoInput.addEventListener('change', e => {
            if (e.target.files.length > 0) {
                document.getElementById('nombreArchivo').value =
                    e.target.files[0].name;
            }
        });
    }

    // Cancel button
    const btnCancelar = document.getElementById('btnCancelar');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', () => {
            showConfirm(
                '¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.',
                () => { window.location.href = '../revisarProyectosUser/'; },
                'Cancelar cambios',
                {
                    type: 'warning',
                    confirmText: 'Sí, cancelar',
                    cancelText: 'Volver al formulario'
                }
            );
        });
    }

    // Form submit
    const proyectoForm = document.getElementById('proyectoForm');
    if (proyectoForm) {
        proyectoForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (editMode.isEditing) {
                editarProyecto();
            } else {
                crearProyecto();
            }
        });
    }
}

/* ═══════════════════════════════════════════════════════
   BUILD FORM DATA
═══════════════════════════════════════════════════════ */
function buildFormData(form) {
    const formData     = new FormData(form);
    const tipoProyecto = document.querySelector(
        'input[name="id_tipo_proyecto"]:checked'
    )?.value || '2';

    formData.set('es_libre',          app.isLibre ? '1' : '0');
    formData.set('id_tipo_proyecto',  tipoProyecto);
    formData.set(
        'puede_editar_otros',
        document.querySelector('input[name="puede_editar_otros"]:checked')?.value || '0'
    );

    // For libre projects the backend will use NULL dept; for regular projects
    // the hidden #id_departamento field already has the correct value.
    if (app.isLibre) {
        formData.set('id_departamento', '');
    }

    // Grupo users
    if (tipoProyecto === '1') {
        formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
    }

    return formData;
}

/* ═══════════════════════════════════════════════════════
   VALIDATION HELPERS
═══════════════════════════════════════════════════════ */
function validateBeforeSubmit() {
    const form         = document.getElementById('proyectoForm');
    const tipoProyecto = document.querySelector(
        'input[name="id_tipo_proyecto"]:checked'
    )?.value || '2';

    if (!form.checkValidity()) {
        showAlert('Por favor, completa todos los campos requeridos.', 'danger');
        form.classList.add('was-validated');
        return false;
    }

    // For regular projects, dept must be loaded
    if (!app.isLibre) {
        const idDepartamento = document.getElementById('id_departamento').value;
        if (!idDepartamento || idDepartamento === '0' || idDepartamento === '') {
            showAlert(
                'Error: No se ha cargado tu departamento. Recarga la página.',
                'danger'
            );
            return false;
        }
    }

    // Grupo must have at least one user
    if (tipoProyecto === '1' && grupalState.selectedUsers.length === 0) {
        showAlert('Debes seleccionar al menos un usuario para el proyecto grupal.', 'danger');
        return false;
    }

    return true;
}

/* ═══════════════════════════════════════════════════════
   CREATE
═══════════════════════════════════════════════════════ */
function crearProyecto() {
    if (!validateBeforeSubmit()) return;

    const form         = document.getElementById('proyectoForm');
    const archivoInput = document.getElementById('archivoInput');
    const btnCrear     = document.getElementById('btnCrear');

    btnCrear.disabled   = true;
    btnCrear.innerHTML  =
        '<span class="spinner-border spinner-border-sm me-2"></span>Creando...';

    const proceed = (filePath) => {
        const formData = buildFormData(form);
        formData.set('archivo_adjunto', filePath || '');
        submitForm(formData, btnCrear, 'create');
    };

    if (archivoInput.files.length > 0) {
        uploadFile(archivoInput.files[0], filePath => {
            if (filePath) {
                proceed(filePath);
            } else {
                btnCrear.disabled  = false;
                btnCrear.innerHTML = 'Crear';
            }
        });
    } else {
        proceed('');
    }
}

/* ═══════════════════════════════════════════════════════
   EDIT / LOAD FOR EDIT
═══════════════════════════════════════════════════════ */
function editarProyecto() {
    if (!validateBeforeSubmit()) return;

    const form         = document.getElementById('proyectoForm');
    const archivoInput = document.getElementById('archivoInput');
    const btnCrear     = document.getElementById('btnCrear');

    btnCrear.disabled  = true;
    btnCrear.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Actualizando...';

    const proceed = (filePath) => {
        const formData = buildFormData(form);
        if (filePath) {
            formData.set('archivo_adjunto', filePath);
        } else {
            const existing = document.getElementById('nombreArchivo').value;
            if (existing) formData.set('archivo_adjunto', existing);
        }
        submitForm(formData, btnCrear, 'edit');
    };

    if (archivoInput.files.length > 0) {
        uploadFile(archivoInput.files[0], filePath => {
            if (filePath) {
                proceed(filePath);
            } else {
                btnCrear.disabled  = false;
                btnCrear.innerHTML = 'Actualizar';
            }
        });
    } else {
        proceed(null);
    }
}

function cargarProyectoParaEditar(projectId) {
    fetch(`../php/get_project_by_id.php?id=${projectId}`)
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(data => {
            if (!data.success || !data.proyecto) {
                throw new Error(data.message || 'No se pudo cargar el proyecto');
            }
            const p = data.proyecto;

            document.getElementById('nombre').value      = p.nombre      || '';
            document.getElementById('descripcion').value = p.descripcion || '';
            document.getElementById('progreso').value    = p.progreso    || 0;
            document.getElementById('ar').value          = p.ar          || '';
            document.getElementById('estado').value      = p.estado      || 'pendiente';

            if (p.fecha_inicio) {
                document.getElementById('fecha_creacion').value =
                    p.fecha_inicio.replace(' ', 'T').substring(0, 16);
            }
            if (p.fecha_cumplimiento) {
                document.getElementById('fecha_cumplimiento').value =
                    p.fecha_cumplimiento.split(' ')[0];
            }
            if (p.archivo_adjunto) {
                document.getElementById('nombreArchivo').value =
                    p.archivo_adjunto.split('/').pop();
            }

            // Tipo proyecto radio
            const tipoVal = p.id_tipo_proyecto == 1 ? '1' : '2';
            const tipoRadio = document.querySelector(
                `input[name="id_tipo_proyecto"][value="${tipoVal}"]`
            );
            if (tipoRadio) tipoRadio.checked = true;

            // Permisos edición
            const editVal = p.puede_editar_otros == 1 ? '1' : '0';
            const editRadio = document.querySelector(
                `input[name="puede_editar_otros"][value="${editVal}"]`
            );
            if (editRadio) editRadio.checked = true;

            // es_libre — lock the checkbox
            const esLibre     = parseInt(p.es_libre) === 1;
            editMode.originalEsLibre = esLibre ? 1 : 0;
            const checkbox = document.getElementById('esLibre');
            if (checkbox) {
                checkbox.checked  = esLibre;
                checkbox.disabled = true;
                checkbox.title    = 'No se puede cambiar el tipo de alcance de un proyecto existente';
            }
            applyLibreState(esLibre);

            // Participants
            if (tipoVal === '1' && p.usuarios_asignados) {
                grupalState.selectedUsers =
                    p.usuarios_asignados.map(u => u.id_usuario);
                setTimeout(() => {
                    actualizarVisualizacionGrupal();
                    updateSelectedCount();
                }, 500);
                // disable individual select
                const sel = document.getElementById('id_participante');
                if (sel) sel.disabled = true;
            } else {
                document.getElementById('id_participante').value =
                    p.id_participante || 0;
            }

            showAlert('Proyecto cargado correctamente.', 'success');
        })
        .catch(err => {
            console.error('Error cargando proyecto:', err);
            showAlert('Error al cargar el proyecto: ' + err.message, 'danger');
            setTimeout(() => {
                window.location.href = '../revisarProyectosUser/';
            }, 2000);
        });
}

/* ═══════════════════════════════════════════════════════
   SUBMIT + FILE UPLOAD
═══════════════════════════════════════════════════════ */
function uploadFile(file, callback) {
    const fd = new FormData();
    fd.append('archivo', file);

    fetch('../php/upload_file.php', { method: 'POST', body: fd })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(data => {
            if (data.success) {
                callback(data.filePath);
            } else {
                showAlert('Error al subir el archivo: ' + data.message, 'danger');
                callback(null);
            }
        })
        .catch(err => {
            showAlert('Error al subir el archivo: ' + err.message, 'danger');
            callback(null);
        });
}

function submitForm(formData, btnCrear, action) {
    // User-side endpoints
    const endpoint = action === 'edit'
        ? '../php/user_update_project.php'
        : '../php/user_create_project.php';

    if (editMode.isEditing) {
        formData.append('id_proyecto', editMode.projectId);
    }

    fetch(endpoint, { method: 'POST', body: formData })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(data => {
            if (data.success) {
                showAlert(
                    action === 'edit'
                        ? '¡Proyecto actualizado exitosamente!'
                        : '¡Proyecto creado exitosamente!',
                    'success'
                );
                setTimeout(() => {
                    window.location.href = '../revisarProyectosUser/';
                }, 1500);
            } else {
                showAlert('Error: ' + data.message, 'danger');
                btnCrear.disabled  = false;
                btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear';
            }
        })
        .catch(err => {
            showAlert(
                (action === 'edit'
                    ? 'Error al actualizar el proyecto: '
                    : 'Error al crear el proyecto: ') + err.message,
                'danger'
            );
            btnCrear.disabled  = false;
            btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear';
        });
}

/* ═══════════════════════════════════════════════════════
   UI HELPERS
═══════════════════════════════════════════════════════ */
function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    if (!container) return;

    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.setAttribute('role', 'alert');
    div.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    container.innerHTML = '';
    container.appendChild(div);

    setTimeout(() => { if (div.parentNode) div.remove(); }, 5000);
}

function setupCharacterCounters() {
    [
        { id: 'nombre',      max: 100 },
        { id: 'descripcion', max: 200 }
    ].forEach(({ id, max }) => {
        const el = document.getElementById(id);
        if (!el) return;

        const counter = document.createElement('small');
        counter.className   = 'form-text text-muted';
        counter.textContent = `0/${max} caracteres`;
        el.parentElement.appendChild(counter);

        el.addEventListener('input', function () {
            const len = this.value.length;
            counter.textContent = `${len}/${max} caracteres`;
            counter.classList.toggle('text-danger', len > max);
            counter.classList.toggle('text-muted',  len <= max);
        });
    });
}

/* expose for debugging */
window.grupalState = grupalState;
window.app         = app;