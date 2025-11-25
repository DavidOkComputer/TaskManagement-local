/*dashboard_charts_bar.js grafica de barras para progreso de proyectos */ 

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
        const completedCount = projects.filter(proj => //contar proyectos completados por departamento 
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

function updateBarChart(data) { 
    const ctx = document.getElementById('barChart'); 

    if (!ctx) { 
        console.warn('Bar chart canvas not found'); 
        return; 
    } 

    if (dashboardChartsInstance.charts.barChart) {//destruir graficas existentes  
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

function updateBarChartForDepartment(projects, deptName) { 
    const ctx = document.getElementById('barChart'); 

    if (!ctx) { 
        console.warn('Bar chart canvas not found'); 
        return; 
    } 

    if (dashboardChartsInstance.charts.barChart) { 
        dashboardChartsInstance.charts.barChart.destroy(); 
    } 

    const progressData = {//preparar info de progreso para proyectos 
        labels: projects.slice(0, 5).map(p => shortenProjectTitle(p.nombre)), //mostrar top 5 proyectos con titulo corto 
        data: projects.slice(0, 5).map(p => p.progreso), 
        backgroundColor: projects.slice(0, 5).map((p, i) => { 
            if (p.progreso === 100) return 'rgba(34, 139, 89, 0.7)';        // Green -  completado 
            if (p.progreso >= 75) return 'rgba(80, 154, 108, 0.7)';         // Green Light  
            if (p.progreso >= 50) return 'rgba(130, 140, 150, 0.7)';        // Gray - en progreso 
            return 'rgba(200, 205, 210, 0.7)';                              // Ice - pendiente 
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
        indexAxis: 'y', //grafica de barras horizontal 
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
                    label: function(context) {//mostrar titulo fr proyecto completo 
                        return 'Progreso: ' + context.parsed.x + '%'; 
                    }, 

                    title: function(context) {//mostrar titulo completo cuando se pasa el mouse encima 
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