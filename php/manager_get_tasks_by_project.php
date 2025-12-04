<?php
/*manager_get_tasks_by_project.php - obtener tareas de un proyecto validando que pertenezca al departamento del gerente*/

session_start();
header('Content-Type: application/json');
require_once('db_config.php');

error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'tasks' => []];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['message'] = 'Método de solicitud inválido';
    echo json_encode($response);
    exit;
}

try {
    // Validar sesión del usuario
    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }

    // Validar el id del proyecto
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

    // Obtener el departamento del usuario logueado
    $id_departamento_usuario = null;
    
    if (isset($_SESSION['id_departamento']) && $_SESSION['id_departamento'] > 0) {
        $id_departamento_usuario = (int)$_SESSION['id_departamento'];
    } else {
        $user_query = "SELECT id_departamento FROM tbl_usuarios WHERE id_usuario = ?";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param("i", $id_usuario);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_row = $user_result->fetch_assoc()) {
            $id_departamento_usuario = (int)$user_row['id_departamento'];
            $_SESSION['id_departamento'] = $id_departamento_usuario;
        }
        $user_stmt->close();
    }
    
    if (!$id_departamento_usuario) {
        throw new Exception('No se pudo determinar el departamento del usuario');
    }

    // Verificar que el proyecto pertenece al departamento del usuario
    $check_query = "SELECT id_departamento FROM tbl_proyectos WHERE id_proyecto = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $id_proyecto);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('El proyecto no existe');
    }
    
    $proyecto_row = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ((int)$proyecto_row['id_departamento'] !== $id_departamento_usuario) {
        throw new Exception('No tiene permiso para acceder a las tareas de este proyecto');
    }

    // Query para obtener las tareas del proyecto con info del participante
    $query = "SELECT 
                t.id_tarea,
                t.nombre,
                t.descripcion,
                t.id_proyecto,
                t.fecha_cumplimiento,
                t.estado,
                t.fecha_creacion,
                t.id_participante,
                u_creador.nombre as creador,
                u_participante.nombre as participante_nombre,
                u_participante.apellido as participante_apellido,
                u_participante.num_empleado as participante_num_empleado,
                p.nombre as proyecto
            FROM tbl_tareas t
            LEFT JOIN tbl_usuarios u_creador ON t.id_creador = u_creador.id_usuario
            LEFT JOIN tbl_usuarios u_participante ON t.id_participante = u_participante.id_usuario
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
            WHERE t.id_proyecto = ?
            ORDER BY 
                CASE t.estado
                    WHEN 'pendiente' THEN 1
                    WHEN 'en proceso' THEN 2
                    WHEN 'vencido' THEN 3
                    WHEN 'completado' THEN 4
                    ELSE 5
                END,
                t.fecha_cumplimiento ASC";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];

    while ($row = $result->fetch_assoc()) {
        // Construir nombre completo del participante con numero de empleado
        $participante_display = null;
        if ($row['participante_nombre']) {
            $participante_display = $row['participante_nombre'] . ' ' . $row['participante_apellido'] . ' (#' . $row['participante_num_empleado'] . ')';
        }
        
        $tasks[] = [
            'id_tarea' => (int)$row['id_tarea'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'id_proyecto' => (int)$row['id_proyecto'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'fecha_creacion' => $row['fecha_creacion'],
            'id_participante' => $row['id_participante'] ? (int)$row['id_participante'] : null,
            'creador' => $row['creador'],
            'participante' => $participante_display,
            'proyecto' => $row['proyecto']
        ];
    }

    $response['success'] = true;
    $response['tasks'] = $tasks;
    $response['total'] = count($tasks);
    $result->free();
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = 'Error al cargar tareas: ' . $e->getMessage();
    error_log('manager_get_tasks_by_project.php Error: ' . $e->getMessage());
}

if (isset($conn) && $conn) {
    $conn->close();
}

echo json_encode($response);
?>