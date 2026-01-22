<?php
/*manager_get_projects.php Obtiene todos los proyectos del departamento del gerente con conteo de tareas*/

header('Content-Type: application/json');
session_start();
require_once 'db_config.php';
 
$response = [
    'success' => false,
    'message' => '',
    'proyectos' => [],
    'total' => 0
];
 
try {
    // Verificar autenticación
    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }
 
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
 
    $role_query = "
        SELECT ur.id_rol, ur.id_departamento, ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ? AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param('i', $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
 
    $is_admin = false;
    $departamentos_gerente = [];
    $departamento_principal = null;
 
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        if ($row['id_rol'] == 2) {
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
        if ($row['es_principal'] == 1 || $departamento_principal === null) {
            $departamento_principal = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
 
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('Usuario no tiene rol de gerente en ningún departamento');
    }
 
    if ($is_admin) {
        // Admin ve todos los proyectos
        $query = "
            SELECT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_cumplimiento,
                p.progreso,
                p.estado,
                p.id_tipo_proyecto,
                p.id_creador,
                p.id_departamento,
                d.nombre as area,
                u.nombre as participante_nombre,
                u.apellido as participante_apellido,
                p.id_participante,
                creator.nombre as creador_nombre,
                creator.apellido as creador_apellido,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as total_tareas
            FROM tbl_proyectos p
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
            LEFT JOIN tbl_usuarios creator ON p.id_creador = creator.id_usuario
            ORDER BY
                CASE
                    WHEN p.estado = 'vencido' THEN 1
                    WHEN p.estado = 'en proceso' THEN 2
                    WHEN p.estado = 'pendiente' THEN 3
                    ELSE 4
                END,
                p.fecha_cumplimiento ASC
        ";
        $stmt = $conn->prepare($query);
    } else {
        // Gerente ve proyectos de:
        // 1. Sus departamentos
        // 2. Proyectos que él creó
        // 3. Proyectos donde él es participante
        // 4. Proyectos donde él está en el grupo
        // 5. Proyectos de sus subordinados (usuarios donde id_superior = id del gerente)
        
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        
        $query = "
            SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_cumplimiento,
                p.progreso,
                p.estado,
                p.id_tipo_proyecto,
                p.id_creador,
                p.id_departamento,
                d.nombre as area,
                u.nombre as participante_nombre,
                u.apellido as participante_apellido,
                p.id_participante,
                creator.nombre as creador_nombre,
                creator.apellido as creador_apellido,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as total_tareas
            FROM tbl_proyectos p
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
            LEFT JOIN tbl_usuarios creator ON p.id_creador = creator.id_usuario
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
            ORDER BY
                CASE
                    WHEN p.estado = 'vencido' THEN 1
                    WHEN p.estado = 'en proceso' THEN 2
                    WHEN p.estado = 'pendiente' THEN 3
                    ELSE 4
                END,
                p.fecha_cumplimiento ASC
        ";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            // Tipos: departamentos (i por cada uno) + id_usuario x6
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
    $proyectos = [];
 
    while ($row = $result->fetch_assoc()) {
        // Determinar el texto del participante según el tipo de proyecto
        if ((int)$row['id_tipo_proyecto'] === 1) {
            $participante_text = 'Grupo';
        } elseif ($row['participante_nombre']) {
            $participante_text = $row['participante_nombre'] . ' ' . $row['participante_apellido'];
        } else {
            $participante_text = 'Sin asignar';
        }
 
        // Determinar si es de un departamento gestionado
        $is_managed_dept = in_array((int)$row['id_departamento'], $departamentos_gerente);
        
        // Verificar si el proyecto es de un subordinado
        $is_subordinate_project = false;
        if (!$is_managed_dept && $row['id_creador'] != $id_usuario) {
            // Verificar si el creador o participante es subordinado
            $sub_check = $conn->prepare("
                SELECT 1 FROM tbl_usuarios
                WHERE id_usuario IN (?, ?) AND id_superior = ?
                LIMIT 1
            ");
            $sub_check->bind_param('iii', $row['id_creador'], $row['id_participante'], $id_usuario);
            $sub_check->execute();
            $is_subordinate_project = $sub_check->get_result()->num_rows > 0;
            $sub_check->close();
        }

        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado'],
            'participante' => $participante_text,
            'id_participante' => (int)$row['id_participante'],
            'id_tipo_proyecto' => (int)$row['id_tipo_proyecto'],
            'id_creador' => (int)$row['id_creador'],
            'id_departamento' => (int)$row['id_departamento'],
            'creador' => ($row['creador_nombre'] ?? '') . ' ' . ($row['creador_apellido'] ?? ''),
            'puede_editar' => $is_managed_dept || $row['id_creador'] == $id_usuario,
            'total_tareas' => (int)$row['total_tareas'],
            'is_managed_department' => $is_managed_dept,
            'is_subordinate_project' => $is_subordinate_project
        ];
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['total'] = count($proyectos);
    $response['id_departamento'] = $departamento_principal;
    $response['managed_departments'] = $departamentos_gerente;
    $response['managed_departments_count'] = count($departamentos_gerente);
    $response['is_admin'] = $is_admin;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar proyectos: ' . $e->getMessage();
    error_log('manager_get_projects.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);