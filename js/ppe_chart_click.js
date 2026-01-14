/*ppe_chart_click.js para redirigir a lista filtrada desde grafica de dona*/

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initPPEChartClickHandler, 1000);
});

function initPPEChartClickHandler() {
    const chartCanvas = document.getElementById('doughnutChart');
    
    if (!chartCanvas) {
        console.warn('PPE Chart canvas not found');
        return;
    }
    
    const chartInstance = Chart.getChart(chartCanvas);
    
    if (!chartInstance) {
        console.warn('PPE Chart instance not found, retrying...');
        setTimeout(initPPEChartClickHandler, 1000);
        return;
    }
    
    chartCanvas.addEventListener('click', function(event) {
        handleChartClick(event, chartInstance);
    });
    
    chartCanvas.style.cursor = 'pointer';
    
    console.log('PPE Chart click handler initialized');
}

function handleChartClick(event, chart) {
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
        'Vencido': 'vencido',
        'Vencidos': 'vencido'
    };
    
    const statusFilter = statusMap[label];
    
    if (!statusFilter) {
        console.warn('Unknown status label:', label);
        return;
    }
    
    redirectToProjectsWithFilter(statusFilter);
}

function redirectToProjectsWithFilter(status) {
    const encodedStatus = encodeURIComponent(status);
    
    const baseUrl = '../revisarProyectos/';
    const filterUrl = `${baseUrl}?estado=${encodedStatus}`;
    
    showFilterRedirectToast(status);
    setTimeout(() => {
        window.location.href = filterUrl;
    }, 300);
}

function showFilterRedirectToast(status) {
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

function addClickHandlerToChart(chartInstance) {
    if (!chartInstance) {
        console.error('Chart instance is required');
        return;
    }
    
    const canvas = chartInstance.canvas;
    
    canvas.addEventListener('click', function(event) {
        handleChartClick(event, chartInstance);
    });
    
    canvas.style.cursor = 'pointer';
}

window.initPPEChartClickHandler = initPPEChartClickHandler;
window.addClickHandlerToChart = addClickHandlerToChart;