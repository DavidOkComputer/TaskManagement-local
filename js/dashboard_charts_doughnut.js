/**
 * dashboard_charts_doughnut.js
 * Project status distribution doughnut chart
 * Shows: Completado, En Proceso, Pendiente, Vencido
 */

/**
 * Prepare project status distribution data (comparison view - all projects)
 */
function prepareProjectStatusDistribution(projects) {
    console.log('Preparando: Distribución de estados de proyectos');

    const statusCounts = {
        'completado': 0,
        'en proceso': 0,
        'pendiente': 0,
        'vencido': 0
    };

    // Count projects by status
    projects.forEach(proj => {
        const status = proj.estado.toLowerCase();
        if (statusCounts.hasOwnProperty(status)) {
            statusCounts[status]++;
        }
    });

    const data = {
        labels: ['Completados', 'En Proceso', 'Pendientes', 'Vencidos'],
        data: [
            statusCounts['completado'],
            statusCounts['en proceso'],
            statusCounts['pendiente'],
            statusCounts['vencido']
        ],
        backgroundColor: [
            'rgba(34, 139, 89, 0.7)',     // Green - Completado
            'rgba(130, 140, 150, 0.7)',   // Gray - En Proceso
            'rgba(200, 205, 210, 0.7)',   // Ice - Pendiente
            'rgba(50, 50, 50, 0.7)'       // Black - Vencidos
        ],
        borderColor: [
            'rgba(34, 139, 89, 1)',       // Green
            'rgba(130, 140, 150, 1)',     // Gray
            'rgba(200, 205, 210, 1)',     // Ice
            'rgba(50, 50, 50, 1)'         // Black
        ]
    };

    console.log('Datos de estado preparados:', data);
    return data;
}

/**
 * Prepare department-specific status distribution
 */
function prepareDepartmentStatusDistribution(projects) {
    const statusCounts = {
        'completado': 0,
        'en proceso': 0,
        'pendiente': 0,
        'vencido': 0
    };

    projects.forEach(proj => {
        const status = proj.estado.toLowerCase();
        if (statusCounts.hasOwnProperty(status)) {
            statusCounts[status]++;
        }
    });

    return {
        labels: ['Completados', 'En Proceso', 'Pendientes', 'Vencidos'],
        data: [
            statusCounts['completado'],
            statusCounts['en proceso'],
            statusCounts['pendiente'],
            statusCounts['vencido']
        ],
        backgroundColor: [
            'rgba(34, 139, 89, 0.7)',     // Green - Completado
            'rgba(130, 140, 150, 0.7)',   // Gray - En Proceso
            'rgba(200, 205, 210, 0.7)',   // Ice - Pendiente
            'rgba(50, 50, 50, 0.7)'       // Black - Vencido
        ],
        borderColor: [
            'rgba(34, 139, 89, 1)',       // Green
            'rgba(130, 140, 150, 1)',     // Gray
            'rgba(200, 205, 210, 1)',     // Ice
            'rgba(50, 50, 50, 1)'         // Black
        ]
    };
}

/**
 * Update doughnut chart for comparison view (all projects)
 */
function updateDoughnutChart(data) {
    const ctx = document.getElementById('doughnutChart');
    if (!ctx) {
        console.warn('Doughnut chart canvas not found');
        return;
    }

    // Destroy existing chart
    if (dashboardChartsInstance.charts.doughnutChart) {
        dashboardChartsInstance.charts.doughnutChart.destroy();
    }

    const chartData = {
        labels: data.labels,
        datasets: [{
            data: data.data,
            backgroundColor: data.backgroundColor,
            borderColor: data.borderColor,
            borderWidth: 1
        }]
    };

    const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            title: {
                display: true,
                text: 'Distribución de Estados de Proyectos'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    };

    dashboardChartsInstance.charts.doughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });

    console.log('Doughnut chart actualizado (comparación)');
}

/**
 * Update doughnut chart for department view
 */
function updateDoughnutChartForDepartment(data, deptName) {
    const ctx = document.getElementById('doughnutChart');

    if (!ctx) {
        console.warn('Doughnut chart canvas not found');
        return;
    }

    // Destroy existing chart
    if (dashboardChartsInstance.charts.doughnutChart) {
        dashboardChartsInstance.charts.doughnutChart.destroy();
    }

    const chartData = {
        labels: data.labels,
        datasets: [{
            data: data.data,
            backgroundColor: data.backgroundColor,
            borderColor: data.borderColor,
            borderWidth: 1
        }]
    };

    const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            title: {
                display: true,
                text: `Distribución de Estados - ${deptName}`
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    };

    dashboardChartsInstance.charts.doughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });

    console.log('Doughnut chart actualizado (departamento)');
}