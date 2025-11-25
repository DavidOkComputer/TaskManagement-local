/*dashboard_charts_area.js para avances por periodo de tiempo */ 

function initializeAreaChart() { 
    console.log('Inicializando gráfico de área de tendencias de tareas...'); 
    loadUserDepartmentForAreaChart(); 
} 

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
                console.log('Cargando tendencia d tareas del departamento:', userDept.nombre); 
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
                throw new Error('API returned empty response'); 
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
        }); 
} 

function updateAreaChart(data, mode, deptName = null) { 
    const ctx = document.getElementById('areaChart'); 
    if (!ctx) { 
        console.warn('Area chart canvas not found'); 
        return; 
    } 

    if (dashboardChartsInstance.charts.areaChart) { 
        dashboardChartsInstance.charts.areaChart.destroy(); 
    } 

    let chartTitle = 'Tendencia de Tareas Completadas'; 
    if (mode === 'single' && deptName) { 
        chartTitle = `Tareas Completadas - ${deptName}`; 
    } else if (mode === 'comparison') { 
        chartTitle = 'Comparación de Tareas Completadas - Todos los Departamentos'; 
    } 

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

                stacked: mode === 'comparison', // Stack para vista de comparacion 

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

                stacked: mode === 'comparison', //Stack necesario para vista de comparacion 

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