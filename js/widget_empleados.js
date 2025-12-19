/* widget_empleados.js - Widget para mostrar empleados del departamento en el dashboard de gerente */
/* ACTUALIZADO: Soporte para fotos de perfil */
(function() {
    const employeeColors = [{
        bg: '#31ab6a',
        light: '#31ab6a'
    }];
    
    // Configuración de imágenes
    const IMAGE_CONFIG = {
        DEFAULT_AVATAR: '../images/default-avatar.png',
        UPLOADS_BASE: '../uploads/profile_pictures/',
        THUMBNAILS_BASE: '../uploads/profile_pictures/thumbnails/'
    };
    
    /**
     * Obtiene la URL de la foto de perfil del empleado
     * @param {object} emp - Objeto empleado
     * @returns {string} URL de la imagen
     */
    function getEmployeePhotoUrl(emp) {
        // Si tiene foto_thumbnail (preferido para widget pequeño)
        if (emp.foto_thumbnail) {
            return '../' + emp.foto_thumbnail;
        }
        
        // Si tiene foto_url
        if (emp.foto_url) {
            return '../' + emp.foto_url;
        }
        
        // Si tiene foto_perfil (construir URL manualmente)
        if (emp.foto_perfil) {
            return IMAGE_CONFIG.THUMBNAILS_BASE + 'thumb_' + emp.foto_perfil;
        }
        
        // Retornar avatar por defecto
        return IMAGE_CONFIG.DEFAULT_AVATAR;
    }
    
    /**
     * Maneja errores de carga de imagen
     * @param {HTMLImageElement} img - Elemento de imagen
     */
    function handleImageError(img) {
        // Evitar loop infinito
        if (img.dataset.fallbackApplied === 'true') {
            return;
        }
        img.dataset.fallbackApplied = 'true';
        img.src = IMAGE_CONFIG.DEFAULT_AVATAR;
    }
    
    // CARGAR EMPLEADOS DEL DEPARTAMENTO DESDE LA API 
    function loadEmployees() {
        const container = document.getElementById('employeesWidgetContainer');
        if (!container) return;
        
        fetch('../php/manager_get_users.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.usuarios) {
                    renderEmployees(data.usuarios);
                } else {
                    console.error('Error loading employees:', data.message);
                    renderEmptyState();
                }
            })
            .catch(error => {
                console.error('Error fetching employees:', error);
                renderEmptyState();
            });
    }
    
    // Renderizar los items de empleados como si fueran banderas 
    function renderEmployees(employees) {
        const container = document.getElementById('employeesWidgetContainer');
        if (!container) return;
        
        if (employees.length === 0) {
            renderEmptyState();
            return;
        }
        
        // Filtrar al usuario actual de la lista (el gerente no se ve a sí mismo) 
        const currentUserId = parseInt(document.querySelector('body').dataset.userId) || 0;
        const filteredEmployees = employees.filter(emp => emp.id_usuario !== currentUserId);
        
        // Limitar a los primeros 6 empleados 
        const displayEmps = filteredEmployees.slice(0, 6);
        
        let html = '';
        displayEmps.forEach((emp, index) => {
            const colorScheme = employeeColors[index % employeeColors.length];
            const initials = getInitials(emp.nombre, emp.apellido);
            const fullName = `${emp.nombre} ${emp.apellido}`;
            const roleText = getRoleText(emp.id_rol);
            
            // Obtener URL de la foto de perfil
            const photoUrl = getEmployeePhotoUrl(emp);
            
            html += ` 
                <div class="emp-flag"  
                     data-emp-id="${emp.id_usuario}"  
                     title="${fullName} - ${roleText}${emp.e_mail ? '\n' + emp.e_mail : ''}" 
                     style="--emp-color: ${colorScheme.bg}; --emp-light: ${colorScheme.light};"> 
                    <div class="emp-flag-stripe"></div> 
                    <div class="emp-flag-content"> 
                        <div class="preview-thumbnail">
                            <img src="${photoUrl}" 
                                 alt="${fullName}" 
                                 class="img-sm profile-pic" 
                                 style="border-radius: 35px; width: 35px; height: 35px; object-fit: cover;"
                                 onerror="this.dataset.fallbackApplied !== 'true' && (this.dataset.fallbackApplied = 'true', this.src = '${IMAGE_CONFIG.DEFAULT_AVATAR}')">
                        </div>
                        <span class="emp-flag-initials">${initials}</span> 
                        <span class="emp-flag-name">${truncateName(fullName, 12)}</span> 
                    </div> 
                </div> 
            `;
        });
        
        // Agregar indicador de "más" si hay más empleados 
        if (filteredEmployees.length > 6) {
            html += ` 
                <div class="emp-flag emp-flag-more"  
                     title="Ver todos los empleados (${filteredEmployees.length} total)" 
                     style="--emp-color: #6c757d; --emp-light: #868e96;"> 
                    <div class="emp-flag-stripe"></div> 
                    <div class="emp-flag-content"> 
                        <i class="mdi mdi-dots-horizontal"></i> 
                        <span class="emp-flag-initials">+${filteredEmployees.length - 6}</span> 
                        <span class="emp-flag-name">Más</span> 
                    </div> 
                </div> 
            `;
        }
        
        container.innerHTML = html;
        attachClickHandlers();
    }
    
    // Renderizar estado vacío 
    function renderEmptyState() {
        const container = document.getElementById('employeesWidgetContainer');
        if (!container) return;
        
        container.innerHTML = ` 
            <div class="emp-flag emp-flag-empty"  
                 style="--emp-color: #adb5bd; --emp-light: #ced4da;"> 
                <div class="emp-flag-stripe"></div> 
                <div class="emp-flag-content"> 
                    <i class="mdi mdi-account-off-outline"></i> 
                    <span class="emp-flag-initials">--</span> 
                    <span class="emp-flag-name">Sin empleados</span> 
                </div> 
            </div> 
        `;
    }
    
    // Obtener iniciales del empleado 
    function getInitials(nombre, apellido) {
        if (!nombre && !apellido) return '??';
        const firstName = (nombre || '').trim();
        const lastName = (apellido || '').trim();
        
        if (firstName && lastName) {
            return (firstName[0] + lastName[0]).toUpperCase();
        } else if (firstName) {
            return firstName.substring(0, 2).toUpperCase();
        } else if (lastName) {
            return lastName.substring(0, 2).toUpperCase();
        }
        return '??';
    }
    
    // Truncar nombre para mostrarlo 
    function truncateName(name, maxLength) {
        if (!name) return '';
        if (name.length <= maxLength) return name;
        return name.substring(0, maxLength - 1) + '…';
    }
    
    // Obtener texto del rol 
    function getRoleText(idRol) {
        const roles = {
            1: 'Administrador',
            2: 'Gerente',
            3: 'Usuario',
            4: 'Practicante'
        };
        return roles[idRol] || 'Usuario';
    }
    
    // Agregar click handlers a los items de empleados 
    function attachClickHandlers() {
        const items = document.querySelectorAll('.emp-flag');
        items.forEach(item => {
            item.addEventListener('click', function() {
                const empId = this.dataset.empId;
                // Redirigir a la página de gestión de empleados 
                window.location.href = '../gestionDeEmpleados-Gerente/';
            });
        });
    }
    
    // Inicializar al cargar el documento 
    document.addEventListener('DOMContentLoaded', function() {
        loadEmployees();
        // Actualizar cada 15 minutos (900000 ms) 
        setInterval(loadEmployees, 900000);
    });
    
    // Hacer función global para poder refrescarla manualmente 
    window.refreshEmployeesWidget = loadEmployees;
})();