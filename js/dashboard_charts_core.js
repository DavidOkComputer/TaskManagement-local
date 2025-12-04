/*dashboard_charts_core.js - Admin Dashboard Controller
 * Simplified version: Comparison view vs Department view only
 * No role-based restrictions
 */

let dashboardChartsInstance = {
    charts: {},
    currentDepartment: null,
    refreshInterval: null,
    refreshRate: 60000, // 60 seconds
    isRefreshing: false,
    
    departmentColors: [
        'rgba(34, 139, 89, 0.7)',      // Green (Primary)
        'rgba(80, 154, 108, 0.7)',     // Green Light
        'rgba(24, 97, 62, 0.7)',       // Green Dark
        'rgba(200, 205, 210, 0.7)',    // Ice/Light Gray
        'rgba(130, 140, 150, 0.7)',    // Gray
        'rgba(50, 50, 50, 0.7)',       // Black
        'rgba(45, 110, 80, 0.7)',      // Green Secondary
        'rgba(160, 170, 180, 0.7)',    // Gray Light
    ],

    departmentBorderColors: [
        'rgba(34, 139, 89, 1)',        // Green (Primary)
        'rgba(80, 154, 108, 1)',       // Green Light
        'rgba(24, 97, 62, 1)',         // Green Dark
        'rgba(200, 205, 210, 1)',      // Ice/Light Gray
        'rgba(130, 140, 150, 1)',      // Gray
        'rgba(50, 50, 50, 1)',         // Black
        'rgba(45, 110, 80, 1)',        // Green Secondary
        'rgba(160, 170, 180, 1)',      // Gray Light
    ]
};

/**
 * Shorten project titles for display
 */
function shortenProjectTitle(title, maxLength = 15) {
    if (!title) return '';
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength) + '...';
}

/**
 * Initialize all dashboard charts
 * Starts with comparison view (all departments)
 */
function initializeDashboardCharts() {
    console.log('Inicializando gráficos del dashboard (Admin)...');
    
    // Load comparison view by default
    loadComparisonView();
    
    // Initialize other charts
    initializeLineChart();
    initializeAreaChart();
    initializeScatterChart();
    
    // Start auto-refresh
    startAutoRefresh();
    console.log(`Auto-refresh activado: cada ${dashboardChartsInstance.refreshRate / 1000} segundos`);
}

/**
 * Start auto-refresh interval
 */
function startAutoRefresh() {
    if (dashboardChartsInstance.refreshInterval) {
        clearInterval(dashboardChartsInstance.refreshInterval);
    }

    dashboardChartsInstance.refreshInterval = setInterval(() => {
        refreshDashboardData();
    }, dashboardChartsInstance.refreshRate);
    
    console.log('Auto-refresh iniciado');
}

/**
 * Stop auto-refresh interval
 */
function stopAutoRefresh() {
    if (dashboardChartsInstance.refreshInterval) {
        clearInterval(dashboardChartsInstance.refreshInterval);
        dashboardChartsInstance.refreshInterval = null;
        console.log('Auto-refresh detenido');
    }
}

/**
 * Refresh dashboard data based on current view
 */
function refreshDashboardData() {
    if (dashboardChartsInstance.isRefreshing) {
        console.log('Refresh ya en progreso, saltando...');
        return;
    }

    dashboardChartsInstance.isRefreshing = true;
    console.log('Actualizando datos del dashboard...', new Date().toLocaleTimeString());

    if (dashboardChartsInstance.currentDepartment) {
        // Refresh department view
        const deptId = dashboardChartsInstance.currentDepartment.id;
        const deptName = dashboardChartsInstance.currentDepartment.name;
        console.log(`Refrescando vista del departamento: ${deptName}`);
        refreshDepartmentView(deptId, deptName);
    } else {
        // Refresh comparison view
        console.log('Refrescando vista de comparación');
        refreshComparisonView();
    }
}

/**
 * Refresh comparison view data
 */
function refreshComparisonView() {
    Promise.all([
        fetch('../php/get_departments.php').then(r => r.json()),
        fetch('../php/get_projects.php').then(r => r.json())
    ])
    .then(([deptResponse, projResponse]) => {
        if (deptResponse.success && projResponse.success) {
            const departments = deptResponse.departamentos;
            const projects = projResponse.proyectos;
            console.log('Datos de comparación actualizados');
            processComparisonData(departments, projects);
            
            // Refresh other charts
            loadProjectTrendComparison();
            loadTaskTrendComparison();
            loadDepartmentEfficiency();
        } else {
            console.error('Error en refresh de comparación');
        }
    })
    .catch(error => {
        console.error('Error en refreshComparisonView:', error);
    })
    .finally(() => {
        dashboardChartsInstance.isRefreshing = false;
    });
}

/**
 * Refresh department view data
 */
function refreshDepartmentView(deptId, deptName) {
    fetch(`../php/get_projects_by_department.php?id_departamento=${deptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`Datos del departamento ${deptName} actualizados`);

                if (data.proyectos.length === 0) {
                    console.log(`No hay proyectos en el departamento ${deptName}`);
                    showNoDepartmentData(deptName);
                } else {
                    processDepartmentData(data.proyectos, deptName);
                    loadProjectTrendForDepartment(deptId, deptName);
                    loadTaskTrendForDepartment(deptId, deptName);
                    loadPersonEfficiencyByDepartment(deptId, deptName);
                }
            } else {
                console.error('Error en refresh de departamento:', data.message);
            }
        })
        .catch(error => {
            console.error('Error en refreshDepartmentView:', error);
        })
        .finally(() => {
            dashboardChartsInstance.isRefreshing = false;
        });
}

/**
 * Change refresh rate (in milliseconds)
 */
function setRefreshRate(milliseconds) {
    dashboardChartsInstance.refreshRate = milliseconds;
    console.log(`Intervalo de refresh actualizado a ${milliseconds / 1000} segundos`);
    startAutoRefresh();
}

/**
 * Show "no data" message for a department
 */
function showNoDepartmentData(deptName) {
    console.log(`Mostrando mensaje de "sin datos" para ${deptName}`);
    
    showNoDataMessage('barChart', `No hay proyectos en ${deptName}`, 'No se encontraron proyectos para mostrar');
    showNoDataMessage('doughnutChart', `Sin datos - ${deptName}`, 'No hay proyectos para mostrar');
    showNoDataMessage('lineChart', `Sin datos - ${deptName}`, 'No hay progreso de proyectos para mostrar');
    showNoDataMessage('areaChart', `Sin datos - ${deptName}`, 'No hay avances de tareas para mostrar');
    showNoDataMessage('scatterChart', `Sin datos - ${deptName}`, 'No hay datos de eficiencia para mostrar');
    showNoDataMessage('workloadChart', `Sin datos - ${deptName}`, 'No hay distribución de carga para mostrar');
}

/**
 * Display "no data" message on a canvas
 */
function showNoDataMessage(canvasId, title, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`Canvas ${canvasId} no encontrado`);
        return;
    }

    // Destroy existing chart if exists
    if (dashboardChartsInstance.charts[canvasId]) {
        dashboardChartsInstance.charts[canvasId].destroy();
        dashboardChartsInstance.charts[canvasId] = null;
    }

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Background
    ctx.fillStyle = '#e0e0e0';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Title
    ctx.fillStyle = '#555';
    ctx.font = 'bold 18px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(title, canvas.width / 2, canvas.height / 2 + 20);

    // Message
    ctx.fillStyle = '#777';
    ctx.font = '14px Arial';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2 + 45);

    console.log(`Mensaje "sin datos" mostrado en ${canvasId}`);
}

/**
 * Load comparison view (all departments)
 */
function loadComparisonView() {
    console.log('Cargando vista de comparación (todos los departamentos)');
    dashboardChartsInstance.currentDepartment = null;

    Promise.all([
        fetch('../php/get_departments.php').then(r => r.json()),
        fetch('../php/get_projects.php').then(r => r.json())
    ])
    .then(([deptResponse, projResponse]) => {
        if (deptResponse.success && projResponse.success) {
            const departments = deptResponse.departamentos;
            const projects = projResponse.proyectos;
            console.log('Datos de comparación obtenidos - actualizando gráficos...');
            processComparisonData(departments, projects);
        } else {
            console.error('Error obteniendo info para la vista de comparación');
        }
    })
    .catch(error => {
        console.error('Error in loadComparisonView:', error);
    });
}

/**
 * Process data for comparison view
 */
function processComparisonData(departments, projects) {
    console.log('Procesando datos de comparación...');
    console.log('Departamentos:', departments.length);
    console.log('Proyectos:', projects.length);

    const completedByDept = prepareCompletedProjectsByDepartment(departments, projects);
    const statusDistribution = prepareProjectStatusDistribution(projects);
    
    updateBarChart(completedByDept);
    updateDoughnutChart(statusDistribution);
}

/**
 * Load department-specific view
 */
function loadDepartmentView(deptId, deptName) {
    console.log('Cambiando a vista de departamento:', deptName);
    
    dashboardChartsInstance.currentDepartment = {
        id: deptId,
        name: deptName,
        updatedAt: new Date().getTime()
    };

    console.log('Estado de departamento actualizado:', dashboardChartsInstance.currentDepartment);

    fetch(`../php/get_projects_by_department.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('Info de proyecto recibida:', data.proyectos.length, 'proyectos');

                if (data.proyectos.length === 0) {
                    console.log(`No hay proyectos en el departamento ${deptName}`);
                    showNoDepartmentData(deptName);
                    return;
                }

                // Process data and update charts
                processDepartmentData(data.proyectos, deptName);
                loadProjectTrendForDepartment(deptId, deptName);
                loadTaskTrendForDepartment(deptId, deptName);
                loadPersonEfficiencyByDepartment(deptId, deptName);

            } else {
                console.error('Error obteniendo proyectos:', data.message);
                alert('Error al cargar proyectos: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error cargando vista de departamento:', error);
            alert('Error de conexión al cargar proyectos del departamento');
        });
}

/**
 * Process data for department view
 */
function processDepartmentData(projects, deptName) {
    console.log(`Procesando datos del departamento: ${deptName}`);
    console.log(`Total de proyectos: ${projects.length}`);
    
    const statusDistribution = prepareDepartmentStatusDistribution(projects);
    updateDoughnutChartForDepartment(statusDistribution, deptName);
    updateBarChartForDepartment(projects, deptName);
}

/**
 * Clear department selection and return to comparison view
 */
function clearDepartmentSelection() {
    console.log('Limpiando selección de departamento...');
    dashboardChartsInstance.currentDepartment = null;
    updateDropdownButtonText('Seleccionar área');

    // Load comparison view for all charts
    loadComparisonView();

    setTimeout(() => {
        loadProjectTrendComparison();
    }, 300);

    setTimeout(() => {
        loadTaskTrendComparison();
    }, 600);

    setTimeout(() => {
        loadDepartmentEfficiency();
    }, 900);

    setTimeout(() => {
        console.log('Estado actual del departamento:', dashboardChartsInstance.currentDepartment);
    }, 1200);
}

/**
 * Select department from dropdown
 */
function selectDepartmentFromDropdown(deptId, deptName) {
    console.log(`Departamento seleccionado: ${deptName} (ID: ${deptId})`);
    updateDropdownButtonText(deptName);
    loadDepartmentView(deptId, deptName);
}

/**
 * Update dropdown button text
 */
function updateDropdownButtonText(text) {
    const dropdownButton = document.querySelector('#messageDropdown');
    if (dropdownButton) {
        const existingIcon = dropdownButton.querySelector('i');
        dropdownButton.textContent = text + ' ';
        if (existingIcon) {
            const newIcon = existingIcon.cloneNode(true);
            dropdownButton.appendChild(newIcon);
        }
    }
}

/**
 * Get current department info
 */
function getCurrentDepartment() {
    return dashboardChartsInstance.currentDepartment;
}

/**
 * Check if currently in comparison view
 */
function isComparisonView() {
    return dashboardChartsInstance.currentDepartment === null;
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboardCharts();
});