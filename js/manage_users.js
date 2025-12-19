// manage_users.js - FIXED VERSION con manejo correcto de fotos de perfil
const Config = { 
    API_ENDPOINTS: {  
        DELETE: '../php/delete_users.php',
        GET_DEPARTMENTS: '../php/get_departments.php',
        GET_USERS: '../php/get_users.php',
        UPDATE_USER: '../php/update_users.php'
    },
    // Ruta correcta para el avatar por defecto (relativa a gestionDeEmpleados/)
    DEFAULT_AVATAR: '../images/default-avatar.png',
    // Base path para uploads (relativa a gestionDeEmpleados/)
    UPLOADS_BASE: '../uploads/profile_pictures/'
}; 

const AUTO_REFRESH_CONFIG = {
    USERS_INTERVAL: 120000,     // 2 minutos - reducido para evitar llamadas excesivas
    MODAL_INTERVAL: 120000,     // 2 minutos
    DEBUG: false                // Desactivar debug en producción
};

const IMAGE_CONFIG = {
    MAX_SIZE: 5 * 1024 * 1024,
    ALLOWED_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ALLOWED_EXTENSIONS: ['jpg', 'jpeg', 'png', 'gif', 'webp']
};

let allUsuarios = [];
let filteredUsuarios = [];
let allDepartamentos = [];
let usersProgressCache = {};
let currentSortColumn = null;
let sortDirection = 'asc';
let currentPage = 1;
let rowsPerPage = 10;
let totalPages = 0;
let autoRefreshInterval = null;
let modalRefreshInterval = null;
let currentUserIdForProject = null;

// Variables para foto de perfil en edición
let editSelectedImage = null;
let editRemovePhoto = false;

// Cache para evitar llamadas repetidas
let lastUsersLoad = 0;
const MIN_LOAD_INTERVAL = 5000; // Mínimo 5 segundos entre cargas

document.addEventListener('DOMContentLoaded', function() {
    loadDepartamentos();
    loadUsuarios();
    
    // Iniciar auto-refresh después de un delay
    setTimeout(() => {
        startAutoRefresh();
    }, 5000);

    const searchInput = document.getElementById('searchUser');
    if (searchInput) {
        searchInput.addEventListener('input', filterUsuarios);
    }
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }
    
    const saveUserChanges = document.getElementById('saveUserChanges');
    if (saveUserChanges) {
        saveUserChanges.addEventListener('click', handleSaveUserChanges);
    }

    setupSorting();
    setupPagination();
    setupModalEventListeners();
    setupEditProfilePictureHandlers();
});

createCustomDialogSystem();

// ========== FUNCIONES DE FOTO DE PERFIL ==========

/**
 * Obtiene la URL correcta para la foto de perfil
 * @param {object} usuario - Objeto usuario con datos de foto
 * @param {boolean} thumbnail - Si usar thumbnail o imagen completa
 * @returns {string} URL de la imagen
 */
function getProfilePictureUrl(usuario, thumbnail = true) {
    // Si no hay foto, retornar default
    if (!usuario || !usuario.foto_perfil) {
        return Config.DEFAULT_AVATAR;
    }
    
    // Construir path correcto
    if (thumbnail && usuario.foto_thumbnail) {
        return '../' + usuario.foto_thumbnail;
    } else if (usuario.foto_url) {
        return '../' + usuario.foto_url;
    } else if (usuario.foto_perfil) {
        // Fallback: construir URL manualmente
        if (thumbnail) {
            return Config.UPLOADS_BASE + 'thumbnails/thumb_' + usuario.foto_perfil;
        }
        return Config.UPLOADS_BASE + usuario.foto_perfil;
    }
    
    return Config.DEFAULT_AVATAR;
}

/**
 * Maneja errores de carga de imagen - solo una vez
 */
function handleImageError(imgElement) {
    // Evitar loop infinito verificando si ya se intentó el fallback
    if (imgElement.dataset.fallbackApplied === 'true') {
        return;
    }
    imgElement.dataset.fallbackApplied = 'true';
    imgElement.src = Config.DEFAULT_AVATAR;
}

function setupEditProfilePictureHandlers() {
    const fileInput = document.getElementById('editFotoPerfil');
    const dropZone = document.getElementById('editProfilePictureDropZone');
    const removeBtn = document.getElementById('editRemoveProfilePicture');
    const changeBtn = document.getElementById('editChangeProfilePicture');

    if (fileInput) {
        fileInput.addEventListener('change', handleEditFileSelect);
    }

    if (dropZone) {
        dropZone.addEventListener('dragover', handleEditDragOver);
        dropZone.addEventListener('dragleave', handleEditDragLeave);
        dropZone.addEventListener('drop', handleEditDrop);
        dropZone.addEventListener('click', () => fileInput?.click());
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', handleRemoveEditPhoto);
    }

    if (changeBtn) {
        changeBtn.addEventListener('click', () => fileInput?.click());
    }

    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('hidden.bs.modal', resetEditImageState);
    }
}

function handleEditDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.add('drag-over');
}

function handleEditDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
}

function handleEditDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        processEditImageFile(files[0]);
    }
}

function handleEditFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        processEditImageFile(file);
    }
}

function processEditImageFile(file) {
    if (!IMAGE_CONFIG.ALLOWED_TYPES.includes(file.type)) {
        showError('Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP');
        return;
    }

    if (file.size > IMAGE_CONFIG.MAX_SIZE) {
        showError('El archivo es demasiado grande. Máximo 5MB');
        return;
    }

    const extension = file.name.split('.').pop().toLowerCase();
    if (!IMAGE_CONFIG.ALLOWED_EXTENSIONS.includes(extension)) {
        showError('Extensión de archivo no permitida');
        return;
    }

    editSelectedImage = file;
    editRemovePhoto = false;
    showEditImagePreview(file);
}

function showEditImagePreview(file) {
    const reader = new FileReader();
    const previewImage = document.getElementById('editImagePreview');
    const currentPhotoContainer = document.getElementById('editCurrentPhotoContainer');
    const newPhotoContainer = document.getElementById('editNewPhotoContainer');
    const removeBtn = document.getElementById('editRemoveProfilePicture');

    reader.onload = function(e) {
        if (previewImage) {
            previewImage.src = e.target.result;
        }
        if (currentPhotoContainer) {
            currentPhotoContainer.style.display = 'none';
        }
        if (newPhotoContainer) {
            newPhotoContainer.style.display = 'block';
        }
        if (removeBtn) {
            removeBtn.style.display = 'inline-block';
        }
    };

    reader.readAsDataURL(file);
}

function handleRemoveEditPhoto() {
    editSelectedImage = null;
    editRemovePhoto = true;
    
    const fileInput = document.getElementById('editFotoPerfil');
    const previewImage = document.getElementById('editImagePreview');
    const currentPhotoContainer = document.getElementById('editCurrentPhotoContainer');
    const newPhotoContainer = document.getElementById('editNewPhotoContainer');
    const currentPhoto = document.getElementById('editCurrentPhoto');
    const removeBtn = document.getElementById('editRemoveProfilePicture');

    if (fileInput) fileInput.value = '';
    if (previewImage) previewImage.src = '';
    if (newPhotoContainer) newPhotoContainer.style.display = 'none';
    if (currentPhotoContainer) currentPhotoContainer.style.display = 'block';
    if (currentPhoto) currentPhoto.src = Config.DEFAULT_AVATAR;
    if (removeBtn) removeBtn.style.display = 'none';
}

function resetEditImageState() {
    editSelectedImage = null;
    editRemovePhoto = false;
    
    const fileInput = document.getElementById('editFotoPerfil');
    if (fileInput) fileInput.value = '';
}

function setEditCurrentPhoto(photoUrl, hasPhoto) {
    const currentPhoto = document.getElementById('editCurrentPhoto');
    const currentPhotoContainer = document.getElementById('editCurrentPhotoContainer');
    const newPhotoContainer = document.getElementById('editNewPhotoContainer');
    const removeBtn = document.getElementById('editRemoveProfilePicture');

    editSelectedImage = null;
    editRemovePhoto = false;

    if (currentPhoto) {
        currentPhoto.src = photoUrl || Config.DEFAULT_AVATAR;
        currentPhoto.onerror = function() { 
            this.src = Config.DEFAULT_AVATAR; 
            this.onerror = null; // Prevenir loop
        };
    }
    if (currentPhotoContainer) {
        currentPhotoContainer.style.display = 'block';
    }
    if (newPhotoContainer) {
        newPhotoContainer.style.display = 'none';
    }
    if (removeBtn) {
        removeBtn.style.display = hasPhoto ? 'inline-block' : 'none';
    }
}

// ========== FUNCIONES DE MODAL Y REFRESH ==========

function setupModalEventListeners() {
    const modal = document.getElementById('viewProjectsModal');
    if (!modal) return;
    
    modal.addEventListener('hide.bs.modal', function() {
        currentUserIdForProject = null;
    });
}

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    
    autoRefreshInterval = setInterval(() => {
        // Solo refrescar si la pestaña está visible
        if (!document.hidden) {
            refreshUserData();
        }
    }, AUTO_REFRESH_CONFIG.USERS_INTERVAL);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    if (modalRefreshInterval) {
        clearInterval(modalRefreshInterval);
        modalRefreshInterval = null;
    }
}

function refreshUserData() {
    // Evitar llamadas muy frecuentes
    const now = Date.now();
    if (now - lastUsersLoad < MIN_LOAD_INTERVAL) {
        return;
    }
    
    fetch(Config.API_ENDPOINTS.GET_USERS)
    .then(response => {
        if (!response.ok) {
            throw new Error('La respuesta de red no fue ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.usuarios) {
            lastUsersLoad = Date.now();
            
            const searchInput = document.getElementById('searchUser');
            const currentSearchQuery = searchInput ? searchInput.value : '';
            
            // Usar cache de progreso si está disponible para evitar llamadas extra
            const usersWithProgress = data.usuarios.map(usuario => {
                const cachedProgress = usersProgressCache[usuario.id_usuario];
                if (cachedProgress) {
                    return { ...usuario, ...cachedProgress };
                }
                return { 
                    ...usuario, 
                    avgProgress: 0, 
                    totalProjects: 0, 
                    totalTasks: 0, 
                    completedTasks: 0 
                };
            });
            
            allUsuarios = usersWithProgress;
            
            if (currentSearchQuery.trim() !== '') {
                filterUsuarios();
            } else {
                filteredUsuarios = [...allUsuarios];
            }
            
            if (currentSortColumn) {
                filteredUsuarios = sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection);
            }
            
            const newTotalPages = calculatePages(filteredUsuarios);
            if (currentPage > newTotalPages && newTotalPages > 0) {
                currentPage = newTotalPages;
            }
            
            displayUsuarios(filteredUsuarios);
            
            // Actualizar progreso en segundo plano (sin bloquear UI)
            updateProgressInBackground(data.usuarios);
        }
    })
    .catch(error => {
        console.error('Error al refrescar usuarios:', error);
    });
}

// Actualizar progreso en segundo plano sin bloquear
async function updateProgressInBackground(usuarios) {
    for (const usuario of usuarios) {
        // No actualizar si ya tenemos datos recientes
        if (usersProgressCache[usuario.id_usuario]?.lastUpdate > Date.now() - 60000) {
            continue;
        }
        
        try {
            const progress = await calculateUserProgress(usuario.id_usuario);
            usersProgressCache[usuario.id_usuario] = {
                ...progress,
                lastUpdate: Date.now()
            };
            
            // Actualizar en el array
            const index = allUsuarios.findIndex(u => u.id_usuario === usuario.id_usuario);
            if (index !== -1) {
                allUsuarios[index] = { ...allUsuarios[index], ...progress };
            }
        } catch (e) {
            // Ignorar errores individuales
        }
        
        // Pequeña pausa para no sobrecargar
        await new Promise(r => setTimeout(r, 100));
    }
}

// ========== FUNCIONES DE CARGA DE DATOS ==========

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
    });
}

function populateDepartamentosDropdown() {
    const dropdown = document.getElementById('editDepartamento');
    if (!dropdown) return;
    
    dropdown.innerHTML = '<option value="">-- Seleccionar departamento --</option>';
    
    allDepartamentos.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept.id_departamento;
        option.textContent = dept.nombre;
        dropdown.appendChild(option);
    });
}

async function loadUsuarios() { 
    const tableBody = document.getElementById('usuariosTableBody'); 
    
    try { 
        const response = await fetch(Config.API_ENDPOINTS.GET_USERS, { 
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
            lastUsersLoad = Date.now();
            allUsuarios = data.usuarios;
            
            // Inicializar con progreso 0, luego cargar en segundo plano
            allUsuarios = allUsuarios.map(usuario => ({
                ...usuario,
                avgProgress: 0,
                totalProjects: 0,
                totalTasks: 0,
                completedTasks: 0
            }));
            
            filteredUsuarios = [...allUsuarios];  
            currentPage = 1; 
            displayUsuarios(allUsuarios);
            
            // Cargar progreso en segundo plano
            loadProgressAsync();
        } else { 
            const errorMsg = data.message || 'Error desconocido'; 
            showError('Error al cargar usuarios: ' + errorMsg); 
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar usuarios</td></tr>'; 
        } 
    } catch (error) { 
        console.error('Error de conexión en loadUsuarios:', error); 
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión</td></tr>'; 
    } 
}

async function loadProgressAsync() {
    for (const usuario of allUsuarios) {
        try {
            const progress = await calculateUserProgress(usuario.id_usuario);
            usersProgressCache[usuario.id_usuario] = {
                ...progress,
                lastUpdate: Date.now()
            };
            
            // Actualizar usuario en el array
            const index = allUsuarios.findIndex(u => u.id_usuario === usuario.id_usuario);
            if (index !== -1) {
                allUsuarios[index] = { ...allUsuarios[index], ...progress };
                
                // Actualizar también en filteredUsuarios
                const filteredIndex = filteredUsuarios.findIndex(u => u.id_usuario === usuario.id_usuario);
                if (filteredIndex !== -1) {
                    filteredUsuarios[filteredIndex] = { ...filteredUsuarios[filteredIndex], ...progress };
                }
            }
            
            // Actualizar la fila específica en la tabla sin recargar todo
            updateUserRowProgress(usuario.id_usuario, progress);
            
        } catch (e) {
            console.error('Error loading progress for user', usuario.id_usuario, e);
        }
        
        // Pequeña pausa
        await new Promise(r => setTimeout(r, 50));
    }
}

function updateUserRowProgress(userId, progress) {
    // Buscar la fila del usuario y actualizar solo el progreso
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (row) {
        const progressCell = row.querySelector('.progress-cell');
        if (progressCell) {
            progressCell.innerHTML = `
                <div class="d-flex flex-column">
                    <div class="d-flex justify-content-between mb-1">
                        <small>${progress.avgProgress ? progress.avgProgress.toFixed(1) : '0.0'}%</small>
                        <small>${progress.totalProjects || 0} proyecto${progress.totalProjects !== 1 ? 's' : ''}</small>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar ${getProgressBarClass(progress.avgProgress || 0)}"
                             role="progressbar"
                             style="width: ${progress.avgProgress || 0}%;"
                             aria-valuenow="${progress.avgProgress || 0}"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>
            `;
        }
    }
}

// ========== FUNCIONES DE ORDENAMIENTO Y PAGINACIÓN ==========

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
            valueA = a.avgProgress || 0;
            valueB = b.avgProgress || 0; 
            return direction === 'asc' ? valueA - valueB : valueB - valueA;
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
            currentPage = 1;
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

    paginationContainer.innerHTML = '';

    const infoText = document.createElement('div');
    infoText.className = 'pagination-info';
    const startItem = filteredUsuarios.length > 0 ? ((currentPage - 1) * rowsPerPage) + 1 : 0;
    const endItem = Math.min(currentPage * rowsPerPage, filteredUsuarios.length);
    infoText.innerHTML = `
        <p>Mostrando <strong>${startItem}</strong> a <strong>${endItem}</strong> de <strong>${filteredUsuarios.length}</strong> empleados</p>
    `;
    paginationContainer.appendChild(infoText);

    if (totalPages <= 1) return;

    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'pagination-buttons';

    const prevBtn = document.createElement('button');
    prevBtn.className = 'btn btn-sm btn-outline-primary';
    prevBtn.innerHTML = '<i class="mdi mdi-chevron-left"></i> Anterior';
    prevBtn.disabled = currentPage === 1;
    prevBtn.addEventListener('click', () => changePage(currentPage - 1));
    buttonContainer.appendChild(prevBtn);

    const pageButtonsContainer = document.createElement('div');
    pageButtonsContainer.className = 'page-buttons';

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (currentPage <= 3) {
        endPage = Math.min(totalPages, 5);
    }
    if (currentPage > totalPages - 3) {
        startPage = Math.max(1, totalPages - 4);
    }

    if (startPage > 1) {
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
        const pageBtn = document.createElement('button');
        pageBtn.className = `btn btn-sm page-btn ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => changePage(i));
        pageButtonsContainer.appendChild(pageBtn);
    }

    if (endPage < totalPages) {
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

    const nextBtn = document.createElement('button');
    nextBtn.className = 'btn btn-sm btn-outline-primary';
    nextBtn.innerHTML = 'Siguiente <i class="mdi mdi-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.addEventListener('click', () => changePage(currentPage + 1));
    buttonContainer.appendChild(nextBtn);

    paginationContainer.appendChild(buttonContainer);
}

// ========== FUNCIONES DE PROYECTOS ==========

async function fetchUserProjects(userId) { 
    try { 
        const response = await fetch(`../php/get_user_projects.php?id_usuario=${userId}`); 
        const data = await response.json(); 
        if (data.success) { 
            return data.proyectos || []; 
        } else { 
            return [];
        } 
    } catch (error) { 
        console.error('Error fetching user projects:', error); 
        return [];
    } 
} 

async function calculateUserProgress(userId) { 
    const projects = await fetchUserProjects(userId); 
    if (!projects || projects.length === 0) { 
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
        totalProgress += project.progreso || 0; 
        totalTasks += project.tareas_totales || 0; 
        completedTasks += project.tareas_completadas || 0; 
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
    document.getElementById('employeeName').textContent = userName;
    document.getElementById('employeeEmail').textContent = userEmail; 
    document.getElementById('projectsLoading').style.display = 'block';
    document.getElementById('projectsContainer').style.display = 'none'; 
    document.getElementById('noProjects').style.display = 'none'; 
    
    currentUserIdForProject = userId;
    
    modal.show(); 
    const projects = await fetchUserProjects(userId);
    document.getElementById('projectsLoading').style.display = 'none';
    
    if (!projects || projects.length === 0) { 
        document.getElementById('noProjects').style.display = 'block'; 
        return; 
    } 

    document.getElementById('projectsContainer').style.display = 'block'; 

    let totalTasks = 0;
    let completedTasks = 0; 
    let totalProgress = 0; 
    projects.forEach(project => { 
        totalTasks += project.tareas_totales || 0; 
        completedTasks += project.tareas_completadas || 0; 
        totalProgress += project.progreso || 0; 
    }); 

    const avgProgress = projects.length > 0 ? totalProgress / projects.length : 0; 
    document.getElementById('totalProjects').textContent = projects.length; 
    document.getElementById('totalTasks').textContent = totalTasks; 
    document.getElementById('avgProgress').textContent = avgProgress.toFixed(1) + '%'; 
    
    const projectsList = document.getElementById('projectsList');
    projectsList.innerHTML = projects.map(project => ` 
        <div class="card mb-3"> 
            <div class="card-body"> 
                <div class="d-flex justify-content-between align-items-start mb-2"> 
                    <div> 
                        <h6 class="mb-1 fw-bold">${escapeHtml(project.nombre || '')}</h6> 
                        <p class="text-muted mb-2 small">${escapeHtml(project.descripcion || 'Sin descripción')}</p> 
                    </div> 
                    <span class="badge ${getStatusBadgeClass(project.estado)}">${project.estado || 'N/A'}</span> 
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
                        <small class="text-muted">Progreso: ${project.progreso_porcentaje || project.progreso || 0}%</small> 
                        <small class="text-muted">${project.tareas_completadas || 0}/${project.tareas_totales || 0} tareas</small> 
                    </div> 
                    <div class="progress" style="height: 10px;"> 
                        <div class="progress-bar ${getProgressBarClass(project.progreso || 0)}"  
                             role="progressbar"  
                             style="width: ${project.progreso || 0}%;" 
                             aria-valuenow="${project.progreso || 0}"  
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

// ========== FUNCIONES DE DISPLAY ==========

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
    
    attachButtonListeners(); 
    updatePaginationControls(); 
} 

function createUsuarioRow(usuario) {
    const tr = document.createElement('tr');
    tr.dataset.userId = usuario.id_usuario; // Para actualizaciones parciales
    
    const rolBadge = getRolBadge(usuario.id_rol);
    const nombreCompleto = `${usuario.nombre} ${usuario.apellido}`;
    
    // Usar la función helper para obtener URL correcta
    const fotoUrl = getProfilePictureUrl(usuario, true);
    
    tr.innerHTML = `
        <td>
            <div class="d-flex align-items-center">
                <img src="${fotoUrl}" 
                     alt="Foto de ${escapeHtml(nombreCompleto)}" 
                     class="profile-thumbnail me-3"
                     style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #e9ecef;"
                     onerror="handleImageError(this)">
                <div>
                    <h6 class="mb-0">${escapeHtml(nombreCompleto)}</h6>
                    <small class="text-muted">${escapeHtml(usuario.e_mail || '')}</small>
                </div>
            </div>
        </td>
        <td>
            <h6>${getDepartamentoName(usuario.id_departamento)}</h6>
            <p class="text-muted mb-0">${escapeHtml(usuario.usuario || '')}</p>
        </td>
        <td>
            <h6>${getSuperiorName(usuario.id_superior)}</h6>
        </td>
        <td>
            ${rolBadge}
        </td>
        <td class="progress-cell">
            <div class="d-flex flex-column">
                <div class="d-flex justify-content-between mb-1">
                    <small>${usuario.avgProgress ? usuario.avgProgress.toFixed(1) : '0.0'}%</small>
                    <small>${usuario.totalProjects || 0} proyecto${usuario.totalProjects !== 1 ? 's' : ''}</small>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar ${getProgressBarClass(usuario.avgProgress || 0)}"
                         role="progressbar"
                         style="width: ${usuario.avgProgress || 0}%;"
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
                        data-email="${escapeHtml(usuario.e_mail || '')}"
                        title="Ver proyectos">
                    <i class="mdi mdi-folder-account"></i>
                </button>
                <button type="button" class="btn btn-sm btn-success btn-edit"
                        data-user-id="${usuario.id_usuario}"
                        data-nombre="${escapeHtml(usuario.nombre || '')}"
                        data-apellido="${escapeHtml(usuario.apellido || '')}"
                        data-usuario="${escapeHtml(usuario.usuario || '')}"
                        data-email="${escapeHtml(usuario.e_mail || '')}"
                        data-depart="${usuario.id_departamento}"
                        data-foto="${usuario.foto_perfil || ''}"
                        data-foto-url="${usuario.foto_url || ''}">
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
    if (!deptId) return 'Sin departamento';
    const dept = allDepartamentos.find(d => d.id_departamento == deptId);
    return dept ? dept.nombre : 'Departamento ' + deptId;
}

function getSuperiorName(superiorId) {
    if (!superiorId || superiorId === 0) return 'N/A';
    const superior = allUsuarios.find(u => u.id_usuario == superiorId);
    return superior ? `${superior.nombre} ${superior.apellido}` : 'N/A';
}

function filterUsuarios() {
    const searchInput = document.getElementById('searchUser');
    const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
    
    if (!searchValue.trim()) {
        filteredUsuarios = [...allUsuarios]; 
        currentPage = 1; 
        const sorted = currentSortColumn
            ? sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection)
            : filteredUsuarios;
        displayUsuarios(sorted);
        return;
    }
    
    const filtered = allUsuarios.filter(usuario => {
        const fullName = `${usuario.nombre || ''} ${usuario.apellido || ''}`.toLowerCase();
        const email = (usuario.e_mail || '').toLowerCase();
        const numEmpleado = String(usuario.num_empleado || '');
        const username = (usuario.usuario || '').toLowerCase();
        
        return fullName.includes(searchValue) || 
               email.includes(searchValue) || 
               numEmpleado.includes(searchValue) ||
               username.includes(searchValue);
    });
    
    filteredUsuarios = filtered; 
    currentPage = 1; 
    const sorted = currentSortColumn
        ? sortUsuarios(filteredUsuarios, currentSortColumn, sortDirection)
        : filteredUsuarios;
    displayUsuarios(sorted);
}

function attachButtonListeners() {
    const editButtons = document.querySelectorAll('.btn-edit');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const nombre = this.getAttribute('data-nombre');
            const apellido = this.getAttribute('data-apellido');
            const usuario = this.getAttribute('data-usuario');
            const email = this.getAttribute('data-email');
            const depart = this.getAttribute('data-depart');
            const foto = this.getAttribute('data-foto');
            const fotoUrl = this.getAttribute('data-foto-url');
            openEditModal(userId, nombre, apellido, usuario, email, depart, foto, fotoUrl);
        });
    });
 
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const nombre = this.getAttribute('data-nombre');
            confirmDelete(userId, nombre);
        });
    });
 
    const viewProjectsButtons = document.querySelectorAll('.btn-view-projects');
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

// ========== FUNCIONES DE EDICIÓN ==========

function openEditModal(userId, nombre, apellido, usuario, email, departId, foto, fotoUrl) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editNombre').value = nombre || '';
    document.getElementById('editApellido').value = apellido || '';
    document.getElementById('editUsuario').value = usuario || '';
    document.getElementById('editEmail').value = email || '';
    
    const departmentDropdown = document.getElementById('editDepartamento');
    if (departmentDropdown) {
        departmentDropdown.value = departId || '';
    }
    
    // Configurar foto actual
    const hasPhoto = foto && foto.length > 0;
    let photoUrl = Config.DEFAULT_AVATAR;
    
    if (hasPhoto) {
        if (fotoUrl) {
            photoUrl = '../' + fotoUrl;
        } else {
            photoUrl = Config.UPLOADS_BASE + foto;
        }
    }
    
    setEditCurrentPhoto(photoUrl, hasPhoto);
    
    const currentPhotoInput = document.getElementById('editCurrentFotoName');
    if (currentPhotoInput) {
        currentPhotoInput.value = foto || '';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function handleSaveUserChanges(event) {
    event.preventDefault();

    const validation = validateEditForm();
    if (!validation.isValid) {
        validation.errors.forEach(error => {
            showError(error);
        });
        return;
    }
    
    const userId = document.getElementById('editUserId').value;
    const nombre = document.getElementById('editNombre').value.trim();
    const apellido = document.getElementById('editApellido').value.trim();
    const usuario = document.getElementById('editUsuario').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    const id_departamento = parseInt(document.getElementById('editDepartamento').value) || 0;
    
    showInfo('Guardando cambios...');
    
    const formData = new FormData();
    formData.append('id_usuario', userId);
    formData.append('nombre', nombre);
    formData.append('apellido', apellido);
    formData.append('usuario', usuario);
    formData.append('e_mail', email);
    formData.append('id_departamento', id_departamento);
    
    if (editSelectedImage) {
        formData.append('foto_perfil', editSelectedImage);
    }
    
    if (editRemovePhoto) {
        formData.append('remove_photo', 'true');
    }
    
    fetch('../php/update_users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(responseData => {
        if (responseData.success) {
            showSuccess('Usuario actualizado exitosamente');
            const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
            modal.hide();
            loadUsuarios();
        } else {
            const errorMsg = responseData.message || responseData.error || 'Error desconocido';
            showError('Error al actualizar usuario: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('Error de conexión:', error);
        showError('Error de conexión: ' + error.message);
    });
}

// ========== FUNCIONES DE ELIMINACIÓN ==========

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
            
            displayUsuarios(filteredUsuarios);
        } else {
            showErrorAlert(data.message || 'Error al eliminar el usuario');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorAlert('Error al conectar con el servidor');
    });
}

// ========== FUNCIONES DE ALERTAS ==========

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

    setTimeout(() => { 
        if (alertDiv.style.display !== 'none') { 
            alertDiv.style.display = 'none'; 
        } 
    }, 5000); 
}

function showSuccess(message) {
    displayNotification(message, 'success');
}

function showError(message) {
    console.error('Error:', message);
    displayNotification(message, 'error');
}

function showInfo(message) {
    displayNotification(message, 'info');
}

function displayNotification(message, type = 'info') {
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
    const bgColor = {
        'success': '#009B4A',
        'error': '#dc3545',
        'info': '#17a2b8'
    }[type] || '#6c757d';

    const toast = document.createElement('div');
    toast.id = toastId;
    toast.style.cssText = `
        background-color: ${bgColor};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease-out;
    `;
    toast.innerHTML = `
        <span>${message}</span>
        <button style="background:none;border:none;color:white;cursor:pointer;font-size:18px;margin-left:auto;padding:0;" 
                onclick="this.parentElement.remove()">×</button>
    `;
    
    toastContainer.appendChild(toast);

    setTimeout(() => {
        if (document.getElementById(toastId)) {
            toast.remove();
        }
    }, 4000);
}

// ========== FUNCIONES DE UTILIDAD ==========

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; }); 
}

function validateEditForm() {
    const errors = [];
    const nombre = document.getElementById('editNombre').value;
    const apellido = document.getElementById('editApellido').value;
    const usuario = document.getElementById('editUsuario').value;
    const email = document.getElementById('editEmail').value;
    const departamento = document.getElementById('editDepartamento').value;

    if (!nombre || nombre.trim().length < 2) {
        errors.push('El nombre debe tener al menos 2 caracteres');
    }

    if (!apellido || apellido.trim().length < 2) {
        errors.push('El apellido debe tener al menos 2 caracteres');
    }

    if (!usuario || usuario.trim().length < 3) {
        errors.push('El usuario debe tener al menos 3 caracteres');
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push('Formato de email inválido');
    }

    if (!departamento) {
        errors.push('Debe seleccionar un departamento');
    }

    return {
        isValid: errors.length === 0,
        errors: errors
    };
}

// ========== DIALOGO PERSONALIZADO ==========

function createCustomDialogSystem() {
    if (document.getElementById('customConfirmModal')) return;
    
    const dialogHTML = `
        <div class="modal fade" id="customConfirmModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="mdi mdi-help-circle-outline me-2"></i>
                            <span id="confirmTitle">Confirmar acción</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
    const modal = document.getElementById('customConfirmModal');
    const titleElement = document.getElementById('confirmTitle');
    const messageElement = document.getElementById('confirmMessage');
    const headerElement = modal.querySelector('.modal-header');
    const confirmBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    
    const config = {
        confirmText: 'Aceptar',
        cancelText: 'Cancelar',
        type: 'warning',
        ...options
    };
    
    titleElement.textContent = title;
    messageElement.innerHTML = message.replace(/\n/g, '<br>'); 
    
    confirmBtn.textContent = config.confirmText;
    cancelBtn.textContent = config.cancelText;
    
    headerElement.className = 'modal-header';
    
    const typeClasses = {
        'danger': 'bg-danger text-white',
        'warning': 'bg-warning',
        'info': 'bg-info text-white',
        'success': 'bg-success text-white'
    };
    
    headerElement.classList.add(...(typeClasses[config.type] || 'bg-warning').split(' '));
    confirmBtn.className = `btn btn-${config.type === 'danger' ? 'danger' : 'primary'}`;
    
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
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

// Exponer funciones necesarias globalmente
window.confirmDelete = confirmDelete;
window.changePage = changePage;
window.stopAutoRefresh = stopAutoRefresh;
window.startAutoRefresh = startAutoRefresh;
window.handleImageError = handleImageError;

// Agregar estilos para la animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);