<?php
require_once('../php/check_auth.php');
$user_name = $_SESSION['nombre']; 
$user_apellido = $_SESSION['apellido']; 
$user_email = $_SESSION['e_mail']; 
$user_id = $_SESSION['user_id']; 
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Administrador de objetivos</title>
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
          <a class="navbar-brand brand-logo" href="../userDashboard">
            <img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
          </a>
          <a class="navbar-brand brand-logo-mini" href="../userDashboard">
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
            <h3 class="welcome-sub-text">Crea y desarrolla nuevos objetivos</h3>
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
                <p class="mb-1 mt-3 font-weight-semibold"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="fw-light text-muted mb-0"><?php echo htmlspecialchars($user_email); ?></p>
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
          <div class="col-12 grid-margin stretch-card">
            <div class="card card-rounded">
              <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start">
                  <div>
                    <h4 class="card-title card-title-dash">Gestion de objetivos</h4>
                    <p class="card-subtitle card-subtitle-dash">Crea un nuevo objetivo para tu departamento</p>
                  </div>
                  <div>
                    <a href="../revisarObjetivosUser">
                      <button class="btn btn-success btn-lg text-white mb-0 me-0" type="button">
                        <i class="mdi mdi-checkbox-multiple-marked"></i>Ver lista de objetivos
                      </button>
                    </a>
                  </div>
                </div>
                <div><br></div>
                <!-- FORM START -->
                <form id="formCrearObjetivo" method="POST" enctype="multipart/form-data">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Nombre<span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <input 
                            type="text" 
                            class="form-control" 
                            id="nombre"
                            name="nombre"
                            maxlength="100"
                            placeholder="Ingrese el nombre del objetivo"
                            required
                          />
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Descripción<span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <textarea 
                            class="form-control" 
                            id="descripcion"
                            name="descripcion"
                            rows="3"
                            maxlength="200"
                            placeholder="Ingrese la descripción del objetivo"
                            required
                          ></textarea>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha de inicio<span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <input 
                            type="date" 
                            class="form-control"
                            id="fecha_inicio"
                            name="fecha_inicio"
                            required
                          />
                          <small class="form-text text-muted">Seleccione la fecha de inicio de este objetivo</small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha de cumplimiento<span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <input 
                            type="date" 
                            class="form-control"
                            id="fecha_cumplimiento"
                            name="fecha_cumplimiento"
                            required
                          />
                          <small class="form-text text-muted">Seleccione la fecha límite para completar este objetivo</small>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">AR (Opcional)</label>
                        <div class="col-sm-9">
                          <input 
                            type="text" 
                            class="form-control"
                            id="ar"
                            name="ar"
                            maxlength="200"
                            placeholder="Ingrese el código AR si aplica"
                          />
                          <small class="form-text text-muted">Código de referencia adicional</small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label for="subirArchivo" class="col-sm-3 col-form-label">Archivo adjunto</label>
                        <div class="col-sm-9">
                          <input 
                            type="file" 
                            name="archivo" 
                            class="file-upload-default"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip"
                          />
                          <div class="input-group">
                            <input 
                              type="text" 
                              class="form-control" 
                              id="fileUploadLabel"
                              disabled 
                              placeholder="Seleccione el archivo para subir"
                            />
                            <button class="btn btn-success file-upload-browse" type="button">Subir</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Departamento<span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                          <select 
                            class="form-control"
                            id="id_departamento"
                            name="id_departamento"
                            required
                          >
                            <option value="">Seleccione un departamento</option>
                            <!-- Las opciones se muestran con JavaScript -->
                          </select>
                          <!-- Hidden field para enviar el ID real del departamento -->
                          <input type="hidden" id="id_departamento_hidden" name="id_departamento">
                          <small class="form-text text-muted">
                            <i class="mdi mdi-information-outline"></i> 
                            Tu departamento está asignado automáticamente
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <button type="submit" class="btn btn-success">
                          <i class="mdi mdi-check"></i> Crear Objetivo
                        </button>
                        <button type="button" class="btn btn-light">
                          <i class="mdi mdi-close"></i> Cancelar
                        </button>
                      </div>
                    </div>
                  </div>
                  <!-- Hidden field for user ID -->
                  <input type="hidden" name="id_creador" value="<?php echo $user_id; ?>" />
                </form>
                <!-- FORM END -->
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
   <script src="../js/custom_dialogs.js"></script>
  <script src="../js/template.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="../js/dashboard.js"></script>
  <!-- Custom js for objective form -->
  <script src="../js/user_objetivo_form.js"></script>
  <script src="../js/notifications.js"></script>
  <!-- End custom js for this page-->
  
  <script>
    //agregar el user id cuando se sube el form
    function getUserId() {
      return <?php echo $user_id; ?>;
    }
  </script>
</body>
</html>