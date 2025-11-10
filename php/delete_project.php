<?php
// eliminar_proyecto.php

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'message' => ''];

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    // Get JSON data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id_proyecto']) || empty($input['id_proyecto'])) {
        throw new Exception('El ID del proyecto es requerido');
    }

    $id_proyecto = intval($input['id_proyecto']);

    // Validate project ID
    if ($id_proyecto <= 0) {
        throw new Exception('ID de proyecto inválido');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Check if project exists
    $checkQuery = "SELECT id_proyecto FROM tbl_proyectos WHERE id_proyecto = ?";
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        throw new Exception('Error al preparar la consulta de verificación: ' . $conn->error);
    }

    $checkStmt->bind_param("i", $id_proyecto);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('El proyecto no existe');
    }

    $checkStmt->close();

    // Delete associated tasks first (if needed)
    $deleteTasksQuery = "DELETE FROM tbl_tareas WHERE id_proyecto = ?";
    $deleteTasksStmt = $conn->prepare($deleteTasksQuery);
    
    if (!$deleteTasksStmt) {
        throw new Exception('Error al preparar la consulta de eliminación de tareas: ' . $conn->error);
    }

    $deleteTasksStmt->bind_param("i", $id_proyecto);
    
    if (!$deleteTasksStmt->execute()) {
        throw new Exception('Error al eliminar tareas asociadas: ' . $deleteTasksStmt->error);
    }

    $deleteTasksStmt->close();

    // Delete project
    $deleteQuery = "DELETE FROM tbl_proyectos WHERE id_proyecto = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $deleteStmt->bind_param("i", $id_proyecto);
    
    if ($deleteStmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Proyecto eliminado exitosamente';
    } else {
        throw new Exception('Error al eliminar el proyecto: ' . $deleteStmt->error);
    }

    $deleteStmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>