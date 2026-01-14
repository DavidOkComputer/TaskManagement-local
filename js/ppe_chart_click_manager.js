/*ppe_chart_click_manager.js para hacer clic en grafica de dona de gerente*/

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initManagerChartClickHandler, 1500);
});

function initManagerChartClickHandler() {
    const chartCanvas = document.getElementById('doughnutChart');
    
    if (!chartCanvas) {
        console.warn('Manager P.P.E. doughnut chart canvas not found');
        return;
    }
    
    const chartInstance = Chart.getChart(chartCanvas);
    
    if (!chartInstance) {
        console.warn('Manager chart instance not found, retrying...');
        setTimeout(initManagerChartClickHandler, 1000);
        return;
    }
    
    chartCanvas.addEventListener('click', function(event) {
        handleManagerChartClick(event, chartInstance);
    });
    
    chartCanvas.style.cursor = 'pointer';
    console.log('Manager dashboard P.P.E. chart click handler initialized');
}

function handleManagerChartClick(event, chart) {
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
    
    redirectToManagerProjectsWithFilter(statusFilter);
}

function redirectToManagerProjectsWithFilter(status) {
    const encodedStatus = encodeURIComponent(status);
    const baseUrl = '../revisarProyectosGerente/';
    const filterUrl = `${baseUrl}?estado=${encodedStatus}`;
    
    showManagerFilterRedirectToast(status);
    
    setTimeout(() => {
        window.location.href = filterUrl;
    }, 300);
}

function showManagerFilterRedirectToast(status) {
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

function addManagerClickHandlerToChart(chartInstance) {
    if (!chartInstance) {
        console.error('Chart instance is required');
        return;
    }
    
    const canvas = chartInstance.canvas;
    
    canvas.addEventListener('click', function(event) {
        handleManagerChartClick(event, chartInstance);
    });
    
    canvas.style.cursor = 'pointer';
}

window.initManagerChartClickHandler = initManagerChartClickHandler;
window.addManagerClickHandlerToChart = addManagerClickHandlerToChart;