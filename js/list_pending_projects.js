/*listar Proyectos Pendientes*/

$(document).ready(function() {
    loadProyectosPendientes();
    setInterval(loadProyectosPendientes, 30000);//actualizar cada 30s
});

function loadProyectosPendientes() {
    showLoadingState();
    
    $.ajax({//obtener los proyectos pendientes
        url: '../php/api_get_pending_projects.php', 
        type: 'GET',
        dataType: 'json',
        timeout: 10000, // 10s
        success: function(response) {
            if (response.success) {
                populateProyectosPendientesTable(response.data);//agregar proyectos a la tabla
                hideLoadingState();
            } else {
                showError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar los proyectos pendientes:', error);
            console.error('Response text:', xhr.responseText); // DEBUG necesario
            
            let errorMessage = 'Error al cargar proyectos pendientes';
            
            if (xhr.status === 401) {
                errorMessage = 'Sesión expirada. Por favor, inicie sesión nuevamente.';
            } else if (xhr.status === 403) {
                errorMessage = 'No tiene permiso para ver proyectos';
            } else if (status === 'timeout') {
                errorMessage = 'Tiempo de espera agotado. Por favor, intente de nuevo.';
            }
            
            showError(errorMessage);
        }
    });
}

function populateProyectosPendientesTable(proyectos) {
    const tbody = $('table.select-table tbody');
    
    tbody.empty();//limpiar filas existentes
    
    if (!proyectos || proyectos.length === 0) {//revisar si hay proyectos
        tbody.html(`
            <tr>
                <td colspan="5" class="text-center py-4">
                    <p class="text-muted">No hay proyectos pendientes</p>
                </td>
            </tr>
        `);
        return;
    }
    
    proyectos.forEach(function(proyecto) {//iteracion a traves de los proyectos y crear las filas de la tabla
        const row = createProyectoRow(proyecto);
        tbody.append(row);
    });
    
    updateProyectoCount(proyectos.length);//actualizar contador de proyectos
}

function createProyectoRow(proyecto) {
    const fechaCumplimiento = formatDate(proyecto.fecha_cumplimiento);
    
    const progressBarId = 'progress-' + proyecto.id_proyecto;//id s unicos para los elementos 
    
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
    const subtitle = $('p.card-subtitle-dash');//actualizar contador
    if (subtitle.length) {
        const plural = count === 1 ? 'proyecto' : 'proyectos';
        subtitle.text('Tienes ' + count + ' ' + plural);
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
                <span class="text-muted">Cargando proyectos pendientes...</span>
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

function viewProyectoDetails(proyectoId) {
    console.log('Viewing project details:', proyectoId);
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