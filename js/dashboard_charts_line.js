/*dashboard_charts_line.js completacion de prouyectos sobre el tiempo, para admin*/

function initializeLineChart() {
    const currentDept = dashboardChartsInstance.currentDepartment;
    
    if (currentDept && currentDept.id && currentDept.id > 0) {
        //el departamento es seleccionado mostrar info del depar
        loadProjectTrendForDepartment(currentDept.id, currentDept.name);
    } else {
        //sino hoay departamento seleccionado mostrar el modo de comparacin
        loadProjectTrendComparison();
    }
}

function loadProjectTrendForDepartment(deptId, deptName) {
    fetch(`../php/get_project_trends.php?id_departamento=${deptId}&weeks=12`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }

            try {
                const data = JSON.parse(text);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                updateLineChart(data, 'single', deptName);
            } else {
                console.warn('Error en datos de tendencia:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading project trends:', error.message);
            console.error('Department:', deptName, 'ID:', deptId);
        });
}

function loadProjectTrendComparison() {
    fetch('../php/get_project_trends.php?weeks=12')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response.');
            }

            try {
                const data = JSON.parse(text);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid JSON from API. Check PHP file for errors.');
            }
        })
        .then(data => {
            if (data.success && data.data) {
                updateLineChart(data, 'comparison');
            } else {
                console.error('Error en datos de comparación:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error loading comparison trends:', error.message);
        });
}

function updateLineChart(data, mode, deptName = null) {
    const ctx = document.getElementById('lineChart');
    if (!ctx) {
        console.warn('Line chart canvas not found');
        return;
    }

    if (dashboardChartsInstance.charts.lineChart) {//destruir graficas existentes
        dashboardChartsInstance.charts.lineChart.destroy();
    }

    let chartTitle = 'Tendencia de Proyectos Completados';//preparar titulo del graficos
    if (mode === 'single' && deptName) {
        chartTitle = `Tendencia - ${deptName}`;
    } else if (mode === 'comparison') {
        chartTitle = 'Comparación de Tendencias - Todos los Departamentos';
    }

    const chartData = {
        labels: data.data.labels,
        datasets: data.data.datasets
    };

    const options = {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: { size: 11 }
                },
                title: {
                    display: true,
                    text: 'Proyectos Completados (Acumulativo)'
                }
            },
            x: {
                ticks: {
                    font: { size: 10 }
                },
                title: {
                    display: true,
                    text: 'Semanas'
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    font: { size: 12 },
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            title: {
                display: true,
                text: chartTitle,
                font: { size: 14, weight: 'bold' },
                padding: 15
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const value = context.parsed.y || 0;
                        return label + ': ' + value + ' proyectos';
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 12 },
                bodyFont: { size: 11 },
                padding: 10,
                displayColors: true
            }
        }
    };

    dashboardChartsInstance.charts.lineChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: options
    });
}