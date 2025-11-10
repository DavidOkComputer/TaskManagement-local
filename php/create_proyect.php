<?php
header('Content-Type: application/json');
require_once 'db_config.php';

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
        'progreso',
        'ar',
        'estado',
        'archivo_adjunto',
        'id_creador',
        'id_participante',
        'id_tipo_proyecto'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    // Sanitize and validate inputs
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_departamento = intval($_POST['id_departamento']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso = intval($_POST['progreso']);
    $ar = intval($_POST['ar']);
    $estado = trim($_POST['estado']); // FIXED: was $_POST['id_superior']
    $archivo_adjunto = trim($_POST['archivo_adjunto']);
    $id_creador = intval($_POST['id_creador']);
    $id_participante = intval($_POST['id_participante']);
    $id_tipo_proyecto = intval($_POST['id_tipo_proyecto']);

    // Validate field lengths
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres'); // FIXED: was checking $apellido
    }
    if (strlen($archivo_adjunto) > 300) {
        throw new Exception('La ruta del archivo no puede exceder 300 caracteres');
    }

    // Validate progress is between 0-100
    if ($progreso < 0 || $progreso > 100) {
        throw new Exception('El progreso debe estar entre 0 y 100');
    }

    // Validate estado is one of the allowed values
    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
    }

    // Validate dates
    if (strtotime($fecha_creacion) === false) {
        throw new Exception('La fecha de creación no es válida');
    }
    if (strtotime($fecha_cumplimiento) === false) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }

    // Get database connection
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // SQL query - FIXED: was tbl_usuario, now tbl_proyectos
    $sql = "INSERT INTO tbl_proyectos (
                nombre,
                descripcion,
                id_departamento,
                fecha_creacion,
                fecha_cumplimiento,
                progreso,
                ar,
                estado,
                archivo_adjunto,
                id_creador,
                id_participante,
                id_tipo_proyecto
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    // Bind parameters - FIXED: corrected type string from "ssississssiii" to "ssisssiissiii"
    // Types: s=string, i=integer
    // nombre(s), descripcion(s), id_departamento(i), fecha_creacion(s), fecha_cumplimiento(s),
    // progreso(i), ar(i), estado(s), archivo_adjunto(s), id_creador(i), id_participante(i), id_tipo_proyecto(i)
    $stmt->bind_param(
        "ssisssiissiii",
        $nombre,
        $descripcion,
        $id_departamento,
        $fecha_creacion,
        $fecha_cumplimiento,
        $progreso,
        $ar,
        $estado,
        $archivo_adjunto,
        $id_creador,
        $id_participante,
        $id_tipo_proyecto
    );

    // Execute query
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Proyecto registrado exitosamente';
        $response['id_proyecto'] = $stmt->insert_id; // FIXED: was id_objetivo
    } else {
        throw new Exception('Error al crear el proyecto: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    // Optional: Log the error
    // error_log($e->getMessage());
}

echo json_encode($response);
?>