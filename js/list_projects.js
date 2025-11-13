/**
 * Proyectos Totales - Dynamic List Manager
 * 
 * Purpose: Handles the dynamic loading and display of user's projects
 * Functions:
 * - Fetch projects from API
 * - Populate table with project data
 * - Handle loading and error states
 * - Format dates and progress display
 * 
 * Requires: jQuery (already included in template)
 */

$(document).ready(function() {
    // Initialize the projects list on page load
    loadProyectos();
    
    // Optional: Refresh projects every 30 seconds for real-time updates
    setInterval(loadProyectos, 30000);
});

/**
 * Load projects from API endpoint
 * Fetches all projects related to logged-in user
 */
function loadProyectos() {
    // Show loading state
    showLoadingState();
    
    // AJAX request to fetch projects
    $.ajax({
        url: '../api_get_proyectos.php', // Path to API endpoint
        type: 'GET',
        dataType: 'json',
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (response.success) {
                // Populate table with projects
                populateProyectosTable(response.data);
                hideLoadingState();
            } else {
                // Handle API error response
                showError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            // Handle AJAX request errors
            console.error('Error loading projects:', error);
            
            let errorMessage = 'Error al cargar proyectos';
            
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

/**
 * Populate the projects table with data
 * @param {Array} proyectos - Array of project objects from API
 */
function populateProyectosTable(proyectos) {
    const tbody = $('table.select-table tbody');
    
    // Clear existing rows (except if we want to keep a "no data" message)
    tbody.empty();
    
    // Check if there are projects
    if (!proyectos || proyectos.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="5" class="text-center py-4">
                    <p class="text-muted">No hay proyectos asignados</p>
                </td>
            </tr>
        `);
        return;
    }
    
    // Iterate through each project and create table rows
    proyectos.forEach(function(proyecto) {
        const row = createProyectoRow(proyecto);
        tbody.append(row);
    });
    
    // Update project count in header
    updateProyectoCount(proyectos.length);
}

/**
 * Create a table row for a single project
 * @param {Object} proyecto - Project data object
 * @returns {jQuery} - jQuery object containing the table row
 */
function createProyectoRow(proyecto) {
    // Format dates using JavaScript Date object
    const fechaCumplimiento = formatDate(proyecto.fecha_cumplimiento);
    
    // Generate unique IDs for dynamic elements
    const progressBarId = 'progress-' + proyecto.id_proyecto;
    
    // Create the row HTML
    const row = $(`
        <tr data-proyecto-id="${proyecto.id_proyecto}">
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
                    <!-- Placeholder image - can be replaced with department logo -->
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
    
    // Add click event to row for viewing project details (optional)
    row.on('click', function(e) {
        // Don't trigger on checkbox click
        if (e.target.type !== 'checkbox') {
            viewProyectoDetails(proyecto.id_proyecto);
        }
    });
    
    return row;
}

/**
 * Convert badge style class to readable class name
 * @param {String} estado_style - The style class (e.g., 'badge-success')
 * @returns {String} - The style name without 'badge-' prefix
 */
function getEstadoClass(estado_style) {
    return estado_style.replace('badge-', '');
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
 * Update the project count displayed in the card header
 * @param {Number} count - Number of projects
 */
function updateProyectoCount(count) {
    // Find and update the subtitle that shows project count
    const subtitle = $('p.card-subtitle-dash');
    if (subtitle.length) {
        const plural = count === 1 ? 'proyecto' : 'proyectos';
        subtitle.text('Tienes ' + count + ' ' + plural);
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
                <span class="text-muted">Cargando proyectos...</span>
            </td>
        </tr>
    `);
}

/**
 * Hide loading state
 */
function hideLoadingState() {
    // Loading state is replaced by actual content in populateProyectosTable
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
 * Navigate to project details (placeholder for future functionality)
 * @param {Number} proyectoId - Project ID
 */
function viewProyectoDetails(proyectoId) {
    // This can be extended to navigate to a project details page
    console.log('Viewing project details:', proyectoId);
    // window.location.href = '../proyectoDetalle/?id=' + proyectoId;
}

/**
 * Get selected projects (from checkboxes)
 * @returns {Array} - Array of selected project IDs
 */
function getSelectedProyectos() {
    const selected = [];
    $('input.proyecto-checkbox:checked').each(function() {
        selected.push($(this).data('proyecto-id'));
    });
    return selected;
}

/**
 * Handle bulk actions on selected projects
 * @param {String} action - Action to perform (e.g., 'delete', 'archive')
 */
function bulkActionProyectos(action) {
    const selected = getSelectedProyectos();
    
    if (selected.length === 0) {
        alert('Por favor seleccione al menos un proyecto');
        return;
    }
    
    console.log('Performing action:', action, 'on projects:', selected);
    // Implement bulk action logic here
}