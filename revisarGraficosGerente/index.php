<?php
/* Manager Dashboard para mostrar los datos de dashboard de gerente*/
require_once('../php/check_auth.php');

$user_id = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$user_name = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Gerente';
$user_apellido = isset($_SESSION['apellido']) ? $_SESSION['apellido'] : '';
$user_email = isset($_SESSION['e_mail']) ? $_SESSION['e_mail'] : '';
$user_rol = isset($_SESSION['id_rol']) ? (int)$_SESSION['id_rol'] : 0;

if ($user_rol !== 2 && $user_rol !== 1) {
    header('Location: ../login/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard Gerente - Administrador de Proyectos</title>
    
    <!-- Plugins CSS -->
    <link rel="stylesheet" href="../vendors/feather/feather.css">
    <link rel="stylesheet" href="../vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../vendors/typicons/typicons.css">
    <link rel="stylesheet" href="../vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../images/Nidec Institutional Logo_Original Version.png" />
    
    <style>
        .chart-card {
            transition: box-shadow 0.3s ease;
        }
        .chart-card:hover {
            box-shadow: 0 4px 15px rgba(34, 139, 89, 0.15);
        }
        .department-badge {
            background: linear-gradient(135deg, rgba(34, 139, 89, 0.1) 0%, rgba(80, 154, 108, 0.1) 100%);
            border-left: 4px solid rgba(34, 139, 89, 1);
            padding: 10px 15px;
            border-radius: 0 6px 6px 0;
            margin-bottom: 20px;
        }
        .department-badge h4 {
            color: rgba(34, 139, 89, 1);
            margin: 0;
            font-weight: 600;
        }
        .refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(34, 139, 89, 0.9);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            display: none;
            z-index: 1000;
        }
        .refresh-indicator.active {
            display: block;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <!-- Navbar -->
        <nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
                <div class="me-3">
                    <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-bs-toggle="minimize">
                        <span class="icon-menu"></span>
                    </button>
                </div>
                <div>
                    <a class="navbar-brand brand-logo" href="../managerDashboard/">
                        <img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
                    </a>
                    <a class="navbar-brand brand-logo-mini" href="../managerDashboard/">
                        <img src="../images/Nidec Institutional Logo_Original Version.png" alt="logo" />
                    </a>
                </div>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-top">
                <ul class="navbar-nav">
                    <li class="nav-item font-weight-semibold d-none d-lg-block ms-0">
                        <h1 class="welcome-text">Buenos días, 
                            <span class="text-black fw-bold">
                                <?php echo htmlspecialchars($user_name); ?>
                            </span>
                        </h1>
                        <h3 class="welcome-sub-text">Cargando departamento...</h3>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <!-- Notifications -->
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
                    <!-- User Profile -->
                    <li class="nav-item dropdown d-none d-lg-block user-dropdown">
                        <a class="nav-link" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                            <img class="img-xs rounded-circle" src="../images/faces/face8.jpg" alt="Profile image">
                        </a>
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
                            <a class="dropdown-item" href="../php/logout.php">
                                <i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>
                                Cerrar sesión
                            </a>
                        </div>
                    </li>
                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-bs-toggle="offcanvas">
                    <span class="mdi mdi-menu"></span>
                </button>
            </div>
        </nav>
        
        <div class="container-fluid page-body-wrapper">
            <!-- Sidebar -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    <li class="nav-item nav-category">Gestión de usuarios</li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
                            <i class="menu-icon mdi mdi-account-multiple"></i>
                            <span class="menu-title">Empleados</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="collapse" id="ui-basic">
                            <ul class="nav flex-column sub-menu">
                                <li class="nav-item"><a class="nav-link" href="../gestionDeEmpleados-Gerente/">Gestión de empleados</a></li>
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
                                <li class="nav-item"><a class="nav-link" href="../revisarGraficosGerente">Revisar gráficos</a></li>
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
                                <li class="nav-item"><a class="nav-link" href="../revisarProyectosGerente/">Revisar proyectos</a></li>
                                <li class="nav-item"><a class="nav-link" href="../revisarObjetivosGerente/">Revisar objetivos</a></li>
                                <li class="nav-item"><a class="nav-link" href="../revisarTareasGerente/">Revisar tareas</a></li>
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
            
            <!-- Main Content -->
            <div class="main-panel">
                <div class="content-wrapper">
                    <!-- Charts Row 1 -->
                    <div class="row">
                        <div class="col-lg-6 grid-margin stretch-card">
                            <div class="card chart-card">
                                <div class="card-body">
                                    <h4 class="card-title">Progreso sobre el tiempo (Proyectos completados)</h4>
                                    <canvas id="lineChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 grid-margin stretch-card">
                            <div class="card chart-card">
                                <div class="card-body">
                                    <h4 class="card-title">Progreso de proyectos</h4>
                                    <canvas id="barChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Row 2 -->
                    <div class="row">
                        <div class="col-lg-6 grid-margin stretch-card">
                            <div class="card chart-card">
                                <div class="card-body">
                                    <h4 class="card-title">Avances por periodo de tiempo</h4>
                                    <canvas id="areaChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 grid-margin stretch-card">
                            <div class="card chart-card">
                                <div class="card-body">
                                    <h4 class="card-title">Proyectos por estado</h4>
                                    <canvas id="doughnutChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Row 3 -->
                    <div class="row">
                        <div class="col-lg-6 grid-margin grid-margin-lg-0 stretch-card">
                            <div class="card chart-card">
                                <div class="card-body">
                                    <h4 class="card-title">Medidas de eficiencia</h4>
                                    <canvas id="scatterChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 grid-margin grid-margin-lg-0 stretch-card">
                            <div class="card chart-card">
                                <div class="card-body">
                                    <h4 class="card-title">Distribución de Carga</h4>
                                    <canvas id="workloadChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="mdi mdi-refresh mdi-spin"></i> Actualizando...
    </div>
    
    <!-- Core JS -->
    <script src="../vendors/js/vendor.bundle.base.js"></script>
    
    <!-- Chart.js -->
    <script src="../vendors/chart.js/Chart.min.js"></script>
    
    <!-- Template JS -->
    <script src="../js/off-canvas.js"></script>
    <script src="../js/hoverable-collapse.js"></script>
    <script src="../js/template.js"></script>
    <script src="../js/settings.js"></script>
    
    <!-- Manager Dashboard Charts - Load in order -->
    <script src="../js/manager_dashboard_core.js"></script>
    <script src="../js/manager_charts_doughnut.js"></script>
    <script src="../js/manager_charts_bar.js"></script>
    <script src="../js/manager_charts_line.js"></script>
    <script src="../js/manager_charts_area.js"></script>
    <script src="../js/manager_charts_scatter.js"></script>
    <script src="../js/manager_charts_workload.js"></script>
    <script src="../js/notifications.js"></script>
</body>
</html>