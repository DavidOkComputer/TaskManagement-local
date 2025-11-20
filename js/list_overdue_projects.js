/*lista de Proyectos Vencidos*/

$(document).ready(function() {
    loadProyectosVencidos();
    //refrescar los proyectos vencidos de manera automatica
    setInterval(loadProyectosVencidos, 30000);
});

function loadProyectosVencidos() {
    showLoadingState();
    
    
    $.ajax({//ajax para refrescar los proyectos vencidos
        url: '../php/api_get_overdue_projects.php',
        type: 'GET',
        dataType: 'json',
        timeout: 10000, // 10segundos
        success: function(response) {
            if (response.success) {
                //llenar la tabla
                populateProyectosVencidosTable(response.data);
                hideLoadingState();
            } else {
                showError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar los proyectos vencidos:', error);
            console.error('Response text:', xhr.responseText); // DEBUG
            
            let errorMessage = 'Error al cargar proyectos vencidos';
            
            if (xhr.status === 401) {
                errorMessage = 'Sesión expirada. Por favor, inicie sesión nuevamente.';
            } else if (xhr.status === 403) {
                errorMessage = 'No tiene permiso para ver proyectos vencidos';
            } else if (status === 'timeout') {
                errorMessage = 'Tiempo de espera agotado. Por favor, intente de nuevo.';
            }
            
            showError(errorMessage);
        }
    });
}

function populateProyectosVencidosTable(proyectos) {
    const tbody = $('table.select-table tbody');
    
    //limpiar filas existentes
    tbody.empty();
    
    //revisar si hay proyectos vencidos
    if (!proyectos || proyectos.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="5" class="text-center py-4">
                    <p class="text-muted">No hay proyectos vencidos asignados</p>
                </td>
            </tr>
        `);
        return;
    }
    
    //hacer sort por dias de vencido, los mas vencidos priemero
    proyectos.sort((a, b) => b.dias_vencidos - a.dias_vencidos);
    
    proyectos.forEach(function(proyecto) {//iteracion de proyectos
        const row = createProyectoVencidoRow(proyecto);
        tbody.append(row);
    });
    
    updateProyectoVencidoCount(proyectos.length);//actualizar contador
}

function createProyectoVencidoRow(proyecto) {
    const fechaCumplimiento = formatDate(proyecto.fecha_cumplimiento); //formato de fecha
    
    const progressBarId = 'progress-' + proyecto.id_proyecto; //generar id para elementos dinamicos
    
    let urgencyClass = 'text-danger';//indicador de urgencia basado en los dias de vencido
    let urgencyText = 'CRÍTICO';
    if (proyecto.dias_vencidos > 30) {
        urgencyClass = 'text-danger fw-bold';
        urgencyText = 'CRÍTICO - ' + proyecto.dias_vencidos + ' días';
    } else if (proyecto.dias_vencidos > 14) {
        urgencyClass = 'text-danger fw-bold';
        urgencyText = 'MUY URGENTE - ' + proyecto.dias_vencidos + ' días';
    } else if (proyecto.dias_vencidos > 7) {
        urgencyClass = 'text-danger';
        urgencyText = 'URGENTE - ' + proyecto.dias_vencidos + ' días';
    } else {
        urgencyClass = 'text-danger';
        urgencyText = proyecto.dias_vencidos + ' días vencido';
    }
    
    const row = $(`
        <tr data-proyecto-id="${proyecto.id_proyecto}" class="table-danger">
            
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
                <p class="text-muted small mb-0">
                    Departamento: ${proyecto.departamento}
                </p>
            </td>
            
            <!-- Progreso column -->
            <td>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1 max-width-progress-wrap">
                        <p class="text-danger mb-0 fw-bold">${proyecto.progreso}%</p>
                        <p class="text-muted small mb-0">Vencido: ${fechaCumplimiento}</p>
                    </div>
                    <div class="progress progress-md">
                        <div class="progress-bar bg-danger" 
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
                <div>
                    <div class="badge badge-opacity-danger">
                        ${proyecto.estado_display}
                    </div>
                    <br>
                    <small class="${urgencyClass} fw-bold mt-2 d-block">
                        ${urgencyText}
                    </small>
                </div>
            </td>
        </tr>
    `);
    
    row.on('click', function(e) {
        if (e.target.type !== 'checkbox') { //no activar en checkbox clic
            viewProyectoVencidoDetails(proyecto.id_proyecto);
        }
    });
    
    return row;
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

function updateProyectoVencidoCount(count) {
    const subtitle = $('p.card-subtitle-dash');//buscar y actualizar subtitulo que muestre el conteo de proyectos
    if (subtitle.length) {
        const plural = count === 1 ? 'proyecto' : 'proyectos';
        const text = count === 0 
            ? 'No hay proyectos vencidos' 
            : 'Tienes ' + count + ' ' + plural + ' vencido' + (count > 1 ? 's' : '') + ' - Acción requerida';
        subtitle.text(text);
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
                <span class="text-muted">Cargando proyectos vencidos...</span>
            </td>
        </tr>
    `);
}

function hideLoadingState() {
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

function viewProyectoVencidoDetails(proyectoId) {
    
    console.log('Viewing overdue project details:', proyectoId);
    // window.location.href = '../proyectoDetalle/?id=' + proyectoId;
}

function getSelectedProyectosVencidos() {
    const selected = [];
    $('input.proyecto-checkbox:checked').each(function() {
        selected.push($(this).data('proyecto-id'));
    });
    return selected;
}

function bulkActionProyectosVencidos(action) {
    const selected = getSelectedProyectosVencidos();
    
    if (selected.length === 0) {
        alert('Por favor seleccione al menos un proyecto vencido');
        return;
    }
    
    console.log('Performing action:', action, 'on overdue projects:', selected);
    //se puede implementar acciones masivas aqui, como extender la fecha de vencimiento de varios proyectos
}