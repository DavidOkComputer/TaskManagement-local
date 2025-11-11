/**manage projects maneja la carga y muestra todos lo proyectos de la tabla con botones de accion
 */

document.addEventListener('DOMContentLoaded', function() {//cargar tabla de proectos cuando cargue la pagina
  cargarProyectos();
});

function cargarProyectos() {
  const tableBody = document.querySelector('table tbody');
  
  fetch('../php/get_projects.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de red no fue ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.proyectos) {
        tableBody.innerHTML = ''; // limpiar spiner
        
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

        data.proyectos.forEach((proyecto, index) => {//llenar la tabla con proyectos
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

function createProjectRow(proyecto, index) {
  const row = document.createElement('tr');
  
  const statusColor = getStatusColor(proyecto.estado);//color de la insignia de estatus
  const statusBadge = `<span class="badge badge-${statusColor}">${proyecto.estado}</span>`;
  
  const progressBar = createProgressBar(proyecto.progreso);
  
  const actionsButtons = `
    <div class="action-buttons">
      <button class="btn btn-sm btn-success btn-action" onclick="editarProyecto(${proyecto.id_proyecto})" title="Editar">
        <i class="mdi mdi-pencil"></i>
      </button>
      <button class="btn btn-sm btn-danger btn-action" onclick="eliminarProyecto(${proyecto.id_proyecto})" title="Eliminar">
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

function getStatusColor(estado) {
  const colorMap = {
    'pendiente': 'warning',
    'en proceso': 'primary',
    'vencido': 'danger',
    'completado': 'success'
  };
  
  return colorMap[estado] || 'warning';
}

function truncateText(text, length) {
  if (!text) return '-';
  return text.length > length ? text.substring(0, length) + '...' : text;
}

function formatDate(dateString) {
  if (!dateString) return '-';
  
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  const date = new Date(dateString);
  return date.toLocaleDateString('es-MX', options);
}

function verProyecto(idProyecto) {
  window.location.href = `../detallesProyecto/?id=${idProyecto}`;//redirigir a detalles de proyecto
}

function editarProyecto(idProyecto) {//redirigir a pagina de crear proyecto en modo edicion
  window.location.href = `../nuevoProyecto/?edit=${idProyecto}`;
}

function eliminarProyecto(idProyecto) {
  if (confirm('¿Estás seguro de que deseas eliminar este proyecto? Esta acción no se puede deshacer.')) {
    fetch('../php/delete_project.php', {
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
        cargarProyectos(); //recargar tabla de proyectos 
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