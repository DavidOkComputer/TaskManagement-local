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
        'id_departamento',
        'fecha_creacion',
        'fecha_cumplimiento',
        'ar',
        'estado',
        'archivo_adjunto',
        'id_creador',
        'id_tipo_proyecto'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $nombre = trim($_POST['nombre']);//limpiar y validar inputs
    $descripcion = trim($_POST['descripcion']);
    $id_departamento = intval($_POST['id_departamento']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso = intval($_POST['progreso']);
    $ar = intval($_POST['ar']);
    $estado = trim($_POST['estado']); 
    $archivo_adjunto = trim($_POST['archivo_adjunto']);
    $id_creador = intval($_POST['id_creador']);
    $id_participante = intval($_POST['id_participante']);
    $id_tipo_proyecto = intval($_POST['id_tipo_proyecto']);

    if (strlen($nombre) > 100) {//validar el largo delos campos
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres'); 
    }
    if (strlen($archivo_adjunto) > 300) {
        throw new Exception('La ruta del archivo no puede exceder 300 caracteres');
    }

    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];//validar que el estado sea uno de los valores establecidos
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
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
                id_departamento,
                fecha_inicio,
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

    $stmt->bind_param(
        "ssissiissiii",
        $nombre,              //s-1
        $descripcion,        //s-2
        $id_departamento,           //i-3
        $fecha_creacion,            //s-4
        $fecha_cumplimiento,        //s-5
        $progreso,                  //i-6
        $ar,                        //i-7
        $estado,                    //s-8
        $archivo_adjunto,           //s-9
        $id_creador,                //i-10
        $id_participante,           //i-11
        $id_tipo_proyecto           //i-12
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