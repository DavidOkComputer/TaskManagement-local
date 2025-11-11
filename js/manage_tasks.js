/*task-management.js - manejo detareas*/ 
document.addEventListener('DOMContentLoaded', function() { 

    const projectSelect = document.getElementById('id_proyecto'); 
    const tasksList = document.getElementById('tasksList'); 
    const tasksLoading = document.getElementById('tasksLoading'); 
    const addBtn = document.querySelector('.todo-list-add-btn'); 
    let currentProjectId = null; //seguimiento del proyecto seleccionado 

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
        // inicializar eventos del modal 
        initializeModalEventListeners(); 
    } 
    loadProjects(); 

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

    function populateProjectSelect(selectElement, projects) { 
        selectElement.innerHTML = '<option value="">Seleccione un proyecto</option>'; 
        projects.forEach(project => { 
            const option = document.createElement('option'); 
            option.value = project.id_proyecto; 
            option.textContent = project.nombre; 
            selectElement.appendChild(option); 
        }); 
    } 

    projectSelect.addEventListener('change', function() { 
        if (this.value) { 
            currentProjectId = this.value; 
            loadTasks(this.value); 
        } else { 
            currentProjectId = null; 
            showDefaultMessage(); 
        } 
    }); 

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

    function renderTasks(tasks) { 
        tasksList.innerHTML = ''; 
        tasks.forEach((task, index) => { 
            const isLast = index === tasks.length - 1; 
            const taskElement = createTaskElement(task, isLast); 
            tasksList.insertAdjacentHTML('beforeend', taskElement); 
        }); 
        attachCheckboxListeners(); 
    } 

    function createTaskElement(task, isLast = false) { 
        const dateObj = new Date(task.fecha_cumplimiento); 
        const formattedDate = dateObj.toLocaleDateString('es-MX', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric' 
        }); 

        const badgeInfo = getTaskBadgeInfo(task.estado); 
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
                    <div class="d-flex mt-2"> 
                        <div class="ps-4 text-small me-3">${formattedDate}</div> 
                        <div class="badge ${badgeInfo.class} me-3 task-badge">${badgeInfo.text}</div> 
                        <i class="mdi mdi-flag ms-2 flag-color"></i> 
                    </div> 
                    <div class="ps-4 text-muted small mt-1">${task.descripcion}</div> 
                </div> 
            </li> 
        `; 
    } 

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

    function attachCheckboxListeners() { 
        const checkboxes = document.querySelectorAll('.task-checkbox'); 
        checkboxes.forEach(checkbox => { 
            checkbox.addEventListener('change', handleTaskStatusChange); 
        }); 
    } 

    function handleTaskStatusChange(event) { 
        const checkbox = event.target; 
        const taskId = checkbox.getAttribute('data-task-id'); 
        const isChecked = checkbox.checked; 
        const newStatus = isChecked ? 'completado' : 'pendiente'; 
        const taskLi = checkbox.closest('li');//tener el item para la lista de tareas 
        taskLi.style.opacity = '0.6'; 
        taskLi.style.pointerEvents = 'none'; 
        checkbox.disabled = true; 
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
                const badge = taskLi.querySelector('.task-badge'); 
                const badgeInfo = getTaskBadgeInfo(newStatus); 
                if (badge) { 
                    badge.className = `badge ${badgeInfo.class} me-3 task-badge`; 
                    badge.textContent = badgeInfo.text; 
                } 
                taskLi.style.opacity = '1'; 
                taskLi.style.pointerEvents = 'auto'; 
                checkbox.disabled = false; 
                showNotification('Estado de tarea actualizado correctamente', 'success'); 
            } else { 
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
    function initializeModalEventListeners() { 
        const modal = document.getElementById('addTaskModal'); 
        const saveBtn = document.getElementById('saveTaskBtn'); 
        modal.addEventListener('show.bs.modal', function() { 
            loadProjectsForModal(); 
            if (currentProjectId) { 
                setTimeout(() => { 
                    document.getElementById('taskProject').value = currentProjectId; 
                }, 100); 
            } 
        }); 
        modal.addEventListener('hidden.bs.modal', function() { 
            document.getElementById('addTaskForm').reset(); 
            document.getElementById('taskMessage').style.display = 'none'; 
        }); 
        if (saveBtn) { 

            saveBtn.addEventListener('click', handleSaveTask); 
        } 
    } 
    function loadProjectsForModal() { 
        fetch('../php/get_projects.php') 
            .then(response => response.json()) 
            .then(data => { 
                const modalProjectSelect = document.getElementById('taskProject'); 
                if (data.success && data.proyectos) { 
                    populateProjectSelect(modalProjectSelect, data.proyectos); 
                } else { 
                    showModalMessage('Error al cargar proyectos', 'danger'); 
                } 
            }) 

            .catch(error => { 
                console.error('Error loading projects:', error); 
                showModalMessage('Error al cargar proyectos', 'danger'); 
            }); 
    } 
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
        setModalLoading(true); 
        const formData = new FormData(); 
        formData.append('nombre', taskName); 
        formData.append('descripcion', taskDescription); 
        formData.append('id_proyecto', taskProject); 
        formData.append('fecha_vencimiento', taskDate); 
        formData.append('estado', taskStatus); 
        formData.append('id_creador', 1); // remplazar con el id de la sesion 

        fetch('../php/save_task.php', { 
            method: 'POST', 
            body: formData 
        }) 
        .then(response => response.json()) 
        .then(data => {
            setModalLoading(false);
            if (data.success) { 
                showModalMessage('Tarea guardada exitosamente', 'success'); 
                // si la nueva tarea pertenece al proyecto actual agregarlo a la lista
                if (currentProjectId && taskProject == currentProjectId) { 
                    addTaskToList(data.task_id, taskName, taskDescription, taskDate, taskStatus); 
                }  
                //reiniciar modal y cerrarlo
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

    function addTaskToList(taskId, taskName, taskDescription, taskDate, taskStatus) {  
        //quitar el texto de sin tareas si existe
        const noTasksMessage = tasksList.querySelector('.text-center'); 
        if (noTasksMessage) { 
            tasksList.innerHTML = ''; 
        } //darle formato a la fecha
        let formattedDate = 'Sin fecha'; 
        if (taskDate) {
            const dateObj = new Date(taskDate); 
            formattedDate = dateObj.toLocaleDateString('es-MX', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric' 
            }); 
        } 
        //obtener informacion de la insignia
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
                    <div class="d-flex mt-2"> 
                        <div class="ps-4 text-small me-3">${formattedDate}</div> 
                        <div class="badge ${badgeInfo.class} me-3 task-badge">${badgeInfo.text}</div> 
                        <i class="mdi mdi-flag ms-2 flag-color"></i> 
                    </div> 
                    <div class="ps-4 text-muted small mt-1">${taskDescription}</div> 
                </div> 
            </li> 
        `; //agregar a la lista
        tasksList.insertAdjacentHTML('beforeend', newTaskHTML); 
        const newCheckbox = tasksList.querySelector(`li[data-task-id="${taskId}"] .task-checkbox`); 
        if (newCheckbox) { 
            newCheckbox.addEventListener('change', handleTaskStatusChange); 
        } 
    } 

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
            if (!currentProjectId) { 
                alert('Por favor seleccione un proyecto primero'); 
                return; 
            } 
            const modal = new bootstrap.Modal(document.getElementById('addTaskModal')); 
            modal.show(); 
        }); 
    } 

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
        //implementar notificacion de tostadora en esta seccion
        console.log(`[${type.toUpperCase()}] ${message}`); 
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