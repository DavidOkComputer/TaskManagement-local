/**
 * dashboard_charts_workload.js (Enhanced)
 * Extension para dashboard_charts.js
 * Maneja el gráfico de pastel de distribución de carga de trabajo
 * 
 * FEATURES:
 * - Muestra distribución de carga por departamento (por defecto)
 * - Al seleccionar un departamento en el dropdown, muestra carga por proyectos
 * - Auto-refresh cada 60 segundos
 * - Detección automática de cambios en el dropdown
 */

// Variable global para rastrear el modo de visualización
let currentViewMode = 'departments'; // 'departments' o 'projects'
let selectedDepartmentId = null;
let selectedDepartmentName = null;

/**
 * Initialize workload distribution chart on page load
 */
function initializeWorkloadChart() {
    console.log('Inicializando gráfico de distribución de carga de trabajo...');
    setupDepartmentDropdownListener();
    loadWorkloadDistribution();
}

/**
 * Setup listener for department dropdown changes
 */
function setupDepartmentDropdownListener() {
    const dropdown = document.getElementById('messageDropdown');
    
    if (!dropdown) {
        console.warn('Dropdown de departamentos no encontrado con ID "messageDropdown"');
        return;
    }

    // Escuchar clicks en los items del dropdown
    const dropdownMenu = dropdown.nextElementSibling;
    
    if (!dropdownMenu) {
        console.warn('Dropdown menu no encontrado');
        return;
    }

    // Delegación de eventos para items del dropdown
    dropdownMenu.addEventListener('click', function(e) {
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

/**
 * Select department and update chart
 */
function selectDepartment(deptId, deptName) {
    selectedDepartmentId = deptId;
    selectedDepartmentName = deptName;
    currentViewMode = 'projects';
    
    console.log(`Cargando proyectos para departamento: ${deptName} (ID: ${deptId})`);
    loadProjectWorkload(deptId, deptName);
    
    // Actualizar el texto del dropdown sin romper la estructura
    updateDropdownButtonText(deptName);
}

/**
 * Reset to departments view
 */
function resetToAllDepartments() {
    selectedDepartmentId = null;
    selectedDepartmentName = null;
    currentViewMode = 'departments';
    
    console.log('Volviendo a vista de departamentos...');
    loadWorkloadDistribution();
    
    // Resetear texto del dropdown a original
    resetDropdownButtonText();
}

/**
 * Safely update dropdown button text without breaking dropdown functionality
 */
function updateDropdownButtonText(deptName) {
    const dropdownButton = document.getElementById('messageDropdown');
    if (!dropdownButton) return;
    
    // Find or create a span to hold the text (preserves dropdown structure)
    let textSpan = dropdownButton.querySelector('.dropdown-text');
    
    if (!textSpan) {
        // First time: wrap existing text in span
        const originalText = dropdownButton.textContent.trim();
        textSpan = document.createElement('span');
        textSpan.className = 'dropdown-text';
        textSpan.textContent = originalText;
        
        // Clear and recreate button structure
        dropdownButton.innerHTML = '';
        dropdownButton.appendChild(textSpan);
    }
    
    // Update only the text content, not the entire button
    textSpan.textContent = deptName;
}

/**
 * Reset dropdown button text to original
 */
function resetDropdownButtonText() {
    const dropdownButton = document.getElementById('messageDropdown');
    if (!dropdownButton) return;
    
    // Reset to original text
    let textSpan = dropdownButton.querySelector('.dropdown-text');
    
    if (textSpan) {
        textSpan.textContent = 'Seleccionar Categoría';
    } else {
        // Fallback if span doesn't exist
        dropdownButton.textContent = 'Seleccionar Categoría';
    }
}

/**
 * Load workload data from API endpoint (all departments)
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
                showChartError('No hay datos disponibles');
            }
        })
        .catch(error => {
            console.error('Error loading workload distribution:', error.message);
            showChartError('Error al cargar datos');
        });
}

/**
 * Load project workload for a specific department
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

/**
 * Update workload pie chart with data
 */
function updateWorkloadChart(data, chartTitle = 'Distribución de Carga de Trabajo por Departamento') {
    const ctx = document.getElementById('workloadChart');
    
    if (!ctx) {
        console.warn('Workload chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (dashboardChartsInstance && dashboardChartsInstance.charts && dashboardChartsInstance.charts.workloadChart) {
        dashboardChartsInstance.charts.workloadChart.destroy();
    }
    
    // Check if data is empty
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
    
    // Show canvas if it was hidden
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
                        // Show breakdown of task statuses
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

/**
 * Show error message in chart area
 */
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

/**
 * Refresh workload chart
 */
function refreshWorkloadChart() {
    console.log('Refrescando gráfico de carga de trabajo...');
    
    if (currentViewMode === 'projects' && selectedDepartmentId) {
        loadProjectWorkload(selectedDepartmentId, selectedDepartmentName);
    } else {
        loadWorkloadDistribution();
    }
}

/**
 * Hook into existing chart initialization
 */
function initializeDashboardChartsWithWorkload() {
    // Original initialization
    initializeDashboardCharts();
    
    // Add workload chart
    initializeWorkloadChart();
}

/**
 * Auto-refresh functionality for workload chart
 */
function startWorkloadChartAutoRefresh(intervalSeconds = 60) {
    console.log('Iniciando auto-actualización de gráfico de carga de trabajo cada ' + intervalSeconds + ' segundos');
    
    setInterval(function() {
        refreshWorkloadChart();
    }, intervalSeconds * 1000);
}

/**
 * Public function to manually select department (for external use)
 */
window.selectDepartmentWorkload = function(deptId, deptName) {
    selectDepartment(deptId, deptName);
};

/**
 * Public function to reset view (for external use)
 */
window.resetWorkloadView = function() {
    resetToAllDepartments();
};

// Initialize on DOM ready if workload chart canvas exists
document.addEventListener('DOMContentLoaded', function() {
    const workloadCanvas = document.getElementById('workloadChart');
    if (workloadCanvas) {
        console.log('Workload chart canvas detectado, inicializando...');
        initializeWorkloadChart();
        
        // Optionally start auto-refresh
        // Uncomment the following line to enable auto-refresh every 60 seconds
        // startWorkloadChartAutoRefresh(60);
    }
});