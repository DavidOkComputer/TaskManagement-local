/*manager_charts_bar.js grafica de barras para el progreso de los proyectos del departamento*/

function initializeManagerBarChart() {
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    loadBarData(deptId, deptName);
}

function loadBarData(deptId, deptName) {
    fetch(`../php/manager_get_projects.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.proyectos && data.proyectos.length > 0) {
                renderBarChart(data.proyectos, deptName);
            } else {
                console.warn('Sin proyectos para gráfica de barras:', data.message);
                showNoDataMessage('barChart', `Sin datos - ${deptName}`, 'No hay proyectos para mostrar');
            }
        })
        .catch(error => {
            console.error('Error cargando datos de barras:', error);
            showNoDataMessage('barChart', 'Error', 'No se pudieron cargar los datos');
        });
}

function renderBarChart(projects, deptName) {
    const ctx = document.getElementById('barChart');
    
    if (!ctx) {
        console.warn('Canvas barChart no encontrado');
        return;
    }
    
    //destruir graficas existentes
    if (managerDashboard.charts.barChart) {
        managerDashboard.charts.barChart.destroy();
    }
    
    //ordenar por progreso y tomar los top 8 proyectos
    const sortedProjects = [...projects]
        .sort((a, b) => b.progreso - a.progreso)
        .slice(0, 8);
    
    if (sortedProjects.length === 0) {
        showNoDataMessage('barChart', `Sin proyectos - ${deptName}`, 'No hay proyectos en este departamento');
        return;
    }
    
    //preparar datos
    const labels = sortedProjects.map(p => shortenTitle(p.nombre, 20));
    const progressData = sortedProjects.map(p => p.progreso);
    const backgroundColors = sortedProjects.map(p => getProgressColor(p.progreso));
    const borderColors = backgroundColors.map(c => c.replace('0.7', '1'));
    
    const chartData = {
        labels: labels,
        datasets: [{
            label: 'Progreso (%)',
            data: progressData,
            backgroundColor: backgroundColors,
            borderColor: borderColors,
            borderWidth: 1,
            borderRadius: 4
        }]
    };
    
    const options = {
        indexAxis: 'y', //grafica de barras horizontal
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            x: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    font: { size: 11 },
                    callback: function(value) {
                        return value + '%';
                    }
                },
                title: {
                    display: true,
                    text: 'Porcentaje de Avance',
                    font: { size: 12 }
                },
                grid: {
                    color: 'rgba(200, 205, 210, 0.3)'
                }
            },
            y: {
                ticks: {
                    font: { size: 11 }
                },
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: {
                display: false //ocultar pie de grafica
            },
            title: {
                display: true,
                text: `Progreso de Proyectos - ${deptName}`,
                font: {
                    size: 14,
                    weight: 'bold'
                },
                padding: 15,
                color: managerDashboard.colors.primarySolid
            },
            tooltip: {
                callbacks: {
                    title: function(context) {
                        //mostrar el nombre completo del proyecto al pasar encima
                        const index = context[0].dataIndex;
                        return sortedProjects[index].nombre;
                    },
                    label: function(context) {
                        const progress = context.parsed.x;
                        const project = sortedProjects[context.dataIndex];
                        return [
                            `Progreso: ${progress}%`,
                            `Estado: ${project.estado}`
                        ];
                    },
                    afterLabel: function(context) {
                        const project = sortedProjects[context.dataIndex];
                        if (project.fecha_cumplimiento) {
                            const fecha = new Date(project.fecha_cumplimiento);
                            return `Fecha límite: ${fecha.toLocaleDateString('es-MX')}`;
                        }
                        return '';
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 12, weight: 'bold' },
                bodyFont: { size: 11 },
                padding: 12
            }
        }
    };
    
    managerDashboard.charts.barChart = new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: options
    });
}

function refreshManagerBarChart(deptId, deptName) {
    return new Promise((resolve, reject) => {
        fetch(`../php/manager_get_projects.php?id_departamento=${deptId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.proyectos && data.proyectos.length > 0) {
                    renderBarChart(data.proyectos, deptName);
                }
                resolve();
            })
            .catch(error => {
                console.error('Error refrescando barras:', error);
                reject(error);
            });
    });
}