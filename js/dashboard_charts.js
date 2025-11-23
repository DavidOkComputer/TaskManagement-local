/**
 * dashboard_charts.js (UPDATED WITH ENHANCED SCATTER TOOLTIP)
 * Integra gráficos Chart.js con datos dinámicos del dashboard
 * Incluye área chart para tendencias de tareas completadas
 * Incluye scatter chart para matriz de eficiencia departamental
 * Muestra comparación entre departamentos cuando no hay uno seleccionado
 * Incluye gráfico de línea de tendencia de proyectos completados
 * Updated with official brand colors: Green, Black, Gray, Ice
 * 
 * ENHANCEMENT: Scatter chart tooltip now displays person/department name
 * prominently with corresponding numbers for better visibility
 */

let dashboardChartsInstance = {
    charts: {},
    currentDepartment: null,
    // Official brand colors: Green, Black, Gray, Ice
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
 * Shorten long project titles for display
 * Truncates to 25 characters and adds ellipsis if longer
 */
function shortenProjectTitle(title, maxLength = 25) {
    if (!title) return '';
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength) + '...';
}

/**
 * Initialize all charts on page load
 */
function initializeDashboardCharts() {
    console.log('Inicializando gráficos del dashboard...');
    
    // Load user's department and show their data by default
    loadUserDepartmentView();
    
    // Initialize line chart
    initializeLineChart();
    
    // Initialize area chart
    initializeAreaChart();
    
    // Initialize scatter chart - Department Efficiency Matrix
    initializeScatterChart();
}

/**
 * Initialize scatter chart - Department Efficiency Matrix or Person Efficiency
 * Automatically switches between:
 * - Department view (all departments comparison)
 * - Person view (selected department, shows individual employees)
 */
function initializeScatterChart() {
    console.log('Inicializando gráfico de dispersión...');
    
    // Check if a department is actually selected
    const hasDepartmentSelected = dashboardChartsInstance.currentDepartment && 
                                  dashboardChartsInstance.currentDepartment.id &&
                                  dashboardChartsInstance.currentDepartment.id > 0;
    
    console.log('Scatter chart - Department selected?', hasDepartmentSelected);
    console.log('Current department state:', dashboardChartsInstance.currentDepartment);
    
    if (hasDepartmentSelected) {
        // Load person efficiency for selected department
        console.log('Loading person efficiency for:', dashboardChartsInstance.currentDepartment.name);
        loadPersonEfficiencyByDepartment(
            dashboardChartsInstance.currentDepartment.id,
            dashboardChartsInstance.currentDepartment.name
        );
    } else {
        // Load department efficiency comparison
        console.log('Loading department efficiency comparison (all departments)');
        loadDepartmentEfficiency();
    }
}

/**
 * DEBUG HELPER: Log current chart state
 * Call this in console to see what's happening
 */
function debugChartState() {
    console.log('╔════════════════════════════════════════╗');
    console.log('║ DASHBOARD CHARTS STATE DEBUG           ║');
    console.log('╚════════════════════════════════════════╝');
    console.log('Current Department:', dashboardChartsInstance.currentDepartment);
    console.log('Active Charts:');
    console.log('  - Line Chart:', dashboardChartsInstance.charts.lineChart ? '✅ Active' : '❌ Not initialized');
    console.log('  - Area Chart:', dashboardChartsInstance.charts.areaChart ? '✅ Active' : '❌ Not initialized');
    console.log('  - Scatter Chart:', dashboardChartsInstance.charts.scatterChart ? '✅ Active' : '❌ Not initialized');
    console.log('  - Bar Chart:', dashboardChartsInstance.charts.barChart ? '✅ Active' : '❌ Not initialized');
    console.log('  - Doughnut Chart:', dashboardChartsInstance.charts.doughnutChart ? '✅ Active' : '❌ Not initialized');
    console.log('╚════════════════════════════════════════╝');
}

/**
 * Load person efficiency data for a specific department
 */
function loadPersonEfficiencyByDepartment(deptId, deptName) {
    console.log(`📊 Loading person efficiency for department: ${deptName} (ID: ${deptId})`);
    
    // Validate state matches
    if (!dashboardChartsInstance.currentDepartment || 
        dashboardChartsInstance.currentDepartment.id !== deptId) {
        console.warn('⚠️ State mismatch! Current dept:', dashboardChartsInstance.currentDepartment, 'Requested:', deptId);
    }
    
    fetch(`../php/get_person_efficiency_by_department.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for person scatter chart:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }
            
            try {
                const data = JSON.parse(text);
                console.log('✅ Person efficiency data loaded:', data.data.details.length, 'people');
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response preview:', text.substring(0, 200));
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                console.log('Updating scatter chart to person mode');
                updateScatterChart(data.data, 'person', deptName);
            } else {
                console.warn('Error in person efficiency data:', data.message);
                // Don't fall back - keep showing what we have
            }
        })
        .catch(error => {
            console.error('❌ Error loading person efficiency:', error.message);
            console.warn('Falling back to department view');
            loadDepartmentEfficiency();
        });
}

/**
 * Load department efficiency data for scatter chart
 */
function loadDepartmentEfficiency() {
    console.log('Cargando datos de eficiencia departamental...');
    
    fetch('../php/get_department_efficiency.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for scatter chart:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }
            
            try {
                const data = JSON.parse(text);
                console.log('Datos de eficiencia departamental cargados:', data);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response preview:', text.substring(0, 200));
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                updateScatterChart(data.data);
            } else {
                console.warn('Error en datos de eficiencia:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading department efficiency:', error.message);
            console.warn('DEBUG: Check the following:');
            console.warn('1. Is get_department_efficiency.php deployed to /php/?');
            console.warn('2. Open browser Network tab (F12) to see API response');
        });
}

/**
 * Update scatter chart with department or person efficiency data
 * Supports two modes:
 * - 'department': Shows all departments (comparison view)
 * - 'person': Shows individuals in selected department
 */
function updateScatterChart(data, mode = 'department', deptName = null) {
    const ctx = document.getElementById('scatterChart');
    
    if (!ctx) {
        console.warn('Scatter chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (dashboardChartsInstance.charts.scatterChart) {
        dashboardChartsInstance.charts.scatterChart.destroy();
    }
    
    // Prepare chart title based on mode
    let chartTitle = 'Matriz de Eficiencia Departamental';
    let xAxisLabel = 'Carga de Trabajo (Total de Tareas)';
    let yAxisLabel = 'Tasa de Completación (%)';
    
    if (mode === 'person' && deptName) {
        chartTitle = `Eficiencia de Personas - ${deptName}`;
        xAxisLabel = 'Tareas Asignadas a la Persona';
        yAxisLabel = 'Tasa de Completación (%)';
    }
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            x: {
                beginAtZero: true,
                type: 'linear',
                position: 'bottom',
                title: {
                    display: true,
                    text: xAxisLabel,
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15,
                    color: 'rgba(50, 50, 50, 1)'
                },
                ticks: {
                    font: {
                        size: 11
                    },
                    stepSize: 1,
                    color: 'rgba(80, 80, 80, 1)'
                },
                grid: {
                    color: 'rgba(200, 205, 210, 0.2)',
                    drawBorder: true
                }
            },
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: yAxisLabel,
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15,
                    color: 'rgba(50, 50, 50, 1)'
                },
                ticks: {
                    font: {
                        size: 11
                    },
                    callback: function(value) {
                        return value + '%';
                    },
                    stepSize: 10,
                    color: 'rgba(80, 80, 80, 1)'
                },
                grid: {
                    color: 'rgba(200, 205, 210, 0.2)',
                    drawBorder: true
                }
            }
        },
        plugins: {
            filler: {
                propagate: true
            },
            legend: {
                display: true,
                position: 'top',
                labels: {
                    font: {
                        size: 12
                    },
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    color: 'rgba(50, 50, 50, 1)'
                }
            },
            title: {
                display: true,
                text: chartTitle,
                font: {
                    size: 15,
                    weight: 'bold'
                },
                padding: 20,
                color: 'rgba(34, 139, 89, 1)'
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.95)',
                titleFont: {
                    size: 16,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                },
                padding: 16,
                displayColors: false,
                borderColor: 'rgba(34, 139, 89, 1)',
                borderWidth: 2,
                usePointStyle: false,
                callbacks: {
                    // Enhanced title showing department/person with icon - MORE PROMINENT
                    title: function(context) {
                        if (context.length > 0) {
                            const label = context[0].raw.label || 'Elemento';
                            const icon = mode === 'person' ? '👤' : '🏢';
                            return icon + ' ' + label;
                        }
                        return '';
                    },
                    // Main information lines - CLEANER FORMAT
                    label: function(context) {
                        const point = context.raw;
                        const workloadLabel = mode === 'person' ? 'Tareas Asignadas' : 'Carga de Trabajo';
                        
                        return [
                            '━━━━━━━━━━━━━━━━━━',
                            '📊 ' + workloadLabel + ': ' + point.x + ' tarea' + (point.x !== 1 ? 's' : ''),
                            '⚡ Eficiencia: ' + point.y + '%',
                            '━━━━━━━━━━━━━━━━━━'
                        ];
                    },
                    // Additional details section - ONLY IF AVAILABLE
                    afterLabel: function(context) {
                        const raw = context.raw;
                        // Find the detail object for this point
                        const detail = data.details.find(d => d.nombre_completo === raw.label || d.nombre === raw.label);
                        
                        if (detail) {
                            const lines = [
                                '📋 Detalles:'
                            ];
                            
                            if (detail.completadas !== undefined) lines.push('  ✅ Completadas: ' + detail.completadas);
                            if (detail.en_proceso !== undefined) lines.push('  ⏳ En Proceso: ' + detail.en_proceso);
                            if (detail.pendientes !== undefined) lines.push('  ⏸️  Pendientes: ' + detail.pendientes);
                            if (detail.vencidas !== undefined) lines.push('  ⚠️  Vencidas: ' + detail.vencidas);
                            
                            return lines;
                        }
                        return '';
                    }
                }
            }
        }
    };
    
    // Add quadrant guidelines for department mode
    if (mode === 'department') {
        options.plugins.annotation = {
            annotations: {
                // Average efficiency line
                averageLine: {
                    type: 'line',
                    yMin: data.avg_completion,
                    yMax: data.avg_completion,
                    borderColor: 'rgba(34, 139, 89, 0.4)',
                    borderWidth: 2,
                    borderDash: [5, 5]
                }
            }
        };
    }
    
    dashboardChartsInstance.charts.scatterChart = new Chart(ctx, {
        type: 'bubble',
        data: {
            datasets: data.datasets
        },
        options: options
    });
    
    // Add legend text below chart
    addScatterChartLegend(mode, deptName, data);
    
    console.log(`Gráfico de dispersión actualizado: ${chartTitle}`);
}

/**
 * Add contextual legend below scatter chart
 */
function addScatterChartLegend(mode, deptName, data) {
    const canvasContainer = document.getElementById('scatterChart').parentElement;
    
    // Remove old legend if exists
    const oldLegend = canvasContainer.querySelector('.scatter-chart-legend');
    if (oldLegend) {
        oldLegend.remove();
    }
    
    // Create legend div
    const legendDiv = document.createElement('div');
    legendDiv.className = 'scatter-chart-legend';
    legendDiv.style.cssText = `
        margin-top: 15px;
        padding: 12px;
        background-color: rgba(200, 205, 210, 0.15);
        border-left: 4px solid rgba(34, 139, 89, 1);
        border-radius: 4px;
        font-size: 13px;
        line-height: 1.6;
        color: rgba(50, 50, 50, 1);
    `;
    
    let legendHTML = '';
    
    if (mode === 'person') {
        legendHTML = `
            <strong>📍 Guía de Lectura - Eficiencia de Personas:</strong><br>
            <span style="margin-left: 15px;">
                🟢 <strong>Superior-Derecha:</strong> Alto rendimiento (Muchas tareas, alta eficiencia)<br>
                🟡 <strong>Superior-Izquierda:</strong> Especialista eficiente (Pocas tareas, muy eficiente)<br>
                🔵 <strong>Inferior-Derecha:</strong> Necesita apoyo (Muchas tareas, baja eficiencia)<br>
                ⚪ <strong>Inferior-Izquierda:</strong> Capacidad disponible (Pocas tareas, baja eficiencia)
            </span>
        `;
    } else {
        legendHTML = `
            <strong>📍 Guía de Lectura - Eficiencia Departamental:</strong><br>
            <span style="margin-left: 15px;">
                🟢 <strong>Superior-Derecha:</strong> Departamentos estrella (Alta carga, alta eficiencia)<br>
                🟡 <strong>Superior-Izquierda:</strong> Especialización (Baja carga, alta eficiencia)<br>
                🔴 <strong>Inferior-Derecha:</strong> Requieren recursos (Alta carga, baja eficiencia)<br>
                ⚪ <strong>Inferior-Izquierda:</strong> Capacidad para crecer (Baja carga, baja eficiencia)<br>
                <strong style="color: rgba(34, 139, 89, 1);">━━━ Línea punteada:</strong> Promedio de eficiencia organizacional
            </span>
        `;
    }
    
    legendDiv.innerHTML = legendHTML;
    canvasContainer.appendChild(legendDiv);
}

/**
 * Initialize area chart on page load
 */
function initializeAreaChart() {
    console.log('Inicializando gráfico de área de tendencias de tareas...');
    
    // Load user's department for area chart
    loadUserDepartmentForAreaChart();
}

/**
 * Load user's department for area chart
 */
function loadUserDepartmentForAreaChart() {
    console.log('Cargando vista de departamento del usuario para gráfico de área...');
    
    fetch('../php/get_user_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error fetching user department');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.department) {
                const userDept = data.department;
                console.log('Cargando tendencia de tareas del departamento:', userDept.nombre);
                loadTaskTrendForDepartment(userDept.id_departamento, userDept.nombre);
            } else {
                console.warn('No se pudo obtener el departamento del usuario, mostrando comparación');
                loadTaskTrendComparison();
            }
        })
        .catch(error => {
            console.error('Error loading user department for area chart:', error);
            loadTaskTrendComparison();
        });
}

/**
 * Load task trends for a specific department
 */
function loadTaskTrendForDepartment(deptId, deptName) {
    console.log(`Cargando tendencia de tareas para: ${deptName}`);
    
    fetch(`../php/get_task_trends.php?id_departamento=${deptId}&weeks=12`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for department area chart:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }
            
            try {
                const data = JSON.parse(text);
                console.log('Datos de tendencia de tareas cargados:', data);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response preview:', text.substring(0, 200));
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                updateAreaChart(data, 'single', deptName);
            } else {
                console.warn('Error en datos de tendencia de tareas:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading task trends:', error.message);
            console.error('Department:', deptName, 'ID:', deptId);
        });
}

/**
 * Load comparison view - all departments task trends
 */
function loadTaskTrendComparison() {
    console.log('Cargando vista de comparación de tendencias de tareas (todos los departamentos)');
    
    fetch('../php/get_task_trends.php?weeks=12')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for comparison area chart:', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response. Check: 1) Is get_task_trends.php deployed? 2) Is PHP file in correct location (/php/)?');
            }
            
            try {
                const data = JSON.parse(text);
                console.log('JSON parsed successfully:', data);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text.substring(0, 200));
                throw new Error('Invalid JSON from API. Response: ' + text.substring(0, 100) + '... Check PHP file for errors.');
            }
        })
        .then(data => {
            if (data.success && data.data) {
                console.log('Datos de comparación de tareas cargados:', data);
                updateAreaChart(data, 'comparison');
            } else {
                console.error('Error en datos de comparación:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error loading comparison task trends:', error.message);
            console.error('Full error:', error);
            console.warn('DEBUG: Check the following:');
            console.warn('1. Is get_task_trends.php deployed to /php/?');
            console.warn('2. Open browser Network tab (F12) to see API response');
        });
}

/**
 * Update area chart with task trends data
 */
function updateAreaChart(data, mode, deptName = null) {
    const ctx = document.getElementById('areaChart');
    
    if (!ctx) {
        console.warn('Area chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (dashboardChartsInstance.charts.areaChart) {
        dashboardChartsInstance.charts.areaChart.destroy();
    }
    
    // Prepare chart title
    let chartTitle = 'Tendencia de Tareas Completadas';
    if (mode === 'single' && deptName) {
        chartTitle = `Tareas Completadas - ${deptName}`;
    } else if (mode === 'comparison') {
        chartTitle = 'Comparación de Tareas Completadas - Todos los Departamentos';
    }
    
    // Prepare chart data
    const chartData = {
        labels: data.data.labels,
        datasets: data.data.datasets
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: mode === 'comparison', // Stack for comparison view
                ticks: {
                    stepSize: mode === 'single' ? 1 : undefined,
                    font: {
                        size: 11
                    }
                },
                title: {
                    display: true,
                    text: mode === 'single' ? 'Tareas Completadas (Acumulativo)' : 'Tareas Completadas'
                }
            },
            x: {
                stacked: mode === 'comparison', // Stack for comparison view
                ticks: {
                    font: {
                        size: 10
                    }
                },
                title: {
                    display: true,
                    text: 'Semanas'
                }
            }
        },
        plugins: {
            filler: {
                propagate: true
            },
            legend: {
                display: true,
                position: 'top',
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
                        const label = context.dataset.label || '';
                        const value = context.parsed.y || 0;
                        return label + ': ' + value + ' tareas';
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: {
                    size: 12
                },
                bodyFont: {
                    size: 11
                },
                padding: 10,
                displayColors: true
            }
        }
    };
    
    dashboardChartsInstance.charts.areaChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: options
    });
    
    console.log('Gráfico de área actualizado: ' + chartTitle);
}

/**
 * Initialize line chart on page load
 */
function initializeLineChart() {
    console.log('Inicializando gráfico de línea de tendencias...');
    
    // Load user's department and show their data by default
    loadUserDepartmentForLineChart();
}

/**
 * Load user's department for line chart
 */
function loadUserDepartmentForLineChart() {
    console.log('Cargando vista de departamento del usuario para gráfico de línea...');
    
    fetch('../php/get_user_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error fetching user department');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.department) {
                const userDept = data.department;
                console.log('Cargando tendencia del departamento:', userDept.nombre);
                loadProjectTrendForDepartment(userDept.id_departamento, userDept.nombre);
            } else {
                console.warn('No se pudo obtener el departamento del usuario, mostrando comparación');
                loadProjectTrendComparison();
            }
        })
        .catch(error => {
            console.error('Error loading user department for line chart:', error);
            loadProjectTrendComparison();
        });
}

/**
 * Load project trends for a specific department
 */
function loadProjectTrendForDepartment(deptId, deptName) {
    console.log(`Cargando tendencia de proyectos para: ${deptName}`);
    
    fetch(`../php/get_project_trends.php?id_departamento=${deptId}&weeks=12`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response for department:', text);
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response');
            }
            
            try {
                const data = JSON.parse(text);
                console.log('Datos de tendencia cargados:', data);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response preview:', text.substring(0, 200));
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                updateLineChart(data, 'single', deptName);
            } else {
                console.warn('Error en datos de tendencia:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading project trends:', error.message);
            console.error('Department:', deptName, 'ID:', deptId);
        });
}

/**
 * Load comparison view - all departments project trends
 */
function loadProjectTrendComparison() {
    console.log('Cargando vista de comparación de tendencias (todos los departamentos)');
    
    fetch('../php/get_project_trends.php?weeks=12')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error, status = ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw API response:', text);
            
            if (!text || text.trim() === '') {
                throw new Error('API returned empty response. Check: 1) Is get_project_trends.php deployed? 2) Is PHP file in correct location (/php/)?');
            }
            
            try {
                const data = JSON.parse(text);
                console.log('JSON parsed successfully:', data);
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text.substring(0, 200));
                throw new Error('Invalid JSON from API. Response: ' + text.substring(0, 100) + '... Check PHP file for errors.');
            }
        })
        .then(data => {
            if (data.success && data.data) {
                console.log('Datos de comparación cargados:', data);
                updateLineChart(data, 'comparison');
            } else {
                console.error('Error en datos de comparación:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error loading comparison trends:', error.message);
            console.error('Full error:', error);
            console.warn('DEBUG: Check the following:');
            console.warn('1. Is get_project_trends.php deployed to /php/?');
            console.warn('2. Open browser Network tab (F12) to see API response');
            console.warn('3. Run diagnose_project_trends.php to test connection');
        });
}

/**
 * Update line chart with project trends data
 */
function updateLineChart(data, mode, deptName = null) {
    const ctx = document.getElementById('lineChart');
    
    if (!ctx) {
        console.warn('Line chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (dashboardChartsInstance.charts.lineChart) {
        dashboardChartsInstance.charts.lineChart.destroy();
    }
    
    // Prepare chart title
    let chartTitle = 'Tendencia de Proyectos Completados';
    if (mode === 'single' && deptName) {
        chartTitle = `Tendencia - ${deptName}`;
    } else if (mode === 'comparison') {
        chartTitle = 'Comparación de Tendencias - Todos los Departamentos';
    }
    
    // Prepare chart data
    const chartData = {
        labels: data.data.labels,
        datasets: data.data.datasets
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 11
                    }
                },
                title: {
                    display: true,
                    text: 'Proyectos Completados (Acumulativo)'
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 10
                    }
                },
                title: {
                    display: true,
                    text: 'Semanas'
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
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
                        const label = context.dataset.label || '';
                        const value = context.parsed.y || 0;
                        return label + ': ' + value + ' proyectos';
                    }
                },
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: {
                    size: 12
                },
                bodyFont: {
                    size: 11
                },
                padding: 10,
                displayColors: true
            }
        }
    };
    
    dashboardChartsInstance.charts.lineChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: options
    });
    
    console.log('Gráfico de línea actualizado: ' + chartTitle);
}

/**
 * Load and display user's own department data on page load
 */
function loadUserDepartmentView() {
    console.log('Cargando vista del departamento del usuario...');
    
    // Fetch current user's department
    fetch('../php/get_user_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error fetching user department');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.department) {
                const userDept = data.department;
                console.log('Departamento del usuario:', userDept);
                
                // Set current department to user's department
                dashboardChartsInstance.currentDepartment = {
                    id: userDept.id_departamento,
                    name: userDept.nombre,
                    isUserDept: true
                };
                
                // Update dropdown button to show user's department
                updateDropdownButtonText(userDept.nombre);
                
                // Load and display user's department data
                loadDepartmentView(userDept.id_departamento, userDept.nombre);
            } else {
                console.warn('No se pudo obtener el departamento del usuario:', data.message);
                // Fallback to comparison view if department not found
                loadComparisonView();
            }
        })
        .catch(error => {
            console.error('Error loading user department:', error);
            // Fallback to comparison view on error
            loadComparisonView();
        });
}

/**
 * Load comparison view - all departments data
 */
function loadComparisonView() {
    console.log('Cargando vista de comparación (todos los departamentos)');
    console.log('Reseteando estado a comparación...');
    
    // Reset the current department state
    dashboardChartsInstance.currentDepartment = null;
    
    // Fetch all projects and departments
    Promise.all([
        fetch('../php/get_departments.php').then(r => r.json()),
        fetch('../php/get_projects.php').then(r => r.json())
    ])
    .then(([deptResponse, projResponse]) => {
        if (deptResponse.success && projResponse.success) {
            const departments = deptResponse.departamentos;
            const projects = projResponse.proyectos;
            
            console.log('Datos de comparación obtenidos - actualizando gráficos...');
            
            // Process data for comparison charts (bar + doughnut)
            processComparisonData(departments, projects);
            
            console.log('Gráficos de bar y doughnut actualizados');
        } else {
            console.error('Error fetching data for comparison view');
        }
    })
    .catch(error => {
        console.error('Error in loadComparisonView:', error);
    });
}

/**
 * Process and display comparison data
 */
function processComparisonData(departments, projects) {
    console.log('Procesando datos de comparación...');
    console.log('Departamentos:', departments.length);
    console.log('Proyectos:', projects.length);
    
    // Prepare data for bar chart - Completed Projects by Department
    const completedByDept = prepareCompletedProjectsByDepartment(departments, projects);
    
    // Prepare data for doughnut chart - Project Status Distribution
    const statusDistribution = prepareProjectStatusDistribution(projects);
    
    // Update charts
    updateBarChart(completedByDept);
    updateDoughnutChart(statusDistribution);
}

/**
 * Prepare data: Completed projects by department
 * Returns: { labels: [...], data: [...], colors: [...] }
 */
function prepareCompletedProjectsByDepartment(departments, projects) {
    console.log('Preparando: Proyectos completados por departamento');
    
    const data = {
        labels: [],
        data: [],
        backgroundColor: [],
        borderColor: [],
        borderWidth: 1
    };
    
    departments.forEach((dept, index) => {
        // Count completed projects for this department
        const completedCount = projects.filter(proj => 
            proj.area === dept.nombre && proj.estado === 'completado'
        ).length;
        
        data.labels.push(dept.nombre);
        data.data.push(completedCount);
        data.backgroundColor.push(dashboardChartsInstance.departmentColors[index % dashboardChartsInstance.departmentColors.length]);
        data.borderColor.push(dashboardChartsInstance.departmentBorderColors[index % dashboardChartsInstance.departmentBorderColors.length]);
    });
    
    console.log('Datos preparados:', data);
    return data;
}

/**
 * Prepare data: Project status distribution
 * Uses official brand colors: Green (completed), Gray (in progress), Ice (pending), Black (overdue)
 * Returns: { labels: [...], data: [...], backgroundColor: [...] }
 */
function prepareProjectStatusDistribution(projects) {
    console.log('Preparando: Distribución de estados de proyectos');
    
    const statusCounts = {
        'completado': 0,
        'en proceso': 0,
        'pendiente': 0,
        'vencido': 0
    };
    
    // Count projects by status
    projects.forEach(proj => {
        const status = proj.estado.toLowerCase();
        if (statusCounts.hasOwnProperty(status)) {
            statusCounts[status]++;
        }
    });
    
    const data = {
        labels: ['Completados', 'En Proceso', 'Pendientes', 'Vencidos'],
        data: [
            statusCounts['completado'],
            statusCounts['en proceso'],
            statusCounts['pendiente'],
            statusCounts['vencido']
        ],
        backgroundColor: [
            'rgba(34, 139, 89, 0.7)',     // Green - Completed
            'rgba(130, 140, 150, 0.7)',   // Gray - In Progress
            'rgba(200, 205, 210, 0.7)',   // Ice - Pending
            'rgba(50, 50, 50, 0.7)'       // Black - Overdue
        ],
        borderColor: [
            'rgba(34, 139, 89, 1)',       // Green
            'rgba(130, 140, 150, 1)',     // Gray
            'rgba(200, 205, 210, 1)',     // Ice
            'rgba(50, 50, 50, 1)'         // Black
        ]
    };
    
    console.log('Datos de estado preparados:', data);
    return data;
}

/**
 * Update Bar Chart - Completed Projects by Department
 */
function updateBarChart(data) {
    const ctx = document.getElementById('barChart');
    
    if (!ctx) {
        console.warn('Bar chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (dashboardChartsInstance.charts.barChart) {
        dashboardChartsInstance.charts.barChart.destroy();
    }
    
    const chartData = {
        labels: data.labels,
        datasets: [{
            label: 'Proyectos Completados',
            data: data.data,
            backgroundColor: data.backgroundColor,
            borderColor: data.borderColor,
            borderWidth: data.borderWidth
        }]
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 11
                    }
                },
                title: {
                    display: true,
                    text: 'Número de Proyectos'
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 11
                    }
                },
                title: {
                    display: true,
                    text: 'Departamento'
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            title: {
                display: true,
                text: 'Proyectos Completados por Departamento'
            }
        }
    };
    
    dashboardChartsInstance.charts.barChart = new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: options
    });
    
    console.log('Bar chart actualizado');
}

/**
 * Update Doughnut Chart - Project Status Distribution
 */
function updateDoughnutChart(data) {
    const ctx = document.getElementById('doughnutChart');
    
    if (!ctx) {
        console.warn('Doughnut chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (dashboardChartsInstance.charts.doughnutChart) {
        dashboardChartsInstance.charts.doughnutChart.destroy();
    }
    
    const chartData = {
        labels: data.labels,
        datasets: [{
            data: data.data,
            backgroundColor: data.backgroundColor,
            borderColor: data.borderColor,
            borderWidth: 1
        }]
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            title: {
                display: true,
                text: 'Distribución de Estados de Proyectos'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    };
    
    dashboardChartsInstance.charts.doughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
    
    console.log('Doughnut chart actualizado');
}

/**
 * Update charts when department is selected
 */
function loadDepartmentView(deptId, deptName) {
    console.log('🏢 SWITCHING TO DEPARTMENT VIEW:', deptName);
    
    // Update state FIRST
    dashboardChartsInstance.currentDepartment = { 
        id: deptId, 
        name: deptName,
        updatedAt: new Date().getTime()
    };
    
    console.log('Department state updated:', dashboardChartsInstance.currentDepartment);
    
    // Fetch projects for this department
    console.log('Loading department-specific projects data...');
    fetch(`../php/get_projects.php?id_departamento=${deptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Projects data received:', data.proyectos.length, 'projects');
                processDepepartmentData(data.proyectos, deptName);
            } else {
                console.error('Error fetching projects:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading department view projects:', error);
        });
    
    // Update line chart for this department
    console.log('Loading line chart for department...');
    loadProjectTrendForDepartment(deptId, deptName);
    
    // Update area chart for this department  
    console.log('Loading area chart for department...');
    loadTaskTrendForDepartment(deptId, deptName);
    
    // UPDATE SCATTER CHART: Switch to person efficiency view for selected department
    console.log('Loading scatter chart (person efficiency) for department...');
    loadPersonEfficiencyByDepartment(deptId, deptName);
    
    console.log('✅ All department-specific charts queued for loading');
}

/**
 * Process and display department-specific data
 */
function processDepepartmentData(projects, deptName) {
    console.log(`Procesando datos del departamento: ${deptName}`);
    console.log(`Total de proyectos: ${projects.length}`);
    
    // Prepare status distribution for this department
    const statusDistribution = prepareDepartmentStatusDistribution(projects);
    
    // Update doughnut chart with department-specific data
    updateDoughnutChartForDepartment(statusDistribution, deptName);
    
    // Update bar chart to show progress if needed
    updateBarChartForDepartment(projects, deptName);
}

/**
 * Prepare department status distribution
 */
function prepareDepartmentStatusDistribution(projects) {
    const statusCounts = {
        'completado': 0,
        'en proceso': 0,
        'pendiente': 0,
        'vencido': 0
    };
    
    projects.forEach(proj => {
        const status = proj.estado.toLowerCase();
        if (statusCounts.hasOwnProperty(status)) {
            statusCounts[status]++;
        }
    });
    
    return {
        labels: ['Completados', 'En Proceso', 'Pendientes', 'Vencidos'],
        data: [
            statusCounts['completado'],
            statusCounts['en proceso'],
            statusCounts['pendiente'],
            statusCounts['vencido']
        ],
        backgroundColor: [
            'rgba(34, 139, 89, 0.7)',     // Green - Completed
            'rgba(130, 140, 150, 0.7)',   // Gray - In Progress
            'rgba(200, 205, 210, 0.7)',   // Ice - Pending
            'rgba(50, 50, 50, 0.7)'       // Black - Overdue
        ],
        borderColor: [
            'rgba(34, 139, 89, 1)',       // Green
            'rgba(130, 140, 150, 1)',     // Gray
            'rgba(200, 205, 210, 1)',     // Ice
            'rgba(50, 50, 50, 1)'         // Black
        ]
    };
}

/**
 * Update doughnut chart for specific department
 */
function updateDoughnutChartForDepartment(data, deptName) {
    const ctx = document.getElementById('doughnutChart');
    
    if (!ctx) {
        console.warn('Doughnut chart canvas not found');
        return;
    }
    
    if (dashboardChartsInstance.charts.doughnutChart) {
        dashboardChartsInstance.charts.doughnutChart.destroy();
    }
    
    const chartData = {
        labels: data.labels,
        datasets: [{
            data: data.data,
            backgroundColor: data.backgroundColor,
            borderColor: data.borderColor,
            borderWidth: 1
        }]
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            title: {
                display: true,
                text: `Distribución de Estados - ${deptName}`
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    };
    
    dashboardChartsInstance.charts.doughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
    });
}

/**
 * Update bar chart for specific department
 * Shows top 5 projects with shortened titles
 * Uses green for completed (100%), gray for in-progress, ice for pending, black for overdue
 */
function updateBarChartForDepartment(projects, deptName) {
    const ctx = document.getElementById('barChart');
    
    if (!ctx) {
        console.warn('Bar chart canvas not found');
        return;
    }
    
    if (dashboardChartsInstance.charts.barChart) {
        dashboardChartsInstance.charts.barChart.destroy();
    }
    
    // Prepare progress data for projects
    const progressData = {
        labels: projects.slice(0, 5).map(p => shortenProjectTitle(p.nombre)), // Show top 5 projects with shortened titles
        data: projects.slice(0, 5).map(p => p.progreso),
        backgroundColor: projects.slice(0, 5).map((p, i) => {
            // Color based on progress - using official brand colors
            if (p.progreso === 100) return 'rgba(34, 139, 89, 0.7)';        // Green - Completed
            if (p.progreso >= 75) return 'rgba(80, 154, 108, 0.7)';         // Green Light
            if (p.progreso >= 50) return 'rgba(130, 140, 150, 0.7)';        // Gray - In Progress
            return 'rgba(200, 205, 210, 0.7)';                              // Ice - Pending
        })
    };
    
    const chartData = {
        labels: progressData.labels,
        datasets: [{
            label: 'Progreso (%)',
            data: progressData.data,
            backgroundColor: progressData.backgroundColor,
            borderColor: progressData.backgroundColor.map(c => c.replace('0.7', '1')),
            borderWidth: 1
        }]
    };
    
    const options = {
        indexAxis: 'y', // Horizontal bar chart
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            x: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    font: { size: 11 }
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            title: {
                display: true,
                text: `Progreso de Proyectos - ${deptName}`
            },
            tooltip: {
                callbacks: {
                    // Show full project title in tooltip
                    label: function(context) {
                        return 'Progreso: ' + context.parsed.x + '%';
                    },
                    // Show full title when hovering
                    title: function(context) {
                        const index = context[0].dataIndex;
                        const fullTitle = projects.slice(0, 5)[index].nombre;
                        return fullTitle;
                    }
                }
            }
        }
    };
    
    dashboardChartsInstance.charts.barChart = new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: options
    });
}

/**
 * Clear department selection and return to comparison view
 * Called when user clears department selection or clicks "Revisar departamentos"
 */
function clearDepartmentSelection() {
    console.log('⭐ CLEARING DEPARTMENT SELECTION - SWITCHING TO COMPARISON MODE');
    console.log('Reseteando state y cargando vista de comparación para TODOS los gráficos...');
    
    // Reset current department FIRST
    dashboardChartsInstance.currentDepartment = null;
    
    // Update dropdown button text
    updateDropdownButtonText('Revisar departamentos');
    
    // SEQUENTIAL LOADING: Ensure each chart loads with comparison data
    // This prevents race conditions and ensures proper state management
    
    console.log('Step 1: Loading bar/doughnut comparison data...');
    loadComparisonView();
    
    // Use setTimeout to ensure sequential loading with slight delays
    // This prevents race conditions where state gets mixed
    
    setTimeout(() => {
        console.log('Step 2: Loading line chart comparison data...');
        loadProjectTrendComparison();
    }, 300);
    
    setTimeout(() => {
        console.log('Step 3: Loading area chart comparison data...');
        loadTaskTrendComparison();
    }, 600);
    
    setTimeout(() => {
        console.log('Step 4: Loading scatter chart department efficiency...');
        loadDepartmentEfficiency();
    }, 900);
    
    setTimeout(() => {
        console.log('✅ ALL CHARTS UPDATED TO COMPARISON MODE');
        console.log('Current department state:', dashboardChartsInstance.currentDepartment);
    }, 1200);
}

/**
 * Handle department selection from dropdown
 * This is called when user selects a department from the dropdown
 * Ensures ALL charts including area chart are updated
 */
function selectDepartmentFromDropdown(deptId, deptName) {
    console.log('═══════════════════════════════════════════════════════');
    console.log('👤 DEPARTMENT SELECTION FROM DROPDOWN');
    console.log('Department:', deptName, '(ID:', deptId + ')');
    console.log('Previous state:', dashboardChartsInstance.currentDepartment);
    
    // Call loadDepartmentView which handles all chart updates
    loadDepartmentView(deptId, deptName);
    
    console.log('New state:', dashboardChartsInstance.currentDepartment);
    console.log('═══════════════════════════════════════════════════════');
}

/**
 * Update dropdown button text - FIXED: Keep only original icon, don't create duplicates
 */
function updateDropdownButtonText(text) {
    const dropdownButton = document.querySelector('#messageDropdown');
    if (dropdownButton) {
        // Get the existing icon element to preserve it
        const existingIcon = dropdownButton.querySelector('i');
        // Clear the button and set new text
        dropdownButton.textContent = text + ' ';
        
        // Re-append the original icon if it existed
        if (existingIcon) {
            const newIcon = existingIcon.cloneNode(true);
            dropdownButton.appendChild(newIcon);
        }
    }
}

/**
 * Initialize on DOM ready
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboardCharts();
});