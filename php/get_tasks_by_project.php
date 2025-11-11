<?php
// get_tasks_by_project.php para tener las tareas de un proyecto especifico
header('Content-Type: application/json');
require_once 'db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'tasks' => []];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['message'] = 'Método de solicitud inválido';
    echo json_encode($response);
    exit;
}

try {
    //validar el id del proyecto
    if (!isset($_GET['id_proyecto']) || empty($_GET['id_proyecto'])) {
        throw new Exception('El ID del proyecto es requerido');
    }

    $id_proyecto = intval($_GET['id_proyecto']);

    if ($id_proyecto <= 0) {
        throw new Exception('El ID del proyecto no es válido');
    }

    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // query para tener los proyectos especificos
    $query = "SELECT 
                t.id_tarea,
                t.nombre,
                t.descripcion,
                t.fecha_cumplimiento,
                t.estado,
                t.fecha_creacion,
                u.nombre as creador,
                p.nombre as proyecto
            FROM tbl_tareas t
            LEFT JOIN tbl_usuarios u ON t.id_creador = u.id_usuario
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
            WHERE t.id_proyecto = ?
            ORDER BY t.fecha_cumplimiento ASC";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];

    while ($row = $result->fetch_assoc()) {
        $tasks[] = [
            'id_tarea' => $row['id_tarea'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'fecha_creacion' => $row['fecha_creacion'],
            'creador' => $row['creador'],
            'proyecto' => $row['proyecto']
        ];
    }

    $response['success'] = true;
    $response['tasks'] = $tasks;
    $result->free();
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = 'Error al cargar tareas: ' . $e->getMessage();
    error_log('get_tasks_by_project.php Error: ' . $e->getMessage());
}

$conn->close();
echo json_encode($response);
?>