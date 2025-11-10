// registro-usuario.js

(function() {
    'use strict';

    // Estado de la aplicación
    const app = {
        usuarios: [],
        departamentos: [],
        roles: []
    };

    /**
     * Inicializar la aplicación cuando el DOM esté listo
     */
    document.addEventListener('DOMContentLoaded', function() {
        initializeApp();
    });

    /**
     * Inicializar todos los componentes de la aplicación
     */
    function initializeApp() {
        console.log('Inicializando aplicación de registro de usuarios...');
        
        // Cargar datos iniciales
        loadDepartamentos();
        loadRoles();
        loadUsuarios();
        
        // Configurar manejadores de eventos
        setupEventHandlers();
        
        console.log('Aplicación inicializada correctamente');
    }

    /**
     * Configurar todos los manejadores de eventos
     */
    function setupEventHandlers() {
        // Manejador del formulario
        const form = document.getElementById('formCrearUsuario');
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }

        // Manejador del botón de reset
        const resetButton = form.querySelector('button[type="reset"]');
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                hideAlert();
                // Recargar los dropdowns después de un breve delay
                setTimeout(function() {
                    loadDepartamentos();
                    loadRoles();
                    loadUsuarios();
                }, 100);
            });
        }

        // Toggle de contraseña
        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', handlePasswordToggle);
        }

        // Validación en tiempo real
        setupRealtimeValidation();
    }

    /**
     * Configurar validación en tiempo real de los campos
     */
    function setupRealtimeValidation() {
        const usuarioInput = document.getElementById('usuario');
        if (usuarioInput) {
            usuarioInput.addEventListener('input', function(e) {
                const value = e.target.value;
                // Solo permitir letras, números, punto, guión y guión bajo
                const sanitized = value.replace(/[^a-zA-Z0-9._-]/g, '');
                if (value !== sanitized) {
                    e.target.value = sanitized;
                }
            });
        }

        const numEmpleadoInput = document.getElementById('num_empleado');
        if (numEmpleadoInput) {
            numEmpleadoInput.addEventListener('input', function(e) {
                // Asegurar que solo sean números positivos
                if (e.target.value < 0) {
                    e.target.value = 0;
                }
            });
        }
    }

    /**
     * Cargar departamentos desde el servidor
     */
    function loadDepartamentos() {
        fetch('../php/get_departments.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.departamentos) {
                    app.departamentos = data.departamentos;
                    populateDepartamentosSelect(data.departamentos);
                    console.log(`${data.departamentos.length} departamentos cargados`);
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

    /**
     * Cargar roles desde el servidor
     */
    function loadRoles() {
        fetch('../php/get_roles.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.roles) {
                    app.roles = data.roles;
                    populateRolesSelect(data.roles);
                    console.log(`${data.roles.length} roles cargados`);
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

    /**
     * Cargar usuarios desde el servidor (para el dropdown de superiores)
     * Solo carga usuarios con id_rol = 2
     */
    function loadUsuarios() {
        // Filtrar solo usuarios con id_rol = 2 para superiores
        //../php/get_users.php?id_rol=2'
        fetch('../php/get_users.php?id_rol=2')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.usuarios) {
                    app.usuarios = data.usuarios;
                    populateSuperioresSelect(data.usuarios);
                    console.log(`${data.usuarios.length} usuarios con rol 2 cargados para superiores`);
                } else {
                    console.error('Error al cargar usuarios:', data.message);
                }
            })
            .catch(error => {
                console.error('Error en fetch de usuarios:', error);
            });
    }

    /**
     * Poblar el select de departamentos
     */
    function populateDepartamentosSelect(departamentos) {
        const select = document.getElementById('id_departamento');
        if (!select) return;

        // Limpiar opciones existentes excepto la primera
        select.innerHTML = '<option value="0">Seleccione un departamento</option>';

        // Agregar opciones
        departamentos.forEach(dept => {
            const option = document.createElement('option');
            option.value = dept.id_departamento;
            option.textContent = dept.nombre;
            select.appendChild(option);
        });
    }

    /**
     * Poblar el select de roles
     */
    function populateRolesSelect(roles) {
        const select = document.getElementById('id_rol');
        if (!select) return;

        // Limpiar opciones existentes excepto la primera
        select.innerHTML = '<option value="0">Seleccione un rol</option>';

        // Agregar opciones
        roles.forEach(rol => {
            const option = document.createElement('option');
            option.value = rol.id_rol;
            option.textContent = rol.nombre;
            select.appendChild(option);
        });
    }

    /**
     * Poblar el select de superiores
     */
    function populateSuperioresSelect(usuarios) {
        const select = document.getElementById('id_superior');
        if (!select) return;

        // Limpiar opciones existentes excepto la primera
        select.innerHTML = '<option value="0">Sin superior asignado</option>';

        // Agregar opciones
        usuarios.forEach(usuario => {
            const option = document.createElement('option');
            option.value = usuario.id_usuario;
            option.textContent = usuario.nombre_completo + ' (ID: ' + usuario.num_empleado + ')';
            select.appendChild(option);
        });
    }

    /**
     * Manejar el toggle de visibilidad de la contraseña
     */
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

    /**
     * Manejar el envío del formulario
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitButton = document.getElementById('btnSubmit');
        
        // Validar formulario antes de enviar
        if (!validateForm(form)) {
            return;
        }

        // Deshabilitar el botón de envío
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Creando...';

        // Recopilar datos del formulario
        const formData = new FormData(form);

        // Enviar datos al servidor
        fetch('../php/create_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                form.reset();
                
                // Recargar la lista de usuarios (solo rol 2) para el dropdown de superiores
                loadUsuarios();
                
                // Scroll al mensaje
                scrollToAlert();
                
                // Opcional: redirigir después de 2 segundos
                setTimeout(() => {
                    // window.location.href = '../gestionDeEmpleados/';
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
            // Rehabilitar el botón de envío
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="mdi mdi-account-plus"></i> Crear Usuario';
        });
    }

    /**
     * Validar el formulario antes de enviar
     */
    function validateForm(form) {
        const nombre = form.nombre.value.trim();
        const apellido = form.apellido.value.trim();
        const usuario = form.usuario.value.trim();
        const acceso = form.acceso.value;
        const num_empleado = parseInt(form.num_empleado.value);
        const id_departamento = parseInt(form.id_departamento.value);
        const id_rol = parseInt(form.id_rol.value);
        const e_mail = form.e_mail.value.trim();

        // Validar nombre
        if (nombre.length < 2 || nombre.length > 100) {
            showAlert('error', 'El nombre debe tener entre 2 y 100 caracteres');
            return false;
        }

        // Validar apellido
        if (apellido.length < 2 || apellido.length > 100) {
            showAlert('error', 'El apellido debe tener entre 2 y 100 caracteres');
            return false;
        }

        // Validar usuario
        if (usuario.length < 4 || usuario.length > 100) {
            showAlert('error', 'El usuario debe tener entre 4 y 100 caracteres');
            return false;
        }

        // Validar formato de usuario
        const usuarioRegex = /^[a-zA-Z0-9._-]+$/;
        if (!usuarioRegex.test(usuario)) {
            showAlert('error', 'El usuario solo puede contener letras, números, punto, guión y guión bajo');
            return false;
        }

        // Validar contraseña
        if (acceso.length < 6) {
            showAlert('error', 'La contraseña debe tener al menos 6 caracteres');
            return false;
        }

        // Validar número de empleado
        if (num_empleado <= 0 || isNaN(num_empleado)) {
            showAlert('error', 'Debe ingresar un número de empleado válido');
            return false;
        }

        // Validar departamento
        if (id_departamento <= 0 || isNaN(id_departamento)) {
            showAlert('error', 'Debe seleccionar un departamento');
            return false;
        }

        // Validar rol
        if (id_rol <= 0 || isNaN(id_rol)) {
            showAlert('error', 'Debe seleccionar un rol');
            return false;
        }

        // Validar email si se proporciona
        if (e_mail && e_mail.length > 0) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(e_mail)) {
                showAlert('error', 'El formato del correo electrónico no es válido');
                return false;
            }
        }

        return true;
    }

    /**
     * Mostrar mensaje de alerta
     */
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

        // Auto-ocultar después de 5 segundos si es éxito
        if (type === 'success') {
            setTimeout(() => {
                hideAlert();
            }, 5000);
        }
    }

    /**
     * Ocultar mensaje de alerta
     */
    function hideAlert() {
        const alertDiv = document.getElementById('alertMessage');
        if (alertDiv) {
            alertDiv.style.display = 'none';
        }
    }

    /**
     * Hacer scroll hasta el mensaje de alerta
     */
    function scrollToAlert() {
        const alertDiv = document.getElementById('alertMessage');
        if (alertDiv && alertDiv.style.display !== 'none') {
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

})();