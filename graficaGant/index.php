 <?php 
/*Vista de Gantt para tareas de proyectos*/ 
require_once('../php/check_auth.php'); 
$user_name = $_SESSION['nombre']; 
$user_apellido = $_SESSION['apellido']; 
$user_email = $_SESSION['e_mail']; 
$user_id = $_SESSION['user_id']; 
?>
 <!DOCTYPE html>
 <html lang="es">
 	<head>
 		<meta charset="utf-8">
 		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
 		<title>Diagrama de Gantt - Proyectos</title>
 		<!-- plugins:css -->
 		<link rel="stylesheet" href="../vendors/feather/feather.css">
 		<link rel="stylesheet" href="../vendors/mdi/css/materialdesignicons.min.css">
 		<link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
 		<link rel="stylesheet" href="../vendors/typicons/typicons.css">
 		<link rel="stylesheet" href="../vendors/simple-line-icons/css/simple-line-icons.css">
 		<link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
 		<!-- inject:css -->
 		<link rel="stylesheet" href="../css/vertical-layout-light/style.css">
 		<link rel="shortcut icon" href="../images/Nidec Institutional Logo_Original Version.png" />
 	</head>
 	<body>
 		<div class="container-scroller">
 			<!-- Navbar -->
 			<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
 				<div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
 					<div class="me-3">
 						<button class="navbar-toggler navbar-toggler align-self-center" type="button" data-bs-toggle="minimize">
 							<span class="icon-menu"></span>
 						</button>
 					</div>
 					<div>
 						<a class="navbar-brand brand-logo" href="../adminDashboard/">
 							<img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
 						</a>
 						<a class="navbar-brand brand-logo-mini" href="../adminDashboard/">
 							<img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
 						</a>
 					</div>
 				</div>
 				<div class="navbar-menu-wrapper d-flex align-items-top">
 					<ul class="navbar-nav">
 						<li class="nav-item font-weight-semibold d-none d-lg-block ms-0">
 							<h1 class="welcome-text">Buenos días, <span class="text-black fw-bold"> <?php echo htmlspecialchars($user_name); ?> </span>
 							</h1>
 							<h3 class="welcome-sub-text">Visualiza el progreso de las tareas en el diagrama de Gantt</h3>
 						</li>
 					</ul>
 					<ul class="navbar-nav ms-auto">
 						<li class="nav-item dropdown">
 							<a class="nav-link count-indicator" id="countDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
 								<i class="icon-bell"></i>
 								<span class="count" style="display: none;"></span>
 							</a>
 							<div class="dropdown-menu dropdown-menu-right navbar-dropdown notification-dropdown pb-0" aria-labelledby="countDropdown">
 								<div class="dropdown-header d-flex justify-content-between align-items-center py-3 border-bottom">
 									<span class="font-weight-semibold">Notificaciones</span>
 									<a href="javascript:void(0)" id="markAllNotificationsRead" class="text-primary small">
 										<i class="mdi mdi-check-all me-1"></i>Marcar todas como leídas </a>
 								</div>
 								<div id="notificationsContainer" style="max-height: 350px; overflow-y: auto;">
 									<div class="notification-loading py-4 text-center">
 										<div class="spinner-border spinner-border-sm text-primary" role="status">
 											<span class="visually-hidden">Cargando...</span>
 										</div>
 										<p class="mt-2 mb-0 text-muted small">Cargando notificaciones...</p>
 									</div>
 								</div>
 							</div>
 						</li>
 						<li class="nav-item dropdown d-none d-lg-block user-dropdown">
 							<a class="nav-link" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
 								<i class="mdi mdi-account" alt="profile icon"></i>
 							</a>
 							<div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
 								<div class="dropdown-header text-center">
 									<p class="mb-1 mt-3 font-weight-semibold"> <?php echo htmlspecialchars($user_name . ' ' . $user_apellido); ?> </p>
 									<p class="fw-light text-muted mb-0"> <?php echo htmlspecialchars($user_email); ?> </p>
 								</div>
 								<a class="dropdown-item" href="../php/logout.php">
 									<i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Cerrar sesión </a>
 							</div>
 						</li>
 					</ul>
 					<button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-bs-toggle="offcanvas">
 						<span class="mdi mdi-menu"></span>
 					</button>
 				</div>
 			</nav>
 			<!-- End Navbar -->
 			<div class="container-fluid page-body-wrapper">
 				<!-- Sidebar -->
 				<nav class="sidebar sidebar-offcanvas" id="sidebar">
 					<ul class="nav">
 						<li class="nav-item nav-category">Gestión de usuarios</li>
 						<li class="nav-item">
 							<a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
 								<i class="menu-icon mdi mdi-account-multiple"></i>
 								<span class="menu-title">Empleados</span>
 								<i class="menu-arrow"></i>
 							</a>
 							<div class="collapse" id="ui-basic">
 								<ul class="nav flex-column sub-menu">
 									<li class="nav-item">
 										<a class="nav-link" href="../gestionDeEmpleados/">Gestión de empleados</a>
 									</li>
 									<li class="nav-item">
 										<a class="nav-link" href="../registroDeEmpleados">Registrar nuevo empleado</a>
 									</li>
 								</ul>
 							</div>
 						</li>
 						<li class="nav-item">
 							<a class="nav-link" data-bs-toggle="collapse" href="#departamentos" aria-expanded="false" aria-controls="departamentos">
 								<i class="menu-icon mdi mdi-view-week"></i>
 								<span class="menu-title">Departamentos</span>
 								<i class="menu-arrow"></i>
 							</a>
 							<div class="collapse" id="departamentos">
 								<ul class="nav flex-column sub-menu">
 									<li class="nav-item">
 										<a class="nav-link" href="../gestionDeDepartamentos/">Gestión de departamentos</a>
 									</li>
 									<li class="nav-item">
 										<a class="nav-link" href="../registroDeDepartamentos">Registrar departamento</a>
 									</li>
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
 									<li class="nav-item">
 										<a class="nav-link" href="../nuevoProyecto/">Crear nuevo proyecto</a>
 									</li>
 									<li class="nav-item">
 										<a class="nav-link" href="../nuevoObjetivo/">Crear nuevo objetivo</a>
 									</li>
 									<li class="nav-item">
 										<a class="nav-link" href="../nuevoTarea/">Crear nueva tarea</a>
 									</li>
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
 									<li class="nav-item">
 										<a class="nav-link" href="../revisarGraficos">Revisar gráficos</a>
 									</li>
 									<li class="nav-item">
 										<a class="nav-link" href="../ganttProyectos/">Diagrama de Gantt</a>
 									</li>
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
 									<li class="nav-item">
 										<a class="nav-link" href="../revisarProyectos/">Revisar proyectos</a>
 									</li>
 									<li class="nav-item">
 										<a class="nav-link" href="../revisarObjetivos/">Revisar objetivos</a>
 									</li>
 									<li class="nav-item">
 										<a class="nav-link" href="../revisarTareas/">Revisar tareas</a>
 									</li>
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
 									<li class="nav-item">
 										<a class="nav-link" href="../php/logout.php">Cerrar Sesión</a>
 									</li>
 								</ul>
 							</div>
 						</li>
 					</ul>
 				</nav>
 				<!-- End Sidebar -->
 				<div class="main-panel">
 					<div class="content-wrapper">
 						<!-- Alert Container -->
 						<div id="alertContainer"></div>
 						<!-- seccion de seleccion de proyecto -->
 						<div class="row">
 							<div class="col-12 grid-margin">
 								<div class="card">
 									<div class="card-body">
 										<h4 class="card-title">
 											<i class="mdi mdi-chart-gantt me-2"></i>Diagrama de Gantt
 										</h4>
 										<p class="card-description"> Visualice las tareas del proyecto en una línea de tiempo </p>
 										<hr>
 										<!-- Controls Row -->
 										<div class="row md-12">
 											<div class="col-md-4">
 												<label class="form-label fw-bold">Seleccione el proyecto</label>
 												<select class="form-control" id="id_proyecto" name="id_proyecto">
 													<option value="">Seleccione un proyecto</option>
 												</select>
 											</div>
 											<div class="col-md-3">
 												<label class="form-label fw-bold">Vista</label>
 												<select class="form-control" id="ganttViewMode">
 													<option value="day">Días</option>
 													<option value="week" selected>Semanas</option>
 													<option value="month">Meses</option>
 												</select>
 											</div>
 											<div class="col-md-3">
 												<label class="form-label fw-bold">Agrupar por</label>
 												<select class="form-control" id="ganttGroupBy">
 													<option value="user" selected>Usuario</option>
 													<option value="status">Estado</option>
 													<option value="none">Sin agrupar</option>
 												</select>
 											</div>
 											<div class="col-md-2 d-flex align-items-end">
 												<button class="btn btn-outline-primary w-100" id="btnTodayGantt" title="Ir a hoy">
 													<i class="mdi mdi-calendar-today me-1"></i>Hoy </button>
 											</div>
 										</div>
 										<!-- Project Info Summary -->
 										<div id="projectInfoSummary" class="row mb-3" style="display: none;">
 											<div class="col-12">
 												<div class="alert alert-info py-2 mb-0">
 													<div class="d-flex flex-wrap justify-content-between align-items-center">
 														<span>
 															<i class="mdi mdi-folder-open me-1"></i>
 															<strong id="summaryProjectName">-</strong>
 														</span>
 														<span>
 															<i class="mdi mdi-calendar-range me-1"></i>
 															<span id="summaryDateRange">-</span>
 														</span>
 														<span>
 															<i class="mdi mdi-format-list-checks me-1"></i>
 															<span id="summaryTaskCount">0</span> tareas </span>
 														<span>
 															<i class="mdi mdi-progress-check me-1"></i> Progreso: <span id="summaryProgress">0</span>% </span>
 													</div>
 												</div>
 											</div>
 										</div>
 										<!-- Legend -->
 										<div class="row mb-3">
 											<div class="col-12">
 												<div class="gantt-legend d-flex flex-wrap gap-3">
 													<span class="legend-item">
 														<span class="legend-color bg-warning"></span> Pendiente </span>
 													<span class="legend-item">
 														<span class="legend-color bg-info"></span> En Proceso </span>
 													<span class="legend-item">
 														<span class="legend-color bg-success"></span> Completado </span>
 													<span class="legend-item">
 														<span class="legend-color bg-danger"></span> Vencido </span>
 													<span class="legend-item">
 														<span class="legend-color today-marker"></span> Hoy </span>
 												</div>
 											</div>
 										</div>
 									</div>
 								</div>
 							</div>
 						</div>
 						<!-- Gantt Chart Container -->
 						<div class="row">
 							<div class="col-12 grid-margin">
 								<div class="card">
 									<div class="card-body p-0">
 										<!-- Loading Spinner -->
 										<div id="ganttLoading" class="text-center py-5" style="display: none;">
 											<div class="spinner-border text-primary" role="status">
 												<span class="visually-hidden">Cargando...</span>
 											</div>
 											<p class="mt-2 text-muted">Cargando diagrama de Gantt...</p>
 										</div>
 										<!-- Default Message -->
 										<div id="ganttDefaultMessage" class="text-center py-5">
 											<i class="mdi mdi-chart-gantt mdi-48px text-muted"></i>
 											<p class="mt-2 text-muted">Seleccione un proyecto para visualizar el diagrama de Gantt</p>
 										</div>
 										<!-- No Tasks Message -->
 										<div id="ganttNoTasks" class="text-center py-5" style="display: none;">
 											<i class="mdi mdi-clipboard-text-off mdi-48px text-muted"></i>
 											<p class="mt-2 text-muted">Este proyecto no tiene tareas asignadas</p>
 										</div>
 										<!-- Gantt Chart -->
 										<div id="ganttChartWrapper" class="gantt-wrapper" style="display: none;">
 											<div id="ganttChart" class="gantt-container"></div>
 										</div>
 									</div>
 								</div>
 							</div>
 						</div>
 					</div>
 					<!-- content-wrapper ends -->
 				</div>
 				<!-- main-panel ends -->
 			</div>
 			<!-- page-body-wrapper ends -->
 		</div>
 		<!-- container-scroller -->
 		<!-- Task Detail Modal -->
 		<div class="modal fade" id="taskDetailModal" tabindex="-1" role="dialog" aria-labelledby="taskDetailModalLabel" aria-hidden="true">
 			<div class="modal-dialog modal-dialog-centered" role="document">
 				<div class="modal-content">
 					<div class="modal-header">
 						<h5 class="modal-title" id="taskDetailModalLabel">
 							<i class="mdi mdi-clipboard-text me-2"></i>Detalle de Tarea
 						</h5>
 						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
 					</div>
 					<div class="modal-body">
 						<div class="task-detail-content">
 							<div class="mb-3">
 								<label class="form-label text-muted small">Nombre de la tarea</label>
 								<p class="fw-bold mb-0" id="modalTaskName">-</p>
 							</div>
 							<div class="mb-3">
 								<label class="form-label text-muted small">Descripción</label>
 								<p class="mb-0" id="modalTaskDescription">-</p>
 							</div>
 							<div class="row">
 								<div class="col-6 mb-3">
 									<label class="form-label text-muted small">Fecha de vencimiento</label>
 									<p class="mb-0" id="modalTaskDate">-</p>
 								</div>
 								<div class="col-6 mb-3">
 									<label class="form-label text-muted small">Estado</label>
 									<p class="mb-0">
 										<span id="modalTaskStatus" class="badge">-</span>
 									</p>
 								</div>
 							</div>
 							<div class="mb-3">
 								<label class="form-label text-muted small">Asignado a</label>
 								<p class="mb-0" id="modalTaskAssignee">-</p>
 							</div>
 							<div class="mb-0">
 								<label class="form-label text-muted small">Proyecto</label>
 								<p class="mb-0" id="modalTaskProject">-</p>
 							</div>
 						</div>
 					</div>
 					<div class="modal-footer">
 						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
 						<a href="#" class="btn btn-primary" id="modalEditTaskBtn">
 							<i class="mdi mdi-pencil me-1"></i>Editar Tarea </a>
 					</div>
 				</div>
 			</div>
 		</div>
 		<!-- plugins:js -->
 		<script src="../vendors/js/vendor.bundle.base.js"></script>
 		<!-- inject:js -->
 		<script src="../js/template.js"></script>
 		<script src="../js/hoverable-collapse.js"></script>
 		<!-- Pass session data to JavaScript -->
 		<script>
 			window.APP_CONFIG = {
 				userId: <?php echo intval($user_id); ?> ,
 				userName : <?php echo json_encode($user_name, JSON_HEX_TAG | JSON_HEX_AMP); ?> ,
 				userApellido : <?php echo json_encode($user_apellido, JSON_HEX_TAG | JSON_HEX_AMP); ?> ,
 				userEmail : <?php echo json_encode($user_email, JSON_HEX_TAG | JSON_HEX_AMP); ?>
 			};
 		</script>
 		<!-- Custom js for Gantt -->
 		<script src="../js/gantt_chart.js"></script>
 		<script src="../js/notifications.js"></script>
 	</body>
 </html>