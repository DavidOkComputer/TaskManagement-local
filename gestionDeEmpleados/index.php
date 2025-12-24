<?php
require_once('../php/check_auth.php');
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
              <i class="mdi mdi-account" alt="profile icon"></i>
            </a>  
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
              <div class="dropdown-header text-center">
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
                <li class="nav-item"> <a class="nav-link" href="../php/logout.php"> Cerrar Sesión </a></li>
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

<!-- Modal de Gestión de Roles --> 
    <div class="modal fade" id="rolesManagerModal" tabindex="-1" aria-labelledby="rolesManagerTitle" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
              <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title" id="rolesManagerTitle"> 
                  <i class="mdi mdi-account-key me-2"></i>Gestionar Roles 
                  </h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button> 
              </div>
              <div class="modal-body">
                  <!-- Alerta para mensajes --> 
                  <div id="rolesManagerAlert" class="alert" style="display: none;"></div>
                  <!-- Roles Actuales --> 
                  <div class="card mb-4">
                  <div class="card-header bg-light">
                      <h6 class="mb-0"> 
                          <i class="mdi mdi-format-list-bulleted me-2"></i>Roles Asignados 
                      </h6>
                  </div>
                  <div class="card-body p-0">
                      <div id="currentRolesList">
                          <!-- Se llena dinámicamente --> 
                      </div>
                  </div>
                  </div>
                  <!-- Agregar Nuevo Rol --> 
                  <div class="card border-success">
                  <div class="card-header bg-success text-white">
                      <h6 class="mb-0"> 
                          <i class="mdi mdi-plus-circle me-2"></i>Agregar Nuevo Rol 
                      </h6>
                  </div>
                  <div class="card-body">
                      <div class="row g-3">
                          <div class="col-md-5">
                              <label class="form-label">Departamento</label> 
                              <select class="form-select" id="newRoleDepartamento">
                              <option value="">Seleccione un departamento</option>
                              </select>
                              <small class="text-muted">Solo departamentos sin rol asignado</small> 
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">Rol</label> 
                              <select class="form-select" id="newRoleRol">
                              <option value="">Seleccione un rol</option>
                              </select>
                          </div>
                          <div class="col-md-3">
                              <label class="form-label d-block">&nbsp;</label> 
                              <div class="form-check mt-2"> 
                              <input class="form-check-input" type="checkbox" id="newRoleEsPrincipal"> 
                              <label class="form-check-label" for="newRoleEsPrincipal"> 
                              <i class="mdi mdi-star text-warning"></i> Principal 
                              </label> 
                              </div>
                          </div>
                      </div>
                      <div class="mt-3"> 
                          <button type="button" class="btn btn-success" id="btnAddRole" onclick="addNewRole()"> 
                          <i class="mdi mdi-plus"></i> Agregar Rol 
                          </button> 
                      </div>
                  </div>
                  </div>
              </div>
              <div class="modal-footer"> 
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> 
                  <i class="mdi mdi-close"></i> Cerrar 
                  </button> 
              </div>
          </div>
      </div>
    </div> 

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="editUserModalLabel">
                    <i class="mdi mdi-account-edit me-2"></i>Editar Usuario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" enctype="multipart/form-data">
                    <input type="hidden" id="editUserId">
                    <input type="hidden" id="editCurrentFotoName">
                    
                    <!-- Sección de Foto de Perfil -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="edit-photo-section">
                                <label class="form-label fw-bold">
                                    <i class="mdi mdi-camera me-1"></i>Foto de Perfil
                                </label>
                                
                                <!-- Contenedor de foto actual -->
                                <div id="editCurrentPhotoContainer" class="text-center mb-3">
                                    <img id="editCurrentPhoto" 
                                         src="../images/default-avatar.png" 
                                         alt="Foto actual" 
                                         class="edit-photo-preview">
                                    <p class="text-muted small mb-2">Foto actual</p>
                                </div>
                                
                                <!-- Contenedor de nueva foto (preview) -->
                                <div id="editNewPhotoContainer" class="text-center mb-3" style="display: none;">
                                    <img id="editImagePreview" 
                                         src="" 
                                         alt="Nueva foto" 
                                         class="edit-photo-preview"
                                         style="border-color: #28a745;">
                                    <p class="text-success small mb-2">
                                        <i class="mdi mdi-check-circle"></i> Nueva foto seleccionada
                                    </p>
                                </div>
                                
                                <!-- Drop Zone para nueva foto -->
                                <div id="editProfilePictureDropZone" class="edit-photo-dropzone">
                                    <input type="file" 
                                           id="editFotoPerfil" 
                                           name="foto_perfil" 
                                           accept="image/jpeg,image/png,image/gif,image/webp"
                                           style="display: none;">
                                    <i class="mdi mdi-cloud-upload" style="font-size: 32px; color: #ccc;"></i>
                                    <p class="mb-0 small">Arrastra una imagen o haz clic para cambiar la foto</p>
                                    <small class="text-muted">JPG, PNG, GIF o WebP - Máximo 5MB</small>
                                </div>
                                
                                <!-- Botones de acción para foto -->
                                <div class="mt-2 d-flex gap-2 justify-content-center">
                                    <button type="button" 
                                            id="editChangeProfilePicture" 
                                            class="btn btn-sm btn-outline-primary">
                                        <i class="mdi mdi-camera"></i> Cambiar foto
                                    </button>
                                    <button type="button" 
                                            id="editRemoveProfilePicture" 
                                            class="btn btn-sm btn-outline-danger"
                                            style="display: none;">
                                        <i class="mdi mdi-delete"></i> Eliminar foto
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="mb-4">
                    
                    <!-- Datos del Usuario -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editNombre" class="form-label">
                                    <i class="mdi mdi-account me-1"></i>Nombre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="editNombre" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editApellido" class="form-label">
                                    <i class="mdi mdi-account me-1"></i>Apellido <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="editApellido" required maxlength="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editUsuario" class="form-label">
                                    <i class="mdi mdi-account-circle me-1"></i>Usuario <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="editUsuario" required maxlength="100">
                                <small class="form-text text-muted">Solo letras, números, punto, guión y guión bajo</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editEmail" class="form-label">
                                    <i class="mdi mdi-email me-1"></i>Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="editEmail" required maxlength="200">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editDepartamento" class="form-label">
                                    <i class="mdi mdi-domain me-1"></i>Departamento <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="editDepartamento" required>
                                    <option value="">-- Seleccionar departamento --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-success" id="saveUserChanges">
                    <i class="mdi mdi-content-save me-1"></i>Guardar Cambios
                </button>
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
   
  <script src="../js/user_roles_manager.js"></script>
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