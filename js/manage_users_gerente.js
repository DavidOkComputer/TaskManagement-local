const Config = { 
    API_ENDPOINTS: {  
        GET_DEPARTMENTS: '../php/get_departments.php',
        GET_USERS: '../php/get_users.php'
    } 
}; 

// AUTO-REFRESH CONFIGURATION
const AUTO_REFRESH_CONFIG = {
    USERS_INTERVAL: 30000,      // 30 seconds - refresh user list and progress
    MODAL_INTERVAL: 15000,      // 15 seconds - refresh modal projects when open
    DEBUG: true                  // Enable console logging for debugging
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

// Auto-refresh variables
let autoRefreshInterval = null;
let modalRefreshInterval = null;
let currentUserIdForProject = null;

document.addEventListener('DOMContentLoaded', function() {
    // inicializar
    console.clear();
    console.log('%cSistema de Gestión de Empleados (Vista Gerente) v2.0 - Enhanced Auto-Refresh', 'font-size: 16px; font-weight: bold; color: #28a745;');
    console.log('%c=====================================', 'color: #28a745;');
    console.log('Fecha/Hora:', new Date().toLocaleString());
    console.log('URL:', window.location.href);
    console.log('User Agent:', navigator.userAgent);
    console.log('Auto-refresh interval (users):', AUTO_REFRESH_CONFIG.USERS_INTERVAL + 'ms');
    console.log('Auto-refresh interval (modal):', AUTO_REFRESH_CONFIG.MODAL_INTERVAL + 'ms');
    console.log('%c=====================================', 'color: #28a745;');
    
    logAction('Página cargada - Inicializando sistema');
    
    loadDepartamentos(); // Cargar departamentos para el dropdown
    loadUsuarios();//cargar usuarios al cargar la pagina
    startAutoRefresh();//iniciar refresco de usuarios y progreso
    
    const searchInput = document.getElementById('searchUser');//funcionalidad de buscar
    if (searchInput) {
        searchInput.addEventListener('input', filterUsuarios);
        console.log('Búsqueda inicializada');
    }
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');//marcar todas las checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
        console.log('Checkbox "Seleccionar todos" inicializado');
    }
    
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', handleSaveUserChanges);
        console.log('Formulario de edición inicializado');
    }
    
    const saveUserChanges = document.getElementById('saveUserChanges');//guardar boton d eguardar
    if (saveUserChanges) {
        saveUserChanges.addEventListener('click', handleSaveUserChanges);
        console.log('Botón "Guardar Cambios" inicializado');
    }

    setupSorting();
    setupPagination();
    setupModalEventListeners(); // Setup modal events for auto-refresh
    
    console.log('%cSistema inicializado correctamente', 'color: #34b0aa; font-weight: bold;');
    console.log('%cConsola abierta: Presiona F12 para ver logs detallados', 'color: #17a2b8; font-style: italic;');
});

createCustomDialogSystem();

// Setup listeners for modal open/close events
function setupModalEventListeners() {
    const modal = document.getElementById('viewProjectsModal');
    if (!modal) return;
    
    modal.addEventListener('show.bs.modal', function() {
        if (AUTO_REFRESH_CONFIG.DEBUG) {
            console.log('Modal abierto - iniciando auto-refresh de proyectos');
        }
    });
    
    modal.addEventListener('hide.bs.modal', function() {
        if (AUTO_REFRESH_CONFIG.DEBUG) {
            console.log('Modal cerrado - deteniendo auto-refresh de proyectos');
        }
        currentUserIdForProject = null;
    });
}

function startAutoRefresh(){
    if(autoRefreshInterval){
        clearInterval(autoRefreshInterval);
    }
    
    // Auto-refresh user data and progress
    autoRefreshInterval = setInterval(() => {
        if(AUTO_REFRESH_CONFIG.DEBUG) {
            console.log('%cAuto-refresh: Actualizando datos de usuarios y progreso...', 'color: #17a2b8; font-weight: bold;');
        }
        refreshUserData();
    }, AUTO_REFRESH_CONFIG.USERS_INTERVAL);
    
    // Separate interval for modal projects when open
    startModalAutoRefresh();
    
    if(AUTO_REFRESH_CONFIG.DEBUG) {
        console.log('Auto-refresh iniciado. Intervalos:', AUTO_REFRESH_CONFIG);
    }
}

function startModalAutoRefresh(){
    if(modalRefreshInterval){
        clearInterval(modalRefreshInterval);
    }
    
    // Check every few seconds if modal is open, and refresh if needed
    modalRefreshInterval = setInterval(() => {
        const modal = document.getElementById('viewProjectsModal');
        if(modal && modal.classList.contains('show')) {
            if(currentUserIdForProject) {
                if(AUTO_REFRESH_CONFIG.DEBUG) {
                    console.log('%cModal abierto - refrescando datos de proyectos para usuario: ' + currentUserIdForProject, 'color: #ffc107; font-weight: bold;');
                }
                refreshUserProjectData();
            }
        }
    }, 5000);
}

function stopAutoRefresh(){
    if(autoRefreshInterval){
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        console.log('Auto-refresh de usuarios detenido');
    }
    if(modalRefreshInterval){
        clearInterval(modalRefreshInterval);
        modalRefreshInterval = null;
        console.log('Auto-refresh de modal detenido');
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
            
            // Actualizar usuarios y recalcular progreso
            logAction('Recalculando progreso de todos los usuarios...');
            
            Promise.all(
                data.usuarios.map(async usuario => {
                    const progress = await calculateUserProgress(usuario.id_usuario);
                    usersProgressCache[usuario.id_usuario] = progress;
                    return { ...usuario, ...progress };
                })
            ).then(usersWithProgress => {
                allUsuarios = usersWithProgress;
                
                if(currentSearchQuery.trim()!==''){//reaplicar los filtros de busqueda si existen
                    filterUsuarios();
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
                if(AUTO_REFRESH_CONFIG.DEBUG) {
                    console.log('✓ Datos de usuarios y progreso actualizados exitosamente', {
                        total_usuarios: allUsuarios.length,
                        usuarios_con_proyectos: allUsuarios.filter(u => u.totalProjects > 0).length
                    });
                }
            });
        }
    })
    .catch(error =>{
        console.error('Error al refrescar usuarios:', error);
        //no mostrar alerta para no interrumpir al usuario
    });
}

// NEW: Refresh project data for the modal
async function refreshUserProjectData(){
    if(!currentUserIdForProject) return;
    
    try {
        const projects = await fetchUserProjects(currentUserIdForProject);
        
        if(projects.length === 0) {
            if(AUTO_REFRESH_CONFIG.DEBUG) {
                console.log('No projects found for user:', currentUserIdForProject);
            }
            return;
        }
        
        // Update modal content with new project data
        updateProjectsModal(projects);
        
        if(AUTO_REFRESH_CONFIG.DEBUG) {
            console.log('✓ Proyectos actualizados en modal:', {
                total_proyectos: projects.length,
                total_tareas: projects.reduce((sum, p) => sum + p.tareas_totales, 0),
                tareas_completadas: projects.reduce((sum, p) => sum + p.tareas_completadas, 0)
            });
        }
    } catch(error) {
        console.error('Error al refrescar proyectos en modal:', error);
    }
}

// NEW: Update projects modal with fresh data
function updateProjectsModal(projects) {
    if(projects.length === 0) {
        document.getElementById('projectsContainer').style.display = 'none';
        document.getElementById('noProjects').style.display = 'block';
        return;
    }
    
    // Calculate new statistics
    let totalTasks = 0;
    let completedTasks = 0;
    let totalProgress = 0;
    
    projects.forEach(project => {
        totalTasks += project.tareas_totales;
        completedTasks += project.tareas_completadas;
        totalProgress += project.progreso;
    });
    
    const avgProgress = projects.length > 0 ? totalProgress / projects.length : 0;
    
    // Update statistics
    document.getElementById('totalProjects').textContent = projects.length;
    document.getElementById('totalTasks').textContent = totalTasks;
    document.getElementById('avgProgress').textContent = avgProgress.toFixed(1) + '%';
    
    // Update projects list with animated transitions
    const projectsList = document.getElementById('projectsList');
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
    logAction('Cargando departamentos del servidor');
    
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
            logAction('Departamentos cargados exitosamente', { 
                cantidad: data.departamentos.length,
                departamentos: data.departamentos.map(d => ({ id: d.id_departamento, nombre: d.nombre }))
            });
            console.log('Departamentos disponibles:', allDepartamentos);
            populateDepartamentosDropdown();
        } else {
            const errorMsg = data.message || 'Error desconocido';
            logAction('Error al cargar departamentos', { error: errorMsg });
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
    
    logAction('Dropdown de departamentos poblado', { 
        cantidad: allDepartamentos.length 
    });
}

function loadUsuarios() {
    const tableBody = document.getElementById('usuariosTableBody');
    
    logAction('Cargando usuarios del servidor');
    
    fetch('../php/get_users.php', {
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
        if (data.success && data.usuarios) {
            allUsuarios = data.usuarios;
            filteredUsuarios = [...allUsuarios]; 
            currentPage = 1;
            logAction('Usuarios cargados exitosamente', { 
                cantidad: data.usuarios.length,
                usuarios: data.usuarios.map(u => ({ id: u.id_usuario, nombre: u.nombre + ' ' + u.apellido }))
            });
            console.table(data.usuarios); //mostrar formato d etabla
            displayUsuarios(allUsuarios); 
            showSuccess(`Se cargaron ${data.usuarios.length} usuarios`);
        } else {
            const errorMsg = data.message || 'Error desconocido';
            logAction('Error al cargar usuarios', { error: errorMsg });
            showError('Error al cargar usuarios: ' + errorMsg);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar usuarios</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error de conexión en loadUsuarios:', error);
        showError('Error de conexión: ' + error.message, error);
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión</td></tr>';
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
        let valueA = a[column];
        let valueB = b[column];
        
        if (valueA === null || valueA === undefined) valueA = '';
        if (valueB === null || valueB === undefined) valueB = '';
        
        if (column === 'progreso') {
            valueA = parseFloat(a.avgProgress) || 0;
            valueB = parseFloat(b.avgProgress) || 0;
            // Para números, comparar directamente 
            if (direction === 'asc') { 
                return valueA - valueB; 
            } else { 
                return valueB - valueA; 
            }
        } else if (column === 'superior') {
            valueA = getSuperiorName(a.id_superior);
            valueB = getSuperiorName(b.id_superior);
        } else if (column === 'nombre') {
            valueA = `${a.nombre} ${a.apellido}`;
            valueB = `${b.nombre} ${b.apellido}`;
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
    
    // Set current user for auto-refresh
    currentUserIdForProject = userId;
    
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
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center">No hay usuarios registrados</td></tr>';
        updatePaginationControls();
        return;
    }
 
    if (paginatedUsuarios.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center empty-state">
                    <i class="mdi mdi-magnify" style="font-size: 48px; color: #ccc;"></i>
                    <h5 class="mt-3">No se encontraron resultados en esta página</h5>
                </td>
            </tr>
        `;
        updatePaginationControls();
        return;
    }
 
    // Calculate progress for all paginated users
    const usersWithProgress = await Promise.all(paginatedUsuarios.map(async usuario => {
        const progress = await calculateUserProgress(usuario.id_usuario);
        return { ...usuario, ...progress };
    }));
 
    tableBody.innerHTML = '';
    usersWithProgress.forEach(usuario => {
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
            <h6>${getSuperiorName(usuario.id_superior)}</h6>
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
        logAction('Búsqueda cancelada', { resultados: allUsuarios.length });
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
    
    logAction('Búsqueda realizada', { 
        termino: searchInput,
        total_usuarios: allUsuarios.length,
        resultados_encontrados: filtered.length,
        usuarios: filtered.map(u => u.nombre + ' ' + u.apellido)
    });
    
    console.log(`Búsqueda: "${searchInput}" - ${filtered.length} resultados de ${allUsuarios.length}`);
    filteredUsuarios = filtered; 
    currentPage = 1; 
    const sorted = currentSortColumn
        ? sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection)
        : filteredUsuarios;
    displayUsuarios(sorted);
}

function performSearch(query) {
    // Helper function for search implementation
    filterUsuarios();
}

function attachCheckboxListeners() {
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllCheckbox);
    });
}

function attachButtonListeners() {
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

function showSuccess(message, data = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] hecho: ${message}`;
    
    console.log(logMessage);//logear en consola
    if (data) {
        console.log('Data:', data);
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
    let toastContainer = document.getElementById('toastContainer');//crear contenedor para la notificacion si no existe
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

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
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
    console.log('Timestamp:', new Date().toISOString());
    console.log('Action:', action);
    if (Object.keys(details).length > 0) {
        console.log('Details:', details);
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

window.changePage = changePage;
window.stopAutoRefresh = stopAutoRefresh;
window.startAutoRefresh = startAutoRefresh;