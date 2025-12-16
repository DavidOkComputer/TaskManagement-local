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
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_department'])) {
        throw new Exception('Usuario no autenticado');
    }

    $id_usuario = (int)$_SESSION['user_id'];
    $id_departamento = (int)$_SESSION['user_department'];

    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }

    // Query para obtener todos los proyectos del departamento con conteo de tareas
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
        WHERE p.id_departamento = ?
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
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_departamento);
    
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
            'creador' => $row['creador_nombre'] . ' ' . $row['creador_apellido'],
            'puede_editar' => true,
            'total_tareas' => (int)$row['total_tareas']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['total'] = count($proyectos);
    $response['id_departamento'] = $id_departamento;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar proyectos: ' . $e->getMessage();
    error_log('manager_get_projects.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>