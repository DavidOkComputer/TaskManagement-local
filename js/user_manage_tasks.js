/* user_manage_tasks para manejar las tareas desde la version de usuario*/ 

document.addEventListener( 
    'DOMContentLoaded',  
    function() { 
    const projectSelect = document.getElementById( 'id_proyecto'); 
    const tasksList = document.getElementById( 'tasksList' ); 
    const tasksLoading = document.getElementById('tasksLoading'); 
    const addBtn = document.querySelector('.todo-list-add-btn'); 
    const projectPermissionNote = document.getElementById('projectPermissionNote'); 
    let currentProjectId = null; 
    let currentUserId = null; 

    //ocultar boton de agregar para usuarios
    if (addBtn) {addBtn.style.display = 'none';} 
    loadProjects(); 

    function loadProjects() { 
        fetch('../php/user_get_projects.php') 

        .then(response => { 
            if (!response.ok) { 
                throw new Error( 
                    'Error de red' 
                ); 
            } 
            return response.json(); 
        }) 

        .then(data => { 
            if (data.success && data.proyectos) {
                currentUserId = data.id_usuario; 
                populateProjectSelect( 
                    projectSelect,  
                    data.proyectos 
                ); 

                //mostrar nota
                if (projectPermissionNote) { 
                    projectPermissionNote 
                        .innerHTML = ` 
                        <i class="mdi  
                           mdi-information-outline  
                           text-info"></i> 
                        <strong>Nota:</strong>Solo puedes ver y actualizar el estado de las tareas asignadas a ti `; 
                  
                    projectPermissionNote 
                        .style.display = 'block'; 
                } 
            } else { 
                showNotification( 
                    'Error al cargar ' + 'proyectos',  
                    'warning' 
                ); 
            } 
        }) 

        .catch(error => { 
            console.error(error); 
            showNotification( 
                'Error al cargar ' + 'proyectos',  
                'danger' 
            ); 
        }); 
    } 

    function populateProjectSelect( 
        selectElement,  
        projects 
    ) { 
        selectElement.innerHTML = '<option value="">' + 'Seleccione un proyecto' + '</option>'; 
        projects.forEach(project => { 
            const option = document.createElement('option');            
            option.value = project.id_proyecto; 
            option.textContent = project.nombre; 
            selectElement.appendChild(option); 
        }); 
    } 

    projectSelect.addEventListener( 
        'change',  

        function() { 
            if (this.value) { 
                currentProjectId = this.value; 
                loadTasks(this.value); 
            } else { 
                currentProjectId = null; 
                showDefaultMessage(); 
            } 
        } 
    ); 

    function loadTasks(projectId) { 
        tasksLoading.style.display = 'block'; 
        tasksList.style.display = 'none'; 

        fetch('../php/' + 'user_get_tasks_by_project.php' + '?id_proyecto=' + projectId) 

        .then(response => response.json()) 

        .then(data => { 
            tasksLoading.style.display = 'none'; 
            tasksList.style.display = 'block'; 
     
               if (data.success && data.tasks && data.tasks.length > 0) {                
                renderTasks(data.tasks); 
            } else if (data.success  
                && data.tasks.length === 0) { 
                showNoTasksMessage(); 
            } else { 
                showErrorMessage( 
                    data.message ||  
                    'Error cargando tareas' 
                ); 
            } 
        }) 

        .catch(error => { 
            console.error(error); 
            tasksLoading.style.display = 'none'; 
            tasksList.style.display = 'block'; 

            showErrorMessage('Error de conexión'); 
        }); 
    } 

    function renderTasks(tasks) { 
        tasksList.innerHTML = '';        
        tasks.forEach((task, index) => { 
            const isLast = index === tasks.length - 1;            
            const taskElement = createTaskElement( task, isLast); 

            tasksList 
                .insertAdjacentHTML( 
                    'beforeend',  
                    taskElement 
                ); 
        });        
        attachTaskListeners(); 
    } 

    function parseDateStringToLocal( 
        dateString 
    ) { 
        if (!dateString) return null;         
        const parts = dateString.split('-'); 
        if (parts.length !== 3)  
            return null; 

        const year = parseInt(parts[0], 10); 
        const month = parseInt(parts[1], 10) - 1; 
        const day = parseInt(parts[2], 10); 
        
        return new Date( 
            year,  
            month,  
            day 
        ); 
    } 

    function formatDateForDisplay( 
        dateString 
    ) { 
        const dateObj = parseDateStringToLocal(dateString); 

        if (!dateObj)  
            return 'Sin fecha'; 

        return dateObj.toLocaleDateString('es-MX',  
                { 
                    day: '2-digit', 
                    month: 'short', 
                    year: 'numeric' 
                } 
            ); 
    } 

    function createTaskElement( 
        task,  
        isLast = false 
    ) { 
        const formattedDate = formatDateForDisplay(task.fecha_cumplimiento); 
        const badgeInfo = getTaskBadgeInfo(task.estado); 
        const isCompleted = task.estado === 'completado'; 

        const borderClass = isLast ? 'border-bottom-0' : ''; 
        const checkboxIcon = isCompleted ? 'mdi-checkbox-marked-' + 'circle-outline' : 'mdi-checkbox-blank-' + 'circle-outline'; 
        const checkboxColor =  isCompleted ? 'text-success' : 'text-muted'; 

        return ` 
            <li class="d-block  ${borderClass}" data-task-id="${task.id_tarea}"> 
                <div class="d-flex align-items-start w-100 gap-2"> 
                    <i class="mdi mdi-24px 
                              ${checkboxIcon}  
                              ${checkboxColor}  
                              task-checkbox-icon  
                              flex-shrink-0"  
                              data-task-id=" 
                              ${task.id_tarea}"  
                              style="cursor: pointer;  
                              margin-top: 2px;" 
                       title="Click marcar"> 
                    </i> 

                    <div class="flex-grow-1"> 
                        <div> 
                            <label style="cursor: pointer;  
                                ${isCompleted  
                                    ? 'text-decoration: ' + 
                                      'line-through; ' + 
                                      'color: #6c757d;'  
                                    : ''}"> 

                                ${escapeHtml( 
                                    task.nombre 
                                )} 
                            </label>

                        </div> 
                        <div class="d-flex mt-2 align-items-center"> 
                            <div class="text-small me-3"> 
                                <i class="mdi mdi-calendar-clock"> </i>  
                                ${formattedDate} 
                            </div> 

                            <div class="badge ${badgeInfo.class} me-3 task-badge"> 
                                ${badgeInfo.text} 
                            </div> 

                            <i class="mdi mdi-flag ms-2 flag-color"> </i> 
                        </div> 

                        <div class="text-muted small mt-2"> 
                            <i class="mdi mdi-text"> </i>  
                            ${escapeHtml( 
                                task.descripcion 
                            )} 
                        </div> 
                    </div> 
                </div> 
            </li> 
        `; 
    } 

    function getTaskBadgeInfo( 
        status 
    ) { 
        const statusMap = { 
            'completado': { 
                class:  
                    'badge-opacity-success', 
                text: 'Completado' 
            }, 
            'en proceso': { 
                class:  
                    'badge-opacity-info', 
                text: 'En Progreso' 
            }, 
            'en-progreso': { 
                class:  
                    'badge-opacity-info', 
                text: 'En Progreso' 
            }, 
            'vencido': { 
                class:  
                    'badge-opacity-danger', 
                text: 'Vencido' 
            }, 
            'pendiente': { 
                class:  
                    'badge-opacity-warning', 
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
        
        return text.toString() 
            .replace( 
                /[&<>"']/g,  
                m => map[m] 
            ); 
    } 

    function attachTaskListeners() { 
        const checkboxIcons = document.querySelectorAll('.task-checkbox-icon'); 
        
        checkboxIcons.forEach(icon => { 
            icon.addEventListener('click', handleTaskStatusChange); 
        }); 
    } 

    function handleTaskStatusChange( 
        event 
    ) { 
        const icon = event.target; 
        const taskId = icon.getAttribute('data-task-id'); 
        const isCurrentlyChecked = icon.classList.contains('mdi-checkbox-marked-' +'circle-outline');         
        const newStatus = isCurrentlyChecked ? 'pendiente' : 'completado';         
        const taskLi = icon.closest('li'); 

        //mostrar cargando 

        taskLi.style.opacity = '0.6'; 
        taskLi.style.pointerEvents =  'none'; 
        icon.style.pointerEvents =  'none'; 

        //preparar info 
        const updateData = new FormData(); 
        updateData.append('id_tarea', taskId); 
        updateData.append('estado',newStatus); 

        fetch('../php/' + 'user_update_task_status.php',  
            { 
                method: 'POST', 
                body: updateData 
            } 
        ) 

        .then(response =>  
            response.json() 
        ) 

        .then(data => { 
            if (data.success) { 
                if (newStatus === 'completado') { 
                    icon.classList.remove('mdi-checkbox-' + 'blank-circle-' + 'outline'); 
                    icon.classList.add('mdi-checkbox-' + 'marked-circle-' + 'outline'); 
                    icon.classList.remove('text-muted'); 
                    icon.classList.add('text-success'); 

                } else { 
                    icon.classList.remove('mdi-checkbox-' + 'marked-circle-' + 'outline'); 
                    icon.classList.add('mdi-checkbox-' + 'blank-circle-' + 'outline'); 
                    icon.classList 

                        .remove('text-success'); 

                    icon.classList 

                        .add('text-muted'); 

                } 


                //actualzar insignia

                const badge =  

                    taskLi.querySelector( 

                        '.task-badge' 

                    ); 

                const badgeInfo =  

                    getTaskBadgeInfo( 

                        newStatus 

                    ); 

                 

                if (badge) { 

                    badge.className =  

                        `badge ` + 

                        `${badgeInfo.class} ` + 

                        `me-3 task-badge`; 

                    badge.textContent =  

                        badgeInfo.text; 

                } 


                // Update decoration 

                const label =  

                    taskLi.querySelector( 

                        'label' 

                    ); 

                 

                if (label) { 

                    if (newStatus ===  

                        'completado') { 

                        label.style 

                            .textDecoration =  

                            'line-through'; 

                        label.style.color =  

                            '#6c757d'; 

                    } else { 

                        label.style 

                            .textDecoration =  

                            'none'; 

                        label.style.color =  

                            'inherit'; 

                    } 

                } 

                // Restore normal 

                taskLi.style.opacity =  

                    '1'; 

                taskLi.style.pointerEvents =  

                    'auto'; 

                icon.style.pointerEvents =  

                    'auto'; 

                 

                const statusMessage =  

                    newStatus ===  

                    'completado'  

                    ? 'Tarea marcada ' + 

                      'como completada'  

                    : 'Tarea marcada ' + 

                      'como pendiente'; 

                 

                showNotification( 

                    statusMessage,  

                    'success' 

                ); 


            } else { 

                // Revert on error 

                taskLi.style.opacity =  

                    '1'; 

                taskLi.style.pointerEvents =  

                    'auto'; 

                icon.style.pointerEvents =  

                    'auto'; 

                 

                showNotification( 

                    data.message ||  

                    'Error al actualizar',  

                    'danger' 

                ); 

            } 

        }) 

        .catch(error => { 

            console.error(error); 

             

            taskLi.style.opacity = '1'; 

            taskLi.style.pointerEvents =  

                'auto'; 

            icon.style.pointerEvents =  

                'auto'; 

             

            showNotification( 

                'Error de conexión',  

                'danger' 

            ); 

        }); 

    } 

    function showDefaultMessage() { 

        tasksList.innerHTML = ` 

            <li class="d-block  

                       text-center py-4"> 

                <i class="mdi  

                          mdi-folder-open  

                          mdi-48px  

                          text-muted mb-3"> 

                </i> 

                <p class="text-muted"> 

                    Seleccione un proyecto  

                    para ver sus tareas  

                    asignadas 

                </p> 

            </li> 

        `; 

    } 

    function showNoTasksMessage() { 

        tasksList.innerHTML = ` 

            <li class="d-block  

                       text-center py-4"> 

                <i class="mdi  

                          mdi-checkbox-marked- 

                          circle-outline  

                          mdi-48px  

                          text-success mb-3"> 

                </i> 

                <p class="text-muted"> 

                    No tienes tareas  

                    asignadas en este  

                    proyecto 

                </p> 

                <small class="text-muted"> 

                    Contacta al  

                    administrador si  

                    esto es un error 

                </small> 

            </li> 

        `; 

    } 

    function showErrorMessage( 

        message =  

        'Error al cargar tareas' 

    ) { 

        tasksList.innerHTML = ` 

            <li class="d-block  

                       text-center py-4"> 

                <i class="mdi  

                          mdi-alert-circle- 

                          outline  

                          mdi-48px  

                          text-danger mb-3"> 

                </i> 

                <p class="text-danger"> 

                    ${escapeHtml(message)} 

                </p> 

            </li> 

        `; 

    } 

    function showNotification( 

        message,  

        type 

    ) { 

        const toast =  

            document.createElement( 

                'div' 

            ); 

         

        toast.className =  

            `alert alert-${type} ` + 

            `alert-dismissible ` + 

            `fade show ` + 

            `position-fixed ` + 

            `top-0 end-0 m-3`; 

         

        toast.style.zIndex = '9999'; 

        toast.style.minWidth =  

            '300px'; 

         

        toast.innerHTML = ` 

            ${message} 

            <button type="button"  

                    class="btn-close"  

                    data-bs-dismiss="alert"> 

            </button> 

        `; 

         

        document.body.appendChild( 

            toast 

        ); 

         

        setTimeout(() => { 

            toast.classList.remove( 

                'show' 

            ); 

            setTimeout(() => { 

                toast.remove(); 

            }, 150); 

        }, 5000); 

    } 

 

}); // End DOMContentLoaded 
