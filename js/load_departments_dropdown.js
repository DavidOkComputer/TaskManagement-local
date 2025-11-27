/*load_departments_dropdown.js carga dinamica de los departamentos en el dropdown 
 * Updated to support role-based restrictions:
 * - Admins (id_rol=1): Full dropdown functionality
 * - Managers (id_rol=2): Dropdown hidden, department switching blocked
 * - Users (id_rol=3): Dropdown hidden, department switching blocked
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando dropdown de departamentos...');
    
    // Check if user can view all departments before loading dropdown
    if (!canUserViewAllDepartments()) {
        console.log('Usuario con rol restringido - dropdown de departamentos deshabilitado');
        return; // Don't load dropdown for managers/users
    }
    
    loadDepartmentsIntoDropdown();
});

/**
 * Check if current user can view all departments
 * Returns true for admins, false for managers/users
 */
function canUserViewAllDepartments() {
    // Check PHP-passed configuration first
    if (window.dashboardUserConfig) {
        console.log('Verificando permisos de usuario:', window.dashboardUserConfig);
        return window.dashboardUserConfig.canViewAllDepartments === true;
    }
    
    // Fallback: check dashboardChartsInstance if available
    if (typeof dashboardChartsInstance !== 'undefined' && dashboardChartsInstance.userConfig) {
        return dashboardChartsInstance.userConfig.canViewAllDepartments === true;
    }
    
    // If no config available, assume restricted (safer default)
    console.warn('No se encontró configuración de usuario - asumiendo acceso restringido');
    return false;
}

/**
 * Check if user is role-locked (cannot switch departments)
 */
function isUserRoleLocked() {
    // Check PHP-passed configuration
    if (window.dashboardUserConfig) {
        return !window.dashboardUserConfig.canViewAllDepartments;
    }
    
    // Fallback: check dashboardChartsInstance
    if (typeof dashboardChartsInstance !== 'undefined') {
        return dashboardChartsInstance.isRoleLocked === true;
    }
    
    return true; // Default to locked for safety
}

function loadDepartmentsIntoDropdown() {
    // Safety check - don't load if user is role-locked
    if (isUserRoleLocked()) {
        console.log('Carga de departamentos bloqueada - usuario con rol restringido');
        return;
    }
    
    console.log('Cargando departamentos para dropdown...');
    
    fetch('../php/get_departments.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Departamentos cargados:', data);
            
            if (data.success && data.departamentos && Array.isArray(data.departamentos)) {
                populateDepartmentDropdown(data.departamentos);
            } else {
                console.error('Error en respuesta de departamentos:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading departments for dropdown:', error.message);
        });
}

function populateDepartmentDropdown(departamentos) {
    // Safety check
    if (isUserRoleLocked()) {
        console.log('Población de dropdown bloqueada - usuario con rol restringido');
        return;
    }
    
    const dropdownMenu = document.querySelector('[aria-labelledby="messageDropdown"]');
    
    if (!dropdownMenu) {
        console.warn('Dropdown menu no encontrado con aria-labelledby="messageDropdown"');
        return;
    }
    
    // Encontrar el divider
    const divider = dropdownMenu.querySelector('.dropdown-divider');
    
    if (!divider) {
        console.warn('Divider no encontrado en dropdown');
        return;
    }
    
    // Limpiar items existentes después del divider
    let nextElement = divider.nextElementSibling;
    while (nextElement) {
        const toRemove = nextElement;
        nextElement = nextElement.nextElementSibling;
        toRemove.remove();
    }
    
    // Agregar departamentos
    departamentos.forEach(dept => {
        const link = document.createElement('a');
        link.className = 'dropdown-item preview-item department-item';
        link.href = '#';
        link.setAttribute('data-department-id', dept.id_departamento);
        link.setAttribute('data-department-name', dept.nombre);
        
        link.innerHTML = `
            <div class="preview-item-content flex-grow py-2">
                <p class="preview-subject ellipsis font-weight-medium text-dark">${escapeHtml(dept.nombre)}</p>
                <p class="fw-light small-text mb-0">${escapeHtml(dept.descripcion || 'Sin descripción')}</p>
            </div>
        `;
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Block department selection for role-locked users
            if (isUserRoleLocked()) {
                console.log('═══════════════════════════════════════════════════════');
                console.log('SELECCIÓN DE DEPARTAMENTO BLOQUEADA');
                console.log('Los gerentes solo pueden ver su propio departamento');
                console.log('═══════════════════════════════════════════════════════');
                return;
            }
            
            const deptId = parseInt(this.getAttribute('data-department-id'));
            const deptName = this.getAttribute('data-department-name');
            
            console.log('═══════════════════════════════════════════════════════');
            console.log('DEPARTAMENTO SELECCIONADO:', deptName, '(ID:', deptId + ')');
            console.log('═══════════════════════════════════════════════════════');
            
            console.log('Step 1: Updating main dashboard charts (bar, doughnut, line, area, scatter)...');
            if (typeof selectDepartmentFromDropdown === 'function') {
                console.log('  Calling selectDepartmentFromDropdown()');
                selectDepartmentFromDropdown(deptId, deptName);
            } else {
                console.error('  ERROR: selectDepartmentFromDropdown() NOT FOUND!');
                console.error('  Make sure dashboard_charts_core.js is loaded BEFORE this file');
            }
            
            console.log('Step 2: Updating workload chart...');
            if (typeof selectDepartmentWorkload === 'function') {
                console.log('   Calling selectDepartmentWorkload()');
                selectDepartmentWorkload(deptId, deptName);
            } else {
                console.warn('  selectDepartmentWorkload() NOT FOUND');
                console.warn('  Make sure dashboard_charts_workload.js is loaded');
            }
            
            // Cerrar el dropdown de forma segura
            closeDropdownSafely();
        });
        
        dropdownMenu.appendChild(link);
    });
    
    // Agregar divisor antes de la opción de "Ver todas"
    const dividerEnd = document.createElement('div');
    dividerEnd.className = 'dropdown-divider';
    dropdownMenu.appendChild(dividerEnd);
    
    // Agregar opción para ver todas
    const allLink = document.createElement('a');
    allLink.className = 'dropdown-item text-center';
    allLink.href = '#';
    allLink.innerHTML = '<small class="text-muted">Ver todos los departamentos</small>';
    
    allLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Block "view all" for role-locked users
        if (isUserRoleLocked()) {
            console.log('═══════════════════════════════════════════════════════');
            console.log('VISTA DE COMPARACIÓN BLOQUEADA');
            console.log('Los gerentes solo pueden ver su propio departamento');
            console.log('═══════════════════════════════════════════════════════');
            return;
        }
        
        console.log('═══════════════════════════════════════════════════════');
        console.log('VISTA DE COMPARACIÓN SELECCIONADA');
        console.log('Cargando todos los departamentos...');
        console.log('═══════════════════════════════════════════════════════');
        
        console.log('Step 1: Clearing main dashboard charts...');
        if (typeof clearDepartmentSelection === 'function') {
            console.log('  Calling clearDepartmentSelection()');
            clearDepartmentSelection();
        } else {
            console.error('  ERROR: clearDepartmentSelection() NOT FOUND!');
            console.error('  Make sure dashboard_charts_core.js is loaded BEFORE this file');
        }
        
        console.log('Step 2: Clearing workload chart...');
        if (typeof resetWorkloadView === 'function') {
            console.log('  Calling resetWorkloadView()');
            resetWorkloadView();
        } else {
            console.warn('  resetWorkloadView() NOT FOUND');
            console.warn('  Make sure dashboard_charts_workload.js is loaded');
        }
        
        // Cerrar el dropdown de forma segura
        closeDropdownSafely();
    });
    
    dropdownMenu.appendChild(allLink);
    
    console.log(`Dropdown poblado con ${departamentos.length} departamentos\n`);
}

/**
 * Safely close the dropdown
 */
function closeDropdownSafely() {
    try {
        const dropdown = document.getElementById('messageDropdown');
        if (dropdown && dropdown.classList.contains('show')) {
            const bsDropdown = bootstrap.Dropdown.getInstance(dropdown);
            if (bsDropdown) {
                bsDropdown.hide();
            } else {
                dropdown.classList.remove('show'); // Eliminar la clase de manera manual
                const dropdownMenu = dropdown.nextElementSibling;
                if (dropdownMenu) {
                    dropdownMenu.classList.remove('show');
                }
            }
        }
    } catch (err) {
        console.error('Error closing dropdown:', err);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Public function to refresh departments list
window.refreshDepartmentsList = function() {
    // Check permissions before refreshing
    if (isUserRoleLocked()) {
        console.log('Refresh bloqueado - usuario con rol restringido');
        return;
    }
    
    console.log('Refrescando lista de departamentos...');
    loadDepartmentsIntoDropdown();
};

// Export role check functions for use by other scripts
window.departmentDropdownUtils = {
    canViewAllDepartments: canUserViewAllDepartments,
    isRoleLocked: isUserRoleLocked,
    refresh: function() {
        if (!isUserRoleLocked()) {
            loadDepartmentsIntoDropdown();
        }
    }
};