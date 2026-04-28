/*project_task_panel.js para el panel de detalles de proyecto*/

const PTP = {
  projectId: null,
  projectData: null,
  tasks: [],
  users: [],
  canAssign: false,
  filter: "all",
  modalInstance: null,
  //estado de edicion
  editingTaskId: null,   //si es null esta en modo creacion si es numero modo edicion
};

document.addEventListener("DOMContentLoaded", function () {
  document
      .getElementById("proyectosTableBody")
      .addEventListener("click", function (e) {
        if (e.target.closest(".action-buttons") || e.target.closest("button"))
          return;
        const row = e.target.closest("tr");
        if (!row) return;
        const editBtn = row.querySelector('[onclick^="editarProyecto"]');
        if (!editBtn) return;
        const match = editBtn.getAttribute("onclick").match(/\d+/);
        if (!match) return;
        openSplitModal(parseInt(match[0]));
      });

  // pestañas de filtro
  document
      .getElementById("rpFilterTabs")
      .addEventListener("click", function (e) {
        const tab = e.target.closest(".rp-filter-tab");
        if (!tab) return;
        document
            .querySelectorAll(".rp-filter-tab")
            .forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");
        PTP.filter = tab.dataset.filter;
        renderTaskList();
      });

  document
      .getElementById("rpShowTaskFormBtn")
      .addEventListener("click", showTaskForm);
  document
      .getElementById("rpCancelTaskBtn")
      .addEventListener("click", hideTaskForm);
  document.getElementById("rpSaveTaskBtn").addEventListener("click", saveTask);

  document
      .getElementById("splitBtnEdit")
      .addEventListener("click", function () {
        if (PTP.projectId)
          window.location.href = `../nuevoProyecto/?edit=${PTP.projectId}`;
      });
});

// Abrir modal
function openSplitModal(projectId) {
  PTP.assignedUsers = [];
  PTP.projectId = projectId;
  PTP.projectData = null;
  PTP.tasks = [];
  PTP.users = [];
  PTP.filter = "all";
  PTP.editingTaskId = null;

  showLeftLoading(true);
  resetRightPanel();
  hideTaskForm();

  document
      .querySelectorAll(".rp-filter-tab")
      .forEach((t) => t.classList.toggle("active", t.dataset.filter === "all"));

  const modalEl = document.getElementById("projectSplitModal");
  PTP.modalInstance =
      bootstrap.Modal.getInstance(modalEl) ||
      new bootstrap.Modal(modalEl, { keyboard: true });
  PTP.modalInstance.show();

  Promise.all([
    loadProjectDetails(projectId),
    loadProjectUsers(projectId),
  ]).then(() => loadTaskList(projectId));
}

//panel izquierdo con detalles del proyecto
function loadProjectDetails(projectId) {
  return fetch(`../php/get_project_details.php?id=${projectId}`)
      .then((r) => {
        if (!r.ok) throw new Error("Error de red");
        return r.json();
      })
      .then((data) => {
        if (!data.success || !data.proyecto)
          throw new Error(data.message || "No se pudo cargar el proyecto");
        PTP.projectData = data.proyecto;
        renderLeftPanel(data.proyecto);
      })
      .catch((err) => {
        console.error("Error cargando detalles:", err);
        document.getElementById("splitLeftLoading").innerHTML = `
        <div class="text-center py-4">
          <i class="mdi mdi-alert-circle text-danger" style="font-size:2rem;"></i>
          <p class="text-danger small mt-2">${escPTP(err.message)}</p>
        </div>`;
      });
}

function renderLeftPanel(p) {
  showLeftLoading(false);

  document.getElementById("splitModalTitle").textContent = p.nombre;
  PTP.assignedUsers = p.usuarios_asignados || [];
  const statusBadge = document.getElementById("splitModalStatusBadge");
  statusBadge.textContent = capFirst(p.estado || "");
  statusBadge.className = `badge ms-2 badge-${statusColor(p.estado)}`;

  const s = p.estadisticas;
  setText("spl-total-tareas", s.total_tareas);
  setText("spl-completadas", s.tareas_completadas);
  setText("spl-en-proceso", s.tareas_en_proceso);
  setText("spl-vencidas", s.tareas_vencidas);

  const pct = p.progreso || 0;
  const bar = document.getElementById("spl-progress-bar");
  bar.style.width = `${pct}%`;
  bar.style.background =
      pct >= 70 ? "#009b4a" : pct >= 40 ? "#f0a500" : "#e74c3c";
  setText("spl-progress-pct", `${pct}%`);

  setText("spl-departamento", p.departamento?.nombre || "Sin departamento");
  setText(
      "spl-tipo",
      p.tipo_proyecto?.nombre ||
      (p.tipo_proyecto?.id === 1 ? "Grupal" : "Individual"),
  );
  setText("spl-creador", p.creador?.nombre || "–");
  setText("spl-fecha-inicio", fmtDate(p.fecha_creacion));
  setText("spl-fecha-limite", fmtDate(p.fecha_cumplimiento));

  const participanteRow = document.getElementById("spl-participante-row");
  if (p.tipo_proyecto?.id === 1) {
    participanteRow.style.display = "none";
  } else {
    participanteRow.style.display = "";
    setText("spl-participante", p.participante?.nombre || "Sin asignar");
  }

  const usersSection = document.getElementById("spl-users-section");
  if (p.tipo_proyecto?.id === 1 && p.usuarios_asignados?.length > 0) {
    usersSection.style.display = "";
    setText("spl-users-count", p.usuarios_asignados.length);
    renderUsersTable(p.usuarios_asignados);
  } else {
    usersSection.style.display = "none";
  }

  const userId = window.APP_CONFIG?.userId;

  PTP.canAssign =
      p.puede_editar_otros == 1 || parseInt(p.creador?.id) === parseInt(userId);

  document.getElementById("splitModalPermNote").style.display = PTP.canAssign
      ? "none"
      : "inline";
  document.getElementById("rpAddTaskToggle").style.display = PTP.canAssign
      ? "block"
      : "none";

  const assigneeNote = document.getElementById("rpAssigneeNote");
  if (!PTP.canAssign) {
    assigneeNote.style.display = "block";
    document.getElementById("rpTaskAssignee").disabled = true;
  } else {
    assigneeNote.style.display = "none";
    document.getElementById("rpTaskAssignee").disabled = false;
  }
}

function renderUsersTable(usuarios) {
  document.getElementById("spl-users-tbody").innerHTML = usuarios
      .map((u) => {
        const pct = u.progreso || 0;
        const color = pct >= 70 ? "#009b4a" : pct >= 40 ? "#f0a500" : "#e74c3c";
        return `
        <tr>
          <td><strong>${escPTP(u.nombre_completo)}</strong></td>
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
      })
      .join("");
}

//usuarios asignados en select del form
function loadProjectUsers(projectId) {
  return fetch(`../php/get_project_user.php?id=${projectId}`)
      .then((r) => r.json())
      .then((data) => {
        PTP.users = data.success && data.usuarios ? data.usuarios : [];
        if (data.es_libre === 1)
          document.getElementById("splitModalLibreBadge").style.display =
              "inline";
        populateAssigneeSelect(PTP.users);
      })
      .catch((err) => {
        console.error("Error cargando usuarios:", err);
        PTP.users = [];
      });
}

function populateAssigneeSelect(users) {
  const sel = document.getElementById("rpTaskAssignee");
  sel.innerHTML = '<option value="">Sin asignar</option>';
  users.forEach((u) => {
    const opt = document.createElement("option");
    opt.value = u.id_usuario;
    opt.textContent = `${u.nombre} ${u.apellido} (#${u.num_empleado})`;
    sel.appendChild(opt);
  });
}

//panel derecho con lista de tareas
function loadTaskList(projectId) {
  showRpLoading(true);
  fetch(`../php/get_tasks_by_project.php?id_proyecto=${projectId}`)
      .then((r) => r.json())
      .then((data) => {
        showRpLoading(false);
        PTP.tasks = data.success && data.tasks ? data.tasks : [];
        renderTaskList();
      })
      .catch((err) => {
        showRpLoading(false);
        console.error("Error cargando tareas:", err);
        showRpEmpty("Error al cargar las tareas.");
      });
}

function refreshAssignedUsersTable() {
  const section = document.getElementById('spl-users-section');
  if (!section || section.style.display === 'none') return;
  if (!PTP.assignedUsers || PTP.assignedUsers.length === 0) return;

  const updated = PTP.assignedUsers.map(user => {
    const userTasks = PTP.tasks.filter(t => t.id_participante == user.id_usuario);
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
  const list = document.getElementById("rpTaskList");
  list.innerHTML = "";

  let tasks = PTP.tasks;
  if (PTP.filter !== "all")
    tasks = tasks.filter((t) => (t.estado || "").toLowerCase() === PTP.filter);

  if (!tasks.length) {
    list.innerHTML = `
      <li class="rp-task-item justify-content-center">
        <span class="text-muted small">
          ${
        PTP.tasks.length === 0
            ? "No hay tareas registradas en este proyecto."
            : `No hay tareas con estado "${PTP.filter}".`
    }
        </span>
      </li>`;
    return;
  }
  tasks.forEach((task) => list.appendChild(buildTaskItem(task)));
}

function buildTaskItem(task) {
  const li = document.createElement("li");
  li.className = "rp-task-item";
  li.dataset.taskId = task.id_tarea;

  const isCompleted = (task.estado || "").toLowerCase() === "completado";
  const overdue = isOverdue(task.fecha_cumplimiento, task.estado);
  const iconClass = isCompleted
      ? "mdi-checkbox-marked-circle-outline text-success"
      : "mdi-checkbox-blank-circle-outline text-muted";
  const nameStyle = isCompleted
      ? "text-decoration:line-through;color:#6c757d;"
      : "";
  const dateStr = task.fecha_cumplimiento
      ? fmtDate(task.fecha_cumplimiento)
      : "Sin fecha";
  const dateHtml = overdue
      ? `<span class="overdue-text">${dateStr} · Vencida</span>`
      : dateStr;
  const assignee = task.participante ? `· ${escPTP(task.participante)}` : "";

  //boton editar solo visible si el usuario tiene permiso
  const editBtnHtml = PTP.canAssign
      ? `<button class="btn btn-link p-0 rp-edit-task-btn"
                data-task-id="${task.id_tarea}"
                title="Editar tarea"
                style="font-size:0.8rem;line-height:1;color:#6c757d;margin-left:4px;">
         <i class="mdi mdi-pencil"></i>
       </button>`
      : "";

  li.innerHTML = `
    <i class="mdi mdi-24px ${iconClass} rp-task-icon"
       data-task-id="${task.id_tarea}"
       title="${isCompleted ? "Marcar como pendiente" : "Marcar como completado"}"></i>
    <div class="rp-task-body">
      <p class="rp-task-name" style="${nameStyle}">${escPTP(task.nombre)}</p>
      <span class="rp-task-meta">${dateHtml} ${assignee}</span>
    </div>
    <div class="d-flex align-items-flex-start gap-1 flex-shrink-0" style="margin-top:2px;">
      <span class="badge badge-${statusColor(task.estado)}"
            style="font-size:0.65rem;align-self:flex-start;">
        ${capFirst(task.estado || "pendiente")}
      </span>
      ${editBtnHtml}
    </div>`;

  //toggle completado o pendiente
  li.querySelector(".rp-task-icon").addEventListener("click", function () {
    toggleTaskStatus(
        task.id_tarea,
        isCompleted ? "pendiente" : "completado",
        li,
    );
  });

  //editar tarea
  const editBtn = li.querySelector(".rp-edit-task-btn");
  if (editBtn) {
    editBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      editTask(task.id_tarea);
    });
  }

  return li;
}

function toggleTaskStatus(taskId, newStatus, liEl) {
  liEl.style.opacity = "0.5";
  liEl.style.pointerEvents = "none";
  const fd = new FormData();
  fd.append("id_tarea", taskId);
  fd.append("estado", newStatus);
  fetch("../php/update_task_status.php", { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        liEl.style.opacity = "1";
        liEl.style.pointerEvents = "auto";
        if (data.success) {
          const t = PTP.tasks.find((t) => t.id_tarea == taskId);
          if (t) t.estado = newStatus;
          renderTaskList();
          refreshProjectStats();
          refreshAssignedUsersTable();
          showRpToast(
              newStatus === "completado"
                  ? "Tarea completada"
                  : "Tarea marcada como pendiente",
              "success",
          );
        } else {
          showRpToast(data.message || "Error al actualizar", "danger");
        }
      })
      .catch((err) => {
        liEl.style.opacity = "1";
        liEl.style.pointerEvents = "auto";
        showRpToast("Error de red", "danger");
        console.error(err);
      });
}

function refreshProjectStats() {
  const total = PTP.tasks.length;
  const completado = PTP.tasks.filter((t) => t.estado === "completado").length;
  const enProceso = PTP.tasks.filter((t) => t.estado === "en proceso").length;
  const vencido = PTP.tasks.filter((t) => t.estado === "vencido").length;
  const pct = total > 0 ? Math.round((completado / total) * 100) : 0;
  setText("spl-total-tareas", total);
  setText("spl-completadas", completado);
  setText("spl-en-proceso", enProceso);
  setText("spl-vencidas", vencido);
  setText("spl-progress-pct", `${pct}%`);
  const bar = document.getElementById("spl-progress-bar");
  bar.style.width = `${pct}%`;
  bar.style.background =
      pct >= 70 ? "#009b4a" : pct >= 40 ? "#f0a500" : "#e74c3c";
}

//form de tarea para crear o editar

//abre el form en modo CREACIoN
function showTaskForm() {
  PTP.editingTaskId = null;
  setFormTitle("Nueva Tarea", "mdi-plus-circle-outline");
  setSaveButtonLabel("Guardar Tarea", "mdi-content-save");

  //limpiar campos
  document.getElementById("rpTaskName").value = "";
  document.getElementById("rpTaskDesc").value = "";
  document.getElementById("rpTaskDate").value = "";
  document.getElementById("rpTaskStatus").value = "pendiente";
  document.getElementById("rpTaskAssignee").value = "";
  document.getElementById("rpTaskFormAlert").style.display = "none";

  openTaskFormPanel();
}

//carga la tarea en el form y lo abre en modo EDICION
function editTask(taskId) {
  const task = PTP.tasks.find((t) => t.id_tarea == taskId);
  if (!task) return;

  PTP.editingTaskId = taskId;
  setFormTitle("Editar Tarea", "mdi-pencil");
  setSaveButtonLabel("Actualizar Tarea", "mdi-content-save-edit");

  //rellenar campos con los datos actuales
  document.getElementById("rpTaskName").value = task.nombre || "";
  document.getElementById("rpTaskDesc").value = task.descripcion || "";
  document.getElementById("rpTaskDate").value = task.fecha_cumplimiento
      ? task.fecha_cumplimiento.split("T")[0]   // recortar a YYYY-MM-DD si viene con hora
      : "";
  document.getElementById("rpTaskStatus").value = task.estado || "pendiente";
  document.getElementById("rpTaskAssignee").value = task.id_participante || "";
  document.getElementById("rpTaskFormAlert").style.display = "none";

  openTaskFormPanel();
}

function openTaskFormPanel() {
  // Ocultar lista y filtros, mostrar form
  document.getElementById("rpTaskList").style.display = "none";
  document.getElementById("rpFilterTabs").style.display = "none";
  document.getElementById("rpAddTaskForm").style.display = "block";
  document.getElementById("rpAddTaskToggle").style.display = "none";

  document.getElementById("rpTaskName").focus();
}

function hideTaskForm() {
  PTP.editingTaskId = null;

  document.getElementById("rpAddTaskForm").style.display = "none";
  document.getElementById("rpTaskList").style.display = "block";
  document.getElementById("rpFilterTabs").style.display = "flex";
  document.getElementById("rpAddTaskToggle").style.display = PTP.canAssign
      ? "block"
      : "none";

  //limpiar campos
  document.getElementById("rpTaskName").value = "";
  document.getElementById("rpTaskDesc").value = "";
  document.getElementById("rpTaskDate").value = "";
  document.getElementById("rpTaskStatus").value = "pendiente";
  document.getElementById("rpTaskAssignee").value = "";
  document.getElementById("rpTaskFormAlert").style.display = "none";
}

// Guardar  decide si crear o actualizar según PTP.editingTaskId
function saveTask() {
  const name = document.getElementById("rpTaskName").value.trim();
  const desc = document.getElementById("rpTaskDesc").value.trim();
  const date = document.getElementById("rpTaskDate").value;
  const status = document.getElementById("rpTaskStatus").value;
  const asign = document.getElementById("rpTaskAssignee").value;

  if (!name) {
    showFormAlert("El nombre de la tarea es requerido.", "warning");
    return;
  }
  if (!desc) {
    showFormAlert("La descripción es requerida.", "warning");
    return;
  }
  if (!PTP.projectId) return;

  if (PTP.editingTaskId) {
    updateTask(PTP.editingTaskId, name, desc, date, status, asign);
  } else {
    createTask(name, desc, date, status, asign);
  }
}

//crea una tarea nueva
function createTask(name, desc, date, status, asign) {
  const userId = window.APP_CONFIG?.userId || 0;
  setRpSaveLoading(true);

  const fd = new FormData();
  fd.append("nombre", name);
  fd.append("descripcion", desc);
  fd.append("id_proyecto", PTP.projectId);
  fd.append("fecha_vencimiento", date);
  fd.append("estado", status);
  fd.append("id_participante", asign || "");
  fd.append("id_creador", userId);

  fetch("../php/save_task.php", { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        setRpSaveLoading(false);
        if (data.success) {
          const assigneeUser = PTP.users.find((u) => u.id_usuario == asign);
          const assigneeName = assigneeUser
              ? `${assigneeUser.nombre} ${assigneeUser.apellido} (#${assigneeUser.num_empleado})`
              : null;

          PTP.tasks.push({
            id_tarea: data.task_id,
            nombre: name,
            descripcion: desc,
            fecha_cumplimiento: date || null,
            estado: status,
            id_participante: asign || null,
            participante: assigneeName,
          });

          hideTaskForm();
          renderTaskList();
          refreshProjectStats();
          refreshAssignedUsersTable();
          showRpToast("Tarea creada exitosamente.", "success");
        } else {
          showFormAlert(data.message || "Error al crear la tarea.", "danger");
        }
      })
      .catch((err) => {
        setRpSaveLoading(false);
        showFormAlert("Error de red: " + err.message, "danger");
        console.error(err);
      });
}

///actualiza una tarea existente
function updateTask(taskId, name, desc, date, status, asign) {
  const userId = window.APP_CONFIG?.userId || 0;
  setRpSaveLoading(true);

  const fd = new FormData();
  fd.append("id_tarea", taskId);
  fd.append("nombre", name);
  fd.append("descripcion", desc);
  fd.append("id_proyecto", PTP.projectId);
  fd.append("fecha_vencimiento", date);
  fd.append("estado", status);
  fd.append("id_participante", asign || "");
  fd.append("id_creador", userId);

  fetch("../php/update_task.php", { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        setRpSaveLoading(false);
        if (data.success) {
          // Actualizar la tarea localmente en PTP.tasks
          const idx = PTP.tasks.findIndex((t) => t.id_tarea == taskId);
          if (idx !== -1) {
            const assigneeUser = PTP.users.find((u) => u.id_usuario == asign);
            const assigneeName = assigneeUser
                ? `${assigneeUser.nombre} ${assigneeUser.apellido} (#${assigneeUser.num_empleado})`
                : null;

            PTP.tasks[idx] = {
              ...PTP.tasks[idx],
              nombre: name,
              descripcion: desc,
              fecha_cumplimiento: date || null,
              estado: status,
              id_participante: asign || null,
              participante: assigneeName,
            };
          }

          hideTaskForm();
          renderTaskList();
          refreshProjectStats();
          refreshAssignedUsersTable();
          showRpToast("Tarea actualizada exitosamente.", "success");
        } else {
          showFormAlert(data.message || "Error al actualizar la tarea.", "danger");
        }
      })
      .catch((err) => {
        setRpSaveLoading(false);
        showFormAlert("Error de red: " + err.message, "danger");
        console.error(err);
      });
}

//helpers del form

function setFormTitle(text, iconClass) {
  const titleEl = document.querySelector("#rpAddTaskForm .split-section-title");
  if (titleEl) {
    titleEl.innerHTML = `<i class="mdi ${iconClass} text-success me-1"></i>${text}`;
  }
}

function setSaveButtonLabel(text, iconClass) {
  const btnText = document.querySelector("#rpSaveTaskBtn .btn-text");
  if (btnText) {
    btnText.innerHTML = `<i class="mdi ${iconClass} me-1"></i>${text}`;
  }
}

//UI helpers
function showLeftLoading(show) {
  document.getElementById("splitLeftLoading").style.display = show
      ? "block"
      : "none";
  document.getElementById("splitLeftContent").style.display = show
      ? "none"
      : "block";
}
function showRpLoading(show) {
  document.getElementById("rpTaskLoading").style.display = show
      ? "block"
      : "none";
  document.getElementById("rpTaskList").style.display = show ? "none" : "block";
}
function showRpEmpty(msg) {
  document.getElementById("rpTaskList").innerHTML = `
    <li class="rp-task-item justify-content-center">
      <span class="text-muted small">${escPTP(msg)}</span>
    </li>`;
}
function resetRightPanel() {
  showRpEmpty("Cargando tareas...");
  hideTaskForm();
}
function showFormAlert(msg, type) {
  const el = document.getElementById("rpTaskFormAlert");
  el.className = `alert alert-${type} py-2 small`;
  el.textContent = msg;
  el.style.display = "block";
  setTimeout(() => {
    el.style.display = "none";
  }, 4000);
}
function setRpSaveLoading(loading) {
  const btn = document.getElementById("rpSaveTaskBtn");
  btn.disabled = loading;
  btn.querySelector(".btn-text").style.display = loading ? "none" : "inline";
  btn.querySelector(".spinner-border").style.display = loading
      ? "inline-block"
      : "none";
}
function showRpToast(msg, type) {
  const t = document.createElement("div");
  t.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
  t.style.cssText = "z-index:9999;font-size:0.82rem;";
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}
function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val ?? "–";
}
function capFirst(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : "";
}
function statusColor(estado) {
  return (
      {
        pendiente: "warning",
        "en proceso": "primary",
        vencido: "danger",
        completado: "success",
      }[(estado || "").toLowerCase()] || "warning"
  );
}
function fmtDate(ds) {
  if (!ds) return "–";
  const p = ds.split(/[- T]/);
  if (p.length < 3) return ds;
  return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString("es-MX", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}
function isOverdue(dateStr, estado) {
  if (!dateStr || (estado || "").toLowerCase() === "completado") return false;
  const p = dateStr.split(/[- T]/);
  if (p.length < 3) return false;
  const d = new Date(+p[0], +p[1] - 1, +p[2]);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return d < today;
}
function escPTP(text) {
  if (!text) return "";
  return String(text).replace(
      /[&<>"']/g,
      (m) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;",
          })[m],
  );
}

window.openSplitModal = openSplitModal;
