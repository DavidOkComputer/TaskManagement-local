/**
 * dashboard_charts_scatter.js
 * Efficiency matrix (bubble chart)
 * Admin only - Department comparison vs Person efficiency within department
 */

/**
 * Initialize scatter chart
 * Starts with department efficiency comparison
 */
function initializeScatterChart() {
    console.log('Inicializando gráfico de dispersión...');
    
    const currentDept = dashboardChartsInstance.currentDepartment;
    
    if (currentDept && currentDept.id && currentDept.id > 0) {
        // Department is selected - show person efficiency
        console.log('Loading person efficiency for:', currentDept.name);
        loadPersonEfficiencyByDepartment(currentDept.id, currentDept.name);
    } else {
        // No department selected - show department comparison
        console.log('Loading department efficiency comparison');
        loadDepartmentEfficiency();
    }
}

/**
 * Load person efficiency for a specific department
 */
function loadPersonEfficiencyByDepartment(deptId, deptName) {
    console.log(`Loading person efficiency for department: ${deptName} (ID: ${deptId})`);

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
                console.log('Person efficiency data loaded:', data.data.details.length, 'people');
                return data;
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                console.log('Updating scatter chart to person mode');
                updateScatterChart(data.data, 'person', deptName);
            } else {
                console.warn('Error in person efficiency data:', data.message);
                // Fallback to department view
                loadDepartmentEfficiency();
            }
        })
        .catch(error => {
            console.error('Error loading person efficiency:', error.message);
            console.warn('Falling back to department view');
            loadDepartmentEfficiency();
        });
}

/**
 * Load department efficiency comparison
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
                throw new Error('Invalid JSON from API: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                updateScatterChart(data.data, 'department', null);
            } else {
                console.warn('Error en datos de eficiencia:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading department efficiency:', error.message);
        });
}

/**
 * Update scatter chart with data
 */
function updateScatterChart(data, mode = 'department', deptName = null) {
    const ctx = document.getElementById('scatterChart');
    if (!ctx) {
        console.warn('Scatter chart canvas not found');
        return;
    }

    // Destroy existing chart
    if (dashboardChartsInstance.charts.scatterChart) {
        dashboardChartsInstance.charts.scatterChart.destroy();
    }

    // Prepare title and labels based on mode
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
            mode: 'point',
            intersect: true
        },
        scales: {
            x: {
                beginAtZero: true,
                type: 'linear',
                position: 'bottom',
                title: {
                    display: true,
                    text: xAxisLabel,
                    font: { size: 13, weight: 'bold' },
                    padding: 15,
                    color: 'rgba(50, 50, 50, 1)'
                },
                ticks: {
                    font: { size: 11 },
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
                    font: { size: 13, weight: 'bold' },
                    padding: 15,
                    color: 'rgba(50, 50, 50, 1)'
                },
                ticks: {
                    font: { size: 11 },
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
            filler: { propagate: true },
            legend: { display: false },
            title: {
                display: true,
                text: chartTitle,
                font: { size: 15, weight: 'bold' },
                padding: 20,
                color: 'rgba(34, 139, 89, 1)'
            },
            tooltip: {
                enabled: true,
                backgroundColor: 'rgba(255, 255, 255, 0.98)',
                titleColor: 'rgba(34, 139, 89, 1)',
                bodyColor: 'rgba(50, 50, 50, 1)',
                borderColor: 'rgba(34, 139, 89, 1)',
                borderWidth: 2,
                titleFont: { size: 15, weight: 'bold' },
                bodyFont: { size: 13 },
                padding: 15,
                displayColors: true,
                usePointStyle: true,
                boxWidth: 12,
                boxHeight: 12,
                boxPadding: 6,
                callbacks: {
                    title: function(context) {
                        if (context.length > 0) {
                            const point = context[0];
                            const label = point.raw.label || 'Elemento';
                            const icon = mode === 'person' ? '👤' : '🏢';
                            return icon + ' ' + label;
                        }
                        return '';
                    },
                    label: function(context) {
                        const point = context.raw;
                        const workloadLabel = mode === 'person' ? 'Tareas Asignadas' : 'Carga de Trabajo';

                        return [
                            workloadLabel + ': ' + point.x + ' tarea' + (point.x !== 1 ? 's' : ''),
                            'Eficiencia: ' + point.y + '%'
                        ];
                    },
                    afterLabel: function(context) {
                        const raw = context.raw;
                        const detail = data.details.find(d =>
                            d.nombre_completo === raw.label || d.nombre === raw.label
                        );

                        if (detail) {
                            const lines = ['───────────────'];

                            if (detail.completadas !== undefined) {
                                lines.push('✓ Completadas: ' + detail.completadas);
                            }
                            if (detail.en_proceso !== undefined) {
                                lines.push('◐ En Proceso: ' + detail.en_proceso);
                            }
                            if (detail.pendientes !== undefined) {
                                lines.push('○ Pendientes: ' + detail.pendientes);
                            }
                            if (detail.vencidas !== undefined && detail.vencidas > 0) {
                                lines.push('⚠ Vencidas: ' + detail.vencidas);
                            }

                            return lines;
                        }
                        return '';
                    }
                }
            }
        }
    };

    // Add average line annotation for department mode
    if (mode === 'department' && data.avg_completion) {
        options.plugins.annotation = {
            annotations: {
                averageLine: {
                    type: 'line',
                    yMin: data.avg_completion,
                    yMax: data.avg_completion,
                    borderColor: 'rgba(34, 139, 89, 0.4)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    label: {
                        display: true,
                        content: 'Promedio: ' + data.avg_completion + '%',
                        position: 'end',
                        backgroundColor: 'rgba(34, 139, 89, 0.8)',
                        color: 'white',
                        font: { size: 11 },
                        padding: 4
                    }
                }
            }
        };
    }

    dashboardChartsInstance.charts.scatterChart = new Chart(ctx, {
        type: 'bubble',
        data: { datasets: data.datasets },
        options: options
    });

    addScatterChartLegend(mode, deptName, data);
    console.log(`Gráfico de dispersión actualizado: ${chartTitle}`);
}

/**
 * Add custom legend below scatter chart
 */
function addScatterChartLegend(mode, deptName, data) {
    const canvasContainer = document.getElementById('scatterChart').parentElement;
    
    // Remove existing legend
    const oldLegend = canvasContainer.querySelector('.scatter-chart-legend');
    if (oldLegend) {
        oldLegend.remove();
    }

    // Create legend div
    const legendDiv = document.createElement('div');
    legendDiv.className = 'scatter-chart-legend';
    legendDiv.style.cssText = `
        margin-top: 15px;
        padding: 15px;
        background: linear-gradient(135deg, rgba(200, 205, 210, 0.1) 0%, rgba(34, 139, 89, 0.05) 100%);
        border-left: 4px solid rgba(34, 139, 89, 1);
        border-radius: 6px;
        font-size: 13px;
        line-height: 1.8;
        color: rgba(50, 50, 50, 1);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    `;

    let legendHTML = '';

    if (mode === 'person') {
        legendHTML = `
            <div style="margin-bottom: 10px;">
                <strong style="color: rgba(34, 139, 89, 1); font-size: 14px;">
                    Guía de Interpretación - Eficiencia Individual
                </strong>
            </div>
            <div style="margin-left: 15px; display: grid; gap: 8px;">
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(34, 139, 89, 1); margin-right: 8px;">▲</span>
                    <strong>Superior-Derecha:</strong> Alto rendimiento (Muchas tareas, alta eficiencia)
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(80, 154, 108, 1); margin-right: 8px;">▲</span>
                    <strong>Superior-Izquierda:</strong> Especialista eficiente (Pocas tareas, muy eficiente)
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(130, 140, 150, 1); margin-right: 8px;">▼</span>
                    <strong>Inferior-Derecha:</strong> Necesita apoyo (Muchas tareas, baja eficiencia)
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(200, 205, 210, 1); margin-right: 8px;">▼</span>
                    <strong>Inferior-Izquierda:</strong> Capacidad disponible (Pocas tareas, baja eficiencia)
                </div>
            </div>
            <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(200, 205, 210, 0.3); font-size: 12px; color: rgba(80, 80, 80, 1);">
                <em>Tip: Pasa el cursor sobre cada punto para ver detalles completos</em>
            </div>
        `;
    } else {
        legendHTML = `
            <div style="margin-bottom: 10px;">
                <strong style="color: rgba(34, 139, 89, 1); font-size: 14px;">
                    Guía de Interpretación - Eficiencia Departamental
                </strong>
            </div>
            <div style="margin-left: 15px; display: grid; gap: 8px;">
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(34, 139, 89, 1); margin-right: 8px;">●</span>
                    <strong>Superior-Derecha:</strong> Departamentos estrella (Alta carga, alta eficiencia)
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(80, 154, 108, 1); margin-right: 8px;">●</span>
                    <strong>Superior-Izquierda:</strong> Especialización (Baja carga, alta eficiencia)
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(130, 140, 150, 1); margin-right: 8px;">●</span>
                    <strong>Inferior-Derecha:</strong> Requieren recursos (Alta carga, baja eficiencia)
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(200, 205, 210, 1); margin-right: 8px;">●</span>
                    <strong>Inferior-Izquierda:</strong> Capacidad para crecer (Baja carga, baja eficiencia)
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="color: rgba(34, 139, 89, 0.5); margin-right: 8px;">━━━</span>
                    <strong style="color: rgba(34, 139, 89, 1);">Línea punteada:</strong> Promedio organizacional (${data.avg_completion || 0}%)
                </div>
            </div>
            <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(200, 205, 210, 0.3); font-size: 12px; color: rgba(80, 80, 80, 1);">
                <em>Tip: Colores distintos representan departamentos diferentes</em>
            </div>
        `;
    }
    
    legendDiv.innerHTML = legendHTML;
    canvasContainer.appendChild(legendDiv);
}