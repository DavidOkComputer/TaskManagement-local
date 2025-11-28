/*lista de Proyectos Vencidos con Grafico de Todos los Proyectos*/

$(document).ready(function() {
    loadProyectosVencidosAndChart();
    //refrescar los proyectos vencidos y grafico de manera automatica
    setInterval(loadProyectosVencidosAndChart, 30000);
});

function loadProyectosVencidosAndChart() {
    showLoadingState();
    
    // Hacer dos llamadas AJAX: una para proyectos vencidos y otra para todos los proyectos
    $.when(
        loadOverdueProjectsData(),
        loadAllProjectsForChart()
    ).done(function(overdueResponse, allProjectsResponse) {
        hideLoadingState();
    }).fail(function(error) {
        console.error('Error loading data:', error);
        showError('Error al cargar los datos');
    });
}

function loadOverdueProjectsData() {
    return $.ajax({
        url: '../php/api_get_overdue_projects.php',
        type: 'GET',
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.success) {
                populateProyectosVencidosTable(response.data);
            } else {
                showError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar los proyectos vencidos:', error);
            console.error('Response text:', xhr.responseText);
            
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

function loadAllProjectsForChart() {
    return $.ajax({
        url: '../php/api_get_projects.php',
        type: 'GET',
        dataType: 'text',
        timeout: 10000,
        success: function(response) {
            try {
                const parsed = JSON.parse(response);
                if (parsed.success) {
                    // Actualizar el grafico con todos los proyectos
                    updateProyectoStatusChart(parsed.data, parsed.total);
                } else {
                    console.error('Error: ' + parsed.message);
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Response was:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar todos los proyectos para el grafico:', error);
            console.error('Response text:', xhr.responseText);
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

function updateProyectoStatusChart(proyectos, total) {
    // Verificar si el chart existe
    if (!window.doughnutChart) {
        console.warn('Doughnut chart not initialized yet');
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
        document.getElementById('doughnut-chart-legend').innerHTML = window.doughnutChart.generateLegend();
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
    document.getElementById('doughnut-chart-legend').innerHTML = window.doughnutChart.generateLegend();
    
    console.log('Chart updated (Overdue Projects Page):', {
        pendientes: statusCounts['pendiente'],
        completados: statusCounts['completado'],
        vencidos: statusCounts['vencido'],
        enProgreso: statusCounts['en proceso'],
        total: total
    });
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