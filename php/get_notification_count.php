<?php
/*get_notification_count.php contar las notificaciones no leidas*/

header('Content-Type: application/json');
session_start();

// Establecer zona horaria de México (Central)
date_default_timezone_set('America/Mexico_City');

require_once(__DIR__ . '/db_config.php');
require_once('../email/NotificationHelper.php');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    $id_usuario = (int)$_SESSION['user_id'];
    $conn = getDBConnection();
    $notificationHelper = new NotificationHelper($conn);
    $count = $notificationHelper->contarNoLeidas($id_usuario);
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    error_log("get_notification_count.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Error al obtener conteo'
    ]);
}
?>