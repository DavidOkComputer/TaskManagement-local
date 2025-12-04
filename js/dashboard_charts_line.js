/**
 * dashboard_charts_line.js
 * Project completion trends over time (line chart)
 * Admin only - Comparison view vs Department view
 */

/**
 * Initialize line chart
 * Starts with comparison view (all departments)
 */
function initializeLineChart() {
    console.log('Inicializando gráfico de línea de tendencias...');
    
    const currentDept = dashboardChartsInstance.currentDepartment;
    
    if (currentDept && currentDept.id && currentDept.id > 0) {
        // Department is selected - show that department's trend
        console.log('Loading project trend for department:', currentDept.name);
        loadProjectTrendForDepartment(currentDept.id, currentDept.name);
    } else {
        // No department selected - show comparison
        console.log('Loading project trend comparison (all departments)');
        loadProjectTrendComparison();
    }
}

/**
 * Load project trend for a specific department
 */
function loadProjectTrendForDepartment(deptId, deptName) {
    console.log(`Cargando tendencia de proyectos para: ${deptName}`);
    
    fetch(`../php/get_project_trends.php?id_departamento=${deptId}&weeks=12`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for department:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }

            try {
                const data = JSON.parse(text);
                console.log('Datos de tendencia cargados:', data);
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

/**
 * Load project trend comparison (all departments)
 */
function loadProjectTrendComparison() {
    console.log('Cargando vista de comparación de tendencias (todos los departamentos)');
    
    fetch('../php/get_project_trends.php?weeks=12')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response.');
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
                console.log('Datos de comparación cargados:', data);
                updateLineChart(data, 'comparison');
            } else {
                console.error('Error en datos de comparación:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error loading comparison trends:', error.message);
        });
}

/**
 * Update line chart with data
 */
function updateLineChart(data, mode, deptName = null) {
    const ctx = document.getElementById('lineChart');
    if (!ctx) {
        console.warn('Line chart canvas not found');
        return;
    }

    // Destroy existing chart
    if (dashboardChartsInstance.charts.lineChart) {
        dashboardChartsInstance.charts.lineChart.destroy();
    }

    // Prepare chart title
    let chartTitle = 'Tendencia de Proyectos Completados';
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
    
    console.log('Gráfico de línea actualizado: ' + chartTitle);
}