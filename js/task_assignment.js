document.addEventListener('DOMContentLoaded', function() {
  
  // Get elements
  const projectSelect = document.getElementById('projectSelect');
  const tasksList = document.getElementById('tasksList');
  const tasksLoading = document.getElementById('tasksLoading');
  const addBtn = document.querySelector('.todo-list-add-btn');

  // Load projects on page load
  loadProjects();

  // Event listener for project selection change
  projectSelect.addEventListener('change', function() {
    if (this.value) {
      loadTasks(this.value);
    } else {
      // Show default message when no project selected
      tasksList.innerHTML = `
        <li class="d-block text-center py-4">
          <p class="text-muted">Seleccione un proyecto para ver sus tareas</p>
        </li>
      `;
    }
  });

  /**
   * Load all active projects from database
   */
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

  /**
   * Load tasks for selected project from database
   * @param {number} id_proyecto - Project ID
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

  /**
   * Render tasks in the list
   * @param {Array} tasks - Array of task objects from database
   */
  function renderTasks(tasks) {
    tasksList.innerHTML = '';

    tasks.forEach((task, index) => {
      const taskHTML = createTaskElement(task, index === tasks.length - 1);
      tasksList.insertAdjacentHTML('beforeend', taskHTML);
    });

    // Add event listeners to checkboxes
    addCheckboxListeners();
  }

  /**
   * Create HTML element for a single task
   * @param {Object} task - Task object
   * @param {boolean} isLast - Is this the last task in the list
   * @returns {string} HTML string
   */
  function createTaskElement(task, isLast) {
    // Format date
    const dateObj = new Date(task.fecha_cumplimiento);
    const formattedDate = dateObj.toLocaleDateString('es-MX', { 
      day: '2-digit', 
      month: 'short', 
      year: 'numeric' 
    });

    // Determine badge class and text based on status
    let badgeClass = 'badge-opacity-warning';
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

    // Check if task is completed
    const isCompleted = task.estado === 'completado';
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

  /**
   * Add event listeners to task checkboxes
   */
  function addCheckboxListeners() {
    const checkboxes = document.querySelectorAll('.task-checkbox');
    
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const taskId = this.getAttribute('data-task-id');
        const isChecked = this.checked;
        
        // You can add functionality here to update task status in database
        console.log(`Task ${taskId} checked: ${isChecked}`);
        
        // Optional: Update the visual state
        const taskLi = this.closest('li');
        if (isChecked) {
          taskLi.style.opacity = '0.6';
        } else {
          taskLi.style.opacity = '1';
        }
      });
    });
  }

  // Add button event listener
  if (addBtn) {
    addBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      // Check if a project is selected
      if (!projectSelect.value) {
        alert('Por favor seleccione un proyecto primero');
        return;
      }
      
      // Show the task modal from task-modal.js
      const modal = document.getElementById('addTaskModal');
      if (modal) {
        const taskModal = new bootstrap.Modal(modal);
        taskModal.show();
      }
    });
  }

});