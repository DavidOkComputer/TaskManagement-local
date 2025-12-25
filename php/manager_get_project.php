<?php
/*manager_get_project.php usado por la grafica de barras, obtiene los proyectos del departamento*/

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

$response = [
    'success' => false,
    'proyectos' => [],
    'message' => ''
];

try {
    // Validar id del departamento
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) {
        throw new Exception('ID de departamento requerido');
    }
    
    $id_departamento = (int)$_GET['id_departamento'];
    
    if ($id_departamento <= 0) {
        throw new Exception('ID de departamento inválido');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    
    if ($id_usuario) {
        $perm_query = "
            SELECT ur.id_rol 
            FROM tbl_usuario_roles ur 
            WHERE ur.id_usuario = ? 
                AND ur.activo = 1
                AND (ur.id_rol = 1 OR (ur.id_rol = 2 AND ur.id_departamento = ?))
            LIMIT 1
        ";
        $perm_stmt = $conn->prepare($perm_query);
        $perm_stmt->bind_param('ii', $id_usuario, $id_departamento);
        $perm_stmt->execute();
        $perm_result = $perm_stmt->get_result();
        
        if ($perm_result->num_rows === 0) {
            // No es admin ni gerente de este departamento
            // Verificar si al menos tiene algún rol activo
            $any_role = $conn->prepare("
                SELECT 1 FROM tbl_usuario_roles 
                WHERE id_usuario = ? AND activo = 1
                LIMIT 1
            ");
            $any_role->bind_param('i', $id_usuario);
            $any_role->execute();
            
            if ($any_role->get_result()->num_rows > 0) {
                throw new Exception('No tiene permiso para ver proyectos de este departamento');
            }
            $any_role->close();
        }
        $perm_stmt->close();
    }
    
    // Obtener todos los proyectos del departamento
    $query = "
        SELECT 
            p.id_proyecto,
            p.nombre,
            p.descripcion,
            p.fecha_cumplimiento,
            p.progreso,
            p.estado,
            p.id_tipo_proyecto,
            p.id_participante,
            u.nombre AS participante_nombre,
            u.apellido AS participante_apellido
        FROM tbl_proyectos p
        LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
        WHERE p.id_departamento = ?
        ORDER BY 
            CASE p.estado
                WHEN 'vencido' THEN 1
                WHEN 'en proceso' THEN 2
                WHEN 'pendiente' THEN 3
                WHEN 'completado' THEN 4
                ELSE 5
            END,
            p.progreso DESC
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $id_departamento);
    
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $proyectos = [];
    
    while ($row = $result->fetch_assoc()) {
        // Determinar texto del participante
        if ((int)$row['id_tipo_proyecto'] === 1) {
            $participante = 'Grupo';
        } elseif ($row['participante_nombre']) {
            $participante = $row['participante_nombre'] . ' ' . $row['participante_apellido'];
        } else {
            $participante = 'Sin asignar';
        }
        
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado'],
            'participante' => $participante,
            'id_tipo_proyecto' => (int)$row['id_tipo_proyecto']
        ];
    }
    
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['total'] = count($proyectos);
    $response['id_departamento'] = $id_departamento;
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_project.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>