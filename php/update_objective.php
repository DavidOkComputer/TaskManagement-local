<?php
header('Content-Type: application/json');
require_once 'db_config.php';

// Reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => ''];

try {
    // Revisar si el método es post
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    // Validar ID del objetivo
    if (!isset($_POST['id_objetivo']) || empty($_POST['id_objetivo'])) {
        throw new Exception('ID de objetivo no proporcionado');
    }

    $id_objetivo = intval($_POST['id_objetivo']);

    if ($id_objetivo <= 0) {
        throw new Exception('ID de objetivo inválido');
    }

    // Validar los campos requeridos
    $required_fields = ['nombre', 'descripcion', 'fecha_cumplimiento', 'id_departamento'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    // Limpiar inputs
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $id_departamento = intval($_POST['id_departamento']);
    $ar = isset($_POST['ar']) ? trim($_POST['ar']) : '';

    // Validar longitud 
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres');
    }

    // Validar formato de fecha
    $date = DateTime::createFromFormat('Y-m-d', $fecha_cumplimiento);
    if (!$date || $date->format('Y-m-d') !== $fecha_cumplimiento) {
        throw new Exception('Formato de fecha inválido');
    }

    // Manejo de archivo adjunto
    $archivo_adjunto = '';
    $upload_dir = '../uploads/objetivos/';
    
    // Si hay archivo nuevo, subirlo
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            // Crear directorio para subir archivos si no existe
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $max_size = 10 * 1024 * 1024; // 10MB
            if ($_FILES['archivo']['size'] > $max_size) {
                throw new Exception('El archivo no puede exceder 10MB');
            }
            
            // Extension del archivo
            $file_name = $_FILES['archivo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Extensiones permitidas
            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Tipo de archivo no permitido');
            }

            // Generar un nombre de archivo único
            $new_filename = uniqid('obj_') . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            // Mover archivo subido
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $upload_path)) {
                $archivo_adjunto = $upload_path;
            } else {
                throw new Exception('Error al subir el archivo');
            }
        } else {
            throw new Exception('Error en la carga del archivo: ' . $_FILES['archivo']['error']);
        }
    }

    $conn = getDBConnection(); // Conexión a base de datos
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Primero, verificar que el objetivo existe
    $check_sql = "SELECT id_objetivo, archivo_adjunto FROM tbl_objetivos WHERE id_objetivo = ?";
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
    $old_archivo = $old_objetivo['archivo_adjunto'];
    
    $check_stmt->close();

    // Si no hay archivo nuevo, mantener el existente
    if (empty($archivo_adjunto)) {
        $archivo_adjunto = $old_archivo;
    } else {
        // Si hay archivo nuevo, eliminar el antiguo
        if (!empty($old_archivo) && file_exists($old_archivo)) {
            unlink($old_archivo);
        }
    }

    // Actualizar el objetivo
    $sql = "UPDATE tbl_objetivos SET 
                nombre = ?,
                descripcion = ?,
                id_departamento = ?,
                fecha_cumplimiento = ?,
                ar = ?,
                archivo_adjunto = ?
            WHERE id_objetivo = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    // Bind para parámetros
    $stmt->bind_param(
        "ssisssi",
        $nombre,
        $descripcion,
        $id_departamento,
        $fecha_cumplimiento,
        $ar,
        $archivo_adjunto,
        $id_objetivo
    );

    // Ejecutar la consulta
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Objetivo actualizado exitosamente';
            $response['id_objetivo'] = $id_objetivo;
        } else {
            // Si no hay cambios en los datos
            $response['success'] = true;
            $response['message'] = 'Objetivo actualizado (sin cambios en los datos)';
            $response['id_objetivo'] = $id_objetivo;
        }
    } else {
        throw new Exception('Error al actualizar el objetivo: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Eliminar el archivo si la base de datos no puede actualizar
    if (isset($archivo_adjunto) && !empty($archivo_adjunto) && file_exists($archivo_adjunto)) {
        unlink($archivo_adjunto);
    }
    
    error_log('Error in update_objective.php: ' . $e->getMessage());
}

echo json_encode($response);
?>