/**
 * load_projects.js
 * Handles loading and displaying all projects in a table with action buttons
 */

// Initialize projects table on page load
document.addEventListener('DOMContentLoaded', function() {
  cargarProyectos();
});

/**
 * Load all projects from database and populate table
 */
function cargarProyectos() {
  const tableBody = document.querySelector('table tbody');
  
  fetch('../php/obtener_proyectos.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.proyectos) {
        tableBody.innerHTML = ''; // Clear loading spinner
        
        if (data.proyectos.length === 0) {
          tableBody.innerHTML = `
            <tr>
              <td colspan="9" class="text-center">
                <p class="mt-3">No hay proyectos registrados</p>
              </td>
            </tr>
          `;
          return;
        }

        // Populate table with projects
        data.proyectos.forEach((proyecto, index) => {
          const row = createProjectRow(proyecto, index + 1);
          tableBody.appendChild(row);
        });
      } else {
        tableBody.innerHTML = `
          <tr>
            <td colspan="9" class="text-center text-danger">
              <p class="mt-3">Error al cargar proyectos</p>
            </td>
          </tr>
        `;
      }
    })
    .catch(error => {
      console.error('Error loading projects:', error);
      tableBody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center text-danger">
            <p class="mt-3">Error al cargar los proyectos</p>
          </td>
        </tr>
      `;
    });
}

/**
 * Create a table row for a project
 * @param {Object} proyecto - Project object containing all project data
 * @param {number} index - Row number
 * @returns {HTMLTableRowElement} - Table row element
 */
function createProjectRow(proyecto, index) {
  const row = document.createElement('tr');
  
  // Get status badge color
  const statusColor = getStatusColor(proyecto.estado);
  const statusBadge = `<span class="badge badge-${statusColor}">${proyecto.estado}</span>`;
  
  // Create progress bar
  const progressBar = createProgressBar(proyecto.progreso);
  
  // Create actions buttons
  const actionsButtons = `
    <div class="btn-group" role="group">
      <button type="button" class="btn btn-sm btn-info" onclick="verProyecto(${proyecto.id_proyecto})" title="Ver">
        <i class="mdi mdi-eye"></i>
      </button>
      <button type="button" class="btn btn-sm btn-warning" onclick="editarProyecto(${proyecto.id_proyecto})" title="Editar">
        <i class="mdi mdi-pencil"></i>
      </button>
      <button type="button" class="btn btn-sm btn-danger" onclick="eliminarProyecto(${proyecto.id_proyecto})" title="Eliminar">
        <i class="mdi mdi-delete"></i>
      </button>
    </div>
  `;
  
  row.innerHTML = `
    <td>${index}</td>
    <td>
      <strong>${truncateText(proyecto.nombre, 30)}</strong>
    </td>
    <td>${truncateText(proyecto.descripcion, 40)}</td>
    <td>${proyecto.area}</td>
    <td>${formatDate(proyecto.fecha_cumplimiento)}</td>
    <td>
      ${progressBar}
    </td>
    <td>
      ${statusBadge}
    </td>
    <td>${proyecto.participante}</td>
    <td>
      ${actionsButtons}
    </td>
  `;
  
  return row;
}

/**
 * Create a progress bar element
 * @param {number} progress - Progress percentage (0-100)
 * @returns {string} - HTML string for progress bar
 */
function createProgressBar(progress) {
  const progressClass = progress >= 75 ? 'bg-success' : progress >= 50 ? 'bg-info' : progress >= 25 ? 'bg-warning' : 'bg-danger';
  
  return `
    <div class="progress" style="height: 20px;">
      <div class="progress-bar ${progressClass}" role="progressbar" style="width: ${progress}%;" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100">
        ${progress}%
      </div>
    </div>
  `;
}

/**
 * Get Bootstrap badge color based on status
 * @param {string} estado - Project status
 * @returns {string} - Bootstrap color class
 */
function getStatusColor(estado) {
  const colorMap = {
    'pendiente': 'secondary',
    'en proceso': 'primary',
    'vencido': 'danger',
    'completado': 'success'
  };
  
  return colorMap[estado] || 'secondary';
}

/**
 * Truncate text to specified length
 * @param {string} text - Text to truncate
 * @param {number} length - Maximum length
 * @returns {string} - Truncated text
 */
function truncateText(text, length) {
  if (!text) return '-';
  return text.length > length ? text.substring(0, length) + '...' : text;
}

/**
 * Format date to readable format
 * @param {string} dateString - Date string from database
 * @returns {string} - Formatted date
 */
function formatDate(dateString) {
  if (!dateString) return '-';
  
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  const date = new Date(dateString);
  return date.toLocaleDateString('es-MX', options);
}

/**
 * View project details
 * @param {number} idProyecto - Project ID
 */
function verProyecto(idProyecto) {
  // Redirect to project details page
  window.location.href = `../detallesProyecto/?id=${idProyecto}`;
}

/**
 * Edit project
 * @param {number} idProyecto - Project ID
 */
function editarProyecto(idProyecto) {
  // Redirect to edit project page
  window.location.href = `../editarProyecto/?id=${idProyecto}`;
}

/**
 * Delete project with confirmation
 * @param {number} idProyecto - Project ID
 */
function eliminarProyecto(idProyecto) {
  if (confirm('¿Estás seguro de que deseas eliminar este proyecto? Esta acción no se puede deshacer.')) {
    fetch('../php/eliminar_proyecto.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        id_proyecto: idProyecto
      })
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        alert('Proyecto eliminado exitosamente');
        cargarProyectos(); // Reload projects table
      } else {
        alert('Error al eliminar el proyecto: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error deleting project:', error);
      alert('Error al eliminar el proyecto');
    });
  }
}