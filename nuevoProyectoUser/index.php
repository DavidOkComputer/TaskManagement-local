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
                                <p class="mb-1 mt-3 font-weight-semibold"> <?php echo htmlspecialchars($user_name . ' ' . $user_apellido); ?> </p> 
                                <p class="fw-light text-muted mb-0"> <?php echo htmlspecialchars($user_email); ?> </p> 
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
                                        <h4 class="card-title card-title-dash">Crear nuevo proyecto</h4> 
                                        <p class="card-subtitle card-subtitle-dash">Completa el formulario para crear un nuevo proyecto personal</p> 
                                    </div> 
                                    <div> 
                                        <a href="../revisarProyectosUser"> 
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
                                                    <input type="text" id="nombre" name="nombre" class="form-control" maxlength="100" placeholder="Ingrese el nombre del proyecto" required/> 
                                                </div> 
                                            </div> 
                                        </div> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Departamento</label> 
                                                <div class="col-sm-9"> 
                                                    <input type="text" id="departamento_display" class="form-control" disabled placeholder="Cargando departamento..." style="background-color: #f8f9fa;"/> 
                                                    <small class="form-text text-muted">Tu departamento asignado</small> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <div class="row"> 
                                        <div class="col-md-12"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-md-1 col-lg-1 col-form-label">Descripción<span class="text-danger">*</span></label> 
                                                <div class="col-sm-9 col-md-11 col-lg-11"> 
                                                    <textarea type="text" id="descripcion" name="descripcion" class="form-control" placeholder="Ingrese la descripción del proyecto" maxlength="200" rows="3" required ></textarea> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Fecha de inicio <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="datetime-local" id="fecha_creacion" name="fecha_creacion" class="form-control" required/> 
                                                    <small class="form-text text-muted">Seleccione la fecha de inicio del proyecto</small> 
                                                </div> 
                                            </div> 
                                        </div> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Fecha de entrega <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="date" id="fecha_cumplimiento" name="fecha_cumplimiento" class="form-control" required/> 
                                                    <small class="form-text text-muted">Seleccione la fecha límite para el proyecto</small> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label for="subirArchivo" class="col-sm-3 col-form-label">Subir archivo</label> 
                                                <input type="file" id="archivoInput" name="archivo_adjunto" class="file-upload-default"> 
                                                <div class="col-sm-6"> 
                                                    <input type="text" id="nombreArchivo" class="form-control" disabled placeholder="Seleccione el archivo para subir"> 
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
                                                    <input type="text" id="ar" name="ar" class="form-control" maxlength="200" placeholder="Ingrese el código AR si aplica" /> 
                                                    <small class="form-text text-muted">Código de referencia adicional</small> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <!-- Hidden fields - automaticamente llenados--> 

                                    <input type="hidden" id="id_departamento" name="id_departamento" value=""/> 
                                    <input type="hidden" id="id_participante" name="id_participante" value="<?php echo htmlspecialchars($user_id); ?>"/> 
                                    <input type="hidden" id="id_creador" name="id_creador" value="<?php echo htmlspecialchars($user_id); ?>"/> 
                                    <input type="hidden" id="id_tipo_proyecto" name="id_tipo_proyecto" value="2"/> <!-- Siempre individual para usuarios --> 
                                    <input type="hidden" id="puede_editar_otros" name="puede_editar_otros" value="0"/> <!-- Solo el creador puede editar --> 
                                    <input type="hidden" id="progreso" name="progreso" value="0"/> 
                                    <input type="hidden" id="estado" name="estado" value="pendiente"/> 
                                    <input type="hidden" id="archivo_adjunto_ruta" name="archivo_adjunto"/> 

                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <button type="submit" class="btn btn-success" id="btnCrear">Crear</button> 
                                            <button type="button" class="btn btn-light" id="btnCancelar">Cancelar</button> 
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
    <script src="../js/user_create_project.js"></script> 
    <script src="../js/notifications.js"></script>
    <!-- End custom js for this page--> 
</body>
</html> 