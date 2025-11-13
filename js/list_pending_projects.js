/*Proyectos Pendientes */

$(document).ready(function() {
    loadProyectosPendientes();
    
    setInterval(loadProyectosPendientes, 30000);//refrescar proyectos pendientes cada 30s de maner automatica
});

function loadProyectosPendientes() {
    showLoadingState();
    
    $.ajax({//obtener proyectos pendientes
        url: '../api_get_proyectos_pendientes.php',
        type: 'GET',
        dataType: 'json',
        timeout: 10000, // 10 s
        success: function(response) {
            if (response.success) {
                populateProyectosPendientesTable(response.data);//llenado de tabla
                hideLoadingState();
            } else {
                showError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading pending projects:', error);
            
            let errorMessage = 'Error al cargar proyectos pendientes';
            
            if (xhr.status === 401) {
                errorMessage = 'Sesión expirada. Por favor, inicie sesión nuevamente.';
            } else if (xhr.status === 403) {
                errorMessage = 'No tiene permiso para ver proyectos pendientes';
            } else if (status === 'timeout') {
                errorMessage = 'Tiempo de espera agotado. Por favor, intente de nuevo.';
            }
            
            showError(errorMessage);
        }
    });
}

function populateProyectosPendientesTable(proyectos) {
    const tbody = $('table.select-table tbody');
    
    tbody.empty();//limpiar registros existentes
    
    if (!proyectos || proyectos.length === 0) {//revisar si hay proyecto pendientes
        tbody.html(`
            <tr>
                <td colspan="5" class="text-center py-4">
                    <p class="text-muted">No hay proyectos pendientes asignados</p>
                </td>
            </tr>
        `);
        return;
    }
    
    proyectos.forEach(function(proyecto) {//iteracion entrecada proyecto y crear los registros de la tabla
        const row = createProyectoPendienteRow(proyecto);
        tbody.append(row);
    });
    
    updateProyectoPendienteCount(proyectos.length);
}

function createProyectoPendienteRow(proyecto) {
    const fechaCumplimiento = formatDate(proyecto.fecha_cumplimiento);//formato de fecha
    
    const isOverdue = isDateOverdue(proyecto.fecha_cumplimiento);//revisar si el proyecto esta vencido
    const overdueClass = isOverdue ? 'table-danger' : '';
    
    const progressBarId = 'progress-' + proyecto.id_proyecto;//ids dinamicas para los elementos
    
    //HTML
    const row = $(`
        <tr data-proyecto-id="${proyecto.id_proyecto}" class="${overdueClass}">
            <!-- Checkbox column -->
            <td>
                <div class="form-check form-check-flat mt-0">
                    <label class="form-check-label">
                        <input type="checkbox" class="form-check-input proyecto-checkbox" 
                               data-proyecto-id="${proyecto.id_proyecto}" 
                               aria-checked="false">
                        <i class="input-helper"></i>
                    </label>
                </div>
            </td>
            
            <!-- Proyecto Nombre column -->
            <td>
                <div class="d-flex">
                    <!-- Placeholder image -->
                    <img src="../images/faces/face1.jpg" alt="${proyecto.nombre}" class="me-2">
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
                    ${isOverdue ? '<br><span class="badge badge-danger">VENCIDO</span>' : ''}
                </p>
            </td>
            
            <!-- Progreso column -->
            <td>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1 max-width-progress-wrap">
                        <p class="text-danger mb-0 fw-bold">${proyecto.progreso}%</p>
                        <p class="text-muted small mb-0">Fecha límite: ${fechaCumplimiento}</p>
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
                <div class="badge badge-opacity-danger">
                    ${proyecto.estado_display}
                </div>
            </td>
        </tr>
    `);
    
    row.on('click', function(e) {//ver detalles del proyecto
        if (e.target.type !== 'checkbox') {// no activar al hacer clic en checkbox
            viewProyectoPendienteDetails(proyecto.id_proyecto);
        }
    });
    
    return row;
}

function isDateOverdue(dateString) {
    if (!dateString) return false;
    
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0); //reiniciar dia
    
    return date < today;
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

function updateProyectoPendienteCount(count) {
    const subtitle = $('p.card-subtitle-dash');//encontrar y actualizar el subtitulo de conteo de proyectos
    if (subtitle.length) {
        const plural = count === 1 ? 'proyecto' : 'proyectos';
        const text = count === 0 
            ? 'No hay proyectos pendientes' 
            : 'Tienes ' + count + ' ' + plural + ' pendiente' + (count > 1 ? 's' : '');
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

function viewProyectoPendienteDetails(proyectoId) {
    console.log('Viewing pending project details:', proyectoId);
    // window.location.href = '../proyectoDetalle/?id=' + proyectoId;
}

function getSelectedProyectosPendientes() {
    const selected = [];
    $('input.proyecto-checkbox:checked').each(function() {
        selected.push($(this).data('proyecto-id'));
    });
    return selected;
}

function bulkActionProyectosPendientes(action) {
    const selected = getSelectedProyectosPendientes();
    
    if (selected.length === 0) {
        alert('Por favor seleccione al menos un proyecto pendiente');
        return;
    }
    
    console.log('Performing action:', action, 'on pending projects:', selected);
    //se puede agregar opcion de acciones masivas como iniciar varios proyectos pendientes
}