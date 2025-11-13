/**
 * Proyectos Vencidos - Dynamic List Manager
 * 
 * Purpose: Handles the dynamic loading and display of user's overdue projects
 * Shows only projects with estado = 'vencido' (overdue/expired)
 * Functions:
 * - Fetch overdue projects from API
 * - Populate table with project data
 * - Handle loading and error states
 * - Format dates and progress display
 * - Show days overdue
 * - Sort by days overdue (most urgent first)
 * 
 * Requires: jQuery (already included in template)
 */

$(document).ready(function() {
    // Initialize the overdue projects list on page load
    loadProyectosVencidos();
    
    // Optional: Refresh overdue projects every 30 seconds for real-time updates
    setInterval(loadProyectosVencidos, 30000);
});

/**
 * Load overdue projects from API endpoint
 * Fetches all overdue projects related to logged-in user
 */
function loadProyectosVencidos() {
    // Show loading state
    showLoadingState();
    
    // AJAX request to fetch overdue projects
    $.ajax({
        url: '../api_get_proyectos_vencidos.php', // Path to overdue projects API endpoint
        type: 'GET',
        dataType: 'json',
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (response.success) {
                // Populate table with overdue projects
                populateProyectosVencidosTable(response.data);
                hideLoadingState();
            } else {
                // Handle API error response
                showError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            // Handle AJAX request errors
            console.error('Error loading overdue projects:', error);
            
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

/**
 * Populate the overdue projects table with data
 * @param {Array} proyectos - Array of overdue project objects from API
 */
function populateProyectosVencidosTable(proyectos) {
    const tbody = $('table.select-table tbody');
    
    // Clear existing rows
    tbody.empty();
    
    // Check if there are overdue projects
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
    
    // Sort by days overdue (most overdue first)
    proyectos.sort((a, b) => b.dias_vencidos - a.dias_vencidos);
    
    // Iterate through each overdue project and create table rows
    proyectos.forEach(function(proyecto) {
        const row = createProyectoVencidoRow(proyecto);
        tbody.append(row);
    });
    
    // Update overdue project count in header
    updateProyectoVencidoCount(proyectos.length);
}

/**
 * Create a table row for a single overdue project
 * @param {Object} proyecto - Project data object
 * @returns {jQuery} - jQuery object containing the table row
 */
function createProyectoVencidoRow(proyecto) {
    // Format dates using JavaScript Date object
    const fechaCumplimiento = formatDate(proyecto.fecha_cumplimiento);
    
    // Generate unique IDs for dynamic elements
    const progressBarId = 'progress-' + proyecto.id_proyecto;
    
    // Create urgency indicator based on days overdue
    let urgencyClass = 'text-danger';
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
    
    // Create the row HTML
    const row = $(`
        <tr data-proyecto-id="${proyecto.id_proyecto}" class="table-danger">
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
    
    // Add click event to row for viewing project details (optional)
    row.on('click', function(e) {
        // Don't trigger on checkbox click
        if (e.target.type !== 'checkbox') {
            viewProyectoVencidoDetails(proyecto.id_proyecto);
        }
    });
    
    return row;
}

/**
 * Format date to readable format (DD/MM/YYYY)
 * @param {String} dateString - Date string from database
 * @returns {String} - Formatted date
 */
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

/**
 * Update the overdue project count displayed in the card header
 * @param {Number} count - Number of overdue projects
 */
function updateProyectoVencidoCount(count) {
    // Find and update the subtitle that shows overdue project count
    const subtitle = $('p.card-subtitle-dash');
    if (subtitle.length) {
        const plural = count === 1 ? 'proyecto' : 'proyectos';
        const text = count === 0 
            ? 'No hay proyectos vencidos' 
            : 'Tienes ' + count + ' ' + plural + ' vencido' + (count > 1 ? 's' : '') + ' - Acción requerida';
        subtitle.text(text);
    }
}

/**
 * Show loading state in the table
 */
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

/**
 * Hide loading state
 */
function hideLoadingState() {
    // Loading state is replaced by actual content in populateProyectosVencidosTable
}

/**
 * Show error message in table
 * @param {String} message - Error message to display
 */
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

/**
 * Navigate to overdue project details (placeholder for future functionality)
 * @param {Number} proyectoId - Project ID
 */
function viewProyectoVencidoDetails(proyectoId) {
    // This can be extended to navigate to a project details page
    console.log('Viewing overdue project details:', proyectoId);
    // window.location.href = '../proyectoDetalle/?id=' + proyectoId;
}

/**
 * Get selected overdue projects (from checkboxes)
 * @returns {Array} - Array of selected project IDs
 */
function getSelectedProyectosVencidos() {
    const selected = [];
    $('input.proyecto-checkbox:checked').each(function() {
        selected.push($(this).data('proyecto-id'));
    });
    return selected;
}

/**
 * Handle bulk actions on selected overdue projects
 * @param {String} action - Action to perform (e.g., 'extend', 'archive')
 */
function bulkActionProyectosVencidos(action) {
    const selected = getSelectedProyectosVencidos();
    
    if (selected.length === 0) {
        alert('Por favor seleccione al menos un proyecto vencido');
        return;
    }
    
    console.log('Performing action:', action, 'on overdue projects:', selected);
    // Implement bulk action logic here
    // Example: Extend deadline for multiple overdue projects
}