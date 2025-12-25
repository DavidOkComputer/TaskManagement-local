<?php
/*manager_get_person_efficiency.php calculo de eficiencia de personas usado por el scatter chart*/

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

$response = [
    'success' => false,
    'data' => null,
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
        // Verificar permisos del usuario
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
            // Verificar si es admin
            $admin_check = $conn->prepare("
                SELECT 1 FROM tbl_usuario_roles 
                WHERE id_usuario = ? AND id_rol = 1 AND activo = 1
            ");
            $admin_check->bind_param('i', $id_usuario);
            $admin_check->execute();
            
            if ($admin_check->get_result()->num_rows === 0) {
                throw new Exception('No tiene permiso para ver este departamento');
            }
            $admin_check->close();
        }
        $perm_stmt->close();
    }
    
    $query = "
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            ur.id_rol,
            r.nombre as rol_nombre,
            COUNT(t.id_tarea) AS total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) AS completadas,
            SUM(CASE WHEN t.estado = 'en proceso' THEN 1 ELSE 0 END) AS en_proceso,
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) AS vencidas
        FROM tbl_usuarios u
        INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario
        LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol
        LEFT JOIN tbl_tareas t ON u.id_usuario = t.id_participante
        WHERE ur.id_departamento = ?
            AND ur.activo = 1
        GROUP BY u.id_usuario, u.nombre, u.apellido, ur.id_rol, r.nombre
        HAVING total_tareas > 0
        ORDER BY total_tareas DESC
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
    
    $datasets = [];
    $details = [];
    
    $colors = [
        ['bg' => 'rgba(34, 139, 89, 0.6)', 'border' => 'rgba(34, 139, 89, 1)'],
        ['bg' => 'rgba(80, 154, 108, 0.6)', 'border' => 'rgba(80, 154, 108, 1)'],
        ['bg' => 'rgba(24, 97, 62, 0.6)', 'border' => 'rgba(24, 97, 62, 1)'],
        ['bg' => 'rgba(130, 140, 150, 0.6)', 'border' => 'rgba(130, 140, 150, 1)'],
        ['bg' => 'rgba(45, 110, 80, 0.6)', 'border' => 'rgba(45, 110, 80, 1)'],
        ['bg' => 'rgba(160, 170, 180, 0.6)', 'border' => 'rgba(160, 170, 180, 1)'],
        ['bg' => 'rgba(50, 50, 50, 0.6)', 'border' => 'rgba(50, 50, 50, 1)'],
        ['bg' => 'rgba(200, 205, 210, 0.6)', 'border' => 'rgba(200, 205, 210, 1)']
    ];
    
    $colorIndex = 0;
    
    while ($row = $result->fetch_assoc()) {
        $totalTareas = (int)$row['total_tareas'];
        $completadas = (int)$row['completadas'];
        $nombreCompleto = $row['nombre'] . ' ' . $row['apellido'];
        
        // Calcular la eficiencia de la persona dependiendo de las tareas completadas
        $efficiency = $totalTareas > 0 ? round(($completadas / $totalTareas) * 100, 1) : 0;
        
        // El tamaño de la burbuja depende del total de tareas
        $bubbleSize = max(8, min(25, 8 + ($totalTareas * 2)));
        
        $color = $colors[$colorIndex % count($colors)];
        
        // Agregar información para la persona
        $datasets[] = [
            'label' => $nombreCompleto,
            'data' => [
                [
                    'x' => $totalTareas,
                    'y' => $efficiency,
                    'r' => $bubbleSize,
                    'label' => $nombreCompleto
                ]
            ],
            'backgroundColor' => $color['bg'],
            'borderColor' => $color['border'],
            'borderWidth' => 2
        ];
        
        // Guardar detalles para tooltips
        $details[] = [
            'nombre_completo' => $nombreCompleto,
            'total_tareas' => $totalTareas,
            'completadas' => $completadas,
            'en_proceso' => (int)$row['en_proceso'],
            'pendientes' => (int)$row['pendientes'],
            'vencidas' => (int)$row['vencidas'],
            'efficiency' => $efficiency,
            'id_rol' => (int)$row['id_rol'],
            'rol_nombre' => $row['rol_nombre']
        ];
        
        $colorIndex++;
    }
    
    if (empty($datasets)) {
        $response['success'] = false;
        $response['message'] = 'No hay personas con tareas asignadas en este departamento';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Calcular la eficiencia promedio
    $avgEfficiency = 0;
    if (!empty($details)) {
        $totalEfficiency = array_sum(array_column($details, 'efficiency'));
        $avgEfficiency = round($totalEfficiency / count($details), 1);
    }
    
    $response['success'] = true;
    $response['data'] = [
        'datasets' => $datasets,
        'details' => $details,
        'avg_completion' => $avgEfficiency
    ];
    $response['id_departamento'] = $id_departamento;
    $response['total_personas'] = count($details);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_person_efficiency.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>