/*dashboard_charts_workload.js grafica de distribucion de carga para admin*/

let workloadChartState = {
    currentMode: 'departments', // 'comparacion' o 'departamento'
    selectedDepartmentId: null,
    selectedDepartmentName: null
};

function initializeWorkloadChart() {
    setupWorkloadDropdownListener();
    loadWorkloadDistribution();
}

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
            
            selectDepartmentForWorkload(deptId, deptName);
        }
    });
}

function selectDepartmentForWorkload(deptId, deptName) {
    workloadChartState.selectedDepartmentId = deptId;
    workloadChartState.selectedDepartmentName = deptName;
    workloadChartState.currentMode = 'projects';
    
    loadProjectWorkload(deptId, deptName);
}

function resetWorkloadToComparison() {
    workloadChartState.selectedDepartmentId = null;
    workloadChartState.selectedDepartmentName = null;
    workloadChartState.currentMode = 'departments';
    
    loadWorkloadDistribution();
}

function loadWorkloadDistribution() {
    
    fetch('../php/get_task_workload_by_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.json();
        })
        .then(data => {
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

function loadProjectWorkload(deptId, deptName) {
    
    fetch(`../php/get_task_workload_by_project.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.json();
        })
        .then(data => {
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

function updateWorkloadChart(data, chartTitle) {
    const ctx = document.getElementById('workloadChart');
    
    if (!ctx) {
        console.warn('Workload chart canvas not found');
        return;
    }
    
    //destruir graficas existentes
    if (dashboardChartsInstance && dashboardChartsInstance.charts && dashboardChartsInstance.charts.workloadChart) {
        dashboardChartsInstance.charts.workloadChart.destroy();
    }
    
    //revisar si hay info vacia
    if (!data.labels || data.labels.length === 0) {
        console.warn('No data available for chart');
        showWorkloadChartError('No hay datos disponibles para mostrar');
        return;
    }
    
    //mostrar canva y quitar mensajes de error
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
    
    //revisar que exista
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
}

function showWorkloadChartError(message) {
    const ctx = document.getElementById('workloadChart');
    if (!ctx) return;
    
    ctx.style.display = 'none';
    const parent = ctx.parentElement;
    
    let errorDiv = parent.querySelector('.chart-error');//eliminar errores existentes
    if (errorDiv) {
        errorDiv.remove();
    }
    
    errorDiv = document.createElement('div');//crear mensaje de error
    errorDiv.className = 'chart-error alert alert-warning';
    errorDiv.innerHTML = `<i class="mdi mdi-alert-circle"></i> ${message}`;
    parent.appendChild(errorDiv);
}

function refreshWorkloadChart() {
    
    if (workloadChartState.currentMode === 'projects' && workloadChartState.selectedDepartmentId) {
        loadProjectWorkload(workloadChartState.selectedDepartmentId, workloadChartState.selectedDepartmentName);
    } else {
        loadWorkloadDistribution();
    }
}

function syncWorkloadWithDashboard() {
    if (dashboardChartsInstance.currentDepartment) {
        const dept = dashboardChartsInstance.currentDepartment;
        selectDepartmentForWorkload(dept.id, dept.name);
    } else {
        resetWorkloadToComparison();
    }
}

window.selectDepartmentWorkload = selectDepartmentForWorkload;//exportar funciones para uso externo
window.resetWorkloadView = resetWorkloadToComparison;
window.refreshWorkloadChart = refreshWorkloadChart;

document.addEventListener('DOMContentLoaded', function() {
    const workloadCanvas = document.getElementById('workloadChart');
    if (workloadCanvas) {
        initializeWorkloadChart();
    }
});