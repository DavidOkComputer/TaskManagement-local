<?php
//create_tarea.php - creare nueva tarea

header('Content-Type: application/json');
require_once 'db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }
    $required_fields = [//validar campos requeridos
        'nombre',
        'descripcion',
        'id_proyecto',
        'fecha_vencimiento',
        'estado'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $nombre = trim($_POST['nombre']);//limpiar y validar inputs
    $descripcion = trim($_POST['descripcion']);
    $id_proyecto = intval($_POST['id_proyecto']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $estado = trim($_POST['estado']);

    //validar longitud
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 250) {
        throw new Exception('La descripción no puede exceder 250 caracteres');
    }
    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
    }
    if (strtotime($fecha_cumplimiento) === false) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }
    if ($id_proyecto <= 0) {
        throw new Exception('El ID del proyecto no es válido');
    }

    $conn = getDBConnection();//conexion a abse de datos
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    //verificar que existe el proyecto
    $verify_query = "SELECT id_proyecto FROM tbl_proyectos WHERE id_proyecto = ?";
    $verify_stmt = $conn->prepare($verify_query);
    
    if (!$verify_stmt) {
        throw new Exception('Error al preparar la consulta de verificación: ' . $conn->error);
    }

    $verify_stmt->bind_param("i", $id_proyecto);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        throw new Exception('El proyecto especificado no existe');
    }
    $verify_stmt->close();

    //reemplazar con id de la sesion cuando se implemente
    $id_creador = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 1;

    //preparar y ejecutar el insert
    $sql = "INSERT INTO tbl_tareas (
                nombre,
                descripcion,
                id_proyecto,
                id_creador,
                fecha_cumplimiento,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param(
        "ssiiss",
        $nombre,            // s-1
        $descripcion,      // s-2
        $id_proyecto,             // i-3
        $id_creador,              // i-4
        $fecha_cumplimiento,      // s-5
        $estado                   // s-6
    );

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Tarea registrada exitosamente';
        $response['task_id'] = $stmt->insert_id;
    } else {
        throw new Exception('Error al crear la tarea: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('save_task.php Error: ' . $e->getMessage());
}

echo json_encode($response);
?>