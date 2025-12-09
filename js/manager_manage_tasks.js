/*manager_manage_tasks.js para el manejo de tareas para gerentes*/

document.addEventListener('DOMContentLoaded', function() {
    
    const projectSelect = document.getElementById('id_proyecto');
    const tasksList = document.getElementById('tasksList');
    const tasksLoading = document.getElementById('tasksLoading');
    const addBtn = document.querySelector('.todo-list-add-btn');
    const projectPermissionNote = document.getElementById('projectPermissionNote');
    
    let currentProjectId = null; // seguir el proyecto seleccionado actualmente
    let currentUserId = window.currentUserId || 1; // obtener desde variable global definida en PHP
    let currentDepartmentId = window.currentDepartmentId || null; // departamento del usuario
    let currentProjectData = null; // almacenar datos del proyecto actual
    
    createCustomDialogSystem();
    createTaskModal();
    loadManagerProjects();
    
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
                                    <label for="taskProject" class="form-label">Proyecto <span class="text-danger">*</span></label>
                                    <select class="form-control" id="taskProject" required>
                                        <option value="">Seleccione un proyecto</option>
                                    </select>
                                    <small class="form-text text-muted">Solo proyectos de su departamento</small>
                                </div>
                                <div class="mb-3">
                                    <label for="taskDate" class="form-label">Fecha de Vencimiento</label>
                                    <input type="date" class="form-control" id="taskDate">
                                    <small class="form-text text-muted" id="taskDateNote" style="display: none;"></small>
                                </div>
                                <div class="mb-3">
                                    <label for="taskStatus" class="form-label">Estado</label>
                                    <select class="form-control" id="taskStatus" required>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="en proceso">En Progreso</option>
                                        <option value="completado">Completado</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="taskAssignee" class="form-label">
                                        <i class="mdi mdi-account-check"></i> Asignar a
                                    </label>
                                    <select class="form-control" id="taskAssignee" disabled required>
                                        <option value="">Seleccione un proyecto primero</option>
                                    </select>
                                    <small class="form-text text-muted" id="taskAssigneeNote" style="display: none; margin-top: 5px;"></small>
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
        
        initializeModalEventListeners();
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
    
    // Mostrar dialogo de alerta de la app
    function showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alertDiv = document.createElement('div');
        
        alertDiv.classList.add('alert', `alert-${type}`, 'alert-dismissible', 'fade', 'show');
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.innerHTML = '';
        alertContainer.appendChild(alertDiv);
        
        setTimeout(function() {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Mostrar dialogo de confirmacion
    function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
        const modal = document.getElementById('customConfirmModal');
        const titleElement = document.getElementById('confirmTitle');
        const messageElement = document.getElementById('confirmMessage');
        const headerElement = modal.querySelector('.modal-header');
        const iconElement = modal.querySelector('.modal-title i');
        const confirmBtn = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');
        
        const config = {
            confirmText: 'Aceptar',
            cancelText: 'Cancelar',
            type: 'warning',
            ...options
        };
        
        titleElement.textContent = title;
        messageElement.textContent = message;
        confirmBtn.textContent = config.confirmText;
        cancelBtn.textContent = config.cancelText;
        
        headerElement.className = 'modal-header';
        
        const iconMap = {
            'info': { icon: 'mdi-information-outline', class: 'bg-info text-white', btnClass: 'btn-info' },
            'warning': { icon: 'mdi-alert-outline', class: 'bg-warning text-white', btnClass: 'btn-warning' },
            'danger': { icon: 'mdi-alert-octagon-outline', class: 'bg-danger text-white', btnClass: 'btn-danger' },
            'success': { icon: 'mdi-check-circle-outline', class: 'bg-success text-white', btnClass: 'btn-success' }
        };
        
        const typeConfig = iconMap[config.type] || iconMap['warning'];
        iconElement.className = `mdi ${typeConfig.icon} me-2`;
        headerElement.classList.add(typeConfig.class);
        confirmBtn.className = `btn ${typeConfig.btnClass}`;
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.addEventListener('click', function() {
            const confirmModal = bootstrap.Modal.getInstance(modal);
            confirmModal.hide();
            if (onConfirm && typeof onConfirm === 'function') {
                onConfirm();
            }
        });
        
        const confirmModal = new bootstrap.Modal(modal);
        confirmModal.show();
    }

    function loadManagerProjects() {
        // Usar el endpoint específico para gerentes que filtra por departamento
        fetch('../php/manager_api_get_projects.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('La respuesta de red no fue ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    // Guardar el department_id para uso posterior
                    if (data.department_id) {
                        currentDepartmentId = data.department_id;
                    }
                    if (data.user_id) {
                        currentUserId = data.user_id;
                    }
                    
                    populateProjectSelect(projectSelect, data.data);
                } else {
                    showNotification(data.message || 'Error al cargar proyectos del departamento', 'warning');
                }
            })
            .catch(error => {
                console.error('Error al cargar los proyectos:', error);
                showNotification('Error al cargar proyectos del departamento', 'danger');
            });
    }
    
    // Cargar usuarios del proyecto específico
    function loadProjectUsers(projectId) {
        fetch(`../php/manager_get_project_users.php?id_proyecto=${projectId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error cargando usuarios del proyecto');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.usuarios && data.usuarios.length > 0) {
                    populateUserSelect(document.getElementById('taskAssignee'), data.usuarios);
                } else {
                    populateUserSelect(document.getElementById('taskAssignee'), []);
                    showNotification('No hay usuarios asignados a este proyecto', 'warning');
                }
            })
            .catch(error => {
                console.error('Error al cargar usuarios del proyecto:', error);
                populateUserSelect(document.getElementById('taskAssignee'), []);
                showNotification('Error al cargar usuarios del proyecto', 'danger');
            });
    }
    
    // Popular el dropdown de usuarios
    function populateUserSelect(selectElement, users) {
        if (!selectElement) return;
        
        selectElement.innerHTML = '';
        
        if (users.length === 0) {
            selectElement.innerHTML = '<option value="">No hay usuarios en este proyecto</option>';
            selectElement.disabled = true;
            selectElement.classList.add('is-invalid');
        } else {
            selectElement.innerHTML = '<option value="">Sin asignar</option>';
            selectElement.disabled = false;
            selectElement.classList.remove('is-invalid');
            
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id_usuario;
                option.textContent = `${user.nombre} ${user.apellido} (#${user.num_empleado})`;
                option.dataset.userId = user.id_usuario;
                selectElement.appendChild(option);
            });
        }
    }
    
    function populateProjectSelect(selectElement, projects) {
        selectElement.innerHTML = '<option value="">Seleccione un proyecto</option>';
        
        projects.forEach(project => {
            const option = document.createElement('option');
            // manager_api_get_projects usa 'id_proyecto', no 'id'
            option.value = project.id_proyecto;
            option.textContent = project.nombre;
            // Agregar indicador de estado
            if (project.estado === 'completado') {
                option.textContent += ' ✓';
            } else if (project.estado === 'vencido') {
                option.textContent += ' ⚠';
            }
            selectElement.appendChild(option);
        });
    }
    
    // Evento de cambio de proyecto
    projectSelect.addEventListener('change', function() {
        if (this.value) {
            currentProjectId = this.value;
            
            fetchProjectDetails(this.value, function(projectData) {
                currentProjectData = projectData;
                updateTaskAssignmentPermissions(projectData);
                loadTasks(this.value);
            }.bind(this));
        } else {
            currentProjectId = null;
            currentProjectData = null;
            showDefaultMessage();
            projectPermissionNote.style.display = 'none';
        }
    });
    
    // Obtener detalles del proyecto
    function fetchProjectDetails(projectId, callback) {
        fetch(`../php/get_project_by_id.php?id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.proyecto) {
                    callback(data.proyecto);
                } else {
                    console.error('Error al cargar detalles del proyecto');
                }
            })
            .catch(error => {
                console.error('Error fetching project details:', error);
            });
    }
    
    // Establecer fecha mínima del proyecto
    function setTaskDateMinimum(projectData) {
        const taskDateInput = document.getElementById('taskDate');
        const taskDateNoteDiv = document.getElementById('taskDateNote');
        
        if (!taskDateInput) return;
        
        if (projectData && projectData.fecha_inicio) {
            taskDateInput.min = projectData.fecha_inicio;
            
            if (taskDateNoteDiv) {
                const fecha = parseDateStringToLocal(projectData.fecha_inicio);
                const fechaFormato = fecha.toLocaleDateString('es-MX', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                });
                
                taskDateNoteDiv.textContent = `La fecha mínima es ${fechaFormato} (inicio del proyecto)`;
                taskDateNoteDiv.style.display = 'block';
            }
        } else {
            taskDateInput.min = '';
            if (taskDateNoteDiv) {
                taskDateNoteDiv.style.display = 'none';
            }
        }
    }
    
    // Actualizar permisos de asignación de tareas
    function updateTaskAssignmentPermissions(projectData) {
        const canAssignTasks = canAssignTasksToProject(projectData);
        
        if (!canAssignTasks) {
            projectPermissionNote.innerHTML = `
                <i class="mdi mdi-lock text-warning"></i>
                <strong>Nota:</strong> Solo el creador del proyecto puede asignar tareas
            `;
            projectPermissionNote.style.display = 'block';
        } else {
            projectPermissionNote.style.display = 'none';
        }
    }
    
    // Verificar si el usuario puede asignar tareas
    function canAssignTasksToProject(projectData) {
        if (projectData.puede_editar_otros == 1) {
            return true;
        }
        
        if (projectData.puede_editar_otros == 0) {
            return projectData.id_creador == currentUserId;
        }
        
        return false;
    }
    
    // Actualizar estado de asignación en el modal
    function updateModalTaskAssignmentPermissions() {
        if (!currentProjectData) return;
        
        const assigneeField = document.getElementById('taskAssignee');
        const assigneeNote = document.getElementById('taskAssigneeNote');
        const canAssign = canAssignTasksToProject(currentProjectData);
        
        if (!canAssign) {
            assigneeField.disabled = true;
            assigneeField.value = '';
            assigneeNote.innerHTML = `
                <i class="mdi mdi-lock"></i>
                Solo el creador del proyecto puede asignar tareas
            `;
            assigneeNote.style.display = 'block';
        } else {
            assigneeField.disabled = false;
            assigneeNote.style.display = 'none';
        }
    }

    // Cargar tareas de un proyecto
    function loadTasks(projectId) {
        tasksLoading.style.display = 'block';
        tasksList.style.display = 'none';
        
        fetch(`../php/manager_get_tasks_by_project.php?id_proyecto=${projectId}`)
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
    
    // Renderizar tareas en la lista
    function renderTasks(tasks) {
        tasksList.innerHTML = '';
        
        tasks.forEach((task, index) => {
            const isLast = index === tasks.length - 1;
            const taskElement = createTaskElement(task, isLast);
            tasksList.insertAdjacentHTML('beforeend', taskElement);
        });
        
        attachTaskListeners();
    }
    
    // Parsear fecha correctamente
    function parseDateStringToLocal(dateString) {
        if (!dateString) return null;
        
        const parts = dateString.split('-');
        if (parts.length !== 3) return null;
        
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        
        return new Date(year, month, day);
    }

    // Formatear fecha para mostrar
    function formatDateForDisplay(dateString) {
        const dateObj = parseDateStringToLocal(dateString);
        if (!dateObj) return 'Sin fecha';
        
        return dateObj.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }
    
    // Crear elemento HTML de tarea
    function createTaskElement(task, isLast = false) {
        const formattedDate = formatDateForDisplay(task.fecha_cumplimiento);
        const badgeInfo = getTaskBadgeInfo(task.estado);
        const isCompleted = task.estado === 'completado';
        const borderClass = isLast ? 'border-bottom-0' : '';
        const checkboxIcon = isCompleted ? 'mdi-checkbox-marked-circle-outline' : 'mdi-checkbox-blank-circle-outline';
        const checkboxColor = isCompleted ? 'text-success' : 'text-muted';
        const assigneeDisplay = task.participante ? ` <small class="text-muted">(Asignado a: ${escapeHtml(task.participante)})</small>` : '';
        
        return `
            <li class="d-block ${borderClass}" data-task-id="${task.id_tarea}">
                <div class="d-flex align-items-start w-100 gap-2">
                    <i class="mdi mdi-24px ${checkboxIcon} ${checkboxColor} task-checkbox-icon flex-shrink-0" 
                       data-task-id="${task.id_tarea}" 
                       style="cursor: pointer; margin-top: 2px; transition: color 0.2s ease;"
                       title="Click para marcar como completado"></i>
                    <div class="flex-grow-1">
                        <div>
                            <label style="cursor: pointer; ${isCompleted ? 'text-decoration: line-through; color: #6c757d;' : ''}">
                                ${escapeHtml(task.nombre)}${assigneeDisplay}
                            </label>
                        </div>
                        <div class="d-flex mt-2 align-items-center">
                            <div class="text-small me-3">${formattedDate}</div>
                            <div class="badge ${badgeInfo.class} me-3 task-badge">${badgeInfo.text}</div>
                            <i class="mdi mdi-flag ms-2 flag-color"></i>
                            <button class="btn btn-sm btn-link text-primary ms-auto task-edit-btn" 
                                    data-task-id="${task.id_tarea}"
                                    data-task-name="${escapeHtml(task.nombre)}"
                                    data-task-description="${escapeHtml(task.descripcion)}"
                                    data-task-date="${task.fecha_cumplimiento}"
                                    data-task-status="${task.estado}"
                                    data-task-project="${task.id_proyecto}"
                                    data-task-assignee="${task.id_participante || ''}"
                                    title="Editar tarea">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                        </div>
                        <div class="text-muted small mt-1">${escapeHtml(task.descripcion)}</div>
                    </div>
                </div>
            </li>
        `;
    }

    // Obtener info de badge según estado
    function getTaskBadgeInfo(status) {
        const statusMap = {
            'completado': { class: 'badge-opacity-success', text: 'Completado' },
            'en proceso': { class: 'badge-opacity-info', text: 'En Progreso' },
            'en-progreso': { class: 'badge-opacity-info', text: 'En Progreso' },
            'vencido': { class: 'badge-opacity-danger', text: 'Vencido' },
            'pendiente': { class: 'badge-opacity-warning', text: 'Pendiente' }
        };
        
        return statusMap[status] || statusMap['pendiente'];
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Agregar event listeners a checkboxes y botones
    function attachTaskListeners() {
        const checkboxIcons = document.querySelectorAll('.task-checkbox-icon');
        checkboxIcons.forEach(icon => {
            icon.addEventListener('click', handleTaskStatusChange);
        });
        
        const editButtons = document.querySelectorAll('.task-edit-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', handleEditTask);
        });
    }
    
    // Manejar cambio de estado de tarea
    function handleTaskStatusChange(event) {
        const icon = event.target;
        const taskId = icon.getAttribute('data-task-id');
        const isCurrentlyChecked = icon.classList.contains('mdi-checkbox-marked-circle-outline');
        const newStatus = isCurrentlyChecked ? 'pendiente' : 'completado';
        const taskLi = icon.closest('li');
        
        taskLi.style.opacity = '0.6';
        taskLi.style.pointerEvents = 'none';
        icon.style.pointerEvents = 'none';
        
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
                if (newStatus === 'completado') {
                    icon.classList.remove('mdi-checkbox-blank-circle-outline');
                    icon.classList.add('mdi-checkbox-marked-circle-outline');
                    icon.classList.remove('text-muted');
                    icon.classList.add('text-success');
                } else {
                    icon.classList.remove('mdi-checkbox-marked-circle-outline');
                    icon.classList.add('mdi-checkbox-blank-circle-outline');
                    icon.classList.remove('text-success');
                    icon.classList.add('text-muted');
                }
                
                const badge = taskLi.querySelector('.task-badge');
                const badgeInfo = getTaskBadgeInfo(newStatus);
                
                if (badge) {
                    badge.className = `badge ${badgeInfo.class} me-3 task-badge`;
                    badge.textContent = badgeInfo.text;
                }
                
                const label = taskLi.querySelector('label');
                if (label) {
                    if (newStatus === 'completado') {
                        label.style.textDecoration = 'line-through';
                        label.style.color = '#6c757d';
                    } else {
                        label.style.textDecoration = 'none';
                        label.style.color = 'inherit';
                    }
                }
                
                taskLi.style.opacity = '1';
                taskLi.style.pointerEvents = 'auto';
                icon.style.pointerEvents = 'auto';
                
                const statusMessage = newStatus === 'completado' ? 'Tarea marcada como completada' : 'Tarea marcada como pendiente';
                showNotification(statusMessage, 'success');
            } else {
                taskLi.style.opacity = '1';
                taskLi.style.pointerEvents = 'auto';
                icon.style.pointerEvents = 'auto';
                
                showNotification(data.message || 'Error al actualizar la tarea', 'danger');
            }
        })
        .catch(error => {
            console.error('Error updating task:', error);
            
            taskLi.style.opacity = '1';
            taskLi.style.pointerEvents = 'auto';
            icon.style.pointerEvents = 'auto';
            
            showNotification('Error al conectar con el servidor', 'danger');
        });
    }

    // Manejar edición de tarea
    function handleEditTask(event) {
        event.preventDefault();
        const button = event.currentTarget;
        
        const taskId = button.getAttribute('data-task-id');
        const taskName = button.getAttribute('data-task-name');
        const taskDescription = button.getAttribute('data-task-description');
        const taskDate = button.getAttribute('data-task-date');
        const taskStatus = button.getAttribute('data-task-status');
        const taskProject = button.getAttribute('data-task-project');
        const taskAssignee = button.getAttribute('data-task-assignee');
        
        document.getElementById('taskName').value = taskName;
        document.getElementById('taskDescription').value = taskDescription;
        document.getElementById('taskDate').value = taskDate;
        document.getElementById('taskStatus').value = taskStatus;
        
        const form = document.getElementById('addTaskForm');
        form.setAttribute('data-task-id', taskId);
        form.setAttribute('data-mode', 'edit');
        
        document.getElementById('addTaskModalLabel').textContent = 'Editar Tarea';
        document.querySelector('#saveTaskBtn .btn-text').textContent = 'Actualizar Tarea';
        
        loadProjectsForModal(() => {
            document.getElementById('taskProject').value = taskProject;
            
            loadProjectUsersForModal(taskProject, () => {
                if (taskAssignee) {
                    document.getElementById('taskAssignee').value = taskAssignee;
                }
            });
            
            if (currentProjectData && currentProjectData.id_proyecto == taskProject) {
                setTaskDateMinimum(currentProjectData);
            } else {
                fetchProjectDetails(taskProject, function(projectData) {
                    setTaskDateMinimum(projectData);
                });
            }
        });
        
        const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
        modal.show();
    }
    
    // Inicializar event listeners del modal
    function initializeModalEventListeners() {
        const modal = document.getElementById('addTaskModal');
        const saveBtn = document.getElementById('saveTaskBtn');
        const form = document.getElementById('addTaskForm');
        const projectSelect = document.getElementById('taskProject');
        const assigneeSelect = document.getElementById('taskAssignee');
        
        projectSelect.addEventListener('change', function() {
            if (this.value) {
                loadProjectUsersForModal(this.value, () => {
                    fetchProjectDetails(this.value, function(projectData) {
                        setTaskDateMinimum(projectData);
                        
                        const canAssign = canAssignTasksToProject(projectData);
                        const assigneeNote = document.getElementById('taskAssigneeNote');
                        
                        if (!canAssign) {
                            assigneeSelect.disabled = true;
                            assigneeNote.innerHTML = `
                                <i class="mdi mdi-lock text-danger"></i>
                                Solo el creador del proyecto puede asignar tareas
                            `;
                            assigneeNote.style.display = 'block';
                        } else {
                            assigneeSelect.disabled = false;
                            assigneeNote.style.display = 'none';
                        }
                    });
                });
            } else {
                assigneeSelect.innerHTML = '<option value="">Seleccione un proyecto primero</option>';
                assigneeSelect.disabled = true;
                document.getElementById('taskAssigneeNote').style.display = 'none';
                document.getElementById('taskDateNote').style.display = 'none';
            }
        });
        
        modal.addEventListener('show.bs.modal', function() {
            const mode = form.getAttribute('data-mode');
            
            if (mode !== 'edit') {
                loadProjectsForModal(() => {
                    if (currentProjectId) {
                        document.getElementById('taskProject').value = currentProjectId;
                        loadProjectUsersForModal(currentProjectId);
                        setTaskDateMinimum(currentProjectData);
                        updateModalTaskAssignmentPermissions();
                    }
                });
            }
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            form.reset();
            form.removeAttribute('data-task-id');
            form.removeAttribute('data-mode');
            document.getElementById('taskMessage').style.display = 'none';
            document.getElementById('taskDateNote').style.display = 'none';
            
            document.getElementById('addTaskModalLabel').textContent = 'Agregar Nueva Tarea';
            document.querySelector('#saveTaskBtn .btn-text').textContent = 'Guardar Tarea';
            
            assigneeSelect.innerHTML = '<option value="">Seleccione un proyecto primero</option>';
            assigneeSelect.disabled = true;
        });
        
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSaveTask);
        }
    }
    
    // Cargar proyectos para el modal (usando endpoint de gerente)
    function loadProjectsForModal(callback) {
        fetch('../php/manager_api_get_projects.php')
            .then(response => response.json())
            .then(data => {
                const modalProjectSelect = document.getElementById('taskProject');
                
                if (data.success && data.data) {
                    populateProjectSelect(modalProjectSelect, data.data);
                    
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
    
    // Cargar usuarios del proyecto en el modal
    function loadProjectUsersForModal(projectId, callback) {
        fetch(`../php/manager_get_project_users.php?id_proyecto=${projectId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const assigneeSelect = document.getElementById('taskAssignee');
                
                if (data.success && data.usuarios) {
                    populateUserSelect(assigneeSelect, data.usuarios);
                } else {
                    console.warn(`No hay usuarios en este proyecto`);
                    populateUserSelect(assigneeSelect, []);
                }
                
                if (callback && typeof callback === 'function') {
                    callback();
                }
            })
            .catch(error => {
                console.error('Error loading project users:', error);
                populateUserSelect(document.getElementById('taskAssignee'), []);
                showModalMessage('Error al cargar usuarios del proyecto', 'danger');
            });
    }
    
    // Manejar guardado de tarea
    function handleSaveTask() {
        const form = document.getElementById('addTaskForm');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const taskName = document.getElementById('taskName').value;
        const taskDescription = document.getElementById('taskDescription').value;
        const taskProject = document.getElementById('taskProject').value;
        const taskDate = document.getElementById('taskDate').value;
        const taskStatus = document.getElementById('taskStatus').value;
        const taskAssignee = document.getElementById('taskAssignee').value;
        const taskDateInput = document.getElementById('taskDate');
        
        if (taskDate && taskDateInput.min) {
            if (taskDate < taskDateInput.min) {
                showModalMessage(
                    'La fecha de vencimiento no puede ser anterior a la fecha de inicio del proyecto',
                    'danger'
                );
                return;
            }
        }
        
        const mode = form.getAttribute('data-mode');
        const taskId = form.getAttribute('data-task-id');
        const isEditMode = mode === 'edit' && taskId;
        
        setModalLoading(true);
        
        const formData = new FormData();
        formData.append('nombre', taskName);
        formData.append('descripcion', taskDescription);
        formData.append('id_proyecto', taskProject);
        formData.append('fecha_vencimiento', taskDate);
        formData.append('estado', taskStatus);
        formData.append('id_participante', taskAssignee || '');
        formData.append('id_creador', currentUserId);
        
        if (isEditMode) {
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
                    
                    if (currentProjectId && taskProject == currentProjectId) {
                        updateTaskInList(taskId, taskName, taskDescription, taskDate, taskStatus, taskAssignee);
                    } else if (currentProjectId) {
                        removeTaskFromList(taskId);
                    }
                    
                    if (currentProjectId && taskProject != currentProjectId) {
                        loadTasks(currentProjectId);
                    }
                    
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
            fetch('../php/save_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setModalLoading(false);
                
                if (data.success) {
                    showModalMessage('Tarea guardada exitosamente', 'success');
                    
                    if (currentProjectId && taskProject == currentProjectId) {
                        addTaskToList(data.task_id, taskName, taskDescription, taskDate, taskStatus, taskProject, taskAssignee);
                    }
                    
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
    
    // Agregar tarea a la lista
    function addTaskToList(taskId, taskName, taskDescription, taskDate, taskStatus, taskProject, taskAssignee) {
        const noTasksMessage = tasksList.querySelector('.text-center');
        if (noTasksMessage) {
            tasksList.innerHTML = '';
        }
        
        const formattedDate = formatDateForDisplay(taskDate);
        const badgeInfo = getTaskBadgeInfo(taskStatus);
        const isCompleted = taskStatus === 'completado';
        const checkboxIcon = isCompleted ? 'mdi-checkbox-marked-circle-outline' : 'mdi-checkbox-blank-circle-outline';
        const checkboxColor = isCompleted ? 'text-success' : 'text-muted';
        
        const newTaskHTML = `
            <li class="d-block" data-task-id="${taskId}">
                <div class="d-flex align-items-start w-100 gap-2">
                    <i class="mdi mdi-24px ${checkboxIcon} ${checkboxColor} task-checkbox-icon flex-shrink-0" 
                       data-task-id="${taskId}" 
                       style="cursor: pointer; margin-top: 2px; transition: color 0.2s ease;"
                       title="Click para marcar como completado"></i>
                    <div class="flex-grow-1">
                        <div>
                            <label style="cursor: pointer; ${isCompleted ? 'text-decoration: line-through; color: #6c757d;' : ''}">
                                ${escapeHtml(taskName)}
                            </label>
                        </div>
                        <div class="d-flex mt-2 align-items-center">
                            <div class="text-small me-3">${formattedDate}</div>
                            <div class="badge ${badgeInfo.class} me-3 task-badge">${badgeInfo.text}</div>
                            <i class="mdi mdi-flag ms-2 flag-color"></i>
                            <button class="btn btn-sm btn-link text-primary ms-auto task-edit-btn" 
                                    data-task-id="${taskId}"
                                    data-task-name="${escapeHtml(taskName)}"
                                    data-task-description="${escapeHtml(taskDescription)}"
                                    data-task-date="${taskDate}"
                                    data-task-status="${taskStatus}"
                                    data-task-project="${taskProject}"
                                    data-task-assignee="${taskAssignee || ''}"
                                    title="Editar tarea">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                        </div>
                        <div class="text-muted small mt-1">${escapeHtml(taskDescription)}</div>
                    </div>
                </div>
            </li>
        `;
        
        tasksList.insertAdjacentHTML('beforeend', newTaskHTML);
        
        const newTaskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (newTaskLi) {
            const checkboxIcon = newTaskLi.querySelector('.task-checkbox-icon');
            const editBtn = newTaskLi.querySelector('.task-edit-btn');
            
            if (checkboxIcon) {
                checkboxIcon.addEventListener('click', handleTaskStatusChange);
            }
            if (editBtn) {
                editBtn.addEventListener('click', handleEditTask);
            }
        }
    }
    
    // Actualizar tarea en la lista
    function updateTaskInList(taskId, taskName, taskDescription, taskDate, taskStatus, taskAssignee) {
        const taskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (!taskLi) return;
        
        const formattedDate = formatDateForDisplay(taskDate);
        const badgeInfo = getTaskBadgeInfo(taskStatus);
        
        const icon = taskLi.querySelector('.task-checkbox-icon');
        if (icon) {
            const isCompleted = taskStatus === 'completado';
            if (isCompleted) {
                icon.classList.remove('mdi-checkbox-blank-circle-outline');
                icon.classList.add('mdi-checkbox-marked-circle-outline');
                icon.classList.remove('text-muted');
                icon.classList.add('text-success');
            } else {
                icon.classList.remove('mdi-checkbox-marked-circle-outline');
                icon.classList.add('mdi-checkbox-blank-circle-outline');
                icon.classList.remove('text-success');
                icon.classList.add('text-muted');
            }
        }
        
        const label = taskLi.querySelector('label');
        if (label) {
            const isCompleted = taskStatus === 'completado';
            label.textContent = taskName;
            if (isCompleted) {
                label.style.textDecoration = 'line-through';
                label.style.color = '#6c757d';
            } else {
                label.style.textDecoration = 'none';
                label.style.color = 'inherit';
            }
        }
        
        const dateDiv = taskLi.querySelector('.text-small');
        if (dateDiv) {
            dateDiv.textContent = formattedDate;
        }
        
        const badge = taskLi.querySelector('.task-badge');
        if (badge) {
            badge.className = `badge ${badgeInfo.class} me-3 task-badge`;
            badge.textContent = badgeInfo.text;
        }
        
        const descDiv = taskLi.querySelector('.text-muted.small');
        if (descDiv) {
            descDiv.textContent = taskDescription;
        }
        
        const editBtn = taskLi.querySelector('.task-edit-btn');
        if (editBtn) {
            editBtn.setAttribute('data-task-name', escapeHtml(taskName));
            editBtn.setAttribute('data-task-description', escapeHtml(taskDescription));
            editBtn.setAttribute('data-task-date', taskDate);
            editBtn.setAttribute('data-task-status', taskStatus);
            editBtn.setAttribute('data-task-assignee', taskAssignee || '');
        }
        
        showNotification('Tarea actualizada en la lista', 'success');
    }
    
    // Quitar tarea de la lista
    function removeTaskFromList(taskId) {
        const taskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (taskLi) {
            taskLi.remove();
            
            if (tasksList.children.length === 0) {
                showNoTasksMessage();
            }
        }
    }
    
    // Establecer estado de carga del modal
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
    
    // Botón agregar tarea
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!currentProjectId) {
                showAlert('Por favor seleccione un proyecto primero', 'warning');
                return;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
            modal.show();
        });
    }
    
    // Mensajes de estado
    function showDefaultMessage() {
        tasksList.innerHTML = `
            <li class="d-block text-center py-4">
                <p class="text-muted">Seleccione un proyecto para ver sus tareas</p>
            </li>
        `;
    }
    
    function showNoTasksMessage() {
        tasksList.innerHTML = `
            <li class="d-block text-center py-4">
                <p class="text-muted">No hay tareas para este proyecto</p>
            </li>
        `;
    }
     
    function showErrorMessage() {
        tasksList.innerHTML = `
            <li class="d-block text-center py-4">
                <p class="text-danger">Error al cargar las tareas</p>
            </li>
        `;
    }
    
    function showNotification(message, type) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
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