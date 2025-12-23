<?php 
require_once('../php/check_auth.php');

//obtener la informacion del usuario  
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
    <title>Registro de Usuarios - Sistema de Gestión</title> 
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
        <!-- Navigation Bar --> 
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
                        <h3 class="welcome-sub-text">Registra nuevos usuarios en el sistema</h3> 
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

        <!-- Sidebar --> 
        <div class="container-fluid page-body-wrapper"> 
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

            <!-- Main Panel --> 
            <div class="main-panel"> 
                <div class="content-wrapper"> 
                    <div class="col-12 grid-margin stretch-card"> 
                        <div class="card card-rounded"> 
                            <div class="card-body"> 
                                <div class="d-sm-flex justify-content-between align-items-start"> 
                                    <div> 
                                        <h4 class="card-title card-title-dash">Registrar Nuevo Usuario</h4> 
                                        <p class="card-subtitle card-subtitle-dash">Ingresa la información del nuevo usuario</p> 
                                    </div> 
                                    <div> 
                                        <a href="../gestionDeEmpleados"> 
                                            <button class="btn btn-success btn-lg text-white mb-0 me-0" type="button"> 
                                                <i class="mdi mdi-account-multiple"></i> Ver lista de usuarios 
                                            </button> 
                                        </a> 
                                    </div> 
                                </div> 
                                 
                                <div><br></div> 

                                <!-- Alert Messages --> 
                                <div id="alertMessage" style="display: none;"></div> 

                                <!-- FORM START --> 
                                <form id="formCrearUsuario" method="POST"> 
                                    <!-- Row 1: Nombre y Apellido --> 
                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Nombre <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="text"  
                                                           class="form-control"  
                                                           id="nombre"  
                                                           name="nombre"  
                                                           maxlength="100"  
                                                           placeholder="Ingrese el nombre" 
                                                           required /> 
                                                </div> 
                                            </div> 
                                        </div> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Apellido <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="text"  
                                                           class="form-control"  
                                                           id="apellido"  
                                                           name="apellido"  
                                                           maxlength="100"  
                                                           placeholder="Ingrese el apellido" 
                                                           required /> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <!-- Row 2: Usuario y Contraseña --> 
                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Usuario <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="text"  
                                                           class="form-control"  
                                                           id="usuario"  
                                                           name="usuario"  
                                                           maxlength="100"  
                                                           placeholder="Nombre de usuario" 
                                                           required /> 
                                                    <small class="form-text text-muted">Será usado para iniciar sesión</small> 
                                                </div> 
                                            </div> 
                                        </div> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Contraseña <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <div class="password-toggle"> 
                                                        <input type="password"  
                                                               class="form-control"  
                                                               id="acceso"  
                                                               name="acceso"  
                                                               maxlength="100"  
                                                               placeholder="Contraseña" 
                                                               required /> 
                                                        <i class="mdi mdi-eye-off password-toggle-icon" id="togglePassword"></i> 
                                                    </div> 
                                                    <small class="form-text text-muted">Mínimo 6 caracteres</small> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <!-- Row 3: Número de Empleado y Email --> 
                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Num. Empleado <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="number"  
                                                           class="form-control"  
                                                           id="num_empleado"  
                                                           name="num_empleado"  
                                                           placeholder="Número de empleado" 
                                                           required /> 
                                                </div> 
                                            </div> 
                                        </div> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Email<span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <input type="email"  
                                                           class="form-control"  
                                                           id="e_mail"  
                                                           name="e_mail"  
                                                           maxlength="200"  
                                                           placeholder="correo@nidec.com" 
                                                           required/> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <!-- Row 4: Departamento y Rol --> 
                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Departamento <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <select class="form-control"  
                                                            id="id_departamento"  
                                                            name="id_departamento"  
                                                            required> 
                                                        <option value="0">Seleccione un departamento</option> 
                                                        <!-- Options will be loaded dynamically --> 
                                                    </select> 
                                                </div> 
                                            </div> 
                                        </div> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Rol <span class="text-danger">*</span></label> 
                                                <div class="col-sm-9"> 
                                                    <select class="form-control"  
                                                            id="id_rol"  
                                                            name="id_rol"  
                                                            required> 
                                                        <option value="0">Seleccione un rol</option> 
                                                        <!-- Options will be loaded dynamically --> 
                                                    </select> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <!-- Row 5: Superior --> 
                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Superior</label> 
                                                <div class="col-sm-9"> 
                                                    <select class="form-control"  
                                                            id="id_superior"  
                                                            name="id_superior"> 
                                                        <option value="0">Sin superior asignado</option> 
                                                        <!-- Options will be loaded dynamically --> 
                                                    </select> 
                                                    <small class="form-text text-muted">Opcional</small> 
                                                </div> 
                                            </div> 
                                        </div> 
                                         <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">Foto de Perfil</label>
                                                <small class="text-muted d-block mb-2">Opcional - Formatos: JPG, PNG, GIF, WebP (máx. 5MB)</small>
                                                
                                                <!-- Drop Zone -->
                                                <div id="profilePictureDropZone" class="profile-picture-dropzone">
                                                    <input type="file" 
                                                        id="foto_perfil" 
                                                        name="foto_perfil" 
                                                        accept="image/jpeg,image/png,image/gif,image/webp"
                                                        style="display: none;">
                                                    
                                                    <!-- Estado inicial (sin imagen) -->
                                                    <div class="dropzone-content">
                                                        <i class="mdi mdi-cloud-upload" style="font-size: 48px; color: #ccc;"></i>
                                                        <p class="mb-1">Arrastra una imagen aquí o haz clic para seleccionar</p>
                                                        <small class="text-muted">JPG, PNG, GIF o WebP - Máximo 5MB</small>
                                                    </div>
                                                    
                                                    <!-- Vista previa de imagen -->
                                                    <div id="imagePreviewContainer" style="display: none;">
                                                        <img id="imagePreview" src="" alt="Vista previa" class="img-preview">
                                                        <p id="selectedFileName" class="mt-2 mb-0 small text-muted"></p>
                                                    </div>
                                                </div>
                                                
                                                <!-- Botón para eliminar imagen -->
                                                <button type="button" 
                                                        id="removeProfilePicture" 
                                                        class="btn btn-sm btn-outline-danger mt-2"
                                                        style="display: none;">
                                                    <i class="mdi mdi-delete"></i> Eliminar imagen
                                                </button>
                                            </div>
                                        </div>
                                    </div> 

                                    <!-- Submit Buttons --> 
                                    <div class="row"> 
                                        <div class="col-md-12"> 
                                            <div class="form-group"> 
                                                <button type="submit" class="btn btn-success btn-lg" id="btnSubmit"> 
                                                    <i class="mdi mdi-account-plus"></i> Crear Usuario 
                                                </button> 
                                                <button type="reset" class="btn btn-light btn-lg"> 
                                                    <i class="mdi mdi-refresh"></i> Limpiar 
                                                </button>
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
    <script src="../js/hoverable-collapse.js"></script>
    <!-- endinject --> 
    <!-- Custom js for this page--> 
    <script src="../js/file-upload.js"></script> 
    <script src="../js/dashboard.js"></script> 
    <script src="../js/user_register.js"></script> 
    <script src="../js/notifications.js"></script>
    <script src="../js/user_roles_manager.js"></script>
    <!-- End custom js for this page--> 
</body> 
</html>