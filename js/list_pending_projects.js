/**
 * Proyectos Pendientes - Dynamic List Manager
 * 
 * Purpose: Handles the dynamic loading and display of user's pending projects
 * Shows only projects with estado = 'pendiente'
 * Functions:
 * - Fetch pending projects from API
 * - Populate table with project data
 * - Handle loading and error states
 * - Format dates and progress display
 * - Sort by due date (earliest first)
 * 
 * Requires: jQuery (already included in template)
 */

$(document).ready(function() {
    // Initialize the pending projects list on page load
    loadProyectosPendientes();
    
    // Optional: Refresh pending projects every 30 seconds for real-time updates
    setInterval(loadProyectosPendientes, 30000);
});

/**
 * Load pending projects from API endpoint
 * Fetches all pending projects related to logged-in user
 */
function loadProyectosPendientes() {
    // Show loading state
    showLoadingState();
    
    // AJAX request to fetch pending projects
    $.ajax({
        url: '../api_get_proyectos_pendientes.php', // Path to pending projects API endpoint
        type: 'GET',
        dataType: 'json',
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (response.success) {
                // Populate table with pending projects
                populateProyectosPendientesTable(response.data);
                hideLoadingState();
            } else {
                // Handle API error response
                showError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            // Handle AJAX request errors
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

/**
 * Populate the pending projects table with data
 * @param {Array} proyectos - Array of pending project objects from API
 */
function populateProyectosPendientesTable(proyectos) {
    const tbody = $('table.select-table tbody');
    
    // Clear existing rows
    tbody.empty();
    
    // Check if there are pending projects
    if (!proyectos || proyectos.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="5" class="text-center py-4">
                    <p class="text-muted">No hay proyectos pendientes asignados</p>
                </td>
            </tr>
        `);
        return;
    }
    
    // Iterate through each pending project and create table rows
    proyectos.forEach(function(proyecto) {
        const row = createProyectoPendienteRow(proyecto);
        tbody.append(row);
    });
    
    // Update pending project count in header
    updateProyectoPendienteCount(proyectos.length);
}

/**
 * Create a table row for a single pending project
 * @param {Object} proyecto - Project data object
 * @returns {jQuery} - jQuery object containing the table row
 */
function createProyectoPendienteRow(proyecto) {
    // Format dates using JavaScript Date object
    const fechaCumplimiento = formatDate(proyecto.fecha_cumplimiento);
    
    // Check if project is overdue (fecha_cumplimiento has passed)
    const isOverdue = isDateOverdue(proyecto.fecha_cumplimiento);
    const overdueClass = isOverdue ? 'table-danger' : '';
    
    // Generate unique IDs for dynamic elements
    const progressBarId = 'progress-' + proyecto.id_proyecto;
    
    // Create the row HTML
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
    
    // Add click event to row for viewing project details (optional)
    row.on('click', function(e) {
        // Don't trigger on checkbox click
        if (e.target.type !== 'checkbox') {
            viewProyectoPendienteDetails(proyecto.id_proyecto);
        }
    });
    
    return row;
}

/**
 * Check if a date is overdue (in the past)
 * @param {String} dateString - Date string from database
 * @returns {Boolean} - True if date is in the past
 */
function isDateOverdue(dateString) {
    if (!dateString) return false;
    
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Reset time to start of day
    
    return date < today;
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
 * Update the pending project count displayed in the card header
 * @param {Number} count - Number of pending projects
 */
function updateProyectoPendienteCount(count) {
    // Find and update the subtitle that shows pending project count
    const subtitle = $('p.card-subtitle-dash');
    if (subtitle.length) {
        const plural = count === 1 ? 'proyecto' : 'proyectos';
        const text = count === 0 
            ? 'No hay proyectos pendientes' 
            : 'Tienes ' + count + ' ' + plural + ' pendiente' + (count > 1 ? 's' : '');
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
                <span class="text-muted">Cargando proyectos pendientes...</span>
            </td>
        </tr>
    `);
}

/**
 * Hide loading state
 */
function hideLoadingState() {
    // Loading state is replaced by actual content in populateProyectosPendientesTable
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
 * Navigate to pending project details (placeholder for future functionality)
 * @param {Number} proyectoId - Project ID
 */
function viewProyectoPendienteDetails(proyectoId) {
    // This can be extended to navigate to a project details page
    console.log('Viewing pending project details:', proyectoId);
    // window.location.href = '../proyectoDetalle/?id=' + proyectoId;
}

/**
 * Get selected pending projects (from checkboxes)
 * @returns {Array} - Array of selected project IDs
 */
function getSelectedProyectosPendientes() {
    const selected = [];
    $('input.proyecto-checkbox:checked').each(function() {
        selected.push($(this).data('proyecto-id'));
    });
    return selected;
}

/**
 * Handle bulk actions on selected pending projects
 * @param {String} action - Action to perform (e.g., 'start', 'delete')
 */
function bulkActionProyectosPendientes(action) {
    const selected = getSelectedProyectosPendientes();
    
    if (selected.length === 0) {
        alert('Por favor seleccione al menos un proyecto pendiente');
        return;
    }
    
    console.log('Performing action:', action, 'on pending projects:', selected);
    // Implement bulk action logic here
    // Example: Start multiple pending projects
}