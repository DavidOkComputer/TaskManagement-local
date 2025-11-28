/*manager_dashboard_stats.js Carga las estadísticas del dashboard y gráficos para gerentes*/

// Variable global para el gráfico de dona
let doughnutChartInstance = null;

function initializeManagerDashboard() {
    console.log('Iniciando dashboard de gerente...');
    
    // Cargar estadísticas generales
    loadDashboardStats();
    
    // Cargar gráfico de dona (proyectos por estado)
    loadProjectsStatusChart();
}

function loadDashboardStats() {
    console.log('Cargando estadísticas del dashboard...');
    
    fetch('../php/manager_get_dashboard_stats.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('Estadísticas recibidas:', data);
        
        if (data.success && data.stats) {
            updateStatisticsDisplay(data.stats);
        } else {
            console.error('Error al cargar estadísticas:', data.message);
            showErrorInStats();
        }
    })
    .catch(error => {
        console.error('Error al cargar estadísticas:', error);
        showErrorInStats();
    });
}

function updateStatisticsDisplay(stats) {
    // Actualizar total de objetivos
    updateStatElement(0, {
        title: 'Total de objetivos',
        value: stats.objetivos_departamento || 0,
        percentage: null,
        trend: 'neutral'
    });
    
    // Actualizar total de proyectos
    updateStatElement(1, {
        title: 'Total de proyectos',
        value: stats.proyectos_departamento || 0,
        percentage: null,
        trend: 'neutral'
    });
    
    // Actualizar total de tareas
    updateStatElement(2, {
        title: 'Total de Tareas',
        value: stats.tareas_departamento || 0,
        percentage: stats.porcentaje_tareas_completadas ? `${stats.porcentaje_tareas_completadas}% completadas` : null,
        trend: stats.porcentaje_tareas_completadas >= 70 ? 'success' : (stats.porcentaje_tareas_completadas >= 40 ? 'neutral' : 'danger')
    });
    
    // Actualizar proyectos completados
    updateStatElement(3, {
        title: 'Proyectos completados',
        value: stats.proyectos_completados || 0,
        percentage: stats.porcentaje_completados ? `${stats.porcentaje_completados}%` : null,
        trend: stats.porcentaje_completados >= 70 ? 'success' : 'neutral'
    });
    
    // Actualizar proyectos en proceso
    updateStatElement(4, {
        title: 'Proyectos en proceso',
        value: stats.proyectos_en_proceso || 0,
        percentage: stats.progreso_promedio ? `${stats.progreso_promedio}% progreso` : null,
        trend: 'neutral'
    });
    
    // Actualizar proyectos pendientes
    updateStatElement(5, {
        title: 'Proyectos pendientes',
        value: stats.proyectos_pendientes || 0,
        percentage: null,
        trend: 'neutral'
    });
    
    // Actualizar proyectos vencidos
    updateStatElement(6, {
        title: 'Proyectos vencidos',
        value: stats.proyectos_vencidos || 0,
        percentage: null,
        trend: stats.proyectos_vencidos > 0 ? 'danger' : 'success'
    });
    
    console.log('Estadísticas actualizadas exitosamente');
}

function updateStatElement(index, data) {
    const statElements = document.querySelectorAll('.statistics-details > div');
    
    if (statElements[index]) {
        const element = statElements[index];
        
        // Actualizar título
        const titleElement = element.querySelector('.statistics-title');
        if (titleElement) {
            titleElement.textContent = data.title;
        }
        
        // Actualizar valor
        const valueElement = element.querySelector('.rate-percentage');
        if (valueElement) {
            valueElement.textContent = data.value;
        }
        
        // Actualizar porcentaje/descripción
        const percentageElement = element.querySelector('p:last-child');
        if (percentageElement && data.percentage) {
            // Limpiar clases anteriores
            percentageElement.className = 'd-flex';
            
            // Agregar clase según tendencia
            if (data.trend === 'success') {
                percentageElement.classList.add('text-success');
                percentageElement.innerHTML = `<i class="mdi mdi-menu-up"></i><span>${data.percentage}</span>`;
            } else if (data.trend === 'danger') {
                percentageElement.classList.add('text-danger');
                percentageElement.innerHTML = `<i class="mdi mdi-menu-down"></i><span>${data.percentage}</span>`;
            } else {
                percentageElement.classList.add('text-muted');
                percentageElement.innerHTML = `<i class="mdi mdi-minus"></i><span>${data.percentage || 'Sin cambios'}</span>`;
            }
        } else if (percentageElement && !data.percentage) {
            percentageElement.innerHTML = '';
        }
    }
}

function showErrorInStats() {
    const statElements = document.querySelectorAll('.statistics-details > div');
    statElements.forEach((element, index) => {
        const valueElement = element.querySelector('.rate-percentage');
        if (valueElement) {
            valueElement.textContent = '--';
        }
        
        const percentageElement = element.querySelector('p:last-child');
        if (percentageElement) {
            percentageElement.className = 'text-muted d-flex';
            percentageElement.innerHTML = '<i class="mdi mdi-alert-circle"></i><span>Error al cargar</span>';
        }
    });
}

function loadProjectsStatusChart() {
    console.log('Cargando gráfico de proyectos por estado...');
    
    fetch('../php/manager_get_dashboard_stats.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos para gráfico recibidos:', data);
        
        if (data.success && data.stats) {
            createDoughnutChart(data.stats);
        } else {
            console.error('Error al cargar datos del gráfico:', data.message);
            showChartError();
        }
    })
    .catch(error => {
        console.error('Error al cargar gráfico:', error);
        showChartError();
    });
}

function createDoughnutChart(stats) {
    const canvas = document.getElementById('doughnutChart');
    
    if (!canvas) {
        console.error('Canvas doughnutChart no encontrado');
        return;
    }
    
    // Destruir gráfico anterior si existe
    if (doughnutChartInstance) {
        doughnutChartInstance.destroy();
        doughnutChartInstance = null;
    }
    
    const ctx = canvas.getContext('2d');
    
    // Preparar datos
    const chartData = {
        labels: ['Completados', 'En Proceso', 'Pendientes', 'Vencidos'],
        datasets: [{
            data: [
                stats.proyectos_completados || 0,
                stats.proyectos_en_proceso || 0,
                stats.proyectos_pendientes || 0,
                stats.proyectos_vencidos || 0
            ],
            backgroundColor: [
                '#009b4a', // Verde para completados
                '#666666', // Azul para en proceso
                '#e9e9e9', // Amarillo para pendientes
                '#000000'  // Rojo para vencidos
            ],
            borderColor: [
                '#009b4a', // Verde para completados
                '#666666', // Azul para en proceso
                '#e9e9e9', // Amarillo para pendientes
                '#000000'  // Rojo para vencidos
            ],
            borderWidth: 2
        }]
    };
    
    // Configuración del gráfico
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '70%',
        plugins: {
            legend: {
                display: false // Usaremos leyenda personalizada
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
                }
            }
        }
    };
    
    // Crear gráfico
    doughnutChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
    
    // Crear leyenda personalizada
    createCustomLegend(chartData, stats.proyectos_departamento || 0);
    
    console.log('Gráfico de dona creado exitosamente');
}

/**
 * Crea una leyenda personalizada para el gráfico de dona
 */
function createCustomLegend(chartData, total) {
    const legendContainer = document.getElementById('doughnut-chart-legend');
    
    if (!legendContainer) {
        console.error('Contenedor de leyenda no encontrado');
        return;
    }
    
    let legendHtml = '<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px;">';
    
    chartData.labels.forEach((label, index) => {
        const value = chartData.datasets[0].data[index];
        const color = chartData.datasets[0].backgroundColor[index];
        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
        
        legendHtml += `
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="display: inline-block; width: 12px; height: 12px; background-color: ${color}; border-radius: 2px;"></span>
                <span style="font-size: 12px; color: #6c757d;">
                    <strong>${label}:</strong> ${value} (${percentage}%)
                </span>
            </div>
        `;
    });
    
    legendHtml += '</div>';
    legendContainer.innerHTML = legendHtml;
}

function showChartError() {
    const canvas = document.getElementById('doughnutChart');
    const legendContainer = document.getElementById('doughnut-chart-legend');
    
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#000000';
        ctx.font = '14px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Error al cargar gráfico', canvas.width / 2, canvas.height / 2);
    }
    
    if (legendContainer) {
        legendContainer.innerHTML = '<p class="text-danger text-center">Error al cargar datos</p>';
    }
}

// Inicializar cuando el documento esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeManagerDashboard);
} else {
    initializeManagerDashboard();
}
