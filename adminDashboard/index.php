<?php 
/*adminDashboard.php para el Dashboard principal de admin*/ 
require_once('../php/check_auth.php'); 
?> 
<!DOCTYPE html> 
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<title>Administrador de proyectos</title>
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
					<!--meter nuevo navbar aqui--> 
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
									<li class="nav-item"><a class="nav-link" href="../gestionDeEmpleados/">Gestion de empleados</a></li>
									<li class="nav-item"><a class="nav-link" href="../registroDeEmpleados">Registrar nuevo empleado</a></li>
								</ul>
							</div>
						</li>
						<li class="nav-item">
							<a class="nav-link" data-bs-toggle="collapse" href="#departamentos" aria-expanded="false" aria-controls="ui-basic"> 
							<i class="menu-icon mdi mdi-view-week"></i> 
							<span class="menu-title">Departamentos</span> 
							<i class="menu-arrow"></i> 
							</a> 
							<div class="collapse" id="departamentos">
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../gestionDeDepartamentos/">Gestion de departamentos</a></li>
									<li class="nav-item"><a class="nav-link" href="../registroDeDepartamentos">Registrar departamento</a></li>
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
									<li class="nav-item"><a class="nav-link" href="../nuevoProyecto/">Crear nuevo proyecto</a></li>
									<li class="nav-item"><a class="nav-link" href="../nuevoObjetivo/">Crear nuevo objetivo</a></li>
									<li class="nav-item"><a class="nav-link" href="../nuevoTarea/">Crear nueva tarea</a></li>
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
									<li class="nav-item"><a class="nav-link" href="../revisarGraficos">Revisar graficos</a></li>
									<li class="nav-item"><a class="nav-link" href="../graficaGantt">Gráfica de Gantt</a></li>
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
									<li class="nav-item"><a class="nav-link" href="../revisarProyectos/">Revisar proyectos</a></li>
								</ul>
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../revisarObjetivos/">Revisar objetivos</a></li>
								</ul>
								<ul class="nav flex-column sub-menu">
									<li class="nav-item"><a class="nav-link" href="../revisarTareas/">Revisar tareas</a></li>
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
						<!-- segunda fila estadisticas de resumen y pestanias--> 
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
														<p class="mt-2 mb-0" style="font-size:0.8rem;">Cargando objetivos...</p>
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
											<h3 id="statTotalTareas" class="mb-0 mt-2">0</h3>
											<small class="text-muted">Total Tareas</small>
										</div>
									</div>
								</div>
								<div class="col-md-3 col-6 mb-3">
									<div class="card bg-light h-100">
										<div class="card-body text-center py-3">
											<i class="mdi mdi-check-circle-outline text-success" style="font-size:2rem;"></i>
											<h3 id="statTareasCompletadas" class="mb-0 mt-2">0</h3>
											<small class="text-muted">Completadas</small>
										</div>
									</div>
								</div>
								<div class="col-md-3 col-6 mb-3">
									<div class="card bg-light h-100">
										<div class="card-body text-center py-3">
											<i class="mdi mdi-progress-clock text-info" style="font-size:2rem;"></i>
											<h3 id="statTareasEnProceso" class="mb-0 mt-2">0</h3>
											<small class="text-muted">En Proceso</small>
										</div>
									</div>
								</div>
								<div class="col-md-3 col-6 mb-3">
									<div class="card bg-light h-100">
										<div class="card-body text-center py-3">
											<i class="mdi mdi-alert-circle-outline text-danger" style="font-size:2rem;"></i>
											<h3 id="statTareasVencidas" class="mb-0 mt-2">0</h3>
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
		<script src="../js/dashboard_overview.js"></script> 
		<script src="../js/custom_dialogs.js"></script> 
		<script src="../js/notifications.js"></script> 
		<script src="../js/datetime_widget.js"></script> 
		<script src="../js/ppe_chart_click.js"></script> 
		<script src="../js/project_details.js"></script> 
	</body>
</html>