/* dashboard_charts_workload.js Maneja el gráfico de pastel de distribución de carga de trabajo*/

let currentViewMode = 'departments'; // 'departments' o 'projects'
let selectedDepartmentId = null;
let selectedDepartmentName = null;

function initializeWorkloadChart() {
    console.log('Inicializando gráfico de distribución de carga de trabajo...');
    setupDepartmentDropdownListener();
    loadWorkloadDistribution();
}

function setupDepartmentDropdownListener() {
    const dropdown = document.getElementById('messageDropdown');
    
    if (!dropdown) {
        console.warn('Dropdown de departamentos no encontrado con ID "messageDropdown"');
        return;
    }

    const dropdownMenu = dropdown.nextElementSibling;// Escuchar clicks en los items del dropdown
    
    if (!dropdownMenu) {
        console.warn('Dropdown menu no encontrado');
        return;
    }

    dropdownMenu.addEventListener('click', function(e) {//eventos para items de dropdown
        const departmentItem = e.target.closest('[data-department-id]');
        
        if (departmentItem) {
            const deptId = parseInt(departmentItem.getAttribute('data-department-id'));
            const deptName = departmentItem.getAttribute('data-department-name') || 'Departamento';
            
            console.log('Departamento seleccionado:', deptId, deptName);
            selectDepartment(deptId, deptName);
        }
    });

    console.log('Listener de dropdown de departamentos configurado');
}

function selectDepartment(deptId, deptName) {
    selectedDepartmentId = deptId;
    selectedDepartmentName = deptName;
    currentViewMode = 'projects';
    
    console.log(`Cargando proyectos para departamento: ${deptName} (ID: ${deptId})`);
    loadProjectWorkload(deptId, deptName);
    
    updateDropdownButtonText(deptName);//Actualizar el texto del dropdown sin romper la estructura
}

function resetToAllDepartments() {
    selectedDepartmentId = null;
    selectedDepartmentName = null;
    currentViewMode = 'departments';
    
    console.log('Volviendo a vista de departamentos...');
    loadWorkloadDistribution();
    
    resetDropdownButtonText();//texto original del dropdown
}

function updateDropdownButtonText(deptName) {
    const dropdownButton = document.getElementById('messageDropdown');
    if (!dropdownButton) return;
    
    let textSpan = dropdownButton.querySelector('.dropdown-text');//encontrar o crear span para el texto
    
    if (!textSpan) {
        const originalText = dropdownButton.textContent.trim();
        textSpan = document.createElement('span');
        textSpan.className = 'dropdown-text';
        textSpan.textContent = originalText;
        
        dropdownButton.innerHTML = '';//LIMPIAR Y CREAR LA ESTRUCTURA DEL BOTON
        dropdownButton.appendChild(textSpan);
    }
    
    textSpan.textContent = deptName;
}

function resetDropdownButtonText() {
    const dropdownButton = document.getElementById('messageDropdown');
    if (!dropdownButton) return;
    
    let textSpan = dropdownButton.querySelector('.dropdown-text');//texto original
    
    if (textSpan) {
        textSpan.textContent = 'Seleccionar Categoría';
    } else {
        dropdownButton.textContent = 'Seleccionar Categoría';
    }
}

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
                const chartTitle = deptName;
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
    
    //destruir chart actual si existe
    if (dashboardChartsInstance && dashboardChartsInstance.charts && dashboardChartsInstance.charts.workloadChart) {
        dashboardChartsInstance.charts.workloadChart.destroy();
    }
    
    if (!data.labels || data.labels.length === 0) {//revisar si la data esta vacia
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
    
    ctx.style.display = 'block';//mostrar el canva si estaba oculto
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
                        const dataIndex = context.dataIndex;//mostrar explicacion de estados de tareas
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

window.selectDepartmentWorkload = function(deptId, deptName) {
    selectDepartment(deptId, deptName);
};

window.resetWorkloadView = function() {
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