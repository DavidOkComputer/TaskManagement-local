/**
 * departments_widget.js - Department Display Widget
 * Displays available departments as flag-style items in the navbar
 */

(function() {
    // Department colors palette - matching quick stats style
    const departmentColors = [
        { bg: '#667eea', light: '#8b9ff5' },
        { bg: '#11998e', light: '#3dbdb2' },
        { bg: '#ee5a24', light: '#f57f4d' },
        { bg: '#9b59b6', light: '#b07cc6' },
        { bg: '#3498db', light: '#5faee3' },
        { bg: '#1abc9c', light: '#48d1b5' },
        { bg: '#e74c3c', light: '#ee7b6e' },
        { bg: '#f39c12', light: '#f6b93b' }
    ];

    // Load departments from API
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

    // Render department items as flags
    function renderDepartments(departments) {
        const container = document.getElementById('departmentsWidgetContainer');
        if (!container) return;

        if (departments.length === 0) {
            renderEmptyState();
            return;
        }

        // Limit to first 5 departments for navbar space
        const displayDepts = departments.slice(0, 5);
        
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

        // Add "more" indicator if there are more departments
        if (departments.length > 5) {
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

    // Render empty state
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

    // Get initials from department name
    function getInitials(name) {
        if (!name) return '??';
        const words = name.trim().split(/\s+/);
        if (words.length === 1) {
            return words[0].substring(0, 2).toUpperCase();
        }
        return (words[0][0] + words[1][0]).toUpperCase();
    }

    // Truncate name for display
    function truncateName(name, maxLength) {
        if (!name) return '';
        if (name.length <= maxLength) return name;
        return name.substring(0, maxLength - 1) + '…';
    }

    // Attach click handlers to department items
    function attachClickHandlers() {
        const items = document.querySelectorAll('.dept-flag');
        items.forEach(item => {
            item.addEventListener('click', function() {
                const deptId = this.dataset.deptId;
                // Navigate to department management
                window.location.href = '../gestionDeDepartamentos/';
            });
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadDepartments();
        
        // Refresh departments every 5 minutes
        setInterval(loadDepartments, 300000);
    });

    // Expose function globally for manual refresh
    window.refreshDepartmentsWidget = loadDepartments;
})();

// Quick Stats Bar - Load user's task statistics
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
    // Update pending tasks
    const pendingElement = document.querySelector('#navPendingTasks .stat-value');
    if (pendingElement) {
        pendingElement.textContent = stats.pendientes || 0;
    }
    
    // Update today's tasks
    const todayElement = document.querySelector('#navTodayTasks .stat-value');
    if (todayElement) {
        todayElement.textContent = stats.hoy || 0;
    }
    
    // Update overdue tasks
    const overdueElement = document.querySelector('#navOverdueTasks .stat-value');
    const overdueContainer = document.getElementById('navOverdueTasks');
    if (overdueElement) {
        overdueElement.textContent = stats.vencidas || 0;
        
        // Add pulse animation if there are overdue tasks
        if (stats.vencidas > 0) {
            overdueContainer.classList.add('has-items');
        } else {
            overdueContainer.classList.remove('has-items');
        }
    }
}
 
// Add click handlers for quick navigation
document.addEventListener('DOMContentLoaded', function() {
    loadQuickStats();
    
    // Refresh every 2 minutes
    setInterval(loadQuickStats, 120000);
    
    // Click handlers
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