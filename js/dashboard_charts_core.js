/*dashboard_charts_core.js controlador de todos los graficos version de admin*/

let dashboardChartsInstance = {
    charts: {},
    currentDepartment: null,
    refreshInterval: null,
    refreshRate: 60000, // 60 segundos
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

function shortenProjectTitle(title, maxLength = 15) {
    if (!title) return '';
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength) + '...';
}

function initializeDashboardCharts() {
    loadComparisonView();//cargar vista de comparacion por default
    initializeLineChart();//incializar los graficos
    initializeAreaChart();
    initializeScatterChart();
    startAutoRefresh();
}

function startAutoRefresh() {
    if (dashboardChartsInstance.refreshInterval) {
        clearInterval(dashboardChartsInstance.refreshInterval);
    }

    dashboardChartsInstance.refreshInterval = setInterval(() => {
        refreshDashboardData();
    }, dashboardChartsInstance.refreshRate);
}

function stopAutoRefresh() {
    if (dashboardChartsInstance.refreshInterval) {
        clearInterval(dashboardChartsInstance.refreshInterval);
        dashboardChartsInstance.refreshInterval = null;
    }
}

function refreshDashboardData() {
    if (dashboardChartsInstance.isRefreshing) {
        return;
    }

    dashboardChartsInstance.isRefreshing = true;

    if (dashboardChartsInstance.currentDepartment) {
        //refrescar la vista de departamento
        const deptId = dashboardChartsInstance.currentDepartment.id;
        const deptName = dashboardChartsInstance.currentDepartment.name;
        refreshDepartmentView(deptId, deptName);
    } else {
        //refrescar la vista de comparacion
        refreshComparisonView();
    }
}

function refreshComparisonView() {
    Promise.all([
        fetch('../php/get_departments.php').then(r => r.json()),
        fetch('../php/get_projects.php').then(r => r.json())
    ])
    .then(([deptResponse, projResponse]) => {
        if (deptResponse.success && projResponse.success) {
            const departments = deptResponse.departamentos;
            const projects = projResponse.proyectos;
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

function refreshDepartmentView(deptId, deptName) {
    fetch(`../php/get_projects_by_department.php?id_departamento=${deptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.proyectos.length === 0) {
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

function setRefreshRate(milliseconds) {
    dashboardChartsInstance.refreshRate = milliseconds;
    startAutoRefresh();
}

function showNoDepartmentData(deptName) {
    showNoDataMessage('barChart', `No hay proyectos en ${deptName}`, 'No se encontraron proyectos para mostrar');
    showNoDataMessage('doughnutChart', `Sin datos - ${deptName}`, 'No hay proyectos para mostrar');
    showNoDataMessage('lineChart', `Sin datos - ${deptName}`, 'No hay progreso de proyectos para mostrar');
    showNoDataMessage('areaChart', `Sin datos - ${deptName}`, 'No hay avances de tareas para mostrar');
    showNoDataMessage('scatterChart', `Sin datos - ${deptName}`, 'No hay datos de eficiencia para mostrar');
    showNoDataMessage('workloadChart', `Sin datos - ${deptName}`, 'No hay distribución de carga para mostrar');
}

function showNoDataMessage(canvasId, title, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`Canvas ${canvasId} no encontrado`);
        return;
    }

    if (dashboardChartsInstance.charts[canvasId]) {//destruir graficas existentes
        dashboardChartsInstance.charts[canvasId].destroy();
        dashboardChartsInstance.charts[canvasId] = null;
    }

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);


    ctx.fillStyle = '#e0e0e0';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Titul0
    ctx.fillStyle = '#555';
    ctx.font = 'bold 18px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(title, canvas.width / 2, canvas.height / 2 + 20);

    // mensaje
    ctx.fillStyle = '#777';
    ctx.font = '14px Arial';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2 + 45);
}

function loadComparisonView() {
    dashboardChartsInstance.currentDepartment = null;

    Promise.all([
        fetch('../php/get_departments.php').then(r => r.json()),
        fetch('../php/get_projects.php').then(r => r.json())
    ])
    .then(([deptResponse, projResponse]) => {
        if (deptResponse.success && projResponse.success) {
            const departments = deptResponse.departamentos;
            const projects = projResponse.proyectos;
            processComparisonData(departments, projects);
        } else {
            console.error('Error obteniendo info para la vista de comparación');
        }
    })
    .catch(error => {
        console.error('Error in loadComparisonView:', error);
    });
}

function processComparisonData(departments, projects) {
    const completedByDept = prepareCompletedProjectsByDepartment(departments, projects);
    const statusDistribution = prepareProjectStatusDistribution(projects);
    
    updateBarChart(completedByDept);
    updateDoughnutChart(statusDistribution);
}

function loadDepartmentView(deptId, deptName) {
    dashboardChartsInstance.currentDepartment = {
        id: deptId,
        name: deptName,
        updatedAt: new Date().getTime()
    };

    fetch(`../php/get_projects_by_department.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (data.proyectos.length === 0) {
                    showNoDepartmentData(deptName);
                    return;
                }

                //procesar datos y actualizar graficas
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

function processDepartmentData(projects, deptName) {
    const statusDistribution = prepareDepartmentStatusDistribution(projects);
    updateDoughnutChartForDepartment(statusDistribution, deptName);
    updateBarChartForDepartment(projects, deptName);
}

function clearDepartmentSelection() {
    dashboardChartsInstance.currentDepartment = null;
    updateDropdownButtonText('Seleccionar área');

    loadComparisonView();//cargar a vista de comparacion para todas las graficas

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
    }, 1200);
}

function selectDepartmentFromDropdown(deptId, deptName) {
    updateDropdownButtonText(deptName);
    loadDepartmentView(deptId, deptName);
}

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

function getCurrentDepartment() {
    return dashboardChartsInstance.currentDepartment;
}

function isComparisonView() {
    return dashboardChartsInstance.currentDepartment === null;
}

window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboardCharts();
});