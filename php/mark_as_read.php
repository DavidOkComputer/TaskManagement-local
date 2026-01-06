<?php
/*mark_as_read.php para marcar las notificaciones como leidas*/

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
    $conn = getDBConnection();
    $notificationHelper = new NotificationHelper($conn);
    
    // Si se envía 'todas' => true, marcar todas como leídas
    if (isset($input['todas']) && $input['todas'] === true) {
        $affected = $notificationHelper->marcarTodasComoLeidas($id_usuario);
        echo json_encode([
            'success' => true,
            'message' => "Se marcaron {$affected} notificaciones como leídas",
            'affected' => $affected
        ]);
        exit;
    }
    
    // Si se envía un ID específico
    if (isset($input['id_notificacion'])) {
        $id_notificacion = (int)$input['id_notificacion'];
        $result = $notificationHelper->marcarComoLeida($id_notificacion, $id_usuario);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Notificación marcada como leída'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo marcar la notificación como leída'
            ]);
        }
        exit;
    }
    
    // Si se envía un array de IDs
    if (isset($input['ids']) && is_array($input['ids'])) {
        $affected = 0;
        foreach ($input['ids'] as $id) {
            if ($notificationHelper->marcarComoLeida((int)$id, $id_usuario)) {
                $affected++;
            }
        }
        echo json_encode([
            'success' => true,
            'message' => "Se marcaron {$affected} notificaciones como leídas",
            'affected' => $affected
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros inválidos. Envíe id_notificacion, ids[] o todas=true'
    ]);
    
} catch (Exception $e) {
    error_log("mark_as_read.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al marcar notificación: ' . $e->getMessage()
    ]);
}
?>