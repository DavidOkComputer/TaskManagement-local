<?php
/**
 * api_email_config.php
 * API para configurar y gestionar el sistema de notificaciones por email
 * 
 * Endpoints:
 *   GET    - Obtener configuración actual
 *   POST   - Actualizar configuración
 *   POST action=test_connection - Probar conexión SMTP
 *   POST action=send_test - Enviar email de prueba
 *   POST action=configure_gmail - Configurar Gmail rápidamente
 *   GET  action=stats - Obtener estadísticas de la cola
 * 
 * @package TaskManagement\API
 */

ob_start();
session_start();
header('Content-Type: application/json');
ob_end_clean();

// Verificar autenticación (solo administradores)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['acceso']) || $_SESSION['acceso'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Acceso denegado. Solo administradores pueden acceder a esta configuración.'
    ]);
    exit;
}

require_once('db_config.php');
require_once(__DIR__ . '/../includes/email/EmailService.php');

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión a base de datos']);
    exit;
}

$emailService = new EmailService($conn);
$config = $emailService->getConfig();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'stats') {
                // Obtener estadísticas de la cola
                $stats = $emailService->getQueueStats();
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
            } else {
                // Obtener configuración actual (sin contraseñas)
                $configData = $config->getAll();
                $validation = $config->validateConfig();
                
                echo json_encode([
                    'success' => true,
                    'config' => $configData,
                    'validation' => $validation,
                    'is_enabled' => $config->isEnabled(),
                    'is_test_mode' => $config->isTestMode()
                ]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $action = $input['action'] ?? $action;
            
            switch ($action) {
                case 'test_connection':
                    // Probar conexión SMTP
                    $result = $emailService->testConnection();
                    echo json_encode([
                        'success' => $result['success'],
                        'message' => $result['message'],
                        'debug' => $result['debug'] ?? null
                    ]);
                    break;
                    
                case 'send_test':
                    // Enviar email de prueba
                    $to_email = $input['email'] ?? $_SESSION['user_email'] ?? null;
                    
                    if (empty($to_email)) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Se requiere un email de destino'
                        ]);
                        break;
                    }
                    
                    $result = $emailService->sendTestEmail($to_email);
                    echo json_encode($result);
                    break;
                    
                case 'configure_gmail':
                    // Configuración rápida de Gmail
                    $email = $input['email'] ?? null;
                    $app_password = $input['app_password'] ?? null;
                    
                    if (empty($email) || empty($app_password)) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Se requiere email y contraseña de aplicación'
                        ]);
                        break;
                    }
                    
                    // Validar formato de email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Formato de email inválido'
                        ]);
                        break;
                    }
                    
                    // Configurar Gmail
                    $success = $config->configureGmail($email, $app_password);
                    
                    if ($success) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Gmail configurado correctamente. Ahora puede probar la conexión.'
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Error al guardar la configuración'
                        ]);
                    }
                    break;
                    
                case 'update':
                default:
                    // Actualizar configuración
                    $allowed_keys = [
                        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_encryption',
                        'smtp_from_name', 'smtp_from_email', 'smtp_reply_to',
                        'email_enabled', 'test_mode', 'system_url',
                        'dias_recordatorio_antes', 'max_reintentos', 'emails_por_lote'
                    ];
                    
                    $updated = [];
                    $errors = [];
                    
                    foreach ($input as $key => $value) {
                        if ($key === 'action') continue;
                        
                        if (in_array($key, $allowed_keys)) {
                            if ($config->set($key, $value)) {
                                $updated[] = $key;
                            } else {
                                $errors[] = "Error al actualizar: $key";
                            }
                        }
                        
                        // Contraseña requiere encriptación
                        if ($key === 'smtp_password' && !empty($value)) {
                            if ($config->set('smtp_password', $value, true)) {
                                $updated[] = 'smtp_password';
                            } else {
                                $errors[] = "Error al actualizar: smtp_password";
                            }
                        }
                    }
                    
                    if (empty($errors)) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Configuración actualizada',
                            'updated' => $updated
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'errors' => $errors,
                            'updated' => $updated
                        ]);
                    }
                    break;
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}

$conn->close();