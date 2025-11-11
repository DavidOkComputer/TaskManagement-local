<?php
/**
 * update_project.php - Updated to handle group project user changes
 */

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    if (!isset($_POST['id_proyecto'])) {
        throw new Exception('ID de proyecto requerido');
    }

    $id_proyecto = intval($_POST['id_proyecto']);

    // Same validation as create_project.php
    $required_fields = ['nombre', 'descripcion', 'id_departamento', 'fecha_creacion', 
                        'fecha_cumplimiento', 'estado', 'id_creador', 'id_tipo_proyecto'];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_departamento = intval($_POST['id_departamento']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;
    $ar = isset($_POST['ar']) ? intval($_POST['ar']) : 0;
    $estado = trim($_POST['estado']);
    $archivo_adjunto = trim($_POST['archivo_adjunto']);
    $id_creador = intval($_POST['id_creador']);
    $id_tipo_proyecto = intval($_POST['id_tipo_proyecto']);
    
    // Parse usuarios_grupo if it's a group project
    $usuarios_grupo = [];
    if ($id_tipo_proyecto == 1 && isset($_POST['usuarios_grupo'])) {
        $usuarios_grupo = json_decode($_POST['usuarios_grupo'], true);
        if (!is_array($usuarios_grupo) || empty($usuarios_grupo)) {
            throw new Exception('Debes seleccionar al menos un usuario para el proyecto grupal');
        }
    }

    // For individual projects, use id_participante
    $id_participante = 0;
    if ($id_tipo_proyecto == 2 && isset($_POST['id_participante'])) {
        $id_participante = intval($_POST['id_participante']);
    }

    // Validations
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres');
    }
    if (strlen($archivo_adjunto) > 300) {
        throw new Exception('La ruta del archivo no puede exceder 300 caracteres');
    }

    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
    }
    
    if (strtotime($fecha_creacion) === false) {
        throw new Exception('La fecha de creación no es válida');
    }
    if (strtotime($fecha_cumplimiento) === false) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Update project
    $sql = "UPDATE tbl_proyectos SET
            nombre = ?,
            descripcion = ?,
            id_departamento = ?,
            fecha_inicio = ?,
            fecha_cumplimiento = ?,
            progreso = ?,
            ar = ?,
            estado = ?,
            archivo_adjunto = ?,
            id_participante = ?,
            id_tipo_proyecto = ?
            WHERE id_proyecto = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param(
        "ssissiissiiii",
        $nombre,
        $descripcion,
        $id_departamento,
        $fecha_creacion,
        $fecha_cumplimiento,
        $progreso,
        $ar,
        $estado,
        $archivo_adjunto,
        $id_participante,
        $id_tipo_proyecto,
        $id_proyecto
    );

    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el proyecto: ' . $stmt->error);
    }

    $stmt->close();

    // Handle group project users
    if ($id_tipo_proyecto == 1) {
        // Delete existing user assignments
        $sql_delete = "DELETE FROM tbl_proyecto_usuarios WHERE id_proyecto = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        
        if (!$stmt_delete) {
            throw new Exception('Error al preparar delete: ' . $conn->error);
        }

        $stmt_delete->bind_param("i", $id_proyecto);
        if (!$stmt_delete->execute()) {
            throw new Exception('Error al eliminar asignaciones anteriores: ' . $stmt_delete->error);
        }
        $stmt_delete->close();

        // Insert new user assignments
        if (!empty($usuarios_grupo)) {
            $sql_insert = "INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);

            if (!$stmt_insert) {
                throw new Exception('Error al preparar insert: ' . $conn->error);
            }

            foreach ($usuarios_grupo as $id_usuario) {
                $id_usuario = intval($id_usuario);
                $stmt_insert->bind_param("ii", $id_proyecto, $id_usuario);
                
                if (!$stmt_insert->execute()) {
                    throw new Exception('Error al asignar usuarios: ' . $stmt_insert->error);
                }
            }
            $stmt_insert->close();
        }
    }

    $response['success'] = true;
    $response['message'] = 'Proyecto actualizado exitosamente';
    $response['id_proyecto'] = $id_proyecto;

    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log($e->getMessage());
}

echo json_encode($response);
?>