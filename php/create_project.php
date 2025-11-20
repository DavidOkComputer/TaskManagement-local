<?php
/**
 * create_project.php - Crear proyectos con manejo robusto de errores
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

    // Validate required fields
    $required_fields = [
        'nombre',
        'descripcion',
        'id_departamento',
        'fecha_creacion',
        'fecha_cumplimiento',
        'estado',
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
    $estado = trim($_POST['estado']);
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

    // Validate estado
    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
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

    // Insert project - FIXED: Include puede_editar_otros in the query
    $sql = "INSERT INTO tbl_proyectos (
                nombre,
                descripcion,
                id_departamento,
                fecha_inicio,
                fecha_cumplimiento,
                progreso,
                ar,
                estado,
                archivo_adjunto,
                id_creador,
                id_participante,
                id_tipo_proyecto,
                puede_editar_otros
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    // FIXED: Bind parameters with correct types - 13 total (was missing puede_editar_otros)
    $stmt->bind_param(
        "ssissississii",
        $nombre,              // s-1
        $descripcion,         // s-2
        $id_departamento,     // i-3
        $fecha_creacion,      // s-4
        $fecha_cumplimiento,  // s-5
        $progreso,            // i-6
        $ar,                  // s-7
        $estado,              // s-8
        $archivo_adjunto,     // s-9
        $id_creador,          // i-10
        $id_participante,     // i-11
        $id_tipo_proyecto,    // i-12
        $puede_editar_otros   // i-13
    );

    if (!$stmt->execute()) {
        throw new Exception('Error al crear el proyecto: ' . $stmt->error);
    }

    $id_proyecto = $stmt->insert_id;
    $stmt->close();

    // Insert group project users if applicable
    if ($id_tipo_proyecto == 1 && !empty($usuarios_grupo)) {
        $sql_usuarios = "INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)";
        $stmt_usuarios = $conn->prepare($sql_usuarios);

        if (!$stmt_usuarios) {
            throw new Exception('Error al preparar consulta de usuarios: ' . $conn->error);
        }

        foreach ($usuarios_grupo as $id_usuario) {
            $id_usuario = intval($id_usuario);
            $stmt_usuarios->bind_param("ii", $id_proyecto, $id_usuario);
            
            if (!$stmt_usuarios->execute()) {
                throw new Exception('Error al asignar usuarios al proyecto: ' . $stmt_usuarios->error);
            }
        }
        $stmt_usuarios->close();
    }

    $response['success'] = true;
    $response['message'] = 'Proyecto registrado exitosamente';
    $response['id_proyecto'] = $id_proyecto;

    $conn->close();

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Error in create_project.php: ' . $e->getMessage());
}

// Clear output buffer and send response
ob_end_clean();
echo json_encode($response);
exit;
?>