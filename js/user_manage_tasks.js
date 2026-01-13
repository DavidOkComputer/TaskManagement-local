/*user_manage_tasks.js para ver las tareas asignadas a usuarios */

document.addEventListener('DOMContentLoaded', function() {
	// Elementos del DOM 
	const projectSelect = document.getElementById('id_proyecto');
	const tasksList = document.getElementById('tasksList');
	const tasksLoading = document.getElementById('tasksLoading');
	const addBtn = document.querySelector('.todo-list-add-btn');
	const projectPermissionNote = document.getElementById('projectPermissionNote');
	// Estado global 
	let currentProjectId = null;
	let currentUserId = null;
	let currentProjectData = null;
	let projectsData = [];
	// Inicializar 
	loadProjects();
	setupAddTaskModal();

	function loadProjects() {
		fetch('../php/user_get_projects.php')
			.then(response => {
				if (!response.ok) {
					throw new Error('Error de red');
				}
				return response.json();
			})
			.then(data => {
				if (data.success && data.proyectos) {
					currentUserId = data.id_usuario;
					projectsData = data.proyectos;
					populateProjectSelect(projectSelect, data.proyectos);
				} else {
					showNotification('Error al cargar proyectos', 'warning');
				}
			})
			.catch(error => {
				console.error('Error cargando proyectos:', error);
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
			currentProjectId = parseInt(this.value);
			currentProjectData = projectsData.find(p => p.id_proyecto === currentProjectId);
			loadTasks(this.value);
			updateAddButtonVisibility();
		} else {
			currentProjectId = null;
			currentProjectData = null;
			showDefaultMessage();
			updateAddButtonVisibility();
		}
	});

	function updateAddButtonVisibility() {
		if (!addBtn) return;
		if (!currentProjectData) {
			addBtn.style.display = 'none';
			addBtn.disabled = true;
			return;
		}
		// El CREADOR siempre puede crear tareas 
		const isCreator = currentProjectData.id_creador === currentUserId;
		if (isCreator) {
			addBtn.style.display = 'inline-block';
			addBtn.disabled = false;
			addBtn.title = 'Crear nueva tarea';
		} else {
			// Para no-creadores, verificar si el proyecto permite edición 
			const canEdit = currentProjectData.puede_editar_otros === 1;
			const isParticipant = currentProjectData.id_participante === currentUserId || currentProjectData.es_mi_proyecto;
			if (canEdit && isParticipant) {
				addBtn.style.display = 'inline-block';
				addBtn.disabled = false;
				addBtn.title = 'Crear nueva tarea';
			} else {
				addBtn.style.display = 'none';
				addBtn.disabled = true;
				addBtn.title = 'Solo el creador puede agregar tareas en este proyecto';
			}
		}
	}

	function setupAddTaskModal() {
		if (!document.getElementById('addTaskModal')) {
			createAddTaskModal();
		}
		if (addBtn) {
			addBtn.addEventListener('click', function(e) {
				e.preventDefault();
				if (!currentProjectId) {
					showNotification('Selecciona un proyecto primero', 'warning');
					return;
				}
				if (!currentProjectData) {
					showNotification('Error: datos del proyecto no disponibles', 'danger');
					return;
				}
				// El creador siempre puede crear 
				const isCreator = currentProjectData.id_creador === currentUserId;
				if (!isCreator) {
					// Verificar permisos para no-creadores 
					const canEdit = currentProjectData.puede_editar_otros === 1;
					if (!canEdit) {
						showNotification('Solo el creador del proyecto puede agregar tareas', 'warning');
						return;
					}
				}
				openAddTaskModal();
			});
		}
	}

	function createAddTaskModal() {
		const modalHTML = ` 
            <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true"> 
                <div class="modal-dialog"> 
                    <div class="modal-content"> 
                        <div class="modal-header"> 
                            <h5 class="modal-title" id="addTaskModalLabel"> 
                                <i class="mdi mdi-plus-circle me-2"></i>Nueva Tarea 
                            </h5> 
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> 
                        </div> 
                        <form id="addTaskForm"> 
                            <div class="modal-body"> 
                                <div class="alert alert-info small mb-3"> 
                                    <i class="mdi mdi-information me-1"></i> 
                                    La tarea se asignará automáticamente a ti. 
                                </div> 
                                <input type="hidden" id="taskProjectId" name="id_proyecto"> 
                                <div class="mb-3"> 
                                    <label for="taskProjectName" class="form-label">Proyecto</label> 
                                    <input type="text" class="form-control" id="taskProjectName" readonly> 
                                </div> 
                                <div class="mb-3"> 
                                    <label for="taskName" class="form-label"> 
                                        Nombre de la tarea <span class="text-danger">*</span> 
                                    </label> 
                                    <input type="text" class="form-control" id="taskName" name="nombre"  
                                           maxlength="100" required placeholder="Ingresa el nombre de la tarea"> 
                                    <small class="text-muted">Máximo 100 caracteres</small> 
                                </div> 

                                <div class="mb-3"> 
                                    <label for="taskDescription" class="form-label">Descripción</label> 
                                    <textarea class="form-control" id="taskDescription" name="descripcion"  
                                              rows="3" maxlength="250" placeholder="Describe la tarea (opcional)"></textarea> 
                                    <small class="text-muted">Máximo 250 caracteres</small> 
                                </div> 
 
                                <div class="mb-3"> 
                                    <label for="taskDueDate" class="form-label">Fecha de cumplimiento</label> 
                                    <input type="date" class="form-control" id="taskDueDate" name="fecha_cumplimiento"> 
                                    <small class="form-text text-muted" id="taskDateNote" style="display: none;"></small> 
                                    <small class="form-text text-warning" id="taskDateWarning" style="display: none;"> 
                                        <i class="mdi mdi-alert"></i> La fecha es anterior al inicio del proyecto. La tarea se marcará como vencida. 
                                    </small> 
                                </div> 
                            </div> 

                            <div class="modal-footer"> 
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> 
                                    <i class="mdi mdi-close me-1"></i>Cancelar 
                                </button> 
                                <button type="submit" class="btn btn-primary" id="saveTaskBtn"> 
                                    <i class="mdi mdi-content-save me-1"></i>Guardar Tarea 
                                </button> 
                            </div> 
                        </form> 
                    </div> 
                </div> 
            </div> 
        `;
		document.body.insertAdjacentHTML('beforeend', modalHTML);
		const form = document.getElementById('addTaskForm');
		form.addEventListener('submit', handleTaskSubmit);
		// Agregar listener para verificar fecha 
		const taskDueDateInput = document.getElementById('taskDueDate');
		taskDueDateInput.addEventListener('change', function() {
			if (currentProjectData && currentProjectData.fecha_inicio) {
				checkTaskDateWarning(this.value, currentProjectData.fecha_inicio);
			}
		});
	}

	function openAddTaskModal() {
		const modal = document.getElementById('addTaskModal');
		const form = document.getElementById('addTaskForm');
		form.reset();
		document.getElementById('taskProjectId').value = currentProjectId;
		document.getElementById('taskProjectName').value = currentProjectData.nombre;
		// Mostrar nota de fecha de inicio del proyecto 
		if (currentProjectData && currentProjectData.fecha_inicio) {
			const taskDateNoteDiv = document.getElementById('taskDateNote');
			if (taskDateNoteDiv) {
				const fecha = parseDateStringToLocal(currentProjectData.fecha_inicio);
				const fechaFormato = fecha.toLocaleDateString('es-MX', {
					day: '2-digit',
					month: 'long',
					year: 'numeric'
				});
				taskDateNoteDiv.textContent = `Fecha de inicio del proyecto: ${fechaFormato}`;
				taskDateNoteDiv.style.display = 'block';
			}
		}
		// Ocultar advertencia 
		const warningDiv = document.getElementById('taskDateWarning');
		if (warningDiv) warningDiv.style.display = 'none';
		const bsModal = new bootstrap.Modal(modal);
		bsModal.show();
	}
	// Verificar si la fecha de la tarea es anterior al inicio del proyecto 
	function checkTaskDateWarning(taskDate, projectStartDate) {
		const warningDiv = document.getElementById('taskDateWarning');
		if (!warningDiv || !taskDate || !projectStartDate) {
			if (warningDiv) warningDiv.style.display = 'none';
			return;
		}
		const taskDateObj = parseDateStringToLocal(taskDate);
		const projectStartObj = parseDateStringToLocal(projectStartDate);
		if (taskDateObj && projectStartObj && taskDateObj < projectStartObj) {
			warningDiv.style.display = 'block';
		} else {
			warningDiv.style.display = 'none';
		}
	}
	// Verificar si una tarea está vencida (fecha pasada y no completada) 
	function isTaskOverdue(taskDate, taskStatus) {
		if (!taskDate || taskDate === '0000-00-00' || taskStatus === 'completado') {
			return false;
		}
		const taskDateObj = parseDateStringToLocal(taskDate);
		if (!taskDateObj) return false;
		const today = new Date();
		today.setHours(0, 0, 0, 0); // Normalizar a inicio del día 
		return taskDateObj < today;
	}

	function handleTaskSubmit(e) {
		e.preventDefault();
		const form = e.target;
		const submitBtn = document.getElementById('saveTaskBtn');
		const originalBtnText = submitBtn.innerHTML;
		const formData = new FormData(form);
		const nombre = formData.get('nombre').trim();
		if (!nombre) {
			showNotification('El nombre de la tarea es requerido', 'warning');
			return;
		}
		// Ya no validamos que la fecha no sea anterior al inicio del proyecto 
		submitBtn.disabled = true;
		submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
		fetch('../php/user_create_task.php', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				submitBtn.disabled = false;
				submitBtn.innerHTML = originalBtnText;
				if (data.success) {
					showNotification(data.message || 'Tarea creada exitosamente', 'success');
					const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
					if (modal) {
						modal.hide();
					}
					loadTasks(currentProjectId);
					form.reset();
				} else {
					showNotification(data.message || 'Error al crear la tarea', 'danger');
				}
			})
			.catch(error => {
				console.error('Error:', error);
				submitBtn.disabled = false;
				submitBtn.innerHTML = originalBtnText;
				showNotification('Error de conexión al crear la tarea', 'danger');
			});
	}

	function loadTasks(projectId) {
		tasksLoading.style.display = 'block';
		tasksList.style.display = 'none';
		fetch('../php/user_get_tasks_by_project.php?id_proyecto=' + projectId)
			.then(response => response.json())
			.then(data => {
				tasksLoading.style.display = 'none';
				tasksList.style.display = 'block';
				if (data.success && data.tasks && data.tasks.length > 0) {
					renderTasks(data.tasks);
				} else if (data.success && data.tasks.length === 0) {
					showNoTasksMessage();
				} else {
					showErrorMessage(data.message || 'Error cargando tareas');
				}
			})
			.catch(error => {
				console.error('Error cargando tareas:', error);
				tasksLoading.style.display = 'none';
				tasksList.style.display = 'block';
				showErrorMessage('Error de conexión');
			});
	}

	function renderTasks(tasks) {
		tasksList.innerHTML = '';
		tasks.forEach((task, index) => {
			const isLast = index === tasks.length - 1;
			const taskElement = createTaskElement(task, isLast);
			tasksList.insertAdjacentHTML('beforeend', taskElement);
		});
		attachTaskListeners();
	}

	function parseDateStringToLocal(dateString) {
		if (!dateString || dateString === '0000-00-00') return null;
		const parts = dateString.split('-');
		if (parts.length !== 3) return null;
		const year = parseInt(parts[0], 10);
		const month = parseInt(parts[1], 10) - 1;
		const day = parseInt(parts[2], 10);
		return new Date(year, month, day);
	}

	function formatDateForDisplay(dateString) {
		const dateObj = parseDateStringToLocal(dateString);
		if (!dateObj) return 'Sin fecha';
		return dateObj.toLocaleDateString('es-MX', {
			day: '2-digit',
			month: 'short',
			year: 'numeric'
		});
	}

	function createTaskElement(task, isLast = false) {
		const formattedDate = formatDateForDisplay(task.fecha_cumplimiento);
		const badgeInfo = getTaskBadgeInfo(task.estado);
		const isCompleted = task.estado === 'completado';
		const borderClass = isLast ? 'border-bottom-0' : '';
		const checkboxIcon = isCompleted ? 'mdi-checkbox-marked-circle-outline' : 'mdi-checkbox-blank-circle-outline';
		const checkboxColor = isCompleted ? 'text-success' : 'text-muted';
		// Verificar si la tarea está vencida 
		const overdue = isTaskOverdue(task.fecha_cumplimiento, task.estado);
		const overdueIndicator = overdue ? '<span class="text-danger fw-bold ms-1" title="Tarea vencida">*</span>' : '';
		const overdueClass = overdue ? 'task-overdue' : '';
		const dateClass = overdue ? 'text-danger fw-bold' : '';
		const dateText = overdue ? `${formattedDate} (Vencida)` : formattedDate;
		return ` 
            <li class="d-block ${borderClass} ${overdueClass}" data-task-id="${task.id_tarea}"> 
                <div class="d-flex align-items-start w-100 gap-2"> 
                    <i class="mdi mdi-24px ${checkboxIcon} ${checkboxColor} task-checkbox-icon flex-shrink-0" 
                       data-task-id="${task.id_tarea}" 
                       style="cursor: pointer; margin-top: 2px;" 
                       title="Click para cambiar estado"> 
                    </i> 
                    <div class="flex-grow-1"> 
                        <div> 
                            <label style="cursor: pointer; ${isCompleted ? 'text-decoration: line-through; color: #6c757d;' : ''}"> 
                                ${escapeHtml(task.nombre)}${overdueIndicator} 
                            </label> 
                        </div> 
                        <div class="d-flex mt-2 align-items-center flex-wrap gap-2"> 
                            <div class="text-small ${dateClass}"> 
                                <i class="mdi mdi-calendar-clock"></i> ${dateText} 
                            </div> 
                            <div class="badge ${badgeInfo.class} task-badge"> 
                                ${badgeInfo.text} 
                            </div> 
                            <i class="mdi mdi-flag flag-color"></i> 
                        </div> 

                        ${task.descripcion ? ` 
                            <div class="text-muted small mt-2"> 
                                <i class="mdi mdi-text"></i> ${escapeHtml(task.descripcion)} 
                            </div> 
                        ` : ''} 
                    </div> 
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

    function escapeHtml(text) {
		if (!text) return '';
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.toString().replace(/[&<>"']/g, m => map[m]);
	}

    function attachTaskListeners() {
		const checkboxIcons = document.querySelectorAll('.task-checkbox-icon');
		checkboxIcons.forEach(icon => {
			icon.addEventListener('click', handleTaskStatusChange);
		});
	}

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
		fetch('../php/user_update_task_status.php', {
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
						// Quitar indicador de vencido si se completa 
						taskLi.classList.remove('task-overdue');
						const overdueIndicator = taskLi.querySelector('.text-danger.fw-bold[title="Tarea vencida"]');
						if (overdueIndicator) {
							overdueIndicator.remove();
						}
						// Actualizar texto de fecha para quitar "(Vencida)" 
						const dateDiv = taskLi.querySelector('.text-small');
						if (dateDiv) {
							dateDiv.classList.remove('text-danger', 'fw-bold');
							dateDiv.innerHTML = dateDiv.innerHTML.replace(' (Vencida)', '');
						}
					} else {
						icon.classList.remove('mdi-checkbox-marked-circle-outline');
						icon.classList.add('mdi-checkbox-blank-circle-outline');
						icon.classList.remove('text-success');
						icon.classList.add('text-muted');
						// Recargar para verificar si debe mostrar indicador de vencido 
						// Es más simple recargar toda la lista 
						loadTasks(currentProjectId);
						return;
					}
					const badge = taskLi.querySelector('.task-badge');
					const badgeInfo = getTaskBadgeInfo(newStatus);
					if (badge) {
						badge.className = `badge ${badgeInfo.class} task-badge`;
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
					const statusMessage = newStatus === 'completado' ?
						'Tarea marcada como completada' :
						'Tarea marcada como pendiente';
					showNotification(statusMessage, 'success');
				} else {
					taskLi.style.opacity = '1';
					taskLi.style.pointerEvents = 'auto';
					icon.style.pointerEvents = 'auto';
					showNotification(data.message || 'Error al actualizar', 'danger');
				}
			})
			.catch(error => {
				console.error('Error actualizando estado:', error);
				taskLi.style.opacity = '1';
				taskLi.style.pointerEvents = 'auto';
				icon.style.pointerEvents = 'auto';
				showNotification('Error de conexión', 'danger');
			});
	}

    function showDefaultMessage() {
		tasksList.innerHTML = ` 
            <li class="d-block text-center py-4"> 
                <i class="mdi mdi-folder-open mdi-48px text-muted mb-3"></i> 
                <p class="text-muted"> 
                    Seleccione un proyecto para ver sus tareas asignadas 
                </p> 
            </li> 
        `;
	}

    function showNoTasksMessage() {
		const isCreator = currentProjectData && currentProjectData.id_creador === currentUserId;
		let additionalMessage = '';
		if (isCreator) {
			additionalMessage = ` 
                <p class="text-primary mt-2"> 
                    <i class="mdi mdi-plus-circle me-1"></i> 
                    Eres el creador de este proyecto. Puedes agregar tareas usando el botón "+". 
                </p> 
            `;
		}
		tasksList.innerHTML = ` 
            <li class="d-block text-center py-4"> 
                <i class="mdi mdi-checkbox-marked-circle-outline mdi-48px text-success mb-3"></i> 
                <p class="text-muted"> 
                    No tienes tareas asignadas en este proyecto. 
                </p> 
                ${additionalMessage} 
            </li> 
        `;
	}

    function showErrorMessage(message = 'Error al cargar tareas') {
		tasksList.innerHTML = ` 
            <li class="d-block text-center py-4"> 
                <i class="mdi mdi-alert-circle-outline mdi-48px text-danger mb-3"></i> 
                <p class="text-danger">${escapeHtml(message)}</p> 
            </li> 
        `;
	}

    function showNotification(message, type) {
		const toast = document.createElement('div');
		toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
		toast.style.zIndex = '9999';
		toast.style.minWidth = '300px';
		toast.innerHTML = ` 
            ${message} 
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button> 
        `;
		document.body.appendChild(toast);
		setTimeout(() => {
			toast.classList.remove('show');
			setTimeout(() => {
				toast.remove();
			}, 150);
		}, 5000);
	}
});