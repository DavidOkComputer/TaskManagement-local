<?php
require_once('check_auth.php');
require_once('db_config.php');
 $conn = getDBConnection();
header('Content-Type: application/json');
 
try {
    $id_usuario = $_SESSION['user_id'];
    $id_rol = $_SESSION['id_rol'];
    $id_departamento = $_SESSION['user_department'];
    
    // contar las tareas pendientes
    $queryPendientes = "
        SELECT COUNT(*) as total
        FROM tbl_tareas
        WHERE id_creador = ?
        AND estado IN ('pendiente', 'en proceso')
    ";
    
    $stmt = $conn->prepare($queryPendientes);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $pendientes = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    //contar las tareas para hoy
    $queryHoy = "
        SELECT COUNT(*) as total
        FROM tbl_tareas
        WHERE id_creador = ?
        AND DATE(fecha_cumplimiento) = CURDATE()
        AND estado != 'completado'
    ";
    
    $stmt = $conn->prepare($queryHoy);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $hoy = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    //contar las tareas vencidas
    $queryVencidas = "
        SELECT COUNT(*) as total
        FROM tbl_tareas
        WHERE id_creador = ?
        AND fecha_cumplimiento < CURDATE()
        AND estado != 'completado'
    ";
    
    $stmt = $conn->prepare($queryVencidas);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $vencidas = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'pendientes' => intval($pendientes),
            'hoy' => intval($hoy),
            'vencidas' => intval($vencidas)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en get_user_quick_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar estadísticas',
        'stats' => [
            'pendientes' => 0,
            'hoy' => 0,
            'vencidas' => 0
        ]
    ]);
}
?>