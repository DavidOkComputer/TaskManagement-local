/*project_details.js para la visualización del modal de detalles del proyecto*/
 
// Variable para almacenar los datos del proyecto actual
let currentProjectDetails = null;
let currentProjectTasks = [];
let projectDetailsModalInstance = null;
function viewProjectDetails(projectId) {
    const modal = document.getElementById('projectDetailsModal');
    if (!modal) {
        console.error('Modal de detalles no encontrado');
        return;
    }
 
    // Mostrar loading y ocultar contenido
    document.getElementById('projectDetailsLoading').style.display = 'block';
    document.getElementById('projectDetailsContent').style.display = 'none';
 
    // Obtener o crear instancia del modal
    projectDetailsModalInstance = bootstrap.Modal.getInstance(modal);
    if (!projectDetailsModalInstance) {
        projectDetailsModalInstance = new bootstrap.Modal(modal, {
            keyboard: true
        });
    }
 
    projectDetailsModalInstance.show();
    fetchProjectDetails(projectId);
}
 
function fetchProjectDetails(projectId) {
    fetch(`../php/get_project_details.php?id=${projectId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.proyecto) {
                currentProjectDetails = data.proyecto;
                currentProjectTasks = data.proyecto.tareas || [];
                displayProjectDetails(data.proyecto);
            } else {
                throw new Error(data.message || 'No se pudo cargar el proyecto');
            }
        })
        .catch(error => {
            console.error('Error al cargar detalles del proyecto:', error);
            document.getElementById('projectDetailsLoading').innerHTML = `
                <div class="text-center py-5">
                    <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-danger">${error.message}</p>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            `;
        });
}
 function capitalizeFirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
function displayProjectDetails(proyecto) {
    document.getElementById('projectDetailsLoading').style.display = 'none';
    document.getElementById('projectDetailsContent').style.display = 'block';
    document.getElementById('projectDetailTitle').textContent = proyecto.nombre;
    document.getElementById('detailProjectName').textContent = proyecto.nombre;
    document.getElementById('detailProjectDescription').textContent = proyecto.descripcion || 'Sin descripción';
 
    // Estado del proyecto
    const statusBadge = document.getElementById('detailProjectStatus');
    statusBadge.textContent = capitalizeFirst(proyecto.estado);
    statusBadge.className = `badge fs-6 badge-${getStatusColor(proyecto.estado)}`;
 
    // Tipo de proyecto
    const typeBadge = document.getElementById('detailProjectType');
    typeBadge.textContent = proyecto.tipo_proyecto.nombre || (proyecto.tipo_proyecto.id === 1 ? 'Grupal' : 'Individual');
 
    // Barra de progreso
    const progressBar = document.getElementById('detailProgressBar');
    const progreso = proyecto.progreso || 0;
    progressBar.style.width = `${progreso}%`;
    progressBar.textContent = `${progreso}%`;
    progressBar.className = `progress-bar ${getProgressColor(progreso)}`;
 
    // Estadísticas
    const stats = proyecto.estadisticas;
    document.getElementById('statTotalTareas').textContent = stats.total_tareas;
    document.getElementById('statTareasCompletadas').textContent = stats.tareas_completadas;
    document.getElementById('statTareasEnProceso').textContent = stats.tareas_en_proceso;
    document.getElementById('statTareasVencidas').textContent = stats.tareas_vencidas;
 
    // Información general
    document.getElementById('detailDepartamento').textContent = proyecto.departamento.nombre;
    document.getElementById('detailCreador').textContent = proyecto.creador.nombre;
    document.getElementById('detailFechaCreacion').textContent = formatDateLong(proyecto.fecha_creacion);
    document.getElementById('detailFechaLimite').textContent = formatDateLong(proyecto.fecha_cumplimiento);
 
    // Participante (solo para proyectos individuales)
    const participanteRow = document.getElementById('detailParticipanteRow');
    if (proyecto.tipo_proyecto.id === 1) {
        participanteRow.style.display = 'none';
    } else {
        participanteRow.style.display = '';
        const participante = proyecto.participante;
        document.getElementById('detailParticipante').textContent = participante ? participante.nombre : 'Sin asignar';
    }
 
    // Usuarios asignados (solo para proyectos grupales)
    const usuariosSection = document.getElementById('detailUsuariosSection');
    if (proyecto.tipo_proyecto.id === 1 && proyecto.usuarios_asignados.length > 0) {
        usuariosSection.style.display = '';
        displayProjectUsers(proyecto.usuarios_asignados);
    } else {
        usuariosSection.style.display = 'none';
    }
 
    displayProjectTasks(proyecto.tareas);
}

function getProgressColor(progreso) {
    if (progreso < 30) {
        return 'bg-danger';
    } else if (progreso < 70) {
        return 'bg-warning';
    } else {
        return 'bg-success';
    }
}
function formatDateLong(dateString) { 
    if (!dateString) return '-'; 
    const options = { year: 'numeric', month: 'short', day: 'numeric' }; 
    const date = new Date(dateString); 
    return date.toLocaleDateString('es-MX', options); 
} 

function formatDateShort(dateString) { 
    if (!dateString) return '-'; 
    const options = { year: 'numeric', month: 'short', day: 'numeric' }; 
    const date = new Date(dateString); 
    return date.toLocaleDateString('es-MX', options); 
} 
 
 
function displayProjectUsers(usuarios) {
    const tbody = document.getElementById('detailUsuariosTableBody');
    const countElement = document.getElementById('detailUsuariosCount');
    
    countElement.textContent = usuarios.length;
 
    if (!usuarios || usuarios.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-3">
                    Sin usuarios asignados
                </td>
            </tr>
        `;
        return;
    }
 
    let html = '';
    usuarios.forEach(usuario => {
        const progressClass = getProgressColor(usuario.progreso);
        html += `
            <tr>
                <td>
                    <strong>${escapeHtml(usuario.nombre_completo)}</strong>
                </td>
                <td>${usuario.num_empleado}</td>
                <td><small>${escapeHtml(usuario.e_mail)}</small></td>
                <td>
                    <span class="badge bg-secondary">${usuario.tareas_completadas}/${usuario.tareas_asignadas}</span>
                </td>
                <td style="min-width: 120px;">
                    <div class="progress" style="height: 18px;">
                        <div class="progress-bar ${progressClass}" role="progressbar"
                             style="width: ${usuario.progreso}%;"
                             aria-valuenow="${usuario.progreso}" aria-valuemin="0" aria-valuemax="100">
                            ${usuario.progreso.toFixed(0)}%
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });
 
    tbody.innerHTML = html;
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


function displayProjectTasks(tareas, filter = 'all') {
    const tbody = document.getElementById('detailTareasTableBody');
    const noTareasDiv = document.getElementById('detailNoTareas');
 
    // Filtrar tareas si es necesario
    let tareasToShow = tareas;
    if (filter !== 'all') {
        tareasToShow = tareas.filter(t => t.estado.toLowerCase() === filter.toLowerCase());
    }
 
    if (!tareasToShow || tareasToShow.length === 0) {
        tbody.innerHTML = '';
        noTareasDiv.style.display = 'block';
        noTareasDiv.querySelector('p').textContent =
            filter === 'all' ? 'No hay tareas registradas en este proyecto' :
            `No hay tareas con estado "${filter}"`;
        return;
    }
 
    noTareasDiv.style.display = 'none';
 
    let html = '';
    tareasToShow.forEach(tarea => {
        const estadoClass = getStatusColor(tarea.estado);
        
        html += `
            <tr>
                <td>
                    <div>
                        <strong>${escapeHtml(tarea.nombre)}</strong>
                        ${tarea.descripcion ? `<small class="text-muted d-block">${truncateText(tarea.descripcion, 50)}</small>` : ''}
                    </div>
                </td>
                <td>${escapeHtml(tarea.asignado_a)}</td>
                <td>${formatDateShort(tarea.fecha_cumplimiento)}</td>
                <td>
                    <span class="badge badge-${estadoClass}">${capitalizeFirst(tarea.estado)}</span>
                </td>
            </tr>
        `;
    });
 
    tbody.innerHTML = html;
}
 
function editarProyecto(idProyecto) { 
    window.location.href = `../nuevoProyectoUser/?edit=${idProyecto}`; 
} 

function filterProjectTasks(filter) {
    // Actualizar botones activos
    const buttons = document.querySelectorAll('#projectDetailsModal .btn-group .btn');
    buttons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-filter') === filter) {
            btn.classList.add('active');
        }
    })};