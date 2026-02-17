<?php
/*manager_get_objectives_dashboard.php Obtiene proyectos formateados como objetivos para el dashboard del gerente*/

header('Content-Type: application/json; charset=utf-8');
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

$response = [
    'success' => false,
    'objetivos' => [],
    'message' => ''
];

try {
    require_once('db_config.php');
    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    //Obtener id_usuario de la sesión
    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;

    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }

    $id_usuario = (int)$id_usuario;

    //obtener roles y departamentos
    $role_query = "
        SELECT ur.id_rol, ur.id_departamento, ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    $role_stmt = $conn->prepare($role_query);
    if (!$role_stmt) {
        throw new Exception('Error preparando consulta de roles: ' . $conn->error);
    }
    $role_stmt->bind_param('i', $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();

    $is_admin = false;
    $departamentos_gerente = [];

    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        if ($row['id_rol'] == 2 && !empty($row['id_departamento'])) {
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();

    //también revisar tbl_usuarios por si el rol de gerente está ahí
    $user_role_query = "
        SELECT u.id_departamento
        FROM tbl_usuarios u
        WHERE u.id_usuario = ?
            AND u.id_rol = 2
            AND u.id_departamento IS NOT NULL
    ";
    $user_role_stmt = $conn->prepare($user_role_query);
    if ($user_role_stmt) {
        $user_role_stmt->bind_param('i', $id_usuario);
        $user_role_stmt->execute();
        $user_role_result = $user_role_stmt->get_result();
        if ($row = $user_role_result->fetch_assoc()) {
            $depto_id = (int)$row['id_departamento'];
            if ($depto_id > 0 && !in_array($depto_id, $departamentos_gerente)) {
                $departamentos_gerente[] = $depto_id;
            }
        }
        $user_role_stmt->close();
    }

    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('Usuario no tiene rol de gerente en ningún departamento');
    }

    //consultar proyectos
    if ($is_admin) {
        // Admin ve todos los proyectos
        $query = "
            SELECT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.progreso,
                p.estado,
                p.id_tipo_proyecto,
                p.fecha_cumplimiento,
                CONCAT(u.nombre, ' ', u.apellido) AS participante,
                tp.nombre AS tipo_nombre
            FROM tbl_proyectos p
            LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
            LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto
            ORDER BY p.progreso DESC, p.nombre ASC
        ";
        $stmt = $conn->prepare($query);

    } else {
        //incluye proyectos de subordinados via id_superior
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));

        $query = "
            SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.progreso,
                p.estado,
                p.id_tipo_proyecto,
                p.fecha_cumplimiento,
                CONCAT(u.nombre, ' ', u.apellido) AS participante,
                tp.nombre AS tipo_nombre
            FROM tbl_proyectos p
            LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
            LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            LEFT JOIN tbl_usuarios subordinate_creator ON p.id_creador = subordinate_creator.id_usuario
            LEFT JOIN tbl_usuarios subordinate_participant ON p.id_participante = subordinate_participant.id_usuario
            LEFT JOIN tbl_proyecto_usuarios pu_sub ON p.id_proyecto = pu_sub.id_proyecto
            LEFT JOIN tbl_usuarios subordinate_group ON pu_sub.id_usuario = subordinate_group.id_usuario
            WHERE (
                p.id_departamento IN ($placeholders)
                OR p.id_creador = ?
                OR p.id_participante = ?
                OR pu.id_usuario = ?
                OR subordinate_creator.id_superior = ?
                OR subordinate_participant.id_superior = ?
                OR subordinate_group.id_superior = ?
            )
            ORDER BY p.progreso DESC, p.nombre ASC
        ";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $types = str_repeat('i', count($departamentos_gerente)) . 'iiiiii';
            $params = array_merge(
                $departamentos_gerente,
                [$id_usuario, $id_usuario, $id_usuario, $id_usuario, $id_usuario, $id_usuario]
            );
            $stmt->bind_param($types, ...$params);
        }
    }

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $objetivos = [];

    while ($row = $result->fetch_assoc()) {
        $tipo = 'Global';
        if ((int)$row['id_tipo_proyecto'] === 2) {
            $tipo = 'Regional';
        }

        // Determinar participante según tipo de proyecto
        if ((int)$row['id_tipo_proyecto'] === 1) {
            $participante_text = 'Grupo';
        } elseif ($row['participante']) {
            $participante_text = $row['participante'];
        } else {
            $participante_text = 'Sin asignar';
        }

        $objetivos[] = [
            'id_proyecto'        => (int)$row['id_proyecto'],
            'nombre'             => $row['nombre'],
            'descripcion'        => $row['descripcion'],
            'progreso'           => (int)$row['progreso'],
            'estado'             => $row['estado'],
            'tipo'               => $tipo,
            'tipo_nombre'        => $row['tipo_nombre'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'participante'       => $participante_text
        ];
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['objetivos'] = $objetivos;
    $response['total'] = count($objetivos);
    $response['managed_departments'] = $departamentos_gerente;
    $response['is_admin'] = $is_admin;
    $response['user_id'] = $id_usuario;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_objectives_dashboard.php Error: ' . $e->getMessage());
}

ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();
?>