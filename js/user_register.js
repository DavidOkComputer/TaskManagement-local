// user_register.js para registrar usuarios CON SOPORTE DE FOTO DE PERFIL
(function() {
    'use strict';

    // Estado de la aplicación
    const app = {
        usuarios: [],
        departamentos: [],
        roles: [],
        selectedImage: null
    };

    // Configuración de imagen
    const IMAGE_CONFIG = {
        MAX_SIZE: 5 * 1024 * 1024, // 5MB
        ALLOWED_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        ALLOWED_EXTENSIONS: ['jpg', 'jpeg', 'png', 'gif', 'webp']
    };

    document.addEventListener('DOMContentLoaded', function() {
        initializeApp();
    });

    function initializeApp() {
        loadDepartamentos();
        loadRoles();
        clearSuperioresSelect();
        setupEventHandlers();
        setupProfilePictureHandlers();
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
                    clearSuperioresSelect();
                }, 100);
            });
        }

        const departamentoSelect = document.getElementById('id_departamento');
        if (departamentoSelect) {
            departamentoSelect.addEventListener('change', handleDepartamentoChange);
        }

        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', handlePasswordToggle);
        }

        setupRealtimeValidation();
    }

    // ========== FUNCIONES DE FOTO DE PERFIL ==========
    
    function setupProfilePictureHandlers() {
        const fileInput = document.getElementById('foto_perfil');
        const dropZone = document.getElementById('profilePictureDropZone');
        const removeBtn = document.getElementById('removeProfilePicture');

        if (fileInput) {
            fileInput.addEventListener('change', handleFileSelect);
        }

        if (dropZone) {
            // Eventos de drag and drop
            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('dragleave', handleDragLeave);
            dropZone.addEventListener('drop', handleDrop);
            dropZone.addEventListener('click', () => fileInput?.click());
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', clearImagePreview);
        }
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            processImageFile(files[0]);
        }
    }

    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) {
            processImageFile(file);
        }
    }

    function processImageFile(file) {
        // Validar tipo
        if (!IMAGE_CONFIG.ALLOWED_TYPES.includes(file.type)) {
            showAlert('error', 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP');
            return;
        }

        // Validar tamaño
        if (file.size > IMAGE_CONFIG.MAX_SIZE) {
            showAlert('error', 'El archivo es demasiado grande. Máximo 5MB');
            return;
        }

        // Validar extensión
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
            if (previewImage) {
                previewImage.src = e.target.result;
            }
            if (previewContainer) {
                previewContainer.style.display = 'block';
            }
            if (dropZone) {
                dropZone.classList.add('has-image');
            }
            if (removeBtn) {
                removeBtn.style.display = 'inline-block';
            }
            if (fileName) {
                fileName.textContent = file.name;
            }
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

        if (fileInput) {
            fileInput.value = '';
        }
        if (previewImage) {
            previewImage.src = '';
        }
        if (previewContainer) {
            previewContainer.style.display = 'none';
        }
        if (dropZone) {
            dropZone.classList.remove('has-image');
        }
        if (removeBtn) {
            removeBtn.style.display = 'none';
        }
        if (fileName) {
            fileName.textContent = '';
        }
    }

    // ========== FUNCIONES EXISTENTES (sin cambios) ==========

    function handleDepartamentoChange(e) {
        const departamentoId = parseInt(e.target.value);
        clearSuperioresSelect();
        if (departamentoId > 0) {
            loadUsuariosByDepartamento(departamentoId);
        }
    }

    function clearSuperioresSelect() {
        const select = document.getElementById('id_superior');
        if (!select) return;
        select.innerHTML = '<option value="0">Sin superior asignado</option>';
        select.value = "0";
    }

    function setupRealtimeValidation() {
        const usuarioInput = document.getElementById('usuario');
        if (usuarioInput) {
            usuarioInput.addEventListener('input', function(e) {
                const value = e.target.value;
                const sanitized = value.replace(/[^a-zA-Z0-9._-]/g, '');
                if (value !== sanitized) {
                    e.target.value = sanitized;
                }
            });
        }

        const numEmpleadoInput = document.getElementById('num_empleado');
        if (numEmpleadoInput) {
            numEmpleadoInput.addEventListener('input', function(e) {
                if (e.target.value < 0) {
                    e.target.value = 0;
                }
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
                    console.error('Error al cargar departamentos:', data.message);
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
                    console.error('Error al cargar roles:', data.message);
                    showAlert('error', 'No se pudieron cargar los roles');
                }
            })
            .catch(error => {
                console.error('Error en fetch de roles:', error);
                showAlert('error', 'Error de conexión al cargar roles');
            });
    }

    function loadUsuariosByDepartamento(departamentoId) {
        fetch(`../php/get_users.php?id_rol=2&id_departamento=${departamentoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.usuarios) {
                    app.usuarios = data.usuarios;
                    populateSuperioresSelect(data.usuarios);
                } else {
                    console.error('Error al cargar usuarios:', data.message);
                    clearSuperioresSelect();
                }
            })
            .catch(error => {
                console.error('Error en fetch de usuarios:', error);
                clearSuperioresSelect();
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
            select.appendChild(option);
        });
    }

    function populateSuperioresSelect(usuarios) {
        const select = document.getElementById('id_superior');
        if (!select) return;
        select.innerHTML = '<option value="0">Sin superior asignado</option>';
        usuarios.forEach(usuario => {
            const option = document.createElement('option');
            option.value = usuario.id_usuario;
            option.textContent = usuario.nombre_completo + ' (ID: ' + usuario.num_empleado + ')';
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

        if (!validateForm(form)) {
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Creando...';

        // Usar FormData para enviar archivos
        const formData = new FormData(form);

        // Agregar la imagen si existe
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
                if (data.foto_warning) {
                    message += ' (Nota: ' + data.foto_warning + ')';
                }
                showAlert('success', message);
                form.reset();
                clearSuperioresSelect();
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
            submitButton.innerHTML = '<i class="mdi mdi-account-plus"></i> Crear Usuario';
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

        const usuarioRegex = /^[a-zA-Z0-9._-]+$/;
        if (!usuarioRegex.test(usuario)) {
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

        if (id_rol <= 0 || isNaN(id_rol)) {
            showAlert('error', 'Debe seleccionar un rol');
            return false;
        }

        if (e_mail && e_mail.length > 0) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(e_mail)) {
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
            setTimeout(() => {
                hideAlert();
            }, 5000);
        }
    }

    function hideAlert() {
        const alertDiv = document.getElementById('alertMessage');
        if (alertDiv) {
            alertDiv.style.display = 'none';
        }
    }

    function scrollToAlert() {
        const alertDiv = document.getElementById('alertMessage');
        if (alertDiv && alertDiv.style.display !== 'none') {
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

})();