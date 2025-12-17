(function() {
    function updateDateTime() {
        const now = new Date();
        
        // Update time (HH:MM:SS format)
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds}`;
        
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
        
        // Update date (Día, DD de Mes de YYYY format)
        const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        
        const diaSemana = dias[now.getDay()];
        const dia = now.getDate();
        const mes = meses[now.getMonth()];
        const año = now.getFullYear();
        
        const dateString = `${diaSemana}, ${dia} de ${mes} ${año}`;
        
        const dateElement = document.getElementById('currentDate');
        if (dateElement) {
            dateElement.textContent = dateString;
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateDateTime(); // Update immediately
        setInterval(updateDateTime, 1000); // Update every second
    });
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