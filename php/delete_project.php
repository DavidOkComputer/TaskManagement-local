<?php
/*delete_project.php para Eliminar proyecto solo el creador puede eliminar */
 
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';
 
// Iniciar buffer de salida
ob_start();
 
$response = ['success' => false, 'message' => ''];
 
try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
 
    $id_usuario = (int)$_SESSION['user_id'];
 
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }
 
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id_proyecto']) || empty($input['id_proyecto'])) {
        throw new Exception('El ID del proyecto es requerido');
    }
 
    $id_proyecto = intval($input['id_proyecto']);
    
    if ($id_proyecto <= 0) {
        throw new Exception('ID de proyecto inválido');
    }
 
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    //Verificar que el proyecto existe y que el usuario es el creador
    $checkQuery = "
        SELECT id_proyecto, id_creador, nombre
        FROM tbl_proyectos
        WHERE id_proyecto = ?
    ";
    
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
    
    $proyecto = $result->fetch_assoc();
    $checkStmt->close();
 
    //verificar que el usuario es el creador del proyecto
    if ((int)$proyecto['id_creador'] !== $id_usuario) {
        throw new Exception('No tienes permiso para eliminar este proyecto. Solo el creador puede eliminarlo.');
    }
 
    // Iniciar transacción para asegurar integridad de datos
    $conn->begin_transaction();
 
    try {
        //Eliminar las tareas relacionadas con el proyecto
        $deleteTasksQuery = "DELETE FROM tbl_tareas WHERE id_proyecto = ?";
        $deleteTasksStmt = $conn->prepare($deleteTasksQuery);
        
        if (!$deleteTasksStmt) {
            throw new Exception('Error al preparar la consulta de eliminación de tareas: ' . $conn->error);
        }
        
        $deleteTasksStmt->bind_param("i", $id_proyecto);
        
        if (!$deleteTasksStmt->execute()) {
            throw new Exception('Error al eliminar tareas asociadas: ' . $deleteTasksStmt->error);
        }
        
        $tareas_eliminadas = $deleteTasksStmt->affected_rows;
        $deleteTasksStmt->close();
 
        //Eliminar las asignaciones de usuarios en proyectos grupales
        $deleteUsersQuery = "DELETE FROM tbl_proyecto_usuarios WHERE id_proyecto = ?";
        $deleteUsersStmt = $conn->prepare($deleteUsersQuery);
        
        if (!$deleteUsersStmt) {
            throw new Exception('Error al preparar la consulta de eliminación de usuarios: ' . $conn->error);
        }
        
        $deleteUsersStmt->bind_param("i", $id_proyecto);
        
        if (!$deleteUsersStmt->execute()) {
            throw new Exception('Error al eliminar asignaciones de usuarios: ' . $deleteUsersStmt->error);
        }
        
        $deleteUsersStmt->close();
 
        //Eliminar el proyecto
        $deleteQuery = "DELETE FROM tbl_proyectos WHERE id_proyecto = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        
        if (!$deleteStmt) {
            throw new Exception('Error al preparar la consulta de eliminación: ' . $conn->error);
        }
        
        $deleteStmt->bind_param("i", $id_proyecto);
        
        if (!$deleteStmt->execute()) {
            throw new Exception('Error al eliminar el proyecto: ' . $deleteStmt->error);
        }
        
        $deleteStmt->close();
 
        // Confirmar transacción
        $conn->commit();
 
        $response['success'] = true;
        $response['message'] = 'Proyecto "' . $proyecto['nombre'] . '" eliminado exitosamente';
        $response['tareas_eliminadas'] = $tareas_eliminadas;
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        throw $e;
    }
 
    $conn->close();
 
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('delete_project.php Error: ' . $e->getMessage());
}
 
// Limpiar buffer y enviar respuesta
ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();
?>