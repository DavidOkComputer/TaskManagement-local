/**
 * dashboard_charts_workload.js 
 * Maneja el gráfico de pastel de distribución de carga de trabajo
 * 
 * Role-based restrictions:
 * - Admins: Can view all departments and switch between them
 * - Managers: Only see their department's workload distribution
 */

let currentViewMode = 'departments'; // 'departments' o 'projects'
let selectedDepartmentId = null;
let selectedDepartmentName = null;

function initializeWorkloadChart() {
    console.log('Inicializando gráfico de distribución de carga de trabajo...');
    
    // Check user role configuration
    const userConfig = window.dashboardUserConfig;
    const isRoleLocked = userConfig && !userConfig.canViewAllDepartments;
    
    if (isRoleLocked) {
        // Manager or user - load only their department's workload
        console.log('Workload chart: User is role-locked, loading department-specific view');
        loadDepartmentWorkloadLocked();
    } else {
        // Admin - can switch between views
        setupDepartmentDropdownListener();
        loadWorkloadDistribution();
    }
}

/**
 * Load workload for role-locked users (managers/users)
 * They can only see their own department's projects
 */
function loadDepartmentWorkloadLocked() {
    const userConfig = window.dashboardUserConfig;
    
    if (!userConfig || !userConfig.userDepartamento) {
        console.error('No department configured for locked user');
        showChartError('No se pudo determinar el departamento');
        return;
    }
    
    // Fetch department name first
    fetch('../php/get_user_department.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.department) {
                const deptId = data.department.id_departamento;
                const deptName = data.department.nombre;
                
                // Set as locked - cannot change
                selectedDepartmentId = deptId;
                selectedDepartmentName = deptName;
                currentViewMode = 'projects';
                
                console.log(`Loading locked workload for department: ${deptName}`);
                loadProjectWorkload(deptId, deptName);
            } else {
                showChartError('Error al obtener departamento');
            }
        })
        .catch(error => {
            console.error('Error loading department for workload:', error);
            showChartError('Error de conexión');
        });
}

function setupDepartmentDropdownListener() {
    // Check if user is role-locked (safety check)
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        console.log('Workload dropdown listener: Skipped - user is role-locked');
        return;
    }

    const dropdown = document.getElementById('messageDropdown');
    
    if (!dropdown) {
        console.warn('Dropdown de departamentos no encontrado con ID "messageDropdown"');
        return;
    }

    const dropdownMenu = dropdown.nextElementSibling;
    
    if (!dropdownMenu) {
        console.warn('Dropdown menu no encontrado');
        return;
    }

    dropdownMenu.addEventListener('click', function(e) {
        const departmentItem = e.target.closest('[data-department-id]');
        
        if (departmentItem) {
            const deptId = parseInt(departmentItem.getAttribute('data-department-id'));
            const deptName = departmentItem.getAttribute('data-department-name') || 'Departamento';
            
            console.log('Departamento seleccionado para workload:', deptId, deptName);
            selectDepartment(deptId, deptName);
        }
    });

    console.log('Listener de dropdown de departamentos configurado para workload');
}

function selectDepartment(deptId, deptName) {
    // Check if role-locked (safety check)
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        console.log('Department selection blocked for workload - user is role-locked');
        return;
    }

    selectedDepartmentId = deptId;
    selectedDepartmentName = deptName;
    currentViewMode = 'projects';
    
    console.log(`Cargando proyectos para departamento: ${deptName} (ID: ${deptId})`);
    loadProjectWorkload(deptId, deptName);
    
    updateDropdownButtonText(deptName);
}

function resetToAllDepartments() {
    // Check if role-locked (block this action for managers)
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        console.log('Reset to all departments blocked - user is role-locked');
        return;
    }

    selectedDepartmentId = null;
    selectedDepartmentName = null;
    currentViewMode = 'departments';
    
    console.log('Volviendo a vista de departamentos...');
    loadWorkloadDistribution();
    
    resetDropdownButtonText();
}

function updateDropdownButtonText(deptName) {
    // Don't update if role-locked (dropdown is hidden anyway)
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        return;
    }

    const dropdownButton = document.getElementById('messageDropdown');
    if (!dropdownButton) return;
    
    let textSpan = dropdownButton.querySelector('.dropdown-text');
    
    if (!textSpan) {
        const originalText = dropdownButton.textContent.trim();
        textSpan = document.createElement('span');
        textSpan.className = 'dropdown-text';
        textSpan.textContent = originalText;
        
        dropdownButton.innerHTML = '';
        dropdownButton.appendChild(textSpan);
    }
    
    textSpan.textContent = deptName;
}

function resetDropdownButtonText() {
    const dropdownButton = document.getElementById('messageDropdown');
    if (!dropdownButton) return;
    
    let textSpan = dropdownButton.querySelector('.dropdown-text');
    
    if (textSpan) {
        textSpan.textContent = 'Seleccionar Categoría';
    } else {
        dropdownButton.textContent = 'Seleccionar Categoría';
    }
}

function loadWorkloadDistribution() {
    // For role-locked users, redirect to department-specific view
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        loadDepartmentWorkloadLocked();
        return;
    }

    console.log('Cargando distribución de carga de trabajo por departamento...');
    
    fetch('../php/get_task_workload_by_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Datos de carga de trabajo cargados:', data);
            if (data.success && data.data) {
                updateWorkloadChart(data.data, 'Distribución de Carga de Trabajo por Departamento');
            } else {
                console.error('Error en datos de carga de trabajo:', data.message);
                showChartError('No hay datos disponibles');
            }
        })
        .catch(error => {
            console.error('Error loading workload distribution:', error.message);
            showChartError('Error al cargar datos');
        });
}

function loadProjectWorkload(deptId, deptName) {
    console.log(`Cargando carga de trabajo por proyectos del departamento: ${deptName}...`);
    
    fetch(`../php/get_task_workload_by_project.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Datos de proyectos cargados:', data);
            if (data.success && data.data) {
                // Update title based on role
                let chartTitle = deptName;
                if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
                    chartTitle = `Carga de Trabajo - ${deptName}`;
                }
                updateWorkloadChart(data.data, chartTitle);
            } else {
                console.error('Error en datos de proyectos:', data.message);
                showChartError(data.message || 'No hay proyectos con tareas en este departamento');
            }
        })
        .catch(error => {
            console.error('Error loading project workload:', error.message);
            showChartError('Error al cargar proyectos');
        });
}

function updateWorkloadChart(data, chartTitle = 'Distribución de Carga de Trabajo por Departamento') {
    const ctx = document.getElementById('workloadChart');
    
    if (!ctx) {
        console.warn('Workload chart canvas not found');
        return;
    }
    
    if (dashboardChartsInstance && dashboardChartsInstance.charts && dashboardChartsInstance.charts.workloadChart) {
        dashboardChartsInstance.charts.workloadChart.destroy();
    }
    
    if (!data.labels || data.labels.length === 0) {
        console.warn('No data available for chart');
        ctx.style.display = 'none';
        const parent = ctx.parentElement;
        let errorDiv = parent.querySelector('.chart-error');
        if (errorDiv) {
            errorDiv.remove();
        }
        errorDiv = document.createElement('div');
        errorDiv.className = 'chart-error alert alert-info';
        errorDiv.textContent = 'No hay datos disponibles para mostrar';
        parent.appendChild(errorDiv);
        return;
    }
    
    ctx.style.display = 'block';
    const parent = ctx.parentElement;
    const errorDiv = parent.querySelector('.chart-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    
    const chartData = {
        labels: data.labels,
        datasets: [{
            data: data.data,
            backgroundColor: data.backgroundColor,
            borderColor: data.borderColor,
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
                    font: {
                        size: 12
                    },
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            title: {
                display: true,
                text: chartTitle,
                font: {
                    size: 14,
                    weight: 'bold'
                },
                padding: 15
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value + ' tareas (' + percentage + '%)';
                    },
                    afterLabel: function(context) {
                        const dataIndex = context.dataIndex;
                        if (data.details && data.details[dataIndex]) {
                            const detail = data.details[dataIndex];
                            return [
                                'Completadas: ' + detail.completadas,
                                'En proceso: ' + detail.en_proceso,
                                'Pendientes: ' + detail.pendientes,
                                'Vencidas: ' + detail.vencidas
                            ];
                        }
                        return '';
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: {
                    size: 12
                },
                bodyFont: {
                    size: 11
                },
                padding: 12,
                displayColors: true,
                multiKeyBackground: 'rgba(0, 0, 0, 0.8)'
            }
        }
    };
    
    if (!dashboardChartsInstance) {
        dashboardChartsInstance = { charts: {} };
    }
    
    dashboardChartsInstance.charts.workloadChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
    
    console.log('Gráfico de carga de trabajo actualizado - Total de tareas: ' + data.total_tareas);
}

function showChartError(message) {
    const ctx = document.getElementById('workloadChart');
    if (!ctx) return;
    
    ctx.style.display = 'none';
    const parent = ctx.parentElement;
    
    let errorDiv = parent.querySelector('.chart-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    
    errorDiv = document.createElement('div');
    errorDiv.className = 'chart-error alert alert-warning';
    errorDiv.innerHTML = `<i class="mdi mdi-alert-circle"></i> ${message}`;
    parent.appendChild(errorDiv);
}

function refreshWorkloadChart() {
    console.log('Refrescando gráfico de carga de trabajo...');
    
    // For role-locked users, always refresh department view
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        if (selectedDepartmentId) {
            loadProjectWorkload(selectedDepartmentId, selectedDepartmentName);
        } else {
            loadDepartmentWorkloadLocked();
        }
        return;
    }
    
    if (currentViewMode === 'projects' && selectedDepartmentId) {
        loadProjectWorkload(selectedDepartmentId, selectedDepartmentName);
    } else {
        loadWorkloadDistribution();
    }
}

function initializeDashboardChartsWithWorkload() {
    initializeDashboardCharts();
    initializeWorkloadChart();
}

function startWorkloadChartAutoRefresh(intervalSeconds = 60) {
    console.log('Iniciando auto-actualización de gráfico de carga de trabajo cada ' + intervalSeconds + ' segundos');
    
    setInterval(function() {
        refreshWorkloadChart();
    }, intervalSeconds * 1000);
}

// Export functions - but block department switching for managers
window.selectDepartmentWorkload = function(deptId, deptName) {
    // Check if role-locked
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        console.log('selectDepartmentWorkload blocked - user is role-locked');
        return;
    }
    selectDepartment(deptId, deptName);
};

window.resetWorkloadView = function() {
    // Check if role-locked
    if (dashboardChartsInstance && dashboardChartsInstance.isRoleLocked) {
        console.log('resetWorkloadView blocked - user is role-locked');
        return;
    }
    resetToAllDepartments();
};

document.addEventListener('DOMContentLoaded', function() {
    const workloadCanvas = document.getElementById('workloadChart');
    if (workloadCanvas) {
        console.log('Workload chart canvas detectado, inicializando...');
        initializeWorkloadChart();
        startWorkloadChartAutoRefresh(60);
    }
});