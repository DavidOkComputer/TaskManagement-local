<?php
/**
 * update_project.php - Actualizar proyectos con manejo robusto de errores
 * FIXED: Auto-calculate estado based on deadline and progress
 */

// Start output buffering to prevent premature output
ob_start();

header('Content-Type: application/json; charset=UTF-8');
require_once 'db_config.php';

$response = ['success' => false, 'message' => ''];

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    // Validate project ID
    if (!isset($_POST['id_proyecto'])) {
        throw new Exception('ID de proyecto requerido');
    }

    $id_proyecto = intval($_POST['id_proyecto']);
    if ($id_proyecto <= 0) {
        throw new Exception('ID de proyecto inválido');
    }

    // Validate required fields
    $required_fields = [
        'nombre',
        'descripcion',
        'id_departamento',
        'fecha_creacion',
        'fecha_cumplimiento',
        'id_creador',
        'id_tipo_proyecto',
        'puede_editar_otros'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
        
        if (empty(trim($_POST[$field])) && $field !== 'puede_editar_otros' && $field !== 'ar') {
            throw new Exception("El campo {$field} no puede estar vacío");
        }
    }

    // Sanitize and validate inputs
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_departamento = intval($_POST['id_departamento']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;
    $ar = isset($_POST['ar']) ? trim($_POST['ar']) : '';
    $archivo_adjunto = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : '';
    $id_creador = intval($_POST['id_creador']);
    $id_tipo_proyecto = intval($_POST['id_tipo_proyecto']);
    $puede_editar_otros = intval($_POST['puede_editar_otros']);
    
    // Validate field lengths
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres');
    }
    if (strlen($archivo_adjunto) > 300) {
        throw new Exception('La ruta del archivo no puede exceder 300 caracteres');
    }

    // Format dates for database
    if (strpos($fecha_creacion, 'T') !== false) {
        $fecha_creacion = str_replace('T', ' ', $fecha_creacion);
    }
    
    // Validate dates
    if (strtotime($fecha_creacion) === false) {
        throw new Exception('La fecha de creación no es válida');
    }
    if (strtotime($fecha_cumplimiento) === false) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }
    
    if (strtotime($fecha_cumplimiento) < strtotime($fecha_creacion)) {
        throw new Exception('La fecha de entrega debe ser posterior o igual a la fecha de inicio');
    }

    // FIXED: Auto-calculate estado based on deadline and progress
    // This ensures that when a deadline is updated, the status reflects the actual state
    $today = date('Y-m-d');
    $deadline = substr($fecha_cumplimiento, 0, 10); // Extract date portion
    
    if ($progreso >= 100) {
        $estado = 'completado';
    } elseif ($deadline < $today) {
        $estado = 'vencido';
    } elseif ($progreso > 0) {
        $estado = 'en proceso';
    } else {
        $estado = 'pendiente';
    }

    // Get database connection
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Handle group project users
    $usuarios_grupo = [];
    $id_participante = 0;
    
    if ($id_tipo_proyecto == 1) {
        if (isset($_POST['usuarios_grupo'])) {
            $usuarios_grupo = json_decode($_POST['usuarios_grupo'], true);
            if (!is_array($usuarios_grupo) || empty($usuarios_grupo)) {
                throw new Exception('Debes seleccionar al menos un usuario para el proyecto grupal');
            }
        } else {
            throw new Exception('Debes seleccionar usuarios para el proyecto grupal');
        }
    } else {
        // Individual project - use participante
        if (isset($_POST['id_participante'])) {
            $id_participante = intval($_POST['id_participante']);
        }
    }

    // Update project with auto-calculated estado
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
            id_tipo_proyecto = ?,
            puede_editar_otros = ?
            WHERE id_proyecto = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    // Bind parameters with auto-calculated estado
    $stmt->bind_param(
        "ssissississii",
        $nombre,              // s-1
        $descripcion,         // s-2
        $id_departamento,     // i-3
        $fecha_creacion,      // s-4
        $fecha_cumplimiento,  // s-5
        $progreso,            // i-6
        $ar,                  // s-7
        $estado,              // s-8 (auto-calculated)
        $archivo_adjunto,     // s-9
        $id_participante,     // i-10
        $id_tipo_proyecto,    // i-11
        $puede_editar_otros,  // i-12
        $id_proyecto          // i-13 (WHERE clause)
    );

    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el proyecto: ' . $stmt->error);
    }

    $stmt->close();

    // Handle group project users
    if ($id_tipo_proyecto == 1) {
        // Delete existing assignments
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

        // Insert new assignments
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
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Error in update_project.php: ' . $e->getMessage());
}

// Clear output buffer and send response
ob_end_clean();
echo json_encode($response);
exit;
?>