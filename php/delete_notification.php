<?php
/*delete_notification.php para borrar notificaciones*/

header('Content-Type: application/json');
session_start();

require_once(__DIR__ . '/db_config.php');
require_once('../email/NotificationHelper.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    $id_usuario = (int)$_SESSION['user_id'];
    
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id_notificacion'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de notificación requerido'
        ]);
        exit;
    }
    
    $id_notificacion = (int)$input['id_notificacion'];
    $conn = getDBConnection();
    $notificationHelper = new NotificationHelper($conn);
    $result = $notificationHelper->eliminarNotificacion($id_notificacion, $id_usuario);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificación eliminada exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo eliminar la notificación'
        ]);
    }
    
} catch (Exception $e) {
    error_log("delete_notification.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar notificación: ' . $e->getMessage()
    ]);
}
?>