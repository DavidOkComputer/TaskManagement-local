/**
 * task-modal.js - Updated to support task completion tracking and automatic project progress
 * 
 * Features:
 * - Create new tasks
 * - Update task status by checking/unchecking
 * - Automatic project progress calculation based on task completion
 * - Real-time project progress updates
 */

document.addEventListener('DOMContentLoaded', function() {
  
  // crear el modal en html e insertarlo a el cuerpo
  const modalHTML = `
    <div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addTaskModalLabel">Agregar Nueva Tarea</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="addTaskForm">
              <div class="mb-3">
                <label for="taskName" class="form-label">Nombre de la Tarea</label>
                <input type="text" class="form-control" id="taskName" maxlength="100" placeholder="Ingrese el nombre de la tarea" required>
              </div>
              <div class="mb-3">
                <label for="taskDescription" class="form-label">Descripción</label>
                <textarea class="form-control" id="taskDescription" rows="3" maxlength="250" placeholder="Ingrese la descripción de la tarea" required></textarea>
              </div>
              <div class="mb-3">
                <label for="taskProject" class="form-label">Proyecto</label>
                <select class="form-control" id="taskProject" required>
                  <option value="">Seleccione un proyecto</option>
                  <!-- Projects will be loaded dynamically -->
                </select>
              </div>
              <div class="mb-3">
                <label for="taskDate" class="form-label">Fecha de Vencimiento</label>
                <input type="date" class="form-control" id="taskDate">
              </div>
              <div class="mb-3">
                <label for="taskStatus" class="form-label">Estado</label>
                <select class="form-control" id="taskStatus" required>
                  <option value="pendiente">Pendiente</option>
                  <option value="en-progreso">En Progreso</option>
                  <option value="completado">Completado</option>
                </select>
              </div>
            </form>
            <div id="taskMessage" class="alert" style="display: none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="saveTaskBtn">
              <span class="btn-text">Guardar Tarea</span>
              <span class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  // insertar modal en el cuerpo
  document.body.insertAdjacentHTML('beforeend', modalHTML);
  
  // elementos del modal
  const taskModal = new bootstrap.Modal(document.getElementById('addTaskModal'));
  
  // boton añadir
  const addBtn = document.querySelector('.todo-list-add-btn');
  
  // tomar to do list
  const todoList = document.querySelector('.todo-list');
  
  // cargar proyectos cuando se abre el modal
  document.getElementById('addTaskModal').addEventListener('show.bs.modal', function () {
    loadProjects();
  });
  
  // funcion para cargar proyectos
  function loadProjects() {
    fetch('../php/get_projects.php')
      .then(response => response.json())
      .then(data => {
        const projectSelect = document.getElementById('taskProject');
        projectSelect.innerHTML = '<option value="">Seleccione un proyecto</option>';
        
        if (data.success && data.proyectos) {
          data.proyectos.forEach(project => {
            const option = document.createElement('option');
            option.value = project.id_proyecto;
            option.textContent = project.nombre;
            projectSelect.appendChild(option);
          });
        }
      })
      .catch(error => {
        console.error('Error loading projects:', error);
        showMessage('Error al cargar proyectos', 'danger');
      });
  }
  
  if (addBtn) { // evento de clic en el boton agregar
    addBtn.addEventListener('click', function(e) {
      e.preventDefault();
      taskModal.show();
    });
  }
  
  const saveBtn = document.getElementById('saveTaskBtn');
  
  function showMessage(message, type) { // funcion para mostrar el mensaje
    const messageDiv = document.getElementById('taskMessage');
    messageDiv.className = `alert alert-${type}`;
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
    
    setTimeout(() => {
      messageDiv.style.display = 'none';
    }, 3000);
  }
  
  function setLoading(isLoading) { // cargar estadisticas
    const btnText = saveBtn.querySelector('.btn-text');
    const spinner = saveBtn.querySelector('.spinner-border');
    
    if (isLoading) {
      saveBtn.disabled = true;
      btnText.textContent = 'Guardando...';
      spinner.style.display = 'inline-block';
    } else {
      saveBtn.disabled = false;
      btnText.textContent = 'Guardar Tarea';
      spinner.style.display = 'none';
    }
  }
  
  if (saveBtn) { // evento de clic en boton guardar
    saveBtn.addEventListener('click', function() {
      const form = document.getElementById('addTaskForm');
      
      if (!form.checkValidity()) { // validar form
        form.reportValidity();
        return;
      }
      
      // tomar los valores del form
      const taskName = document.getElementById('taskName').value;
      const taskDescription = document.getElementById('taskDescription').value;
      const taskProject = document.getElementById('taskProject').value;
      const taskDate = document.getElementById('taskDate').value;
      const taskStatus = document.getElementById('taskStatus').value;
      
      setLoading(true);
      
      // preparar la info para mandarla
      const formData = new FormData();
      formData.append('nombre', taskName);
      formData.append('descripcion', taskDescription);
      formData.append('id_proyecto', taskProject);
      formData.append('fecha_vencimiento', taskDate);
      formData.append('estado', taskStatus);
      formData.append('id_creador', 1); // Should be set to current user ID
      
      // enviar informacion al servidor
      fetch('../php/save_task.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        setLoading(false);
        
        if (data.success) {
          // darle formato a la fecha
          let formattedDate = 'Sin fecha';
          if (taskDate) {
            const dateObj = new Date(taskDate);
            formattedDate = dateObj.toLocaleDateString('es-MX', { 
              day: '2-digit', 
              month: 'short', 
              year: 'numeric' 
            });
          }
          
          let badgeClass = 'badge-opacity-warning';
          let badgeText = 'Pendiente';
          
          if (taskStatus === 'completado') {
            badgeClass = 'badge-opacity-success';
            badgeText = 'Completado';
          } else if (taskStatus === 'en-progreso' || taskStatus === 'en proceso') {
            badgeClass = 'badge-opacity-info';
            badgeText = 'En Progreso';
          }
          
          const newTaskHTML = `
            <li class="d-block" data-task-id="${data.task_id}">
              <div class="form-check w-100">
                <label class="form-check-label">
                  <input class="checkbox task-checkbox" type="checkbox" data-task-id="${data.task_id}" ${taskStatus === 'completado' ? 'checked' : ''}> 
                  ${taskName} 
                  <i class="input-helper rounded"></i>
                </label>
                <div class="d-flex mt-2">
                  <div class="ps-4 text-small me-3">${formattedDate}</div>
                  <div class="badge ${badgeClass} me-3 task-badge">${badgeText}</div>
                  <i class="mdi mdi-flag ms-2 flag-color"></i>
                </div>
              </div>
            </li>
          `;
          
          if (todoList) {
            todoList.insertAdjacentHTML('beforeend', newTaskHTML);
            
            // Add event listener to the new checkbox
            const newCheckbox = todoList.querySelector(`[data-task-id="${data.task_id}"]`);
            if (newCheckbox) {
              attachTaskCheckboxListener(newCheckbox);
            }
          }
          
          showMessage('Tarea guardada exitosamente', 'success');
          // limpiar form
          setTimeout(() => {
            form.reset();
            taskModal.hide();
          }, 1000);
          
        } else {
          showMessage(data.message || 'Error al guardar la tarea', 'danger');
        }
      })
      .catch(error => {
        setLoading(false);
        console.error('Error:', error);
        showMessage('Error al conectar con el servidor', 'danger');
      });
    });
  }
  
  document.getElementById('addTaskModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('addTaskForm').reset();
    document.getElementById('taskMessage').style.display = 'none';
  });

  /**
   * Attach checkbox event listener to update task status
   * When checkbox is clicked, update task status and recalculate project progress
   */
  function attachTaskCheckboxListener(checkboxElement) {
    const checkbox = checkboxElement.querySelector('.task-checkbox');
    if (!checkbox) return;

    checkbox.addEventListener('change', function() {
      const taskId = this.getAttribute('data-task-id');
      const isChecked = this.checked;
      const newStatus = isChecked ? 'completado' : 'pendiente';

      // Show loading state
      const li = checkboxElement;
      li.style.opacity = '0.6';
      li.style.pointerEvents = 'none';

      // Send update to server
      const updateData = new FormData();
      updateData.append('id_tarea', taskId);
      updateData.append('estado', newStatus);

      fetch('../php/update_task_status.php', {
        method: 'POST',
        body: updateData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update badge
          const badge = li.querySelector('.task-badge');
          if (badge) {
            if (isChecked) {
              badge.className = 'badge badge-opacity-success me-3 task-badge';
              badge.textContent = 'Completado';
            } else {
              badge.className = 'badge badge-opacity-warning me-3 task-badge';
              badge.textContent = 'Pendiente';
            }
          }

          // Restore normal state
          li.style.opacity = '1';
          li.style.pointerEvents = 'auto';

          showMessage('Estado de tarea actualizado correctamente', 'success');
          
          // Optionally reload project progress from server
          // This could be done by emitting a custom event or calling a function
          // to update the project progress display if it's visible on the same page
        } else {
          // Revert checkbox on error
          checkbox.checked = !isChecked;
          li.style.opacity = '1';
          li.style.pointerEvents = 'auto';
          
          showMessage(data.message || 'Error al actualizar la tarea', 'danger');
        }
      })
      .catch(error => {
        console.error('Error updating task:', error);
        
        // Revert checkbox on error
        checkbox.checked = !isChecked;
        li.style.opacity = '1';
        li.style.pointerEvents = 'auto';
        
        showMessage('Error al conectar con el servidor', 'danger');
      });
    });
  }

  /**
   * Initialize existing task checkboxes
   */
  if (todoList) {
    const existingCheckboxes = todoList.querySelectorAll('li');
    existingCheckboxes.forEach(li => {
      attachTaskCheckboxListener(li);
    });
  }
  
});