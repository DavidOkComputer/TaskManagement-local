/*task-management.js - manejo de tareas, creacion, actualizacion y asignacion de usuarios*/

document.addEventListener('DOMContentLoaded', function() {
    
    const projectSelect = document.getElementById('id_proyecto');
    const tasksList = document.getElementById('tasksList');
    const tasksLoading = document.getElementById('tasksLoading');
    const addBtn = document.querySelector('.todo-list-add-btn');
    
    let currentProjectId = null; //seguir el proyecto seleccionado actualmente
    let currentUserId = 1; //id del usuario actual - remplazar con sesion real
    
    createCustomDialogSystem();
    createTaskModal();
    loadUsers(); //cargar usuarios para dropdown
    
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
                                <div class="mb-3">
                                    <label for="taskAssignee" class="form-label">Asignar a</label>
                                    <select class="form-control" id="taskAssignee">
                                        <option value="">Sin asignar</option>
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
    
    //mostrar dialogo de alrta de la app y no navegador
    function showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alertDiv = document.createElement('div');
        
        //agreagar clases de una en una
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

    // mostrar dialogo de confiracion de la app y no buscador
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
        messageElement.textContent = message;
        
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
        headerElement.classList.add(typeConfig.class);
        
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

    loadProjects();
    
    // cargar proyectos y mostrarlos en la lista dropdown
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
    
    // cargar usuarios para el dropdown
    function loadUsers() {
        fetch('../php/get_users.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error cargando usuarios');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.usuarios) {
                    populateUserSelect(document.getElementById('taskAssignee'), data.usuarios);
                }
            })
            .catch(error => {
                console.error('Error al cargar usuarios:', error);
            });
    }
    
    //popular el dropdown de usuarios con nombre completo y numero de empleado
    function populateUserSelect(selectElement, users) {
        //mantener la opcion default
        selectElement.innerHTML = '<option value="">Sin asignar</option>';
        
        users.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id_usuario;
            //mostrar: Nombre Apellido (#NumEmpleado)
            option.textContent = `${user.nombre} ${user.apellido} (#${user.num_empleado})`;
            selectElement.appendChild(option);
        });
    }
    
    //popular el elemento con proyectos
    function populateProjectSelect(selectElement, projects) {
        //mantener la opcion default
        selectElement.innerHTML = '<option value="">Seleccione un proyecto</option>';
        
        projects.forEach(project => {
            const option = document.createElement('option');
            option.value = project.id_proyecto;
            option.textContent = project.nombre;
            selectElement.appendChild(option);
        });
    }
    
    //cambio de proyecto
    projectSelect.addEventListener('change', function() {
        if (this.value) {
            currentProjectId = this.value;
            loadTasks(this.value);
        } else {
            currentProjectId = null;
            showDefaultMessage();
        }
    });

    //cargar tareas de un proyecto especifico
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
    
    //cargar tareas en la lista
    function renderTasks(tasks) {
        tasksList.innerHTML = '';
        
        tasks.forEach((task, index) => {
            const isLast = index === tasks.length - 1;
            const taskElement = createTaskElement(task, isLast);
            tasksList.insertAdjacentHTML('beforeend', taskElement);
        });
        
        //agregar event listeners a todas las checkboxes y botones de edicion
        attachTaskListeners();
    }
    
    //crear un html para un solo elemento de tarea
    function createTaskElement(task, isLast = false) {
        //formato de fecha
        const dateObj = new Date(task.fecha_cumplimiento);
        const formattedDate = dateObj.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        
        //estilo de la insignia basado en estatus
        const badgeInfo = getTaskBadgeInfo(task.estado);
        
        //revisar si la tarea es completada
        const isCompleted = task.estado === 'completado';
        const borderClass = isLast ? 'border-bottom-0' : '';
        
        //mostrar el participante asignado si existe
        const assigneeDisplay = task.participante ? ` <small class="text-muted">(Asignado a: ${escapeHtml(task.participante)})</small>` : '';
        
        return `
            <li class="d-block ${borderClass}" data-task-id="${task.id_tarea}">
                <div class="form-check w-100">
                    <label class="form-check-label">
                        <input class="checkbox task-checkbox" type="checkbox" 
                               ${isCompleted ? 'checked' : ''} 
                               data-task-id="${task.id_tarea}">
                        ${task.nombre}${assigneeDisplay}
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
                                data-task-assignee="${task.id_participante || ''}"
                                title="Editar tarea">
                            <i class="mdi mdi-pencil"></i>
                        </button>
                    </div>
                    <div class="ps-4 text-muted small mt-1">${task.descripcion}</div>
                </div>
            </li>
        `;
    }

    //obtener la clase y texto de la insigniaa basado en el estado de la tarea
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

    //agregar event listeners a todas las checkboxes y botones de edicion
    function attachTaskListeners() {
        const checkboxes = document.querySelectorAll('.task-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', handleTaskStatusChange);
        });
        
        const editButtons = document.querySelectorAll('.task-edit-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', handleEditTask);
        });
    }
    
    //manejo de cambio de estado cuando se hace clic en la checkbox 
    function handleTaskStatusChange(event) {
        const checkbox = event.target;
        const taskId = checkbox.getAttribute('data-task-id');
        const isChecked = checkbox.checked;
        
        //Checado = completado, sin checar = pendiente 
        const newStatus = isChecked ? 'completado' : 'pendiente';
        
        //obtener el item de la lista de tareas
        const taskLi = checkbox.closest('li');
        
        //mostrar estado de carga
        taskLi.style.opacity = '0.6';
        taskLi.style.pointerEvents = 'none';
        checkbox.disabled = true;
        
        //preparar info para la actualizacion
        const updateData = new FormData();
        updateData.append('id_tarea', taskId);
        updateData.append('estado', newStatus);
        
        //enviar actualizacion al servidor
        fetch('../php/update_task_status.php', {
            method: 'POST',
            body: updateData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                //actualizar insignia
                const badge = taskLi.querySelector('.task-badge');
                const badgeInfo = getTaskBadgeInfo(newStatus);
                
                if (badge) {
                    badge.className = `badge ${badgeInfo.class} me-3 task-badge`;
                    badge.textContent = badgeInfo.text;
                }
                
                //devolver a estado normal
                taskLi.style.opacity = '1';
                taskLi.style.pointerEvents = 'auto';
                checkbox.disabled = false;
                
                const statusMessage = isChecked ? 'Tarea marcada como completada' : 'Tarea marcada como pendiente';
                showNotification(statusMessage, 'success');
            } else {
                //regresar checkbox a estado original si hay error
                checkbox.checked = !isChecked;
                taskLi.style.opacity = '1';
                taskLi.style.pointerEvents = 'auto';
                checkbox.disabled = false;
                
                showNotification(data.message || 'Error al actualizar la tarea', 'danger');
            }
        })
        .catch(error => {
            console.error('Error updating task:', error);
            
            checkbox.checked = !isChecked;
            taskLi.style.opacity = '1';
            taskLi.style.pointerEvents = 'auto';
            checkbox.disabled = false;
            
            showNotification('Error al conectar con el servidor', 'danger');
        });
    }

    //manejo de boton de editar tarea
    function handleEditTask(event) {
        event.preventDefault();
        const button = event.currentTarget;
        
        //obtener atributos del boton
        const taskId = button.getAttribute('data-task-id');
        const taskName = button.getAttribute('data-task-name');
        const taskDescription = button.getAttribute('data-task-description');
        const taskDate = button.getAttribute('data-task-date');
        const taskStatus = button.getAttribute('data-task-status');
        const taskProject = button.getAttribute('data-task-project');
        const taskAssignee = button.getAttribute('data-task-assignee');
        
        //llenar el modal con la info de la tarea
        document.getElementById('taskName').value = taskName;
        document.getElementById('taskDescription').value = taskDescription;
        document.getElementById('taskDate').value = taskDate;
        document.getElementById('taskStatus').value = taskStatus;
        
        //guardar id de tarea para actualizar
        const form = document.getElementById('addTaskForm');
        form.setAttribute('data-task-id', taskId);
        form.setAttribute('data-mode', 'edit');
        
        //cambiar el titulo del modal
        document.getElementById('addTaskModalLabel').textContent = 'Editar Tarea';
        document.querySelector('#saveTaskBtn .btn-text').textContent = 'Actualizar Tarea';
        
        //cargar proyecto y preseleccionar el proyecto de la tarea
        loadProjectsForModal(() => {
            document.getElementById('taskProject').value = taskProject;
            
            //preseleccionar el usuario asignado
            if (taskAssignee) {
                document.getElementById('taskAssignee').value = taskAssignee;
            }
        });
        
        //mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
        modal.show();
    }
    
    function initializeModalEventListeners() {
        const modal = document.getElementById('addTaskModal');
        const saveBtn = document.getElementById('saveTaskBtn');
        const form = document.getElementById('addTaskForm');
        
        // cargar proyectos cuando se muestra el modal
        modal.addEventListener('show.bs.modal', function() {
            const mode = form.getAttribute('data-mode');
            
            //si no se esta en modo de edicion cargar proyectos y preselccionar proyecto actual
            if (mode !== 'edit') {
                loadProjectsForModal(() => {
                    if (currentProjectId) {
                        document.getElementById('taskProject').value = currentProjectId;
                    }
                    //preseleccionar el usuario actual por defecto
                    document.getElementById('taskAssignee').value = currentUserId;
                });
            }
        });
        
        //limpiar el form y reiniciar modal cuando se esconde
        modal.addEventListener('hidden.bs.modal', function() {
            form.reset();
            form.removeAttribute('data-task-id');
            form.removeAttribute('data-mode');
            document.getElementById('taskMessage').style.display = 'none';
            
            //reiniciar titulo del modal y texto del botn
            document.getElementById('addTaskModalLabel').textContent = 'Agregar Nueva Tarea';
            document.querySelector('#saveTaskBtn .btn-text').textContent = 'Guardar Tarea';
        });
        
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSaveTask);
        }
    }
    
    //cargar proyectos para el dropdown del modal
    function loadProjectsForModal(callback) {
        fetch('../php/get_projects.php')
            .then(response => response.json())
            .then(data => {
                const modalProjectSelect = document.getElementById('taskProject');
                
                if (data.success && data.proyectos) {
                    populateProjectSelect(modalProjectSelect, data.proyectos);
                    
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
    
    //maneja crear una nueva tarea y actualizar las que ya existen
    function handleSaveTask() {
        const form = document.getElementById('addTaskForm');
        
        //validar form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        //obtener valores del form
        const taskName = document.getElementById('taskName').value;
        const taskDescription = document.getElementById('taskDescription').value;
        const taskProject = document.getElementById('taskProject').value;
        const taskDate = document.getElementById('taskDate').value;
        const taskStatus = document.getElementById('taskStatus').value;
        const taskAssignee = document.getElementById('taskAssignee').value;
        
        //revisar si esta en modo de edicion
        const mode = form.getAttribute('data-mode');
        const taskId = form.getAttribute('data-task-id');
        const isEditMode = mode === 'edit' && taskId;
        
        setModalLoading(true);
        
        //preparar info
        const formData = new FormData();
        formData.append('nombre', taskName);
        formData.append('descripcion', taskDescription);
        formData.append('id_proyecto', taskProject);
        formData.append('fecha_vencimiento', taskDate);
        formData.append('estado', taskStatus);
        formData.append('id_participante', taskAssignee || null);
        
        if (isEditMode) {
            //modo de edicion, actualizar tarea existente
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
                    
                    //actualizar la tarea en la lista si pertenece al pryecto actual
                    if (currentProjectId && taskProject == currentProjectId) {
                        updateTaskInList(taskId, taskName, taskDescription, taskDate, taskStatus, taskAssignee);
                    } else if (currentProjectId) {
                        //si la tarea fue movida a un proyecto diferente se elimina de esta lista
                        removeTaskFromList(taskId);
                    }
                    
                    //si el proyecto actual esta vacia y se mueve la tarea a otro, recargar
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
            //modo crear, nueva tarea
            formData.append('id_creador', currentUserId); //usar el id del usuario actual
            
            fetch('../php/save_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setModalLoading(false);
                
                if (data.success) {
                    showModalMessage('Tarea guardada exitosamente', 'success');
                    
                    //si la nueva tarea pertenece al proyecto seleccionado agregarlo a la lista
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
    
    //agregar nueva tarea creada a la lista actual
    function addTaskToList(taskId, taskName, taskDescription, taskDate, taskStatus, taskProject, taskAssignee) {
        //quitar el mensaje de no hay tareas si es que existe
        const noTasksMessage = tasksList.querySelector('.text-center');
        if (noTasksMessage) {
            tasksList.innerHTML = '';
        }
        
        let formattedDate = 'Sin fecha';
        if (taskDate) {
            const dateObj = new Date(taskDate);
            formattedDate = dateObj.toLocaleDateString('es-MX', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
        const badgeInfo = getTaskBadgeInfo(taskStatus);
        
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
                                data-task-assignee="${taskAssignee || ''}"
                                title="Editar tarea">
                            <i class="mdi mdi-pencil"></i>
                        </button>
                    </div>
                    <div class="ps-4 text-muted small mt-1">${taskDescription}</div>
                </div>
            </li>
        `;
        
        tasksList.insertAdjacentHTML('beforeend', newTaskHTML);
        
        //agregar event listener a nueva tarea
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
    
    //actualizar una tarea en la lista
    function updateTaskInList(taskId, taskName, taskDescription, taskDate, taskStatus, taskAssignee) {
        const taskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (!taskLi) return;
        
        let formattedDate = 'Sin fecha';
        if (taskDate) {
            const dateObj = new Date(taskDate);
            formattedDate = dateObj.toLocaleDateString('es-MX', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
        
        //info de la insignia
        const badgeInfo = getTaskBadgeInfo(taskStatus);
        
        //actualizar nombre de la tarea
        const label = taskLi.querySelector('.form-check-label');
        const checkbox = taskLi.querySelector('.task-checkbox');
        if (label && checkbox) {
            //quedarse con la checkbox y actualizar el label
            const isChecked = taskStatus === 'completado';
            checkbox.checked = isChecked;
            
            //actualizar el contenido del label sin cambiar checkbox
            label.childNodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    node.textContent = taskName;
                }
            });
        }
        
        //actualizar fecha 
        const dateDiv = taskLi.querySelector('.text-small');
        if (dateDiv) {
            dateDiv.textContent = formattedDate;
        }
        
        //actualizar insignia
        const badge = taskLi.querySelector('.task-badge');
        if (badge) {
            badge.className = `badge ${badgeInfo.class} me-3 task-badge`;
            badge.textContent = badgeInfo.text;
        }
        
        //actualizar descripcion
        const descDiv = taskLi.querySelector('.text-muted.small');
        if (descDiv) {
            descDiv.textContent = taskDescription;
        }
        
        //actualizar boron de edicion
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
    
    //quitar una tarea de la lista
    function removeTaskFromList(taskId) {
        const taskLi = tasksList.querySelector(`li[data-task-id="${taskId}"]`);
        if (taskLi) {
            taskLi.remove();
            
            // revisar si la lista esta vacia
            if (tasksList.children.length === 0) {
                showNoTasksMessage();
            }
        }
    }
    
    //asignar estado de carga
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
    
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            //revisar si el proyecto esta seleccionado
            if (!currentProjectId) {
                showAlert(
                    'Por favor seleccione un proyecto primero',
                    'warning'
                );
                return;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
            modal.show();
        });
    }
    //mostrar mensaje default cuando no hay ningun proyecto seleccionado
    function showDefaultMessage() {
        tasksList.innerHTML = `
            <li class="d-block text-center py-4">
                <p class="text-muted">Seleccione un proyecto para ver sus tareas</p>
            </li>
        `;
    }
    
    //mostrar mensaje cuando el proyecto no tiene tareas
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
        // agregar notificacion de tipo tostada
        console.log(`[${type.toUpperCase()}] ${message}`);
        
        // mejorar notificacion
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    //mostrar mensaje en el modal
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