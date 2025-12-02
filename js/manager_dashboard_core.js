/*
 * manager_dashboard_core.js
 * Core controller for manager dashboard charts
 * 
 * This file is specifically for MANAGER role (id_rol = 2)
 * - Only shows data from manager's assigned department
 * - No department switching functionality
 * - Auto-refresh enabled
 */

let managerDashboard = {
    charts: {},
    department: null,
    refreshInterval: null,
    refreshRate: 60000, // 60 seconds
    isRefreshing: false,
    
    // Color palette for charts (Nidec brand colors)
    colors: {
        primary: 'rgba(34, 139, 89, 0.7)',
        primarySolid: 'rgba(34, 139, 89, 1)',
        secondary: 'rgba(80, 154, 108, 0.7)',
        secondarySolid: 'rgba(80, 154, 108, 1)',
        dark: 'rgba(24, 97, 62, 0.7)',
        darkSolid: 'rgba(24, 97, 62, 1)',
        gray: 'rgba(130, 140, 150, 0.7)',
        graySolid: 'rgba(130, 140, 150, 1)',
        light: 'rgba(200, 205, 210, 0.7)',
        lightSolid: 'rgba(200, 205, 210, 1)',
        black: 'rgba(50, 50, 50, 0.7)',
        blackSolid: 'rgba(50, 50, 50, 1)'
    },
    
    // Status colors
    statusColors: {
        completado: 'rgba(34, 139, 89, 0.7)',
        'en proceso': 'rgba(130, 140, 150, 0.7)',
        pendiente: 'rgba(200, 205, 210, 0.7)',
        vencido: 'rgba(50, 50, 50, 0.7)'
    },
    
    statusBorderColors: {
        completado: 'rgba(34, 139, 89, 1)',
        'en proceso': 'rgba(130, 140, 150, 1)',
        pendiente: 'rgba(200, 205, 210, 1)',
        vencido: 'rgba(50, 50, 50, 1)'
    }
};

/**
 * Initialize the manager dashboard
 */
function initializeManagerDashboard() {
    console.log('═══════════════════════════════════════════════════════');
    console.log('INICIALIZANDO DASHBOARD DE GERENTE');
    console.log('═══════════════════════════════════════════════════════');
    
    // Load manager's department first, then initialize charts
    loadManagerDepartment()
        .then(() => {
            console.log('Departamento cargado:', managerDashboard.department.nombre);
            initializeAllCharts();
            startAutoRefresh();
        })
        .catch(error => {
            console.error('Error inicializando dashboard:', error);
            showGlobalError('Error al cargar información del departamento');
        });
}

/**
 * Load manager's department from session
 * @returns {Promise}
 */
function loadManagerDepartment() {
    return fetch('../php/manager_get_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error de conexión: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Error al obtener departamento');
            }
            
            managerDashboard.department = {
                id: data.department.id_departamento,
                nombre: data.department.nombre,
                descripcion: data.department.descripcion
            };
            
            // Update page title with department name
            updateDepartmentDisplay(managerDashboard.department.nombre);
            
            return managerDashboard.department;
        });
}

/**
 * Update department name display in the UI
 */
function updateDepartmentDisplay(deptName) {
    // Update any elements that show department name
    const deptDisplayElements = document.querySelectorAll('.manager-department-name');
    deptDisplayElements.forEach(el => {
        el.textContent = deptName;
    });
    
    // Update welcome subtitle if exists
    const welcomeSubtext = document.querySelector('.welcome-sub-text');
    if (welcomeSubtext) {
        welcomeSubtext.textContent = `Departamento: ${deptName}`;
    }
}

/**
 * Initialize all charts
 */
function initializeAllCharts() {
    console.log('Inicializando todas las gráficas...');
    
    // Initialize each chart with slight delays to prevent overwhelming the server
    initializeManagerDoughnutChart();
    
    setTimeout(() => {
        initializeManagerBarChart();
    }, 100);
    
    setTimeout(() => {
        initializeManagerLineChart();
    }, 200);
    
    setTimeout(() => {
        initializeManagerAreaChart();
    }, 300);
    
    setTimeout(() => {
        initializeManagerScatterChart();
    }, 400);
    
    setTimeout(() => {
        initializeManagerWorkloadChart();
    }, 500);
}

/**
 * Refresh all dashboard data
 */
function refreshAllCharts() {
    if (managerDashboard.isRefreshing) {
        console.log('Refresh en progreso, saltando...');
        return;
    }
    
    managerDashboard.isRefreshing = true;
    console.log('Refrescando datos del dashboard...', new Date().toLocaleTimeString());
    
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    // Refresh each chart
    Promise.all([
        refreshManagerDoughnutChart(deptId, deptName),
        refreshManagerBarChart(deptId, deptName),
        refreshManagerLineChart(deptId, deptName),
        refreshManagerAreaChart(deptId, deptName),
        refreshManagerScatterChart(deptId, deptName),
        refreshManagerWorkloadChart(deptId, deptName)
    ])
    .then(() => {
        console.log('Todas las gráficas actualizadas');
    })
    .catch(error => {
        console.error('Error en refresh:', error);
    })
    .finally(() => {
        managerDashboard.isRefreshing = false;
    });
}

/**
 * Start auto-refresh interval
 */
function startAutoRefresh() {
    if (managerDashboard.refreshInterval) {
        clearInterval(managerDashboard.refreshInterval);
    }
    
    managerDashboard.refreshInterval = setInterval(() => {
        refreshAllCharts();
    }, managerDashboard.refreshRate);
    
    console.log(`Auto-refresh iniciado: cada ${managerDashboard.refreshRate / 1000} segundos`);
}

/**
 * Stop auto-refresh
 */
function stopAutoRefresh() {
    if (managerDashboard.refreshInterval) {
        clearInterval(managerDashboard.refreshInterval);
        managerDashboard.refreshInterval = null;
        console.log('Auto-refresh detenido');
    }
}

/**
 * Set refresh rate
 */
function setRefreshRate(milliseconds) {
    managerDashboard.refreshRate = milliseconds;
    console.log(`Intervalo actualizado a ${milliseconds / 1000} segundos`);
    startAutoRefresh();
}

/**
 * Utility: Shorten project title for display
 */
function shortenTitle(title, maxLength = 15) {
    if (!title) return '';
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength) + '...';
}

/**
 * Utility: Show "no data" message on a canvas
 */
function showNoDataMessage(canvasId, title, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`Canvas ${canvasId} no encontrado`);
        return;
    }
    
    // Destroy existing chart
    if (managerDashboard.charts[canvasId]) {
        managerDashboard.charts[canvasId].destroy();
        managerDashboard.charts[canvasId] = null;
    }
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Draw background
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Draw icon
    ctx.fillStyle = '#6c757d';
    ctx.font = '48px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('📊', canvas.width / 2, canvas.height / 2 - 30);
    
    // Draw title
    ctx.fillStyle = '#495057';
    ctx.font = 'bold 16px Arial';
    ctx.fillText(title, canvas.width / 2, canvas.height / 2 + 20);
    
    // Draw message
    ctx.fillStyle = '#6c757d';
    ctx.font = '14px Arial';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2 + 45);
}

/**
 * Utility: Show global error message
 */
function showGlobalError(message) {
    console.error('Error global:', message);
    
    // Show error on all canvases
    const canvasIds = ['lineChart', 'barChart', 'areaChart', 'doughnutChart', 'scatterChart', 'workloadChart'];
    canvasIds.forEach(id => {
        showNoDataMessage(id, 'Error', message);
    });
}

/**
 * Utility: Get color by index (cycling through palette)
 */
function getColorByIndex(index, opacity = 0.7) {
    const colorPalette = [
        `rgba(34, 139, 89, ${opacity})`,
        `rgba(80, 154, 108, ${opacity})`,
        `rgba(24, 97, 62, ${opacity})`,
        `rgba(130, 140, 150, ${opacity})`,
        `rgba(200, 205, 210, ${opacity})`,
        `rgba(50, 50, 50, ${opacity})`,
        `rgba(45, 110, 80, ${opacity})`,
        `rgba(160, 170, 180, ${opacity})`
    ];
    return colorPalette[index % colorPalette.length];
}

/**
 * Utility: Get progress color based on percentage
 */
function getProgressColor(progress) {
    if (progress === 100) return managerDashboard.colors.primary;
    if (progress >= 75) return managerDashboard.colors.secondary;
    if (progress >= 50) return managerDashboard.colors.gray;
    return managerDashboard.colors.light;
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeManagerDashboard();
});