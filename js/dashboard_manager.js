/*
 * dashboard_manager.js
 * JavaScript para el dashboard de gerente
 * Inicializa gráficos para visualización de datos del departamento
 */

(function($) {
    'use strict';

    // Inicializar gráfico de dona para proyectos del departamento
    if ($("#doughnutChart").length) {
        const doughnutChartCanvas = document.getElementById('doughnutChart').getContext('2d');
        
        window.doughnutChart = new Chart(doughnutChartCanvas, {
            type: 'doughnut',
            data: {
                labels: ["Pendientes", "Completados", "Vencidos", "En Proceso"],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: [
                        "#ffce56",  // Amarillo - pendientes
                        "#28a745",  // Verde - completados
                        "#dc3545",  // Rojo - vencidos
                        "#007bff"   // Azul - en proceso
                    ],
                    borderColor: [
                        "#ffce56",
                        "#28a745",
                        "#dc3545",
                        "#007bff"
                    ],
                }]
            },
            options: {
                cutoutPercentage: 50,
                animationEasing: "easeOutBounce",
                animateRotate: true,
                animateScale: false,
                responsive: true,
                maintainAspectRatio: true,
                showScale: true,
                legend: {
                    display: false
                },
                legendCallback: function(chart) {
                    var text = [];
                    text.push('<ul class="doughnut-legend" style="list-style: none; padding: 0; display: flex; flex-wrap: wrap; justify-content: center; gap: 15px;">');
                    for (var i = 0; i < chart.data.datasets[0].data.length; i++) {
                        text.push('<li style="display: flex; align-items: center; gap: 5px;">');
                        text.push('<span class="legend-label" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color:' + 
                            chart.data.datasets[0].backgroundColor[i] + '"></span>');
                        if (chart.data.labels[i]) {
                            text.push('<span class="legend-text" style="font-size: 12px;">' + chart.data.labels[i] + 
                                ': <strong>' + chart.data.datasets[0].data[i] + '</strong></span>');
                        }
                        text.push('</li>');
                    }
                    text.push('</ul>');
                    return text.join("");
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            var dataset = data.datasets[tooltipItem.datasetIndex];
                            var total = dataset.data.reduce(function(prev, curr) {
                                return prev + curr;
                            }, 0);
                            var currentValue = dataset.data[tooltipItem.index];
                            var percentage = total > 0 ? Math.round((currentValue / total) * 100) : 0;
                            return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        });

        // Generar leyenda inicial
        const legendElement = document.getElementById('doughnut-chart-legend');
        if (legendElement) {
            legendElement.innerHTML = window.doughnutChart.generateLegend();
        }
    }

})(jQuery);

// Función para actualizar el gráfico de proyectos del departamento
function updateDepartmentProjectsChart(pendientes, completados, vencidos, enProceso) {
    if (!window.doughnutChart) {
        console.warn('Doughnut chart not initialized yet');
        return;
    }

    window.doughnutChart.data.datasets[0].data = [
        pendientes,
        completados,
        vencidos,
        enProceso
    ];

    window.doughnutChart.update();

    // Actualizar leyenda
    const legendElement = document.getElementById('doughnut-chart-legend');
    if (legendElement) {
        legendElement.innerHTML = window.doughnutChart.generateLegend();
    }

    console.log('Chart de departamento actualizado:', {
        pendientes: pendientes,
        completados: completados,
        vencidos: vencidos,
        enProceso: enProceso
    });
}

// Función para actualizar el gráfico basado en array de proyectos
function updateProyectoStatusChart(proyectos) {
    if (!window.doughnutChart) {
        console.warn('Doughnut chart not initialized yet');
        return;
    }

    const statusCounts = {
        'pendiente': 0,
        'completado': 0,
        'vencido': 0,
        'en proceso': 0
    };

    if (!proyectos || proyectos.length === 0) {
        window.doughnutChart.data.datasets[0].data = [0, 0, 0, 0];
        window.doughnutChart.update();
        const legendElement = document.getElementById('doughnut-chart-legend');
        if (legendElement) {
            legendElement.innerHTML = window.doughnutChart.generateLegend();
        }
        return;
    }

    proyectos.forEach(function(proyecto) {
        const estado = proyecto.estado.toLowerCase().trim();
        if (statusCounts.hasOwnProperty(estado)) {
            statusCounts[estado]++;
        }
    });

    window.doughnutChart.data.datasets[0].data = [
        statusCounts['pendiente'],
        statusCounts['completado'],
        statusCounts['vencido'],
        statusCounts['en proceso']
    ];

    window.doughnutChart.update();

    const legendElement = document.getElementById('doughnut-chart-legend');
    if (legendElement) {
        legendElement.innerHTML = window.doughnutChart.generateLegend();
    }
}

// Exportar funciones
window.updateDepartmentProjectsChart = updateDepartmentProjectsChart;
window.updateProyectoStatusChart = updateProyectoStatusChart;