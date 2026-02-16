
/*manager_project_details.js Manejo del modal de detalles de proyecto para el dashboard del gerente */
// Variable para almacenar los datos del proyecto actual 
let currentProjectDetails = null;
let currentProjectTasks = [];
let projectDetailsModalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
	// Configurar botón de editar 
	const btnEditProject = document.getElementById('btnEditProject');
	if (btnEditProject) {
		btnEditProject.addEventListener('click', function() {
			const projectId = this.getAttribute('data-project-id');
			if (projectId) {
				editarProyecto(projectId);
			} else if (currentProjectDetails) {
				editarProyecto(currentProjectDetails.id_proyecto);
			}
		});
	}
});

function viewProjectDetails(projectId) {
	const modal = document.getElementById('projectDetailsModal');
	if (!modal) {
		console.error('Modal de detalles no encontrado');
		return;
	}
	// Mostrar loading y ocultar contenido 
	const loadingEl = document.getElementById('projectDetailsLoading');
	const contentEl = document.getElementById('projectDetailsContent');
	if (loadingEl) {
		loadingEl.style.display = 'block';
		loadingEl.innerHTML = ` 
            <div class="text-center py-5"> 
                <div class="spinner-border text-primary" role="status"> 
                    <span class="visually-hidden">Cargando...</span> 
                </div> 
                <p class="mt-3">Cargando información del proyecto...</p> 
            </div>`;
	}
	if (contentEl) contentEl.style.display = 'none';
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
// Alias para compatibilidad 
window.openProjectDetails = viewProjectDetails;

function fetchProjectDetails(projectId) {
	fetch(`../php/get_project_details.php?id=${projectId}`).then(response => {
		if (!response.ok) {
			throw new Error('Error en la respuesta del servidor');
		}
		return response.json();
	}).then(data => {
		if (data.success && data.proyecto) {
			currentProjectDetails = data.proyecto;
			currentProjectTasks = data.proyecto.tareas || [];
			displayProjectDetails(data.proyecto);
		} else {
			throw new Error(data.message || 'No se pudo cargar el proyecto');
		}
	}).catch(error => {
		console.error('Error al cargar detalles del proyecto:', error);
		const loadingEl = document.getElementById('projectDetailsLoading');
		if (loadingEl) {
			loadingEl.innerHTML = ` 
                    <div class="text-center py-5"> 
                        <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 3rem;"></i> 
                        <p class="mt-3 text-danger">${error.message}</p> 
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> 
                    </div>`;
		}
	});
}

function displayProjectDetails(proyecto) {
	const loadingEl = document.getElementById('projectDetailsLoading');
	const contentEl = document.getElementById('projectDetailsContent');
	if (loadingEl) loadingEl.style.display = 'none';
	if (contentEl) contentEl.style.display = 'block';
	// Título 
	setText('projectDetailTitle', proyecto.nombre);
	setText('detailProjectName', proyecto.nombre);
	setText('detailProjectDescription', proyecto.descripcion || 'Sin descripción');
	// Estado del proyecto 
	const statusBadge = document.getElementById('detailProjectStatus');
	if (statusBadge) {
		statusBadge.textContent = capitalizeFirst(proyecto.estado);
		statusBadge.className = `badge fs-6 badge-${getStatusColor(proyecto.estado)}`;
	}
	// Tipo de proyecto 
	const typeBadge = document.getElementById('detailProjectType');
	if (typeBadge) {
		const tipoNombre = proyecto.tipo_proyecto?.nombre || (proyecto.tipo_proyecto?.id === 1 ? 'Grupal' : 'Individual');
		typeBadge.textContent = tipoNombre;
	}
	// Barra de progreso 
	const progressBar = document.getElementById('detailProgressBar');
	if (progressBar) {
		const progreso = Math.round(parseFloat(proyecto.progreso) || 0);
		progressBar.style.width = `${progreso}%`;
		progressBar.textContent = `${progreso}%`;
		progressBar.className = `progress-bar ${getProgressBarClass(progreso)}`;
	}
	// Estadísticas 
	const stats = proyecto.estadisticas || {};
	setText('modalStatTotalTareas', stats.total_tareas || 0);
	setText('modalStatTareasCompletadas', stats.tareas_completadas || 0);
	setText('modalStatTareasEnProceso', stats.tareas_en_proceso || 0);
	setText('modalStatTareasVencidas', stats.tareas_vencidas || 0);
	// Información general 
	setText('detailDepartamento', proyecto.departamento?.nombre || '-');
	setText('detailCreador', proyecto.creador?.nombre || '-');
	setText('detailFechaCreacion', formatDateLong(proyecto.fecha_creacion));
	setText('detailFechaLimite', formatDateLong(proyecto.fecha_cumplimiento));
	// Participante (solo para proyectos individuales) 
	const participanteRow = document.getElementById('detailParticipanteRow');
	if (participanteRow) {
		if (proyecto.tipo_proyecto?.id === 1) {
			participanteRow.style.display = 'none';
		} else {
			participanteRow.style.display = '';
			const participante = proyecto.participante;
			setText('detailParticipante', participante?.nombre || 'Sin asignar');
		}
	}
	// Usuarios asignados (solo para proyectos grupales) 
	const usuariosSection = document.getElementById('detailUsuariosSection');
	if (usuariosSection) {
		if (proyecto.tipo_proyecto?.id === 1 && proyecto.usuarios_asignados?.length > 0) {
			usuariosSection.style.display = '';
			displayProjectUsers(proyecto.usuarios_asignados);
		} else {
			usuariosSection.style.display = 'none';
		}
	}
	// Tareas 
	displayProjectTasks(proyecto.tareas || []);
	// Guardar ID para el botón de editar 
	const btnEdit = document.getElementById('btnEditProject');
	if (btnEdit) {
		btnEdit.setAttribute('data-project-id', proyecto.id_proyecto);
	}
}

function displayProjectUsers(usuarios) {
	const tbody = document.getElementById('detailUsuariosTableBody');
	const countElement = document.getElementById('detailUsuariosCount');
	if (countElement) countElement.textContent = usuarios.length;
	if (!tbody) return;
	if (!usuarios || usuarios.length === 0) {
		tbody.innerHTML = ` 
            <tr> 
                <td colspan="5" class="text-center text-muted py-3"> 
                    Sin usuarios asignados 
                </td> 
            </tr>`;
		return;
	}
	let html = '';
	usuarios.forEach(usuario => {
		const progreso = Math.round(parseFloat(usuario.progreso) || 0);
		const progressClass = getProgressBarClass(progreso);
		html += ` 
            <tr> 
                <td><strong>${escapeHtml(usuario.nombre_completo)}</strong></td> 
                <td>${usuario.num_empleado || '-'}</td> 
                <td><small>${escapeHtml(usuario.e_mail || '-')}</small></td> 
                <td> 
                    <span class="badge bg-secondary">${usuario.tareas_completadas || 0}/${usuario.tareas_asignadas || 0}</span> 
                </td> 
                <td style="min-width: 120px;"> 
                    <div class="progress" style="height: 18px;"> 
                        <div class="progress-bar ${progressClass}" role="progressbar" 
                             style="width: ${progreso}%;" 
                             aria-valuenow="${progreso}" aria-valuemin="0" aria-valuemax="100"> 
                            ${progreso}% 
                        </div> 
                    </div> 
                </td> 
            </tr>`;
	});
	tbody.innerHTML = html;
}

function displayProjectTasks(tareas, filter = 'all') {
	const tbody = document.getElementById('detailTareasTableBody');
	const noTareasDiv = document.getElementById('detailNoTareas');
	if (!tbody) return;
	// Filtrar tareas si es necesario 
	let tareasToShow = tareas;
	if (filter !== 'all') {
		tareasToShow = tareas.filter(t => t.estado?.toLowerCase() === filter.toLowerCase());
	}
	if (!tareasToShow || tareasToShow.length === 0) {
		tbody.innerHTML = '';
		if (noTareasDiv) {
			noTareasDiv.style.display = 'block';
			const pElement = noTareasDiv.querySelector('p');
			if (pElement) {
				pElement.textContent = filter === 'all' ? 'No hay tareas registradas en este proyecto' : `No hay tareas con estado "${filter}"`;
			}
		}
		return;
	}
	if (noTareasDiv) noTareasDiv.style.display = 'none';
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
                <td>${escapeHtml(tarea.asignado_a || 'Sin asignar')}</td> 
                <td>${formatDateShort(tarea.fecha_cumplimiento)}</td> 
                <td> 
                    <span class="badge badge-${estadoClass}">${capitalizeFirst(tarea.estado)}</span> 
                </td> 
            </tr>`;
	});
	tbody.innerHTML = html;
}

function editarProyecto(idProyecto) {
	window.location.href = `../nuevoProyectoGerente/?edit=${idProyecto}`;
}

function editarProyectoFromModal() {
	if (currentProjectDetails) {
		editarProyecto(currentProjectDetails.id_proyecto);
	}
}

function filterProjectTasks(filter) {
	// Actualizar botones activos 
	const buttons = document.querySelectorAll('#projectDetailsModal .btn-group .btn');
	buttons.forEach(btn => {
		btn.classList.remove('active');
		if (btn.getAttribute('data-filter') === filter) {
			btn.classList.add('active');
		}
	});
	// Re-mostrar tareas filtradas 
	displayProjectTasks(currentProjectTasks, filter);
}

function setText(elementId, value) {
	const el = document.getElementById(elementId);
	if (el) el.textContent = value;
}

function capitalizeFirst(string) {
	if (!string) return '';
	return string.charAt(0).toUpperCase() + string.slice(1);
}

function getStatusColor(estado) {
	const colorMap = {
		'pendiente': 'warning',
		'en proceso': 'primary',
		'vencido': 'danger',
		'completado': 'success'
	};
	return colorMap[estado?.toLowerCase()] || 'secondary';
}

function getProgressBarClass(progreso) {
	const p = parseFloat(progreso) || 0;
	if (p >= 70) return 'bg-success';
	if (p >= 40) return 'bg-warning';
	return 'bg-danger';
}

function formatDateLong(dateString) {
	if (!dateString) return '-';
	const options = {
		year: 'numeric',
		month: 'short',
		day: 'numeric'
	};
	const date = new Date(dateString);
	return date.toLocaleDateString('es-MX', options);
}

function formatDateShort(dateString) {
	if (!dateString) return '-';
	const options = {
		year: 'numeric',
		month: 'short',
		day: 'numeric'
	};
	const date = new Date(dateString);
	return date.toLocaleDateString('es-MX', options);
}

function truncateText(text, maxLength) {
	if (!text) return '';
	return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
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
	return String(text).replace(/[&<>"']/g, m => map[m]);
}
//funciones globales
window.viewProjectDetails = viewProjectDetails;
window.editarProyecto = editarProyecto;
window.editarProyectoFromModal = editarProyectoFromModal;
window.filterProjectTasks = filterProjectTasks;