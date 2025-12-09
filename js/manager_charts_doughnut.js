/*manager_charts_doughnut.js grafica de dona para distribucion de estatus de proyectos*/

function initializeManagerDoughnutChart() {
    
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    loadDoughnutData(deptId, deptName);
}

function loadDoughnutData(deptId, deptName) {
    fetch(`../php/manager_get_project_status.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                renderDoughnutChart(data.data, deptName);
            } else {
                console.warn('Sin datos para gráfica de dona:', data.message);
                showNoDataMessage('doughnutChart', `Sin datos - ${deptName}`, 'No hay proyectos para mostrar');
            }
        })
        .catch(error => {
            console.error('Error cargando datos de dona:', error);
            showNoDataMessage('doughnutChart', 'Error', 'No se pudieron cargar los datos');
        });
}

function renderDoughnutChart(data, deptName) {
    const ctx = document.getElementById('doughnutChart');
    
    if (!ctx) {
        console.warn('Canvas doughnutChart no encontrado');
        return;
    }
    
    //destruir grafica existente
    if (managerDashboard.charts.doughnutChart) {
        managerDashboard.charts.doughnutChart.destroy();
    }
    
    //revisar si hay datos existentes
    const totalProjects = data.data.reduce((a, b) => a + b, 0);
    if (totalProjects === 0) {
        showNoDataMessage('doughnutChart', `Sin proyectos - ${deptName}`, 'No hay proyectos en este departamento');
        return;
    }
    
    const chartData = {
        labels: data.labels,
        datasets: [{
            data: data.data,
            backgroundColor: [
                managerDashboard.statusColors.completado,
                managerDashboard.statusColors['en proceso'],
                managerDashboard.statusColors.pendiente,
                managerDashboard.statusColors.vencido
            ],
            borderColor: [
                managerDashboard.statusBorderColors.completado,
                managerDashboard.statusBorderColors['en proceso'],
                managerDashboard.statusBorderColors.pendiente,
                managerDashboard.statusBorderColors.vencido
            ],
            borderWidth: 2
        }]
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    font: { size: 12 },
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            title: {
                display: true,
                text: `Proyectos por Estado - ${deptName}`,
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
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value} (${percentage}%)`;
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 12 },
                bodyFont: { size: 11 },
                padding: 12
            }
        },
        cutout: '60%' 
    };
    
    managerDashboard.charts.doughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
}

function refreshManagerDoughnutChart(deptId, deptName) {
    return new Promise((resolve, reject) => {
        fetch(`../php/manager_get_project_status.php?id_departamento=${deptId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderDoughnutChart(data.data, deptName);
                }
                resolve();
            })
            .catch(error => {
                console.error('Error refrescando dona:', error);
                reject(error);
            });
    });
}