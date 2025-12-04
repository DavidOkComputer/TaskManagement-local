/*dashboard_charts_area.js tendencias de completacion de tareas, usado por el grafico de area*/

function initializeAreaChart() {
    console.log('Inicializando gráfico de área de tendencias de tareas...');
    
    const currentDept = dashboardChartsInstance.currentDepartment;
    
    if (currentDept && currentDept.id && currentDept.id > 0) {
        //si el departamento es seleccionado mostrar info del depa
        console.log('Cargando tendencias de tareas para el departamento:', currentDept.name);
        loadTaskTrendForDepartment(currentDept.id, currentDept.name);
    } else {
        //sino se selecciona un departamento mostrar el modo de comparacion
        console.log('Cargando tendencias de tareas para todos los departamentos');
        loadTaskTrendComparison();
    }
}

function loadTaskTrendForDepartment(deptId, deptName) {
    console.log(`Cargando tendencia de tareas para: ${deptName}`);

    fetch(`../php/get_task_trends.php?id_departamento=${deptId}&weeks=12`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for department area chart:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }

            try {
                const data = JSON.parse(text);
                console.log('Datos de tendencia de tareas cargados:', data);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                updateAreaChart(data, 'single', deptName);
            } else {
                console.warn('Error en datos de tendencia de tareas:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading task trends:', error.message);
            console.error('Department:', deptName, 'ID:', deptId);
        });
}

function loadTaskTrendComparison() {
    console.log('Cargando vista de comparación de tendencias de tareas (todos los departamentos)');

    fetch('../php/get_task_trends.php?weeks=12')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for comparison area chart:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }

            try {
                const data = JSON.parse(text);
                console.log('JSON parsed successfully:', data);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid JSON from API. Check PHP file for errors.');
            }
        })
        .then(data => {
            if (data.success && data.data) {
                console.log('Datos de comparación de tareas cargados:', data);
                updateAreaChart(data, 'comparison');
            } else {
                console.error('Error en datos de comparación:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error al cargar el modo de comparacion:', error.message);
        });
}

function updateAreaChart(data, mode, deptName = null) {
    const ctx = document.getElementById('areaChart');
    if (!ctx) {
        console.warn('El canvas para el grafico de area no se encontro');
        return;
    }

    //destruir graficas existentes
    if (dashboardChartsInstance.charts.areaChart) {
        dashboardChartsInstance.charts.areaChart.destroy();
    }

    //preparar titulo del grafico
    let chartTitle = 'Tendencia de Tareas Completadas';
    if (mode === 'single' && deptName) {
        chartTitle = `Tareas Completadas - ${deptName}`;
    } else if (mode === 'comparison') {
        chartTitle = 'Comparación de Tareas Completadas - Todos los Departamentos';
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
                stacked: mode === 'comparison', //stack para modo de compracaion
                ticks: {
                    stepSize: mode === 'single' ? 1 : undefined,
                    font: { size: 11 }
                },
                title: {
                    display: true,
                    text: mode === 'single' ? 'Tareas Completadas (Acumulativo)' : 'Tareas Completadas'
                }
            },
            x: {
                stacked: mode === 'comparison', //stack para el modo de comparacion
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
            filler: {
                propagate: true
            },
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
                        return label + ': ' + value + ' tareas';
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

    dashboardChartsInstance.charts.areaChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: options
    });

    console.log('Gráfico de área actualizado: ' + chartTitle);
}