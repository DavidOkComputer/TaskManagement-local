<?php /*managerDashboard.php para Dashboard principal de admin*/ require_once('../php/check_auth.php'); ?>
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
      <!-- partial -->
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
              <h1 class="welcome-text">Buenos dias, <span class="text-black fw-bold"> <?php echo $_SESSION['nombre']; ?> </span>
              </h1>
              <h3 class="welcome-sub-text">Tu resumen de esta semana </h3>
            </li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <!-- widget de departamentos -->
            <li class="nav-item d-none d-xl-flex align-items-center me-3">
              <div class="departments-widget">
                <div id="departmentsWidgetContainer" style="display: flex; gap: 12px;">
                  <!-- estado de carga -->
                  <div class="dept-flag" style="--dept-color: #adb5bd; --dept-light: #ced4da; min-width: 80px;">
                    <div class="dept-flag-content">
                      <div class="spinner-border spinner-border-sm" role="status" style="width: 1.2rem; height: 1.2rem; margin-bottom: 4px;">
                        <span class="visually-hidden">Cargando...</span>
                      </div>
                      <span class="dept-flag-initials">...</span>
                      <span class="dept-flag-name">Cargando</span>
                    </div>
                  </div>
                </div>
              </div>
            </li>
            <!--estadisticas rapidas -->
            <li class="nav-item d-none d-xl-flex align-items-center me-3">
              <div class="quick-stats-bar">
                <div class="stat-item stat-pending" id="navPendingTasks" title="Tareas pendientes">
                  <i class="mdi mdi-clock-alert-outline"></i>
                  <span class="stat-value">-</span>
                  <span class="stat-label">Pendientes</span>
                </div>
                <div class="stat-item stat-today" id="navTodayTasks" title="Tareas de hoy">
                  <i class="mdi mdi-calendar-today"></i>
                  <span class="stat-value">-</span>
                  <span class="stat-label">Hoy</span>
                </div>
                <div class="stat-item stat-overdue" id="navOverdueTasks" title="Tareas vencidas">
                  <i class="mdi mdi-alert-circle-outline"></i>
                  <span class="stat-value">-</span>
                  <span class="stat-label">Vencidas</span>
                </div>
              </div>
            </li>
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
                    <i class="mdi mdi-check-all me-1"></i>Marcar todas como leídas </a>
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
                  <p class="mb-1 mt-3 font-weight-semibold"> <?php echo $_SESSION['nombre']; echo ' '; echo $_SESSION['apellido']; ?> </p>
                  <p class="fw-light text-muted mb-0"> <?php echo $_SESSION['e_mail']; ?> </p>
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
      <!-- partial -->
      <div class="container-fluid page-body-wrapper">
        <!-- partial -->
        <!-- partial menu lateral -->
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
                  <li class="nav-item">
                    <a class="nav-link" href="../gestionDeEmpleados-Gerente/">Gestion de empleados</a>
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
                    <a class="nav-link" href="../nuevoProyectoGerente/">Crear nuevo proyecto</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="../nuevoObjetivoGerente/">Crear nuevo objetivo</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="../nuevoTareaGerente/">Crear nueva tarea</a>
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
                    <a class="nav-link" href="../revisarGraficosGerente">Revisar graficos</a>
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
                    <a class="nav-link" href="../revisarProyectosGerente/">Revisar proyectos</a>
                  </li>
                </ul>
                <ul class="nav flex-column sub-menu">
                  <li class="nav-item">
                    <a class="nav-link" href="../revisarObjetivosGerente/">Revisar objetivos</a>
                  </li>
                </ul>
                <ul class="nav flex-column sub-menu">
                  <li class="nav-item">
                    <a class="nav-link" href="../revisarTareasGerente/">Revisar tareas</a>
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
                    <a class="nav-link" href="../php/logout.php"> Cerrar Sesión </a>
                  </li>
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
                        <a class="nav-link" id="profile-tab" href="../proyectosTotalesGerente" role="tab" aria-selected="false">Proyectos totales</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" id="contact-tab" href="../proyectosPendientesGerente" role="tab" aria-selected="false">Proyectos pendientes</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link border-0" id="more-tab" href="../proyectosVencidosGerente" role="tab" aria-selected="false">Proyectos vencidos</a>
                      </li>
                    </ul>
                    <div class="auto-refresh-controls me-3">
                      <span class="text-muted small" id="autoRefreshStatus">
                        <i class="mdi mdi-sync me-1"></i>Auto-actualizar activo </span>
                      <button class="btn btn-sm btn-outline-primary" onclick="refreshAllData()" title="Actualizar ahora">
                        <i class="mdi mdi-refresh"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-secondary" id="toggleAutoRefreshBtn" onclick="toggleAutoRefresh()" title="Pausar/Reanudar">
                        <i class="mdi mdi-pause"></i>
                      </button>
                    </div>
                  </div>
                  <div class="tab-content tab-content-basic">
                    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview">
                      <div class="row mb-0" style="margin-bottom:0 !important;">
                        <div class="col-sm-12">
                          <div class="statistics-details d-flex align-items-center justify-content-between">
                            <!-- Índice 0: Total de objetivos -->
                            <div style="padding: 2px 10px;">
                              <p class="statistics-title mb-1" style="font-size: 0.95rem;">Total de objetivos</p>
                              <h3 class="rate-percentage" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 1: Total de proyectos -->
                            <div style="padding: 2px 10px;">
                              <p class="statistics-title mb-1" style="font-size: 0.95rem;">Total de proyectos</p>
                              <h3 class="rate-percentage mb-1" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex mb-0" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 2: Total de Tareas -->
                            <div style="padding: 2px 10px;">
                              <p class="statistics-title mb-1" style="font-size: 0.95rem;">Total de Tareas</p>
                              <h3 class="rate-percentage mb-1" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex mb-0" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 3: Proyectos completados -->
                            <div class="d-none d-md-block" style="padding: 2px 10px;">
                              <p class="statistics-title" style="font-size: 0.95rem;">Proyectos completados</p>
                              <h3 class="rate-percentage" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 4: Proyectos pendientes -->
                            <div class="d-none d-md-block" style="padding: 2px 10px;">
                              <p class="statistics-title" style="font-size: 0.95rem;">Proyectos pendientes</p>
                              <h3 class="rate-percentage" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 5: Proyectos vencidos -->
                            <div class="d-none d-md-block" style="padding: 2px 10px;">
                              <p class="statistics-title" style="font-size: 0.95rem;">Proyectos vencidos</p>
                              <h3 class="rate-percentage" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <!--Progreso por responsable-->
                        <div class="col-sm-4 grid-margin stretch-card">
                          <div class="card" style="border: 1px solid #000000;">
                            <div class="card-body">
                              <h4 class="card-title"> Empleados por Progreso </h4>
                              <div class="table-responsive pt-3">
                                <table class="table table-bordered table-hover">
                                  <thead>
                                    <tr>
                                      <th style="width: 10%;"> # </th>
                                      <th style="width: 40%;"> Nombre </th>
                                      <th style="width: 50%;"> Progreso </th>
                                    </tr>
                                  </thead>
                                  <tbody id="topEmployeesTableBody">
                                    <!-- Contenido cargado dinámicamente -->
                                    <tr>
                                      <td colspan="3" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                          <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="mt-2">Cargando empleados...</p>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                        <!--Progreso por proyecto/tareas-->
                        <div class="col-sm-4 grid-margin stretch-card">
                          <div class="card" style="border: 1px solid #000000;">
                            <div class="card-body">
                              <h4 class="card-title">Progreso por proyecto</h4>
                              <div class="table-responsive pt-3">
                                <table class="table table-bordered table-hover">
                                  <thead>
                                    <tr>
                                      <th style="width: 10%;"> # </th>
                                      <th style="width: 50%;"> Proyecto </th>
                                      <th style="width: 40%;"> Progreso </th>
                                    </tr>
                                  </thead>
                                  <tbody id="topProjectsTableBody">
                                    <!-- Contenido cargado dinámicamente -->
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
                        <!--Fin de progreso por proyecto/tarea-->
                        <div class="col-sm-4 grid-margin stretch-card">
                          <div class="card" style="border: 1px solid #000000;">
                            <div class="card-body">
                              <h4 class="card-title">Proyectos por estado</h4>
                              <div class="chart-container" style="width: 100%; display: flex; flex-direction: column;">
                                <div class="chart-wrapper" style="flex: 1; display: flex; justify-content: center; align-items: center;">
                                  <canvas id="doughnutChart" height="200" style="max-width: 100%;"></canvas>
                                </div>
                                <div id="doughnut-chart-legend" class="mt-5 text-center" style="width: 100%; overflow-x: auto;"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-lg-12 grid-margin-stretch-card">
                          <div class="card" style="border: 1px solid #000000">
                            <div class="card-body">
                              <h4 class="card-title">Detalles de los proyectos</h4>
                              <div class="table-responsive mt-3">
                                <table class="table select-table">
                                  <thead>
                                    <tr>
                                      <th class="sortable-header" data-sort="id_proyecto" style="cursor: pointer; user-select: none;"> # <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="nombre" style="cursor: pointer; user-select: none;"> Título <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="descripcion" style="cursor: pointer; user-select: none;"> Descripción <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="fecha_cumplimiento" style="cursor: pointer; user-select: none;"> Fecha de entrega <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="progreso" style="cursor: pointer; user-select: none;"> Progreso <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="estado" style="cursor: pointer; user-select: none;"> Estado <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="participante" style="cursor: pointer; user-select: none;"> Responsable <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                    </tr>
                                  </thead>
                                  <tbody id="proyectosTableBody">
                                    <!--proyectos cargados automaticamente-->
                                    <tr>
                                      <td colspan="9" class="text-center">
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
    <script src="../js/manager_list_projects.js"></script>
    <script src="../js/manager_dashboard_stats.js"></script>
    <script src="../js/manager_dashboard.js"></script>
    <script src="../js/Chart.roundedBarCharts.js"></script>
    <script src="../js/custom_dialogs.js"></script>
    <script src="../js/notifications.js"></script>
    <script src="../js/datetime_widget.js"></script>
    <!-- End custom js for this page-->
  </body>
</html>