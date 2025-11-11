/**
 * task-management.js - Consolidated Task Management System
 * 
 * Features:
 * - Load and display projects
 * - Load and display tasks by project
 * - Create new tasks via modal
 * - Update task status with automatic badge updates
 * - Automatic project progress calculation
 * - Consistent behavior for all tasks (new and existing)
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // ELEMENT REFERENCES
    // ============================================
    const projectSelect = document.getElementById('id_proyecto');
    const tasksList = document.getElementById('tasksList');
    const tasksLoading = document.getElementById('tasksLoading');
    const addBtn = document.querySelector('.todo-list-add-btn');
    
    let currentProjectId = null; // Track currently selected project
    
    // ============================================
    // CUSTOM DIALOG SYSTEM
    // ============================================
    createCustomDialogSystem();
    
    // ============================================
    // MODAL CREATION AND INITIALIZATION
    // ============================================
    createTaskModal();
    
    function createTaskModal() {
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
                                    <input type="text" class="form-control" id="taskName" maxlength="100" 
                                           placeholder="Ingrese el nombre de la tarea" required>
                                </div>
                                <div class="mb-3">
                                    <label for="taskDescription" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="taskDescription" rows="3" maxlength="250" 
                                              placeholder="Ingrese la descripción de la tarea" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="taskProject" class="form-label">Proyecto</label>
                                    <select class="form-control" id="taskProject" required>
                                        <option value="">Seleccione un proyecto</option>
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
                                        <option value="en proceso">En Progreso</option>
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
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Initialize modal event listeners
        initializeModalEventListeners();
    }
    
    // ============================================
    // CUSTOM DIALOG SYSTEM
    // ============================================
    
    /**
     * Create custom dialog system for alerts and confirmations
     * Replaces browser's alert() and confirm() with styled modals
     */
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
    
    /**
     * Show custom alert dialog
     * @param {string} message - Message to display
     * @param {string} title - Optional title (default: "Información")
     * @param {string} type - Optional type for icon (info, warning, error, success)
     */
    function showAlert(message, title = 'Información', type = 'info') {
        const modal = document.getElementById('customAlertModal');
        const titleElement = document.getElementById('alertTitle');
        const messageElement = document.getElementById('alertMessage');
        const headerElement = modal.querySelector('.modal-header');
        const iconElement = modal.querySelector('.modal-title i');
        
        // Set title and message
        titleElement.textContent = title;
        messageElement.textContent = message;
        
        // Reset header classes
        headerElement.className = 'modal-header';
        
        // Set icon and color based on type
        const iconMap = {
            'info': {
                icon: 'mdi-information-outline',
                class: 'bg-info text-white'
            },
            'warning': {
                icon: 'mdi-alert-outline',
                class: 'bg-warning text-white'
            },
            'error': {
                icon: 'mdi-close-circle-outline',
                class: 'bg-danger text-white'
            },
            'success': {
                icon: 'mdi-check-circle-outline',
                class: 'bg-success text-white'
            }
        };
        
        const config = iconMap[type] || iconMap['info'];
        iconElement.className = `mdi ${config.icon} me-2`;
        headerElement.classList.add(config.class);
        
        // Show modal
        const alertModal = new bootstrap.Modal(modal);
        alertModal.show();
    }
    
    /**
     * Show custom confirm dialog
     * @param {string} message - Message to display
     * @param {Function} onConfirm - Callback function when user confirms
     * @param {string} title - Optional title (default: "Confirmar acción")
     * @param {Object} options - Optional configuration {confirmText, cancelText, type}
     */
    function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
        const modal = document.getElementById('customConfirmModal');
        const titleElement = document.getElementById('confirmTitle');
        const messageElement = document.getElementById('confirmMessage');
        const headerElement = modal.querySelector('.modal-header');
        const iconElement = modal.querySelector('.modal-title i');
        const confirmBtn = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');
        
        // Set default options
        const config = {
            confirmText: 'Aceptar',
            cancelText: 'Cancelar',
            type: 'warning',
            ...options
        };
        
        // Set title and message
        titleElement.textContent = title;
        messageElement.textContent = message;
        
        // Set button texts
        confirmBtn.textContent = config.confirmText;
        cancelBtn.textContent = config.cancelText;
        
        // Reset header classes
        headerElement.className = 'modal-header';
        
        // Set icon and color based on type
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
        headerElement.classList.add(typeConfig.class);
        
        // Update confirm button style
        confirmBtn.className = `btn ${typeConfig.btnClass}`;
        
        // Remove old event listeners by cloning and replacing
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        // Add new event listeners
        newConfirmBtn.addEventListener('click', function() {
            const confirmModal = bootstrap.Modal.getInstance(modal);
            confirmModal.hide();
            if (onConfirm && typeof onConfirm === 'function') {
                onConfirm();
            }
        });
        
        // Show modal
        const confirmModal = new bootstrap.Modal(modal);
        confirmModal.show();
    }
    
    // ============================================
    // INITIAL LOAD
    // ============================================
    loadProjects();
    
    // ============================================
    // PROJECT MANAGEMENT
    // ============================================
    
    /**
     * Load all projects and populate the main dropdown
     */
    function loadProjects() {
        fetch('../php/get_projects.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('La respuesta de red no fue ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.proyectos) {
                    populateProjectSelect(projectSelect, data.proyectos);
                } else {
                    showNotification('Error al cargar proyectos', 'warning');
                }
            })
            .catch(error => {
                console.error('Error al cargar los proyectos:', error);
                showNotification('Error al cargar proyectos', 'danger');
            });
    }
    
    /**
     * Populate a select element with projects
     * @param {HTMLElement} selectElement - The select element to populate
     * @param {Array} projects - Array of project objects
     */
    function populateProjectSelect(selectElement, projects) {
        // Keep the default option
        selectElement.innerHTML = '<option value="">Seleccione un proyecto</option>';
        
        projects.forEach(project => {
            const option = document.createElement('option');
            option.value = project.id_proyecto;
            option.textContent = project.nombre;
            selectElement.appendChild(option);
        });
    }
    
    /**
     * Event listener for project selection change
     */
    projectSelect.addEventListener('change', function() {
        if (this.value) {
            currentProjectId = this.value;
            loadTasks(this.value);
        } else {
            currentProjectId = null;
            showDefaultMessage();
        }
    });
    
    // ============================================
    // TASK MANAGEMENT
    // ============================================
    
    /**
     * Load tasks for a specific project
     * @param {string|number} projectId - The project ID
     */
    function loadTasks(projectId) {
        tasksLoading.style.display = 'block';
        tasksList.style.display = 'none';
        
        fetch(`../php/get_tasks_by_project.php?id_proyecto=${projectId}`)
            .then(response => response.json())
            .then(data => {
                tasksLoading.style.display = 'none';
                tasksList.style.display = 'block';
                
                if (data.success && data.tasks && data.tasks.length > 0) {
                    renderTasks(data.tasks);
                } else {
                    showNoTasksMessage();
                }
            })
            .catch(error => {
                console.error('Error loading tasks:', error);
                tasksLoading.style.display = 'none';
                tasksList.style.display = 'block';
                showErrorMessage();
            });
    }
    
    /**
     * Render tasks in the list
     * @param {Array} tasks - Array of task objects
     */
    function renderTasks(tasks) {
        tasksList.innerHTML = '';
        
        tasks.forEach((task, index) => {
            const isLast = index === tasks.length - 1;
            const taskElement = createTaskElement(task, isLast);
            tasksList.insertAdjacentHTML('beforeend', taskElement);
        });
        
        // Attach event listeners to all checkboxes and edit buttons
        attachTaskListeners();
    }
    
    /**
     * Create HTML for a single task element
     * @param {Object} task - Task object
     * @param {boolean} isLast - Whether this is the last task in the list
     * @returns {string} HTML string for the task
     */
    function createTaskElement(task, isLast = false) {
        // Format date
        const dateObj = new Date(task.fecha_cumplimiento);
        const formattedDate = dateObj.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        
        // Get badge styling based on status
        const badgeInfo = getTaskBadgeInfo(task.estado);
        
        // Check if task is completed
        const isCompleted = task.estado === 'completado';
        const borderClass = isLast ? 'border-bottom-0' : '';
        
        return `
            <li class="d-block ${borderClass}" data-task-id="${task.id_tarea}">
                <div class="form-check w-100">
                    <label class="form-check-label">
                        <input class="checkbox task-checkbox" type="checkbox" 
                               ${isCompleted ? 'checked' : ''} 
                               data-task-id="${task.id_tarea}">
                        ${task.nombre}
                        <i class="input-helper rounded"></i>
                    </label>
                    <div class="d-flex mt-2 align-items-center">
                        <div class="ps-4 text-small me-3">${formattedDate}</div>
                        <div class="badge ${badgeInfo.class} me-3 task-badge">${badgeInfo.text}</div>
                        <i class="mdi mdi-flag ms-2 flag-color"></i>
                        <button class="btn btn-sm btn-link text-primary ms-auto task-edit-btn" 
                                data-task-id="${task.id_tarea}"
                                data-task-name="${escapeHtml(task.nombre)}"
                                data-task-description="${escapeHtml(task.descripcion)}"
                                data-task-date="${task.fecha_cumplimiento}"
                                data-task-status="${task.estado}"
                                data-task-project="${task.id_proyecto}"
                                title="Editar tarea">
                            <i class="mdi mdi-pencil"></i>
                        </button>
                    </div>
                    <div class="ps-4 text-muted small mt-1">${task.descripcion}</div>
                </div>
            </li>
        `;
    }
    
    /**
     * Get badge class and text based on task status
     * @param {string} status - Task status
     * @returns {Object} Object with class and text properties
     */
    function getTaskBadgeInfo(status) {
        const statusMap = {
            'completado': {
                class: 'badge-opacity-success',
                text: 'Completado'
            },
            'en proceso': {
                class: 'badge-opacity-info',
                text: 'En Progreso'
            },
            'en-progreso': {
                class: 'badge-opacity-info',
                text: 'En Progreso'
            },
            'vencido': {
                class: 'badge-opacity-danger',
                text: 'Vencido'
            },
            'pendiente': {
                class: 'badge-opacity-warning',
                text: 'Pendiente'
            }
        };
        
        return statusMap[status] || statusMap['pendiente'];
    }
    
    /**
     * Escape HTML to prevent XSS attacks
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    /**
     * Attach event listeners to all task checkboxes and edit buttons
     */
    function attachTaskListeners() {
        // Attach checkbox listeners
        const checkboxes = document.querySelectorAll('.task-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', handleTaskStatusChange);
        });
        
        // Attach edit button listeners
        const editButtons = document.querySelectorAll('.task-edit-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', handleEditTask);
        });
    }
    
    /**
     * Handle task status change when checkbox is clicked
     * CHECKED = completado (Task is complete)
     * UNCHECKED = pendiente (Task is still pending)
     * @param {Event} event - The change event
     */
    function handleTaskStatusChange(event) {
        const checkbox = event.target;
        const taskId = checkbox.getAttribute('data-task-id');
        const isChecked = checkbox.checked;
        
        // Checked = completado, Unchecked = pendiente
        const newStatus = isChecked ? 'completado' : 'pendiente';
        
        // Get the task list item
        const taskLi = checkbox.closest('li');
        
        // Show loading state
        taskLi.style.opacity = '0.6';
        taskLi.style.pointerEvents = 'none';
        checkbox.disabled = true;
        
        // Prepare data for update
        const updateData = new FormData();
        updateData.append('id_tarea', taskId);
        updateData.append('estado', newStatus);
        
        // Send update to server
        fetch('../php/update_task_status.php', {
            method: 'POST',
            body: updateData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge
                const badge = taskLi.querySelector('.task-badge');
                const badgeInfo = getTaskBadgeInfo(newStatus);
                
                if (badge) {
                    badge.className = `badge ${badgeInfo.class} me-3 task-badge`;
                    badge.textContent = badgeInfo.text;
                }
                
                // Restore normal state
                taskLi.style.opacity = '1';
                taskLi.style.pointerEvents = 'auto';
                checkbox.disabled = false;
                
                const statusMessage = isChecked ? 'Tarea marcada como completada' : 'Tarea marcada como pendiente';
                showNotification(statusMessage, 'success');
            } else {
                // Revert checkbox on error
                checkbox.checked = !isChecked;
                taskLi.style.opacity = '1';
                taskLi.style.pointerEvents = 'auto';
                checkbox.disabled = false;
                
                showNotification(data.message || 'Error al actualizar la tarea', 'danger');
            }
        })
        .catch(error => {
            console.error('Error updating task:', error);
            
            // Revert checkbox on error
            checkbox.checked = !isChecked;
            taskLi.style.opacity = '1';
            taskLi.style.pointerEvents = 'auto';
            checkbox.disabled = false;
            
            showNotification('Error al conectar con el servidor', 'danger');
        });
    }
    
    /**
     * Handle edit task button click
     * Opens the modal in edit mode with task data pre-filled
     * @param {Event} event - The click event
     */
    function handleEditTask(event) {
        event.preventDefault();
        const button = event.currentTarget;
        
        // Get task data from button attributes
        const taskId = button.getAttribute('data-task-id');
        const taskName = button.getAttribute('data-task-name');
        const taskDescription = button.getAttribute('data-task-description');
        const taskDate = button.getAttribute('data-task-date');
        const taskStatus = button.getAttribute('data-task-status');
        const taskProject = button.getAttribute('data-task-project');
        
        // Populate modal with task data
        document.getElementById('taskName').value = taskName;
        document.getElementById('taskDescription').value = taskDescription;
        document.getElementById('taskDate').value = taskDate;
        document.getElementById('taskStatus').value = taskStatus;
        
        // Store task ID for update
        const form = document.getElementById('addTaskForm');
        form.setAttribute('data-task-id', taskId);
        form.setAttribute('data-mode', 'edit');
        
        // Change modal title
        document.getElementById('addTaskModalLabel').textContent = 'Editar Tarea';
        document.querySelector('#saveTaskBtn .btn-text').textContent = 'Actualizar Tarea';
        
        // Load projects and pre-select the task's project
        loadProjectsForModal(() => {
            document.getElementById('taskProject').value = taskProject;
        });
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
        modal.show();
    }
    
    // ============================================
    // ADD TASK MODAL FUNCTIONALITY
    // ============================================
    
    /**
     * Initialize modal event listeners
     */
    function initializeModalEventListeners() {
        const modal = document.getElementById('addTaskModal');
        const saveBtn = document.getElementById('saveTaskBtn');
        const form = document.getElementById('addTaskForm');
        
        // Load projects when modal is shown
        modal.addEventListener('show.bs.modal', function() {
            const mode = form.getAttribute('data-mode');
            
            // If not in edit mode, load projects and pre-select current project
            if (mode !== 'edit') {
                loadProjectsForModal(() => {
                    if (currentProjectId) {
                        document.getElementById('taskProject').value = currentProjectId;
                    }
                });
            }
        });
        
        // Clear form and reset modal when hidden
        modal.addEventListener('hidden.bs.modal', function() {
            form.reset();
            form.removeAttribute('data-task-id');
            form.removeAttribute('data-mode');
            document.getElementById('taskMessage').style.display = 'none';
            
            // Reset modal title and button text
            document.getElementById('addTaskModalLabel').textContent = 'Agregar Nueva Tarea';
            document.querySelector('#saveTaskBtn .btn-text').textContent = 'Guardar Tarea';
        });
        
        // Save button click handler
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSaveTask);
        }
    }
    
    /**
     * Load projects for the modal dropdown
     * @param {Function} callback - Optional callback to run after projects are loaded
     */
    function loadProjectsForModal(callback) {
        fetch('../php/get_projects.php')
            .then(response => response.json())
            .then(data => {
                const modalProjectSelect = document.getElementById('taskProject');
                
                if (data.success && data.proyectos) {
                    populateProjectSelect(modalProjectSelect, data.proyectos);
                    
                    // Run callback if provided
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                } else {
                    showModalMessage('Error al cargar proyectos', 'danger');
                }
            })
            .catch(error => {
                console.error('Error loading projects:', error);
                showModalMessage('Error al cargar proyectos', 'danger');
            });
    }
    
    /**
     * Handle save task button click
     * Handles both creating new tasks and updating existing ones
     */
    function handleSaveTask() {
        const form = document.getElementById('addTaskForm');
        
        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Get form values
        const taskName = document.getElementById('taskName').value;
        const taskDescription = document.getElementById('taskDescription').value;
        const taskProject = document.getElementById('taskProject').value;
        const taskDate = document.getElementById('taskDate').value;
        const taskStatus = document.getElementById('taskStatus').value;
        
        // Check if in edit mode
        const mode = form.getAttribute('data-mode');
        const taskId = form.getAttribute('data-task-id');
        const isEditMode = mode === 'edit' && taskId;
        
        setModalLoading(true);
        
        // Prepare data
        const formData = new FormData();
        formData.append('nombre', taskName);
        formData.append('descripcion', taskDescription);
        formData.append('id_proyecto', taskProject);
        formData.append('fecha_vencimiento', taskDate);
        formData.append('estado', taskStatus);
        
        if (isEditMode) {
            // Edit mode - update existing task
            formData.append('id_tarea', taskId);
            
            fetch('../php/update_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setModalLoading(false);
                
                if (data.success) {
                    showModalMessage('Tarea actualizada exitosamente', 'success');
                    
                    // Update the task in the list if it belongs to current project
                    if (currentProjectId && taskProject == currentProjectId) {
                        updateTaskInList(taskId, taskName, taskDescription, taskDate, taskStatus);
                    } else if (currentProjectId) {
                        // Task was moved to different project, remove from current list
                        removeTaskFromList(taskId);
                    }
                    
                    // If current project is empty and we moved the task away, reload
                    if (currentProjectId && taskProject != currentProjectId) {
                        loadTasks(currentProjectId);
                    }
                    
                    // Reset form and close modal after brief delay
                    setTimeout(() => {
                        form.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
                        modal.hide();
                    }, 1000);
                } else {
                    showModalMessage(data.message || 'Error al actualizar la tarea', 'danger');
                }
            })
            .catch(error => {
                setModalLoading(false);
                console.error('Error:', error);
                showModalMessage('Error al conectar con el servidor', 'danger');
            });
        } else {
            // Create mode - new task
            formData.append('id_creador', 1); // TODO: Replace with actual user ID
            
            fetch('../php/save_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setModalLoading(false);
                
                if (data.success) {
                    showModalMessage('Tarea guardada exitosamente', 'success');
                    
                    // If the new task belongs to the currently selected project, add it to the list
                    if (currentProjectId && taskProject == currentProjectId) {
                        addTaskToList(data.task_id, taskName, taskDescription, taskDate, taskStatus, taskProject);
                    }
                    
                    // Reset form and close modal after brief delay
                    setTimeout(() => {
                        form.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
                        modal.hide();
                    }, 1000);
                } else {
                    showModalMessage(data.message || 'Error al guardar la tarea', 'danger');
                }
            })
            .catch(error => {
                setModalLoading(false);
                console.error('Error:', error);
                showModalMessage('Error al conectar con el servidor', 'danger');
            });
        }
    }
    
    /**
     * Add a newly created task to the current task list
     * @param {string|number} taskId - Task ID
     * @param {string} taskName - Task name
     * @param {string} taskDescription - Task description
     * @param {string} taskDate - Task due date
     * @param {string} taskStatus - Task status
     * @param {string|number} taskProject - Project ID
     */
    function addTaskToList(taskId, taskName, taskDescription, taskDate, taskStatus, taskProject) {
        // Remove "no tasks" message if it exists
        const noTasksMessage = tasksList.querySelector('.text-center');
        if (noTasksMessage) {
            tasksList.innerHTML = '';
        }
        
        // Format date
        let formattedDate = 'Sin fecha';
        if (taskDate) {
            const dateObj = new Date(taskDate);
            formattedDate = dateObj.toLocaleDateString('es-MX', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
        
        // Get badge info
        const badgeInfo = getTaskBadgeInfo(taskStatus);
        
        // Create task HTML
        const newTaskHTML = `
            <li class="d-block" data-task-id="${taskId}">
                <div class="form-check w-100">
                    <label class="form-check-label">
                        <input class="checkbox task-checkbox" type="checkbox" 
                               data-task-id="${taskId}" 
                               ${taskStatus === 'completado' ? 'checked' : ''}>
                        ${taskName}
                        <i class="input-helper rounded"></i>
                    </label>
                    <div class="d-flex mt-2 align-items-center">
                        <div class="ps-4 text-small me-3">${formattedDate}</div>
                        <div class="badge ${badgeInfo.class} me-3 task-badge">${badgeInfo.text}</div>
                        <i class="mdi mdi-flag ms-2 flag-color"></i>
                        <button class="btn btn-sm btn-link text-primary ms-auto task-edit-btn" 
                                data-task-id="${taskId}"
                                data-task-name="${escapeHtml(taskName)}"
                                data-task-description="${escapeHtml(taskDescription)}"
                                data-task-date="${taskDate}"
                                data-task-status="${taskStatus}"
                                data-task-project="${taskProject}"
                                title="Editar tarea">
                            <i class="mdi mdi-pencil"></i>
                        </button>
                    </div>
                    <div class="ps-4 text-muted small mt-1">${taskDescription}</div>
                </div>
            </li>
        `;
        
        // Add to list
        tasksList.insertAdjacentHTML('beforeend', newTaskHTML);
        
        // Attach event listeners to the new task
        const newTaskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (newTaskLi) {
            const checkbox = newTaskLi.querySelector('.task-checkbox');
            const editBtn = newTaskLi.querySelector('.task-edit-btn');
            
            if (checkbox) {
                checkbox.addEventListener('change', handleTaskStatusChange);
            }
            if (editBtn) {
                editBtn.addEventListener('click', handleEditTask);
            }
        }
    }
    
    /**
     * Update an existing task in the list
     * @param {string|number} taskId - Task ID
     * @param {string} taskName - Task name
     * @param {string} taskDescription - Task description
     * @param {string} taskDate - Task due date
     * @param {string} taskStatus - Task status
     */
    function updateTaskInList(taskId, taskName, taskDescription, taskDate, taskStatus) {
        const taskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (!taskLi) return;
        
        // Format date
        let formattedDate = 'Sin fecha';
        if (taskDate) {
            const dateObj = new Date(taskDate);
            formattedDate = dateObj.toLocaleDateString('es-MX', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
        
        // Get badge info
        const badgeInfo = getTaskBadgeInfo(taskStatus);
        
        // Update task name
        const label = taskLi.querySelector('.form-check-label');
        const checkbox = taskLi.querySelector('.task-checkbox');
        if (label && checkbox) {
            // Preserve the checkbox and update the label text
            const isChecked = taskStatus === 'completado';
            checkbox.checked = isChecked;
            
            // Update the label content (keeping checkbox intact)
            label.childNodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    node.textContent = taskName;
                }
            });
        }
        
        // Update date
        const dateDiv = taskLi.querySelector('.text-small');
        if (dateDiv) {
            dateDiv.textContent = formattedDate;
        }
        
        // Update badge
        const badge = taskLi.querySelector('.task-badge');
        if (badge) {
            badge.className = `badge ${badgeInfo.class} me-3 task-badge`;
            badge.textContent = badgeInfo.text;
        }
        
        // Update description
        const descDiv = taskLi.querySelector('.text-muted.small');
        if (descDiv) {
            descDiv.textContent = taskDescription;
        }
        
        // Update edit button data attributes
        const editBtn = taskLi.querySelector('.task-edit-btn');
        if (editBtn) {
            editBtn.setAttribute('data-task-name', escapeHtml(taskName));
            editBtn.setAttribute('data-task-description', escapeHtml(taskDescription));
            editBtn.setAttribute('data-task-date', taskDate);
            editBtn.setAttribute('data-task-status', taskStatus);
        }
        
        showNotification('Tarea actualizada en la lista', 'success');
    }
    
    /**
     * Remove a task from the list
     * @param {string|number} taskId - Task ID to remove
     */
    function removeTaskFromList(taskId) {
        const taskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (taskLi) {
            taskLi.remove();
            
            // Check if list is now empty
            if (tasksList.children.length === 0) {
                showNoTasksMessage();
            }
        }
    }
    
    /**
     * Set loading state for modal save button
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setModalLoading(isLoading) {
        const saveBtn = document.getElementById('saveTaskBtn');
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
    
    // ============================================
    // ADD TASK BUTTON
    // ============================================
    
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if a project is selected
            if (!currentProjectId) {
                showAlert(
                    'Por favor seleccione un proyecto primero',
                    'Proyecto requerido',
                    'warning'
                );
                return;
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
            modal.show();
        });
    }
    
    // ============================================
    // UI HELPER FUNCTIONS
    // ============================================
    
    /**
     * Show default message when no project is selected
     */
    function showDefaultMessage() {
        tasksList.innerHTML = `
            <li class="d-block text-center py-4">
                <p class="text-muted">Seleccione un proyecto para ver sus tareas</p>
            </li>
        `;
    }
    
    /**
     * Show message when project has no tasks
     */
    function showNoTasksMessage() {
        tasksList.innerHTML = `
            <li class="d-block text-center py-4">
                <p class="text-muted">No hay tareas para este proyecto</p>
            </li>
        `;
    }
    
    /**
     * Show error message when loading tasks fails
     */
    function showErrorMessage() {
        tasksList.innerHTML = `
            <li class="d-block text-center py-4">
                <p class="text-danger">Error al cargar las tareas</p>
            </li>
        `;
    }
    
    /**
     * Show notification message (for task operations)
     * @param {string} message - Message to display
     * @param {string} type - Bootstrap alert type (success, danger, warning, info)
     */
    function showNotification(message, type) {
        // You can implement a toast notification system here
        // For now, just log to console
        console.log(`[${type.toUpperCase()}] ${message}`);
        
        // Optional: Create a simple toast notification
        // This is a basic implementation - you can enhance it
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    /**
     * Show message in the modal
     * @param {string} message - Message to display
     * @param {string} type - Bootstrap alert type
     */
    function showModalMessage(message, type) {
        const messageDiv = document.getElementById('taskMessage');
        messageDiv.className = `alert alert-${type}`;
        messageDiv.textContent = message;
        messageDiv.style.display = 'block';
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 3000);
    }
});