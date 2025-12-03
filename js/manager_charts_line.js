/*manager_charts_line.js grafica lineal para ver el progreso de los proyectos sobre el tiempo*/

function initializeManagerLineChart() {
    console.log('Inicializando gráfica de línea (tendencia de proyectos)...');
    
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    loadLineData(deptId, deptName);
}

function loadLineData(deptId, deptName) {
    fetch(`../php/manager_get_project_trends.php?id_departamento=${deptId}&weeks=12`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            if (!text || text.trim() === '') {
                throw new Error('Respuesta vacía del servidor');
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error parseando JSON:', text.substring(0, 200));
                throw new Error('Respuesta inválida del servidor');
            }
        })
        .then(data => {
            if (data.success && data.data) {
                renderLineChart(data.data, deptName);
            } else {
                console.warn('Sin datos para gráfica de línea:', data.message);
                showNoDataMessage('lineChart', `Sin datos - ${deptName}`, 'No hay tendencias para mostrar');
            }
        })
        .catch(error => {
            console.error('Error cargando datos de línea:', error);
            showNoDataMessage('lineChart', 'Error', 'No se pudieron cargar los datos');
        });
}

function renderLineChart(data, deptName) {
    const ctx = document.getElementById('lineChart');
    
    if (!ctx) {
        console.warn('Canvas lineChart no encontrado');
        return;
    }
    
    //destruir graficas existentes
    if (managerDashboard.charts.lineChart) {
        managerDashboard.charts.lineChart.destroy();
    }
    
    //revisar si hay informacion
    if (!data.labels || data.labels.length === 0) {
        showNoDataMessage('lineChart', `Sin datos - ${deptName}`, 'No hay datos de tendencias');
        return;
    }
    
    const chartData = {
        labels: data.labels,
        datasets: data.datasets.map((dataset, index) => ({
            ...dataset,
            borderColor: managerDashboard.colors.primarySolid,
            backgroundColor: managerDashboard.colors.primary,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: managerDashboard.colors.primarySolid,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }))
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
                    text: 'Proyectos Completados (Acumulativo)',
                    font: { size: 12 }
                },
                grid: {
                    color: 'rgba(200, 205, 210, 0.3)'
                }
            },
            x: {
                ticks: {
                    font: { size: 10 },
                    maxRotation: 45,
                    minRotation: 0
                },
                title: {
                    display: true,
                    text: 'Semanas',
                    font: { size: 12 }
                },
                grid: {
                    display: false
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
                text: `Tendencia de Proyectos Completados - ${deptName}`,
                font: {
                    size: 14,
                    weight: 'bold'
                },
                padding: 15,
                color: managerDashboard.colors.primarySolid
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const value = context.parsed.y || 0;
                        return `${label}: ${value} proyecto${value !== 1 ? 's' : ''}`;
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 12 },
                bodyFont: { size: 11 },
                padding: 10
            },
            filler: {
                propagate: true
            }
        }
    };
    
    managerDashboard.charts.lineChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: options
    });
    
    console.log('Gráfica de línea actualizada');
}

function refreshManagerLineChart(deptId, deptName) {
    return new Promise((resolve, reject) => {
        fetch(`../php/manager_get_project_trends.php?id_departamento=${deptId}&weeks=12`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderLineChart(data.data, deptName);
                }
                resolve();
            })
            .catch(error => {
                console.error('Error refrescando línea:', error);
                reject(error);
            });
    });
}