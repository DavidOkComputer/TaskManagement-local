/*manager_charts_scatter.js grafica de medidas de eficiencia departamental*/

function initializeManagerScatterChart() {
    
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    loadScatterData(deptId, deptName);
}

function loadScatterData(deptId, deptName) {
    fetch(`../php/manager_get_person_efficiency.php?id_departamento=${deptId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error: ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            if (!text || text.trim() === '') {
                throw new Error('Respuesta vacía del servidor');
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error parseando JSON:', text.substring(0, 200));
                throw new Error('Respuesta inválida del servidor');
            }
        })
        .then(data => {
            if (data.success && data.data) {
                renderScatterChart(data.data, deptName);
            } else {
                console.warn('Sin datos para gráfica de dispersión:', data.message);
                showNoDataMessage('scatterChart', `Sin datos - ${deptName}`, 'No hay datos de eficiencia');
            }
        })
        .catch(error => {
            console.error('Error cargando datos de dispersión:', error);
            showNoDataMessage('scatterChart', 'Error', 'No se pudieron cargar los datos');
        });
}

function renderScatterChart(data, deptName) {
    const ctx = document.getElementById('scatterChart');
    
    if (!ctx) {
        console.warn('Canvas scatterChart no encontrado');
        return;
    }
    
    //destruir graficas existentes
    if (managerDashboard.charts.scatterChart) {
        managerDashboard.charts.scatterChart.destroy();
    }
    
    //revisar si hay informacion
    if (!data.datasets || data.datasets.length === 0) {
        showNoDataMessage('scatterChart', `Sin datos - ${deptName}`, 'No hay datos de eficiencia del personal');
        return;
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
                    text: 'Tareas Asignadas',
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15,
                    color: managerDashboard.colors.blackSolid
                },
                ticks: {
                    font: { size: 11 },
                    stepSize: 1,
                    color: managerDashboard.colors.graySolid
                },
                grid: {
                    color: 'rgba(200, 205, 210, 0.2)'
                }
            },
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Tasa de Completación (%)',
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15,
                    color: managerDashboard.colors.blackSolid
                },
                ticks: {
                    font: { size: 11 },
                    callback: function(value) {
                        return value + '%';
                    },
                    stepSize: 10,
                    color: managerDashboard.colors.graySolid
                },
                grid: {
                    color: 'rgba(200, 205, 210, 0.2)'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            title: {
                display: true,
                text: `Eficiencia del Personal - ${deptName}`,
                font: {
                    size: 15,
                    weight: 'bold'
                },
                padding: 20,
                color: managerDashboard.colors.primarySolid
            },
            tooltip: {
                enabled: true,
                backgroundColor: 'rgba(255, 255, 255, 0.98)',
                titleColor: managerDashboard.colors.primarySolid,
                bodyColor: managerDashboard.colors.blackSolid,
                borderColor: managerDashboard.colors.primarySolid,
                borderWidth: 2,
                titleFont: {
                    size: 15,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                },
                padding: 15,
                displayColors: true,
                usePointStyle: true,
                callbacks: {
                    title: function(context) {
                        if (context.length > 0) {
                            const point = context[0];
                            const label = point.raw.label || 'Persona';
                            return '👤 ' + label;
                        }
                        return '';
                    },
                    label: function(context) {
                        const point = context.raw;
                        return [
                            `Tareas Asignadas: ${point.x} tarea${point.x !== 1 ? 's' : ''}`,
                            `Eficiencia: ${point.y}%`
                        ];
                    },
                    afterLabel: function(context) {
                        const raw = context.raw;
                        const detail = data.details ? data.details.find(d => 
                            d.nombre_completo === raw.label || d.nombre === raw.label
                        ) : null;
                        
                        if (detail) {
                            const lines = ['───────────────'];
                            
                            if (detail.completadas !== undefined) {
                                lines.push(`✓ Completadas: ${detail.completadas}`);
                            }
                            if (detail.en_proceso !== undefined) {
                                lines.push(`● En Proceso: ${detail.en_proceso}`);
                            }
                            if (detail.pendientes !== undefined) {
                                lines.push(`○ Pendientes: ${detail.pendientes}`);
                            }
                            if (detail.vencidas !== undefined && detail.vencidas > 0) {
                                lines.push(`⚠ Vencidas: ${detail.vencidas}`);
                            }
                            
                            return lines;
                        }
                        return '';
                    }
                }
            }
        }
    };
    
    managerDashboard.charts.scatterChart = new Chart(ctx, {
        type: 'bubble',
        data: {
            datasets: data.datasets
        },
        options: options
    });
    
    //agregar pie de grafica
    addScatterLegend(ctx.parentElement, deptName);
}

function addScatterLegend(container, deptName) {
    const existingLegend = container.querySelector('.scatter-legend');//eliminar pie de pagina existente
    if (existingLegend) {
        existingLegend.remove();
    }
    
    const legendDiv = document.createElement('div');
    legendDiv.className = 'scatter-legend';
    legendDiv.style.cssText = `
        margin-top: 15px;
        padding: 15px;
        background: linear-gradient(135deg, rgba(200, 205, 210, 0.1) 0%, rgba(34, 139, 89, 0.05) 100%);
        border-left: 4px solid ${managerDashboard.colors.primarySolid};
        border-radius: 6px;
        font-size: 13px;
        line-height: 1.8;
        color: ${managerDashboard.colors.blackSolid};
    `;
    
    legendDiv.innerHTML = `
        <div style="margin-bottom: 10px;">
            <strong style="color: ${managerDashboard.colors.primarySolid}; font-size: 14px;">
                Guía de Interpretación - Eficiencia Individual
            </strong>
        </div>
        <div style="margin-left: 15px; display: grid; gap: 8px;">
            <div style="display: flex; align-items: center;">
                <span style="color: ${managerDashboard.colors.primarySolid}; margin-right: 8px;">▲</span>
                <strong>Superior-Derecha:</strong>&nbsp;Alto rendimiento (Muchas tareas, alta eficiencia)
            </div>
            <div style="display: flex; align-items: center;">
                <span style="color: ${managerDashboard.colors.secondarySolid}; margin-right: 8px;">▲</span>
                <strong>Superior-Izquierda:</strong>&nbsp;Especialista eficiente (Pocas tareas, muy eficiente)
            </div>
            <div style="display: flex; align-items: center;">
                <span style="color: ${managerDashboard.colors.graySolid}; margin-right: 8px;">▼</span>
                <strong>Inferior-Derecha:</strong>&nbsp;Necesita apoyo (Muchas tareas, baja eficiencia)
            </div>
            <div style="display: flex; align-items: center;">
                <span style="color: ${managerDashboard.colors.lightSolid}; margin-right: 8px;">▼</span>
                <strong>Inferior-Izquierda:</strong>&nbsp;Capacidad disponible (Pocas tareas, baja eficiencia)
            </div>
        </div>
        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(200, 205, 210, 0.3); font-size: 12px; color: ${managerDashboard.colors.graySolid};">
            <em>Tip: Pasa el cursor sobre cada punto para ver detalles completos</em>
        </div>
    `;
    
    container.appendChild(legendDiv);
}

function refreshManagerScatterChart(deptId, deptName) {
    return new Promise((resolve, reject) => {
        fetch(`../php/manager_get_person_efficiency.php?id_departamento=${deptId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderScatterChart(data.data, deptName);
                }
                resolve();
            })
            .catch(error => {
                console.error('Error refrescando dispersión:', error);
                reject(error);
            });
    });
}