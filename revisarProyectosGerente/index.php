<?php
require_once('../php/check_auth.php');
$user_name     = $_SESSION['nombre'];
$user_apellido = $_SESSION['apellido'];
$user_email    = $_SESSION['e_mail'];
$user_id       = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Administrador de proyectos</title>
    <link rel="stylesheet" href="../vendors/feather/feather.css">
    <link rel="stylesheet" href="../vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../vendors/typicons/typicons.css">
    <link rel="stylesheet" href="../vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
    <link rel="shortcut icon" href="../images/Nidec Institutional Logo_Original Version.png" />
    <style>
        /*layout de panel dividido */
        .split-modal-body {
            display: flex;
            gap: 0;
            padding: 0;
            min-height: 520px;
            max-height: 78vh;
            overflow: hidden;
        }

        /*panel izquierdo*/
        .split-panel-left {
            width: 52%;
            flex-shrink: 0;
            border-right: 1px solid #e4e9f0;
            overflow-y: auto;
            padding: 20px 22px;
            background: #f8fafc;
        }

        /*panel derecho*/
        .split-panel-right {
            flex: 1;
            overflow: hidden;
            padding: 20px 22px 14px;
            background: #fff;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .split-section-title {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #8592a3;
            margin-bottom: 10px;
            margin-top: 14px;
        }
        .split-section-title:first-child { margin-top: 0; }

        .detail-info-table td {
            font-size: 0.82rem;
            padding: 3px 6px 3px 0;
            vertical-align: top;
        }
        .detail-info-table td:first-child {
            color: #8592a3;
            white-space: nowrap;
            padding-right: 12px;
            width: 38%;
        }
        .detail-info-table td:last-child { font-weight: 500; }

        .split-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }
        .split-stat-card {
            background: #fff;
            border: 1px solid #e4e9f0;
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
        }
        .split-stat-card .stat-num {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1;
        }
        .split-stat-card .stat-lbl {
            font-size: 0.68rem;
            color: #8592a3;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 3px;
        }

        /*lista de tareas*/
        .rp-task-list {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }
        .rp-task-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid #f0f3f7;
        }
        .rp-task-item:last-child { border-bottom: none; }
        .rp-task-icon {
            font-size: 20px;
            flex-shrink: 0;
            cursor: pointer;
            margin-top: 1px;
            transition: color 0.15s;
        }
        .rp-task-icon:hover { opacity: 0.7; }
        .rp-task-body { flex: 1; min-width: 0; }
        .rp-task-name {
            font-size: 0.83rem;
            font-weight: 600;
            margin: 0 0 2px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .rp-task-meta {
            font-size: 0.72rem;
            color: #8592a3;
        }
        .rp-task-meta .overdue-text { color: #c0392b; font-weight: 600; }

        /*envoltura de la lista de tareas hacer scroll internamente */
        #rpTaskListWrapper {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            margin-bottom: 8px;
        }

        /*form para agregar tareas */
        .rp-add-task-form {
            border-top: 1px solid #e4e9f0;
            padding-top: 14px;
            margin-top: 10px;
        }
        .rp-add-task-form .form-control,
        .rp-add-task-form .form-select {
            font-size: 0.82rem;
            padding: 5px 10px;
        }
        .rp-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        /*tabla compacta de usuarios*/
        .split-users-table {
            font-size: 0.78rem;
            width: 100%;
            border-collapse: collapse;
        }
        .split-users-table th {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #8592a3;
            font-weight: 600;
            padding: 4px 6px;
            border-bottom: 1px solid #e4e9f0;
        }
        .split-users-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #f5f7fa;
            vertical-align: middle;
        }

        /*pestanias de filtro*/
        .rp-filter-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .rp-filter-tab {
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 12px;
            border: 1px solid #dee2e6;
            background: #fff;
            cursor: pointer;
            color: #6c757d;
            transition: all 0.15s;
        }
        .rp-filter-tab.active,
        .rp-filter-tab:hover {
            background: #009b4a;
            border-color: #009b4a;
            color: #fff;
        }

        /*registros que se pueden hacer clic*/
        #proyectosTableBody tr { cursor: pointer; }
        #proyectosTableBody tr:hover { background-color: #f0fff4; }

        /*barra de progreso compactada*/
        .split-progress {
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            background: #e9ecef;
        }
        .split-progress-bar {
            height: 100%;
            border-radius: 5px;
            transition: width 0.4s ease;
        }

        /*insignia de proyecto libre*/
        .libre-badge {
            background: #009b4a;
            color: #fff;
            font-size: 0.68rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
            margin-left: 6px;
        }

        /*barra de scroll*/
        .split-panel-left::-webkit-scrollbar,
        #rpTaskListWrapper::-webkit-scrollbar { width: 4px; }
        .split-panel-left::-webkit-scrollbar-track,
        #rpTaskListWrapper::-webkit-scrollbar-track { background: transparent; }
        .split-panel-left::-webkit-scrollbar-thumb,
        #rpTaskListWrapper::-webkit-scrollbar-thumb {
            background: #c8d0db;
            border-radius: 2px;
        }

        @media (max-width: 768px) {
            .split-modal-body { flex-direction: column; max-height: none; }
            .split-panel-left { width: 100%; border-right: none; border-bottom: 1px solid #e4e9f0; }
        }
    </style>
</head>
<body>
<div class="container-scroller">

    <!-- NAVBAR -->
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
                    <h1 class="welcome-text">Buenos días,
                        <span class="text-black fw-bold">
                            <?php echo htmlspecialchars($user_name); ?>
                        </span>
                    </h1>
                    <h3 class="welcome-sub-text">MOTORES REYNOSA | Gestiona los proyectos registrados</h3>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <form class="search-form" action="#" id="search-form">
                        <i class="icon-search"></i>
                        <input type="search" class="form-control"
                               placeholder="Buscar proyecto"
                               title="Search here" id="searchInput">
                    </form>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link count-indicator" id="countDropdown" href="#"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="icon-bell"></i>
                        <span class="count" style="display: none;"></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right navbar-dropdown notification-dropdown pb-0"
                         aria-labelledby="countDropdown">
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
                    <a class="nav-link" id="UserDropdown" href="#"
                       data-bs-toggle="dropdown" aria-expanded="false">
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
                        <a class="dropdown-item" href="../php/logout.php">
                            <i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>
                            Cerrar sesión
                        </a>
                    </div>
                </li>
            </ul>
            <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center"
                    type="button" data-bs-toggle="offcanvas">
                <span class="mdi mdi-menu"></span>
            </button>
        </div>
    </nav>

    <div class="container-fluid page-body-wrapper">

        <!-- SIDEBAR -->
        <nav class="sidebar sidebar-offcanvas" id="sidebar">
            <ul class="nav">
                <li class="nav-item nav-category">Gestion de usuarios</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic"
                       aria-expanded="false" aria-controls="ui-basic">
                        <i class="menu-icon mdi mdi-account-multiple"></i>
                        <span class="menu-title">Empleados</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="ui-basic">
                        <ul class="nav flex-column sub-menu">
                            <li class="nav-item"><a class="nav-link" href="../gestionDeEmpleados-Gerente/">Gestion de empleados</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item nav-category">Proyectos</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#form-elements"
                       aria-expanded="false" aria-controls="form-elements">
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
                    <a class="nav-link" data-bs-toggle="collapse" href="#charts"
                       aria-expanded="false" aria-controls="charts">
                        <i class="menu-icon mdi mdi-chart-line"></i>
                        <span class="menu-title">Graficado</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="charts">
                        <ul class="nav flex-column sub-menu">
                            <li class="nav-item"><a class="nav-link" href="../revisarGraficosGerente">Revisar graficos</a></li>
                            <li class="nav-item"><a class="nav-link" href="../graficaGanttGerente">Gráfica de Gantt</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#tables"
                       aria-expanded="false" aria-controls="tables">
                        <i class="menu-icon mdi mdi-magnify"></i>
                        <span class="menu-title">Revisar Proyectos</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="collapse" id="tables">
                        <ul class="nav flex-column sub-menu">
                            <li class="nav-item"><a class="nav-link" href="../revisarProyectosGerente/">Revisar proyectos</a></li>
                        </ul>
                        <ul class="nav flex-column sub-menu">
                            <li class="nav-item"><a class="nav-link" href="../revisarObjetivosGerente/">Revisar objetivos</a></li>
                        </ul>
                        <ul class="nav flex-column sub-menu">
                            <li class="nav-item"><a class="nav-link" href="../revisarTareasGerente/">Revisar tareas</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item nav-category">Sesión</li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#auth"
                       aria-expanded="false" aria-controls="auth">
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

        <!--panel principal-->
        <div class="main-panel">
            <div class="content-wrapper">
                <div id="alertMessage" class="alert" style="display:none;" role="alert"></div>
                <div class="row flex-grow">
                    <div class="col-12 grid-margin stretch-card">
                        <div class="card card-rounded">
                            <div class="card-body">
                                <div class="d-sm-flex justify-content-between align-items-start">
                                    <div>
                                        <h4 class="card-title card-title-dash">Gestión de proyectos</h4>
                                        <p class="card-subtitle card-subtitle-dash">
                                            Revisa y gestiona los proyectos registrados.
                                            <small class="text-muted ms-2">
                                                <i class="mdi mdi-gesture-tap"></i>
                                                Haz clic en una fila para ver detalles y gestionar tareas.
                                            </small>
                                        </p>
                                    </div>
                                    <div>
                                        <a href="../nuevoProyectoGerente/">
                                            <button class="btn btn-success btn-lg text-white mb-0 me-0" type="button">
                                                <i class="mdi mdi-plus-circle-outline"></i>Crear nuevo proyecto
                                            </button>
                                        </a>
                                    </div>
                                </div>

                                <div class="rows-per-page-control mb-3 d-flex align-items-center gap-2 flex-wrap">
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
                                                <th class="sortable-header" data-sort="id_proyecto" style="cursor:pointer;user-select:none;">
                                                    # <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th class="sortable-header" data-sort="nombre" style="cursor:pointer;user-select:none;">
                                                    Título <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th class="sortable-header" data-sort="descripcion" style="cursor:pointer;user-select:none;">
                                                    Descripción <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th class="sortable-header" data-sort="area" style="cursor:pointer;user-select:none;">
                                                    Área <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th class="sortable-header" data-sort="fecha_cumplimiento" style="cursor:pointer;user-select:none;">
                                                    Fecha de entrega <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th class="sortable-header" data-sort="progreso" style="cursor:pointer;user-select:none;">
                                                    Progreso <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th class="sortable-header" data-sort="estado" style="cursor:pointer;user-select:none;">
                                                    Estado <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th class="sortable-header" data-sort="participante" style="cursor:pointer;user-select:none;">
                                                    Asignado a <i class="mdi mdi-sort-variant"></i>
                                                </th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="proyectosTableBody">
                                            <tr>
                                                <td colspan="9" class="text-center">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Cargando...</span>
                                                    </div>
                                                    <p class="mt-2">Cargando proyectos...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="pagination-container mt-4"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!--modal dividido para ver los detalles del poryecto ademas de las tareas-->
<div class="modal fade" id="projectSplitModal" tabindex="-1"
     aria-labelledby="projectSplitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:1100px;">
        <div class="modal-content" style="border-radius:10px; overflow:hidden;">

            <!--encabezado -->
            <div class="modal-header bg-success text-white py-2 px-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <i class="mdi mdi-folder-open fs-5"></i>
                    <span id="splitModalTitle" class="fw-semibold">Detalles del Proyecto</span>
                    <span id="splitModalLibreBadge" class="libre-badge" style="display:none;">
                        <i class="mdi mdi-earth"></i> Libre
                    </span>
                    <span id="splitModalStatusBadge" class="badge ms-2">–</span>
                </div>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-light"
                            id="splitBtnEdit" title="Editar proyecto">
                        <i class="mdi mdi-pencil"></i> Editar
                    </button>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!--dividir el cuerpo-->
            <div class="split-modal-body" id="splitModalBody">

                <!--a la izquierda los detalles del proyecto -->
                <div class="split-panel-left" id="splitLeftPanel">
                    <div id="splitLeftLoading" class="text-center py-5">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-3 text-muted small">Cargando detalles...</p>
                    </div>
                    <div id="splitLeftContent" style="display:none;">

                        <p class="split-section-title">Estadísticas de Tareas</p>
                        <div class="split-stat-grid">
                            <div class="split-stat-card">
                                <div class="stat-num text-secondary" id="spl-total-tareas">0</div>
                                <div class="stat-lbl">Total</div>
                            </div>
                            <div class="split-stat-card">
                                <div class="stat-num text-success" id="spl-completadas">0</div>
                                <div class="stat-lbl">Completadas</div>
                            </div>
                            <div class="split-stat-card">
                                <div class="stat-num text-primary" id="spl-en-proceso">0</div>
                                <div class="stat-lbl">En Proceso</div>
                            </div>
                            <div class="split-stat-card">
                                <div class="stat-num text-danger" id="spl-vencidas">0</div>
                                <div class="stat-lbl">Vencidas</div>
                            </div>
                        </div>

                        <p class="split-section-title">Progreso General</p>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="split-progress flex-grow-1">
                                <div class="split-progress-bar" id="spl-progress-bar"
                                     style="width:0%; background:#009b4a;"></div>
                            </div>
                            <span class="fw-bold small" id="spl-progress-pct">0%</span>
                        </div>

                        <p class="split-section-title">Información General</p>
                        <table class="detail-info-table w-100 mb-3">
                            <tr><td>Departamento</td><td id="spl-departamento">–</td></tr>
                            <tr><td>Tipo</td><td id="spl-tipo">–</td></tr>
                            <tr><td>Creado por</td><td id="spl-creador">–</td></tr>
                            <tr><td>Fecha inicio</td><td id="spl-fecha-inicio">–</td></tr>
                            <tr><td>Fecha límite</td><td id="spl-fecha-limite">–</td></tr>
                            <tr id="spl-participante-row">
                                <td>Responsable</td><td id="spl-participante">–</td>
                            </tr>
                        </table>

                        <div id="spl-users-section" style="display:none;">
                            <p class="split-section-title">
                                Usuarios Asignados
                                (<span id="spl-users-count">0</span>)
                            </p>
                            <div style="max-height:180px; overflow-y:auto;">
                                <table class="split-users-table">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Emp.</th>
                                            <th>Tareas</th>
                                            <th style="min-width:80px;">Progreso</th>
                                        </tr>
                                    </thead>
                                    <tbody id="spl-users-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!--a la derecha las tareas del proyecto-->
                <div class="split-panel-right" id="splitRightPanel">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <p class="split-section-title mb-0">Tareas del Proyecto</p>
                        <div class="rp-filter-tabs" id="rpFilterTabs">
                            <button class="rp-filter-tab active" data-filter="all">Todas</button>
                            <button class="rp-filter-tab" data-filter="pendiente">Pendientes</button>
                            <button class="rp-filter-tab" data-filter="completado">Completadas</button>
                            <button class="rp-filter-tab" data-filter="vencido">Vencidas</button>
                        </div>
                    </div>

                    <!--envoltura para la lista de tareas y escrolear internamente-->
                    <div id="rpTaskListWrapper">
                        <div id="rpTaskLoading" class="text-center py-4" style="display:none;">
                            <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                            <p class="small text-muted mt-2">Cargando tareas...</p>
                        </div>
                        <ul class="rp-task-list" id="rpTaskList">
                            <li class="rp-task-item text-muted small text-center py-3">
                                Selecciona un proyecto para ver sus tareas.
                            </li>
                        </ul>
                    </div>

                    <!--agregar form de tarea-->
                    <div class="rp-add-task-form flex-shrink-0" id="rpAddTaskForm" style="display:none;">
                        <p class="split-section-title">
                            <i class="mdi mdi-plus-circle-outline text-success me-1"></i>
                            Nueva Tarea
                        </p>
                        <div id="rpTaskFormAlert" class="alert py-2 small" style="display:none;"></div>

                        <div class="mb-2">
                            <input type="text" class="form-control" id="rpTaskName"
                                   maxlength="100" placeholder="Nombre de la tarea *">
                        </div>
                        <div class="mb-2">
                            <textarea class="form-control" id="rpTaskDesc" rows="2"
                                      maxlength="250" placeholder="Descripción *"></textarea>
                        </div>
                        <div class="rp-form-row mb-2">
                            <div>
                                <label class="form-label small mb-1">Fecha vencimiento</label>
                                <input type="date" class="form-control" id="rpTaskDate">
                            </div>
                            <div>
                                <label class="form-label small mb-1">Estado</label>
                                <select class="form-select" id="rpTaskStatus">
                                    <option value="pendiente">Pendiente</option>
                                    <option value="completado">Completado</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1">Asignar a</label>
                            <select class="form-select" id="rpTaskAssignee">
                                <option value="">Sin asignar</option>
                            </select>
                            <small class="text-muted" id="rpAssigneeNote" style="display:none;">
                                <i class="mdi mdi-lock"></i> Solo el creador puede asignar tareas
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm flex-grow-1" id="rpSaveTaskBtn">
                                <span class="btn-text">
                                    <i class="mdi mdi-content-save me-1"></i>Guardar Tarea
                                </span>
                                <span class="spinner-border spinner-border-sm"
                                      style="display:none;" role="status"></span>
                            </button>
                            <button class="btn btn-secondary btn-sm" id="rpCancelTaskBtn">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <div class="pt-1 flex-shrink-0" id="rpAddTaskToggle">
                        <button class="btn btn-outline-success btn-sm w-100" id="rpShowTaskFormBtn">
                            <i class="mdi mdi-plus me-1"></i>Agregar tarea
                        </button>
                    </div>

                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer py-2">
                <small class="text-muted me-auto" id="splitModalPermNote" style="display:none;">
                    <i class="mdi mdi-lock text-warning me-1"></i>
                    Solo el creador puede agregar tareas a este proyecto.
                </small>
                <button type="button" class="btn btn-secondary btn-sm"
                        data-bs-dismiss="modal">
                    <i class="mdi mdi-close me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- scripts -->
<script src="../vendors/js/vendor.bundle.base.js"></script>
<script src="../vendors/chart.js/Chart.min.js"></script>
<script src="../vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
<script src="../vendors/progressbar.js/progressbar.min.js"></script>
<script src="../js/off-canvas.js"></script>
<script src="../js/hoverable-collapse.js"></script>
<script src="../js/template.js"></script>
<script src="../js/settings.js"></script>
<script src="../js/todolist.js"></script>
<script src="../js/dashboard.js"></script>
<script src="../js/Chart.roundedBarCharts.js"></script>
<script src="../js/custom_dialogs.js"></script>
<script src="../js/manager_manage_projects.js"></script>
<!--dar el id de usuario al js-->
<script>
    window.APP_CONFIG = {
        userId: <?php echo intval($user_id); ?>,
        userName: <?php echo json_encode($user_name, JSON_HEX_TAG | JSON_HEX_AMP); ?>
    };
    window.currentUserId = window.APP_CONFIG.userId;
</script>
<script src="../js/manager_project_task_panel.js"></script>
<script src="../js/notifications.js"></script>
<script src="../js/session_timeout.js"></script>
</body>
<footer>
    <p style="font-size:0.7rem; text-align:center;">
        2026 ACIM - Todos los derechos reservados Motores Reynosa S.A. de C.V.
    </p>
</footer>
</html>
