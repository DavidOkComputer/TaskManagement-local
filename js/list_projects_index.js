const Config = { 
    API_ENDPOINTS: { 
        DELETE: '../php/delete_project.php', 
        GET_PROJECT_USERS: '../php/get_project_user.php', 
        GET_DASHBOARD_STATS: '../php/get_dashboard_stats.php' 
    } 
}; 

let allProjects = []; 
let currentSortColumn = null; 
let sortDirection = 'asc'; 
let filteredProjects = []; 

//variables de paginacion 
let currentPage = 1; 
let rowsPerPage = 10; 
let totalPages = 0; 

// Variable para el auto-refresh 
let autoRefreshInterval = null; 
let currentProjectIdForUsers = null; // Para refrescar modal de usuarios 

document.addEventListener('DOMContentLoaded', function() { 
    initializeCustomDialogs(); 
    setupSorting(); 
    setupPagination(); 
    cargarProyectos(); 
    loadDashboardStats(); // Cargar estadísticas al inicio 
    startAutoRefresh(); // iniciar refresco cada minuto o 60000ms 
    loadTopEmployeesProgress();
    loadTopProjectsProgress();
}); 

function startAutoRefresh() { 
    if (autoRefreshInterval) { 
        //limpiar interval 
        clearInterval(autoRefreshInterval); 
    } 
    autoRefreshInterval = setInterval(() => { 
        //configurar el interval para refrescar cada minuto 
        console.log('Auto-refresh: Actualizando datos de proyectos...'); 
        refreshProjectsData(); 
        loadDashboardStats(); // Refrescar estadísticas también 
        loadTopEmployeesProgress();
        loadTopProjectsProgress();
        if (currentProjectIdForUsers) { 
            //si el modal de usuarios esta abirto refrescar 
            refreshProjectUsersData();
        } 
    }, 60000); // 60000 ms = 1 minuto 
} 

function stopAutoRefresh() { 
    if (autoRefreshInterval) { 
        clearInterval(autoRefreshInterval); 
        autoRefreshInterval = null; 
        console.log('Auto-refresh detenido'); 
    } 
} 

function loadDashboardStats() { 
    fetch(Config.API_ENDPOINTS.GET_DASHBOARD_STATS) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('Error en la respuesta de red'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.stats) { 
                updateDashboardStats(data.stats); 
                console.log('Estadísticas del dashboard actualizadas:', data.stats); 
            } else { 
                console.error('Error al cargar estadísticas:', data.message); 
            } 
        }) 
        .catch(error => { 
            console.error('Error al cargar estadísticas del dashboard:', error); 
        }); 
} 

function updateDashboardStats(stats) { 
    // Actualizar cada estadística en el DOM 
    const statsElements = document.querySelectorAll('.statistics-details > div'); 
    if (statsElements.length >= 7) { 
        // 1. Total de objetivos - mostrar: objetivos retrasados
        const objetivosElement = statsElements[0].querySelector('.rate-percentage'); 
        if (objetivosElement) { 
            objetivosElement.textContent = stats.total_objetivos; 
        } 
        const objetivosProgress = statsElements[0].querySelector('.text-danger, .text-success'); 
        if (objetivosProgress) { 
            const retrasados = stats.objetivos_retrasados || 0;
            const isPositive = retrasados === 0; 
            objetivosProgress.className = isPositive ? 'text-success d-flex' : 'text-danger d-flex'; 
            const icon = objetivosProgress.querySelector('i'); 
            if (icon) { 
                icon.className = isPositive ? 'mdi mdi-check-circle' : 'mdi mdi-alert-circle'; 
            } 
            const span = objetivosProgress.querySelector('span'); 
            if (span) { 
                span.textContent = retrasados === 0 
                    ? 'Sin retrasos' 
                    : `${retrasados} retrasado${retrasados > 1 ? 's' : ''}`; 
            } 
        } 
        
        // 2. Total de proyectos - mostrar: % completados
        const proyectosElement = statsElements[1].querySelector('.rate-percentage'); 
        if (proyectosElement) { 
            proyectosElement.textContent = stats.total_proyectos; 
        } 
        const proyectosProgress = statsElements[1].querySelector('.text-danger, .text-success'); 
        if (proyectosProgress) { 
            const porcentajeComp = stats.porcentaje_completados || 0;
            const isPositive = porcentajeComp >= 50; 
            proyectosProgress.className = isPositive ? 'text-success d-flex' : 'text-danger d-flex'; 
            const icon = proyectosProgress.querySelector('i'); 
            if (icon) { 
                icon.className = isPositive ? 'mdi mdi-menu-up' : 'mdi mdi-menu-down'; 
            } 
            const span = proyectosProgress.querySelector('span'); 
            if (span) { 
                span.textContent = `${porcentajeComp}% completados`; 
            } 
        }
        
        // 3. Total de Tareas - mosrtar: % completadas y tareas retrasadas
        const tareasElement = statsElements[2].querySelector('.rate-percentage'); 
        if (tareasElement) { 
            tareasElement.textContent = `${stats.porcentaje_tareas}%`; 
        } 
        const tareasProgress = statsElements[2].querySelector('.text-danger, .text-success'); 
        if (tareasProgress) { 
            const retrasadas = stats.tareas_retrasadas || 0;
            const isPositive = retrasadas === 0; 
            tareasProgress.className = isPositive ? 'text-success d-flex' : 'text-danger d-flex'; 
            const icon = tareasProgress.querySelector('i'); 
            if (icon) { 
                icon.className = isPositive ? 'mdi mdi-check-circle' : 'mdi mdi-alert-circle'; 
            }
            const span = tareasProgress.querySelector('span'); 
            if (span) { 
                span.textContent = retrasadas === 0 
                    ? 'Sin retrasos' 
                    : `${retrasadas} retrasada${retrasadas > 1 ? 's' : ''}`; 
            } 
        } 
        
        // 4. Proyectos completados - mostrar: % del total
        const completadosElement = statsElements[3].querySelector('.rate-percentage'); 
        if (completadosElement) { 
            completadosElement.textContent = stats.proyectos_completados; 
        } 
        const completadosProgress = statsElements[3].querySelector('.text-danger, .text-success'); 
        if (completadosProgress) { 
            const porcentajeComp = stats.porcentaje_completados || 0;
            completadosProgress.className = 'text-success d-flex';
            const icon = completadosProgress.querySelector('i'); 
            if (icon) { 
                icon.className = 'mdi mdi-trending-up'; 
            } 
            const span = completadosProgress.querySelector('span'); 
            if (span) { 
                span.textContent = `${porcentajeComp}% del total`; 
            } 
        }

        // 6. Proyectos pendientes - mostrar: % del total
        const pendientesElement = statsElements[5].querySelector('.rate-percentage'); 
        if (pendientesElement) { 
            pendientesElement.textContent = stats.proyectos_pendientes; 
        } 
        const pendientesProgress = statsElements[5].querySelector('.text-danger, .text-success'); 
        if (pendientesProgress) { 
            const porcentajePend = stats.porcentaje_pendientes || 0;
            pendientesProgress.className = porcentajePend > 30 ? 'text-warning d-flex' : 'text-success d-flex'; 
            const icon = pendientesProgress.querySelector('i'); 
            if (icon) { 
                icon.className = porcentajePend > 30 ? 'mdi mdi-alert' : 'mdi mdi-check'; 
            } 
            const span = pendientesProgress.querySelector('span'); 
            if (span) { 
                span.textContent = `${porcentajePend}% del total`; 
            } 
        }
        
        // 7. Proyectos vencidos - mostrar: % del total
        const vencidosElement = statsElements[6].querySelector('.rate-percentage'); 
        if (vencidosElement) { 
            vencidosElement.textContent = stats.proyectos_vencidos; 
        } 
        const vencidosProgress = statsElements[6].querySelector('.text-danger, .text-success'); 
        if (vencidosProgress) { 
            const porcentajeVenc = stats.porcentaje_vencidos || 0;
            vencidosProgress.className = 'text-danger d-flex';
            const icon = vencidosProgress.querySelector('i'); 
            if (icon) { 
                icon.className = porcentajeVenc > 0 ? 'mdi mdi-alert-circle' : 'mdi mdi-check'; 
            } 
            const span = vencidosProgress.querySelector('span'); 
            if (span) { 
                span.textContent = porcentajeVenc > 0 
                    ? `${porcentajeVenc}% del total` 
                    : 'Sin retrasos'; 
            } 
        }
    } 
}

function refreshProjectsData() { 
    fetch('../php/get_projects.php') 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json(); 
        }) 

        .then(data => { 
            if (data.success && data.proyectos) { 
                // Guardar el estado actual de búsqueda 
                const searchInput = document.getElementById('searchInput'); 
                const currentSearchQuery = searchInput ? searchInput.value : ''; 
                allProjects = data.proyectos;//actualizar los datos 
                if (currentSearchQuery.trim() !== '') { 
                    //reaplicar los filtros de busqueda si existen 
                    performSearch(currentSearchQuery); 
                } else { 
                    filteredProjects = [...allProjects]; 
                } 
                if (currentSortColumn) { 
                    //reaplicar ordenamiento si existe 
                    filteredProjects = sortProjects(filteredProjects, currentSortColumn, sortDirection); 
                } 
                const newTotalPages = calculatePages(filteredProjects);//actualizar la vista manteniendo la pagina actual si es posible 
                if (currentPage > newTotalPages && newTotalPages > 0) { 
                    currentPage = newTotalPages; 
                } 
                displayProjects(filteredProjects); 
                console.log('Datos de proyectos actualizados exitosamente'); 
            } 
        }) 
        .catch(error => { 
            console.error('Error al refrescar proyectos:', error); 
            // No mostrar alert para no interrumpir al usuario 
        }); 
} 

function refreshProjectUsersData() { 
    if (!currentProjectIdForUsers) return; 
    const tableBody = document.getElementById('projectUsersTableBody'); 
    if (!tableBody) return; 
    fetch(`${Config.API_ENDPOINTS.GET_PROJECT_USERS}?id=${currentProjectIdForUsers}`) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('Error en la respuesta de red'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.usuarios) { 
                // Guardar el estado actual de búsqueda en el modal 
                const searchInput = document.getElementById('projectUsersSearch'); 
                const currentSearchQuery = searchInput ? searchInput.value : ''; 
                projectUsersData = data.usuarios;
                if (currentSearchQuery.trim() !== '') { 
                    //reaplicar filtro de busqueda si existe 
                    const filtered = projectUsersData.filter(user => { 
                        return user.nombre_completo.toLowerCase().includes(currentSearchQuery.toLowerCase()) || 
                            user.e_mail.toLowerCase().includes(currentSearchQuery.toLowerCase()) || 
                            user.num_empleado.toString().includes(currentSearchQuery); 
                    }); 
                    displayProjectUsers(filtered); 
                } else { 
                    displayProjectUsers(projectUsersData); 
                } 
                console.log('Datos de usuarios del proyecto actualizados'); 
            } 
        }) 
        .catch(error => { 
            console.error('Error al refrescar usuarios del proyecto:', error); 
        }); 
} 

function cargarProyectos() { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    if(!tableBody) { 
        console.error('El elemento de cuerpo de tabla no fue encontrado'); 
        return; 
    } 

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

    fetch('../php/get_projects.php') 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json();
        }) 
        .then(data => { 
            console.log('Informacion recivida:', data); 
            if (data.success && data.proyectos) { 
                allProjects = data.proyectos; 
                filteredProjects = [...allProjects]; 
                currentPage = 1; // Reiniciar a la primera pagina al cargar 
                displayProjects(data.proyectos);
                // Actualizar chart y contador después de cargar 
                updateProyectoCount(data.proyectos.length); 
                updateProyectoStatusChart(data.proyectos, data.proyectos.length); 
            } else { 
                tableBody.innerHTML = ` 
                    <tr> 
                        <td colspan="9" class="text-center text-danger"> 
                            <p class="mt-3">Error al cargar proyectos: ${data.message || 'Error desconocido'}</p> 
                        </td> 
                    </tr> 
                `; 
                // Actualizar chart y contador con datos vacíos 
                updateProyectoCount(0); 
                updateProyectoStatusChart([], 0); 
            } 
        }) 
        .catch(error => { 
            console.error('Error cargando proyectos:', error); 
            tableBody.innerHTML = ` 
                <tr> 
                    <td colspan="9" class="text-center text-danger"> 
                        <p class="mt-3">Error al cargar los proyectos: ${error.message}</p> 
                    </td> 
                </tr> 
            `; 
            // Actualizar chart y contador con datos vacíos 
            updateProyectoCount(0); 
            updateProyectoStatusChart([], 0); 
        }); 
} 

function setupSorting() { 
    const headers = document.querySelectorAll('th.sortable-header'); 
    headers.forEach(header => { 
        header.addEventListener('click', function() { 
            const column = this.dataset.sort;
            if (currentSortColumn === column) { 
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; 
            } else { 
                currentSortColumn = column; 
                sortDirection = 'asc'; 
            } 
            updateSortIndicators(); 
            currentPage = 1; //reiniciar a la primera pagina al hacer sort 
            const sorted = sortProjects(filteredProjects, column, sortDirection); 
            displayProjects(sorted); 
        }); 
    }); 
} 

function updateSortIndicators() { 
    const headers = document.querySelectorAll('th.sortable-header'); 
    headers.forEach(header => { 
        const icon = header.querySelector('i'); 
        if (header.dataset.sort === currentSortColumn) { 
            icon.className = sortDirection === 'asc' ? 'mdi mdi-sort-ascending' : 'mdi mdi-sort-descending'; 
            header.style.fontWeight = 'bold'; 
            header.style.color = '#0094baa'; 
        } else { 
            icon.className = 'mdi mdi-sort-variant'; 
            header.style.fontWeight = 'normal'; 
            header.style.color = 'inherit'; 
        } 
    }); 
} 

function sortProjects(projects, column, direction) { 
    const sorted = [...projects]; 
    sorted.sort((a, b) => { 
        let valueA = a[column]; 
        let valueB = b[column]; 
        if (valueA === null || valueA === undefined) valueA = ''; 
        if (valueB === null || valueB === undefined) valueB = ''; 
        if (column === 'progreso' || column === 'id_proyecto') { 
            valueA = parseInt(valueA) || 0; 
            valueB = parseInt(valueB) || 0; 
        } else if (column === 'fecha_cumplimiento') { 
            valueA = new Date(valueA).getTime() || 0; 
            valueB = new Date(valueB).getTime() || 0; 
        } else { 
            valueA = String(valueA).toLowerCase(); 
            valueB = String(valueB).toLowerCase(); 
        } 
        if (valueA < valueB) return direction === 'asc' ? -1 : 1; 
        if (valueA > valueB) return direction === 'asc' ? 1 : -1; 
        return 0; 
    }); 
    return sorted; 
} 

function setupPagination() { 
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect'); 
    if (rowsPerPageSelect) { 
        rowsPerPageSelect.addEventListener('change', function() { 
            rowsPerPage = parseInt(this.value); 
            currentPage = 1; //reiniciar a primera pagina cuano cambien registros por pagina 
            displayProjects(filteredProjects); 
        }); 
    } 
} 
 
function calculatePages(projects) { 
    return Math.ceil(projects.length / rowsPerPage); 
} 

function getPaginatedProjects(projects) { 
    const startIndex = (currentPage - 1) * rowsPerPage; 
    const endIndex = startIndex + rowsPerPage; 
    return projects.slice(startIndex, endIndex); 
} 

function changePage(pageNumber) { 
    if (pageNumber >= 1 && pageNumber <= totalPages) { 
        currentPage = pageNumber; 
        displayProjects(filteredProjects); 
    } 
} 

function updatePaginationControls() { 
    const paginationContainer = document.querySelector('.pagination-container'); 
    if (!paginationContainer) return; 
    paginationContainer.innerHTML = '';//limpiar la paginacion existente 

    //crear texto de info de paginacion 
    const infoText = document.createElement('div'); 
    infoText.className = 'pagination-info'; 
    const startItem = ((currentPage - 1) * rowsPerPage) + 1; 
    const endItem = Math.min(currentPage * rowsPerPage, filteredProjects.length); 
    infoText.innerHTML = ` 
        <p>Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${filteredProjects.length}</strong> proyectos</p> 
    `; 

    paginationContainer.appendChild(infoText); 
    const buttonContainer = document.createElement('div');//contenedor de etiquetas de botones de paginacion 
    buttonContainer.className = 'pagination-buttons'; 
    const prevBtn = document.createElement('button'); //boton anterior 
    prevBtn.className = 'btn btn-sm btn-outline-primary'; 
    prevBtn.innerHTML = '<i class="mdi mdi-chevron-left"></i> Anterior'; 
    prevBtn.disabled = currentPage === 1; 
    prevBtn.addEventListener('click', () => changePage(currentPage - 1)); 
    buttonContainer.appendChild(prevBtn); 
    const pageButtonsContainer = document.createElement('div'); //numero de paginas 
    pageButtonsContainer.className = 'page-buttons'; 
    let startPage = Math.max(1, currentPage - 2); //calculo de paginas para mostrar 
    let endPage = Math.min(totalPages, currentPage + 2); 

    if (currentPage <= 3) { 
        //ajustar dependiendo de si esta en el principio o el fin 
        endPage = Math.min(totalPages, 5); 
    } 
 
    if (currentPage > totalPages - 3) { 
        startPage = Math.max(1, totalPages - 4); 
    } 

    if (startPage > 1) {//boton de primera pagina 
        const firstBtn = document.createElement('button'); 
        firstBtn.className = 'btn btn-sm btn-outline-secondary page-btn'; 
        firstBtn.textContent = '1'; 
        firstBtn.addEventListener('click', () => changePage(1)); 
        pageButtonsContainer.appendChild(firstBtn); 
        if (startPage > 2) { 
            const ellipsis = document.createElement('span'); 
            ellipsis.className = 'pagination-ellipsis'; 
            ellipsis.textContent = '...'; 
            pageButtonsContainer.appendChild(ellipsis); 
        } 
    } 

    for (let i = startPage; i <= endPage; i++) { 
        //numero de paginas 
        const pageBtn = document.createElement('button'); 
        pageBtn.className = `btn btn-sm page-btn ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'}`; 
        pageBtn.textContent = i; 
        pageBtn.addEventListener('click', () => changePage(i)); 
        pageButtonsContainer.appendChild(pageBtn); 
    } 
 
    if (endPage < totalPages) {//boton de ultima pagina 
        if (endPage < totalPages - 1) { 
            const ellipsis = document.createElement('span'); 
            ellipsis.className = 'pagination-ellipsis'; 
            ellipsis.textContent = '...'; 
            pageButtonsContainer.appendChild(ellipsis); 
        } 

        const lastBtn = document.createElement('button'); 
        lastBtn.className = 'btn btn-sm btn-outline-secondary page-btn'; 
        lastBtn.textContent = totalPages; 
        lastBtn.addEventListener('click', () => changePage(totalPages)); 
        pageButtonsContainer.appendChild(lastBtn); 
    } 

    buttonContainer.appendChild(pageButtonsContainer); 
    const nextBtn = document.createElement('button'); //boton siguiente 
    nextBtn.className = 'btn btn-sm btn-outline-primary'; 
    nextBtn.innerHTML = 'Siguiente <i class="mdi mdi-chevron-right"></i>'; 
    nextBtn.disabled = currentPage === totalPages; 
    nextBtn.addEventListener('click', () => changePage(currentPage + 1)); 
    buttonContainer.appendChild(nextBtn); 
    paginationContainer.appendChild(buttonContainer); 
} 

function displayProjects(proyectos) { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    if(!tableBody) return; 
    totalPages = calculatePages(proyectos);//calcular paginacion 
    if (currentPage > totalPages && totalPages > 0) { 
        currentPage = totalPages; 
    } 
    const paginatedProjects = getPaginatedProjects(proyectos); //obtener los proyectos paginados 
    tableBody.innerHTML = ''; 
 
    if(!proyectos || proyectos.length === 0) { 
        displayEmptyState(); 
        updatePaginationControls(); 
        // Actualizar chart y contador con datos vacíos 
        updateProyectoCount(0); 
        updateProyectoStatusChart([], 0); 
        return; 
    } 
 
    if (paginatedProjects.length === 0) { 
        tableBody.innerHTML = ` 
            <tr> 
                <td colspan="9" class="text-center empty-state"> 
                    <i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
                    <h5 class="mt-3">No se encontraron resultados en esta página</h5> 
                </td> 
            </tr> 
        `; 
        updatePaginationControls(); 
        return; 
    } 
    paginatedProjects.forEach((project, index) => { 
        const actualIndex = ((currentPage - 1) * rowsPerPage) + index + 1; 
        const row = createProjectRow(project, actualIndex); 
        tableBody.appendChild(row); 
    }); 
 
    //actualizar controles de paginacion 
    updatePaginationControls(); 
    // Actualizar chart y contador después de mostrar proyectos 
    updateProyectoCount(proyectos.length); 
    updateProyectoStatusChart(proyectos, proyectos.length); 
} 

function createProjectRow(proyecto, index) { 
    const row = document.createElement('tr'); 
    const statusColor = getStatusColor(proyecto.estado); 
    const statusBadge = `<span class="badge badge-${statusColor}">${proyecto.estado || 'N/A'}</span>`; 
    const progressBar = createProgressBar(proyecto.progreso || 0); 
    const viewUsersButton = proyecto.id_tipo_proyecto === 1 //mostrar unicamente boton de grupo para los proyectos que sean grupales 

        ? `<button class="btn btn-sm btn-info btn-action" onclick="viewProjectUsers(${proyecto.id_proyecto}, '${escapeHtml(proyecto.nombre)}')" title="Ver usuarios asignados"> 

                  <i class="mdi mdi-account-multiple"></i> 

           </button>` 

        : ''; 

    const actionsButtons = ` 
        <div class="action-buttons"> 
            <button class="btn btn-sm btn-success btn-action" onclick="editarProyecto(${proyecto.id_proyecto})" title="Editar"> 
                <i class="mdi mdi-pencil"></i> 
            </button> 
            ${viewUsersButton} 
        </div> 
    `; 

    row.innerHTML = ` 
        <td>${index}</td> 
        <td> 
            <strong>${truncateText(proyecto.nombre, 30)}</strong> 
        </td> 
        <td>${truncateText(proyecto.descripcion, 40)}</td> 
        <td>${formatDate(proyecto.fecha_cumplimiento)}</td> 
        <td> 
            ${progressBar} 
        </td> 
        <td> 
            ${statusBadge} 
        </td> 
        <td>${proyecto.participante || '-'}</td> 
    `; 
    return row; 
} 

function createProgressBar(progress) { 
    const progressValue = parseInt(progress) || 0; 
    const progressClass = progressValue >= 75 ? 'bg-success' : 
        progressValue >= 50 ? 'bg-info' : 
        progressValue >= 25 ? 'bg-warning' : 'bg-danger'; 
    return ` 
        <div class="progress" style="height: 20px;"> 
            <div class="progress-bar ${progressClass}" role="progressbar" style="width: ${progressValue}%;"  
                 aria-valuenow="${progressValue}" aria-valuemin="0" aria-valuemax="100"> 
                ${progressValue}% 
            </div> 
        </div> 
    `; 
} 

function getStatusColor(estado) { 
    const colorMap = { 
        'pendiente': 'warning', 
        'en proceso': 'primary', 
        'vencido': 'danger', 
        'completado': 'success' 
    }; 
    return colorMap[estado?.toLowerCase()] || 'warning'; 
} 

function displayEmptyState() { 
    const tableBody = document.querySelector('#proyectosTableBody'); 
    tableBody.innerHTML = ` 
        <tr> 
            <td colspan="9" class="text-center empty-state"> 
                <i class="mdi mdi-folder-open" style="font-size: 48px; color: #e9e9e9;"></i> 
                <h5 class="mt-3">No hay proyectos registrados</h5> 
                <p>Comienza creando un nuevo proyecto</p> 
                <a href="../nuevoProyecto/" class="btn btn-success mt-3"> 
                    <i class="mdi mdi-plus-circle-outline"></i> Crear proyecto 
                </a> 
            </td> 
        </tr> 
    `; 
} 

function performSearch(query) { 
    const normalizedQuery = query.toLowerCase().trim(); 

    if (normalizedQuery === '') { 
        filteredProjects = [...allProjects]; 
        currentPage = 1; //reiniciar a la primer pagina cuando se limpie la busqueda 
        const sorted = currentSortColumn ? 
            sortProjects(filteredProjects, currentSortColumn, sortDirection) : 
            filteredProjects; 
        displayProjects(sorted); 
        return; 
    } 

    const filtered = allProjects.filter(project => { 
        return project.nombre.toLowerCase().includes(normalizedQuery) || 
            (project.descripcion && project.descripcion.toLowerCase().includes(normalizedQuery)) || 
            (project.area && project.area.toLowerCase().includes(normalizedQuery)) || 
            (project.participante && project.participante.toLowerCase().includes(normalizedQuery)); 
    }); 

    filteredProjects = filtered; 
    currentPage = 1; //reiniciar a primer pagina cuando se busca 

    const sorted = currentSortColumn ? 
        sortProjects(filteredProjects, currentSortColumn, sortDirection) : 
        filteredProjects;
    displayProjects(sorted); 
 
    if (sorted.length === 0) { 
        const tableBody = document.querySelector('#proyectosTableBody'); 
        tableBody.innerHTML = ` 
            <tr> 
                <td colspan="9" class="text-center empty-state"> 
                    <i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
                    <h5 class="mt-3">No se encontraron resultados</h5> 
                    <p>No hay proyectos que coincidan con "${escapeHtml(query)}"</p> 
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

function createUserProgressBar(progress) { 
    const progressValue = parseInt(progress) || 0; 
    const progressClass = progressValue >= 75 ? 'bg-success' : 
        progressValue >= 50 ? 'bg-info' : 
        progressValue >= 25 ? 'bg-warning' : 'bg-danger'; 
    return ` 
        <div class="d-flex align-items-center gap-2"> 
            <div class="progress flex-grow-1" style="height: 20px; min-width: 100px;"> 
                <div class="progress-bar ${progressClass}" role="progressbar" style="width: ${progressValue}%;"  
                     aria-valuenow="${progressValue}" aria-valuemin="0" aria-valuemax="100"> 
                    ${progressValue.toFixed(1)}% 
                </div> 
            </div> 
        </div> 
    `; 
} 

function showSuccessAlert(message) { 
    showAlert(message, 'success'); 
} 

function showErrorAlert(message) { 
    showAlert(message, 'danger'); 
} 

function showAlert(message, type) { 
    const alertDiv = document.getElementById('alertMessage'); 
    if (!alertDiv) { 
        console.warn('Alert div not found'); 
        return; 
    } 

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

function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) { 
    const modal = document.getElementById('customConfirmModal'); 
    if (!modal) { 
        console.error('Modal #customConfirmModal not found in DOM'); 
        return; 
    } 

    const titleElement = modal.querySelector('#confirmTitle'); 
    const messageElement = modal.querySelector('#confirmMessage'); 
    const headerElement = modal.querySelector('.modal-header'); 
    const confirmBtn = modal.querySelector('#confirmOkBtn'); 
    const cancelBtn = modal.querySelector('#confirmCancelBtn'); 

    if (!titleElement || !messageElement || !headerElement || !confirmBtn) {//validar todos los elementos 
        console.error('Critical modal elements not found'); 
        console.log({ titleElement, messageElement, headerElement, confirmBtn }); 
        return; 
    } 

    const config = { 
        confirmText: 'Aceptar', 
        cancelText: 'Cancelar', 
        type: 'warning', 
        ...options 
    }; 

    titleElement.textContent = title; 
    messageElement.innerHTML = message.replace(/\n/g, '<br>'); //actualizar contenido de texto 
    confirmBtn.textContent = config.confirmText; 
    if (cancelBtn) { 
        cancelBtn.textContent = config.cancelText; 
    } 

    headerElement.className = 'modal-header';//reiniciar manejo de clase 

    const iconMap = { 
        'info': { icon: 'mdi-information-outline', class: 'bg-info text-white', btnClass: 'btn-info' }, 
        'warning': { icon: 'mdi-alert-outline', class: 'bg-warning text-white', btnClass: 'btn-warning' }, 
        'danger': { icon: 'mdi-alert-octagon-outline', class: 'bg-danger text-white', btnClass: 'btn-danger' }, 
        'success': { icon: 'mdi-check-circle-outline', class: 'bg-success text-white', btnClass: 'btn-success' } 
    }; 

    const typeConfig = iconMap[config.type] || iconMap['warning']; 
    let iconElement = modal.querySelector('.modal-title i');//actualizar icono 
    if (!iconElement) { 
        iconElement = document.createElement('i');//si no existe, crear el icono 
        titleElement.insertBefore(iconElement, titleElement.firstChild); 
    } 

    iconElement.className = `mdi ${typeConfig.icon} me-2`; 
    headerElement.classList.remove('bg-info', 'bg-warning', 'bg-danger', 'bg-success', 'text-white');//actualizar estilos 
    headerElement.classList.add(...typeConfig.class.split(' ')); 
    confirmBtn.className = `btn ${typeConfig.btnClass}`; 
    const newConfirmBtn = confirmBtn.cloneNode(true); 
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn); 
    newConfirmBtn.addEventListener('click', function(e) { 
        e.preventDefault(); 
        e.stopPropagation(); 

        try { 
            const modalInstance = bootstrap.Modal.getInstance(modal); 
            if (modalInstance) { 
                modalInstance.hide(); 
            } 
        } catch (err) { 
            console.error('Error hiding modal:', err); 
        } 
        if (onConfirm && typeof onConfirm === 'function') { 
            onConfirm(); 
        } 
    }, { once: true }); //opcion de una vez para remover despues 
    let modalInstance = bootstrap.Modal.getInstance(modal);//obtener o crear la instancia del modal 
    if (modalInstance) { 
        modalInstance.dispose(); 
    } 

    try { 
        const confirmModal = new bootstrap.Modal(modal, { 
            backdrop: 'static', 
            keyboard: false 
        }); 
        confirmModal.show(); 
    } catch (err) { 
        console.error('Error showing modal:', err); 
    } 
} 

function updateProyectoStatusChart(proyectos, total) { 
    // Verificar si el chart existe 
    if (!window.doughnutChart) { 
        console.warn('Doughnut chart not initialized yet'); 
        return; 
    } 

    // Contar proyectos por estado 
    const statusCounts = { 
        'pendiente': 0, 
        'completado': 0, 
        'vencido': 0, 
        'en proceso': 0 
    }; 

    // Si no hay proyectos, establecer todos en 0 

    if (!proyectos || proyectos.length === 0) { 

        window.doughnutChart.data.datasets[0].data = [0, 0, 0, 0]; 
        window.doughnutChart.update(); 
        const legendElement = document.getElementById('doughnut-chart-legend'); 
        if (legendElement) { 
            legendElement.innerHTML = window.doughnutChart.generateLegend(); 
        } 
        return; 
    } 

    // Contar proyectos por estado 
    proyectos.forEach(function(proyecto) { 
        const estado = proyecto.estado.toLowerCase().trim(); 
        if (statusCounts.hasOwnProperty(estado)) { 
            statusCounts[estado]++; 
        } 
    }); 

    // Actualizar los datos del chart en el orden correcto: pendientes, completados, vencidos, en proceso 

    window.doughnutChart.data.datasets[0].data = [ 
        statusCounts['pendiente'], 
        statusCounts['completado'], 
        statusCounts['vencido'], 
        statusCounts['en proceso'] 
    ]; 

    // Actualizar el chart con animacion 
    window.doughnutChart.update(); 

    // Actualizar la leyenda 
    const legendElement = document.getElementById('doughnut-chart-legend'); 
    if (legendElement) { 
        legendElement.innerHTML = window.doughnutChart.generateLegend(); 
    } 
 
    console.log('Chart updated:', { 
        pendientes: statusCounts['pendiente'], 
        completados: statusCounts['completado'], 
        vencidos: statusCounts['vencido'], 
        enProgreso: statusCounts['en proceso'], 
        total: total 
    }); 
} 

function updateProyectoCount(count) { 
    // Buscar el elemento del contador (puede ser clase card-subtitle-dash o similar) 
    const subtitle = document.querySelector('p.card-subtitle-dash'); 
    if (subtitle) { 
        const plural = count === 1 ? 'proyecto' : 'proyectos'; 
        subtitle.textContent = 'Tienes ' + count + ' ' + plural; 
    } 
} 

function loadTopEmployeesProgress() {//cargar top5de empleados por progreso
    fetch('../php/get_top_employees_progress.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayTopEmployeesProgress(data.empleados);
                console.log('Top empleados cargados:', data.empleados);
            } else {
                console.warn('Aviso al cargar empleados:', data.message);
                displayEmptyEmployeesState();
            }
        })
        .catch(error => {
            console.error('Error al cargar empleados top:', error);
            displayEmptyEmployeesState();
        });
}

function displayTopEmployeesProgress(empleados) {//mostrar top empleados en las tablas
    const tableBody = document.querySelector('#topEmployeesTableBody');
    
    if (!tableBody) {
        console.warn('Elemento #topEmployeesTableBody no encontrado');
        return;
    }

    // Limpiar tabla
    tableBody.innerHTML = '';

    if (!empleados || empleados.length === 0) {
        displayEmptyEmployeesState();
        return;
    }

    // Crear filas para cada empleado
    empleados.forEach((empleado, index) => {
        const row = document.createElement('tr');
        const progressBar = createProgressBarForEmployee(empleado.progreso);
        
        row.innerHTML = `
            <td>
                <strong>${index + 1}</strong>
            </td>
            <td>
                <strong>${escapeHtml(empleado.nombre_completo)}</strong>
                <br>
                <small class="text-muted">#${empleado.num_empleado}</small>
            </td>
            <td>
                ${progressBar}
                <small class="text-muted d-block mt-1">
                    ${empleado.tareas_completadas}/${empleado.total_tareas} tareas
                </small>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

function createProgressBarForEmployee(progress) {
    const progressValue = parseFloat(progress) || 0;
    const progressClass = progressValue >= 75 ? 'bg-success' : 
                         progressValue >= 50 ? 'bg-info' : 
                         progressValue >= 25 ? 'bg-warning' : 'bg-danger';
    
    return `
        <div class="progress" style="height: 20px;">
            <div class="progress-bar ${progressClass}" 
                 role="progressbar" 
                 style="width: ${progressValue}%;"  
                 aria-valuenow="${progressValue}" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                ${progressValue.toFixed(1)}%
            </div>
        </div>
    `;
}

function displayEmptyEmployeesState() {
    const tableBody = document.querySelector('#topEmployeesTableBody');
    
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="3" class="text-center text-muted py-4">
                <i class="mdi mdi-account-off" style="font-size: 32px; opacity: 0.5;"></i>
                <p class="mt-2">No hay empleados con tareas asignadas</p>
            </td>
        </tr>
    `;
}

function loadTopProjectsProgress() {
    fetch('../php/get_top_projects_progress.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta de red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayTopProjectsProgress(data.proyectos);
                console.log('Top proyectos cargados:', data.proyectos);
            } else {
                console.warn('Aviso al cargar proyectos:', data.message);
                displayEmptyProjectsState();
            }
        })
        .catch(error => {
            console.error('Error al cargar proyectos top:', error);
            displayEmptyProjectsState();
        });
}

function displayTopProjectsProgress(proyectos) {
    const tableBody = document.querySelector('#topProjectsTableBody');
    
    if (!tableBody) {
        console.warn('Elemento #topProjectsTableBody no encontrado');
        return;
    }

    // Limpiar tabla
    tableBody.innerHTML = '';

    if (!proyectos || proyectos.length === 0) {
        displayEmptyProjectsState();
        return;
    }

    // Crear filas para cada proyecto
    proyectos.forEach((proyecto, index) => {
        const row = document.createElement('tr');
        const progressBar = createProgressBarForProject(proyecto.progreso);
        
        row.innerHTML = `
            <td>
                <strong>${index + 1}</strong>
            </td>
            <td>
                <strong>${escapeHtml(proyecto.nombre)}</strong>
                <br>
                <small class="text-muted">${proyecto.estado}</small>
            </td>
            <td>
                ${progressBar}
                <small class="text-muted d-block mt-1">
                    ${proyecto.tareas_completadas}/${proyecto.total_tareas} tareas
                </small>
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

function createProgressBarForProject(progress) {
    const progressValue = parseFloat(progress) || 0;
    const progressClass = progressValue >= 75 ? 'bg-success' : 
                         progressValue >= 50 ? 'bg-info' : 
                         progressValue >= 25 ? 'bg-warning' : 'bg-danger';
    
    return `
        <div class="progress" style="height: 20px;">
            <div class="progress-bar ${progressClass}" 
                 role="progressbar" 
                 style="width: ${progressValue}%;"  
                 aria-valuenow="${progressValue}" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                ${progressValue.toFixed(1)}%
            </div>
        </div>
    `;
}

function displayEmptyProjectsState() {
    const tableBody = document.querySelector('#topProjectsTableBody');
    
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="3" class="text-center text-muted py-4">
                <i class="mdi mdi-folder-off" style="font-size: 32px; opacity: 0.5;"></i>
                <p class="mt-2">No hay proyectos en progreso</p>
            </td>
        </tr>
    `;
}

//hacer funciones globalmente disponibles 
window.changePage = changePage; 
window.showConfirm = showConfirm; 
window.stopAutoRefresh = stopAutoRefresh; // Exportar por si se necesita detener manualmente 
window.startAutoRefresh = startAutoRefresh; // Exportar por si se necesita reiniciar manualmente 