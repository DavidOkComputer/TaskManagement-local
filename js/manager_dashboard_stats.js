/**  manager_dashboard_stats.js estadísticas del dashboard y gráficos para gerentes */ 

const DashboardRefreshConfig = { 
    STATS_INTERVAL: 30000,      // 30 segundos para estadísticas 
    CHART_INTERVAL: 60000,      // 60 segundos para gráficos 
    TABLES_INTERVAL: 45000      // 45 segundos para tablas 

}; 

// Variables globales 
let statsRefreshInterval = null; 
let chartRefreshInterval = null; 
let tablesRefreshInterval = null; 
let isAutoRefreshActive = true; 
let lastUpdateTimestamp = null; 
let isManagerDashboardInitialized = false; 

function initializeManagerDashboard() { 
    // Prevenir doble inicialización 
    if (isManagerDashboardInitialized) { 
        console.log('Dashboard ya inicializado, saltando...'); 
        return; 
    } 

    isManagerDashboardInitialized = true; 
    console.log('Iniciando dashboard de gerente con auto-actualización...'); 

    // Cargar estadísticas inmediatamente 
    loadDashboardStats(); 
    // Esperar a que dashboard.js termine de crear el gráfico, luego actualizar datos 
    setTimeout(() => { 
        loadDoughnutChartData(); 
    }, 500); 

    // Iniciar auto-refresh 
    startAutoRefresh();
    // Crear indicador de última actualización 
    createUpdateIndicator(); 
    // Detectar visibilidad de la página 
    setupVisibilityDetection(); 
} 

function startAutoRefresh() { 
    stopAutoRefresh(); // Limpiar intervalos existentes 

    // Intervalo para estadísticas 
    statsRefreshInterval = setInterval(() => { 
        if (isAutoRefreshActive) { 
            console.log('Auto-refresh: Actualizando estadísticas...'); 
            loadDashboardStats(); 
            updateTimestamp(); 
        } 
    }, DashboardRefreshConfig.STATS_INTERVAL); 

    // Intervalo para gráfico de dona 
    chartRefreshInterval = setInterval(() => { 
        if (isAutoRefreshActive) { 
            console.log('Auto-refresh: Actualizando gráfico...'); 
            loadDoughnutChartData(); 
        } 
    }, DashboardRefreshConfig.CHART_INTERVAL); 

    // Intervalo para tablas (empleados y proyectos top) 
    tablesRefreshInterval = setInterval(() => { 
        if (isAutoRefreshActive) {
            console.log('Auto-refresh: Actualizando tablas...'); 
            if (typeof loadTopEmployeesProgress === 'function') { 
                loadTopEmployeesProgress(); 
            } 

            if (typeof loadTopProjectsProgress === 'function') { 
                loadTopProjectsProgress(); 
            } 
        } 
    }, DashboardRefreshConfig.TABLES_INTERVAL);
    console.log('Auto-refresh iniciado: Stats cada ' + (DashboardRefreshConfig.STATS_INTERVAL/1000) + 's, Charts cada ' + (DashboardRefreshConfig.CHART_INTERVAL/1000) + 's'); 
} 

function stopAutoRefresh() { 
    if (statsRefreshInterval) { 
        clearInterval(statsRefreshInterval); 
        statsRefreshInterval = null; 
    } 

    if (chartRefreshInterval) { 
        clearInterval(chartRefreshInterval); 
        chartRefreshInterval = null; 
    } 

    if (tablesRefreshInterval) { 
        clearInterval(tablesRefreshInterval); 
        tablesRefreshInterval = null; 
    } 
    console.log('Auto-refresh detenido'); 
} 

function toggleAutoRefresh() { 
    isAutoRefreshActive = !isAutoRefreshActive; 

    var toggleBtn = document.getElementById('toggleAutoRefreshBtn'); 
    var statusText = document.getElementById('autoRefreshStatus'); 

    if (isAutoRefreshActive) { 
        if (toggleBtn) { 
            toggleBtn.innerHTML = '<i class="mdi mdi-pause"></i>'; 
            toggleBtn.classList.remove('btn-success');
            toggleBtn.classList.add('btn-outline-secondary'); 
        } 

        if (statusText) {
            statusText.innerHTML = '<i class="mdi mdi-sync me-1"></i>Auto-refresh activo'; 
            statusText.classList.remove('text-warning'); 
            statusText.classList.add('text-muted'); 
        } 

        refreshAllData(); 
        console.log('Auto-refresh reanudado'); 
    } else { 
        if (toggleBtn) { 
            toggleBtn.innerHTML = '<i class="mdi mdi-play"></i>'; 
            toggleBtn.classList.remove('btn-outline-secondary'); 
            toggleBtn.classList.add('btn-success'); 
        } 

        if (statusText) {
            statusText.innerHTML = '<i class="mdi mdi-sync-off me-1"></i>Auto-refresh pausado'; 
            statusText.classList.remove('text-muted'); 
            statusText.classList.add('text-warning'); 
        } 

        console.log('Auto-refresh pausado'); 
    } 
    return isAutoRefreshActive; 
} 

function refreshAllData() { 
    showRefreshIndicator(); 
    loadDashboardStats(); 
    loadDoughnutChartData(); 

    if (typeof loadTopEmployeesProgress === 'function') { 
        loadTopEmployeesProgress(); 
    } 

    if (typeof loadTopProjectsProgress === 'function') { 
        loadTopProjectsProgress(); 
    } 

    if (typeof refreshProjectsData === 'function') { 
        refreshProjectsData(); 
    } 
    updateTimestamp(); 
} 

function createUpdateIndicator() { 
    var statsContainer = document.querySelector('.statistics-details'); 

    if (statsContainer && !document.getElementById('lastUpdateContainer')) { 
        var indicator = document.createElement('div'); 
        indicator.id = 'lastUpdateContainer'; 
        indicator.className = 'd-none d-md-block'; 
        indicator.innerHTML =  
            '<p class="statistics-title">Última actualización</p>' + 
            '<h3 class="rate-percentage" id="lastUpdateTime">--:--:--</h3>' + 
            '<p class="text-muted d-flex">' + 
                '<i class="mdi mdi-timer-sand me-1"></i>' + 
                '<span>Cada ' + (DashboardRefreshConfig.STATS_INTERVAL/1000) + 's</span>' + 
            '</p>'; 
        statsContainer.appendChild(indicator); 
    } 

    // Crear indicador flotante de refresh 
    if (!document.getElementById('refreshIndicator')) { 
        var floatingIndicator = document.createElement('div'); 
        floatingIndicator.id = 'refreshIndicator'; 
        floatingIndicator.className = 'refresh-indicator'; 
        floatingIndicator.innerHTML =  
            '<i class="mdi mdi-refresh mdi-spin"></i>' + 
            '<span>Actualizando...</span>'; 
        document.body.appendChild(floatingIndicator); 
         
        // Agregar estilos 
        if (!document.getElementById('refreshIndicatorStyles')) { 
            var style = document.createElement('style'); 
            style.id = 'refreshIndicatorStyles'; 
            style.textContent =  
                '.refresh-indicator {' + 
                    'position: fixed;' + 
                    'bottom: 20px;' + 
                    'right: 20px;' + 
                    'background: #009B4A;' + 
                    'color: white;' + 
                    'padding: 12px 18px;' + 
                    'border-radius: 8px;' + 
                    'font-size: 13px;' + 
                    'z-index: 9999;' + 
                    'display: none;' + 
                    'align-items: center;' + 
                    'gap: 10px;' + 
                    'box-shadow: 0 4px 12px rgba(0,0,0,0.15);' + 
                '}' + 
                '.refresh-indicator.show {' + 
                    'display: flex;' + 
                '}' + 
                '.mdi-spin {' + 
                    'animation: spin 1s linear infinite;' + 
                '}' + 
                '@keyframes spin {' + 
                    'from { transform: rotate(0deg); }' + 
                    'to { transform: rotate(360deg); }' + 
                '}' + 
                '.stat-updating {' + 
                    'animation: pulse 0.5s ease;' + 
                '}' + 
                '@keyframes pulse {' + 
                    '0% { transform: scale(1); opacity: 1; }' + 
                    '50% { transform: scale(1.05); opacity: 0.8; }' + 
                    '100% { transform: scale(1); opacity: 1; }' + 
                '}'; 
            document.head.appendChild(style); 
        } 
    } 
} 

function showRefreshIndicator() { 
    var indicator = document.getElementById('refreshIndicator'); 
    if (indicator) { 
        indicator.classList.add('show'); 
        setTimeout(function() { 
            indicator.classList.remove('show'); 
        }, 1500); 
    } 
} 

function updateTimestamp() { 
    lastUpdateTimestamp = new Date(); 
    var timeEl = document.getElementById('lastUpdateTime'); 

    if (timeEl) { 
        var timeStr = lastUpdateTimestamp.toLocaleTimeString('es-MX', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        }); 

        timeEl.textContent = timeStr; 
        timeEl.classList.add('stat-updating'); 
        setTimeout(function() { 
            timeEl.classList.remove('stat-updating'); 
        }, 500); 
    } 
} 

function setupVisibilityDetection() { 
    document.addEventListener('visibilitychange', function() { 
        if (document.hidden) { 
            console.log('Página oculta - pausando auto-refresh'); 
            stopAutoRefresh(); 
        } else { 
            console.log('Página visible - reanudando auto-refresh'); 
            if (isAutoRefreshActive) { 
                refreshAllData(); 
                startAutoRefresh(); 
            } 
        } 
    }); 

    window.addEventListener('beforeunload', function() { 
        stopAutoRefresh(); 
    }); 
} 

function loadDashboardStats() { 

    console.log('Cargando estadísticas del dashboard...'); 

    fetch('../php/manager_get_dashboard_stats.php', { 
        method: 'GET', 
        headers: { 
            'Content-Type': 'application/json' 
        } 
    }) 

    .then(function(response) { 
        if (!response.ok) { 
            throw new Error('Error en la respuesta del servidor'); 
        } 
        return response.json(); 
    }) 

    .then(function(data) { 
        console.log('Estadísticas recibidas:', data); 
        if (data.success && data.stats) { 
            updateStatisticsDisplay(data.stats); 
        } else { 
            console.error('Error al cargar estadísticas:', data.message); 
            showErrorInStats(); 
        } 
    }) 

    .catch(function(error) { 
        console.error('Error al cargar estadísticas:', error); 
        showErrorInStats(); 
    }); 
} 

function updateStatisticsDisplay(stats) { 
    updateStatElement(0, { 
        title: 'Total de objetivos', 
        value: stats.objetivos_departamento || 0, 
        percentage: null, 
        trend: 'neutral' 
    }); 

    updateStatElement(1, { 
        title: 'Total de proyectos', 
        value: stats.proyectos_departamento || 0, 
        percentage: null, 
        trend: 'neutral' 
    }); 

    updateStatElement(2, { 
        title: 'Total de Tareas', 
        value: stats.tareas_departamento || 0, 
        percentage: stats.porcentaje_tareas_completadas ? stats.porcentaje_tareas_completadas + '% completadas' : null, 
        trend: stats.porcentaje_tareas_completadas >= 70 ? 'success' :  
               (stats.porcentaje_tareas_completadas >= 40 ? 'neutral' : 'danger') 
    }); 

    updateStatElement(3, { 
        title: 'Proyectos completados', 
        value: stats.proyectos_completados || 0, 
        percentage: stats.porcentaje_completados ? stats.porcentaje_completados + '%' : null, 
        trend: stats.porcentaje_completados >= 70 ? 'success' : 'neutral' 
    }); 

    updateStatElement(4, { 
        title: 'Proyectos en proceso', 
        value: stats.proyectos_en_proceso || 0, 
        percentage: stats.progreso_promedio ? stats.progreso_promedio + '% progreso' : null, 
        trend: 'neutral' 
    }); 

    updateStatElement(5, { 
        title: 'Proyectos pendientes', 
        value: stats.proyectos_pendientes || 0, 
        percentage: null, 
        trend: 'neutral' 
    }); 

    updateStatElement(6, { 
        title: 'Proyectos vencidos', 
        value: stats.proyectos_vencidos || 0, 
        percentage: null, 
        trend: stats.proyectos_vencidos > 0 ? 'danger' : 'success' 
    }); 

    console.log('Estadísticas actualizadas exitosamente'); 
} 

function updateStatElement(index, data) { 
    var statElements = document.querySelectorAll('.statistics-details > div'); 

    if (statElements[index]) { 
        var element = statElements[index]; 
        var titleElement = element.querySelector('.statistics-title'); 

        if (titleElement) { 
            titleElement.textContent = data.title; 
        } 

        var valueElement = element.querySelector('.rate-percentage'); 

        if (valueElement) { 
            var currentValue = valueElement.textContent; 
            var newValue = String(data.value); 

            if (currentValue !== newValue) { 
                valueElement.classList.add('stat-updating'); 
                setTimeout(function() { 
                    valueElement.textContent = newValue; 
                    valueElement.classList.remove('stat-updating'); 
                }, 250); 
            } 
        }  

        var percentageElement = element.querySelector('p:last-child'); 
        
        if (percentageElement && data.percentage) { 
            percentageElement.className = 'd-flex'; 

            if (data.trend === 'success') { 
                percentageElement.classList.add('text-success'); 
                percentageElement.innerHTML = '<i class="mdi mdi-menu-up"></i><span>' + data.percentage + '</span>'; 

            } else if (data.trend === 'danger') { 
                percentageElement.classList.add('text-danger'); 
                percentageElement.innerHTML = '<i class="mdi mdi-menu-down"></i><span>' + data.percentage + '</span>'; 
            } else { 
                percentageElement.classList.add('text-muted'); 
                percentageElement.innerHTML = '<i class="mdi mdi-minus"></i><span>' + (data.percentage || 'Sin cambios') + '</span>'; 
            } 

        } else if (percentageElement && !data.percentage) { 
            percentageElement.innerHTML = ''; 
        } 
    } 
} 

function showErrorInStats() { 
    var statElements = document.querySelectorAll('.statistics-details > div'); 

    statElements.forEach(function(element, index) { 
        var valueElement = element.querySelector('.rate-percentage'); 
        if (valueElement) { 
            valueElement.textContent = '--'; 
        } 

        var percentageElement = element.querySelector('p:last-child'); 
        if (percentageElement) { 
            percentageElement.className = 'text-muted d-flex'; 
            percentageElement.innerHTML = '<i class="mdi mdi-alert-circle"></i><span>Error al cargar</span>'; 
        } 
    }); 
} 

function loadDoughnutChartData() { 
    console.log('🍩 Cargando datos para gráfico de dona...'); 

    fetch('../php/manager_get_dashboard_stats.php', { 
        method: 'GET', 
        headers: { 
            'Content-Type': 'application/json' 
        } 
    }) 

    .then(function(response) { 
        if (!response.ok) { 
            throw new Error('Error en la respuesta del servidor'); 
        } 
        return response.json(); 
    }) 

    .then(function(data) { 
        if (data.success && data.stats) { 
            updateDoughnutChart(data.stats); 
        } else { 
            console.error('Error al cargar datos del gráfico:', data.message); 
        } 
    }) 

    .catch(function(error) { 

        console.error('Error al cargar gráfico:', error); 

    }); 
} 

function updateDoughnutChart(stats) { 
    // El gráfico fue creado por dashboard.js y guardado en window.doughnutChart 
    if (!window.doughnutChart) { 
        console.warn('Gráfico de dona no encontrado en window.doughnutChart, reintentando...'); 
        // Reintentar en 500ms 
        setTimeout(function() { 
            if (window.doughnutChart) { 
                updateDoughnutChart(stats); 
            } else { 
                console.error('No se pudo encontrar el gráfico de dona'); 
            } 
        }, 500); 
        return; 
    } 

    // Datos en el orden que dashboard.js espera: 
    // ['Pendientes', 'Completados', 'Vencidos', 'En Progreso'] 
    var newData = [ 
        stats.proyectos_pendientes || 0, 
        stats.proyectos_completados || 0, 
        stats.proyectos_vencidos || 0, 
        stats.proyectos_en_proceso || 0 
    ]; 

    var currentData = window.doughnutChart.data.datasets[0].data; 
    var hasChanged = newData.some(function(val, idx) { 
        return val !== currentData[idx]; 
    }); 

    // Actualizar si hay cambios o si todos los datos son 0 (primera carga) 
    var allZeros = currentData.every(function(v) { return v === 0; }); 

    if (hasChanged || allZeros) { 
        // Actualizar datos 
        window.doughnutChart.data.datasets[0].data = newData; 
        window.doughnutChart.update(); 
        // Actualizar leyenda personalizada 
        updateDoughnutLegend(stats); 
        console.log('Gráfico de dona actualizado:', newData); 

    } 
} 

function updateDoughnutLegend(stats) { 
    var legendContainer = document.getElementById('doughnut-chart-legend'); 
    if (!legendContainer) return; 

    var total = (stats.proyectos_pendientes || 0) +  
                (stats.proyectos_completados || 0) +  
                (stats.proyectos_vencidos || 0) +  
                (stats.proyectos_en_proceso || 0); 

    var items = [ 
        { label: 'Pendientes', value: stats.proyectos_pendientes || 0, color: '#E9E9E9' }, 
        { label: 'Completados', value: stats.proyectos_completados || 0, color: '#009B4A' }, 
        { label: 'Vencidos', value: stats.proyectos_vencidos || 0, color: '#000000' }, 
        { label: 'En Progreso', value: stats.proyectos_en_proceso || 0, color: '#666666' } 
    ]; 
     
    var html = '<div class="chartjs-legend"><ul class="justify-content-center">'; 
    items.forEach(function(item) { 
        var percentage = total > 0 ? ((item.value / total) * 100).toFixed(1) : 0; 
        html += '<li>' + 
                    '<span style="background-color: ' + item.color + ';"></span>' + 
                    item.label + ': ' + item.value + ' (' + percentage + '%)' + 
                '</li>'; 
    }); 

    html += '</ul></div>'; 
    legendContainer.innerHTML = html; 
} 

window.startAutoRefresh = startAutoRefresh; 
window.stopAutoRefresh = stopAutoRefresh; 
window.toggleAutoRefresh = toggleAutoRefresh; 
window.refreshAllData = refreshAllData; 
window.loadDashboardStats = loadDashboardStats; 
window.loadDoughnutChartData = loadDoughnutChartData; 

if (document.readyState === 'loading') { 
    document.addEventListener('DOMContentLoaded', initializeManagerDashboard); 
} else { 
    initializeManagerDashboard(); 
} 