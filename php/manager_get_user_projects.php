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
 
    $id_usuario = (int)$_GET['id_usuario'];
    
    //saber el id de departamento del gerente desde la sesion
    $id_departamento_manager = null;
    
    if (isset($_SESSION['id_departamento']) && $_SESSION['id_departamento'] > 0) {
        $id_departamento_manager = (int)$_SESSION['id_departamento'];
    } else if (isset($_SESSION['user_id'])) {
        //fallback saber el departamento desde el record el usuario
        $user_query = "SELECT id_departamento FROM tbl_usuarios WHERE id_usuario = ?";
        $user_stmt = $conn->prepare($user_query);
        $user_id = (int)$_SESSION['user_id'];
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_row = $user_result->fetch_assoc()) {
            $id_departamento_manager = (int)$user_row['id_departamento'];
            $_SESSION['id_departamento'] = $id_departamento_manager; 
        }
        $user_stmt->close();
    }
    
    if (!$id_departamento_manager) {
        throw new Exception('No se pudo determinar el departamento del usuario');
    }
 
    //verificar que el usuario solicitado responde al departamento
    $verify_query = "SELECT id_departamento FROM tbl_usuarios WHERE id_usuario = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $id_usuario);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        throw new Exception('Usuario no encontrado');
    }
    
    $user_data = $verify_result->fetch_assoc();
    $id_departamento_usuario = (int)$user_data['id_departamento'];
    
    //verificar que el usuario sea del mismo departamento
    if ($id_departamento_usuario !== $id_departamento_manager) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permiso para ver los proyectos de este usuario'
        ]);
        exit;
    }
    
    $verify_stmt->close();
 
    //saber todos los proyectos donde el usuario esta asignado, grupales o individuales
    $query = "SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_inicio,
                p.fecha_cumplimiento,
                p.estado,
                p.id_tipo_proyecto,
                d.nombre as area,
                -- contar el total de tareas del proyecto
                (SELECT COUNT(*)
                 FROM tbl_tareas t
                 WHERE t.id_proyecto = p.id_proyecto) as tareas_totales,
                -- contar tareas completadas
                (SELECT COUNT(*)
                 FROM tbl_tareas t
                 WHERE t.id_proyecto = p.id_proyecto
                 AND t.estado = 'completado') as tareas_completadas,
                -- calcular el porcentaje de  progreso
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
              WHERE p.id_departamento = ?
              AND (
                  -- proyectos individuales
                  (p.id_tipo_proyecto = 1 AND p.id_participante = ?)
                  OR
                  -- prppyectos grupales
                  (p.id_tipo_proyecto = 2 AND EXISTS (
                      SELECT 1 FROM tbl_tareas t
                      WHERE t.id_proyecto = p.id_proyecto
                      AND t.id_participante = ?
                  ))
              )
              ORDER BY p.fecha_cumplimiento DESC, p.nombre ASC";
 
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
 
    $stmt->bind_param("iii", $id_departamento_manager, $id_usuario, $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
 
    $proyectos = [];
    while ($row = $result->fetch_assoc()) {
        $progreso = (float)$row['progreso'];
        $tareas_totales = (int)$row['tareas_totales'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'area' => $row['area'],
            'tareas_totales' => $tareas_totales,
            'tareas_completadas' => $tareas_completadas,
            'progreso' => $progreso,
            'progreso_porcentaje' => number_format($progreso, 1)
        ];
    }
 
    echo json_encode([
        'success' => true,
        'proyectos' => $proyectos,
        'debug' => [
            'id_usuario' => $id_usuario,
            'id_departamento' => $id_departamento_manager,
            'total_proyectos' => count($proyectos)
        ]
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