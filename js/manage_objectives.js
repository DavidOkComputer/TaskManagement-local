/**manage objectives maneja la carga y muestra todos lo objetivos de la tabla con botones de accion
 */

document.addEventListener('DOMContentLoaded', function() {//cargar tabla de objetivos cuando cargue la pagina
  cargarObjetivos();
});

function cargarObjetivos() {
  const tableBody = document.querySelector('table tbody');
  
  fetch('../php/get_objectives.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de red no fue ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.objetivos) {
        tableBody.innerHTML = ''; // limpiar spiner
        
        if (data.objetivos.length === 0) {
          tableBody.innerHTML = `
            <tr>
              <td colspan="9" class="text-center">
                <p class="mt-3">No hay objetivos registrados</p>
              </td>
            </tr>
          `;
          return;
        }

        data.objetivos.forEach((objetivo, index) => {//llenar la tabla con objetivos
          const row = createObjectiveRow(objetivo, index + 1);
          tableBody.appendChild(row);
        });
      } else {
        tableBody.innerHTML = `
          <tr>
            <td colspan="9" class="text-center text-danger">
              <p class="mt-3">Error al cargar objetivos</p>
            </td>
          </tr>
        `;
      }
    })
    .catch(error => {
      console.error('Error al cargar los objetivos:', error);
      tableBody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center text-danger">
            <p class="mt-3">Error al cargar los objetivos</p>
          </td>
        </tr>
      `;
    });
}

function createObjectiveRow(objetivo, index) {
  const row = document.createElement('tr');
  
  const statusColor = getStatusColor(objetivo.estado);//color de la insignia de estatus
  const statusBadge = `<span class="badge badge-${statusColor}">${objetivo.estado}</span>`;
  
  const progressBar = createProgressBar(objetivo.progreso);
  
  const actionsButtons = `
    <div class="action-buttons">
      <button class="btn btn-sm btn-success btn-action" onclick="editarobjetivo(${objetivo.id_objetivo})" title="Editar">
        <i class="mdi mdi-pencil"></i>
      </button>
      <button class="btn btn-sm btn-danger btn-action" onclick="eliminarobjetivo(${objetivo.id_objetivo})" title="Eliminar">
        <i class="mdi mdi-delete"></i>
      </button>
    </div>
  `;
  
  row.innerHTML = `
    <td>${index}</td>
    <td>
      <strong>${truncateText(objetivo.nombre, 30)}</strong>
    </td>
    <td>${truncateText(objetivo.descripcion, 40)}</td>
    <td>${objetivo.area}</td>
    <td>${formatDate(objetivo.fecha_cumplimiento)}</td>
    <td>
      ${progressBar}
    </td>
    <td>
      ${statusBadge}
    </td>
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

function verobjetivo(idObjetivo) {
  window.location.href = `../detallesobjetivo/?id=${idObjetivo}`;//redirigir a detalles de objetivo
}

function editarobjetivo(idObjetivo) {//redirigir a pagina de editar objetivo
  window.location.href = `../editarObjetivo/?id=${idObjetivo}`;
}

function eliminarobjetivo(idObjetivo) {
  if (confirm('¿Estás seguro de que deseas eliminar este objetivo? Esta acción no se puede deshacer.')) {
    fetch('../php/delete_objective.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        id_objetivo: idObjetivo
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
        alert('objetivo eliminado exitosamente');
        cargarObjetivos(); //recargar tabla de objetivos 
      } else {
        alert('Error al eliminar el objetivo: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error deleting objective:', error);
      alert('Error al eliminar el objetivo');
    });
  }
}