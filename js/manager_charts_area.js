/*manager_charts_area.js  grafica de area para las tareas completadas */

function initializeManagerAreaChart() {
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    loadAreaData(deptId, deptName);
}

function loadAreaData(deptId, deptName) {
    fetch(`../php/manager_get_task_trends.php?id_departamento=${deptId}&weeks=12`)
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
                renderAreaChart(data.data, deptName);
            } else {
                console.warn('Sin datos para gráfica de área:', data.message);
                showNoDataMessage('areaChart', `Sin datos - ${deptName}`, 'No hay tendencias de tareas');
            }
        })
        .catch(error => {
            console.error('Error cargando datos de área:', error);
            showNoDataMessage('areaChart', 'Error', 'No se pudieron cargar los datos');
        });
}

function renderAreaChart(data, deptName) {
    const ctx = document.getElementById('areaChart');
    
    if (!ctx) {
        console.warn('Canvas areaChart no encontrado');
        return;
    }
    
    //destruir graficas existentes
    if (managerDashboard.charts.areaChart) {
        managerDashboard.charts.areaChart.destroy();
    }
    
    //revisar si hay informacion
    if (!data.labels || data.labels.length === 0) {
        showNoDataMessage('areaChart', `Sin datos - ${deptName}`, 'No hay datos de tendencias de tareas');
        return;
    }
    
    //darle estilo a cada set de informacion 
    const styledDatasets = data.datasets.map((dataset, index) => ({
        ...dataset,
        borderColor: getColorByIndex(index, 1),
        backgroundColor: getColorByIndex(index, 0.3),
        fill: true,
        tension: 0.4,
        pointBackgroundColor: getColorByIndex(index, 1),
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5
    }));
    
    const chartData = {
        labels: data.labels,
        datasets: styledDatasets
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
                stacked: false,
                ticks: {
                    stepSize: 1,
                    font: { size: 11 }
                },
                title: {
                    display: true,
                    text: 'Tareas Completadas (Acumulativo)',
                    font: { size: 12 }
                },
                grid: {
                    color: 'rgba(200, 205, 210, 0.3)'
                }
            },
            x: {
                stacked: false,
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
                text: `Avances de Tareas por Periodo - ${deptName}`,
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
                        return `${label}: ${value} tarea${value !== 1 ? 's' : ''}`;
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 12 },
                bodyFont: { size: 11 },
                padding: 10
            }
        }
    };
    
    managerDashboard.charts.areaChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: options
    });
    
}

function refreshManagerAreaChart(deptId, deptName) {
    return new Promise((resolve, reject) => {
        fetch(`../php/manager_get_task_trends.php?id_departamento=${deptId}&weeks=12`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderAreaChart(data.data, deptName);
                }
                resolve();
            })
            .catch(error => {
                console.error('Error refrescando área:', error);
                reject(error);
            });
    });
}