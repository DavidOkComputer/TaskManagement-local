<?php
require_once('../php/check_auth.php');
session_start();
$user_name = $_SESSION['nombre']; 
$user_apellido = $_SESSION['apellido']; 
$user_email = $_SESSION['e_mail']; 
$user_id = $_SESSION['user_id']; 
// Gestion de empleados
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Administrador de empleados </title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="../vendors/feather/feather.css">
  <link rel="stylesheet" href="../vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../vendors/typicons/typicons.css">
  <link rel="stylesheet" href="../vendors/simple-line-icons/css/simple-line-icons.css">
  <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="../js/select.dataTables.min.css">
  <!-- End plugin css for this page -->
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
          <a class="navbar-brand brand-logo" href="../adminDashboard">
            <img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
          </a>
          <a class="navbar-brand brand-logo-mini" href="../adminDashboard">
            <img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
          </a>
        </div>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-top"> 
        <ul class="navbar-nav">
          <li class="nav-item font-weight-semibold d-none d-lg-block ms-0">
            <h1 class="welcome-text">Buenos dias, 
              <span class="text-black fw-bold">
                <?php echo htmlspecialchars($user_name); ?>
              </span>
            </h1>
            <h3 class="welcome-sub-text">Gestiona los usuarios registrados</h3>
          </li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <form class="search-form" action="#">
              <i class="mdi mdi-account-search"></i>
              <input type="search" class="form-control" id="searchUser" placeholder="Buscar usuario" title="Search here">
            </form>
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
              <img class="img-xs rounded-circle" src="../images/faces/face8.jpg" alt="Profile image"> </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
              <div class="dropdown-header text-center">
                <img class="img-md rounded-circle" src="../images/faces/face8.jpg" alt="Profile image">
                <p class="mb-1 mt-3 font-weight-semibold">
                  <?php echo htmlspecialchars($user_name . ' ' . $user_apellido); ?>
                </p>
                <p class="fw-light text-muted mb-0">
                  <?php echo htmlspecialchars($user_email); ?>
                </p>
              </div>
              <a class="dropdown-item" href="../php/logout.php"><i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Cerrar sesion</a>
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
      <!-- partial:partials/_sidebar.html -->
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
                <li class="nav-item"> <a class="nav-link" href="../gestionDeEmpleados/">Gestion de empleados</a></li>
                <li class="nav-item"> <a class="nav-link" href="../registroDeEmpleados">Registrar nuevo empleado</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../gestionDeDepartamentos/">Gestion de departamentos</a></li>
                <li class="nav-item"> <a class="nav-link" href="../registroDeDepartamentos">Registrar departamento</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../revisarGraficos">Revisar graficos</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../revisarProyectos/">Revisar proyectos</a></li>
              </ul>
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"> <a class="nav-link" href="../revisarObjetivos/">Revisar objetivos</a></li>
              </ul>
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"> <a class="nav-link" href="../revisarTareas/">Revisar tareas</a></li>
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
                <li class="nav-item"> <a class="nav-link" href=""> Cerrar Sesión </a></li>
              </ul>
            </div>
          </li>
        </ul>
      </nav>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div
              id="alertMessage"
              class="alert"
              style="display:none;"
              role="alert">
          </div>
          <div class="row flex-grow">
            <div class="col-12 grid-margin stretch-card">
              <div class="card card-rounded">
                <div class="card-body">
                  <div class="d-sm-flex justify-content-between align-items-start">
                    <div>
                      <h4 class="card-title card-title-dash">Gestion de empleados</h4>
                      <p class="card-subtitle card-subtitle-dash">Revisa y gestiona los empleados</p>
                    </div>
                    <div>
                      <a href="../registroDeEmpleados">
                        <button class="btn btn-success btn-lg text-white mb-0 me-0" type="button"><i class="mdi mdi-account-plus"></i>Agregar nuevo usuario</button>
                      </a>
                    </div>
                  </div>
                  <!-- Rows Per Page Selector-->
                  <div class="rows-per-page-control mb-3 d-flex align-items-center gap-2">
                    <label for="rowsPerPageSelect" class="form-label mb-0">Filas por página:</label>
                    <select id="rowsPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                      <option value="5">5</option>
                      <option value="10" selected>10</option>
                      <option value="15">15</option>
                      <option value="20">20</option>
                    </select>
                  </div>

                  <div class="table-responsive mt-3">
                    <table class="table select-table">
                      <thead>
                        <tr>
                          <th class="sortable-header" data-sort="nombre" style="cursor: pointer; user-select: none;">
                            Nombre <i class="mdi mdi-sort-variant"></i>
                          </th>
                          <th class="sortable-header" data-sort="departamento" style="cursor: pointer; user-select: none;">
                            Departamento <i class="mdi mdi-sort-variant"></i>
                          </th>
                          <th class="sortable-header" data-sort="superior" style="cursor: pointer; user-select: none;">
                            Superior <i class="mdi mdi-sort-variant"></i>
                          </th>
                          <th class="sortable-header" data-sort="rol" style="cursor: pointer; user-select: none;">
                            Rol <i class="mdi mdi-sort-variant"></i>
                          </th>
                          <th class="sortable-header" data-sort="progreso" style="cursor:pointer; user-select:none;">
                            Progreso <i class="mdi mdi-sort-variant"></i>
                          </th>
                          <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody id="usuariosTableBody">
                        <!-- Users will be loaded here dynamically -->
                        <tr>
                          <td colspan="6" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                              <span class="visually-hidden">Cargando...</span>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- Pagination Controls - NEW -->
                  <div class="pagination-container mt-4">
                    <!-- Pagination info and buttons are dynamically inserted here -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>    
      <!-- main-panel ends -->
    </div>
    <!-- content-wrapper ends --> 
  </div>
  <!-- page-body-wrapper ends -->
</div>
<!-- container-scroller -->

<!-- Edit User Modal - UPDATED VERSION -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Editar Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editUserForm">
          <input type="hidden" id="editUserId">
          <div class="mb-3">
            <label for="editNombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="editNombre" required>
          </div>
          <div class="mb-3">
            <label for="editApellido" class="form-label">Apellido</label>
            <input type="text" class="form-control" id="editApellido" required>
          </div>
          <div class="mb-3">
            <label for="editUsuario" class="form-label">Usuario</label>
            <input type="text" class="form-control" id="editUsuario" required>
          </div>
          <div class="mb-3">
            <label for="editEmail" class="form-label">Email</label>
            <input type="email" class="form-control" id="editEmail" required>
          </div>
          <div class="mb-3">
            <label for="editDepartamento" class="form-label">Departamento</label>
            <select class="form-control" id="editDepartamento" required>
              <option value="">-- Seleccionar departamento --</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="saveUserChanges">Guardar Cambios</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="viewProjectsModal" tabindex="-1" aria-labelledby="viewProjectsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <div>
          <h5 class="modal-title" id="viewProjectsModalLabel">
            <i class="mdi mdi-folder-account"></i> Proyectos del Empleado
          </h5>
          <p class="mb-0 small">
            <span id="employeeName"></span> (<span id="employeeEmail"></span>)
          </p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Loading State -->
        <div id="projectsLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando proyectos...</span>
          </div>
          <p class="mt-3 text-muted">Cargando proyectos...</p>
        </div>
 
        <!-- No Projects State -->
        <div id="noProjects" class="text-center py-5" style="display: none;">
          <i class="mdi mdi-folder-open-outline" style="font-size: 64px; color: #ccc;"></i>
          <h5 class="mt-3 text-muted">No hay proyectos asignados</h5>
          <p class="text-muted">Este empleado no tiene proyectos asignados actualmente.</p>
        </div>
 
        <!-- Projects Container -->
        <div id="projectsContainer" style="display: none;">
          <!-- Summary Stats -->
          <div class="row mb-4">
            <div class="col-md-4">
              <div class="card text-center">
                <div class="card-body py-3">
                  <i class="mdi mdi-folder-multiple text-primary" style="font-size: 24px;"></i>
                  <h4 class="mb-0 mt-2" id="totalProjects">0</h4>
                  <small class="text-muted">Proyectos</small>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card text-center">
                <div class="card-body py-3">
                  <i class="mdi mdi-checkbox-marked-circle-outline text-info" style="font-size: 24px;"></i>
                  <h4 class="mb-0 mt-2" id="totalTasks">0</h4>
                  <small class="text-muted">Tareas Totales</small>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card text-center">
                <div class="card-body py-3">
                  <i class="mdi mdi-chart-line text-success" style="font-size: 24px;"></i>
                  <h4 class="mb-0 mt-2" id="avgProgress">0%</h4>
                  <small class="text-muted">Progreso Promedio</small>
                </div>
              </div>
            </div>
          </div>
 
          <!-- Projects List -->
          <h6 class="mb-3 fw-bold">Lista de Proyectos</h6>
          <div id="projectsList">
            <!-- Projects will be loaded here dynamically -->
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
  <!-- plugins:js -->
  <script src="../vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
   
  <script src="../vendors/chart.js/Chart.min.js"></script>
  <script src="../vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
  <script src="../vendors/progressbar.js/progressbar.min.js"></script>
  <!-- inject:js -->
  <script src="../js/off-canvas.js"></script>
  <script src="../js/hoverable-collapse.js"></script>
  <script src="../js/template.js"></script>
  <script src="../js/settings.js"></script>
  <script src="../js/todolist.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="../js/dashboard.js"></script>
  <script src="../js/Chart.roundedBarCharts.js"></script>
  <!-- Employee Management JS -->
  <script src="../js/manage_users.js"></script>
  <script src="../js/notifications.js"></script>
  <!-- End custom js for this page-->


</body>
</html>