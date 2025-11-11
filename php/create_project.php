<?php
header('Content-Type: application/json');
require_once 'db_config.php';
//create_project.php - para crear proyectos
error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    $required_fields = [
        'nombre',
        'descripcion',
        'id_departamento',
        'fecha_creacion',
        'fecha_cumplimiento',
        'estado',
        'id_creador',
        'id_tipo_proyecto'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            //ar es opcional entonces no lo validamos
            if ($field === 'ar') continue;
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
    
    //insertar usuarios_grupo si es proyecto grupal
    $usuarios_grupo = [];
    if ($id_tipo_proyecto == 1 && isset($_POST['usuarios_grupo'])) {
        $usuarios_grupo = json_decode($_POST['usuarios_grupo'], true);
        if (!is_array($usuarios_grupo) || empty($usuarios_grupo)) {
            throw new Exception('Debes seleccionar al menos un usuario para el proyecto grupal');
        }
    }

    //para proyectos individuales usar id_particiapnte
    $id_participante = 0;
    if ($id_tipo_proyecto == 2 && isset($_POST['id_participante'])) {
        $id_participante = intval($_POST['id_participante']);
    }

    if (strlen($nombre) > 100) {//validacion
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

    //insertar proyecto
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

    if (!$stmt->execute()) {
        throw new Exception('Error al crear el proyecto: ' . $stmt->error);
    }

    $id_proyecto = $stmt->insert_id;
    $stmt->close();

    //si un proyecto grupal insertar usuarios en la tabla usuarios_grupo
    if ($id_tipo_proyecto == 1 && !empty($usuarios_grupo)) {
        $sql_usuarios = "INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)";
        $stmt_usuarios = $conn->prepare($sql_usuarios);

        if (!$stmt_usuarios) {
            throw new Exception('Error al preparar la consulta de usuarios: ' . $conn->error);
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
    $response['message'] = $e->getMessage();
    error_log($e->getMessage());
}

echo json_encode($response);
?>