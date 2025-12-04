/*load_departments_dropdown.js cargar dropdown de seleccion de departamentoss*/

document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando dropdown de departamentos...');
    loadDepartmentsIntoDropdown();
});

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

function populateDepartmentDropdown(departamentos) {
    const dropdownMenu = document.querySelector('[aria-labelledby="messageDropdown"]');
    
    if (!dropdownMenu) {
        console.warn('Dropdown menu no encontrado con aria-labelledby="messageDropdown"');
        return;
    }
    
    const divider = dropdownMenu.querySelector('.dropdown-divider');//para encontrar el divisor
    
    if (!divider) {
        console.warn('Divider no encontrado en dropdown');
        return;
    }
    
    let nextElement = divider.nextElementSibling;//limpiar items existenes despues de division
    while (nextElement) {
        const toRemove = nextElement;
        nextElement = nextElement.nextElementSibling;
        toRemove.remove();
    }
    
    departamentos.forEach(dept => {//agreagar cada departamento individual
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
            
            console.log('DEPARTAMENTO SELECCIONADO:', deptName, '(ID:', deptId + ')');
            
            //actualizar estadisticas del dashboard
            console.log('Actualizando estadisticas del dashboard principal...');
            if (typeof selectDepartmentFromDropdown === 'function') {
                selectDepartmentFromDropdown(deptId, deptName);
            } else {
                console.error('ERROR: selectDepartmentFromDropdown() NO ENCONTRADO!');
            }
            
            //actualizr grafico de carga de trabajo
            console.log('Actualizando grafico de deistribucion de carga de trabajo...');
            if (typeof selectDepartmentWorkload === 'function') {
                selectDepartmentWorkload(deptId, deptName);
            } else {
                console.warn('selectDepartmentWorkload() no encontrado');
            }
            
            closeDropdownSafely();
        });
        
        dropdownMenu.appendChild(link);
    });
    
    const dividerEnd = document.createElement('div');//agregar divisor despues de la opcion ver todos
    dividerEnd.className = 'dropdown-divider';
    dropdownMenu.appendChild(dividerEnd);
    
    const allLink = document.createElement('a');//agregar opcion de todos los departamentos
    allLink.className = 'dropdown-item text-center';
    allLink.href = '#';
    allLink.innerHTML = '<small class="text-muted">Ver todos los departamentos</small>';
    
    allLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        console.log('VISTA DE COMPARACIÓN SELECCIONADA');
        console.log('Cargando todos los departamentos...');
        
        //limpiar dashboard principal graficas
        console.log('Limpiando estadisticas del dashboard principal...');
        if (typeof clearDepartmentSelection === 'function') {
            clearDepartmentSelection();
        } else {
            console.error('ERROR: clearDepartmentSelection() NO ENCONTRADO!');
        }
        
        // Clear workload chart
        console.log('Limpieando grafica de distribucion de carga...');
        if (typeof resetWorkloadView === 'function') {
            resetWorkloadView();
        } else {
            console.warn('resetWorkloadView() no encontrado');
        }
        
        closeDropdownSafely();
    });
    
    dropdownMenu.appendChild(allLink);
    
    console.log(`Dropdown poblado con ${departamentos.length} departamentos`);
}

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

window.refreshDepartmentsList = function() {
    console.log('Refrescando lista de departamentos...');
    loadDepartmentsIntoDropdown();
};