// user_register.js para registrar usuarios

(function() {
    'use strict';

    // Estado global de la pagina
    const app = {
        departamentos: [],
        roles: [],
        selectedImage: null,

        // Estado del selector de superior
        superior: {
            selected: null,            // {id_usuario, nombre_completo, num_empleado, ...} | null
            esSupervisorMode: false,   // toggle actual dentro del modal
            cache: [],                 // lista completa recibida del backend
            filtered: [],              // lista despues de aplicar la busqueda
            activeIndex: -1            // indice del item resaltado por teclado
        }
    };

    const IMAGE_CONFIG = {
        MAX_SIZE: 5 * 1024 * 1024,
        ALLOWED_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        ALLOWED_EXTENSIONS: ['jpg', 'jpeg', 'png', 'gif', 'webp']
    };

    const SUPERVISOR_SENTINEL_ID = -2;

    document.addEventListener('DOMContentLoaded', initializeApp);

    function initializeApp() {
        loadDepartamentos();
        loadRoles();
        setupEventHandlers();
        setupProfilePictureHandlers();
        setupSuperiorModal();

        // Pre-cargar la lista de gerentes normales para que el modal abra con datos listos
        loadManagersIntoCache(false);
    }

    function setupEventHandlers() {
        const form = document.getElementById('formCrearUsuario');
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }

        const resetButton = form ? form.querySelector('button[type="reset"]') : null;
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                hideAlert();
                clearImagePreview();
                setTimeout(function() {
                    loadDepartamentos();
                    loadRoles();

                    // Limpiar seleccion de superior
                    clearSuperiorSelection();

                    // Resetear toggle del modal y recargar cache a gerentes normales
                    const modalToggle = document.getElementById('modalToggleEsSupervisor');
                    if (modalToggle) modalToggle.checked = false;
                    app.superior.esSupervisorMode = false;
                    loadManagersIntoCache(false);
                }, 100);
            });
        }

        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', handlePasswordToggle);
        }

        const rolSelect = document.getElementById('id_rol');
        if (rolSelect) {
            rolSelect.addEventListener('change', function() {
                updateSubmitButtonLabel(parseInt(rolSelect.value));
            });
        }

        setupRealtimeValidation();
    }

    function updateSubmitButtonLabel(selectedRolId) {
        const btn = document.getElementById('btnSubmit');
        if (!btn) return;

        if (selectedRolId === SUPERVISOR_SENTINEL_ID) {
            btn.innerHTML = '<i class="mdi mdi-account-tie"></i> Crear Supervisor';
        } else {
            btn.innerHTML = '<i class="mdi mdi-account-plus"></i> Crear Usuario';
        }
    }

    function setupSuperiorModal() {
        const btnClear = document.getElementById('btnClearSuperior');
        if (btnClear) {
            btnClear.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                clearSuperiorSelection();
            });
        }

        // Toggle dentro del modal se recarga cache
        const modalToggle = document.getElementById('modalToggleEsSupervisor');
        if (modalToggle) {
            modalToggle.addEventListener('change', function() {
                app.superior.esSupervisorMode = this.checked;
                updateModalModeIndicator();
                loadManagersIntoCache(this.checked);
            });
        }

        // Buscador
        const searchInput = document.getElementById('modalSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                applySearchFilter(this.value);
            });
            searchInput.addEventListener('keydown', handleSearchKeydown);
        }

        const btnClearSearch = document.getElementById('btnClearSearch');
        if (btnClearSearch) {
            btnClearSearch.addEventListener('click', function() {
                const input = document.getElementById('modalSearchInput');
                if (input) {
                    input.value = '';
                    applySearchFilter('');
                    input.focus();
                }
            });
        }

        // Limpiar busqueda y enfocar al abrir el modal
        const modalEl = document.getElementById('superiorSearchModal');
        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', function() {
                const input = document.getElementById('modalSearchInput');
                if (input) {
                    input.value = '';
                    input.focus();
                }
                const toggle = document.getElementById('modalToggleEsSupervisor');
                if (toggle) toggle.checked = app.superior.esSupervisorMode;
                //renderizar otra vez utilizando lo que hay en cache
                applySearchFilter('');
            });
        }
    }

    function updateModalModeIndicator() {
        const el = document.getElementById('modalModeIndicator');
        if (!el) return;
        el.textContent = app.superior.esSupervisorMode
            ? 'Mostrando: Supervisores'
            : 'Mostrando: Gerentes';
    }

    function loadManagersIntoCache(esSupervisor) {
        const flag = esSupervisor ? 1 : 0;
        const url = '../php/get_managers.php?es_supervisor=' + flag;

        // Mostrar loading en la lista
        const listEl = document.getElementById('modalManagerList');
        if (listEl) {
            listEl.innerHTML = `
                <div class="manager-list-empty">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    Cargando ${esSupervisor ? 'supervisores' : 'gerentes'}...
                </div>`;
        }
        const counter = document.getElementById('modalResultsCounter');
        if (counter) counter.textContent = 'Cargando...';

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.managers)) {
                    app.superior.cache = data.managers;
                    // Re-aplicar filtro actual (si el usuario ya escribio algo)
                    const input = document.getElementById('modalSearchInput');
                    applySearchFilter(input ? input.value : '');
                } else {
                    console.error('Error al cargar gerentes:', data.message);
                    app.superior.cache = [];
                    applySearchFilter('');
                }
            })
            .catch(error => {
                console.error('Error en fetch de gerentes:', error);
                app.superior.cache = [];
                renderManagerListError();
            });
    }

    function applySearchFilter(queryRaw) {
        const query = (queryRaw || '').trim().toLowerCase();

        if (!query) {
            app.superior.filtered = app.superior.cache.slice();
        } else {
            app.superior.filtered = app.superior.cache.filter(m => {
                const haystack = [
                    m.nombre_completo || '',
                    m.nombre || '',
                    m.apellido || '',
                    String(m.num_empleado || ''),
                    m.nombre_departamento || '',
                    m.e_mail || ''
                ].join(' ').toLowerCase();
                return haystack.includes(query);
            });
        }

        app.superior.activeIndex = app.superior.filtered.length > 0 ? 0 : -1;
        renderManagerList();
    }

    function renderManagerList() {
        const listEl = document.getElementById('modalManagerList');
        const counter = document.getElementById('modalResultsCounter');
        if (!listEl) return;

        const items = app.superior.filtered;
        const mode = app.superior.esSupervisorMode ? 'supervisores' : 'gerentes';

        if (counter) {
            counter.textContent = items.length === 1
                ? '1 resultado'
                : items.length + ' resultados';
        }

        let html = `
            <div class="manager-item clear-option" role="option" tabindex="0" data-clear="1">
                <div class="no-photo"><i class="mdi mdi-account-off"></i></div>
                <div class="info">
                    <div class="name">Sin superior asignado</div>
                    <div class="meta">Quitar cualquier seleccion previa</div>
                </div>
            </div>
        `;

        if (items.length === 0) {
            html += `
                <div class="manager-list-empty">
                    <i class="mdi mdi-account-search" style="font-size: 2rem; color: #ced4da;"></i>
                    <div class="mt-2">No se encontraron ${mode}</div>
                </div>`;
            listEl.innerHTML = html;
            attachManagerItemHandlers();
            return;
        }

        items.forEach((m, idx) => {
            const isActive = (idx === app.superior.activeIndex) ? ' active' : '';
            const badge = m.es_supervisor
                ? '<span class="badge-supervisor"><i class="mdi mdi-account-tie"></i> Supervisor</span>'
                : '<span class="badge-manager"><i class="mdi mdi-account"></i> Gerente</span>';

            const photoHtml = m.foto_thumbnail
                ? `<img src="../${escapeHtml(m.foto_thumbnail)}" alt="${escapeHtml(m.nombre_completo)}" onerror="this.style.display='none'; this.nextElementSibling && (this.nextElementSibling.style.display='flex');">`
                : '';

            const noPhotoHtml = `<div class="no-photo" ${m.foto_thumbnail ? 'style="display:none;"' : ''}><i class="mdi mdi-account"></i></div>`;

            html += `
                <div class="manager-item${isActive}"
                     role="option"
                     tabindex="0"
                     data-index="${idx}"
                     data-id="${m.id_usuario}">
                    ${photoHtml}${noPhotoHtml}
                    <div class="info">
                        <div class="name">${escapeHtml(m.nombre_completo)}</div>
                        <div class="meta">
                            #${m.num_empleado} &middot; ${escapeHtml(m.nombre_departamento || 'Sin departamento')}
                        </div>
                    </div>
                    ${badge}
                </div>
            `;
        });

        listEl.innerHTML = html;
        attachManagerItemHandlers();
    }

    function renderManagerListError() {
        const listEl = document.getElementById('modalManagerList');
        if (!listEl) return;
        listEl.innerHTML = `
            <div class="manager-list-empty">
                <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 2rem;"></i>
                <div class="mt-2">Error al cargar la lista. Intente de nuevo.</div>
            </div>`;
        const counter = document.getElementById('modalResultsCounter');
        if (counter) counter.textContent = 'Error';
    }

    function attachManagerItemHandlers() {
        const listEl = document.getElementById('modalManagerList');
        if (!listEl) return;

        const items = listEl.querySelectorAll('.manager-item');
        items.forEach(item => {
            item.addEventListener('click', function() {
                if (item.dataset.clear === '1') {
                    clearSuperiorSelection();
                    hideSuperiorModal();
                    return;
                }
                const id = parseInt(item.dataset.id);
                const manager = app.superior.cache.find(x => x.id_usuario === id);
                if (manager) {
                    selectSuperior(manager);
                    hideSuperiorModal();
                }
            });
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    item.click();
                }
            });
        });
    }

    function handleSearchKeydown(e) {
        const items = app.superior.filtered;

        if (e.key === 'Enter') {
            e.preventDefault();
            if (app.superior.activeIndex >= 0 && app.superior.activeIndex < items.length) {
                selectSuperior(items[app.superior.activeIndex]);
                hideSuperiorModal();
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            app.superior.activeIndex = Math.min(app.superior.activeIndex + 1, items.length - 1);
            renderManagerList();
            scrollActiveIntoView();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            app.superior.activeIndex = Math.max(app.superior.activeIndex - 1, 0);
            renderManagerList();
            scrollActiveIntoView();
        } else if (e.key === 'Escape') {
            // Bootstrap ya maneja el cierre del modal con Escape
        }
    }

    function scrollActiveIntoView() {
        const listEl = document.getElementById('modalManagerList');
        if (!listEl) return;
        const active = listEl.querySelector('.manager-item.active');
        if (active && active.scrollIntoView) {
            active.scrollIntoView({ block: 'nearest' });
        }
    }

    function selectSuperior(manager) {
        app.superior.selected = manager;

        const hidden = document.getElementById('id_superior');
        if (hidden) hidden.value = manager.id_usuario;

        renderSuperiorDisplay();
    }

    function clearSuperiorSelection() {
        app.superior.selected = null;
        const hidden = document.getElementById('id_superior');
        if (hidden) hidden.value = '0';
        renderSuperiorDisplay();
    }

    function renderSuperiorDisplay() {
        const infoEl = document.getElementById('superiorInfo');
        const btnClear = document.getElementById('btnClearSuperior');
        if (!infoEl) return;

        const s = app.superior.selected;
        if (!s) {
            infoEl.innerHTML = '<span class="placeholder-text">Sin superior asignado</span>';
            if (btnClear) btnClear.style.display = 'none';
            return;
        }

        const badge = s.es_supervisor
            ? '<span class="badge-supervisor ms-2"><i class="mdi mdi-account-tie"></i> Supervisor</span>'
            : '<span class="badge-manager ms-2"><i class="mdi mdi-account"></i> Gerente</span>';

        const photoHtml = s.foto_thumbnail
            ? `<img src="../${escapeHtml(s.foto_thumbnail)}" alt="${escapeHtml(s.nombre_completo)}" onerror="this.style.display='none'; this.nextElementSibling && (this.nextElementSibling.style.display='flex');">`
            : '';
        const noPhotoHtml = `<div class="no-photo" ${s.foto_thumbnail ? 'style="display:none;"' : ''}><i class="mdi mdi-account"></i></div>`;

        infoEl.innerHTML = `
            ${photoHtml}${noPhotoHtml}
            <div class="text">
                <div class="name">${escapeHtml(s.nombre_completo)} ${badge}</div>
                <div class="meta">#${s.num_empleado} &middot; ${escapeHtml(s.nombre_departamento || 'Sin departamento')}</div>
            </div>
        `;

        if (btnClear) btnClear.style.display = 'inline-block';
    }

    function hideSuperiorModal() {
        const modalEl = document.getElementById('superiorSearchModal');
        if (!modalEl) return;
        // Reutilizar instancia existente para evitar conflictos de Bootstrap
        const instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        instance.hide();
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setupProfilePictureHandlers() {
        const fileInput = document.getElementById('foto_perfil');
        const dropZone = document.getElementById('profilePictureDropZone');
        const removeBtn = document.getElementById('removeProfilePicture');

        if (fileInput) fileInput.addEventListener('change', handleFileSelect);
        if (dropZone) {
            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('dragleave', handleDragLeave);
            dropZone.addEventListener('drop', handleDrop);
            dropZone.addEventListener('click', () => fileInput?.click());
        }
        if (removeBtn) removeBtn.addEventListener('click', clearImagePreview);
    }

    function handleDragOver(e) { e.preventDefault(); e.stopPropagation(); this.classList.add('drag-over'); }
    function handleDragLeave(e) { e.preventDefault(); e.stopPropagation(); this.classList.remove('drag-over'); }
    function handleDrop(e) {
        e.preventDefault(); e.stopPropagation();
        this.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) processImageFile(files[0]);
    }
    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) processImageFile(file);
    }

    function processImageFile(file) {
        if (!IMAGE_CONFIG.ALLOWED_TYPES.includes(file.type)) {
            showAlert('error', 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP');
            return;
        }
        if (file.size > IMAGE_CONFIG.MAX_SIZE) {
            showAlert('error', 'El archivo es demasiado grande. Máximo 5MB');
            return;
        }
        const extension = file.name.split('.').pop().toLowerCase();
        if (!IMAGE_CONFIG.ALLOWED_EXTENSIONS.includes(extension)) {
            showAlert('error', 'Extensión de archivo no permitida');
            return;
        }
        app.selectedImage = file;
        showImagePreview(file);
    }

    function showImagePreview(file) {
        const reader = new FileReader();
        const previewContainer = document.getElementById('imagePreviewContainer');
        const previewImage = document.getElementById('imagePreview');
        const dropZone = document.getElementById('profilePictureDropZone');
        const removeBtn = document.getElementById('removeProfilePicture');
        const fileName = document.getElementById('selectedFileName');

        reader.onload = function(e) {
            if (previewImage) previewImage.src = e.target.result;
            if (previewContainer) previewContainer.style.display = 'block';
            if (dropZone) dropZone.classList.add('has-image');
            if (removeBtn) removeBtn.style.display = 'inline-block';
            if (fileName) fileName.textContent = file.name;
        };
        reader.readAsDataURL(file);
    }

    function clearImagePreview() {
        const fileInput = document.getElementById('foto_perfil');
        const previewContainer = document.getElementById('imagePreviewContainer');
        const previewImage = document.getElementById('imagePreview');
        const dropZone = document.getElementById('profilePictureDropZone');
        const removeBtn = document.getElementById('removeProfilePicture');
        const fileName = document.getElementById('selectedFileName');

        app.selectedImage = null;
        if (fileInput) fileInput.value = '';
        if (previewImage) previewImage.src = '';
        if (previewContainer) previewContainer.style.display = 'none';
        if (dropZone) dropZone.classList.remove('has-image');
        if (removeBtn) removeBtn.style.display = 'none';
        if (fileName) fileName.textContent = '';
    }

    function setupRealtimeValidation() {
        const usuarioInput = document.getElementById('usuario');
        if (usuarioInput) {
            usuarioInput.addEventListener('input', function(e) {
                const value = e.target.value;
                const sanitized = value.replace(/[^a-zA-Z0-9._-]/g, '');
                if (value !== sanitized) e.target.value = sanitized;
            });
        }

        const numEmpleadoInput = document.getElementById('num_empleado');
        if (numEmpleadoInput) {
            numEmpleadoInput.addEventListener('input', function(e) {
                if (e.target.value < 0) e.target.value = 0;
            });
        }
    }

    function loadDepartamentos() {
        fetch('../php/get_departments.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.departamentos) {
                    app.departamentos = data.departamentos;
                    populateDepartamentosSelect(data.departamentos);
                } else {
                    showAlert('error', 'No se pudieron cargar los departamentos');
                }
            })
            .catch(error => {
                console.error('Error en fetch de departamentos:', error);
                showAlert('error', 'Error de conexión al cargar departamentos');
            });
    }

    function loadRoles() {
        fetch('../php/get_roles.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.roles) {
                    app.roles = data.roles;
                    populateRolesSelect(data.roles);
                } else {
                    showAlert('error', 'No se pudieron cargar los roles');
                }
            })
            .catch(error => {
                console.error('Error en fetch de roles:', error);
                showAlert('error', 'Error de conexión al cargar roles');
            });
    }

    function populateDepartamentosSelect(departamentos) {
        const select = document.getElementById('id_departamento');
        if (!select) return;
        select.innerHTML = '<option value="0">Seleccione un departamento</option>';
        departamentos.forEach(dept => {
            const option = document.createElement('option');
            option.value = dept.id_departamento;
            option.textContent = dept.nombre;
            select.appendChild(option);
        });
    }

    function populateRolesSelect(roles) {
        const select = document.getElementById('id_rol');
        if (!select) return;
        select.innerHTML = '<option value="0">Seleccione un rol</option>';

        roles.forEach(rol => {
            const option = document.createElement('option');
            option.value = rol.id_rol;
            option.textContent = rol.nombre;

            if (rol.id_rol === SUPERVISOR_SENTINEL_ID || rol.es_supervisor === true) {
                option.textContent = '⭐ ' + rol.nombre;
                option.style.fontWeight = 'bold';
            }

            select.appendChild(option);
        });
    }

    function handlePasswordToggle() {
        const passwordInput = document.getElementById('acceso');
        const toggleIcon = document.getElementById('togglePassword');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('mdi-eye-off');
            toggleIcon.classList.add('mdi-eye');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('mdi-eye');
            toggleIcon.classList.add('mdi-eye-off');
        }
    }

    function handleFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const submitButton = document.getElementById('btnSubmit');

        if (!validateForm(form)) return;

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Creando...';

        const formData = new FormData(form);

        // Mapear supervisor sintetico
        const selectedRolId = parseInt(form.id_rol.value);
        if (selectedRolId === SUPERVISOR_SENTINEL_ID) {
            formData.set('id_rol', '2');
            formData.set('es_supervisor', '1');
        } else {
            formData.set('es_supervisor', '0');
        }

        // id_superior ya esta en el hidden input, pero por seguridad
        formData.set('id_superior', String(app.superior.selected ? app.superior.selected.id_usuario : 0));

        if (app.selectedImage) {
            formData.set('foto_perfil', app.selectedImage);
        }

        fetch('../php/create_user.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = data.message;
                    if (data.foto_warning) message += ' (Nota: ' + data.foto_warning + ')';
                    showAlert('success', message);
                    form.reset();

                    // Limpiar seleccion de superior y toggle
                    clearSuperiorSelection();
                    const modalToggle = document.getElementById('modalToggleEsSupervisor');
                    if (modalToggle) modalToggle.checked = false;
                    app.superior.esSupervisorMode = false;
                    loadManagersIntoCache(false);

                    clearImagePreview();
                    scrollToAlert();
                    setTimeout(() => {
                        window.location.href = '../gestionDeEmpleados/';
                    }, 2000);
                } else {
                    showAlert('error', data.message);
                    scrollToAlert();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error de conexión. Por favor, intente nuevamente.');
                scrollToAlert();
            })
            .finally(() => {
                submitButton.disabled = false;
                const currentRol = parseInt(document.getElementById('id_rol').value);
                updateSubmitButtonLabel(currentRol);
            });
    }

    function validateForm(form) {
        const nombre = form.nombre.value.trim();
        const apellido = form.apellido.value.trim();
        const usuario = form.usuario.value.trim();
        const acceso = form.acceso.value;
        const num_empleado = parseInt(form.num_empleado.value);
        const id_departamento = parseInt(form.id_departamento.value);
        const id_rol = parseInt(form.id_rol.value);
        const e_mail = form.e_mail.value.trim();

        if (nombre.length < 2 || nombre.length > 100) {
            showAlert('error', 'El nombre debe tener entre 2 y 100 caracteres');
            return false;
        }
        if (apellido.length < 2 || apellido.length > 100) {
            showAlert('error', 'El apellido debe tener entre 2 y 100 caracteres');
            return false;
        }
        if (usuario.length < 4 || usuario.length > 100) {
            showAlert('error', 'El usuario debe tener entre 4 y 100 caracteres');
            return false;
        }
        if (!/^[a-zA-Z0-9._-]+$/.test(usuario)) {
            showAlert('error', 'El usuario solo puede contener letras, números, punto, guión y guión bajo');
            return false;
        }
        if (acceso.length < 6) {
            showAlert('error', 'La contraseña debe tener al menos 6 caracteres');
            return false;
        }
        if (num_empleado <= 0 || isNaN(num_empleado)) {
            showAlert('error', 'Debe ingresar un número de empleado válido');
            return false;
        }
        if (id_departamento <= 0 || isNaN(id_departamento)) {
            showAlert('error', 'Debe seleccionar un departamento');
            return false;
        }
        if (isNaN(id_rol) || (id_rol <= 0 && id_rol !== SUPERVISOR_SENTINEL_ID)) {
            showAlert('error', 'Debe seleccionar un rol');
            return false;
        }
        if (e_mail && e_mail.length > 0) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e_mail)) {
                showAlert('error', 'El formato del correo electrónico no es válido');
                return false;
            }
        }
        return true;
    }

    function showAlert(type, message) {
        const alertDiv = document.getElementById('alertMessage');
        if (!alertDiv) return;

        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const iconClass = type === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle';

        alertDiv.className = `alert ${alertClass}`;
        alertDiv.innerHTML = `
            <i class="mdi ${iconClass} me-2"></i>
            <strong>${type === 'success' ? 'Éxito' : 'Error'}:</strong> ${message}
        `;
        alertDiv.style.display = 'block';

        if (type === 'success') {
            setTimeout(hideAlert, 5000);
        }
    }

    function hideAlert() {
        const alertDiv = document.getElementById('alertMessage');
        if (alertDiv) alertDiv.style.display = 'none';
    }

    function scrollToAlert() {
        const alertDiv = document.getElementById('alertMessage');
        if (alertDiv && alertDiv.style.display !== 'none') {
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

})();
