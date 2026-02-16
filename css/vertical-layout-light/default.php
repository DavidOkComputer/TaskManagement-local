# Manager Dashboard - Admin Style Adaptation

## Overview
This package adapts the admin dashboard layout and functionality for the manager role, with department-based access control filtering.

## Files Included

### Main Dashboard Page
- **`managerDashboard/managerDashboard.php`** → Copy to your `managerDashboard/` folder (replace existing)

### JavaScript Files  
- **`js/manager_dashboard_overview.js`** → Copy to your `js/` folder (NEW file)
- **`js/manager_project_details.js`** → Copy to your `js/` folder (replace existing)

### PHP API Files
- **`php/manager_get_objectives_dashboard.php`** → Copy to your `php/` folder (NEW file)
- **`php/manager_get_top_projects_progress.php`** → Copy to your `php/` folder (replace existing)

### CSS File (Required)
You need the `dashboard-override.css` file that you already have. Make sure it's at:
- **`css/vertical-layout-light/dashboard-override.css`**

---

## Installation Steps

### Step 1: Backup Existing Files
```bash
# Create backup folder
mkdir -p backup_manager_dashboard

# Backup existing files
cp managerDashboard/managerDashboard.php backup_manager_dashboard/
cp js/manager_project_details.js backup_manager_dashboard/
cp php/manager_get_top_projects_progress.php backup_manager_dashboard/
```

### Step 2: Copy New Files
```bash
# Copy main dashboard
cp managerDashboard.php /path/to/your/project/managerDashboard/

# Copy JavaScript files
cp js/manager_dashboard_overview.js /path/to/your/project/js/
cp js/manager_project_details.js /path/to/your/project/js/

# Copy PHP files
cp php/manager_get_objectives_dashboard.php /path/to/your/project/php/
cp php/manager_get_top_projects_progress.php /path/to/your/project/php/
```

### Step 3: Verify CSS File
Ensure `dashboard-override.css` exists at:
```
css/vertical-layout-light/dashboard-override.css
```

---

## File Dependency Map

```
managerDashboard.php
├── CSS
│   └── dashboard-override.css
├── JavaScript
│   ├── manager_dashboard_overview.js (main logic)
│   ├── manager_project_details.js (modal handling)
│   └── ppe_chart_click_manager.js (existing - chart click)
└── PHP APIs
    ├── manager_get_dashboard_stats.php (existing)
    ├── manager_get_projects.php (existing)
    ├── manager_get_objectives_dashboard.php (NEW)
    ├── manager_get_top_projects_progress.php (UPDATED)
    ├── manager_get_top_employees_progress.php (existing)
    └── get_project_details.php (existing)
```

---

## What's New/Changed

### Dashboard Layout
- **Header Bar**: User name, filters (Proyecto, Estado, Responsable), progress circle
- **Stats Row**: 9 statistics including objectives, projects, and tasks
- **Main Row (3 columns)**:
  - Left: Progreso por Responsable (horizontal bar chart)
  - Center: Status boxes + Progreso de Proyectos chart
  - Right: Proyectos por estado (doughnut chart)
- **Bottom Row (2 tables)**:
  - Left: Proyectos por Progreso
  - Right: Detalles de la tarea

### JavaScript Changes
- **manager_dashboard_overview.js**: Complete rewrite with admin-style functionality
  - Chart initialization and updates
  - Filter system
  - Auto-refresh (60 second interval)
  - Modal integration

### PHP API Changes
- **manager_get_objectives_dashboard.php**: NEW - Returns projects formatted for objectives table
- **manager_get_top_projects_progress.php**: FIXED - Uses `tbl_usuario_roles` for proper role detection

---

## Department Filtering Logic

All APIs follow this access control:

1. **Admin (id_rol=1)**: Sees all data system-wide
2. **Manager (id_rol=2)**: Sees data from:
   - Their managed department(s)
   - Projects they created
   - Projects assigned to them
   - Projects where they're in the group

---

## Existing Files to Keep (Do NOT Replace)

These files are already correct and should NOT be replaced:
- `js/ppe_chart_click_manager.js` (doughnut chart click handler)
- `php/manager_get_dashboard_stats.php` (already has role filtering)
- `php/manager_get_projects.php` (already has role filtering)
- `php/manager_get_top_employees_progress.php` (already has role filtering)
- `php/get_project_details.php` (shared between admin/manager)

---

## Troubleshooting

### Charts Not Displaying
1. Verify Chart.js is loaded: `vendors/chart.js/Chart.min.js`
2. Check browser console for errors
3. Verify API endpoints return data

### Data Not Loading
1. Check session is active (`$_SESSION['user_id']` or `$_SESSION['id_usuario']`)
2. Verify user has manager role in `tbl_usuario_roles`
3. Check PHP error logs for exceptions

### Modal Not Opening
1. Verify Bootstrap JS is loaded
2. Check that `manager_project_details.js` is included
3. Verify `get_project_details.php` returns valid JSON

### Filters Not Working
1. Check that filter dropdowns have `id` attributes
2. Verify `setupFilterListeners()` is called on DOM ready

---

## Color Scheme Reference

```css
/* Status Colors */
--completed: #009b4a;  /* Green */
--pending:   #ffc107;  /* Yellow */
--overdue:   #dc3545;  /* Red */
--inprocess: #495057;  /* Dark gray */

/* Brand Colors */
--primary:   #009b4a;  /* Nidec Green */
--dark:      #000000;  /* Black */
--light:     #ffffff;  /* White */
```

---

## Contact & Support

For issues related to this adaptation, check:
1. Browser Developer Console (F12) for JavaScript errors
2. Network tab for failed API calls
3. PHP error logs for server-side issues

000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000

<?php 
/*managerDashboard.php para Dashboard principal de gerente - Estilo Admin*/ 
require_once('../php/check_auth.php'); 
?> 
<!DOCTYPE html> 
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<title>Administrador de proyectos - Gerente</title>
		<!-- plugins:css --> 
		<link rel="stylesheet" href="../vendors/feather/feather.css">
		<link rel="stylesheet" href="../vendors/mdi/css/materialdesignicons.min.css">
		<link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
		<link rel="stylesheet" href="../vendors/typicons/typicons.css">
		<link rel="stylesheet" href="../vendors/simple-line-icons/css/simple-line-icons.css">
		<link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
		<!-- endinject --> 
		<!-- inject:css --> 
		<link rel="stylesheet" href="../css/vertical-layout-light/style.css">
		<!-- endinject --> 
		<!-- Dashboard override styles--> 
		<link rel="stylesheet" href="../css/vertical-layout-light/dashboard-override.css">
		<link rel="shortcut icon" href="../images/Nidec Institutional Logo_Original Version.png" />
	</head>
	<body>
		<div class="container-scroller">
			<!-- partial navbar --> 
			<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
				<div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
					<div class="me-3"> 
						<button class="navbar-toggler navbar-toggler align-self-center" type="button" data-bs-toggle="minimize"> 
						<span class="icon-menu"></span> 
						</button> 
					</div>
					<div> 
						<a class="navbar-brand brand-logo" href="#"> 
						<img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" /> 
						</a> 
						<a class="navbar-brand brand-logo-mini" href="#"> 
						<img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" /> 
						</a> 
					</div>
				</div>
				<div class="navbar-menu-wrapper d-flex align-items-top">
					<!--Header bar con filtros--> 
					<ul class="navbar-nav db-header-bar">
						<div class="db-header-left">
							<div class="db-header-title"> 
								<span class="db-title-main"><?php echo $_SESSION['nombre']; echo ' '; echo $_SESSION['apellido']; ?></span> 
								<span class="db-title-sub">Tu resumen de esta semana</span> 
							</div>
						</div>
						<div class="db-header-filters">
							<span class="db-filter-group">
								<label class="db-filter-label">Proyecto</label> 
								<select class="db-filter-select" id="filterObjective">
									<option value="all">Todos</option>
								</select>
							</span>
							<div class="db-filter-group">
								<label class="db-filter-label">Estado</label> 
								<select class="db-filter-select" id="filterStatus">
									<option value="all">Todos</option>
									<option value="Completado">Completados</option>
									<option value="Pendiente">Pendientes</option>
									<option value="Vencido">Atrasados</option>
								</select>
							</div>
							<div class="db-filter-group">
								<label class="db-filter-label">Responsable</label> 
								<select class="db-filter-select" id="filterResponsible">
									<option value="all">Todos</option>
								</select>
							</div>
						</div>
						<div class="db-header-right">
							<span class="db-progress-label">PROGRESO TOTAL</span> 
							<div class="db-progress-circle" id="totalProgressCircle">
								<svg viewBox="0 0 80 80">
									<circle class="db-progress-bg" cx="40" cy="40" r="34"></circle>
									<circle class="db-progress-fill" cx="40" cy="40" r="34" stroke-dasharray="213.63" stroke-dashoffset="213.63"></circle>
								</svg>
								<span class="db-progress-value" id="totalProgressValue">0%</span> 
							</div>
						</div>
					</ul>
					<button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-bs-toggle="offcanvas"> 
					<span class="mdi mdi-menu"></span> 
					</button> 
				</div>
			</nav>
			<div class="container-fluid page-body-wrapper">
				<!-- parcial barra lateral --> 
				<div id="right-sidebar" class="settings-panel">
					<i class="settings-close ti-close"></i> 
					<ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
						<li class="nav-item"> 
							<a class="nav-link active" id="todo-tab" data-bs-toggle="tab" href="" role="tab" aria-controls="todo-section" aria-expanded="true">Lista de que hacer</a> 
						</li>
					</ul>
				</div>
				<!-- parcial menu lateral--> 
				<nav class="sidebar sidebar-offcanvas" id="sidebar">
					<ul class="nav">
						<li class="nav-item nav-category">Gestion de usuarios</li>
						<li class="nav-item">
							<a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic"> 
							<i class="menu-icon mdi mdi-account-multiple"></i> 
							<span class="menu-title">Empleados</span> 
							<i class="menu-arrow"></i> 
							</a> 
							<div class="collapse" id="ui-basic">
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../gestionDeEmpleados-Gerente/">Gestion de empleados</a></li>
								</ul>
							</div>
						</li>
						<li class="nav-item nav-category">Proyectos</li>
						<li class="nav-item">
							<a class="nav-link" data-bs-toggle="collapse" href="#form-elements" aria-expanded="false" aria-controls="form-elements"> 
							<i class="menu-icon mdi mdi-folder-upload"></i> 
							<span class="menu-title">Crear proyecto</span> 
							<i class="menu-arrow"></i> 
							</a> 
							<div class="collapse" id="form-elements">
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../nuevoProyectoGerente/">Crear nuevo proyecto</a></li>
									<li class="nav-item"><a class="nav-link" href="../nuevoObjetivoGerente/">Crear nuevo objetivo</a></li>
									<li class="nav-item"><a class="nav-link" href="../nuevoTareaGerente/">Crear nueva tarea</a></li>
								</ul>
							</div>
						</li>
						<li class="nav-item">
							<a class="nav-link" data-bs-toggle="collapse" href="#charts" aria-expanded="false" aria-controls="charts"> 
							<i class="menu-icon mdi mdi-chart-line"></i> 
							<span class="menu-title">Graficado</span> 
							<i class="menu-arrow"></i> 
							</a> 
							<div class="collapse" id="charts">
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../revisarGraficosGerente">Revisar graficos</a></li>
									<li class="nav-item"><a class="nav-link" href="../graficaGanttGerente">Gráfica de Gantt</a></li>
								</ul>
							</div>
						</li>
						<li class="nav-item">
							<a class="nav-link" data-bs-toggle="collapse" href="#tables" aria-expanded="false" aria-controls="tables"> 
							<i class="menu-icon mdi mdi-magnify"></i> 
							<span class="menu-title">Revisar Proyectos</span> 
							<i class="menu-arrow"></i> 
							</a> 
							<div class="collapse" id="tables">
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../revisarProyectosGerente/">Revisar proyectos</a></li>
								</ul>
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../revisarObjetivosGerente/">Revisar objetivos</a></li>
								</ul>
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../revisarTareasGerente/">Revisar tareas</a></li>
								</ul>
							</div>
						</li>
						<li class="nav-item nav-category">Sesión</li>
						<li class="nav-item">
							<a class="nav-link" data-bs-toggle="collapse" href="#auth" aria-expanded="false" aria-controls="auth"> 
							<i class="menu-icon mdi mdi-logout"></i> 
							<span class="menu-title">Terminar sesión</span> 
							<i class="menu-arrow"></i> 
							</a> 
							<div class="collapse" id="auth">
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../php/logout.php">Cerrar Sesión</a></li>
								</ul>
							</div>
						</li>
					</ul>
				</nav>
				<!-- parcial --> 
				<div class="main-panel">
					<div class="content-wrapper" style="padding: 10px 12px;">
						<!-- segunda fila estadisticas de resumen--> 
						<div class="db-stats-row">
							<div class="db-stats-left">
								<div class="db-stat-item"> 
									<span class="db-stat-label">Total Proyectos</span> 
									<span class="db-stat-value" id="statTotalProyectos">0</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">% Proyectos Vencidos</span> 
									<span class="db-stat-value db-stat-danger" id="statPctVencidos">0%</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">Total Objetivos</span> 
									<span class="db-stat-value" id="statTotalObjetivos">0</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">% Objetivos Completados</span> 
									<span class="db-stat-value db-stat-info" id="statPctObjetivos">0%</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">Objetivos Completados</span> 
									<span class="db-stat-value db-stat-success" id="statObjetivosCompletados">0</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">Total Tareas</span> 
									<span class="db-stat-value" id="statTotalTareas">0</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">% Tareas Completadas</span> 
									<span class="db-stat-value db-stat-highlight" id="statPctTareas">0%</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">Tareas Completadas</span> 
									<span class="db-stat-value db-stat-success" id="statTareasCompletadas">0</span> 
								</div>
								<div class="db-stat-item"> 
									<span class="db-stat-label">Tareas Pendientes</span> 
									<span class="db-stat-value db-stat-warning" id="statTareasPendientes">0</span> 
								</div>
							</div>
						</div>
						<!-- fila 3 dashboard principal-->
						<div class="db-main-row">
							<!-- columna 1 progreso por responsable--> 
							<div class="db-col-responsible">
								<div class="db-card-inner">
									<h6 class="db-section-title">Progreso por Responsable</h6>
									<div class="db-responsible-chart">
										<canvas id="responsibleBarChart"></canvas>
									</div>
								</div>
							</div>
							<!--columna dos cajas de estado y progreso de proyectos-->
							<div class="db-col-center">
								<!--estatus de resumen en cajas--> 
								<div class="db-status-boxes">
									<div class="db-status-box db-status-completed"> 
										<span class="db-status-box-label">Completados</span> 
										<span class="db-status-box-value" id="boxCompleted">0</span> 
									</div>
									<div class="db-status-box db-status-notstarted"> 
										<span class="db-status-box-label">Pendientes</span> 
										<span class="db-status-box-value" id="boxNotStarted">0</span> 
									</div>
									<div class="db-status-box db-status-delay"> 
										<span class="db-status-box-label">Atrasados</span> 
										<span class="db-status-box-value" id="boxDelay">0</span> 
									</div>
								</div>
								<!--grafica de progreso de proyectos--> 
								<div class="db-card-inner db-objectives-card">
									<h6 class="db-section-title">Progreso de Proyectos</h6>
									<div class="db-objectives-chart">
										<canvas id="objectivesBarChart"></canvas>
									</div>
								</div>
							</div>
							<!-- columna tres grafica de proyectos por estado--> 
							<div class="db-col-doughnut">
								<div class="db-card-inner">
									<h6 class="db-section-title">Proyectos por estado</h6>
									<div class="db-doughnut-container">
										<canvas id="doughnutChart"></canvas>
									</div>
									<div id="doughnut-chart-legend" class="db-doughnut-legend"></div>
								</div>
							</div>
						</div>
						<!-- fila 4, tablas inferiores--> 
						<div class="db-bottom-row">
							<div class="db-table-left">
								<div class="db-card-inner db-table-card">
									<h6 class="db-section-title">Proyectos por Progreso</h6>
									<div class="db-table-responsive">
										<table class="db-table">
											<thead>
												<tr>
													<th style="width:28px;"></th>
													<th>Proyecto</th>
													<th>Responsable</th>
													<th>Progreso</th>
												</tr>
											</thead>
											<tbody id="objectivesTableBody">
												<tr>
													<td colspan="4" class="text-center" style="padding:30px;">
														<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Cargando...</span></div>
														<p class="mt-2 mb-0" style="font-size:0.8rem;">Cargando proyectos...</p>
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							</div>
							<div class="db-table-right">
								<div class="db-card-inner db-table-card">
									<h6 class="db-section-title">Detalles de la tarea</h6>
									<div class="db-table-responsive">
										<table class="db-table">
											<thead>
												<tr>
													<th style="width:28px;"></th>
													<th>Descripcion</th>
													<th>Estado</th>
													<th>Fecha de entrega</th>
													<th>Responsable</th>
													<th>Progreso</th>
												</tr>
											</thead>
											<tbody id="proyectosTableBody">
												<tr>
													<td colspan="7" class="text-center" style="padding:30px;">
														<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Cargando...</span></div>
														<p class="mt-2 mb-0" style="font-size:0.8rem;">Cargando tareas...</p>
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- Modal de detalles del proyecto --> 
		<div class="modal fade" id="projectDetailsModal" tabindex="-1" aria-labelledby="projectDetailsLabel" aria-hidden="true">
			<div class="modal-dialog modal-xl modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header bg-primary text-white">
						<h5 class="modal-title" id="projectDetailsLabel"><i class="mdi mdi-folder-open me-2"></i><span id="projectDetailTitle">Detalles del Proyecto</span></h5>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> 
					</div>
					<div class="modal-body" id="projectDetailsBody">
						<div class="text-center py-5" id="projectDetailsLoading">
							<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
							<p class="mt-3">Cargando información del proyecto...</p>
						</div>
						<div id="projectDetailsContent" style="display:none;">
							<div class="row mb-4">
								<div class="col-12">
									<div class="d-flex justify-content-between align-items-start flex-wrap">
										<div>
											<h4 id="detailProjectName" class="mb-1"></h4>
											<p id="detailProjectDescription" class="text-muted mb-2"></p>
										</div>
										<div class="text-end"><span id="detailProjectStatus" class="badge fs-6"></span><span id="detailProjectType" class="badge bg-secondary fs-6 ms-2"></span></div>
									</div>
								</div>
							</div>
							<div class="row mb-4">
								<div class="col-12">
									<label class="form-label fw-bold">Progreso General</label>
									<div class="progress" style="height:25px;">
										<div id="detailProgressBar" class="progress-bar" role="progressbar" style="width:0%;">0%</div>
									</div>
								</div>
							</div>
							<div class="row mb-4" id="detailStatsRow">
								<div class="col-md-3 col-6 mb-3">
									<div class="card bg-light h-100">
										<div class="card-body text-center py-3">
											<i class="mdi mdi-clipboard-text text-primary" style="font-size:2rem;"></i>
											<h3 id="modalStatTotalTareas" class="mb-0 mt-2">0</h3>
											<small class="text-muted">Total Tareas</small>
										</div>
									</div>
								</div>
								<div class="col-md-3 col-6 mb-3">
									<div class="card bg-light h-100">
										<div class="card-body text-center py-3">
											<i class="mdi mdi-check-circle-outline text-success" style="font-size:2rem;"></i>
											<h3 id="modalStatTareasCompletadas" class="mb-0 mt-2">0</h3>
											<small class="text-muted">Completadas</small>
										</div>
									</div>
								</div>
								<div class="col-md-3 col-6 mb-3">
									<div class="card bg-light h-100">
										<div class="card-body text-center py-3">
											<i class="mdi mdi-progress-clock text-info" style="font-size:2rem;"></i>
											<h3 id="modalStatTareasEnProceso" class="mb-0 mt-2">0</h3>
											<small class="text-muted">En Proceso</small>
										</div>
									</div>
								</div>
								<div class="col-md-3 col-6 mb-3">
									<div class="card bg-light h-100">
										<div class="card-body text-center py-3">
											<i class="mdi mdi-alert-circle-outline text-danger" style="font-size:2rem;"></i>
											<h3 id="modalStatTareasVencidas" class="mb-0 mt-2">0</h3>
											<small class="text-muted">Vencidas</small>
										</div>
									</div>
								</div>
							</div>
							<div class="row mb-4">
								<div class="col-md-12">
									<div class="card h-100">
										<div class="card-header bg-light">
											<h6 class="mb-0"><i class="mdi mdi-information-outline me-2"></i>Información General</h6>
										</div>
										<div class="card-body">
											<table class="table table-sm table-borderless mb-0">
												<tr>
													<td class="text-muted" style="width:40%;">Departamento:</td>
													<td id="detailDepartamento" class="fw-semibold">-</td>
												</tr>
												<tr>
													<td class="text-muted">Creado por:</td>
													<td id="detailCreador" class="fw-semibold">-</td>
												</tr>
												<tr>
													<td class="text-muted">Fecha de creación:</td>
													<td id="detailFechaCreacion" class="fw-semibold">-</td>
												</tr>
												<tr>
													<td class="text-muted">Fecha límite:</td>
													<td id="detailFechaLimite" class="fw-semibold">-</td>
												</tr>
												<tr id="detailParticipanteRow">
													<td class="text-muted">Responsable:</td>
													<td id="detailParticipante" class="fw-semibold">-</td>
												</tr>
											</table>
										</div>
									</div>
								</div>
							</div>
							<div class="row mb-4" id="detailUsuariosSection" style="display:none;">
								<div class="col-12">
									<div class="card">
										<div class="card-header bg-light d-flex justify-content-between align-items-center">
											<h6 class="mb-0"><i class="mdi mdi-account-group me-2"></i>Usuarios Asignados (<span id="detailUsuariosCount">0</span>)</h6>
										</div>
										<div class="card-body">
											<div class="table-responsive" style="max-height:250px;overflow-y:auto;">
												<table class="table table-sm table-hover mb-0">
													<thead class="table-light" style="position:sticky;top:0;">
														<tr>
															<th>Nombre</th>
															<th>No. Empleado</th>
															<th>Email</th>
															<th>Tareas</th>
															<th>Progreso</th>
														</tr>
													</thead>
													<tbody id="detailUsuariosTableBody"></tbody>
												</table>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-12">
									<div class="card">
										<div class="card-header bg-light d-flex justify-content-between align-items-center">
											<h6 class="mb-0"><i class="mdi mdi-clipboard-check-outline me-2"></i>Tareas del Proyecto</h6>
										</div>
										<div class="card-body">
											<div class="table-responsive" style="max-height:300px;overflow-y:auto;">
												<table class="table table-sm table-hover mb-0">
													<thead class="table-light" style="position:sticky;top:0;">
														<tr>
															<th>Tarea</th>
															<th>Asignado a</th>
															<th>Fecha Límite</th>
															<th>Estado</th>
														</tr>
													</thead>
													<tbody id="detailTareasTableBody"></tbody>
												</table>
											</div>
											<div id="detailNoTareas" class="text-center py-4" style="display:none;">
												<i class="mdi mdi-clipboard-off-outline text-muted" style="font-size:3rem;"></i>
												<p class="text-muted mt-2 mb-0">No hay tareas registradas en este proyecto</p>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer"> 
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close me-1"></i>Cerrar</button> 
						<button type="button" class="btn btn-success" id="btnEditProject"><i class="mdi mdi-pencil me-1"></i>Editar Proyecto</button> 
					</div>
				</div>
			</div>
		</div>
		<!-- plugins:js --> 
		<script src="../vendors/js/vendor.bundle.base.js"></script> 
		<script src="../vendors/chart.js/Chart.min.js"></script> 
		<script src="../vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script> 
		<script src="../vendors/progressbar.js/progressbar.min.js"></script> 
		<script src="../js/template.js"></script> 
		<script src="../js/hoverable-collapse.js"></script> 
		<script src="../js/settings.js"></script> 
		<script src="../js/manager_dashboard_overview.js"></script> 
		<script src="../js/custom_dialogs.js"></script> 
		<script src="../js/notifications.js"></script> 
		<script src="../js/datetime_widget.js"></script> 
		<script src="../js/ppe_chart_click_manager.js"></script> 
		<script src="../js/manager_project_details.js"></script> 
	</body>
</html>

00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000

/**
 * manager_dashboard_overview.js
 * Dashboard principal para gerentes - Estilo Admin
 * Filtrado por departamento(s) gestionado(s)
 */

// ============================================
// CONFIGURACIÓN Y CONSTANTES
// ============================================
const ManagerDashboardConfig = {
    API_ENDPOINTS: {
        GET_PROJECTS: '../php/manager_get_projects.php',
        GET_DASHBOARD_STATS: '../php/manager_get_dashboard_stats.php',
        GET_OBJECTIVES_DASHBOARD: '../php/manager_get_objectives_dashboard.php',
        GET_TOP_EMPLOYEES: '../php/manager_get_top_employees_progress.php',
        GET_TOP_PROJECTS: '../php/manager_get_top_projects_progress.php'
    },
    COLORS: {
        delay: '#dc3545',
        notStarted: '#ffc107',
        completed: '#009b4a',
        onGoing: '#495057'
    },
    REFRESH_INTERVAL: 60000 // 60 segundos
};

const STATUS_MAP = {
    'pendiente': 'Pendiente',
    'en proceso': 'En Proceso',
    'completado': 'Completado',
    'vencido': 'Vencido'
};

// ============================================
// VARIABLES GLOBALES
// ============================================
let allProjectsData = [];
let allObjectivesData = [];
let responsibleChartInstance = null;
let objectivesChartInstance = null;
let doughnutChartInstance = null;
let autoRefreshInterval = null;
let isAutoRefreshActive = true;

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

function initializeDashboard() {
    // Inicializar gráficos vacíos
    initializeCharts();
    
    // Cargar datos
    loadDashboardStats();
    loadProjectsData();
    loadObjectivesData();
    
    // Configurar filtros
    setupFilterListeners();
    
    // Iniciar auto-refresh
    startAutoRefresh();
    
    // Configurar detección de visibilidad
    setupVisibilityDetection();
}

// ============================================
// GRÁFICOS - INICIALIZACIÓN
// ============================================
function initializeCharts() {
    initResponsibleChart();
    initObjectivesChart();
    initDoughnutChart();
}

function initResponsibleChart() {
    const ctx = document.getElementById('responsibleBarChart');
    if (!ctx) return;
    
    responsibleChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'horizontalBar',
        data: {
            labels: [],
            datasets: [{
                label: 'Progreso %',
                data: [],
                backgroundColor: ManagerDashboardConfig.COLORS.completed,
                borderRadius: 4,
                barThickness: 18
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: {
                xAxes: [{
                    ticks: { beginAtZero: true, max: 100, fontSize: 10 },
                    gridLines: { color: '#e9e9e9' }
                }],
                yAxes: [{
                    ticks: { fontSize: 10 },
                    gridLines: { display: false }
                }]
            },
            tooltips: {
                backgroundColor: '#000000',
                titleFontSize: 11,
                bodyFontSize: 11,
                callbacks: {
                    label: function(tooltipItem) {
                        return tooltipItem.value + '% completado';
                    }
                }
            }
        }
    });
}

function initObjectivesChart() {
    const ctx = document.getElementById('objectivesBarChart');
    if (!ctx) return;
    
    objectivesChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'horizontalBar',
        data: {
            labels: [],
            datasets: [{
                label: 'Progreso %',
                data: [],
                backgroundColor: [],
                borderRadius: 4,
                barThickness: 16
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: {
                xAxes: [{
                    ticks: { beginAtZero: true, max: 100, fontSize: 10 },
                    gridLines: { color: '#e9e9e9' }
                }],
                yAxes: [{
                    ticks: { fontSize: 10 },
                    gridLines: { display: false }
                }]
            },
            tooltips: {
                backgroundColor: '#000000',
                titleFontSize: 11,
                bodyFontSize: 11,
                callbacks: {
                    label: function(tooltipItem) {
                        return tooltipItem.value + '% completado';
                    }
                }
            }
        }
    });
}

function initDoughnutChart() {
    const ctx = document.getElementById('doughnutChart');
    if (!ctx) return;
    
    doughnutChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Pendientes', 'Completados', 'Vencidos', 'En Proceso'],
            datasets: [{
                data: [0, 0, 0, 0],
                backgroundColor: [
                    ManagerDashboardConfig.COLORS.notStarted,
                    ManagerDashboardConfig.COLORS.completed,
                    ManagerDashboardConfig.COLORS.delay,
                    ManagerDashboardConfig.COLORS.onGoing
                ],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutoutPercentage: 55,
            legend: { display: false },
            tooltips: {
                backgroundColor: '#000000',
                titleFontSize: 11,
                bodyFontSize: 11,
                callbacks: {
                    label: function(tooltipItem, data) {
                        const dataset = data.datasets[tooltipItem.datasetIndex];
                        const total = dataset.data.reduce((a, b) => a + b, 0);
                        const value = dataset.data[tooltipItem.index];
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return data.labels[tooltipItem.index] + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    });
    
    // Guardar referencia global para ppe_chart_click_manager.js
    window.doughnutChart = doughnutChartInstance;
    
    updateDoughnutLegend();
}

// ============================================
// CARGA DE DATOS
// ============================================
function loadDashboardStats() {
    fetch(ManagerDashboardConfig.API_ENDPOINTS.GET_DASHBOARD_STATS)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats) {
                updateStatsDisplay(data.stats);
                updateTotalProgressCircle(data.stats);
            }
        })
        .catch(error => {
            console.error('Error cargando estadísticas:', error);
        });
}

function loadProjectsData() {
    fetch(ManagerDashboardConfig.API_ENDPOINTS.GET_PROJECTS)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.proyectos) {
                allProjectsData = data.proyectos;
                
                // Actualizar tabla de tareas (proyectos)
                updateProjectsTable(allProjectsData);
                
                // Actualizar gráfico de dona
                updateDoughnutFromProjects(allProjectsData);
                
                // Actualizar cajas de estado
                updateStatusBoxesFromProjects(allProjectsData);
                
                // Poblar filtros
                populateFilterDropdowns(allProjectsData);
                
                // Actualizar gráfico de responsables
                updateResponsibleChart(allProjectsData);
            }
        })
        .catch(error => {
            console.error('Error cargando proyectos:', error);
            showTableError('proyectosTableBody', 'Error al cargar proyectos');
        });
}

function loadObjectivesData() {
    fetch(ManagerDashboardConfig.API_ENDPOINTS.GET_OBJECTIVES_DASHBOARD)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.objetivos) {
                allObjectivesData = data.objetivos;
                
                // Actualizar tabla de objetivos/proyectos
                updateObjectivesTable(allObjectivesData);
                
                // Actualizar gráfico de progreso de proyectos
                updateObjectivesChart(allObjectivesData);
            }
        })
        .catch(error => {
            console.error('Error cargando objetivos:', error);
            showTableError('objectivesTableBody', 'Error al cargar proyectos');
        });
}

// ============================================
// ACTUALIZACIÓN DE UI - ESTADÍSTICAS
// ============================================
function updateStatsDisplay(stats) {
    // Total Proyectos
    setText('statTotalProyectos', stats.total_proyectos || 0);
    
    // % Proyectos Vencidos
    setText('statPctVencidos', (stats.porcentaje_vencidos || 0) + '%');
    
    // Total Objetivos
    setText('statTotalObjetivos', stats.total_objetivos || 0);
    
    // % Objetivos Completados
    setText('statPctObjetivos', (stats.porcentaje_objetivos || 0) + '%');
    
    // Objetivos Completados
    setText('statObjetivosCompletados', stats.objetivos_completados || 0);
    
    // Total Tareas
    setText('statTotalTareas', stats.total_tareas || 0);
    
    // % Tareas Completadas
    setText('statPctTareas', (stats.porcentaje_tareas || 0) + '%');
    
    // Tareas Completadas
    setText('statTareasCompletadas', stats.tareas_completadas || 0);
    
    // Tareas Pendientes
    setText('statTareasPendientes', stats.tareas_pendientes || 0);
}

function updateTotalProgressCircle(stats) {
    const progressValue = stats.porcentaje_tareas || 0;
    const progressFill = document.querySelector('.db-progress-fill');
    const progressText = document.getElementById('totalProgressValue');
    
    if (progressFill) {
        const circumference = 2 * Math.PI * 34; // r=34
        const offset = circumference - (progressValue / 100) * circumference;
        progressFill.style.strokeDashoffset = offset;
    }
    
    if (progressText) {
        progressText.textContent = progressValue + '%';
    }
}

// ============================================
// ACTUALIZACIÓN DE UI - TABLAS
// ============================================
function updateProjectsTable(projects) {
    const tbody = document.getElementById('proyectosTableBody');
    if (!tbody) return;
    
    if (!projects || projects.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center" style="padding:30px;">
                    <i class="mdi mdi-folder-open" style="font-size:32px;color:#ccc;"></i>
                    <p class="mt-2 mb-0" style="font-size:0.8rem;">No hay proyectos disponibles</p>
                </td>
            </tr>`;
        return;
    }
    
    let html = '';
    projects.forEach((project, index) => {
        const statusClass = getStatusClass(project.estado);
        const statusText = STATUS_MAP[project.estado?.toLowerCase()] || project.estado;
        const progressTier = getProgressTier(project.progreso);
        const responsable = project.participante || 'Grupo';
        
        html += `
            <tr style="cursor:pointer;" onclick="viewProjectDetails(${project.id_proyecto})">
                <td><span class="db-collapse-toggle"><i class="mdi mdi-chevron-right"></i></span></td>
                <td><strong>${escapeHtml(truncateText(project.descripcion || project.nombre, 40))}</strong></td>
                <td><span class="db-status-badge ${statusClass}">${statusText}</span></td>
                <td>${formatDate(project.fecha_cumplimiento)}</td>
                <td>${escapeHtml(truncateText(responsable, 20))}</td>
                <td>
                    <div class="db-progress-badge">
                        <span class="db-progress-text ${progressTier}">${project.progreso || 0}%</span>
                        <div class="db-progress-bar-mini">
                            <div class="db-progress-bar-mini-fill ${progressTier}" style="width:${project.progreso || 0}%;"></div>
                        </div>
                    </div>
                </td>
            </tr>`;
    });
    
    tbody.innerHTML = html;
}

function updateObjectivesTable(objectives) {
    const tbody = document.getElementById('objectivesTableBody');
    if (!tbody) return;
    
    if (!objectives || objectives.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center" style="padding:30px;">
                    <i class="mdi mdi-target" style="font-size:32px;color:#ccc;"></i>
                    <p class="mt-2 mb-0" style="font-size:0.8rem;">No hay proyectos disponibles</p>
                </td>
            </tr>`;
        return;
    }
    
    let html = '';
    objectives.forEach((obj, index) => {
        const progressTier = getProgressTier(obj.progreso);
        const typeClass = obj.tipo === 'Global' ? 'type-global' : 'type-regional';
        const responsable = obj.participante || 'Grupo';
        
        html += `
            <tr style="cursor:pointer;" onclick="viewProjectDetails(${obj.id_proyecto})">
                <td><span class="db-type-badge ${typeClass}">${obj.tipo}</span></td>
                <td><strong>${escapeHtml(truncateText(obj.nombre, 30))}</strong></td>
                <td>${escapeHtml(truncateText(responsable, 15))}</td>
                <td>
                    <div class="db-progress-badge">
                        <span class="db-progress-text ${progressTier}">${obj.progreso || 0}%</span>
                        <div class="db-progress-bar-mini">
                            <div class="db-progress-bar-mini-fill ${progressTier}" style="width:${obj.progreso || 0}%;"></div>
                        </div>
                    </div>
                </td>
            </tr>`;
    });
    
    tbody.innerHTML = html;
}

// ============================================
// ACTUALIZACIÓN DE UI - GRÁFICOS
// ============================================
function updateDoughnutFromProjects(projects) {
    if (!doughnutChartInstance) return;
    
    const counts = {
        pendiente: 0,
        completado: 0,
        vencido: 0,
        'en proceso': 0
    };
    
    projects.forEach(p => {
        const estado = (p.estado || '').toLowerCase();
        if (counts.hasOwnProperty(estado)) {
            counts[estado]++;
        }
    });
    
    // Orden: Pendientes, Completados, Vencidos, En Proceso
    doughnutChartInstance.data.datasets[0].data = [
        counts.pendiente,
        counts.completado,
        counts.vencido,
        counts['en proceso']
    ];
    
    doughnutChartInstance.update();
    updateDoughnutLegend();
}

function updateDoughnutLegend() {
    const legendContainer = document.getElementById('doughnut-chart-legend');
    if (!legendContainer || !doughnutChartInstance) return;
    
    const data = doughnutChartInstance.data.datasets[0].data;
    const labels = doughnutChartInstance.data.labels;
    const colors = doughnutChartInstance.data.datasets[0].backgroundColor;
    const total = data.reduce((a, b) => a + b, 0);
    
    let html = '<div style="display:flex;flex-wrap:wrap;justify-content:center;gap:8px;">';
    labels.forEach((label, i) => {
        const pct = total > 0 ? ((data[i] / total) * 100).toFixed(0) : 0;
        html += `<span style="display:inline-flex;align-items:center;gap:4px;font-size:0.68rem;">
            <span style="width:10px;height:10px;background:${colors[i]};border-radius:2px;"></span>
            ${label}: ${data[i]}
        </span>`;
    });
    html += '</div>';
    
    legendContainer.innerHTML = html;
}

function updateStatusBoxesFromProjects(projects) {
    const counts = {
        completado: 0,
        pendiente: 0,
        vencido: 0
    };
    
    projects.forEach(p => {
        const estado = (p.estado || '').toLowerCase();
        if (estado === 'completado') counts.completado++;
        else if (estado === 'pendiente') counts.pendiente++;
        else if (estado === 'vencido') counts.vencido++;
    });
    
    setText('boxCompleted', counts.completado);
    setText('boxNotStarted', counts.pendiente);
    setText('boxDelay', counts.vencido);
}

function updateResponsibleChart(projects) {
    if (!responsibleChartInstance) return;
    
    // Agrupar por responsable
    const responsibleMap = {};
    
    projects.forEach(p => {
        const responsable = p.participante || 'Grupo';
        if (!responsibleMap[responsable]) {
            responsibleMap[responsable] = { total: 0, sumProgress: 0 };
        }
        responsibleMap[responsable].total++;
        responsibleMap[responsable].sumProgress += (p.progreso || 0);
    });
    
    // Calcular promedio y ordenar
    const responsibles = Object.entries(responsibleMap)
        .map(([name, data]) => ({
            name: name,
            progress: Math.round(data.sumProgress / data.total)
        }))
        .sort((a, b) => b.progress - a.progress)
        .slice(0, 8); // Top 8
    
    responsibleChartInstance.data.labels = responsibles.map(r => truncateText(r.name, 15));
    responsibleChartInstance.data.datasets[0].data = responsibles.map(r => r.progress);
    responsibleChartInstance.data.datasets[0].backgroundColor = responsibles.map(r => getProgressColor(r.progress));
    
    responsibleChartInstance.update();
}

function updateObjectivesChart(objectives) {
    if (!objectivesChartInstance) return;
    
    // Ordenar por progreso y tomar top 8
    const sorted = [...objectives]
        .sort((a, b) => (b.progreso || 0) - (a.progreso || 0))
        .slice(0, 8);
    
    objectivesChartInstance.data.labels = sorted.map(o => truncateText(o.nombre, 20));
    objectivesChartInstance.data.datasets[0].data = sorted.map(o => o.progreso || 0);
    objectivesChartInstance.data.datasets[0].backgroundColor = sorted.map(o => getProgressColor(o.progreso || 0));
    
    objectivesChartInstance.update();
}

// ============================================
// FILTROS
// ============================================
function setupFilterListeners() {
    const filterObjective = document.getElementById('filterObjective');
    const filterStatus = document.getElementById('filterStatus');
    const filterResponsible = document.getElementById('filterResponsible');
    
    if (filterObjective) filterObjective.addEventListener('change', applyFilters);
    if (filterStatus) filterStatus.addEventListener('change', applyFilters);
    if (filterResponsible) filterResponsible.addEventListener('change', applyFilters);
}

function populateFilterDropdowns(projects) {
    const filterObjective = document.getElementById('filterObjective');
    const filterResponsible = document.getElementById('filterResponsible');
    
    // Poblar proyectos
    if (filterObjective) {
        const currentValue = filterObjective.value;
        filterObjective.innerHTML = '<option value="all">Todos</option>';
        
        const uniqueProjects = [...new Set(projects.map(p => p.nombre))];
        uniqueProjects.forEach(name => {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = truncateText(name, 30);
            filterObjective.appendChild(option);
        });
        
        filterObjective.value = currentValue || 'all';
    }
    
    // Poblar responsables
    if (filterResponsible) {
        const currentValue = filterResponsible.value;
        filterResponsible.innerHTML = '<option value="all">Todos</option>';
        
        const uniqueResponsibles = [...new Set(projects.map(p => p.participante || 'Grupo'))];
        uniqueResponsibles.forEach(name => {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = truncateText(name, 25);
            filterResponsible.appendChild(option);
        });
        
        filterResponsible.value = currentValue || 'all';
    }
}

function applyFilters() {
    const filterObjective = document.getElementById('filterObjective')?.value || 'all';
    const filterStatus = document.getElementById('filterStatus')?.value || 'all';
    const filterResponsible = document.getElementById('filterResponsible')?.value || 'all';
    
    let filteredProjects = [...allProjectsData];
    let filteredObjectives = [...allObjectivesData];
    
    // Filtrar por proyecto/objetivo
    if (filterObjective !== 'all') {
        filteredProjects = filteredProjects.filter(p => p.nombre === filterObjective);
        filteredObjectives = filteredObjectives.filter(o => o.nombre === filterObjective);
    }
    
    // Filtrar por estado
    if (filterStatus !== 'all') {
        const statusLower = filterStatus.toLowerCase();
        filteredProjects = filteredProjects.filter(p => (p.estado || '').toLowerCase() === statusLower);
        filteredObjectives = filteredObjectives.filter(o => (o.estado || '').toLowerCase() === statusLower);
    }
    
    // Filtrar por responsable
    if (filterResponsible !== 'all') {
        filteredProjects = filteredProjects.filter(p => (p.participante || 'Grupo') === filterResponsible);
        filteredObjectives = filteredObjectives.filter(o => (o.participante || 'Grupo') === filterResponsible);
    }
    
    // Actualizar UI
    updateProjectsTable(filteredProjects);
    updateObjectivesTable(filteredObjectives);
    updateDoughnutFromProjects(filteredProjects);
    updateStatusBoxesFromProjects(filteredProjects);
    updateResponsibleChart(filteredProjects);
    updateObjectivesChart(filteredObjectives);
}

// ============================================
// AUTO-REFRESH
// ============================================
function startAutoRefresh() {
    stopAutoRefresh();
    autoRefreshInterval = setInterval(() => {
        if (isAutoRefreshActive) {
            refreshAllData();
        }
    }, ManagerDashboardConfig.REFRESH_INTERVAL);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

function refreshAllData() {
    loadDashboardStats();
    loadProjectsData();
    loadObjectivesData();
}

function setupVisibilityDetection() {
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            if (isAutoRefreshActive) {
                refreshAllData();
                startAutoRefresh();
            }
        }
    });
}

// ============================================
// UTILIDADES
// ============================================
function setText(elementId, value) {
    const el = document.getElementById(elementId);
    if (el) {
        el.textContent = value;
    }
}

function getStatusClass(status) {
    const statusLower = (status || '').toLowerCase();
    const classMap = {
        'completado': 'status-completed',
        'pendiente': 'status-notstarted',
        'vencido': 'status-delay',
        'en proceso': 'status-ongoing'
    };
    return classMap[statusLower] || 'status-notstarted';
}

function getProgressTier(progress) {
    const p = parseFloat(progress) || 0;
    if (p >= 75) return 'tier-great';
    if (p >= 50) return 'tier-good';
    if (p >= 25) return 'tier-medium';
    return 'tier-low';
}

function getProgressColor(progress) {
    const p = parseFloat(progress) || 0;
    if (p >= 75) return ManagerDashboardConfig.COLORS.completed;
    if (p >= 50) return '#28a745';
    if (p >= 25) return ManagerDashboardConfig.COLORS.notStarted;
    return ManagerDashboardConfig.COLORS.delay;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    
    const parts = dateString.split('-');
    if (parts.length === 3) {
        const date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
}

function truncateText(text, maxLength) {
    if (!text) return '-';
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

function showTableError(tableId, message) {
    const tbody = document.getElementById(tableId);
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger" style="padding:30px;">
                    <i class="mdi mdi-alert-circle" style="font-size:32px;"></i>
                    <p class="mt-2 mb-0">${message}</p>
                </td>
            </tr>`;
    }
}

// ============================================
// FUNCIONES GLOBALES (para onclick en HTML)
// ============================================
window.viewProjectDetails = function(projectId) {
    // Esta función está definida en manager_project_details.js
    if (typeof window.openProjectDetails === 'function') {
        window.openProjectDetails(projectId);
    } else {
        // Fallback si manager_project_details.js usa viewProjectDetails directamente
        const modal = document.getElementById('projectDetailsModal');
        if (modal) {
            document.getElementById('projectDetailsLoading').style.display = 'block';
            document.getElementById('projectDetailsContent').style.display = 'none';
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            fetchProjectDetailsForModal(projectId);
        }
    }
};

function fetchProjectDetailsForModal(projectId) {
    fetch(`../php/get_project_details.php?id=${projectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.proyecto) {
                displayProjectDetailsInModal(data.proyecto);
            } else {
                showModalError(data.message || 'Error al cargar proyecto');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showModalError('Error de conexión');
        });
}

function displayProjectDetailsInModal(proyecto) {
    document.getElementById('projectDetailsLoading').style.display = 'none';
    document.getElementById('projectDetailsContent').style.display = 'block';
    
    // Título y descripción
    document.getElementById('projectDetailTitle').textContent = proyecto.nombre;
    document.getElementById('detailProjectName').textContent = proyecto.nombre;
    document.getElementById('detailProjectDescription').textContent = proyecto.descripcion || 'Sin descripción';
    
    // Estado
    const statusBadge = document.getElementById('detailProjectStatus');
    const statusText = STATUS_MAP[proyecto.estado?.toLowerCase()] || proyecto.estado;
    statusBadge.textContent = statusText;
    statusBadge.className = 'badge fs-6 badge-' + getStatusBadgeColor(proyecto.estado);
    
    // Tipo
    const typeBadge = document.getElementById('detailProjectType');
    typeBadge.textContent = proyecto.tipo_proyecto?.nombre || (proyecto.tipo_proyecto?.id === 1 ? 'Grupal' : 'Individual');
    
    // Progreso
    const progressBar = document.getElementById('detailProgressBar');
    const progreso = proyecto.progreso || 0;
    progressBar.style.width = progreso + '%';
    progressBar.textContent = progreso + '%';
    progressBar.className = 'progress-bar ' + getProgressBarColor(progreso);
    
    // Estadísticas
    const stats = proyecto.estadisticas || {};
    document.getElementById('modalStatTotalTareas').textContent = stats.total_tareas || 0;
    document.getElementById('modalStatTareasCompletadas').textContent = stats.tareas_completadas || 0;
    document.getElementById('modalStatTareasEnProceso').textContent = stats.tareas_en_proceso || 0;
    document.getElementById('modalStatTareasVencidas').textContent = stats.tareas_vencidas || 0;
    
    // Información general
    document.getElementById('detailDepartamento').textContent = proyecto.departamento?.nombre || '-';
    document.getElementById('detailCreador').textContent = proyecto.creador?.nombre || '-';
    document.getElementById('detailFechaCreacion').textContent = formatDate(proyecto.fecha_creacion);
    document.getElementById('detailFechaLimite').textContent = formatDate(proyecto.fecha_cumplimiento);
    
    // Participante (solo individual)
    const participanteRow = document.getElementById('detailParticipanteRow');
    if (proyecto.tipo_proyecto?.id === 1) {
        participanteRow.style.display = 'none';
    } else {
        participanteRow.style.display = '';
        document.getElementById('detailParticipante').textContent = proyecto.participante?.nombre || 'Sin asignar';
    }
    
    // Usuarios asignados (solo grupal)
    const usuariosSection = document.getElementById('detailUsuariosSection');
    if (proyecto.tipo_proyecto?.id === 1 && proyecto.usuarios_asignados?.length > 0) {
        usuariosSection.style.display = '';
        displayModalUsers(proyecto.usuarios_asignados);
    } else {
        usuariosSection.style.display = 'none';
    }
    
    // Tareas
    displayModalTasks(proyecto.tareas || []);
    
    // Botón editar
    const btnEdit = document.getElementById('btnEditProject');
    if (btnEdit) {
        btnEdit.onclick = function() {
            window.location.href = '../nuevoProyectoGerente/?edit=' + proyecto.id_proyecto;
        };
    }
}

function displayModalUsers(usuarios) {
    const tbody = document.getElementById('detailUsuariosTableBody');
    const countEl = document.getElementById('detailUsuariosCount');
    
    if (countEl) countEl.textContent = usuarios.length;
    
    if (!usuarios || usuarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin usuarios asignados</td></tr>';
        return;
    }
    
    let html = '';
    usuarios.forEach(u => {
        const progressClass = getProgressBarColor(u.progreso || 0);
        html += `
            <tr>
                <td><strong>${escapeHtml(u.nombre_completo)}</strong></td>
                <td>${u.num_empleado || '-'}</td>
                <td><small>${escapeHtml(u.e_mail || '-')}</small></td>
                <td><span class="badge bg-secondary">${u.tareas_completadas || 0}/${u.tareas_asignadas || 0}</span></td>
                <td style="min-width:120px;">
                    <div class="progress" style="height:18px;">
                        <div class="progress-bar ${progressClass}" style="width:${u.progreso || 0}%;">${(u.progreso || 0).toFixed(0)}%</div>
                    </div>
                </td>
            </tr>`;
    });
    
    tbody.innerHTML = html;
}

function displayModalTasks(tareas) {
    const tbody = document.getElementById('detailTareasTableBody');
    const noTareasDiv = document.getElementById('detailNoTareas');
    
    if (!tareas || tareas.length === 0) {
        tbody.innerHTML = '';
        noTareasDiv.style.display = 'block';
        return;
    }
    
    noTareasDiv.style.display = 'none';
    
    let html = '';
    tareas.forEach(t => {
        const estadoClass = getStatusBadgeColor(t.estado);
        const estadoText = STATUS_MAP[t.estado?.toLowerCase()] || t.estado;
        
        html += `
            <tr>
                <td>
                    <strong>${escapeHtml(t.nombre)}</strong>
                    ${t.descripcion ? '<small class="text-muted d-block">' + truncateText(t.descripcion, 50) + '</small>' : ''}
                </td>
                <td>${escapeHtml(t.asignado_a || 'Sin asignar')}</td>
                <td>${formatDate(t.fecha_cumplimiento)}</td>
                <td><span class="badge badge-${estadoClass}">${estadoText}</span></td>
            </tr>`;
    });
    
    tbody.innerHTML = html;
}

function getStatusBadgeColor(status) {
    const statusLower = (status || '').toLowerCase();
    const colorMap = {
        'completado': 'success',
        'pendiente': 'warning',
        'vencido': 'danger',
        'en proceso': 'primary'
    };
    return colorMap[statusLower] || 'secondary';
}

function getProgressBarColor(progress) {
    const p = parseFloat(progress) || 0;
    if (p >= 70) return 'bg-success';
    if (p >= 40) return 'bg-warning';
    return 'bg-danger';
}

function showModalError(message) {
    const loading = document.getElementById('projectDetailsLoading');
    if (loading) {
        loading.innerHTML = `
            <div class="text-center py-5">
                <i class="mdi mdi-alert-circle-outline text-danger" style="font-size:3rem;"></i>
                <p class="mt-3 text-danger">${message}</p>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>`;
    }
}

// Exponer funciones globales
window.refreshAllData = refreshAllData;
window.loadDashboardStats = loadDashboardStats;
window.loadProjectsData = loadProjectsData;
window.loadObjectivesData = loadObjectivesData;

000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000

/**
 * manager_project_details.js
 * Manejo del modal de detalles de proyecto para el dashboard del gerente
 */

// Variable para almacenar los datos del proyecto actual
let currentProjectDetails = null;
let currentProjectTasks = [];
let projectDetailsModalInstance = null;

// ============================================
// INICIALIZACIÓN
// ============================================
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

// ============================================
// FUNCIONES PRINCIPALES
// ============================================

/**
 * Abre el modal y carga los detalles del proyecto
 * @param {number} projectId - ID del proyecto
 */
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

/**
 * Obtiene los detalles del proyecto desde el servidor
 * @param {number} projectId - ID del proyecto
 */
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

/**
 * Muestra los detalles del proyecto en el modal
 * @param {Object} proyecto - Datos del proyecto
 */
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
        const tipoNombre = proyecto.tipo_proyecto?.nombre || 
                          (proyecto.tipo_proyecto?.id === 1 ? 'Grupal' : 'Individual');
        typeBadge.textContent = tipoNombre;
    }

    // Barra de progreso
    const progressBar = document.getElementById('detailProgressBar');
    if (progressBar) {
        const progreso = proyecto.progreso || 0;
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

/**
 * Muestra la tabla de usuarios asignados
 * @param {Array} usuarios - Lista de usuarios
 */
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
        const progressClass = getProgressBarClass(usuario.progreso || 0);
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
                             style="width: ${usuario.progreso || 0}%;"
                             aria-valuenow="${usuario.progreso || 0}" aria-valuemin="0" aria-valuemax="100">
                            ${(usuario.progreso || 0).toFixed(0)}%
                        </div>
                    </div>
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;
}

/**
 * Muestra la tabla de tareas del proyecto
 * @param {Array} tareas - Lista de tareas
 * @param {string} filter - Filtro de estado (opcional)
 */
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
                pElement.textContent = filter === 'all' 
                    ? 'No hay tareas registradas en este proyecto' 
                    : `No hay tareas con estado "${filter}"`;
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

// ============================================
// ACCIONES
// ============================================

/**
 * Redirige a la página de edición del proyecto
 * @param {number} idProyecto - ID del proyecto
 */
function editarProyecto(idProyecto) {
    window.location.href = `../nuevoProyectoGerente/?edit=${idProyecto}`;
}

/**
 * Redirige desde el modal al editar
 */
function editarProyectoFromModal() {
    if (currentProjectDetails) {
        editarProyecto(currentProjectDetails.id_proyecto);
    }
}

/**
 * Filtra las tareas del proyecto por estado
 * @param {string} filter - Estado a filtrar
 */
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

// ============================================
// UTILIDADES
// ============================================

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

// ============================================
// EXPORTAR FUNCIONES GLOBALES
// ============================================
window.viewProjectDetails = viewProjectDetails;
window.editarProyecto = editarProyecto;
window.editarProyectoFromModal = editarProyectoFromModal;
window.filterProjectTasks = filterProjectTasks;

0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000

<?php
/**
 * manager_get_objectives_dashboard.php
 * Obtiene proyectos formateados como objetivos para el dashboard del gerente
 * Filtrado por departamentos gestionados
 */

header('Content-Type: application/json; charset=utf-8');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

$response = [
    'success' => false,
    'objetivos' => [],
    'message' => ''
];

try {
    // Verificar autenticación
    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }
    
    require_once('db_config.php');
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener roles del usuario
    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento,
            ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param('i', $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    $is_admin = false;
    $departamentos_gerente = [];
    
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        if ($row['id_rol'] == 2) {
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
    
    // Verificar que tiene rol de gerente o admin
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('Acceso no autorizado - Se requiere rol de gerente');
    }
    
    // Construir consulta según rol
    if ($is_admin) {
        // Admin ve todos los proyectos
        $query = "
            SELECT 
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.progreso,
                p.estado,
                p.id_tipo_proyecto,
                p.fecha_cumplimiento,
                CONCAT(u.nombre, ' ', u.apellido) as participante,
                tp.nombre as tipo_nombre
            FROM tbl_proyectos p
            LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
            LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto
            ORDER BY p.progreso DESC, p.nombre ASC
        ";
        
        $stmt = $conn->prepare($query);
    } else {
        // Gerente ve proyectos de sus departamentos y relacionados
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        
        $query = "
            SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.progreso,
                p.estado,
                p.id_tipo_proyecto,
                p.fecha_cumplimiento,
                CONCAT(u.nombre, ' ', u.apellido) as participante,
                tp.nombre as tipo_nombre
            FROM tbl_proyectos p
            LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
            LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            WHERE (
                p.id_departamento IN ($placeholders)
                OR p.id_creador = ?
                OR p.id_participante = ?
                OR pu.id_usuario = ?
            )
            ORDER BY p.progreso DESC, p.nombre ASC
        ";
        
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $types = str_repeat('i', count($departamentos_gerente)) . 'iii';
            $params = array_merge($departamentos_gerente, [$id_usuario, $id_usuario, $id_usuario]);
            $stmt->bind_param($types, ...$params);
        }
    }
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $objetivos = [];
    
    while ($row = $result->fetch_assoc()) {
        // Mapear tipo de proyecto a tipo de objetivo
        $tipo = 'Global'; // Default para grupales
        if ((int)$row['id_tipo_proyecto'] === 2) {
            $tipo = 'Regional'; // Individual
        }
        
        $objetivos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado'],
            'tipo' => $tipo,
            'tipo_nombre' => $row['tipo_nombre'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'participante' => $row['participante'] ?: 'Grupo'
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    $response['success'] = true;
    $response['objetivos'] = $objetivos;
    $response['total'] = count($objetivos);
    $response['managed_departments'] = $departamentos_gerente;
    $response['is_admin'] = $is_admin;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_objectives_dashboard.php Error: ' . $e->getMessage());
}

ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();
?>

000000000000000000000000000000000000000000000000000000000000000000000

<?php
/**
 * manager_get_top_projects_progress.php
 * Obtiene los 5 proyectos con mayor progreso para el dashboard del gerente
 * Filtrado por departamentos gestionados usando tbl_usuario_roles
 */

header('Content-Type: application/json; charset=utf-8');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

$response = [
    'success' => false,
    'proyectos' => [],
    'message' => ''
];

try {
    // Verificar autenticación
    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }
    
    require_once('db_config.php');
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener roles del usuario desde tbl_usuario_roles
    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento,
            ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param('i', $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    $is_admin = false;
    $departamentos_gerente = [];
    
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        if ($row['id_rol'] == 2) {
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
    
    // Verificar que tiene rol de gerente o admin
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('Acceso no autorizado - Se requiere rol de gerente');
    }
    
    ob_clean();
    
    // Construir consulta según rol
    if ($is_admin) {
        // Admin ve todos los proyectos
        $sql = "
            SELECT 
                p.id_proyecto,
                p.nombre,
                p.progreso,
                p.estado,
                COUNT(t.id_tarea) as total_tareas,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
            FROM tbl_proyectos p
            LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
            WHERE p.estado != 'completado'
            GROUP BY p.id_proyecto, p.nombre, p.progreso, p.estado
            ORDER BY p.progreso DESC, p.estado ASC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($sql);
    } else {
        // Gerente ve proyectos de sus departamentos
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        
        $sql = "
            SELECT 
                p.id_proyecto,
                p.nombre,
                p.progreso,
                p.estado,
                COUNT(t.id_tarea) as total_tareas,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
            FROM tbl_proyectos p
            LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            WHERE p.estado != 'completado'
                AND (
                    p.id_departamento IN ($placeholders)
                    OR p.id_creador = ?
                    OR p.id_participante = ?
                    OR pu.id_usuario = ?
                )
            GROUP BY p.id_proyecto, p.nombre, p.progreso, p.estado
            ORDER BY p.progreso DESC, p.estado ASC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $types = str_repeat('i', count($departamentos_gerente)) . 'iii';
            $params = array_merge($departamentos_gerente, [$id_usuario, $id_usuario, $id_usuario]);
            $stmt->bind_param($types, ...$params);
        }
    }
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $proyectos = [];
    
    while ($row = $result->fetch_assoc()) {
        $total_tareas = (int)$row['total_tareas'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        
        // Calcular progreso basado en tareas si hay tareas
        if ($total_tareas > 0) {
            $progreso_calculado = round(($tareas_completadas / $total_tareas) * 100, 1);
        } else {
            $progreso_calculado = (float)$row['progreso'];
        }
        
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'progreso' => $progreso_calculado,
            'estado' => $row['estado'],
            'total_tareas' => $total_tareas,
            'tareas_completadas' => $tareas_completadas
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['managed_departments'] = $departamentos_gerente;
    $response['is_admin'] = $is_admin;
    
    if (empty($proyectos)) {
        $response['message'] = 'No hay proyectos en progreso en los departamentos';
    }

} catch (Exception $e) {
    ob_clean();
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_top_projects_progress.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();
?>