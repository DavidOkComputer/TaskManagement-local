const Config = { 
    API_ENDPOINTS: {  
        DELETE: '../php/delete_users.php',
        GET_DEPARTMENTS: '../php/get_departments.php',
        GET_USERS: '../php/get_users.php'
    } 
}; 

const AUTO_REFRESH_CONFIG = {
    USERS_INTERVAL: 60000,      // minuto - refrescar lista de usuarios y progreso
    MODAL_INTERVAL: 60000,      // 1minuto - refrescar el modal de proyectos cuando este abierto
    DEBUG: true
};

let allUsuarios = []; //guardar todos los usuarios para filtrar posteriormente
let filteredUsuarios = [];
let allDepartamentos = []; //guardar todos los departamentos
let allUsersData=[];//guardat todos los usuarios con su informacion de proyecto
let usersProgressCache=[];
let currentSortColumn = null;
let sortDirection = 'asc';
let currentPage = 1;
let rowsPerPage = 10;
let totalPages = 0;
let projectUsersData = [];
//variables de refresco automatico
let autoRefreshInterval = null;
let modalRefreshInterval = null;
let currentUserIdForProject = null;

document.addEventListener('DOMContentLoaded', function() {
    // inicializar
    loadDepartamentos();
    loadUsuarios();
    startAutoRefresh();

    const searchInput = document.getElementById('searchUser');
    if (searchInput) {
        searchInput.addEventListener('input', filterUsuarios);
    }
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }
    
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', handleSaveUserChanges);
    }
    
    const saveUserChanges = document.getElementById('saveUserChanges');
    if (saveUserChanges) {
        saveUserChanges.addEventListener('click', handleSaveUserChanges);
    }

    setupSorting();
    setupPagination();
    setupModalEventListeners();
});

createCustomDialogSystem();

// listeners para modal de eventos de abrir o cerrar
function setupModalEventListeners() {
    const modal = document.getElementById('viewProjectsModal');
    if (!modal) return;
    
    modal.addEventListener('show.bs.modal', function() {
        if (AUTO_REFRESH_CONFIG.DEBUG) {
        }
    });
    
    modal.addEventListener('hide.bs.modal', function() {
        if (AUTO_REFRESH_CONFIG.DEBUG) {
        }
        currentUserIdForProject = null;
    });
}

function startAutoRefresh(){
    if(autoRefreshInterval){
        clearInterval(autoRefreshInterval);
    }
    
    //auto refrescar info de usuarios y progreso
    autoRefreshInterval = setInterval(() => {
        if(AUTO_REFRESH_CONFIG.DEBUG) {
        }
        refreshUserData();
    }, AUTO_REFRESH_CONFIG.USERS_INTERVAL);
    
    //separar intervalo para modal de proyectos cuando se abra
    startModalAutoRefresh();
    
    if(AUTO_REFRESH_CONFIG.DEBUG) {
    }
}

function startModalAutoRefresh(){
    if(modalRefreshInterval){
        clearInterval(modalRefreshInterval);
    }
    
    //revisar cada tanto tiempo si el modal esta abierto y refrescar si es necesario
    modalRefreshInterval = setInterval(() => {
        const modal = document.getElementById('viewProjectsModal');
        if(modal && modal.classList.contains('show')) {
            if(currentUserIdForProject) {
                if(AUTO_REFRESH_CONFIG.DEBUG) {
                }
                refreshUserProjectData();
            }
        }
    }, AUTO_REFRESH_CONFIG.MODAL_INTERVAL);
}

function stopAutoRefresh(){
    if(autoRefreshInterval){
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    if(modalRefreshInterval){
        clearInterval(modalRefreshInterval);
        modalRefreshInterval = null;
    }
}

function refreshUserData(){
    fetch('../php/get_users.php')
    .then(response =>{
        if(!response.ok){
            throw new Error('La respuesta de red no fue ok');
        }
        return response.json();
    })
    .then(data =>{
        if(data.success && data.usuarios){
            //guardar el estado actual de la busqueda
            const searchInput = document.getElementById('searchUser');
            const currentSearchQuery = searchInput ? searchInput.value:'';
            
            Promise.all(
                data.usuarios.map(async usuario => {
                    const progress = await calculateUserProgress(usuario.id_usuario);
                    usersProgressCache[usuario.id_usuario] = progress;
                    return { ...usuario, ...progress };
                })
            ).then(usersWithProgress => {
                allUsuarios = usersWithProgress;
                
                if(currentSearchQuery.trim()!==''){//reaplicar los filtros de busqueda si existen
                    performSearch(currentSearchQuery);
                } else{
                    filteredUsuarios = [...allUsuarios];
                }
                
                if(currentSortColumn){//reaplicar ordenamiento si existe
                    filteredUsuarios = sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection);
                }
                
                const newTotalPages = calculatePages(filteredUsuarios);//actualizar la vista manteniendo la pagina actual si es posible
                if(currentPage > newTotalPages && newTotalPages > 0){
                    currentPage = newTotalPages;
                }
                
                displayUsuarios(filteredUsuarios);
            });
        }
    })
    .catch(error =>{
        console.error('Error al refrescar usuarios:', error);
        //no mostrar alerta para no interrumpir al usuario
    });
}

async function refreshUserProjectData(){
    if(!currentUserIdForProject) return;
    
    try {
        const projects = await fetchUserProjects(currentUserIdForProject);
        
        if(projects.length === 0) {
            if(AUTO_REFRESH_CONFIG.DEBUG) {
                
            }
            return;
        }
        
        updateProjectsModal(projects);//actualizar contenido del modal con nueva informacion del proyecto
        
    } catch(error) {
        console.error('Error al refrescar proyectos en modal:', error);
    }
}

function updateProjectsModal(projects) {//actualizar modal de proyectos con la nueva info
    if(projects.length === 0) {
        document.getElementById('projectsContainer').style.display = 'none';
        document.getElementById('noProjects').style.display = 'block';
        return;
    }
    
    let totalTasks = 0;//calcular nuevas estadisticas
    let completedTasks = 0;
    let totalProgress = 0;
    
    projects.forEach(project => {
        totalTasks += project.tareas_totales;
        completedTasks += project.tareas_completadas;
        totalProgress += project.progreso;
    });
    
    const avgProgress = projects.length > 0 ? totalProgress / projects.length : 0;
    
    document.getElementById('totalProjects').textContent = projects.length;//actuializar estadisticas
    document.getElementById('totalTasks').textContent = totalTasks;
    document.getElementById('avgProgress').textContent = avgProgress.toFixed(1) + '%';
    
    const projectsList = document.getElementById('projectsList');//actualizar lista de proyectos 
    const newHTML = projects.map(project => `
        <div class="card mb-3 project-card-update">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-1 fw-bold">${escapeHtml(project.nombre)}</h6>
                        <p class="text-muted mb-2 small">${escapeHtml(project.descripcion || 'Sin descripción')}</p>
                    </div>
                    <span class="badge ${getStatusBadgeClass(project.estado)}">${project.estado}</span>
                </div>
                <div class="row mb-2">
                    <div class="col-6">
                        <small class="text-muted">
                            <i class="mdi mdi-view-grid"></i> Área: ${escapeHtml(project.area || 'N/A')}
                        </small>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">
                            <i class="mdi mdi-calendar"></i> ${formatDate(project.fecha_cumplimiento)}
                        </small>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Progreso: ${project.progreso_porcentaje}%</small>
                        <small class="text-muted">${project.tareas_completadas}/${project.tareas_totales} tareas</small>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar ${getProgressBarClass(project.progreso)}"
                             role="progressbar"
                             style="width: ${project.progreso}%; transition: width 0.5s ease;"
                             aria-valuenow="${project.progreso}"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    projectsList.innerHTML = newHTML;
}

// Cargar departamentos para el dropdown
function loadDepartamentos() {
    
    fetch(Config.API_ENDPOINTS.GET_DEPARTMENTS, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.departamentos) {
            allDepartamentos = data.departamentos;
            populateDepartamentosDropdown();
        } else {
            const errorMsg = data.message || 'Error desconocido';
            showError('Error al cargar departamentos: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('Error de conexión en loadDepartamentos:', error);
        showError('Error de conexión: ' + error.message, error);
    });
}

// Poblar el dropdown de departamentos
function populateDepartamentosDropdown() {
    const dropdown = document.getElementById('editDepartamento');
    if (!dropdown) {
        console.warn('Dropdown de departamentos no encontrado');
        return;
    }
    
    // Limpiar opciones excepto la primera
    dropdown.innerHTML = '<option value="">-- Seleccionar departamento --</option>';
    
    // Agregar departamentos
    allDepartamentos.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept.id_departamento;
        option.textContent = dept.nombre;
        option.dataset.nombre = dept.nombre;
        dropdown.appendChild(option);
    });
}

async function loadUsuarios() { 
    const tableBody = document.getElementById('usuariosTableBody'); 
    try { 
        const response = await fetch('../php/get_users.php', { 
            method: 'GET', 
            headers: { 
                'Content-Type': 'application/json' 
            } 
        }); 

        if (!response.ok) { 
            throw new Error(`HTTP error! status: ${response.status}`); 
        } 
        const data = await response.json(); 
        if (data.success && data.usuarios) { 
            allUsuarios = data.usuarios; 
            // Calcular progreso para TODOS los usuarios 
            const usersWithProgress = await Promise.all( 
                allUsuarios.map(async usuario => { 
                    const progress = await calculateUserProgress(usuario.id_usuario); 
                    // Guardar en cache 
                    usersProgressCache[usuario.id_usuario] = progress; 
                    return { ...usuario, ...progress }; 
                }) 
            ); 
            allUsuarios = usersWithProgress; 
            filteredUsuarios = [...allUsuarios];  
            currentPage = 1; 
            console.table(data.usuarios); 
            displayUsuarios(allUsuarios);  
        } else { 
            const errorMsg = data.message || 'Error desconocido'; 
            showError('Error al cargar usuarios: ' + errorMsg); 
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar usuarios</td></tr>'; 
        } 
    } catch (error) { 
        console.error('Error de conexión en loadUsuarios:', error); 
        showError('Error de conexión: ' + error.message, error); 
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión</td></tr>'; 
    } 
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
            currentPage = 1; 
            const sorted = sortUsuarios(filteredUsuarios, column, sortDirection);
            displayUsuarios(sorted);
        });
    });
}

function updateSortIndicators() {
    const headers = document.querySelectorAll('th.sortable-header');
    headers.forEach(header => {
        const icon = header.querySelector('i');
        if (header.dataset.sort === currentSortColumn) {
            icon.className = sortDirection === 'asc' 
                ? 'mdi mdi-sort-ascending' 
                : 'mdi mdi-sort-descending';
            header.style.fontWeight = 'bold';
            header.style.color = '#007bff';
        } else {
            icon.className = 'mdi mdi-sort-variant';
            header.style.fontWeight = 'normal';
            header.style.color = 'inherit';
        }
    });
}

function sortUsuarios(usuarios, column, direction) { 
    const sorted = [...usuarios]; 
    sorted.sort((a, b) => { 
        let valueA, valueB; 
        if (column === 'departamento') {     
            valueA = getDepartamentoName(a.id_departamento); 
            valueB = getDepartamentoName(b.id_departamento); 
        } else if (column === 'superior') { 
            valueA = getSuperiorName(a.id_superior); 
            valueB = getSuperiorName(b.id_superior); 
        } else if (column === 'nombre') { 
            valueA = `${a.nombre} ${a.apellido}`; 
            valueB = `${b.nombre} ${b.apellido}`; 
        } else if (column === 'rol') { 
            valueA = getRolText(a.id_rol); 
            valueB = getRolText(b.id_rol); 
        } else if (column === 'progreso') { 
            valueA = a.avgProgress || 0; //ordenar por progreso
            valueB = b.avgProgress || 0; 
            // Para números, comparar directamente 
            if (direction === 'asc') { 
                return valueA - valueB; 
            } else { 
                return valueB - valueA; 
            } 
        } else { 
            valueA = a[column]; 
            valueB = b[column]; 
        } 
        if (valueA === null || valueA === undefined) valueA = ''; 
        if (valueB === null || valueB === undefined) valueB = ''; 
        valueA = String(valueA).toLowerCase(); 
        valueB = String(valueB).toLowerCase(); 
        if (valueA < valueB) return direction === 'asc' ? -1 : 1; 
        if (valueA > valueB) return direction === 'asc' ? 1 : -1; 
        return 0; 
    }); 
    return sorted; 
} 

function getRolText(roleId) {
    const rolMap = {
        1: 'Administrador',
        2: 'Gerente',
        3: 'Usuario',
        4: 'Practicante'
    };
    return rolMap[roleId] || 'Sin rol';
}

function setupPagination() {
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = parseInt(this.value);
            currentPage = 1; //reiniciar a la primer pagina cuando cargan las filas por pagina
            displayUsuarios(filteredUsuarios);
        });
    }
}

function calculatePages(usuarios) {
    return Math.ceil(usuarios.length / rowsPerPage);
}

function getPaginatedUsuarios(usuarios) {
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    return usuarios.slice(startIndex, endIndex);
}

function changePage(pageNumber) {
    if (pageNumber >= 1 && pageNumber <= totalPages) {
        currentPage = pageNumber;
        displayUsuarios(filteredUsuarios);
    }
}

function updatePaginationControls() {
    const paginationContainer = document.querySelector('.pagination-container');
    if (!paginationContainer) return;

    paginationContainer.innerHTML = '';// limpiar paginacion existente

    const infoText = document.createElement('div');// crear texto de info de paginacion
    infoText.className = 'pagination-info';
    const startItem = ((currentPage - 1) * rowsPerPage) + 1;
    const endItem = Math.min(currentPage * rowsPerPage, filteredUsuarios.length);
    infoText.innerHTML = `
        <p>Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${filteredUsuarios.length}</strong> empleados</p>
    `;
    paginationContainer.appendChild(infoText);

    const buttonContainer = document.createElement('div'); //crear contenedor de boton
    buttonContainer.className = 'pagination-buttons';

    const prevBtn = document.createElement('button'); //boton d eanterior
    prevBtn.className = 'btn btn-sm btn-outline-primary';
    prevBtn.innerHTML = '<i class="mdi mdi-chevron-left"></i> Anterior';
    prevBtn.disabled = currentPage === 1;
    prevBtn.addEventListener('click', () => changePage(currentPage - 1));
    buttonContainer.appendChild(prevBtn);

    const pageButtonsContainer = document.createElement('div');//contenedor de botones de pagina
    pageButtonsContainer.className = 'page-buttons';

    let startPage = Math.max(1, currentPage - 2);//calcular que pagina mostrar
    let endPage = Math.min(totalPages, currentPage + 2);

    if (currentPage <= 3) {//ajustar dependiendo de si esta al principio o al fin
        endPage = Math.min(totalPages, 5);
    }
    if (currentPage > totalPages - 3) {
        startPage = Math.max(1, totalPages - 4);
    }

    if (startPage > 1) {//boton de primer pagina
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

    for (let i = startPage; i <= endPage; i++) {//botones de numero d epaginas
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

    const nextBtn = document.createElement('button');//proximo boton
    nextBtn.className = 'btn btn-sm btn-outline-primary';
    nextBtn.innerHTML = 'Siguiente <i class="mdi mdi-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.addEventListener('click', () => changePage(currentPage + 1));
    buttonContainer.appendChild(nextBtn);

    paginationContainer.appendChild(buttonContainer);
}

async function fetchUserProjects(userId) { 
    try { 
        const response = await fetch(`../php/get_user_projects.php?id_usuario=${userId}`); 
        const data = await response.json(); 
        if (data.success) { 
            return data.proyectos; 
        } else { 
            throw new Error(data.message || 'Error al cargar proyectos'); 
        } 
    } catch (error) { 
        console.error('Error fetching user projects:', error); 
        return [];
    } 
} 

async function calculateUserProgress(userId) { 
    const projects = await fetchUserProjects(userId); 
    if (projects.length === 0) { 
        return { 
            avgProgress: 0, 
            totalProjects: 0, 
            totalTasks: 0, 
            completedTasks: 0 
        }; 
    } 
    let totalProgress = 0; 
    let totalTasks = 0; 
    let completedTasks = 0; 
    projects.forEach(project => { 
        totalProgress += project.progreso; 
        totalTasks += project.tareas_totales; 
        completedTasks += project.tareas_completadas; 
    }); 
    return { 
        avgProgress: projects.length > 0 ? totalProgress / projects.length : 0, 
        totalProjects: projects.length, 
        totalTasks: totalTasks, 
        completedTasks: completedTasks 
    }; 
} 

async function showUserProjects(userId, userName, userEmail) { 
    const modal = new bootstrap.Modal(document.getElementById('viewProjectsModal')); 
    document.getElementById('employeeName').textContent = userName; //informacion de empleado
    document.getElementById('employeeEmail').textContent = userEmail; 
    document.getElementById('projectsLoading').style.display = 'block'; //mostrar estado de carga
    document.getElementById('projectsContainer').style.display = 'none'; 
    document.getElementById('noProjects').style.display = 'none'; 
    
    currentUserIdForProject = userId; //configurar usuario actual para la actualizacion automatica
    
    modal.show(); 
    const projects = await fetchUserProjects(userId); //obtener proyectos
    document.getElementById('projectsLoading').style.display = 'none'; //ocular carga
    if (projects.length === 0) { 
        document.getElementById('noProjects').style.display = 'block'; 
        return; 
    } 

    //mostrar contendor de proyectos
    document.getElementById('projectsContainer').style.display = 'block'; 

    let totalTasks = 0; //calcular y mostrar resumen de estadisticas 
    let completedTasks = 0; 
    let totalProgress = 0; 
    projects.forEach(project => { 
        totalTasks += project.tareas_totales; 
        completedTasks += project.tareas_completadas; 
        totalProgress += project.progreso; 
    }); 

    const avgProgress = projects.length > 0 ? totalProgress / projects.length : 0; 
    document.getElementById('totalProjects').textContent = projects.length; 
    document.getElementById('totalTasks').textContent = totalTasks; 
    document.getElementById('avgProgress').textContent = avgProgress.toFixed(1) + '%'; 
    const projectsList = document.getElementById('projectsList'); //construir lista de proyectos
    projectsList.innerHTML = projects.map(project => ` 
        <div class="card mb-3"> 
            <div class="card-body"> 
                <div class="d-flex justify-content-between align-items-start mb-2"> 
                    <div> 
                        <h6 class="mb-1 fw-bold">${escapeHtml(project.nombre)}</h6> 
                        <p class="text-muted mb-2 small">${escapeHtml(project.descripcion || 'Sin descripción')}</p> 
                    </div> 
                    <span class="badge ${getStatusBadgeClass(project.estado)}">${project.estado}</span> 
                </div> 
                <div class="row mb-2"> 
                    <div class="col-6"> 
                        <small class="text-muted"> 
                            <i class="mdi mdi-view-grid"></i> Área: ${escapeHtml(project.area || 'N/A')} 
                        </small> 
                    </div> 
                    <div class="col-6"> 
                        <small class="text-muted"> 
                            <i class="mdi mdi-calendar"></i> ${formatDate(project.fecha_cumplimiento)} 
                        </small> 
                    </div> 
                </div> 
                <div class="mb-2"> 
                    <div class="d-flex justify-content-between mb-1"> 
                        <small class="text-muted">Progreso: ${project.progreso_porcentaje}%</small> 
                        <small class="text-muted">${project.tareas_completadas}/${project.tareas_totales} tareas</small> 
                    </div> 
                    <div class="progress" style="height: 10px;"> 
                        <div class="progress-bar ${getProgressBarClass(project.progreso)}"  
                             role="progressbar"  
                             style="width: ${project.progreso}%; transition: width 0.5s ease;" 
                             aria-valuenow="${project.progreso}"  
                             aria-valuemin="0"  
                             aria-valuemax="100"> 
                        </div> 
                    </div> 
                </div> 
            </div> 
        </div> 
    `).join(''); 
} 

function getStatusBadgeClass(status) { 
    const statusMap = { 
        'pendiente': 'bg-warning', 
        'en_progreso': 'bg-info', 
        'completado': 'bg-success', 
        'cancelado': 'bg-danger' 
    }; 
    return statusMap[status] || 'bg-secondary'; 
} 

function getProgressBarClass(progress) { 
    if (progress >= 75) return 'bg-success'; 
    if (progress >= 50) return 'bg-info'; 
    if (progress >= 25) return 'bg-warning'; 
    return 'bg-danger'; 
} 

function formatDate(dateString) { 
    if (!dateString) return 'N/A'; 
    const date = new Date(dateString); 
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' }); 
} 

async function displayUsuarios(usuarios) { 
    const tableBody = document.getElementById('usuariosTableBody'); 
    if (!tableBody) return; 
    totalPages = calculatePages(usuarios); 
    if (currentPage > totalPages && totalPages > 0) { 
        currentPage = totalPages; 
    } 
    const paginatedUsuarios = getPaginatedUsuarios(usuarios); 
    if (!usuarios || usuarios.length === 0) { 
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No hay usuarios registrados</td></tr>'; 
        updatePaginationControls(); 
        return; 
    } 
    if (paginatedUsuarios.length === 0) { 
        tableBody.innerHTML = ` 
            <tr> 
                <td colspan="6" class="text-center empty-state"> 
                    <i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i> 
                    <h5 class="mt-3">No se encontraron resultados en esta página</h5> 
                </td> 
            </tr> 
        `; 
        updatePaginationControls(); 
        return; 
    } 
    tableBody.innerHTML = ''; 
    paginatedUsuarios.forEach(usuario => { 
        const row = createUsuarioRow(usuario); 
        tableBody.appendChild(row); 
    }); 
    attachCheckboxListeners(); 
    attachButtonListeners(); 
    updatePaginationControls(); 
} 

function createUsuarioRow(usuario) {
    const tr = document.createElement('tr');
    const rolBadge = getRolBadge(usuario.id_rol);
    const nombreCompleto = `${usuario.nombre} ${usuario.apellido}`;
    
    tr.innerHTML = `
        <td>
            <div class="d-flex align-items-center">
                <img src="../images/faces/face1.jpg" alt="image" class="me-3 rounded-circle" style="width: 40px; height: 40px;">
                <div>
                    <h6 class="mb-0">${escapeHtml(nombreCompleto)}</h6>
                    <small class="text-muted">${escapeHtml(usuario.e_mail)}</small>
                </div>
            </div>
        </td>
        <td>
            <h6>${getDepartamentoName(usuario.id_departamento)}</h6>
            <p class="text-muted mb-0">${escapeHtml(usuario.usuario)}</p>
        </td>
        <td>
            <h6>${getSuperiorName(usuario.id_superior)}</h6>
        </td>
        <td>
            ${rolBadge}
        </td>
        <td>
            <div class="d-flex flex-column">
                <div class="d-flex justify-content-between mb-1">
                    <small>${usuario.avgProgress ? usuario.avgProgress.toFixed(1) : '0.0'}%</small>
                    <small>${usuario.totalProjects || 0} proyecto${usuario.totalProjects !== 1 ? 's' : ''}</small>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar ${getProgressBarClass(usuario.avgProgress || 0)}"
                         role="progressbar"
                         style="width: ${usuario.avgProgress || 0}%; transition: width 0.5s ease;"
                         aria-valuenow="${usuario.avgProgress || 0}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>
            </div>
        </td>
        <td class="action-buttons">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-info btn-view-projects"
                        data-user-id="${usuario.id_usuario}"
                        data-nombre="${escapeHtml(nombreCompleto)}"
                        data-email="${escapeHtml(usuario.e_mail)}"
                        title="Ver proyectos">
                    <i class="mdi mdi-folder-account"></i>
                </button>
                <button type="button" class="btn btn-sm btn-success btn-edit"
                        data-user-id="${usuario.id_usuario}"
                        data-nombre="${escapeHtml(usuario.nombre)}"
                        data-apellido="${escapeHtml(usuario.apellido)}"
                        data-usuario="${escapeHtml(usuario.usuario)}"
                        data-email="${escapeHtml(usuario.e_mail)}"
                        data-depart="${usuario.id_departamento}">
                    <i class="mdi mdi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger btn-delete"
                        data-user-id="${usuario.id_usuario}"
                        data-nombre="${escapeHtml(nombreCompleto)}">
                    <i class="mdi mdi-delete"></i>
                </button>
            </div>
        </td>
    `;
 
    return tr;
}

function renderUsuariosTable(usuarios) {
    displayUsuarios(usuarios);
}

function getRolBadge(roleId) {
    const rolMap = {
        1: { class: 'badge-opacity-success', text: 'Administrador' },
        2: { class: 'badge-opacity-success', text: 'Gerente' },
        3: { class: 'badge-opacity-success', text: 'Usuario' },
        4: { class: 'badge-opacity-success', text: 'Practicante' }
    };
    
    const rol = rolMap[roleId] || { class: 'badge-opacity-secondary', text: 'Sin rol' };
    return `<div class="badge ${rol.class}">${rol.text}</div>`;
}

function getDepartamentoName(deptId) {
    const dept = allDepartamentos.find(d => d.id_departamento === deptId);
    return dept ? dept.nombre : 'Departamento ' + deptId;
}

function getSuperiorName(superiorId) {
    if (!superiorId || superiorId === 0) return 'N/A';
    
    const superior = allUsuarios.find(u => u.id_usuario === superiorId);
    return superior ? `${superior.nombre} ${superior.apellido}` : 'N/A';
}

function filterUsuarios() {
    const searchInput = document.getElementById('searchUser').value.toLowerCase();
    
    if (!searchInput.trim()) {
        filteredUsuarios = [...allUsuarios]; 
        currentPage = 1; 
        const sorted = currentSortColumn
            ? sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection)
            : filteredUsuarios;
        displayUsuarios(sorted);
        return;
    }
    
    const filtered = allUsuarios.filter(usuario => {
        const fullName = `${usuario.nombre} ${usuario.apellido}`.toLowerCase();
        const email = usuario.e_mail.toLowerCase();
        const numEmpleado = usuario.num_empleado.toString();
        const username = usuario.usuario.toLowerCase();
        
        return fullName.includes(searchInput) || 
               email.includes(searchInput) || 
               numEmpleado.includes(searchInput) ||
               username.includes(searchInput);
    });
    
    filteredUsuarios = filtered; 
    currentPage = 1; 
    const sorted = currentSortColumn
        ? sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection)
        : filteredUsuarios;
    displayUsuarios(sorted);
}

function performSearch(query) {
    filterUsuarios();
}

function attachCheckboxListeners() {
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllCheckbox);
    });
}

function attachButtonListeners() {
    const editButtons = document.querySelectorAll('.btn-edit');//editar listener de botones
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const nombre = this.getAttribute('data-nombre');
            const apellido = this.getAttribute('data-apellido');
            const usuario = this.getAttribute('data-usuario');
            const email = this.getAttribute('data-email');
            const depart = this.getAttribute('data-depart');
            openEditModal(userId, nombre, apellido, usuario, email, depart);
        });
    });
 
    const deleteButtons = document.querySelectorAll('.btn-delete');//eliminar listeners de botones
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const nombre = this.getAttribute('data-nombre');
            confirmDelete(userId, nombre);
        });
    });
 
    const viewProjectsButtons = document.querySelectorAll('.btn-view-projects');//ver listeners de botones de proyecto
    viewProjectsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const nombre = this.getAttribute('data-nombre');
            const email = this.getAttribute('data-email');
            showUserProjects(userId, nombre, email);
        });
    });
}

function toggleSelectAll(event) {
    const isChecked = event.target.checked;
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
    });
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    const someChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
    
    if(selectAllCheckbox) {
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = someChecked && !allChecked;
    }
}


function openEditModal(userId, nombre, apellido, usuario, email, departId) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editApellido').value = apellido;
    document.getElementById('editUsuario').value = usuario;
    document.getElementById('editEmail').value = email;
    
    // Establecer el valor del dropdown
    const departmentDropdown = document.getElementById('editDepartamento');
    departmentDropdown.value = departId;
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function handleSaveUserChanges(event) {
    event.preventDefault();

    const validation = validateEditForm();//validar form
    if (!validation.isValid) {
        console.error('Validación fallida. Errores:', validation.errors);
        
        validation.errors.forEach(error => {//mostrar cada error
            showError(error);
        });
        return;
    }
    
    const userId = document.getElementById('editUserId').value;
    const nombre = document.getElementById('editNombre').value.trim();
    const apellido = document.getElementById('editApellido').value.trim();
    const usuario = document.getElementById('editUsuario').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    const id_departamento = parseInt(document.getElementById('editDepartamento').value);
    
    const data = {
        id_usuario: parseInt(userId),
        nombre: nombre,
        apellido: apellido,
        usuario: usuario,
        e_mail: email,
        id_departamento: id_departamento
    };

    showInfo('Guardando cambios...');
    
    fetch('../php/update_users.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(responseData => {
        if (responseData.success) {
            showSuccess('Usuario actualizado exitosamente', responseData.usuario);
            const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
            modal.hide();
            loadUsuarios(); //recargar la tabla
        } else {
            const errorMsg = responseData.message || responseData.error || 'Error desconocido';
            showError('Error al actualizar usuario: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('Error de conexión:', error);
        showError('Error de conexión: ' + error.message, error);
    });
}

function confirmDelete(id, nombre) { 
    showConfirm(
        `¿Está seguro de que desea eliminar el usuario "${escapeHtml(nombre)}"?\n\nEsta acción no se puede deshacer.`,
        function() {
            deleteUser(id);
        },
        'Confirmar eliminación',
        {
            type: 'danger',
            confirmText: 'Eliminar',
            cancelText: 'Cancelar'
        }
    );
} 

function deleteUser(id) {
    //se envia json en ves de data
    fetch(Config.API_ENDPOINTS.DELETE, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json' 
        },
        body: JSON.stringify({ id_usuario: id }) 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message || 'Usuario eliminado exitosamente');
            allUsuarios = allUsuarios.filter(u => u.id_usuario != id);
            filteredUsuarios = filteredUsuarios.filter(u => u.id_usuario != id);
            
            totalPages = calculatePages(filteredUsuarios);
            if (currentPage > totalPages && totalPages > 0) {
                currentPage = totalPages;
            }
            
            const sorted = currentSortColumn
                ? sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection)
                : filteredUsuarios;
            displayUsuarios(sorted);
        } else {
            showErrorAlert(data.message || 'Error al eliminar el usuario');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorAlert('Error al conectar con el servidor');
    });
}

function showSuccessAlert(message) { 
    showAlert(message, 'success'); 
} 

function showErrorAlert(message) { 
    showAlert(message, 'danger'); 
} 

function showAlert(message, type) { 
    const alertDiv = document.getElementById('alertMessage'); 
    if (!alertDiv) return; 
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



function showSuccess(message, data = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] hecho: ${message}`;
    
    if (data) {
    }
    
    displayNotification(message, 'success');//mostrar notificacion en estilo de tostador
}

function showError(message, error = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ERROR: ${message}`;
    
    console.error(logMessage);//detalles de error en log
    if (error) {
        console.error('Error Details:', error);
    }
    displayNotification(message, 'error');
}

function showInfo(message) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}]INFO: ${message}`;
    
    console.info(logMessage);
    displayNotification(message, 'info');
}

function showWarning(message) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}]Advertencia: ${message}`;
    
    console.warn(logMessage);
    displayNotification(message, 'advertencia');
}

function displayNotification(message, type = 'info') {
    //crear contenedor para la notificacin si no existe
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(toastContainer);
    }

    const toastId = 'toast-' + Date.now();
    const bgColor = {//elementos de la notificacion
        'success': '#34b0aa',
        'error': '#f85e53',
        'info': '#17a2b8',
        'warning': '#ffc107'
    }[type] || '#17a2b8';

    const toastHTML = `
        <div id="${toastId}" style="
            background-color: ${bgColor};
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <span>${message}</span>
            <button style="
                background: none;
                border: none;
                color: inherit;
                cursor: pointer;
                font-size: 18px;
                margin-left: auto;
                padding: 0;
            " onclick="document.getElementById('${toastId}').remove()">×</button>
        </div>
        <style>
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        </style>
    `;


    setTimeout(() => {//esconder notificacion despues de 5seg
        const toastElement = document.getElementById(toastId);
        if (toastElement) {
            toastElement.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toastElement.remove(), 300);
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

function logAction(action, details = {}) {
    const timestamp = new Date().toLocaleTimeString();
    console.group(`[${timestamp}] ${action}`);
    if (Object.keys(details).length > 0) {
    }
    console.groupEnd();
}

function validateEmail(email) {
    if (!email || email.trim() === '') {
        return { isValid: false, message: 'El email es requerido' };
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        return { isValid: false, message: 'Formato de email inválido (ej: usuario@ejemplo.com)' };
    }

    return { isValid: true, message: '' };
}

function validateTextField(value, fieldName = 'Campo', minLength = 1) {
    if (!value || value.trim() === '') {
        return { isValid: false, message: `${fieldName} es requerido` };
    }

    if (value.trim().length < minLength) {
        return { isValid: false, message: `${fieldName} debe tener al menos ${minLength} caracteres` };
    }

    if (value.trim().length > 100) {
        return { isValid: false, message: `${fieldName} no puede exceder 100 caracteres` };
    }

    return { isValid: true, message: '' };
}

function validateDepartment(departmentId) {
    if (!departmentId || departmentId === '') {
        return { isValid: false, message: 'Departamento es requerido' };
    }
    return { isValid: true, message: '' };
}

function validateEditForm() {
    const errors = [];
    const nombre = document.getElementById('editNombre').value;
    const apellido = document.getElementById('editApellido').value;
    const usuario = document.getElementById('editUsuario').value;
    const email = document.getElementById('editEmail').value;
    const departamento = document.getElementById('editDepartamento').value;

    console.group('Validando formulario de edición');

    const nombreValid = validateTextField(nombre, 'Nombre', 2);//validar nombre 
    if (!nombreValid.isValid) {
        errors.push(nombreValid.message);
        console.warn('Nombre inválido:', nombreValid.message);
    } else {
    }

    const apellidoValid = validateTextField(apellido, 'Apellido', 2);//validar apllido
    if (!apellidoValid.isValid) {
        errors.push(apellidoValid.message);
        console.warn('Apellido inválido:', apellidoValid.message);
    } else {
    }

    const usuarioValid = validateTextField(usuario, 'Usuario', 3);//validar suario
    if (!usuarioValid.isValid) {
        errors.push(usuarioValid.message);
        console.warn('Usuario inválido:', usuarioValid.message);
    } else {
    }

    const emailValid = validateEmail(email);//validar email
    if (!emailValid.isValid) {
        errors.push(emailValid.message);
        console.warn('Email inválido:', emailValid.message);
    } else {
    }

    // Validar departamento
    const deptValid = validateDepartment(departamento);
    if (!deptValid.isValid) {
        errors.push(deptValid.message);
        console.warn('Departamento inválido:', deptValid.message);
    } else {
    }

    console.groupEnd();

    return {
        isValid: errors.length === 0,
        errors: errors
    };
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

// mostrar dialogo de confirmacion de la app y no navegador
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
    messageElement.innerHTML = message.replace(/\n/g, '<br>'); 
    
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
    headerElement.classList.add(...typeConfig.class.split(' '));
    
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

window.confirmDelete = confirmDelete;
window.changePage = changePage;
window.stopAutoRefresh = stopAutoRefresh;
window.startAutoRefresh = startAutoRefresh;