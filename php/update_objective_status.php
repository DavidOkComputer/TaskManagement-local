<?php
header('Content-Type: application/json');
require_once 'db_config.php';

error_reporting(E_ALL);//debug
ini_set('display_errors', 0);

ob_start();//iniciar buffering para que no haya nada antes del JSON

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id_objetivo']) || empty($input['id_objetivo'])) {
        throw new Exception('ID de objetivo no proporcionado');
    }

    $id_objetivo = intval($input['id_objetivo']);

    if ($id_objetivo <= 0) {
        throw new Exception('ID de objetivo inválido');
    }

    if (!isset($input['estado']) || empty($input['estado'])) {
        throw new Exception('Estado no proporcionado');
    }

    $nuevo_estado = trim($input['estado']);

    $estados_validos = ['pendiente', 'en proceso', 'completado', 'vencido'];
    if (!in_array($nuevo_estado, $estados_validos)) {
        throw new Exception('Estado inválido: ' . $nuevo_estado);
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $check_sql = "SELECT id_objetivo, estado FROM tbl_objetivos WHERE id_objetivo = ?";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception('Error al preparar verificación: ' . $conn->error);
    }

    $check_stmt->bind_param('i', $id_objetivo);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        throw new Exception('El objetivo no existe');
    }

    $old_objective = $check_result->fetch_assoc();
    $check_stmt->close();

    $sql = "UPDATE tbl_objetivos SET estado = ? WHERE id_objetivo = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param('si', $nuevo_estado, $id_objetivo);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Estado del objetivo actualizado exitosamente';
            $response['id_objetivo'] = $id_objetivo;
            $response['nuevo_estado'] = $nuevo_estado;
            $response['estado_anterior'] = $old_objective['estado'];
        } else {
            $response['success'] = true;
            $response['message'] = 'Estado del objetivo actualizado (sin cambios en los datos)';
            $response['id_objetivo'] = $id_objetivo;
            $response['nuevo_estado'] = $nuevo_estado;
        }
    } else {
        throw new Exception('Error al actualizar el objetivo: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error in update_objective_status.php: ' . $e->getMessage());
}

ob_clean();//limpiar buffer y enviar json
echo json_encode($response);
exit;
?>