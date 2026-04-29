/*manager_project_task_panel.js panel dividido para ver los detalles del proyecto y las tareas */

const MPTP = {
    projectId: null,
    projectData: null,
    tasks: [],
    users: [],
    canAssign: false,
    filter: 'all',
    modalInstance: null,
    editingTaskId: null
};

document.addEventListener('DOMContentLoaded', function () {

    //delegacion al hacer clic enun registro
    document.getElementById('proyectosTableBody')
        .addEventListener('click', function (e) {
            if (e.target.closest('.action-buttons') ||
                e.target.closest('button')) return;
            const row = e.target.closest('tr');
            if (!row) return;
            const editBtn = row.querySelector('[onclick^="editarProyecto"]');
            if (!editBtn) return;
            const match = editBtn.getAttribute('onclick').match(/\d+/);
            if (!match) return;
            openSplitModal(parseInt(match[0]));
        });

    //pestanias para filtrar
    document.getElementById('rpFilterTabs')
        .addEventListener('click', function (e) {
            const tab = e.target.closest('.rp-filter-tab');
            if (!tab) return;
            document.querySelectorAll('.rp-filter-tab')
                .forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            MPTP.filter = tab.dataset.filter;
            renderTaskList();
        });

    document.getElementById('rpShowTaskFormBtn')
        .addEventListener('click', showTaskForm);
    document.getElementById('rpCancelTaskBtn')
        .addEventListener('click', hideTaskForm);
    document.getElementById('rpSaveTaskBtn')
        .addEventListener('click', saveTask);

    //boton para editar
    document.getElementById('splitBtnEdit')
        .addEventListener('click', function () {
            if (MPTP.projectId)
                window.location.href =
                    `../nuevoProyectoGerente/?edit=${MPTP.projectId}`;
        });
});

//abrir modal
function openSplitModal(projectId) {
    MPTP.assignedUsers = [];
    MPTP.projectId = projectId;
    MPTP.projectData = null;
    MPTP.tasks = [];
    MPTP.users = [];
    MPTP.filter = 'all';

    showLeftLoading(true);
    resetRightPanel();
    hideTaskForm();

    document.querySelectorAll('.rp-filter-tab')
        .forEach(t => t.classList.toggle('active', t.dataset.filter === 'all'));

    //reponer insignia de proyecto libre
    document.getElementById('splitModalLibreBadge').style.display = 'none';

    const modalEl = document.getElementById('projectSplitModal');
    MPTP.modalInstance = bootstrap.Modal.getInstance(modalEl) ||
        new bootstrap.Modal(modalEl, { keyboard: true });
    MPTP.modalInstance.show();

    Promise.all([
        loadProjectDetails(projectId),
        loadProjectUsers(projectId)
    ]).then(() => loadTaskList(projectId));
}

//panel izquierdo
function loadProjectDetails(projectId) {
    return fetch(`../php/get_project_details.php?id=${projectId}`)
        .then(r => { if (!r.ok) throw new Error('Error de red'); return r.json(); })
        .then(data => {
            if (!data.success || !data.proyecto)
                throw new Error(data.message || 'No se pudo cargar el proyecto');
            MPTP.projectData = data.proyecto;
            renderLeftPanel(data.proyecto);
        })
        .catch(err => {
            console.error('Error cargando detalles:', err);
            document.getElementById('splitLeftLoading').innerHTML = `
            <div class="text-center py-4">
              <i class="mdi mdi-alert-circle text-danger" style="font-size:2rem;"></i>
              <p class="text-danger small mt-2">${mEsc(err.message)}</p>
            </div>`;
        });
}

function renderLeftPanel(p) {
    showLeftLoading(false);

    //guardar usuarios asignados para el refresco
    MPTP.assignedUsers = p.usuarios_asignados || [];

    msetText('splitModalTitle', p.nombre);

    const statusBadge = document.getElementById('splitModalStatusBadge');
    statusBadge.textContent = mCapFirst(p.estado || '');
    statusBadge.className = `badge ms-2 badge-${mStatusColor(p.estado)}`;

    const s = p.estadisticas;
    msetText('spl-total-tareas', s.total_tareas);
    msetText('spl-completadas', s.tareas_completadas);
    msetText('spl-en-proceso', s.tareas_en_proceso);
    msetText('spl-vencidas', s.tareas_vencidas);

    const pct = p.progreso || 0;
    const bar = document.getElementById('spl-progress-bar');
    bar.style.width = `${pct}%`;
    bar.style.background = pct >= 70 ? '#009b4a' : pct >= 40 ? '#f0a500' : '#e74c3c';
    msetText('spl-progress-pct', `${pct}%`);

    msetText('spl-departamento', p.departamento?.nombre || 'Sin departamento');
    msetText('spl-tipo',
        p.tipo_proyecto?.nombre ||
        (p.tipo_proyecto?.id === 1 ? 'Grupal' : 'Individual'));
    msetText('spl-creador', p.creador?.nombre || '–');
    msetText('spl-fecha-inicio', mFmtDate(p.fecha_creacion));
    msetText('spl-fecha-limite', mFmtDate(p.fecha_cumplimiento));

    const participanteRow = document.getElementById('spl-participante-row');
    if (p.tipo_proyecto?.id === 1) {
        participanteRow.style.display = 'none';
    } else {
        participanteRow.style.display = '';
        msetText('spl-participante', p.participante?.nombre || 'Sin asignar');
    }

    const usersSection = document.getElementById('spl-users-section');
    if (p.tipo_proyecto?.id === 1 && p.usuarios_asignados?.length > 0) {
        usersSection.style.display = '';
        msetText('spl-users-count', p.usuarios_asignados.length);
        renderUsersTable(p.usuarios_asignados);
    } else {
        usersSection.style.display = 'none';
    }

    //permisos
    const userId = window.APP_CONFIG?.userId || window.currentUserId;
    MPTP.canAssign = (p.puede_editar_otros == 1) ||
        (parseInt(p.creador?.id) === parseInt(userId));

    //boton para editar proyecto solo visible si es creador o el proyecto es de edicion libre
    const canEditProject = (p.puede_editar_otros == 1) || (parseInt(p.creador?.id) === parseInt(userId));
    document.getElementById('splitBtnEdit').style.display =
        canEditProject ? 'inline-block' : 'none';

    document.getElementById('splitModalPermNote').style.display =
        MPTP.canAssign ? 'none' : 'inline';
    document.getElementById('rpAddTaskToggle').style.display =
        MPTP.canAssign ? 'block' : 'none';

    const assigneeNote = document.getElementById('rpAssigneeNote');
    if (!MPTP.canAssign) {
        assigneeNote.style.display = 'block';
        document.getElementById('rpTaskAssignee').disabled = true;
    } else {
        assigneeNote.style.display = 'none';
        document.getElementById('rpTaskAssignee').disabled = false;
    }
}

function renderUsersTable(usuarios) {
    document.getElementById('spl-users-tbody').innerHTML =
        usuarios.map(u => {
            const pct = u.progreso || 0;
            const color = pct >= 70 ? '#009b4a' : pct >= 40 ? '#f0a500' : '#e74c3c';
            return `
            <tr>
              <td><strong>${mEsc(u.nombre_completo)}</strong></td>
              <td>${u.num_empleado}</td>
              <td><span class="badge bg-secondary">
                ${u.tareas_completadas}/${u.tareas_asignadas}
              </span></td>
              <td>
                <div class="split-progress" style="min-width:70px;">
                  <div class="split-progress-bar"
                       style="width:${pct}%;background:${color};"></div>
                </div>
                <small style="font-size:0.68rem;">${pct.toFixed(0)}%</small>
              </td>
            </tr>`;
        }).join('');
}

//usuarios
function loadProjectUsers(projectId) {
    return fetch(`../php/get_project_user.php?id_proyecto=${projectId}`)
        .then(r => r.json())
        .then(data => {
            MPTP.users = (data.success && data.usuarios) ? data.usuarios : [];
            if (data.es_libre === 1)
                document.getElementById('splitModalLibreBadge').style.display = 'inline';
            populateAssigneeSelect(MPTP.users);
        })
        .catch(err => {
            console.error('Error cargando usuarios:', err);
            MPTP.users = [];
        });
}

function populateAssigneeSelect(users) {
    const sel = document.getElementById('rpTaskAssignee');
    sel.innerHTML = '<option value="">Sin asignar</option>';
    users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id_usuario;
        opt.textContent = `${u.nombre} ${u.apellido} (#${u.num_empleado})`;
        sel.appendChild(opt);
    });
}

//lista de tareas
function loadTaskList(projectId) {
    showRpLoading(true);
    fetch(
        `../php/manager_get_tasks_by_project.php?id_proyecto=${projectId}`
    )
        .then(r => r.json())
        .then(data => {
            showRpLoading(false);
            MPTP.tasks = (data.success && data.tasks) ? data.tasks : [];
            renderTaskList();
        })
        .catch(err => {
            showRpLoading(false);
            console.error('Error cargando tareas:', err);
            showRpEmpty('Error al cargar las tareas.');
        });
}

function refreshAssignedUsersTable() {
    const section = document.getElementById('spl-users-section');
    if (!section || section.style.display === 'none') return;
    if (!MPTP.assignedUsers || MPTP.assignedUsers.length === 0) return;

    const updated = MPTP.assignedUsers.map(user => {
        const userTasks = MPTP.tasks.filter(t => t.id_participante == user.id_usuario);
        const total = userTasks.length;
        const completed = userTasks.filter(t => (t.estado || '').toLowerCase() === 'completado').length;
        const progress = total > 0 ? Math.round((completed / total) * 100) : 0;
        return {
            ...user,
            tareas_asignadas: total,
            tareas_completadas: completed,
            progreso: progress
        };
    });

    renderUsersTable(updated);
}

function renderTaskList() {
    const list = document.getElementById('rpTaskList');
    list.innerHTML = '';

    let tasks = MPTP.tasks;
    if (MPTP.filter !== 'all')
        tasks = tasks.filter(t => (t.estado || '').toLowerCase() === MPTP.filter);

    if (!tasks.length) {
        list.innerHTML = `
        <li class="rp-task-item justify-content-center">
          <span class="text-muted small">
            ${MPTP.tasks.length === 0
            ? 'No hay tareas registradas en este proyecto.'
            : `No hay tareas con estado "${MPTP.filter}".`}
          </span>
        </li>`;
        return;
    }
    tasks.forEach(task => list.appendChild(buildTaskItem(task)));
}

function buildTaskItem(task) {
    const li = document.createElement('li');
    li.className = 'rp-task-item';
    li.dataset.taskId = task.id_tarea;

    const isCompleted = (task.estado || '').toLowerCase() === 'completado';
    const overdue = mIsOverdue(task.fecha_cumplimiento, task.estado);
    const iconClass = isCompleted
        ? 'mdi-checkbox-marked-circle-outline text-success'
        : 'mdi-checkbox-blank-circle-outline text-muted';
    const nameStyle = isCompleted ? 'text-decoration:line-through;color:#6c757d;' : '';
    const dateStr = task.fecha_cumplimiento ? mFmtDate(task.fecha_cumplimiento) : 'Sin fecha';
    const dateHtml = overdue
        ? `<span class="overdue-text">${dateStr} · Vencida</span>`
        : dateStr;
    const assignee = task.participante ? `· ${mEsc(task.participante)}` : '';

    //boton de editar solo disponible si es proyecto de libre edicion
    const editBtnHtml = MPTP.canAssign
        ? `<button class="btn btn-link p-0 rp-edit-task-btn" data-task-id="${task.id_tarea}" title="Editar tarea" style="font-size:0.8rem;line-height:1;color:#6c757d;margin-left:4px;">
             <i class="mdi mdi-pencil"></i>
           </button>`
        : '';

    li.innerHTML = `
        <i class="mdi mdi-24px ${iconClass} rp-task-icon"
           data-task-id="${task.id_tarea}"
           title="${isCompleted ? 'Marcar como pendiente' : 'Marcar como completado'}"></i>
        <div class="rp-task-body">
          <p class="rp-task-name" style="${nameStyle}">${mEsc(task.nombre)}</p>
          <span class="rp-task-meta">${dateHtml} ${assignee}</span>
        </div>
        <div class="d-flex align-items-flex-start gap-1 flex-shrink-0" style="margin-top:2px;">
          <span class="badge badge-${mStatusColor(task.estado)}" style="font-size:0.65rem;align-self:flex-start;">
            ${mCapFirst(task.estado || 'pendiente')}
          </span>
          ${editBtnHtml}
        </div>`;

    //selector entre completado o pendiente
    li.querySelector('.rp-task-icon').addEventListener('click', function () {
        toggleTaskStatus(task.id_tarea,
            isCompleted ? 'pendiente' : 'completado', li);
    });

    // Abrir edición de tarea
    const editBtn = li.querySelector('.rp-edit-task-btn');
    if (editBtn) {
        editBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            editTask(task.id_tarea);
        });
    }

    return li;
}

function toggleTaskStatus(taskId, newStatus, liEl) {
    liEl.style.opacity = '0.5';
    liEl.style.pointerEvents = 'none';
    const fd = new FormData();
    fd.append('id_tarea', taskId);
    fd.append('estado', newStatus);
    fetch('../php/update_task_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            liEl.style.opacity = '1';
            liEl.style.pointerEvents = 'auto';
            if (data.success) {
                const t = MPTP.tasks.find(t => t.id_tarea == taskId);
                if (t) t.estado = newStatus;
                renderTaskList();
                refreshProjectStats();
                refreshAssignedUsersTable();
                showRpToast(
                    newStatus === 'completado'
                        ? 'Tarea completada'
                        : 'Tarea marcada como pendiente',
                    'success');
            } else {
                showRpToast(data.message || 'Error al actualizar', 'danger');
            }
        })
        .catch(err => {
            liEl.style.opacity = '1';
            liEl.style.pointerEvents = 'auto';
            showRpToast('Error de red', 'danger');
            console.error(err);
        });
}

function refreshProjectStats() {
    const total = MPTP.tasks.length;
    const completado = MPTP.tasks.filter(t => t.estado === 'completado').length;
    const enProceso = MPTP.tasks.filter(t => t.estado === 'en proceso').length;
    const vencido = MPTP.tasks.filter(t => t.estado === 'vencido').length;
    const pct = total > 0 ? Math.round((completado / total) * 100) : 0;
    msetText('spl-total-tareas', total);
    msetText('spl-completadas', completado);
    msetText('spl-en-proceso', enProceso);
    msetText('spl-vencidas', vencido);
    msetText('spl-progress-pct', `${pct}%`);
    const bar = document.getElementById('spl-progress-bar');
    bar.style.width = `${pct}%`;
    bar.style.background = pct >= 70 ? '#009b4a' : pct >= 40 ? '#f0a500' : '#e74c3c';
}

//form de la tarea
function showTaskForm() {
    //reiniciar el estado de edicion y titulo
    MPTP.editingTaskId = null;
    const titleEl = document.querySelector('#rpAddTaskForm .split-section-title');
    if (titleEl) titleEl.innerHTML = '<i class="mdi mdi-plus-circle-outline text-success me-1"></i>Nueva Tarea';

    document.getElementById('rpTaskName').value = '';
    document.getElementById('rpTaskDesc').value = '';
    document.getElementById('rpTaskDate').value = '';
    document.getElementById('rpTaskStatus').value = 'pendiente';
    document.getElementById('rpTaskAssignee').value = '';
    document.getElementById('rpTaskFormAlert').style.display = 'none';

    document.getElementById('rpTaskList').style.display = 'none';
    document.getElementById('rpFilterTabs').style.display = 'none';
    document.getElementById('rpAddTaskForm').style.display = 'block';
    document.getElementById('rpAddTaskToggle').style.display = 'none';
    document.getElementById('rpTaskName').focus();
}

function hideTaskForm() {
    MPTP.editingTaskId = null;
    document.getElementById('rpAddTaskForm').style.display = 'none';
    document.getElementById('rpTaskList').style.display = 'block';
    document.getElementById('rpFilterTabs').style.display = 'flex';
    document.getElementById('rpAddTaskToggle').style.display =
        MPTP.canAssign ? 'block' : 'none';
    document.getElementById('rpTaskName').value = '';
    document.getElementById('rpTaskDesc').value = '';
    document.getElementById('rpTaskDate').value = '';
    document.getElementById('rpTaskStatus').value = 'pendiente';
    document.getElementById('rpTaskAssignee').value = '';
    document.getElementById('rpTaskFormAlert').style.display = 'none';
}

function saveTask() {
    const name = document.getElementById('rpTaskName').value.trim();
    const desc = document.getElementById('rpTaskDesc').value.trim();
    const date = document.getElementById('rpTaskDate').value;
    const status = document.getElementById('rpTaskStatus').value;
    const asign = document.getElementById('rpTaskAssignee').value;

    if (!name) { showFormAlert('El nombre de la tarea es requerido.', 'warning'); return; }
    if (!desc) { showFormAlert('La descripción es requerida.', 'warning'); return; }
    if (!MPTP.projectId) return;

    if (MPTP.editingTaskId) {
        updateTask(MPTP.editingTaskId, name, desc, date, status, asign);
    } else {
        createTask(name, desc, date, status, asign);
    }
}

function createTask(name, desc, date, status, asign) {
    const userId = window.APP_CONFIG?.userId || window.currentUserId || 0;
    setRpSaveLoading(true);

    const fd = new FormData();
    fd.append('nombre', name);
    fd.append('descripcion', desc);
    fd.append('id_proyecto', MPTP.projectId);
    fd.append('fecha_vencimiento', date);
    fd.append('estado', status);
    fd.append('id_participante', asign || '');
    fd.append('id_creador', userId);

    fetch('../php/save_task.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            setRpSaveLoading(false);
            if (data.success) {
                const assigneeUser = MPTP.users.find(u => u.id_usuario == asign);
                const assigneeName = assigneeUser
                    ? `${assigneeUser.nombre} ${assigneeUser.apellido} (#${assigneeUser.num_empleado})`
                    : null;

                MPTP.tasks.push({
                    id_tarea: data.task_id,
                    nombre: name,
                    descripcion: desc,
                    fecha_cumplimiento: date || null,
                    estado: status,
                    id_participante: asign || null,
                    participante: assigneeName
                });

                hideTaskForm();
                renderTaskList();
                refreshProjectStats();
                refreshAssignedUsersTable();
                showRpToast('Tarea creada exitosamente.', 'success');
            } else {
                showFormAlert(data.message || 'Error al crear la tarea.', 'danger');
            }
        })
        .catch(err => {
            setRpSaveLoading(false);
            showFormAlert('Error de red: ' + err.message, 'danger');
            console.error(err);
        });
}

function editTask(taskId) {
    const task = MPTP.tasks.find(t => t.id_tarea == taskId);
    if (!task) return;

    MPTP.editingTaskId = taskId;
    const titleEl = document.querySelector('#rpAddTaskForm .split-section-title');
    if (titleEl) titleEl.innerHTML = '<i class="mdi mdi-pencil text-success me-1"></i>Editar Tarea';

    document.getElementById('rpTaskName').value = task.nombre || '';
    document.getElementById('rpTaskDesc').value = task.descripcion || '';
    document.getElementById('rpTaskDate').value = task.fecha_cumplimiento
        ? task.fecha_cumplimiento.split('T')[0] : '';
    document.getElementById('rpTaskStatus').value = task.estado || 'pendiente';
    document.getElementById('rpTaskAssignee').value = task.id_participante || '';
    document.getElementById('rpTaskFormAlert').style.display = 'none';

    document.getElementById('rpTaskList').style.display = 'none';
    document.getElementById('rpFilterTabs').style.display = 'none';
    document.getElementById('rpAddTaskForm').style.display = 'block';
    document.getElementById('rpAddTaskToggle').style.display = 'none';
    document.getElementById('rpTaskName').focus();
}

function updateTask(taskId, name, desc, date, status, asign) {
    const userId = window.APP_CONFIG?.userId || window.currentUserId || 0;
    setRpSaveLoading(true);

    const fd = new FormData();
    fd.append('id_tarea', taskId);
    fd.append('nombre', name);
    fd.append('descripcion', desc);
    fd.append('id_proyecto', MPTP.projectId);
    fd.append('fecha_vencimiento', date);
    fd.append('estado', status);
    fd.append('id_participante', asign || '');
    fd.append('id_creador', userId);

    fetch('../php/update_task.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            setRpSaveLoading(false);
            if (data.success) {
                const idx = MPTP.tasks.findIndex(t => t.id_tarea == taskId);
                if (idx !== -1) {
                    const assigneeUser = MPTP.users.find(u => u.id_usuario == asign);
                    const assigneeName = assigneeUser
                        ? `${assigneeUser.nombre} ${assigneeUser.apellido} (#${assigneeUser.num_empleado})`
                        : null;
                    MPTP.tasks[idx] = {
                        ...MPTP.tasks[idx],
                        nombre: name,
                        descripcion: desc,
                        fecha_cumplimiento: date || null,
                        estado: status,
                        id_participante: asign || null,
                        participante: assigneeName
                    };
                }
                hideTaskForm();
                renderTaskList();
                refreshProjectStats();
                refreshAssignedUsersTable();
                showRpToast('Tarea actualizada exitosamente.', 'success');
            } else {
                showFormAlert(data.message || 'Error al actualizar la tarea.', 'danger');
            }
        })
        .catch(err => {
            setRpSaveLoading(false);
            showFormAlert('Error de red: ' + err.message, 'danger');
            console.error(err);
        });
}

//helpers del UI
function showLeftLoading(show) {
    document.getElementById('splitLeftLoading').style.display = show ? 'block' : 'none';
    document.getElementById('splitLeftContent').style.display = show ? 'none' : 'block';
}
function showRpLoading(show) {
    document.getElementById('rpTaskLoading').style.display = show ? 'block' : 'none';
    document.getElementById('rpTaskList').style.display = show ? 'none' : 'block';
}
function showRpEmpty(msg) {
    document.getElementById('rpTaskList').innerHTML = `
    <li class="rp-task-item justify-content-center">
      <span class="text-muted small">${mEsc(msg)}</span>
    </li>`;
}
function resetRightPanel() {
    showRpEmpty('Cargando tareas...');
    hideTaskForm();
}
function showFormAlert(msg, type) {
    const el = document.getElementById('rpTaskFormAlert');
    el.className = `alert alert-${type} py-2 small`;
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}
function setRpSaveLoading(loading) {
    const btn = document.getElementById('rpSaveTaskBtn');
    btn.disabled = loading;
    btn.querySelector('.btn-text').style.display = loading ? 'none' : 'inline';
    btn.querySelector('.spinner-border').style.display = loading ? 'inline-block' : 'none';
}
function showRpToast(msg, type) {
    const t = document.createElement('div');
    t.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    t.style.cssText = 'z-index:9999;font-size:0.82rem;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

//helpers de prefijos para evitar conflictos con manager manage projects js globales
function msetText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? '–';
}
function mCapFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function mStatusColor(estado) {
    return { pendiente: 'warning', 'en proceso': 'primary', vencido: 'danger', completado: 'success' }
        [(estado || '').toLowerCase()] || 'warning';
}
function mFmtDate(ds) {
    if (!ds) return '–';
    const p = ds.split(/[- T]/);
    if (p.length < 3) return ds;
    return new Date(+p[0], +p[1] - 1, +p[2])
        .toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}
function mIsOverdue(dateStr, estado) {
    if (!dateStr || (estado || '').toLowerCase() === 'completado') return false;
    const p = dateStr.split(/[- T]/);
    if (p.length < 3) return false;
    const d = new Date(+p[0], +p[1] - 1, +p[2]);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return d < today;
}
function mEsc(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, m =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
}

window.openSplitModal = openSplitModal;