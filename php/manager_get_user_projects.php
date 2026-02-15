<?php
/* manager_get_user_projects.php saber proyectos de un usuario en especifico*/
session_start();
header('Content-Type: application/json');
require_once('db_config.php');
 
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}
 
if (!isset($_GET['id_usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de usuario requerido'
    ]);
    exit;
}
 
try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    $id_usuario_solicitado = (int)$_GET['id_usuario'];
    $id_usuario_manager = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
 
    if (!$id_usuario_manager) {
        throw new Exception('Usuario no autenticado');
    }
 
    // Obtener departamento principal del manager
    $manager_info_query = "SELECT id_departamento FROM tbl_usuarios WHERE id_usuario = ?";
    $manager_info_stmt = $conn->prepare($manager_info_query);
    $manager_info_stmt->bind_param('i', $id_usuario_manager);
    $manager_info_stmt->execute();
    $manager_info_result = $manager_info_stmt->get_result();
    $manager_info = $manager_info_result->fetch_assoc();
    $departamento_principal_manager = (int)($manager_info['id_departamento'] ?? 0);
    $manager_info_stmt->close();
 
    // Obtener roles del manager
    $role_query = "
        SELECT ur.id_rol, ur.id_departamento, ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
        AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
 
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param('i', $id_usuario_manager);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
 
    $is_admin = false;
    $departamentos_gerente = [];
 
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        if ($row['id_rol'] == 2) {
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
 
    // Fallback: Si no hay registros en junction table, usar tbl_usuarios
    if (empty($departamentos_gerente) && !$is_admin) {
        $legacy_query = "SELECT id_departamento, id_rol FROM tbl_usuarios WHERE id_usuario = ?";
        $legacy_stmt = $conn->prepare($legacy_query);
        $legacy_stmt->bind_param('i', $id_usuario_manager);
        $legacy_stmt->execute();
        $legacy_result = $legacy_stmt->get_result();
        
        if ($row = $legacy_result->fetch_assoc()) {
            if ($row['id_rol'] == 1) {
                $is_admin = true;
            }
            if ($row['id_rol'] == 2 && $row['id_departamento']) {
                $departamentos_gerente[] = (int)$row['id_departamento'];
                $departamento_principal_manager = (int)$row['id_departamento'];
            }
        }
        $legacy_stmt->close();
    }
 
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('No tiene permisos de gerente');
    }
 
    // Verificar si el usuario solicitado es subordinado del manager O del mismo departamento principal
    if (!$is_admin) {
        // Verificar si es subordinado directo
        $subordinate_query = "SELECT 1 FROM tbl_usuarios WHERE id_usuario = ? AND id_superior = ?";
        $subordinate_stmt = $conn->prepare($subordinate_query);
        $subordinate_stmt->bind_param('ii', $id_usuario_solicitado, $id_usuario_manager);
        $subordinate_stmt->execute();
        $subordinate_result = $subordinate_stmt->get_result();
        $is_subordinate = $subordinate_result->num_rows > 0;
        $subordinate_stmt->close();
 
        // Verificar si está en el mismo departamento principal
        $same_dept_query = "SELECT 1 FROM tbl_usuarios WHERE id_usuario = ? AND id_departamento = ?";
        $same_dept_stmt = $conn->prepare($same_dept_query);
        $same_dept_stmt->bind_param('ii', $id_usuario_solicitado, $departamento_principal_manager);
        $same_dept_stmt->execute();
        $same_dept_result = $same_dept_stmt->get_result();
        $is_same_department = $same_dept_result->num_rows > 0;
        $same_dept_stmt->close();
 
        // Si no es subordinado ni del mismo departamento, denegar acceso
        if (!$is_subordinate && !$is_same_department) {
            echo json_encode([
                'success' => false,
                'message' => 'No tiene permiso para ver los proyectos de este usuario'
            ]);
            exit;
        }
    }
 
    // Consulta de proyectos
    if ($is_admin) {
        $query = "
            SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_inicio,
                p.fecha_cumplimiento,
                p.estado,
                p.id_tipo_proyecto,
                p.id_departamento,
                d.nombre as area,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as tareas_totales,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') as tareas_completadas,
                CASE
                    WHEN (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) > 0
                    THEN ROUND(
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') * 100.0 /
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto)
                    , 1)
                    ELSE 0
                END as progreso
            FROM tbl_proyectos p
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            WHERE (
                p.id_participante = ?
                OR pu.id_usuario = ?
                OR EXISTS (
                    SELECT 1 FROM tbl_tareas t
                    WHERE t.id_proyecto = p.id_proyecto
                    AND t.id_participante = ?
                )
            )
            ORDER BY p.fecha_cumplimiento DESC, p.nombre ASC
        ";
 
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $id_usuario_solicitado, $id_usuario_solicitado, $id_usuario_solicitado);
    } else {
        // Manager: Ver proyectos del usuario sin restricción de departamento
        $query = "
            SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_inicio,
                p.fecha_cumplimiento,
                p.estado,
                p.id_tipo_proyecto,
                p.id_departamento,
                d.nombre as area,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as tareas_totales,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') as tareas_completadas,
                CASE
                    WHEN (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) > 0
                    THEN ROUND(
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') * 100.0 /
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto)
                    , 1)
                    ELSE 0
                END as progreso
            FROM tbl_proyectos p
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            WHERE (
                p.id_participante = ?
                OR pu.id_usuario = ?
                OR EXISTS (
                    SELECT 1 FROM tbl_tareas t
                    WHERE t.id_proyecto = p.id_proyecto
                    AND t.id_participante = ?
                )
            )
            ORDER BY p.fecha_cumplimiento DESC, p.nombre ASC
        ";
 
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("iii", $id_usuario_solicitado, $id_usuario_solicitado, $id_usuario_solicitado);
    }
 
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
 
    $stmt->execute();
    $result = $stmt->get_result();
    $proyectos = [];
 
    while ($row = $result->fetch_assoc()) {
        $progreso = (float)$row['progreso'];
        $tareas_totales = (int)$row['tareas_totales'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        $is_managed = in_array((int)$row['id_departamento'], $departamentos_gerente);
 
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'area' => $row['area'],
            'id_departamento' => (int)$row['id_departamento'],
            'tareas_totales' => $tareas_totales,
            'tareas_completadas' => $tareas_completadas,
            'progreso' => $progreso,
            'progreso_porcentaje' => number_format($progreso, 1),
            'is_managed_department' => $is_managed
        ];
    }
 
    echo json_encode([
        'success' => true,
        'proyectos' => $proyectos,
        'total_proyectos' => count($proyectos),
        'id_usuario' => $id_usuario_solicitado,
        'managed_departments' => $departamentos_gerente,
        'departamento_principal_manager' => $departamento_principal_manager,
        'is_admin' => $is_admin
    ]);
 
    $stmt->close();
    $conn->close();
 
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar proyectos: ' . $e->getMessage(),
        'proyectos' => []
    ]);
    error_log('manager_get_user_projects.php Error: ' . $e->getMessage());
}
?>