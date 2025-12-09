/*task_assignment.js para el correcto asignamiento de tareas*/ 
document.addEventListener('DOMContentLoaded', function() {
  
  const projectSelect = document.getElementById('id_proyecto');
  const tasksList = document.getElementById('tasksList');
  const tasksLoading = document.getElementById('tasksLoading');
  const addBtn = document.querySelector('.todo-list-add-btn');

  cargarProyectos();

  //listener de event para seleccionar proyecto
  projectSelect.addEventListener('change', function() {
    if (this.value) {
      loadTasks(this.value);
    } else {
      //mensaje default cuando no hauy proyectos 
      tasksList.innerHTML = `
        <li class="d-block text-center py-4">
          <p class="text-muted">Seleccione un proyecto para ver sus tareas</p>
        </li>
      `;
    }
  });

function loadDepartamentos() {
  fetch('../php/get_departments.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de red no fue ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.departamentos) {
        const select = document.getElementById('id_departamento');
        data.departamentos.forEach(dept => {
          const option = document.createElement('option');
          option.value = dept.id_departamento;
          option.textContent = dept.nombre;
          select.appendChild(option);
        });
      } else {
        showNotification('Error al cargar departamentos', 'warning');
      }
    })
    .catch(error => {
      console.error('Error al cargar los departamentos:', error);
      showNotification('Error al cargar departamentos', 'danger');
    });
}
function cargarProyectos() {
  fetch('../php/get_projects.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de red no fue ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.proyectos) {
        const select = document.getElementById('id_proyecto');
        data.proyectos.forEach(proyect => {
          const option = document.createElement('option');
          option.value = proyect.id_proyecto;
          option.textContent = proyect.nombre;
          select.appendChild(option);
        });
      } else {
        showNotification('Error al cargar proyectos', 'warning');
      }
    })
    .catch(error => {
      console.error('Error al cargar los proyectos:', error);
      showNotification('Error al cargar proyectos', 'danger');
    });
}
/*
  function loadProjects() {
    fetch('../php/get_projects.php')
      .then(response => response.json())
      .then(data => {
        if (data.success && data.projects) {
          projectSelect.innerHTML = '<option value="">Seleccione un proyecto</option>';
          
          data.projects.forEach(project => {
            const option = document.createElement('option');
            option.value = project.id_proyecto;
            option.textContent = `${project.nombre} (${project.estado})`;
            projectSelect.appendChild(option);
          });
        } else {
          projectSelect.innerHTML = '<option value="">No hay proyectos disponibles</option>';
        }
      })
      .catch(error => {
        console.error('Error loading projects:', error);
        projectSelect.innerHTML = '<option value="">Error al cargar proyectos</option>';
      });
  }
*/
  function loadTasks(id_proyecto) {
    tasksLoading.style.display = 'block';
    tasksList.style.display = 'none';

    fetch(`../php/get_tasks_by_project.php?id_proyecto=${id_proyecto}`)
      .then(response => response.json())
      .then(data => {
        tasksLoading.style.display = 'none';
        tasksList.style.display = 'block';

        if (data.success && data.tasks && data.tasks.length > 0) {
          renderTasks(data.tasks);
        } else {
          tasksList.innerHTML = `
            <li class="d-block text-center py-4">
              <p class="text-muted">No hay tareas para este proyecto</p>
            </li>
          `;
        }
      })
      .catch(error => {
        console.error('Error loading tasks:', error);
        tasksLoading.style.display = 'none';
        tasksList.style.display = 'block';
        tasksList.innerHTML = `
          <li class="d-block text-center py-4">
            <p class="text-danger">Error al cargar las tareas</p>
          </li>
        `;
      });
  }

  function renderTasks(tasks) {
    tasksList.innerHTML = '';

    tasks.forEach((task, index) => {
      const taskHTML = createTaskElement(task, index === tasks.length - 1);
      tasksList.insertAdjacentHTML('beforeend', taskHTML);
    });

    addCheckboxListeners();//listener de evento de checkboxes
  }

  function createTaskElement(task, isLast) {
    const dateObj = new Date(task.fecha_cumplimiento);//formato de fecha
    const formattedDate = dateObj.toLocaleDateString('es-MX', { 
      day: '2-digit', 
      month: 'short', 
      year: 'numeric' 
    });

    let badgeClass = 'badge-opacity-warning';//color de insignia basado en el estado
    let badgeText = 'Pendiente';

    if (task.estado === 'completado') {
      badgeClass = 'badge-opacity-success';
      badgeText = 'Completado';
    } else if (task.estado === 'en proceso') {
      badgeClass = 'badge-opacity-info';
      badgeText = 'En Progreso';
    } else if (task.estado === 'vencido') {
      badgeClass = 'badge-opacity-danger';
      badgeText = 'Vencido';
    }

    const isCompleted = task.estado === 'completado';//revisar si la tarea esta terminadoa
    const borderClass = isLast ? 'border-bottom-0' : '';

    return `
      <li class="d-block ${borderClass}" data-task-id="${task.id_tarea}">
        <div class="form-check w-100">
          <label class="form-check-label">
            <input class="checkbox task-checkbox" type="checkbox" ${isCompleted ? 'checked' : ''} data-task-id="${task.id_tarea}"> 
            ${task.nombre} 
            <i class="input-helper rounded"></i>
          </label>
          <div class="d-flex mt-2">
            <div class="ps-4 text-small me-3">${formattedDate}</div>
            <div class="badge ${badgeClass} me-3">${badgeText}</div>
            <i class="mdi mdi-flag ms-2 flag-color"></i>
          </div>
          <div class="ps-4 text-muted small mt-1">${task.descripcion}</div>
        </div>
      </li>
    `;
  }

  function addCheckboxListeners() {
    const checkboxes = document.querySelectorAll('.task-checkbox');
    
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const taskId = this.getAttribute('data-task-id');
        const isChecked = this.checked;
        
        //agregar funcionalidad para actualizar el estado de la tarea en bd
        const taskLi = this.closest('li');
        if (isChecked) {
          taskLi.style.opacity = '0.6';
        } else {
          taskLi.style.opacity = '1';
        }
      });
    });
  }

  if (addBtn) {
    addBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      if (!projectSelect.value) {//revisar si se ha seleccionado algun proyecto
        alert('Por favor seleccione un proyecto primero');
        return;
      }
      
      const modal = document.getElementById('addTaskModal');//mostrar modal
      if (modal) {
        const taskModal = new bootstrap.Modal(modal);
        taskModal.show();
      }
    });
  }

});