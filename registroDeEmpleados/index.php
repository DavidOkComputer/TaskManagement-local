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

    <!-- Estilos especificos para el modal de busqueda de superior -->
    <style>
        /* Display field que reemplaza al select de superior */
        .superior-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            background-color: #fff;
            min-height: 42px;
        }
        .superior-display .superior-info {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }
        .superior-display .superior-info img,
        .superior-display .superior-info .no-photo {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            flex-shrink: 0;
            object-fit: cover;
        }
        .superior-display .superior-info .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e9ecef;
            color: #6c757d;
            font-size: 1.2rem;
        }
        .superior-display .superior-info .text {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .superior-display .superior-info .name {
            font-weight: 500;
        }
        .superior-display .superior-info .meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .superior-display .placeholder-text {
            color: #6c757d;
            flex: 1;
        }

        /* Lista de resultados dentro del modal */
        .manager-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }
        .manager-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f5;
            transition: background-color 0.12s ease;
        }
        .manager-item:last-child {
            border-bottom: none;
        }
        .manager-item:hover,
        .manager-item:focus,
        .manager-item.active {
            background-color: #e7f5ff;
            outline: none;
        }
        .manager-item img,
        .manager-item .no-photo {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            flex-shrink: 0;
            object-fit: cover;
        }
        .manager-item .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e9ecef;
            color: #6c757d;
            font-size: 1.6rem;
        }
        .manager-item .info {
            flex: 1;
            min-width: 0;
        }
        .manager-item .info .name {
            font-weight: 500;
            color: #212529;
        }
        .manager-item .info .meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .manager-item .badge-supervisor {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.2rem 0.5rem;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .manager-item .badge-manager {
            background-color: #e9ecef;
            color: #495057;
            padding: 0.2rem 0.5rem;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .manager-item.clear-option {
            font-style: italic;
            color: #6c757d;
        }

        .manager-list-empty {
            padding: 2rem 1rem;
            text-align: center;
            color: #6c757d;
        }

        /* Contador de resultados */
        .manager-search-counter {
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* Toggle "Es supervisor" dentro del modal */
        #superiorSearchModal .supervisor-toggle-row {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            width: 100%;
            box-sizing: border-box;
        }
        #superiorSearchModal .supervisor-toggle-row .form-check {
            display: flex;
            align-items: center;
            padding-left: 2.5em;
            margin: 0;
            flex-shrink: 0;
            min-height: 1.5em;
        }
        #superiorSearchModal .supervisor-toggle-row .form-check-input {
            width: 2em;
            height: 1em;
            margin-left: -2.5em;
            margin-top: 0.25em;
            flex-shrink: 0;
        }
        #superiorSearchModal .supervisor-toggle-row .toggle-label-text {
            flex: 1;
            min-width: 0;
            line-height: 1.3;
        }
        #superiorSearchModal .supervisor-toggle-row .toggle-label-text strong {
            display: block;
        }
        #superiorSearchModal .supervisor-toggle-row .toggle-label-text small {
            display: block;
            color: #6c757d;
            font-size: 0.8rem;
        }
    </style>
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
                        <h3 class="welcome-sub-text">MOTORES REYNOSA | Registra nuevos usuarios en el sistema</h3> 
                    </li> 
                </ul> 
                <ul class="navbar-nav ms-auto"> 
                    <li class="nav-item dropdown"> 
                        <a class="nav-link count-indicator" id="countDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="icon-bell"></i>
                            <span class="count" style="display: none;"></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown notification-dropdown pb-0" aria-labelledby="countDropdown">
                            <div class="dropdown-header d-flex justify-content-between align-items-center py-3 border-bottom">
                                <span class="font-weight-semibold">Notificaciones</span>
                                <a href="javascript:void(0)" id="markAllNotificationsRead" class="text-primary small">
                                    <i class="mdi mdi-check-all me-1"></i>Marcar todas como leídas
                                </a>
                            </div>
                            <div id="notificationsContainer" style="max-height: 350px; overflow-y: auto;">
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
                                <li class="nav-item"> <a class="nav-link" href="../graficaGantt">Gráfica de Gantt</a></li>
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
                                                    </select> 
                                                </div> 
                                            </div> 
                                        </div> 
                                    </div> 

                                    <!-- Row 5: Superior (ahora con modal de busqueda) --> 
                                    <div class="row"> 
                                        <div class="col-md-6"> 
                                            <div class="form-group row"> 
                                                <label class="col-sm-3 col-form-label">Superior</label> 
                                                <div class="col-sm-9"> 
                                                    <!-- Campo oculto que se envia al backend -->
                                                    <input type="hidden" id="id_superior" name="id_superior" value="0">

                                                    <!-- Display field con la seleccion actual -->
                                                    <div id="superiorDisplay" class="superior-display">
                                                        <div class="superior-info" id="superiorInfo">
                                                            <span class="placeholder-text">Sin superior asignado</span>
                                                        </div>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-danger"
                                                                id="btnClearSuperior"
                                                                style="display: none;"
                                                                title="Quitar selección">
                                                            <i class="mdi mdi-close"></i>
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-sm btn-primary"
                                                                id="btnOpenSuperiorModal"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#superiorSearchModal">
                                                            <i class="mdi mdi-magnify"></i> Buscar
                                                        </button>
                                                    </div>
                                                    <small class="form-text text-muted">Opcional - Busca entre gerentes o supervisores</small>
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
                                                    
                                                    <div class="dropzone-content">
                                                        <i class="mdi mdi-cloud-upload" style="font-size: 48px; color: #ccc;"></i>
                                                        <p class="mb-1">Arrastra una imagen aquí o haz clic para seleccionar</p>
                                                        <small class="text-muted">JPG, PNG, GIF o WebP - Máximo 5MB</small>
                                                    </div>
                                                    
                                                    <div id="imagePreviewContainer" style="display: none;">
                                                        <img id="imagePreview" src="" alt="Vista previa" class="img-preview">
                                                        <p id="selectedFileName" class="mt-2 mb-0 small text-muted"></p>
                                                    </div>
                                                </div>
                                                
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

                <!-- Footer inside main-panel --> 
                <footer class="footer">
                    <p style="font-size:0.7rem; text-align:center; margin: 0;">
                        2026 ACIM - Todos los derechos reservados Motores Reynosa S.A. de C.V.
                    </p>
                </footer>

            </div> 
            <!-- main-panel ends --> 
        </div> 
        <!-- page-body-wrapper ends --> 
    </div> 
    <!-- container-scroller --> 

    <!-- ============================================================ -->
    <!-- MODAL: Busqueda de superior (gerentes / supervisores)         -->
    <!-- ============================================================ -->
    <div class="modal fade" id="superiorSearchModal" tabindex="-1" aria-labelledby="superiorSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="superiorSearchModalLabel">
                        <i class="mdi mdi-account-search"></i> Buscar Superior
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Toggle supervisor/manager -->
                    <div class="supervisor-toggle-row mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="modalToggleEsSupervisor"
                                   role="switch">
                        </div>
                        <label class="toggle-label-text mb-0" for="modalToggleEsSupervisor" style="cursor: pointer;">
                            <strong><i class="mdi mdi-account-tie"></i> Es supervisor</strong>
                            <small>Mostrar solo supervisores en lugar de gerentes</small>
                        </label>
                    </div>

                    <!-- Buscador -->
                    <div class="mb-2">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="mdi mdi-magnify"></i>
                            </span>
                            <input type="text"
                                   class="form-control"
                                   id="modalSearchInput"
                                   placeholder="Buscar por nombre, apellido, num. empleado o departamento..."
                                   autocomplete="off">
                            <button class="btn btn-secondary"
                                    type="button"
                                    id="btnClearSearch"
                                    title="Limpiar busqueda">
                                <i class="mdi mdi-close"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="manager-search-counter" id="modalResultsCounter">
                            Cargando...
                        </span>
                        <span class="manager-search-counter" id="modalModeIndicator">
                            Mostrando: Gerentes
                        </span>
                    </div>

                    <!-- Lista de resultados -->
                    <div class="manager-list" id="modalManagerList" role="listbox">
                        <div class="manager-list-empty">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            Cargando gerentes...
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

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
    <script src="../js/session_timeout.js"></script>
    <!-- End custom js for this page--> 
</body> 
</html>