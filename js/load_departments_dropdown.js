/**
 * load_departments_dropdown.js
 * Dynamic loading of departments into dropdown
 * Admin dashboard - simplified version (no role restrictions)
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando dropdown de departamentos...');
    loadDepartmentsIntoDropdown();
});

/**
 * Load departments from API into dropdown
 */
function loadDepartmentsIntoDropdown() {
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

/**
 * Populate dropdown menu with department items
 */
function populateDepartmentDropdown(departamentos) {
    const dropdownMenu = document.querySelector('[aria-labelledby="messageDropdown"]');
    
    if (!dropdownMenu) {
        console.warn('Dropdown menu no encontrado con aria-labelledby="messageDropdown"');
        return;
    }
    
    // Find the divider
    const divider = dropdownMenu.querySelector('.dropdown-divider');
    
    if (!divider) {
        console.warn('Divider no encontrado en dropdown');
        return;
    }
    
    // Clear existing items after divider
    let nextElement = divider.nextElementSibling;
    while (nextElement) {
        const toRemove = nextElement;
        nextElement = nextElement.nextElementSibling;
        toRemove.remove();
    }
    
    // Add each department
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
            
            const deptId = parseInt(this.getAttribute('data-department-id'));
            const deptName = this.getAttribute('data-department-name');
            
            console.log('═══════════════════════════════════════════════════════');
            console.log('DEPARTAMENTO SELECCIONADO:', deptName, '(ID:', deptId + ')');
            console.log('═══════════════════════════════════════════════════════');
            
            // Update main dashboard charts
            console.log('Step 1: Updating main dashboard charts...');
            if (typeof selectDepartmentFromDropdown === 'function') {
                selectDepartmentFromDropdown(deptId, deptName);
            } else {
                console.error('ERROR: selectDepartmentFromDropdown() NOT FOUND!');
            }
            
            // Update workload chart
            console.log('Step 2: Updating workload chart...');
            if (typeof selectDepartmentWorkload === 'function') {
                selectDepartmentWorkload(deptId, deptName);
            } else {
                console.warn('selectDepartmentWorkload() not found');
            }
            
            closeDropdownSafely();
        });
        
        dropdownMenu.appendChild(link);
    });
    
    // Add divider before "View all" option
    const dividerEnd = document.createElement('div');
    dividerEnd.className = 'dropdown-divider';
    dropdownMenu.appendChild(dividerEnd);
    
    // Add "View all departments" option
    const allLink = document.createElement('a');
    allLink.className = 'dropdown-item text-center';
    allLink.href = '#';
    allLink.innerHTML = '<small class="text-muted">Ver todos los departamentos</small>';
    
    allLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        console.log('═══════════════════════════════════════════════════════');
        console.log('VISTA DE COMPARACIÓN SELECCIONADA');
        console.log('Cargando todos los departamentos...');
        console.log('═══════════════════════════════════════════════════════');
        
        // Clear main dashboard charts
        console.log('Step 1: Clearing main dashboard charts...');
        if (typeof clearDepartmentSelection === 'function') {
            clearDepartmentSelection();
        } else {
            console.error('ERROR: clearDepartmentSelection() NOT FOUND!');
        }
        
        // Clear workload chart
        console.log('Step 2: Clearing workload chart...');
        if (typeof resetWorkloadView === 'function') {
            resetWorkloadView();
        } else {
            console.warn('resetWorkloadView() not found');
        }
        
        closeDropdownSafely();
    });
    
    dropdownMenu.appendChild(allLink);
    
    console.log(`Dropdown poblado con ${departamentos.length} departamentos`);
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
                dropdown.classList.remove('show');
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

/**
 * Escape HTML to prevent XSS
 */
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

/**
 * Public function to refresh departments list
 */
window.refreshDepartmentsList = function() {
    console.log('Refrescando lista de departamentos...');
    loadDepartmentsIntoDropdown();
};