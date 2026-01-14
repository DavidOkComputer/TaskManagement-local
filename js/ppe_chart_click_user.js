/*ppe_chart_click_user.js para hacer clic en grafica de dona en dashboard para el usuario*/

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initUserChartClickHandler, 1000);
});

function initUserChartClickHandler() {
    const chartCanvas = document.getElementById('doughnutChart');
    
    if (!chartCanvas) {
        console.warn('User doughnut chart canvas not found');
        return;
    }
    
    const chartInstance = Chart.getChart(chartCanvas);
    
    if (!chartInstance) {
        console.warn('User chart instance not found, retrying...');
        setTimeout(initUserChartClickHandler, 1000);
        return;
    }
    
    chartCanvas.addEventListener('click', function(event) {
        handleUserChartClick(event, chartInstance);
    });
    
    chartCanvas.style.cursor = 'pointer';
    
    console.log('User dashboard chart click handler initialized');
}

function handleUserChartClick(event, chart) {
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
    const datasetIndex = clickedElement.datasetIndex;
    const index = clickedElement.index;
    const label = chart.data.labels[index];
    
    if (!label) {
        console.warn('No label found for clicked segment');
        return;
    }
    
    const statusMap = {
        'Pendiente': 'pendiente',
        'Pendientes': 'pendiente',
        'En Proceso': 'en proceso',
        'En proceso': 'en proceso',
        'Completado': 'completado',
        'Completados': 'completado',
        'Completada': 'completado',
        'Completadas': 'completado',
        'Vencido': 'vencido',
        'Vencidos': 'vencido',
        'Vencida': 'vencido',
        'Vencidas': 'vencido',
        'Pending': 'pendiente',
        'In Progress': 'en proceso',
        'Completed': 'completado',
        'Overdue': 'vencido'
    };
    
    const statusFilter = statusMap[label];
    
    if (!statusFilter) {
        console.warn('Unknown status label:', label);
        return;
    }
    redirectToUserProjectsWithFilter(statusFilter);
}

function redirectToUserProjectsWithFilter(status) {
    const encodedStatus = encodeURIComponent(status);
    const baseUrl = '../revisarProyectosUser/';
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
        'completado': 'Completados',
        'vencido': 'Vencidos'
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
                    <strong class="me-auto">Filtrando proyectos</strong>
                </div>
                <div class="toast-body">
                    <span id="filterToastMessage">Cargando proyectos...</span>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
    }
    
    const messageEl = document.getElementById('filterToastMessage');
    if (messageEl) {
        messageEl.innerHTML = `
            <i class="mdi mdi-loading mdi-spin me-2"></i>
            Mostrando proyectos <strong>${label}</strong>...
        `;
    }
    
    toast.style.display = 'block';
}

function addUserClickHandlerToChart(chartInstance) {
    if (!chartInstance) {
        console.error('Chart instance is required');
        return;
    }
    
    const canvas = chartInstance.canvas;
    
    canvas.addEventListener('click', function(event) {
        handleUserChartClick(event, chartInstance);
    });
    
    canvas.style.cursor = 'pointer';
}

window.initUserChartClickHandler = initUserChartClickHandler;
window.addUserClickHandlerToChart = addUserClickHandlerToChart;