/*dashboard_charts_line.js  para progreso sobre eltiempo grafica*/ 

function initializeLineChart() { 
    console.log('Inicializando gráfico de línea de tendencias...'); 
    loadUserDepartmentForLineChart(); 
} 

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
        }); 
} 

function updateLineChart(data, mode, deptName = null) { 
    const ctx = document.getElementById('lineChart'); 
    if (!ctx) { 
        console.warn('Line chart canvas not found'); 
        return; 
    } 

    if (dashboardChartsInstance.charts.lineChart) {//destruir graficas si existen 
        dashboardChartsInstance.charts.lineChart.destroy(); 
    } 

    let chartTitle = 'Tendencia de Proyectos Completados';//preparar titulo de grafico 
    if (mode === 'single' && deptName) { 
        chartTitle = `Tendencia - ${deptName}`; 
    } else if (mode === 'comparison') { 
        chartTitle = 'Comparación de Tendencias - Todos los Departamentos'; 
    } 

    const chartData = {//preparar info de chart 
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