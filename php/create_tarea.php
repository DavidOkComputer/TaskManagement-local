<?php
header('Content-Type: application/json');
require_once 'db_config.php';
//crete_project.php
error_reporting(E_ALL);//reportar errores para el debug
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {//validar tipo de solicitud
        throw new Exception('Método de solicitud inválido');
    }

    $required_fields = [//validar campos requerido
        'nombre',
        'descripcion',
        'id_proyecto',
        'fecha_inicio',
        'fecha_creacion',
        'fecha_cumplimiento',
        'estado',
        'id_creador'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $nombre = trim($_POST['nombre']);//limpiar y validar inputs
    $descripcion = trim($_POST['descripcion']);
    $id_proyecto = intval($_POST['id_proyecto']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_inicio = trim($_POST['fecha_inicio']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $estado = trim($_POST['estado']); 
    $id_creador = intval($_POST['id_creador']);

    if (strlen($nombre) > 100) {//validar el largo delos campos
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres'); 
    }
    $estados_validos = ['pendiente', 'vencido', 'completado'];//validar que el estado sea uno de los valores establecidos
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, vencido o completado');
    }
    if (strtotime($fecha_creacion) === false) {//validar datos
        throw new Exception('La fecha de creación no es válida');
    }
    if (strtotime($fecha_cumplimiento) === false) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }

    $conn = getDBConnection();//conexion a base de datos
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $sql = "INSERT INTO tbl_proyectos (
                nombre,
                descripcion,
                id_proyecto,
                fecha_inicio,
                fecha_cumplimiento,
                estado,
                id_creador,
            ) VALUES ( ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param(
        "ssisssis",
        $nombre,              //s-1
        $descripcion,        //s-2
        $id_proyecto,               //i-3
        $fecha_creacion,            //s-4
        $fecha_cumplimiento,        //s-5
        $estado,                    //s-6
        $id_creador,                //i-7
        $fecha_inicio               //s-8
    );

    if ($stmt->execute()) {//ejecutar qury preparada
        $response['success'] = true;
        $response['message'] = 'Proyecto registrado exitosamente';
        $response['id_proyecto'] = $stmt->insert_id;
    } else {
        throw new Exception('Error al crear el proyecto: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log($e->getMessage());
}

echo json_encode($response);
?>