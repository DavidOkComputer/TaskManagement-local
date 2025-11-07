<?php
header('Content-Type: application/json');
require_once 'db_config.php';

//reporte de errores de debg
error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => ''];

try {
    //revisar si el metodo es post
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    //validar los campos requeridos
    $required_fields = ['nombre', 'descripcion', 'id_departamento', 'fecha_creacion', 'fecha_cumplimiento', 'progreso', 'ar', 'estado', 'archivo_adjunto','id_creador','id_participante','id_tipo_proyecto'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    //limpiar inputs
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_departamento =intval($_POST['id_departamento']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso = intval($_POST['progreso']);
    $ar = intval($_POST['ar']);
    $estado = trim($_POST['id_superior']);
    $archivo_adjunto = trim($_POST['archivo_adjunto']);
    $id_creador = intval($_POST['id_creador']);
    $id_participante = intval($_POST['id_participante']);
    $id_tipo_proyecto = intval($_POST['id_tipo_proyecto']);

    //validar longitud 
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($apellido) > 200) {
        throw new Exception('La descipcion no puede exceder 200 caracteres');
    }

    $conn = getDBConnection();//conexion a base de datos
    //query sql
    $sql = "INSERT INTO tbl_usuario (
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

    //bind para parametroz
    $stmt->bind_param(
        "ssississssiii",
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

    //ejecutar la consulta
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Proyecto registrado exitosamente';
        $response['id_objetivo'] = $stmt->insert_id;
    } else {
        throw new Exception('Error al crear el objetivo: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>