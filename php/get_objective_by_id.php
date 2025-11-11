<?php
// get_objective_by_id.php - Obtiene un objetivo específico para edición

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'objetivo' => null, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }

    // Validar que se proporcionó el ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID de objetivo no proporcionado');
    }

    $id_objetivo = intval($_GET['id']);

    if ($id_objetivo <= 0) {
        throw new Exception('ID de objetivo inválido');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Query para obtener todos los datos del objetivo
    $sql = "SELECT 
                o.id_objetivo,
                o.nombre,
                o.descripcion,
                o.id_departamento,
                o.fecha_cumplimiento,
                o.progreso,
                o.ar,
                o.estado,
                o.archivo_adjunto,
                o.id_creador
            FROM tbl_objetivos o
            WHERE o.id_objetivo = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param('i', $id_objetivo);

    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $objetivo = $result->fetch_assoc();

    if (!$objetivo) {
        throw new Exception('Objetivo no encontrado');
    }

    $response['success'] = true;
    $response['objetivo'] = [
        'id_objetivo' => (int)$objetivo['id_objetivo'],
        'nombre' => $objetivo['nombre'],
        'descripcion' => $objetivo['descripcion'],
        'id_departamento' => (int)$objetivo['id_departamento'],
        'fecha_cumplimiento' => $objetivo['fecha_cumplimiento'],
        'progreso' => (int)$objetivo['progreso'],
        'ar' => $objetivo['ar'],
        'estado' => $objetivo['estado'],
        'archivo_adjunto' => $objetivo['archivo_adjunto'],
        'id_creador' => (int)$objetivo['id_creador']
    ];

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Error in get_objective_by_id.php: ' . $e->getMessage());
}

echo json_encode($response);
?>