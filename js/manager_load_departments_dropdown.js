/*manager_load_departments_dropdown.js para cargar dropdown de departamentos para gerentes con múltiples departamentos*/

let managerDepartmentsState = {
    departments: [],
    currentDepartmentId: null,
    currentDepartmentName: null,
    hasMultipleDepartments: false,
    isLoaded: false
};

document.addEventListener('DOMContentLoaded', function() {
    // Esperamos a que el core cargue primero el departamento principal
    setTimeout(() => {
        initializeManagerDepartmentDropdown();
    }, 300);
});

function initializeManagerDepartmentDropdown() {
    if (managerDepartmentsState.isLoaded) {
        return;
    }
    
    fetch('../php/manager_get_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            
            if (data.success) {
                processManagerDepartments(data);
                managerDepartmentsState.isLoaded = true;
            } else {
                console.error('[Dropdown] Error obteniendo departamentos:', data.message);
                hideLoadingState();
            }
        })
        .catch(error => {
            console.error('[Dropdown] Error cargando departamentos del gerente:', error);
            hideLoadingState();
        });
}

function processManagerDepartments(data) {
    // Guardar todos los departamentos donde el usuario tiene acceso
    const managedDepts = data.managed_departments || [];
    const allDepts = data.all_departments || [];
    
    // Usar TODOS los departamentos donde el usuario tiene acceso
    managerDepartmentsState.departments = allDepts;
    managerDepartmentsState.hasMultipleDepartments = allDepts.length > 1;
    
    // Establecer el departamento actual
    if (data.department) {
        managerDepartmentsState.currentDepartmentId = data.department.id_departamento;
        managerDepartmentsState.currentDepartmentName = data.department.nombre;
    }
    
    // Solo mostrar el dropdown si hay múltiples departamentos
    if (managerDepartmentsState.hasMultipleDepartments) {
        showDepartmentDropdown();
        populateManagerDepartmentDropdown(managerDepartmentsState.departments);
        
        // Actualizar el texto del botón con el departamento actual
        updateManagerDropdownButtonText(managerDepartmentsState.currentDepartmentName);
    } else {
        hideDepartmentDropdown();
    }
}

function showDepartmentDropdown() {
    const dropdownContainer = document.getElementById('departmentDropdownContainer');
    
    if (dropdownContainer) {
        // Remover clase d-none si existe y forzar display
        dropdownContainer.classList.remove('d-none');
        dropdownContainer.style.display = 'block';
    } else {
        console.error('[Dropdown] No se encontró el contenedor del dropdown con id "departmentDropdownContainer"');
    }
}

function hideDepartmentDropdown() {
    const dropdownContainer = document.getElementById('departmentDropdownContainer');
    if (dropdownContainer) {
        dropdownContainer.style.cssText = 'display: none !important;';
    }
}

function hideLoadingState() {
    const dropdownMenu = document.querySelector('[aria-labelledby="departmentDropdown"]');
    if (dropdownMenu) {
        const loadingDiv = dropdownMenu.querySelector('.spinner-border');
        if (loadingDiv && loadingDiv.parentElement) {
            loadingDiv.parentElement.innerHTML = '<span class="text-muted">No hay departamentos disponibles</span>';
        }
    }
}

function populateManagerDepartmentDropdown(departments) {
    const dropdownMenu = document.querySelector('[aria-labelledby="departmentDropdown"]');
    
    if (!dropdownMenu) {
        console.error('[Dropdown] Dropdown menu no encontrado con aria-labelledby="departmentDropdown"');
        return;
    }
    
    // Limpiar todo el contenido del dropdown
    dropdownMenu.innerHTML = '';
    
    // Crear el header
    const headerItem = document.createElement('a');
    headerItem.className = 'dropdown-item py-3';
    headerItem.style.cursor = 'default';
    headerItem.innerHTML = `
        <p class="mb-0 font-weight-medium float-left">
            <i class="mdi mdi-office-building-outline me-2"></i>
            Mis Departamentos
        </p>
    `;
    dropdownMenu.appendChild(headerItem);
    
    // Agregar divisor
    const divider = document.createElement('div');
    divider.className = 'dropdown-divider';
    dropdownMenu.appendChild(divider);
    
    // Agregar cada departamento
    departments.forEach(dept => {
        const link = document.createElement('a');
        link.className = 'dropdown-item preview-item department-item';
        link.href = '#';
        link.setAttribute('data-department-id', dept.id_departamento);
        link.setAttribute('data-department-name', dept.nombre);
        
        // Marcar el departamento actual
        const isCurrentDept = dept.id_departamento === managerDepartmentsState.currentDepartmentId;
        
        // Agregar estilo especial si es el departamento actual
        if (isCurrentDept) {
            link.style.backgroundColor = 'rgba(34, 139, 89, 0.08)';
        }
        
        link.innerHTML = `
            <div class="preview-item-content flex-grow py-2">
                <div class="d-flex align-items-center">
                    ${isCurrentDept 
                        ? '<i class="mdi mdi-check-circle text-success me-2" style="font-size: 18px;"></i>' 
                        : '<i class="mdi mdi-office-building me-2" style="color: rgba(34, 139, 89, 0.6); font-size: 18px;"></i>'}
                    <div>
                        <p class="preview-subject ellipsis font-weight-medium text-dark mb-0">${escapeHtml(dept.nombre)}</p>
                        <p class="fw-light small-text mb-0 text-muted">${escapeHtml(dept.descripcion || 'Sin descripción')}</p>
                    </div>
                </div>
            </div>
        `;
        
        // Agregar evento click
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const deptId = parseInt(this.getAttribute('data-department-id'));
            const deptName = this.getAttribute('data-department-name');
            
            // Evitar recargar si es el mismo departamento
            if (deptId === managerDepartmentsState.currentDepartmentId) {
                closeDropdownSafely();
                return;
            }
            
            selectManagerDepartment(deptId, deptName);
            closeDropdownSafely();
        });
        
        dropdownMenu.appendChild(link);
    });
}

function selectManagerDepartment(deptId, deptName) {
    // Actualizar estado local
    managerDepartmentsState.currentDepartmentId = deptId;
    managerDepartmentsState.currentDepartmentName = deptName;
    
    // Actualizar el texto del botón dropdown
    updateManagerDropdownButtonText(deptName);
    
    // Actualizar el subtítulo de bienvenida
    if (typeof updateDepartmentDisplay === 'function') {
        updateDepartmentDisplay(deptName);
    }
    
    // Actualizar el estado global del dashboard
    if (typeof managerDashboard !== 'undefined') {
        managerDashboard.department = {
            id: deptId,
            nombre: deptName,
            descripcion: ''
        };
    }
    
    // Refrescar todas las gráficas con el nuevo departamento
    refreshChartsForDepartment(deptId, deptName);
    
    // Re-poblar el dropdown para actualizar el indicador visual
    populateManagerDepartmentDropdown(managerDepartmentsState.departments);
}

function updateManagerDropdownButtonText(text) {
    const dropdownButton = document.getElementById('departmentDropdown');
    if (dropdownButton) {
        dropdownButton.innerHTML = `${escapeHtml(text)} `;
    }
}

function refreshChartsForDepartment(deptId, deptName) {
    // Mostrar indicador de carga
    showRefreshIndicator();
    
    // Array para almacenar las promesas
    const refreshTasks = [];
    
    // Gráfica de dona (estado de proyectos)
    if (typeof refreshManagerDoughnutChart === 'function') {
        refreshTasks.push(
            refreshManagerDoughnutChart(deptId, deptName).catch(e => console.warn('Error en doughnut:', e))
        );
    }
    
    // Gráfica de barras (progreso de proyectos)
    if (typeof refreshManagerBarChart === 'function') {
        refreshTasks.push(
            new Promise(resolve => setTimeout(resolve, 100))
                .then(() => refreshManagerBarChart(deptId, deptName))
                .catch(e => console.warn('Error en bar:', e))
        );
    }
    
    // Gráfica de línea (tendencia de proyectos)
    if (typeof refreshManagerLineChart === 'function') {
        refreshTasks.push(
            new Promise(resolve => setTimeout(resolve, 200))
                .then(() => refreshManagerLineChart(deptId, deptName))
                .catch(e => console.warn('Error en line:', e))
        );
    }
    
    // Gráfica de área (tendencia de tareas)
    if (typeof refreshManagerAreaChart === 'function') {
        refreshTasks.push(
            new Promise(resolve => setTimeout(resolve, 300))
                .then(() => refreshManagerAreaChart(deptId, deptName))
                .catch(e => console.warn('Error en area:', e))
        );
    }
    
    // Gráfica de dispersión (eficiencia)
    if (typeof refreshManagerScatterChart === 'function') {
        refreshTasks.push(
            new Promise(resolve => setTimeout(resolve, 400))
                .then(() => refreshManagerScatterChart(deptId, deptName))
                .catch(e => console.warn('Error en scatter:', e))
        );
    }
    
    // Gráfica de carga de trabajo
    if (typeof refreshManagerWorkloadChart === 'function') {
        refreshTasks.push(
            new Promise(resolve => setTimeout(resolve, 500))
                .then(() => refreshManagerWorkloadChart(deptId, deptName))
                .catch(e => console.warn('Error en workload:', e))
        );
    }
    
    // Esperar a que todas terminen
    Promise.all(refreshTasks)
        .finally(() => {
            hideRefreshIndicator();
        });
}

function showRefreshIndicator() {
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        indicator.classList.add('active');
    }
}

function hideRefreshIndicator() {
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        indicator.classList.remove('active');
    }
}

function closeDropdownSafely() {
    try {
        const dropdown = document.getElementById('departmentDropdown');
        if (dropdown) {
            // Intentar con Bootstrap 5
            if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                const bsDropdown = bootstrap.Dropdown.getInstance(dropdown);
                if (bsDropdown) {
                    bsDropdown.hide();
                    return;
                }
            }
            
            // Fallback manual
            dropdown.classList.remove('show');
            dropdown.setAttribute('aria-expanded', 'false');
            const dropdownMenu = dropdown.nextElementSibling;
            if (dropdownMenu) {
                dropdownMenu.classList.remove('show');
            }
        }
    } catch (err) {
        console.warn('Error cerrando dropdown:', err);
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

// Función para obtener el departamento actual del gerente
function getCurrentManagerDepartment() {
    return {
        id: managerDepartmentsState.currentDepartmentId,
        nombre: managerDepartmentsState.currentDepartmentName
    };
}

// Función para verificar si el gerente tiene múltiples departamentos
function hasMultipleDepartments() {
    return managerDepartmentsState.hasMultipleDepartments;
}

// Exportar funciones para uso externo
window.selectManagerDepartment = selectManagerDepartment;
window.getCurrentManagerDepartment = getCurrentManagerDepartment;
window.hasMultipleDepartments = hasMultipleDepartments;
window.refreshManagerDepartmentDropdown = initializeManagerDepartmentDropdown;