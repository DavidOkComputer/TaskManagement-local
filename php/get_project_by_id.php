<?php
/*get_project_by_id.php saber detalles de un proyecto incluyendo usuarios para proyectos grupales*/

header('Content-Type: application/json');
require_once('db_config.php');

error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'proyecto' => null];

try {
    // Validar el ID del proyecto 
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('El ID del proyecto es requerido');
    }

    $id_proyecto = intval($_GET['id']);

    if ($id_proyecto <= 0) {
        throw new Exception('El ID del proyecto no es válido');
    }

    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $query = "SELECT 
                id_proyecto,
                nombre,
                descripcion,
                fecha_inicio,
                fecha_cumplimiento,
                progreso,
                estado,
                id_creador,
                puede_editar_otros,
                id_tipo_proyecto
            FROM tbl_proyectos
            WHERE id_proyecto = ?";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);

    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('El proyecto no existe');
    }

    $proyecto = $result->fetch_assoc();

    // Asegurar que puede_editar_otros es un entero (0 o 1)
    if (isset($proyecto['puede_editar_otros'])) {
        $proyecto['puede_editar_otros'] = intval($proyecto['puede_editar_otros']);
    } else {
        $proyecto['puede_editar_otros'] = 0; // default: solo creador puede editar
    }

    // Si es un proyecto grupal (id_tipo_proyecto == 1), obtener los usuarios asignados
    if (isset($proyecto['id_tipo_proyecto']) && intval($proyecto['id_tipo_proyecto']) == 1) {
        $sql_usuarios = "SELECT pu.id_usuario, u.nombre, u.apellido, u.e_mail, u.num_empleado
                        FROM tbl_proyecto_usuarios pu
                        JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
                        WHERE pu.id_proyecto = ?
                        ORDER BY u.apellido ASC, u.nombre ASC";

        $stmt_usuarios = $conn->prepare($sql_usuarios);

        if (!$stmt_usuarios) {
            throw new Exception('Error al preparar consulta de usuarios: ' . $conn->error);
        }

        $stmt_usuarios->bind_param("i", $id_proyecto);

        if (!$stmt_usuarios->execute()) {
            throw new Exception('Error al obtener usuarios: ' . $stmt_usuarios->error);
        }

        $result_usuarios = $stmt_usuarios->get_result();
        $usuarios_asignados = [];

        while ($row = $result_usuarios->fetch_assoc()) {
            $usuarios_asignados[] = [
                'id_usuario' => (int)$row['id_usuario'],
                'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
                'e_mail' => $row['e_mail'],
                'num_empleado' => (int)$row['num_empleado']
            ];
        }

        $proyecto['usuarios_asignados'] = $usuarios_asignados;
        $result_usuarios->free();
        $stmt_usuarios->close();
    }

    // Construir respuesta con type casting para campos numéricos
    $response['success'] = true;
    $response['proyecto'] = [
        'id_proyecto' => (int)$proyecto['id_proyecto'],
        'nombre' => $proyecto['nombre'],
        'descripcion' => $proyecto['descripcion'],
        'fecha_inicio' => $proyecto['fecha_inicio'],
        'fecha_cumplimiento' => $proyecto['fecha_cumplimiento'],
        'progreso' => (int)$proyecto['progreso'],
        'estado' => $proyecto['estado'],
        'id_creador' => (int)$proyecto['id_creador'],
        'puede_editar_otros' => (int)$proyecto['puede_editar_otros'],
        'id_tipo_proyecto' => (int)$proyecto['id_tipo_proyecto'],
        'usuarios_asignados' => isset($proyecto['usuarios_asignados']) ? $proyecto['usuarios_asignados'] : null
    ];

    $result->free();
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = 'Error al cargar el proyecto: ' . $e->getMessage();
    error_log('get_project_by_id.php Error: ' . $e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response);
?>