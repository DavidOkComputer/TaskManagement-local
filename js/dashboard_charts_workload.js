/**
 * dashboard_charts_workload.js
 * Workload distribution chart (doughnut/pie)
 * Admin only - Comparison view vs Department view
 */

let workloadChartState = {
    currentMode: 'departments', // 'departments' or 'projects'
    selectedDepartmentId: null,
    selectedDepartmentName: null
};

/**
 * Initialize workload chart
 */
function initializeWorkloadChart() {
    console.log('Inicializando gráfico de distribución de carga de trabajo...');
    setupWorkloadDropdownListener();
    loadWorkloadDistribution();
}

/**
 * Setup listener for department dropdown changes
 */
function setupWorkloadDropdownListener() {
    const dropdown = document.getElementById('messageDropdown');
    
    if (!dropdown) {
        console.warn('Dropdown de departamentos no encontrado');
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
            selectDepartmentForWorkload(deptId, deptName);
        }
    });

    console.log('Listener de dropdown configurado para workload chart');
}

/**
 * Select a department for workload view
 */
function selectDepartmentForWorkload(deptId, deptName) {
    workloadChartState.selectedDepartmentId = deptId;
    workloadChartState.selectedDepartmentName = deptName;
    workloadChartState.currentMode = 'projects';
    
    console.log(`Cargando proyectos para departamento: ${deptName} (ID: ${deptId})`);
    loadProjectWorkload(deptId, deptName);
}

/**
 * Reset to all departments view
 */
function resetWorkloadToComparison() {
    workloadChartState.selectedDepartmentId = null;
    workloadChartState.selectedDepartmentName = null;
    workloadChartState.currentMode = 'departments';
    
    console.log('Volviendo a vista de departamentos...');
    loadWorkloadDistribution();
}

/**
 * Load workload distribution by department (comparison view)
 */
function loadWorkloadDistribution() {
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
                showWorkloadChartError('No hay datos disponibles');
            }
        })
        .catch(error => {
            console.error('Error loading workload distribution:', error.message);
            showWorkloadChartError('Error al cargar datos');
        });
}

/**
 * Load workload by project within a department
 */
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
                updateWorkloadChart(data.data, `Carga de Trabajo - ${deptName}`);
            } else {
                console.error('Error en datos de proyectos:', data.message);
                showWorkloadChartError(data.message || 'No hay proyectos con tareas en este departamento');
            }
        })
        .catch(error => {
            console.error('Error loading project workload:', error.message);
            showWorkloadChartError('Error al cargar proyectos');
        });
}

/**
 * Update workload chart with data
 */
function updateWorkloadChart(data, chartTitle) {
    const ctx = document.getElementById('workloadChart');
    
    if (!ctx) {
        console.warn('Workload chart canvas not found');
        return;
    }
    
    // Destroy existing chart
    if (dashboardChartsInstance && dashboardChartsInstance.charts && dashboardChartsInstance.charts.workloadChart) {
        dashboardChartsInstance.charts.workloadChart.destroy();
    }
    
    // Check for empty data
    if (!data.labels || data.labels.length === 0) {
        console.warn('No data available for chart');
        showWorkloadChartError('No hay datos disponibles para mostrar');
        return;
    }
    
    // Show canvas and remove any error messages
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
                titleFont: { size: 12 },
                bodyFont: { size: 11 },
                padding: 12,
                displayColors: true
            }
        }
    };
    
    // Ensure dashboardChartsInstance exists
    if (!dashboardChartsInstance) {
        dashboardChartsInstance = { charts: {} };
    }
    if (!dashboardChartsInstance.charts) {
        dashboardChartsInstance.charts = {};
    }
    
    dashboardChartsInstance.charts.workloadChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
    
    console.log('Gráfico de carga de trabajo actualizado - Total de tareas: ' + data.total_tareas);
}

/**
 * Show error message on workload chart
 */
function showWorkloadChartError(message) {
    const ctx = document.getElementById('workloadChart');
    if (!ctx) return;
    
    ctx.style.display = 'none';
    const parent = ctx.parentElement;
    
    // Remove existing error
    let errorDiv = parent.querySelector('.chart-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    
    // Create error message
    errorDiv = document.createElement('div');
    errorDiv.className = 'chart-error alert alert-warning';
    errorDiv.innerHTML = `<i class="mdi mdi-alert-circle"></i> ${message}`;
    parent.appendChild(errorDiv);
}

/**
 * Refresh workload chart based on current state
 */
function refreshWorkloadChart() {
    console.log('Refrescando gráfico de carga de trabajo...');
    
    if (workloadChartState.currentMode === 'projects' && workloadChartState.selectedDepartmentId) {
        loadProjectWorkload(workloadChartState.selectedDepartmentId, workloadChartState.selectedDepartmentName);
    } else {
        loadWorkloadDistribution();
    }
}

/**
 * Sync workload chart with main dashboard state
 */
function syncWorkloadWithDashboard() {
    if (dashboardChartsInstance.currentDepartment) {
        const dept = dashboardChartsInstance.currentDepartment;
        selectDepartmentForWorkload(dept.id, dept.name);
    } else {
        resetWorkloadToComparison();
    }
}

// Export functions for external use
window.selectDepartmentWorkload = selectDepartmentForWorkload;
window.resetWorkloadView = resetWorkloadToComparison;
window.refreshWorkloadChart = refreshWorkloadChart;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const workloadCanvas = document.getElementById('workloadChart');
    if (workloadCanvas) {
        console.log('Workload chart canvas detectado, inicializando...');
        initializeWorkloadChart();
    }
});