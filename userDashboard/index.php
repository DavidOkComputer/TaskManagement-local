<?php
/*userDashboard.php para el Dashboard principal de usuario*/
require_once('../php/check_auth.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Administrador de proyectos </title>
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
  <link rel="shortcut icon" href="../images/Nidec Institutional Logo_Original Version.png" />
</head>
<body>
  <div class="container-scroller"> 
    <!-- partial:partials/_navbar.html -->
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
        <ul class="navbar-nav">
          <li class="nav-item font-weight-semibold d-none d-lg-block ms-0">
            <h1 class="welcome-text">Buenos dias, <span class="text-black fw-bold">
              <?php
                echo $_SESSION['nombre'];
              ?>
            </span></h1>
            <h3 class="welcome-sub-text">Tu resumen de esta semana </h3>
          </li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown"> 
              <a class="nav-link count-indicator" id="countDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="icon-bell"></i>
                  <span class="count" style="display: none;"></span>
              </a>
              <div class="dropdown-menu dropdown-menu-right navbar-dropdown notification-dropdown pb-0" aria-labelledby="countDropdown">
                  <!-- Header del dropdown -->
                  <div class="dropdown-header d-flex justify-content-between align-items-center py-3 border-bottom">
                      <span class="font-weight-semibold">Notificaciones</span>
                      <a href="javascript:void(0)" id="markAllNotificationsRead" class="text-primary small">
                          <i class="mdi mdi-check-all me-1"></i>Marcar todas como leídas
                      </a>
                  </div>
                  <!-- Contenedor de notificaciones (se llena dinámicamente) -->
                  <div id="notificationsContainer" style="max-height: 350px; overflow-y: auto;">
                      <!-- Loading state -->
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
                <p class="mb-1 mt-3 font-weight-semibold">
                  <?php
                    echo $_SESSION['nombre'];
                    echo ' ';
                    echo $_SESSION['apellido'];
                  ?>
                </p>
                <p class="fw-light text-muted mb-0">
                  <?php
                    echo $_SESSION['e_mail'];
                  ?>
                </p>
              </div>
              <a class="dropdown-item" href="../php/logout.php"><i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Cerrar sesión</a>
            </div>
          </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-bs-toggle="offcanvas">
          <span class="mdi mdi-menu"></span>
        </button>
      </div>
    </nav>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_settings-panel.html -->
      <div id="right-sidebar" class="settings-panel">
        <i class="settings-close ti-close"></i>
        <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="todo-tab" data-bs-toggle="tab" href="" role="tab" aria-controls="todo-section" aria-expanded="true">Lista de que hacer</a>
          </li>
        </ul>
      </div>
      <!-- partial -->
      <!-- partial:partials/_sidebar.html -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">
          <li class="nav-item nav-category">Proyectos</li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#form-elements" aria-expanded="false" aria-controls="form-elements">
              <i class="menu-icon mdi mdi-folder-upload"></i>
              <span class="menu-title">Crear proyecto</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="form-elements">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"><a class="nav-link" href="../nuevoProyectoUser/">Crear nuevo proyecto</a></li>
                <li class="nav-item"><a class="nav-link" href="../nuevoObjetivoUser/">Crear nuevo objetivo</a></li>
                <li class="nav-item"><a class="nav-link" href="../nuevoTareaUser/">Crear nueva tarea</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../revisarGraficosUser">Revisar graficos</a></li>
                <li class="nav-item"> <a class="nav-link" href="../graficaGanttUser">Gráfica de Gantt</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../revisarProyectosUser/">Revisar proyectos</a></li>
              </ul>
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"> <a class="nav-link" href="../revisarObjetivosUser/">Revisar objetivos</a></li>
              </ul>
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"> <a class="nav-link" href="../revisarTareasUser/">Revisar tareas</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../php/logout.php"> Cerrar Sesión </a></li>
              </ul>
            </div>
          </li>
        </ul>
      </nav>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-sm-12">
              <div class="home-tab">
                <div class="d-sm-flex align-items-center justify-content-between border-bottom">
                  <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link active ps-0" id="home-tab" data-bs-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true">Resumen</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="profile-tab" href="../proyectosTotalesUser" role="tab" aria-selected="false">Proyectos totales</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="contact-tab" href="../proyectosPendientesUser" role="tab" aria-selected="false">Proyectos pendientes</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link border-0" id="more-tab"  href="../proyectosVencidosUser" role="tab" aria-selected="false">Proyectos vencidos</a>
                    </li>
                  </ul>
                </div>
                <div class="tab-content tab-content-basic">
                  <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview"> 
                    <div class="row">
                      <div class="col-sm-12">
                        <div class="statistics-details d-flex align-items-center justify-content-between">
                          <div>
                            <p class="statistics-title">Mis Proyectos</p>
                            <h3 class="rate-percentage" id="stat-mis-proyectos">0</h3>
                            <p class="text-muted d-flex"><span>Proyectos asignados</span></p>
                          </div>
                          <div>
                            <p class="statistics-title">Mis Tareas</p>
                            <h3 class="rate-percentage" id="stat-mis-tareas">0</h3>
                            <p class="text-muted d-flex"><span>Total de tareas</span></p>
                          </div>
                          <div>
                            <p class="statistics-title">Tareas Completadas</p>
                            <h3 class="rate-percentage" id="stat-tareas-completadas">0%</h3>
                            <p class="text-success d-flex"><i class="mdi mdi-check-circle"></i><span>Completadas</span></p>
                          </div>
                          <div class="d-none d-md-block">
                            <p class="statistics-title">Tareas Pendientes</p>
                            <h3 class="rate-percentage" id="stat-tareas-pendientes">0</h3>
                            <p class="text-warning d-flex"><i class="mdi mdi-clock"></i><span>Por hacer</span></p>
                          </div>
                          <div class="d-none d-md-block">
                            <p class="statistics-title">Tareas Vencidas</p>
                            <h3 class="rate-percentage" id="stat-tareas-vencidas">0</h3>
                            <p class="text-danger d-flex"><i class="mdi mdi-alert"></i><span>Retrasadas</span></p>
                          </div>
                        </div>
                      </div>
                    </div> 
                    <div class="row">
                      <!-- Mi progreso en proyectos -->
                      <div class="col-sm-6 grid-margin stretch-card">
                        <div class="card">
                          <div class="card-body">
                            <h4 class="card-title">Mi Progreso en Proyectos</h4>
                            <p class="card-description">Tus tareas completadas por proyecto</p>
                            <div class="table-responsive pt-3">
                              <table class="table table-bordered table-hover">
                                <thead>
                                  <tr>
                                    <th style="width: 50%;">Proyecto</th>
                                    <th style="width: 50%;">Mi Progreso</th>
                                  </tr>
                                </thead>
                                <tbody id="misProyectosProgresoTableBody">
                                  <tr>
                                    <td colspan="2" class="text-center py-4">
                                      <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                      </div>
                                      <p class="mt-2">Cargando progreso...</p>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Mis tareas recientes -->
                      <div class="col-sm-6 grid-margin stretch-card">
                        <div class="card">
                          <div class="card-body">
                            <h4 class="card-title">Mis Tareas Recientes</h4>
                            <p class="card-description">Últimas tareas asignadas</p>
                            <div class="table-responsive pt-3">
                              <table class="table table-bordered table-hover">
                                <thead>
                                  <tr>
                                    <th style="width: 70%;">Tarea</th>
                                    <th style="width: 30%;">Estado</th>
                                  </tr>
                                </thead>
                                <tbody id="misTareasTableBody">
                                  <tr>
                                    <td colspan="2" class="text-center py-4">
                                      <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                      </div>
                                      <p class="mt-2">Cargando tareas...</p>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <!-- Gráfico de dona de tareas -->
                      <div class="col-sm-6 grid-margin stretch-card">
                        <div class="card">
                          <div class="card-body">
                            <h4 class="card-title">Mis Tareas por Estado</h4>
                            <div class="chart-container" style="width: 100%; display: flex; flex-direction: column;">
                              <div class="chart-wrapper" style="flex: 1; display: flex; justify-content: center; align-items: center; min-height: 250px;">
                                <canvas id="doughnutChart" style="max-width: 100%; max-height: 250px;"></canvas>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Tabla de todos los proyectos del departamento -->
                      <div class="col-lg-6 grid-margin-stretch-card">
                        <div class="card">
                          <div class="card-body">
                            <h4 class="card-title">Proyectos del Departamento</h4>
                            <p class="card-description">Resumen de proyectos activos</p>
                            <div class="table-responsive mt-3">
                              <table class="table table-hover">
                                <thead>
                                  <tr>
                                    <th>Proyecto</th>
                                    <th>Estado</th>
                                    <th>Progreso</th>
                                  </tr>
                                </thead>
                                <tbody id="topProjectsTableBody">
                                  <tr>
                                    <td colspan="3" class="text-center py-4">
                                      <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                      </div>
                                      <p class="mt-2">Cargando proyectos...</p>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-lg-12 grid-margin-stretch-card">
                        <div class="card">
                          <div class="card-body">
                            <h4 class="card-title">Detalles de los proyectos</h4>
                            <div class="table-responsive mt-3">
                              <table class="table select-table">
                                <thead>
                                  <tr>            
                                    <th class="sortable-header" data-sort="id_proyecto" style="cursor: pointer; user-select: none;">
                                      # <i class="mdi mdi-sort-variant"></i>
                                    </th>
                                    <th class="sortable-header" data-sort="nombre" style="cursor: pointer; user-select: none;">
                                      Título <i class="mdi mdi-sort-variant"></i>
                                    </th>
                                    <th class="sortable-header" data-sort="descripcion" style="cursor: pointer; user-select: none;">
                                      Descripción <i class="mdi mdi-sort-variant"></i>
                                    </th>
                                    <th class="sortable-header" data-sort="fecha_cumplimiento" style="cursor: pointer; user-select: none;">
                                      Fecha de entrega <i class="mdi mdi-sort-variant"></i>
                                    </th>
                                    <th class="sortable-header" data-sort="progreso" style="cursor: pointer; user-select: none;">
                                      Progreso <i class="mdi mdi-sort-variant"></i>
                                    </th>
                                    <th class="sortable-header" data-sort="estado" style="cursor: pointer; user-select: none;">
                                      Estado <i class="mdi mdi-sort-variant"></i>
                                    </th>
                                    <th class="sortable-header" data-sort="participante" style="cursor: pointer; user-select: none;">
                                      Responsable <i class="mdi mdi-sort-variant"></i>
                                    </th>
                                  </tr>
                                </thead>
                                <tbody id="proyectosTableBody">
                                  <tr>
                                    <td colspan="7" class="text-center">
                                      <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                      </div>
                                      <p class="mt-2">Cargando proyectos...</p>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                            <!-- Paginación -->
                            <div class="pagination-container mt-4"></div>
                          </div>  
                        </div>  
                      </div>
                    </div>
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

  <!-- Modal para detalles del proyecto -->
  <div class="modal fade" id="projectDetailsModal" tabindex="-1" aria-labelledby="projectDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="projectDetailsLabel">
            <i class="mdi mdi-folder-open me-2"></i>
            <span id="projectDetailTitle">Detalles del Proyecto</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="projectDetailsBody">
          <!-- Contenido dinámico -->
          <div class="text-center py-5" id="projectDetailsLoading">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3">Cargando información del proyecto...</p>
          </div>
          
          <div id="projectDetailsContent" style="display: none;">
            <!-- Header con información principal -->
            <div class="row mb-4">
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                  <div>
                    <h4 id="detailProjectName" class="mb-1"></h4>
                    <p id="detailProjectDescription" class="text-muted mb-2"></p>
                  </div>
                  <div class="text-end">
                    <span id="detailProjectStatus" class="badge fs-6"></span>
                    <span id="detailProjectType" class="badge bg-secondary fs-6 ms-2"></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Barra de progreso principal -->
            <div class="row mb-4">
              <div class="col-12">
                <label class="form-label fw-bold">Progreso General</label>
                <div class="progress" style="height: 25px;">
                  <div id="detailProgressBar" class="progress-bar" role="progressbar" style="width: 0%;">
                    0%
                  </div>
                </div>
              </div>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="row mb-4" id="detailStatsRow">
              <div class="col-md-3 col-6 mb-3">
                <div class="card bg-light h-100">
                  <div class="card-body text-center py-3">
                    <i class="mdi mdi-clipboard-text text-primary" style="font-size: 2rem;"></i>
                    <h3 id="statTotalTareas" class="mb-0 mt-2">0</h3>
                    <small class="text-muted">Total Tareas</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6 mb-3">
                <div class="card bg-light h-100">
                  <div class="card-body text-center py-3">
                    <i class="mdi mdi-check-circle-outline text-success" style="font-size: 2rem;"></i>
                    <h3 id="statTareasCompletadas" class="mb-0 mt-2">0</h3>
                    <small class="text-muted">Completadas</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6 mb-3">
                <div class="card bg-light h-100">
                  <div class="card-body text-center py-3">
                    <i class="mdi mdi-progress-clock text-info" style="font-size: 2rem;"></i>
                    <h3 id="statTareasEnProceso" class="mb-0 mt-2">0</h3>
                    <small class="text-muted">En Proceso</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6 mb-3">
                <div class="card bg-light h-100">
                  <div class="card-body text-center py-3">
                    <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 2rem;"></i>
                    <h3 id="statTareasVencidas" class="mb-0 mt-2">0</h3>
                    <small class="text-muted">Vencidas</small>
                  </div>
                </div>
              </div>
            </div>

            <!-- Información detallada en columnas -->
            <div class="row mb-4">
              <div class="col-md-12">
                <div class="card h-100">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="mdi mdi-information-outline me-2"></i>Información General</h6>
                  </div>
                  <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                      <tr>
                        <td class="text-muted" style="width: 40%;">Departamento:</td>
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

            <!-- Sección de usuarios asignados (solo para proyectos grupales) -->
            <div class="row mb-4" id="detailUsuariosSection" style="display: none;">
              <div class="col-12">
                <div class="card">
                  <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="mdi mdi-account-group me-2"></i>Usuarios Asignados (<span id="detailUsuariosCount">0</span>)</h6>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                      <table class="table table-sm table-hover mb-0">
                        <thead class="table-light" style="position: sticky; top: 0;">
                          <tr>
                            <th>Nombre</th>
                            <th>No. Empleado</th>
                            <th>Email</th>
                            <th>Tareas</th>
                            <th>Progreso</th>
                          </tr>
                        </thead>
                        <tbody id="detailUsuariosTableBody">
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sección de tareas -->
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="mdi mdi-clipboard-check-outline me-2"></i>Tareas del Proyecto</h6>
                    <div class="btn-group btn-group-sm" role="group">
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                      <table class="table table-sm table-hover mb-0">
                        <thead class="table-light" style="position: sticky; top: 0;">
                          <tr>
                            <th>Tarea</th>
                            <th>Asignado a</th>
                            <th>Fecha Límite</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody id="detailTareasTableBody">
                        </tbody>
                      </table>
                    </div>
                    <div id="detailNoTareas" class="text-center py-4" style="display: none;">
                      <i class="mdi mdi-clipboard-off-outline text-muted" style="font-size: 3rem;"></i>
                      <p class="text-muted mt-2 mb-0">No hay tareas registradas en este proyecto</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="mdi mdi-close me-1"></i>Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- plugins:js -->
  <script src="../vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="../vendors/chart.js/Chart.min.js"></script>
  <script src="../vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
  <script src="../vendors/progressbar.js/progressbar.min.js"></script>

  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="../js/template.js"></script>
  <script src="../js/hoverable-collapse.js"></script>
  <script src="../js/settings.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <!--<script src="../js/dashboard.js"></script>-->
  <script src="../js/Chart.roundedBarCharts.js"></script>
  <script src="../js/user_list_projects.js"></script>
  <script src="../js/custom_dialogs.js"></script>
  <script src="../js/notifications.js"></script>
  <script src="../js/user_project_details.js"></script>
  <!-- End custom js for this page-->
</body>
</html>