<?php 

require_once('../php/check_auth.php');
//tomar informacion de usuario
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
    <title>Registro de Departamentos - Sistema de Gestión de Tareas</title> 
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
    <link rel="shortcut icon" href="../images/Nidec Institutional Logo_Original Version.png" type="image/x-icon">
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
                        <h3 class="welcome-sub-text">Crea y desarrolla nuevos departamentos</h3> 
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
                            <img class="img-xs rounded-circle" src="../images/faces/face8.jpg" alt="Profile image"> 
                        </a> 
                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown"> 
                            <div class="dropdown-header text-center"> 
                                <img class="img-md rounded-circle" src="../images/faces/face8.jpg" alt="Profile image"> 
                                <p class="mb-1 mt-3 font-weight-semibold"><?php echo htmlspecialchars($user_name . ' ' . $user_apellido); ?></p> 
                                <p class="fw-light text-muted mb-0"><?php echo htmlspecialchars($user_email); ?></p> 
                            </div> 

                            <a class="dropdown-item" href="../php/logout.php"> 
                                <i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Cerrar sesion 
                            </a> 
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
            <!-- partial --> 
            <div class="main-panel"> 
                <div class="content-wrapper"> 
                    <div class="col-12 grid-margin stretch-card"> 
                        <div class="card card-rounded"> 
                            <div class="card-body"> 
                                <div class="d-sm-flex justify-content-between align-items-start"> 
                                    <div> 
                                        <h4 class="card-title card-title-dash">Registro de Departamentos</h4> 
                                        <p class="card-subtitle card-subtitle-dash">Crea un nuevo departamento</p> 
                                    </div> 
                                    <div> 
                                        <a href="../gestionDeDepartamentos"> 
                                            <button class="btn btn-success btn-lg text-white mb-0 me-0" type="button"> 
                                                <i class="mdi mdi-checkbox-multiple-marked"></i>Ver lista de departamentos 
                                            </button> 
                                        </a> 
                                    </div> 
                                </div> 
                                <div><br></div> 
                                <!-- Alert Messages --> 
                                <div id="alertMessage" style="display: none;"></div>
                                <!-- FORM START --> 
                                <form id="formCrearDepartamento" method="POST" action="../php/create_department.php"> 
                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Nombre<span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="text"  
                                                           class="form-control"  
                                                           id="nombre"  
                                                           name="nombre"  
                                                           maxlength="200"  
                                                           placeholder="Ingrese el nombre del departamento"  
                                                           required /> 
                                                </div> 
                                            </div> 
                                        </div> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Descripción<span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <textarea class="form-control"  
                                                              id="descripcion"  
                                                              name="descripcion"  
                                                              rows="3"  
                                                              maxlength="200"  
                                                              placeholder="Ingrese la descripción del departamento"  
                                                              required></textarea> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 
                                    <!-- Hidden field for user ID --> 
                                    <input type="hidden" name="id_creador" value="<?php echo $user_id; ?>" /> 
                                    <!-- Submit and Reset Buttons --> 
                                    <div class="row"> 
                                        <div class="col-md-12"> 
                                            <div class="form-group row"> 
                                                <div class="col-sm-12 text-end"> 
                                                    <button type="reset" class="btn btn-secondary btn-md me-2"> 
                                                        <i class="mdi mdi-refresh"></i> Limpiar 
                                                    </button> 
                                                    <button type="submit" class="btn btn-success btn-md" id="btnSubmit"> 
                                                        <i class="mdi mdi-content-save"></i> Registrar Departamento 
                                                    </button> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 
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
    <script src="../js/template.js"></script> 
    <script src="../vendors/chart.js/Chart.min.js"></script> 
    <!-- endinject --> 
    <!-- Custom js for this page-->  
    <script src="../js/hoverable-collapse.js"></script>
    <script src="../js/file-upload.js"></script> 
    <script src="../js/dashboard.js"></script> 
    <script src="../js/custom_dialogs.js"></script>
    <!-- End custom js for this page--> 
    <script src="../js/create_department.js"></script> 
    <script src="../js/notifications.js"></script>
</body> 
</html> 