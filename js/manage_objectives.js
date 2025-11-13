/**manage objectives maneja la carga y muestra todos lo objetivos de la tabla con botones de accion
 */

const Config = {
  API_ENDPOINTS:  {
    DELETE: '../php/delete_objective.php'
  }
}
let allObjectives = [];
document.addEventListener('DOMContentLoaded', function() {//cargar tabla de objetivos cuando cargue la pagina
  cargarObjetivos();
  createCustomDialogSystem();
  setupSearch();
});


function cargarObjetivos() {
  const tableBody = document.querySelector('table tbody');
  if(!tableBody) { 
        console.error('El elemento de cuerpo de tabla no fue encontrado'); 
        return; 
    }

    //mostrar estado de carga 
    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="9" class="text-center"> 
                <div class="spinner-border text-primary" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <p class="mt-2">Cargando proyectos...</p> 
            </td> 
        </tr> 
    `; 

  fetch('../php/get_objectives.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de red no fue ok');
      }
      return response.json();
    })
    .then(data => {
      console.log('Informacion recivida:', data);//para debug
      if (data.success && data.objetivos) {
        tableBody.innerHTML = ''; // limpiar spinner
        allObjectives = data.objetivos;//guardar para funcion de buscar
        displayObjectives(data.objetivos);
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

function displayObjectives(objectives){
  const tableBody = document.querySelector('#objetivosTableBody'); 
    if(!tableBody) return; 
    tableBody.innerHTML = ''; 
    if(!objectives || objectives.length === 0) { 
        displayEmptyState(); 
        return; 
    } 
    objectives.forEach((objective, index) => { 
        const row = createObjectiveRow(objective, index + 1); 
        tableBody.appendChild(row); 
    }); 
}

function createObjectiveRow(objetivo, index) {
  const row = document.createElement('tr');
  
  const statusColor = getStatusColor(objetivo.estado);//color de la insignia de estatus
  const statusBadge = `<span class="badge badge-${statusColor}">${objetivo.estado}</span>`;
  
  const progressBar = createProgressBar(objetivo.progreso);
  
  const actionsButtons = `
    <div class="action-buttons">
      <button class="btn btn-sm btn-success btn-action" onclick="editarObjetivo(${objetivo.id_objetivo})" title="Editar">
        <i class="mdi mdi-pencil"></i>
      </button>
      <button class="btn btn-sm btn-danger btn-action" data-objective-id="${objetivo.id_objetivo}" data-nombre="${escapeHtml(objetivo.nombre)}" onclick="confirmDelete(${objetivo.id_objetivo}, '${escapeHtml(objetivo.nombre)}')" title="Eliminar">
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

function confirmDelete(id, nombre) { 
    showConfirm(
        `¿Está seguro de que desea eliminar el objetivo "${escapeHtml(nombre)}"?\n\nEsta acción no se puede deshacer.`,
        function() {
            deleteObjective(id);
        },
        'Confirmar eliminación',
        {
            type: 'danger',
            confirmText: 'Eliminar',
            cancelText: 'Cancelar'
        }
    );
} 

function deleteObjective(id) {
    //se envia json en ves de data
    fetch(Config.API_ENDPOINTS.DELETE, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json' 
        },
        body: JSON.stringify({ id_objetivo: id }) 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message || 'Objetivo eliminado exitosamente');
            allObjectives = allObjectives.filter(o => o.id_objetivo != id); 
            cargarObjetivos();
        } else {
            showErrorAlert(data.message || 'Error al eliminar el objetivo');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorAlert('Error al conectar con el servidor');
    });
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

function displayEmptyState() { 
    const tableBody = document.querySelector('#objetivosTableBody'); 
    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="9" class="text-center empty-state"> 
                <i class="mdi mdi-folder-open" style="font-size: 48px; color: #ccc;"></i> 
                <h5 class="mt-3">No hay objetivos registrados</h5> 
                <p>Comienza creando un nuevo objetivo</p> 
                <a href="../nuevoProyecto/" class="btn btn-success mt-3"> 
                    <i class="mdi mdi-plus-circle-outline"></i> Crear objetivo 
                </a> 
            </td> 
        </tr> 
    `; 
} 

function setupSearch() { 
    const searchInput = document.getElementById('searchInput'); 
    const searchForm = document.getElementById('searchForm'); 
    if (!searchInput) { 
        console.warn('Input de busqueda no encontrado'); 
        return; 
    } 
    if (searchForm) { 
        searchForm.addEventListener('submit', function(e) { 
            e.preventDefault(); 
        }); 
    } 
    let searchTimeout; 
    searchInput.addEventListener('input', function() { 
        clearTimeout(searchTimeout); 
        searchTimeout = setTimeout(() => { 
            performSearch(this.value); 
        }, 300); 
    }); 
} 

function performSearch(query) {
    const normalizedQuery = query.toLowerCase().trim(); 
    if (normalizedQuery === '') { 
        displayObjectives(allObjectives); 
        return; 
    } 
    const filtered = allObjectives.filter(objective => { 
        return objective.nombre.toLowerCase().includes(normalizedQuery) ||  
               (objective.descripcion && objective.descripcion.toLowerCase().includes(normalizedQuery)) ||  
               (objective.area && objective.area.toLowerCase().includes(normalizedQuery)) 
    }); 
    displayObjectives(filtered); 
    if (filtered.length === 0) { 
        const tableBody = document.querySelector('#objetivosTableBody'); 
        tableBody.innerHTML = ` 
            <tr> 
                <td colspan="9" class="text-center empty-state"> 
                    <i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
                    <h5 class="mt-3">No se encontraron resultados</h5> 
                    <p>No hay objetivos que coincidan con "${escapeHtml(query)}"</p> 
                </td> 
            </tr> 
        `; 
    } 
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

function verObjetivo(idObjetivo) {
  window.location.href = `../detallesObjetivo/?id=${idObjetivo}`;//redirigir a detalles de objetivo
}

function editarObjetivo(idObjetivo) {//redirigir a pagina de nuevo objetivo en modo edicion
  window.location.href = `../nuevoObjetivo/?edit=${idObjetivo}`;
}

function showSuccessAlert(message) { 
    showAlert(message, 'success'); 
} 

function showErrorAlert(message) { 
    showAlert(message, 'danger'); 
} 

function showAlert(message, type) { 
    const alertDiv = document.getElementById('alertMessage'); 
    if (!alertDiv) return; 
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger'; 
    const icon = type === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle'; 
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show`; 
    alertDiv.innerHTML = ` 
        <i class="mdi ${icon} me-2"></i> 
        ${message} 
        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button> 
    `; 
    alertDiv.style.display = 'block'; 

    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); 

    setTimeout(() => { 
        if (alertDiv.style.display !== 'none') { 
            alertDiv.style.display = 'none'; 
        } 
    }, 5000); 
} 

function escapeHtml(text) { 
    const map = { 
        '&': '&amp;', 
        '<': '&lt;', 
        '>': '&gt;', 
        '"': '&quot;', 
        "'": '&#039;' 
    }; 
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; }); 
} 

function createCustomDialogSystem() {
    const dialogHTML = `
        <!-- Custom Alert Dialog -->
        <div class="modal fade" id="customAlertModal" tabindex="-1" role="dialog" aria-labelledby="customAlertLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="customAlertLabel">
                            <i class="mdi mdi-information-outline me-2"></i>
                            <span id="alertTitle">Información</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="alertMessage" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Custom Confirm Dialog -->
        <div class="modal fade" id="customConfirmModal" tabindex="-1" role="dialog" aria-labelledby="customConfirmLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="customConfirmLabel">
                            <i class="mdi mdi-help-circle-outline me-2"></i>
                            <span id="confirmTitle">Confirmar acción</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmMessage" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmCancelBtn">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="confirmOkBtn">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', dialogHTML);
}

// mostrar dialogo de confirmacion de la app y no navegador
function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
    const modal = document.getElementById('customConfirmModal');
    const titleElement = document.getElementById('confirmTitle');
    const messageElement = document.getElementById('confirmMessage');
    const headerElement = modal.querySelector('.modal-header');
    const iconElement = modal.querySelector('.modal-title i');
    const confirmBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    
    //opciones default
    const config = {
        confirmText: 'Aceptar',
        cancelText: 'Cancelar',
        type: 'warning',
        ...options
    };
    
    //titulo y mensaje
    titleElement.textContent = title;
    messageElement.innerHTML = message.replace(/\n/g, '<br>'); // Preserve line breaks
    
    //cambiar el texto de los botones
    confirmBtn.textContent = config.confirmText;
    cancelBtn.textContent = config.cancelText;
    
    //clases del header
    headerElement.className = 'modal-header';
    
    const iconMap = {
        'info': {
            icon: 'mdi-information-outline',
            class: 'bg-info text-white',
            btnClass: 'btn-info'
        },
        'warning': {
            icon: 'mdi-alert-outline',
            class: 'bg-warning text-white',
            btnClass: 'btn-warning'
        },
        'danger': {
            icon: 'mdi-alert-octagon-outline',
            class: 'bg-danger text-white',
            btnClass: 'btn-danger'
        },
        'success': {
            icon: 'mdi-check-circle-outline',
            class: 'bg-success text-white',
            btnClass: 'btn-success'
        }
    };
    
    const typeConfig = iconMap[config.type] || iconMap['warning'];
    iconElement.className = `mdi ${typeConfig.icon} me-2`;
    headerElement.classList.add(...typeConfig.class.split(' '));
    
    //actualizar el estilo del boton confirmar
    confirmBtn.className = `btn ${typeConfig.btnClass}`;
    
    //eliminar listeners anteriores clonando y remplazando
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    const newCancelBtn = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    
    //agregar nuevo event listener
    newConfirmBtn.addEventListener('click', function() {
        const confirmModal = bootstrap.Modal.getInstance(modal);
        confirmModal.hide();
        if (onConfirm && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
    
    //mostrar modal
    const confirmModal = new bootstrap.Modal(modal);
    confirmModal.show();
}

window.confirmDelete = confirmDelete;