/*dashboard_user.js para el dashboard del usuario inicializa graficos especificos del usuario*/

(function($) {
    'use strict';

    // Inicializar gráfico de dona para tareas del usuario
    if ($("#doughnutChart").length) {
        const doughnutChartCanvas = document.getElementById('doughnutChart').getContext('2d');
        
        window.doughnutChart = new Chart(doughnutChartCanvas, {
            type: 'doughnut',
            data: {
                labels: ["Pendientes", "Completadas", "Vencidas"],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: [
                        "#666666",  //gris- pendientes
                        "#009b4a",  // Verde - completadas
                        "#000000"   // negro - vencidas
                    ],
                    borderColor: [
                        "#666666",  //gris- pendientes
                        "#009b4a",  // Verde - completadas
                        "#000000"   // negro - vencidas
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
                    text.push('<ul class="doughnut-legend">');
                    for (var i = 0; i < chart.data.datasets[0].data.length; i++) {
                        text.push('<li><span class="legend-label" style="background-color:' + 
                            chart.data.datasets[0].backgroundColor[i] + '"></span>');
                        if (chart.data.labels[i]) {
                            text.push('<span class="legend-text">' + chart.data.labels[i] + 
                                ': ' + chart.data.datasets[0].data[i] + '</span>');
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

// Función para actualizar el gráfico de tareas del usuario
function updateUserTasksChart(tareasPendientes, tareasCompletadas, tareasVencidas) {
    if (!window.doughnutChart) {
        console.warn('Doughnut chart not initialized yet');
        return;
    }

    window.doughnutChart.data.datasets[0].data = [
        tareasPendientes,
        tareasCompletadas,
        tareasVencidas
    ];

    window.doughnutChart.update();

    // Actualizar leyenda
    const legendElement = document.getElementById('doughnut-chart-legend');
    if (legendElement) {
        legendElement.innerHTML = window.doughnutChart.generateLegend();
    }

}