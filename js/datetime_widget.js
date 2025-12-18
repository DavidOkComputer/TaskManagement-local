/*departments_widget.js widgedt para el dashboard de admin principal */

(function() {
    
    const departmentColors = [
        {bg: '#31ab6a',
			light: '#31ab6a' }
    ];

    // CARGAR DEPARTAMENTOS DESDE LA API
    function loadDepartments() {
        const container = document.getElementById('departmentsWidgetContainer');
        if (!container) return;

        fetch('../php/get_departments.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.departamentos) {
                    renderDepartments(data.departamentos);
                } else {
                    console.error('Error loading departments:', data.message);
                    renderEmptyState();
                }
            })
            .catch(error => {
                console.error('Error fetching departments:', error);
                renderEmptyState();
            });
    }

    //renderizar los items de departamentos como si fueran banderaas
    function renderDepartments(departments) {
        const container = document.getElementById('departmentsWidgetContainer');
        if (!container) return;

        if (departments.length === 0) {
            renderEmptyState();
            return;
        }

        //limitar a los 6 primeros departamentos
        const displayDepts = departments.slice(0, 6);
        
        let html = '';
        displayDepts.forEach((dept, index) => {
            const colorScheme = departmentColors[index % departmentColors.length];
            const initials = getInitials(dept.nombre);
            
            html += `
                <div class="dept-flag" 
                     data-dept-id="${dept.id_departamento}"
                     title="${dept.nombre}${dept.descripcion ? ': ' + dept.descripcion : ''}"
                     style="--dept-color: ${colorScheme.bg}; --dept-light: ${colorScheme.light};">
                    <div class="dept-flag-stripe"></div>
                    <div class="dept-flag-content">
                        <i class="mdi mdi-domain"></i>
                        <span class="dept-flag-initials">${initials}</span>
                        <span class="dept-flag-name">${truncateName(dept.nombre, 10)}</span>
                    </div>
                </div>
            `;
        });

        //agregar indicador de mas si hay mas departamentos
        if (departments.length > 6) {
            html += `
                <div class="dept-flag dept-flag-more" 
                     title="Ver todos los departamentos (${departments.length} total)"
                     style="--dept-color: #6c757d; --dept-light: #868e96;">
                    <div class="dept-flag-stripe"></div>
                    <div class="dept-flag-content">
                        <i class="mdi mdi-dots-horizontal"></i>
                        <span class="dept-flag-initials">+${departments.length - 5}</span>
                        <span class="dept-flag-name">Más</span>
                    </div>
                </div>
            `;
        }

        container.innerHTML = html;
        attachClickHandlers();
    }

    //renderizar estado de vacio
    function renderEmptyState() {
        const container = document.getElementById('departmentsWidgetContainer');
        if (!container) return;

        container.innerHTML = `
            <div class="dept-flag dept-flag-empty" 
                 style="--dept-color: #adb5bd; --dept-light: #ced4da;">
                <div class="dept-flag-stripe"></div>
                <div class="dept-flag-content">
                    <i class="mdi mdi-office-building-outline"></i>
                    <span class="dept-flag-initials">--</span>
                    <span class="dept-flag-name">Sin deptos</span>
                </div>
            </div>
        `;
    }

    //iniciales del departamento
    function getInitials(name) {
        if (!name) return '??';
        const words = name.trim().split(/\s+/);
        if (words.length === 1) {
            return words[0].substring(0, 2).toUpperCase();
        }
        return (words[0][0] + words[1][0]).toUpperCase();
    }

    //truncar el nombre para mostrarlo
    function truncateName(name, maxLength) {
        if (!name) return '';
        if (name.length <= maxLength) return name;
        return name.substring(0, maxLength - 1) + '…';
    }

    //agregar click hanglers a lositems de departamentos
    function attachClickHandlers() {
        const items = document.querySelectorAll('.dept-flag');
        items.forEach(item => {
            item.addEventListener('click', function() {
                const deptId = this.dataset.deptId;
                window.location.href = '../gestionDeDepartamentos/';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadDepartments();
        setInterval(loadDepartments, 900000);
    });

    //hacer funcion global para poder refrescarla a mano
    window.refreshDepartmentsWidget = loadDepartments;
})();

//barra de estadisticas rapidas cargar tareas de usuarios
function loadQuickStats() {
    fetch('../php/get_user_quick_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateQuickStats(data.stats);
            }
        })
        .catch(error => {
            console.error('Error loading quick stats:', error);
        });
}
 
function updateQuickStats(stats) {
    const pendingElement = document.querySelector('#navPendingTasks .stat-value');
    if (pendingElement) {
        pendingElement.textContent = stats.pendientes || 0;
    }
    
    const todayElement = document.querySelector('#navTodayTasks .stat-value');
    if (todayElement) {
        todayElement.textContent = stats.hoy || 0;
    }
    
    const overdueElement = document.querySelector('#navOverdueTasks .stat-value');
    const overdueContainer = document.getElementById('navOverdueTasks');
    if (overdueElement) {
        overdueElement.textContent = stats.vencidas || 0;
        
        //animacion para tareas vencidas
        if (stats.vencidas > 0) {
            overdueContainer.classList.add('has-items');
        } else {
            overdueContainer.classList.remove('has-items');
        }
    }
}
 
document.addEventListener('DOMContentLoaded', function() {
    loadQuickStats();
    
    setInterval(loadQuickStats, 120000);
    
    const pendingItem = document.getElementById('navPendingTasks');
    if (pendingItem) {
        pendingItem.addEventListener('click', () => {
            window.location.href = '../revisarTareas/';
        });
    }
    
    const todayItem = document.getElementById('navTodayTasks');
    if (todayItem) {
        todayItem.addEventListener('click', () => {
            window.location.href = '../revisarTareas/';
        });
    }
    
    const overdueItem = document.getElementById('navOverdueTasks');
    if (overdueItem) {
        overdueItem.addEventListener('click', () => {
            window.location.href = '../revisarTareas/';
        });
    }
});