/*
 * manager_charts_workload.js grafica de dona para la distribucion de carga de proyectos
 */

function initializeManagerWorkloadChart() {
    console.log('Inicializando gráfica de carga de trabajo...');
    
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    loadWorkloadData(deptId, deptName);
}

function loadWorkloadData(deptId, deptName) {
    fetch(`../php/manager_get_workload.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                renderWorkloadChart(data.data, deptName);
            } else {
                console.warn('Sin datos para gráfica de carga:', data.message);
                showNoDataMessage('workloadChart', `Sin datos - ${deptName}`, 'No hay tareas para mostrar');
            }
        })
        .catch(error => {
            console.error('Error cargando datos de carga:', error);
            showNoDataMessage('workloadChart', 'Error', 'No se pudieron cargar los datos');
        });
}

function renderWorkloadChart(data, deptName) {
    const ctx = document.getElementById('workloadChart');
    
    if (!ctx) {
        console.warn('Canvas workloadChart no encontrado');
        return;
    }
    
    //destruir graficas existentes
    if (managerDashboard.charts.workloadChart) {
        managerDashboard.charts.workloadChart.destroy();
    }
    
    //revisar si existe la info
    if (!data.labels || data.labels.length === 0) {
        showNoDataMessage('workloadChart', `Sin datos - ${deptName}`, 'No hay proyectos con tareas');
        return;
    }
    
    const backgroundColors = data.labels.map((_, index) => getColorByIndex(index, 0.7));
    const borderColors = data.labels.map((_, index) => getColorByIndex(index, 1));
    
    const chartData = {
        labels: data.labels.map(label => shortenTitle(label, 20)),
        datasets: [{
            data: data.data,
            backgroundColor: backgroundColors,
            borderColor: borderColors,
            borderWidth: 2
        }]
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'right',
                labels: {
                    font: { size: 11 },
                    padding: 12,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    generateLabels: function(chart) {
                        const original = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                        return original.map((label, index) => {
                            // Use full project name in legend
                            label.text = shortenTitle(data.labels[index], 25);
                            return label;
                        });
                    }
                }
            },
            title: {
                display: true,
                text: `Distribución de Carga de Trabajo - ${deptName}`,
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
                        // Show full project name
                        const index = context[0].dataIndex;
                        return data.labels[index];
                    },
                    label: function(context) {
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `Tareas: ${value} (${percentage}%)`;
                    },
                    afterLabel: function(context) {
                        const dataIndex = context.dataIndex;
                        if (data.details && data.details[dataIndex]) {
                            const detail = data.details[dataIndex];
                            return [
                                '───────────────',
                                `✓ Completadas: ${detail.completadas || 0}`,
                                `● En proceso: ${detail.en_proceso || 0}`,
                                `○ Pendientes: ${detail.pendientes || 0}`,
                                `⚠ Vencidas: ${detail.vencidas || 0}`
                            ];
                        }
                        return '';
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                titleFont: { size: 13, weight: 'bold' },
                bodyFont: { size: 11 },
                padding: 12,
                displayColors: true
            }
        },
        cutout: '50%'
    };
    
    managerDashboard.charts.workloadChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
    
    addWorkloadSummary(ctx.parentElement, data, deptName);
    
    console.log('Gráfica de carga de trabajo actualizada - Total tareas:', data.total_tareas);
}

function addWorkloadSummary(container, data, deptName) {
    //remover resumen existente
    const existingSummary = container.querySelector('.workload-summary');
    if (existingSummary) {
        existingSummary.remove();
    }
    
    const totalTareas = data.total_tareas || data.data.reduce((a, b) => a + b, 0);
    const totalProyectos = data.labels.length;
    
    //calcular estadisticas generales si no hay info
    let completadas = 0, enProceso = 0, pendientes = 0, vencidas = 0;
    if (data.details) {
        data.details.forEach(d => {
            completadas += d.completadas || 0;
            enProceso += d.en_proceso || 0;
            pendientes += d.pendientes || 0;
            vencidas += d.vencidas || 0;
        });
    }
    
    const summaryDiv = document.createElement('div');
    summaryDiv.className = 'workload-summary';
    summaryDiv.style.cssText = `
        margin-top: 15px;
        padding: 12px 15px;
        background: rgba(34, 139, 89, 0.05);
        border-radius: 6px;
        font-size: 12px;
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        gap: 10px;
    `;
    
    summaryDiv.innerHTML = `
        <div style="text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: ${managerDashboard.colors.primarySolid};">${totalTareas}</div>
            <div style="color: ${managerDashboard.colors.graySolid};">Total Tareas</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: ${managerDashboard.colors.secondarySolid};">${totalProyectos}</div>
            <div style="color: ${managerDashboard.colors.graySolid};">Proyectos</div>
        </div>
        ${data.details ? `
        <div style="text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: ${managerDashboard.statusBorderColors.completado};">${completadas}</div>
            <div style="color: ${managerDashboard.colors.graySolid};">Completadas</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: ${managerDashboard.statusBorderColors.vencido};">${vencidas}</div>
            <div style="color: ${managerDashboard.colors.graySolid};">Vencidas</div>
        </div>
        ` : ''}
    `;
    
    container.appendChild(summaryDiv);
}

function refreshManagerWorkloadChart(deptId, deptName) {
    return new Promise((resolve, reject) => {
        fetch(`../php/manager_get_workload.php?id_departamento=${deptId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderWorkloadChart(data.data, deptName);
                }
                resolve();
            })
            .catch(error => {
                console.error('Error refrescando carga:', error);
                reject(error);
            });
    });
}