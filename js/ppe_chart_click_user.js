/*ppe_chart_click_user.js para hacer clic en gráfica de dona en dashboard para el usuario*/
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initUserChartClickHandler, 1500);
});
 
function initUserChartClickHandler() {
    const chartCanvas = document.getElementById('doughnutChart');
    
    if (!chartCanvas) {
        console.warn('User doughnut chart canvas not found');
        return;
    }
 
    // El gráfico del usuario se guarda en window.userTasksChart
    const chartInstance = window.userTasksChart;
    
    if (!chartInstance) {
        console.warn('User chart instance not found, retrying...');
        setTimeout(initUserChartClickHandler, 1000);
        return;
    }
 
    // Verificar que el gráfico tiene labels válidas
    if (!chartInstance.data.labels || chartInstance.data.labels.length === 0) {
        console.warn('Chart labels not ready, retrying...');
        setTimeout(initUserChartClickHandler, 500);
        return;
    }
 
    // Verificar que no sea el estado "Sin tareas"
    if (chartInstance.data.labels[0] === 'Sin tareas asignadas') {
        console.log('No tasks assigned, click handler not needed');
        return;
    }
 
    chartCanvas.addEventListener('click', function(event) {
        handleUserChartClick(event, window.userTasksChart);
    });
    
    chartCanvas.style.cursor = 'pointer';
    console.log('User dashboard chart click handler initialized');
    console.log('Chart labels:', chartInstance.data.labels);
}
 
function handleUserChartClick(event, chart) {
    // Verificar que el chart aún existe (puede ser recreado)
    if (!chart || !chart.data) {
        console.warn('Chart instance not available');
        return;
    }
 
    const activePoints = chart.getElementsAtEventForMode(
        event,
        'nearest',
        { intersect: true },
        false
    );
 
    if (activePoints.length === 0) {
        return;
    }
 
    const clickedElement = activePoints[0];
    
    // Chart.js 2.x usa _index, Chart.js 3.x usa index
    const index = clickedElement._index !== undefined ? clickedElement._index : clickedElement.index;
    
    console.log('Clicked element:', clickedElement);
    console.log('Index:', index);
 
    const labels = chart.data.labels;
    
    if (!labels || !Array.isArray(labels) || index === undefined || index >= labels.length) {
        console.warn('Labels not available or index out of range:', {
            labels: labels,
            index: index
        });
        return;
    }
 
    const label = labels[index];
 
    // Ignorar clic si es "Sin tareas asignadas"
    if (!label || label === 'Sin tareas asignadas') {
        console.log('No valid label or no tasks');
        return;
    }
 
    // Mapeo para TAREAS del usuario
    // Labels del usuario: ['Pendientes', 'Completadas', 'Vencidas']
    const statusMap = {
        'Pendiente': 'pendiente',
        'Pendientes': 'pendiente',
        'En Proceso': 'en proceso',
        'En proceso': 'en proceso',
        'En Progreso': 'en proceso',
        'En progreso': 'en proceso',
        'Completado': 'completado',
        'Completados': 'completado',
        'Completada': 'completado',
        'Completadas': 'completado',
        'Vencido': 'vencido',
        'Vencidos': 'vencido',
        'Vencida': 'vencido',
        'Vencidas': 'vencido'
    };
 
    const statusFilter = statusMap[label];
 
    if (!statusFilter) {
        console.warn('Unknown status label:', label);
        return;
    }
 
    console.log('Redirecting to tasks with filter:', statusFilter, 'from label:', label);
    redirectToUserTasksWithFilter(statusFilter);
}
 
function redirectToUserTasksWithFilter(status) {
    const encodedStatus = encodeURIComponent(status);
    // Redirigir a la página de TAREAS del usuario (ajusta la URL según tu estructura)
    const baseUrl = '../revisarTareasUser/';
    const filterUrl = `${baseUrl}?estado=${encodedStatus}`;
 
    showUserFilterRedirectToast(status);
 
    setTimeout(() => {
        window.location.href = filterUrl;
    }, 300);
}
 
function showUserFilterRedirectToast(status) {
    const statusLabels = {
        'pendiente': 'Pendientes',
        'en proceso': 'En Proceso',
        'completado': 'Completadas',
        'vencido': 'Vencidas'
    };
 
    const label = statusLabels[status] || status;
 
    let toast = document.getElementById('filterRedirectToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'filterRedirectToast';
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-primary text-white">
                    <i class="mdi mdi-filter me-2"></i>
                    <strong class="me-auto">Filtrando tareas</strong>
                </div>
                <div class="toast-body">
                    <span id="filterToastMessage">Cargando tareas...</span>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
    }
 
    const messageEl = document.getElementById('filterToastMessage');
    if (messageEl) {
        messageEl.innerHTML = `
            <i class="mdi mdi-loading mdi-spin me-2"></i>
            Mostrando tareas <strong>${label}</strong>...
        `;
    }
    toast.style.display = 'block';
}
 
// Re-inicializar cuando el gráfico se actualiza (porque se destruye y recrea)
function reinitUserChartClickHandler() {
    setTimeout(initUserChartClickHandler, 500);
}
 
window.initUserChartClickHandler = initUserChartClickHandler;
window.reinitUserChartClickHandler = reinitUserChartClickHandler;