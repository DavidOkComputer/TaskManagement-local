<?php
/*get_notifications.php para saber las notificaciones del usuario actual*/

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
    
    // Parámetros opcionales
    $solo_no_leidas = isset($_GET['no_leidas']) && $_GET['no_leidas'] === '1';
    $limite = isset($_GET['limite']) ? min((int)$_GET['limite'], 50) : 20;
    $conn = getDBConnection();
    $notificationHelper = new NotificationHelper($conn);
    $notificaciones = $notificationHelper->obtenerNotificaciones($id_usuario, $solo_no_leidas, $limite);
    $total_no_leidas = $notificationHelper->contarNoLeidas($id_usuario);
    
    // Formatear notificaciones para el frontend
    $notificaciones_formateadas = [];
    foreach ($notificaciones as $notif) {
        // Crear fecha desde la base de datos (asumiendo que está en UTC o en hora del servidor)
        $fecha = new DateTime($notif['fecha_creacion'], new DateTimeZone('America/Mexico_City'));
        $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));
        $diferencia = $ahora->diff($fecha);
        
        // Formatear tiempo relativo
        if ($diferencia->days == 0) {
            if ($diferencia->h == 0) {
                if ($diferencia->i == 0) {
                    $tiempo_relativo = 'Hace un momento';
                } elseif ($diferencia->i == 1) {
                    $tiempo_relativo = 'Hace 1 minuto';
                } else {
                    $tiempo_relativo = 'Hace ' . $diferencia->i . ' minutos';
                }
            } elseif ($diferencia->h == 1) {
                $tiempo_relativo = 'Hace 1 hora';
            } else {
                $tiempo_relativo = 'Hace ' . $diferencia->h . ' horas';
            }
        } elseif ($diferencia->days == 1) {
            $tiempo_relativo = 'Ayer a las ' . $fecha->format('H:i');
        } elseif ($diferencia->days < 7) {
            $tiempo_relativo = 'Hace ' . $diferencia->days . ' días';
        } else {
            $tiempo_relativo = $fecha->format('d/m/Y H:i');
        }
        
        // Formato de fecha completa para referencia
        $fecha_formateada = $fecha->format('d/m/Y H:i:s');
        
        $notificaciones_formateadas[] = [
            'id_notificacion' => (int)$notif['id_notificacion'],
            'tipo' => $notif['tipo'],
            'titulo' => $notif['titulo'],
            'mensaje' => $notif['mensaje'],
            'id_referencia' => $notif['id_referencia'] ? (int)$notif['id_referencia'] : null,
            'tipo_referencia' => $notif['tipo_referencia'],
            'leido' => (bool)$notif['leido'],
            'fecha_creacion' => $notif['fecha_creacion'],
            'fecha_formateada' => $fecha_formateada,
            'tiempo_relativo' => $tiempo_relativo,
            'icono' => NotificationHelper::getIconoPorTipo($notif['tipo']),
            'color' => NotificationHelper::getColorPorTipo($notif['tipo'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notificaciones' => $notificaciones_formateadas,
        'total_no_leidas' => $total_no_leidas,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("get_notifications.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener notificaciones: ' . $e->getMessage()
    ]);
}
?>