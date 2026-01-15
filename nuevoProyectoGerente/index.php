<?php
require_once('../php/check_auth.php');
$user_name = $_SESSION['nombre']; 
$user_apellido = $_SESSION['apellido']; 
$user_email = $_SESSION['e_mail']; 
$user_id = $_SESSION['user_id']; 
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
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
          <a class="navbar-brand brand-logo" href="../managerDashboard">
            <img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
          </a>
          <a class="navbar-brand brand-logo-mini" href="../managerDashboard">
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
            <h3 class="welcome-sub-text">Crea y desarrolla nuevos proyectos</h3>
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
                  <?php echo htmlspecialchars($user_name . ' ' . $user_apellido); ?>
                </p>
                <p class="fw-light text-muted mb-0">
                  <?php echo htmlspecialchars($user_email); ?>
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
                <li class="nav-item"> <a class="nav-link" href="../gestionDeEmpleados-Gerente/">Gestion de empleados</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../revisarGraficosGerente">Revisar graficos</a></li>
                 <li class="nav-item"> <a class="nav-link" href="../graficaGanttGerente">Gráfica de Gantt</a></li>
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
                <li class="nav-item"> <a class="nav-link" href="../revisarProyectosGerente/">Revisar proyectos</a></li>
              </ul>
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"> <a class="nav-link" href="../revisarObjetivosGerente/">Revisar objetivos</a></li>
              </ul>
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"> <a class="nav-link" href="../revisarTareasGerente/">Revisar tareas</a></li>
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
          <div class="col-12 grid-margin stretch-card">
            <div class="card card-rounded">
              <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start">
                  <div>
                    <h4 class="card-title card-title-dash">Crear nuevo proyecto</h4>
                    <p class="card-subtitle card-subtitle-dash">Completa el formulario para crear un nuevo proyecto</p>
                  </div>
                  <div>
                    <a href="../revisarProyectosGerente">
                      <button class="btn btn-success btn-lg text-white mb-0 me-0" type="button"><i class="mdi mdi-checkbox-multiple-marked"></i>Ver lista de proyectos</button>
                    </a>
                  </div>
                </div>
                <div><br></div>
                
                <!-- Alert messages -->
                <div id="alertContainer"></div>

                <!-- Project Form -->
                <form id="proyectoForm">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Nombre <span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <input type="text" 
                                 id="nombre" 
                                 name="nombre" 
                                 class="form-control"
                                 maxlength="100"
                                 placeholder="Ingrese el nombre del proyecto" 
                                 required/>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Descripción<span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <textarea type="text" 
                                    id="descripcion" 
                                    name="descripcion" 
                                    class="form-control"
                                    placeholder="Ingrese la descripción del proyecto"
                                    maxlength="200"
                                    rows="3" 
                                    required
                          ></textarea>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <!-- Department field - enabled for managers with multiple departments -->
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Departamento<span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <select id="id_departamento" 
                                  name="id_departamento_display" 
                                  class="form-control"
                                  style="background-color: #ffffff;">
                            <option value="">Cargando departamentos...</option>
                          </select>
                          <!-- Hidden field para enviar el ID real del departamento -->
                          <input type="hidden" id="id_departamento_hidden" name="id_departamento">
                          <small class="form-text text-muted">
                            <i class="mdi mdi-information-outline"></i> 
                            Selecciona el departamento para el proyecto
                          </small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Asignar a <span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <div class="input-group">
                            <select id="id_participante" name="id_participante" class="form-control" required>
                              <option value="">Seleccione un empleado</option>
                            </select>
                            <button type="button" class="btn btn-success" id="btnSeleccionarGrupo" title="Seleccionar múltiples integrantes para proyecto grupal">
                              <i class="mdi mdi-account-multiple-plus"></i> Grupo
                            </button>
                          </div>
                          <small class="form-text text-muted">
                            <i class="mdi mdi-account"></i> 
                            Los usuarios disponibles corresponden al departamento seleccionado
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha de inicio <span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <input type="datetime-local" 
                                 id="fecha_creacion" 
                                 name="fecha_creacion" 
                                 class="form-control" 
                                 required/>
                          <small class="form-text text-muted">Seleccione la fecha de inicio del proyecto</small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha de entrega <span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <input type="date" 
                                 id="fecha_cumplimiento" 
                                 name="fecha_cumplimiento" 
                                 class="form-control" 
                                 required/>
                          <small class="form-text text-muted">Seleccione la fecha límite para el proyecto</small>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <!--add the permisos de edicion here colmd6-->
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Permisos de edición <span class="text-danger">*</span></label>
                          <div class="col-sm-4">
                            <div class="form-check form-check-success">
                              <label class="form-check-label">
                                <input type="radio" 
                                      class="form-check-input" 
                                      name="puede_editar_otros" 
                                      id="soloCreador" 
                                      value="0"
                                      checked
                                      style="cursor:pointer;" 
                                      required>
                                Edición restringida
                              </label>
                            </div>
                          </div>
                          <div class="col-sm-5">
                            <div class="form-check form-check-success">
                              <label class="form-check-label">
                                <input type="radio" 
                                      class="form-check-input" 
                                      name="puede_editar_otros" 
                                      id="otrosEditan" 
                                      value="1" 
                                      required
                                      style="cursor:pointer;">
                                Edición libre
                              </label>
                            </div>
                          </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Tipo de proyecto <span class="text-danger">*</span></label>
                        <div class="col-sm-4">
                          <div class="form-check form-check-success">
                            <label class="form-check-label">
                              <input type="radio" 
                                     class="form-check-input" 
                                     name="id_tipo_proyecto" 
                                     id="tipoProyecto1" 
                                     value="2" 
                                     required>
                              Individual
                            </label>
                          </div>
                        </div>
                        <div class="col-sm-5">
                          <div class="form-check form-check-success">
                            <label class="form-check-label">
                              <input type="radio" 
                                     class="form-check-input" 
                                     name="id_tipo_proyecto" 
                                     id="tipoProyecto2" 
                                     value="1" 
                                     required>
                              Grupal
                            </label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label for="subirArchivo" class="col-sm-3 col-form-label">Subir archivo</label>
                        <input type="file" 
                               id="archivoInput" 
                               name="archivo_adjunto" 
                               class="file-upload-default">
                        <div class="col-sm-6">
                          <input type="text" 
                                 id="nombreArchivo" 
                                 class="form-control" 
                                 disabled placeholder="Seleccione el archivo para subir">
                          
                          <span class="input-group-append">
                            <button class="file-upload-browse btn btn-success" type="button" id="btnSubirArchivo">Subir</button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">AR (Opcional)</label>
                        <div class="col-sm-9">
                          <input 
                            type="text" 
                            id="ar" 
                            name="ar" 
                            class="form-control"
                            maxlength="200"
                            placeholder="Ingrese el código AR si aplica"
                          />
                          <small class="form-text text-muted">Código de referencia adicional</small>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Hidden fields -->
                  <input type="hidden" id="id_creador" name="id_creador" value="<?php echo htmlspecialchars($user_id); ?>"/>
                  <input type="hidden" id="progreso" name="progreso" value="0"/>
                  <input type="hidden" id="estado" name="estado" value="pendiente"/>
                  <input type="hidden" id="archivo_adjunto_ruta" name="archivo_adjunto"/>

                  <div class="row">
                    <div class="col-md-6">
                      <button type="submit" class="btn btn-success" id="btnCrear">Crear</button>
                      <button type="button" class="btn btn-light" id="btnCancelar">Cancelar</button>
                    </div>
                  </div>
                  <!-- MODAL FOR GROUP PROJECT USER SELECTION -->
                  <!-- Modal for Group Project User Selection -->
                  <div class="modal fade" id="grupalUsuariosModal" tabindex="-1" aria-labelledby="grupalUsuariosLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="grupalUsuariosLabel">
                            <i class="mdi mdi-account-group me-2"></i>
                            Seleccionar integrantes del proyecto grupal
                          </h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <div class="alert alert-info mb-3">
                            <i class="mdi mdi-information me-2"></i>
                            Los usuarios mostrados pertenecen al departamento seleccionado. Si cambias el departamento, la lista se actualizará.
                          </div>
                          <div class="row mb-3">
                            <div class="col-md-12">
                              <input type="text" id="searchUsuarios" class="form-control" placeholder="Buscar usuario por nombre o email...">
                            </div>
                          </div>
                          <div id="usuariosListContainer" style="max-height: 400px; overflow-y: auto;">
                            <!-- Users will be populated here -->
                          </div>
                        </div>
                        <div class="modal-footer">
                          <div class="col-md-12" id="selectedUsersDisplay" class="mb-2">
                            <small class="text-muted">Usuarios seleccionados: <span id="countSelected" class="badge bg-success">0</span></small>
                          </div>
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                          <button type="button" class="btn btn-success" id="btnConfirmarGrupal">
                            <i class="mdi mdi-check me-1"></i>Confirmar selección
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </form>
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
  <!-- inject:js -->
  <script src="../js/template.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="../js/dashboard.js"></script>
  <script src="../js/custom_dialogs.js"></script>
  <script src="../js/manager_create_project.js"></script>
  <script src="../js/notifications.js"></script>
  <!-- End custom js for this page-->
</body>
</html>