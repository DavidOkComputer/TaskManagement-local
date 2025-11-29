/*manager_list_total_projects*/ 

$(document).ready(function() { 
    loadProyectos(); 
    setInterval(loadProyectos, 30000); // actualizar cada 30s 
}); 

function loadProyectos() { 
    showLoadingState(); 
    $.ajax({ 
        url: '../php/manager_api_get_projects.php', 
        type: 'GET', 
        dataType: 'json', 
        timeout: 10000, 
        success: function(response) { 
            console.log('Response from server:', response); 

             
            if (response.success) { 
                populateProyectosTable(response.data); 
                hideLoadingState(); 
                console.log('Proyectos loaded for department:', response.department_id); 

            } else { 
                showError('Error: ' + response.message); 
            } 
        }, 

        error: function(xhr, status, error) { 
            console.error('Error al cargar los proyectos:', error); 
            console.error('Response text:', xhr.responseText); 
            console.error('Status:', xhr.status); 

            let errorMessage = 'Error al cargar proyectos';             

            if (xhr.status === 401) { 
                errorMessage = 'Sesión expirada. Por favor, inicie sesión nuevamente.'; 
            } else if (xhr.status === 403) { 
                errorMessage = 'No tiene permiso para ver proyectos o no tiene departamento asignado'; 
            } else if (status === 'timeout') { 
                errorMessage = 'Tiempo de espera agotado. Por favor, intente de nuevo.'; 
            } 

            showError(errorMessage); 
        } 
    });     
} 

function populateProyectosTable(proyectos) { 
    const tbody = $('table.select-table tbody'); 
    tbody.empty(); 

    if (!proyectos || proyectos.length === 0) { 
        tbody.html(` 
            <tr> 
                <td colspan="5" class="text-center py-4"> 
                    <p class="text-muted">No hay proyectos asignados en tu departamento</p> 
                </td> 
            </tr> 
        `); 
        updateProyectoStatusChart([], 0); 
        return; 
    } 

    proyectos.forEach(function(proyecto) { 
        const row = createProyectoRow(proyecto); 
        tbody.append(row); 
    }); 

    updateProyectoCount(proyectos.length); 
    updateProyectoStatusChart(proyectos, proyectos.length); 
} 

function updateProyectoStatusChart(proyectos, total) { 
    //esperar a que se inicialice la grafica desde dashboard.js
    if (!window.doughnutChart) { 
        console.warn('Doughnut chart not initialized yet, retrying...'); 
        setTimeout(() => updateProyectoStatusChart(proyectos, total), 500); 
        return; 
    } 

    // Contar proyectos por estado 

    const statusCounts = { 
        'pendiente': 0, 
        'completado': 0, 
        'vencido': 0, 
        'en proceso': 0 
    }; 

    // Si no hay proyectos, establecer todos en 0 
    if (!proyectos || proyectos.length === 0) { 
        window.doughnutChart.data.datasets[0].data = [0, 0, 0, 0]; 
        window.doughnutChart.update(); 

        // Update legend if it exists 
        const legendElement = document.getElementById('doughnut-chart-legend'); 
        if (legendElement && window.doughnutChart.generateLegend) { 
            legendElement.innerHTML = window.doughnutChart.generateLegend(); 
        } 
        return; 
    } 

    // Contar proyectos por estado 
    proyectos.forEach(function(proyecto) { 
        const estado = proyecto.estado.toLowerCase().trim(); 
        if (statusCounts.hasOwnProperty(estado)) { 
            statusCounts[estado]++; 
        } 
    }); 

    // Actualizar los datos del chart en el orden correcto: pendientes, completados, vencidos, en proceso 
    window.doughnutChart.data.datasets[0].data = [ 
        statusCounts['pendiente'], 
        statusCounts['completado'], 
        statusCounts['vencido'], 
        statusCounts['en proceso'] 
    ]; 

    // Actualizar el chart con animacion 
    window.doughnutChart.update(); 

    // Actualizar la leyenda 
    const legendElement = document.getElementById('doughnut-chart-legend'); 
    if (legendElement && window.doughnutChart.generateLegend) { 
        legendElement.innerHTML = window.doughnutChart.generateLegend(); 
    } 

    console.log('Chart updated with department data:', { 
        pendientes: statusCounts['pendiente'], 
        completados: statusCounts['completado'], 
        vencidos: statusCounts['vencido'], 
        enProgreso: statusCounts['en proceso'], 
        total: total 
    }); 
} 

function createProyectoRow(proyecto) { 
    const fechaCumplimiento = formatDate(proyecto.fecha_cumplimiento); 
    const progressBarId = 'progress-' + proyecto.id_proyecto; 

    const row = $(` 
        <tr data-proyecto-id="${proyecto.id_proyecto}"> 
            <!-- Proyecto Nombre column --> 
            <td> 
                <div class="d-flex"> 
                    <div> 
                        <h6 class="mb-0">${proyecto.nombre}</h6> 
                        <p class="text-muted small mb-0">${proyecto.tipo_proyecto}</p> 
                    </div> 
                </div> 
            </td> 

            <!-- Descripcion column --> 
            <td> 
                <h6 class="mb-0">${proyecto.descripcion}</h6> 
                <p class="text-muted small mb-0">Departamento: ${proyecto.departamento}</p> 
            </td> 

            <!-- Progreso column --> 
            <td> 
                <div> 
                    <div class="d-flex justify-content-between align-items-center mb-1 max-width-progress-wrap"> 
                        <p class="text-success mb-0 fw-bold">${proyecto.progreso}%</p> 
                        <p class="text-muted small mb-0">${proyecto.progreso}/100</p> 
                    </div> 
                    <div class="progress progress-md"> 
                        <div class="progress-bar ${proyecto.progreso_color}"  
                             id="${progressBarId}"  
                             role="progressbar"  
                             style="width: ${proyecto.progreso}%"  
                             aria-valuenow="${proyecto.progreso}"  
                             aria-valuemin="0"  
                             aria-valuemax="100"> 
                        </div> 
                    </div> 
                </div> 
            </td> 

            <!-- Estado column --> 
            <td> 
                <div class="badge badge-opacity-${getEstadoClass(proyecto.estado_style)}"> 
                    ${proyecto.estado_display} 
                </div> 
            </td> 
        </tr> 
    `); 

    row.on('click', function(e) { 
        if (e.target.type !== 'checkbox') { 
            viewProyectoDetails(proyecto.id_proyecto); 
        } 
    }); 

    return row; 
} 

function getEstadoClass(estado_style) { 
    return estado_style.replace('badge-', ''); 
} 

function formatDate(dateString) { 
    if (!dateString) return 'N/A'; 
    const date = new Date(dateString); 

    if (isNaN(date.getTime())) return 'N/A';  

    return date.toLocaleDateString('es-MX', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit' 
    }); 
} 

function updateProyectoCount(count) { 
    const subtitle = $('p.card-subtitle-dash'); 
    if (subtitle.length) { 
        const plural = count === 1 ? 'proyecto' : 'proyectos'; 
        subtitle.text('Tienes ' + count + ' ' + plural + ' en tu departamento'); 
    } 
} 

function showLoadingState() { 
    const tbody = $('table.select-table tbody'); 
    tbody.html(` 
        <tr> 
            <td colspan="5" class="text-center py-4"> 
                <div class="spinner-border spinner-border-sm me-2" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <span class="text-muted">Cargando proyectos...</span> 
            </td> 
        </tr> 
    `); 
} 

function hideLoadingState() { 
    //se oculta el estado de carga cuando la tabla se llena de info
} 

function showError(message) { 
    const tbody = $('table.select-table tbody'); 
    tbody.html(` 
        <tr> 
            <td colspan="5" class="text-center py-4"> 
                <div class="alert alert-danger alert-dismissible fade show" role="alert"> 
                    <i class="mdi mdi-alert-circle me-2"></i> 
                    ${message} 
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button> 
                </div> 
            </td> 
        </tr> 
    `); 
} 

function viewProyectoDetails(proyectoId) { 
    console.log('Viewing project details:', proyectoId); 
    window.location.href = '../revisarProyectosGerente/?id=' + proyectoId; 
} 

function getSelectedProyectos() { 
    const selected = []; 
    $('input.proyecto-checkbox:checked').each(function() { 
        selected.push($(this).data('proyecto-id')); 
    }); 
    return selected; 
} 

function bulkActionProyectos(action) { 
    const selected = getSelectedProyectos(); 
    if (selected.length === 0) { 
        alert('Por favor seleccione al menos un proyecto'); 
        return; 
    } 
    console.log('Performing action:', action, 'on projects:', selected); 
} 