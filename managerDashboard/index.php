<?php 
/*managerDashboard.php para Dashboard principal de gerente*/ 
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
    <style>
      .objetivo-item {
        padding: 8px 12px;
        margin-bottom: 8px;
        border-left: 3px solid #dee2e6;
        background-color: #f8f9fa;
        border-radius: 4px;
      }
      .objetivo-item.completado {
        border-left-color: #28a745;
      }
      .objetivo-item.pendiente {
        border-left-color: #ffc107;
      }
      .objetivo-item.en-proceso {
        border-left-color: #17a2b8;
      }
      .objetivo-item.vencido {
        border-left-color: #dc3545;
      }
    </style>
  </head>
  <body data-user-id="<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>">
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
            <!-- Widget de empleados del departamento -->
            <li class="nav-item d-none d-xl-flex align-items-center me-3">
              <div class="employees-widget">
                <div id="employeesWidgetContainer" style="display: flex; gap: 12px;">
                  <!-- Estado de carga -->
                  <div class="emp-flag" style="--emp-color: #adb5bd; --emp-light: #ced4da; min-width: 80px;">
                    <div class="emp-flag-content">
                      <div class="spinner-border spinner-border-sm" role="status" style="width: 1.2rem; height: 1.2rem; margin-bottom: 4px;">
                        <span class="visually-hidden">Cargando...</span>
                      </div>
                      <span class="emp-flag-initials">...</span>
                      <span class="emp-flag-name">Cargando</span>
                    </div>
                  </div>
                </div>
              </div>
            </li>
            <!-- Estadísticas rápidas -->
            <li class="nav-item d-none d-xl-flex align-items-center me-3">
              <div class="quick-stats-bar">
                <div class="stat-item stat-today" id="navTodayTasks" title="Tareas de hoy">
                  <i class="mdi mdi-calendar-today"></i>
                  <span class="stat-value">-</span>
                  <span class="stat-label">Hoy</span>
                </div>
                <div class="stat-item stat-pending" id="navPendingTasks" title="Tareas pendientes">
                  <i class="mdi mdi-clock-alert-outline"></i>
                  <span class="stat-value">-</span>
                  <span class="stat-label">Pendientes</span>
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
                <!-- Contenedor de notificaciones -->
                <div id="notificationsContainer" style="max-height: 350px; overflow-y: auto;">
                  <!-- estado de carga -->
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
                      <!-- Barra de estadísticas -->
                      <div class="row mb-0" style="margin-bottom:0 !important;">
                        <div class="col-sm-12">
                          <div class="statistics-details d-flex align-items-center justify-content-between" style="padding: 6px 0 2px 0;">
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
                              <p class="statistics-title mb-1" style="font-size: 0.95rem;">Proyectos completados</p>
                              <h3 class="rate-percentage mb-1" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex mb-0" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 4: Espacio vacío (para mantener simetría con admin) -->
                            <div class="d-none d-md-block" style="padding: 2px 10px;">
                              <p class="statistics-title mb-1" style="font-size: 0.95rem;"></p>
                              <h3 class="rate-percentage mb-1" style="font-size: 1.2rem;"></h3>
                              <p class="text-muted d-flex mb-0" style="font-size: 0.7rem;">
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 5: Proyectos pendientes -->
                            <div class="d-none d-md-block" style="padding: 2px 10px;">
                              <p class="statistics-title mb-1" style="font-size: 0.95rem;">Proyectos pendientes</p>
                              <h3 class="rate-percentage mb-1" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex mb-0" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                            <!-- Índice 6: Proyectos vencidos -->
                            <div class="d-none d-md-block" style="padding: 2px 10px;">
                              <p class="statistics-title mb-1" style="font-size: 0.95rem;">Proyectos vencidos</p>
                              <h3 class="rate-percentage mb-1" style="font-size: 1.2rem;">-</h3>
                              <p class="text-muted d-flex mb-0" style="font-size: 0.7rem;">
                                <i class="mdi mdi-minus"></i>
                                <span></span>
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                      <!-- Tablas principales compactas -->
                      <div class="row" style="height: calc(100vh - 260px);">
                        <!-- Columna izquierda - Tabla de proyectos -->
                        <div class="col-lg-8" style="height: 100%; padding-right: 8px;">
                          <div class="card h-100" style="margin-bottom: 0; border: 1px solid #000000;">
                            <div class="card-body d-flex flex-column" style="padding: 10px;">
                              <h4 class="card-title mb-2" style="font-size: 1rem;">Detalles de los proyectos</h4>
                              <div class="table-responsive flex-grow-1" style="overflow-y: auto; max-height: calc(100% - 40px);">
                                <table class="table select-table table-sm">
                                  <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                                    <tr>
                                      <th class="sortable-header" data-sort="id_proyecto" style="cursor: pointer; user-select: none; font-size: 0.85rem; padding: 8px;"> # <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="nombre" style="cursor: pointer; user-select: none; font-size: 0.85rem; padding: 8px;"> Título <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="descripcion" style="cursor: pointer; user-select: none; font-size: 0.85rem; padding: 8px;"> Descripción <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="fecha_cumplimiento" style="cursor: pointer; user-select: none; font-size: 0.85rem; padding: 8px;"> Fecha <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="progreso" style="cursor: pointer; user-select: none; font-size: 0.85rem; padding: 8px;"> Progreso <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="estado" style="cursor: pointer; user-select: none; font-size: 0.85rem; padding: 8px;"> Estado <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                      <th class="sortable-header" data-sort="participante" style="cursor: pointer; user-select: none; font-size: 0.85rem; padding: 8px;"> Responsable <i class="mdi mdi-sort-variant"></i>
                                      </th>
                                    </tr>
                                  </thead>
                                  <tbody id="proyectosTableBody" style="font-size: 0.85rem;">
                                    <tr>
                                      <td colspan="8" class="text-center">
                                        <div class="spinner-border text-primary spinner-border-sm" role="status">
                                          <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="mt-2 mb-0">Cargando proyectos...</p>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                        <!-- Columna derecha - Top Empleados, Proyectos y Chart -->
                        <div class="col-lg-4" style="height: 100%; padding-left: 8px;">
                          <div class="d-flex flex-column h-100">
                            <!-- Top Empleados -->
                            <div class="card mb-2" style="height: 33%; min-height: 0; border: 1px solid #000000;">
                              <div class="card-body d-flex flex-column" style="padding: 12px;">
                                <h4 class="card-title mb-2" style="font-size: 0.95rem;">Top Empleados</h4>
                                <div class="flex-grow-1" style="overflow-y: auto;">
                                  <table class="table table-sm table-borderless" style="font-size: 0.8rem;">
                                    <tbody id="topEmployeesTableBody">
                                      <tr>
                                        <td colspan="3" class="text-center py-2">
                                          <div class="spinner-border text-primary spinner-border-sm" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                          </div>
                                        </td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            </div>
                            <!-- Top Proyectos -->
                            <div class="card mb-2" style="height: 33%; min-height: 0; border: 1px solid #000000;">
                              <div class="card-body d-flex flex-column" style="padding: 12px;">
                                <h4 class="card-title mb-2" style="font-size: 0.95rem;">Top Proyectos</h4>
                                <div class="flex-grow-1" style="overflow-y: auto;">
                                  <table class="table table-sm table-borderless" style="font-size: 0.8rem;">
                                    <tbody id="topProjectsTableBody">
                                      <tr>
                                        <td colspan="3" class="text-center py-2">
                                          <div class="spinner-border text-primary spinner-border-sm" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                          </div>
                                        </td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            </div>
                            <!-- Gráfico de proyectos por estado -->
                            <div class="card" style="height: 34%; min-height: 0; margin-bottom: 0; border: 1px solid #000000;">
                              <div class="card-body d-flex flex-column" style="padding: 12px;">
                                <h4 class="card-title mb-2" style="font-size: 0.95rem;">P.P.E.</h4>
                                <div class="flex-grow-1 d-flex flex-column justify-content-center" style="min-height: 0;">
                                  <div style="height: 140px; display: flex; justify-content: center; align-items: center;">
                                    <canvas id="doughnutChart" style="max-height: 100%; max-width: 100%;"></canvas>
                                  </div>
                                  <div id="doughnut-chart-legend" class="mt-2 text-center" style="font-size: 0.75rem;"></div>
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
          </div>
          <!-- content-wrapper ends -->
        </div>
        <!-- main-panel ends -->
      </div>
      <!-- page-body-wrapper ends -->
    </div>
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
            <button type="button" class="btn btn-success" id="btnEditProject" onclick="editarProyectoFromModal()">
              <i class="mdi mdi-pencil me-1"></i>Editar Proyecto
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal para ver usuarios del proyecto-->
    <div class="modal fade" id="projectUsersModal" tabindex="-1" aria-labelledby="projectUsersModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="projectUsersModalLabel">
              <i class="mdi mdi-account-multiple me-2"></i>Usuarios del Proyecto
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="projectUsersContent">
            <!-- Contenido cargado dinámicamente -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
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
    <script src="../js/widget_empleados.js"></script>
    <script src="../js/manager_project_details.js"></script>
    <script src="../js/ppe_chart_click_manager.js"></script>
    <!-- End custom js for this page-->
  </body>
</html>