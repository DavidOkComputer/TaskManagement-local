<?php 

/*get_person_efficiency_by_department.php eficiencia de personas en un departamento específico */ 
ob_start(); 
header('Content-Type: application/json; charset=utf-8'); 
error_reporting(E_ALL); 
ini_set('display_errors', 0); 
ini_set('log_errors', 1); 
require_once('db_config.php'); 

$response = [ 
    'success' => false, 
    'message' => '', 
    'data' => null, 
    'departamento' => null 
]; 

try { 
    // Validar parámetro requerido 
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) { 
        throw new Exception('ID de departamento requerido'); 
    } 
    $id_departamento = intval($_GET['id_departamento']); 

    if ($id_departamento <= 0) { 
        throw new Exception('ID de departamento inválido');
    } 

    $conn = getDBConnection(); 

    if (!$conn) { 

        throw new Exception('Error de conexión a la base de datos'); 

    } 

    // Obtener nombre del departamento 
    $dept_query = "SELECT nombre FROM tbl_departamentos WHERE id_departamento = ?"; 
    $dept_stmt = $conn->prepare($dept_query); 

    if (!$dept_stmt) { 
        throw new Exception('Error preparando consulta de departamento: ' . $conn->error); 
    } 
     
    $dept_stmt->bind_param("i", $id_departamento); 
    $dept_stmt->execute(); 
    $dept_result = $dept_stmt->get_result(); 

    if ($dept_result->num_rows === 0) { 
        throw new Exception('Departamento no encontrado'); 
    } 

    $dept_row = $dept_result->fetch_assoc(); 
    $dept_name = $dept_row['nombre']; 

    $response['departamento'] = [ 
        'id' => $id_departamento, 
        'nombre' => $dept_name 
    ];

    $dept_stmt->close(); 

    // Query para obte eficiencia de personas en este departamento 
    // X-axis es el total de tareas asignadas 
    // Y-axis es el porcentaje completado 
    $query = " 
        SELECT  
            u.id_usuario, 
            u.nombre, 
            u.apellido, 
            u.num_empleado, 
            COUNT(t.id_tarea) as total_tareas, 
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas, 
            SUM(CASE WHEN t.estado = 'en proceso' OR t.estado = 'en-progreso' THEN 1 ELSE 0 END) as tareas_en_proceso, 
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as tareas_pendientes, 
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) as tareas_vencidas 
        FROM tbl_usuarios u 
        INNER JOIN tbl_tareas t ON u.id_usuario = t.id_participante 
        INNER JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
        WHERE p.id_departamento = ?
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.num_empleado 
        HAVING total_tareas > 0 
        ORDER BY total_tareas DESC 
    "; 

    $stmt = $conn->prepare($query); 
    if (!$stmt) { 
        throw new Exception('Error preparando consulta: ' . $conn->error); 
    } 

    $stmt->bind_param("i", $id_departamento); 
    if (!$stmt->execute()) { 
        throw new Exception('Error ejecutando consulta: ' . $stmt->error); 
    } 

    $result = $stmt->get_result(); 
    $efficiency_data = []; 

    while ($row = $result->fetch_assoc()) { 
        $total_tareas = (int)$row['total_tareas']; 
        $tareas_completadas = (int)($row['tareas_completadas'] ?? 0); 

        // Calcular porcentaje de completación 
        $completion_rate = $total_tareas > 0 ?  
            round(($tareas_completadas / $total_tareas) * 100, 1) : 0; 
        $efficiency_data[] = [ 
            'id_usuario' => (int)$row['id_usuario'], 
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'], 
            'nombre' => $row['nombre'], 
            'apellido' => $row['apellido'], 
            'num_empleado' => $row['num_empleado'], 
            'total_tareas' => $total_tareas, 
            'tareas_completadas' => $tareas_completadas, 
            'tareas_en_proceso' => (int)($row['tareas_en_proceso'] ?? 0), 
            'tareas_pendientes' => (int)($row['tareas_pendientes'] ?? 0), 
            'tareas_vencidas' => (int)($row['tareas_vencidas'] ?? 0), 
            'tasa_completacion' => $completion_rate 
        ]; 
    } 
    $stmt->close(); 

    if (empty($efficiency_data)) { 
        throw new Exception('No hay personas con tareas en este departamento'); 
    } 

    // Procesar datos para scatter chart 
    $processed_data = processPersonEfficiencyData($efficiency_data); 
    if (ob_get_length()) ob_clean(); 
    $response['success'] = true; 
    $response['data'] = $processed_data; 
    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    $conn->close(); 

} catch (Exception $e) { 
    if (ob_get_length()) ob_clean(); 

    $response['success'] = false; 
    $response['message'] = $e->getMessage(); 

    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    error_log('Error en get_person_efficiency_by_department.php: ' . $e->getMessage()); 
} 

ob_end_flush(); 

function processPersonEfficiencyData($efficiency_data) { 
    $scatterPoints = []; 
    $colors = getOfficialDashboardColors(count($efficiency_data)); 
    $details = []; 

    foreach ($efficiency_data as $index => $person) { 
        $scatterPoints[] = [ 
            'x' => $person['total_tareas'], 
            'y' => $person['tasa_completacion'], 
            'r' => max(7, min(13, $person['total_tareas'] / 2)), 
            'label' => $person['nombre_completo'], 
            'backgroundColor' => $colors[$index]['background'], 
            'borderColor' => $colors[$index]['border'] 
        ]; 

        $details[] = [ 
            'id_usuario' => $person['id_usuario'], 
            'nombre_completo' => $person['nombre_completo'], 
            'num_empleado' => $person['num_empleado'], 
            'total_tareas' => $person['total_tareas'], 
            'completadas' => $person['tareas_completadas'], 
            'en_proceso' => $person['tareas_en_proceso'], 
            'pendientes' => $person['tareas_pendientes'], 
            'vencidas' => $person['tareas_vencidas'], 
            'tasa_completacion' => $person['tasa_completacion'], 
            'color' => $colors[$index]['border']
        ]; 
    } 

    return [ 
        'datasets' => [[ 
            'label' => 'Eficiencia de Personas', 
            'data' => $scatterPoints, 
            'borderWidth' => 2, 
            'pointHoverRadius' => 9, 
            'hoverBorderWidth' => 3 
        ]],

        'details' => $details, 
        'max_tasks' => max(array_map(fn($d) => $d['total_tareas'], $efficiency_data)), 
        'avg_completion' => round(array_sum(array_map(fn($d) => $d['tasa_completacion'], $efficiency_data)) / count($efficiency_data), 1), 
        'persona_count' => count($efficiency_data) 
    ]; 
} 

function getOfficialDashboardColors($count) { 
    // Colores oficiales del dashboard (de dashboard_charts_core.js) 
    $colors = [ 
        // Verde primario (Green Primary) 
        ['border' => 'rgba(34, 139, 89, 1)', 'background' => 'rgba(34, 139, 89, 0.7)'], 

        // Verde claro (Green Light) 
        ['border' => 'rgba(80, 154, 108, 1)', 'background' => 'rgba(80, 154, 108, 0.7)'], 

        // Verde oscuro (Green Dark) 
        ['border' => 'rgba(24, 97, 62, 1)', 'background' => 'rgba(24, 97, 62, 0.7)'], 

        // Ice/Gris claro (Ice/Light Gray) 
        ['border' => 'rgba(200, 205, 210, 1)', 'background' => 'rgba(200, 205, 210, 0.7)'], 

        // Gris (Gray) 
        ['border' => 'rgba(130, 140, 150, 1)', 'background' => 'rgba(130, 140, 150, 0.7)'], 

        // Negro (Black) 
        ['border' => 'rgba(50, 50, 50, 1)', 'background' => 'rgba(50, 50, 50, 0.7)'], 

        // Verde secundario (Green Secondary) 
        ['border' => 'rgba(45, 110, 80, 1)', 'background' => 'rgba(45, 110, 80, 0.7)'], 

        // Gris claro (Gray Light) 
        ['border' => 'rgba(160, 170, 180, 1)', 'background' => 'rgba(160, 170, 180, 0.7)'], 

        // Variaciones adicionales con opacidad diferente para más personas 
        ['border' => 'rgba(34, 139, 89, 0.9)', 'background' => 'rgba(34, 139, 89, 0.6)'], 
        ['border' => 'rgba(80, 154, 108, 0.9)', 'background' => 'rgba(80, 154, 108, 0.6)'], 
        ['border' => 'rgba(24, 97, 62, 0.9)', 'background' => 'rgba(24, 97, 62, 0.6)'], 
        ['border' => 'rgba(45, 110, 80, 0.9)', 'background' => 'rgba(45, 110, 80, 0.6)'], 
        ['border' => 'rgba(200, 205, 210, 0.9)', 'background' => 'rgba(200, 205, 210, 0.6)'], 
        ['border' => 'rgba(130, 140, 150, 0.9)', 'background' => 'rgba(130, 140, 150, 0.6)'], 
        ['border' => 'rgba(50, 50, 50, 0.9)', 'background' => 'rgba(50, 50, 50, 0.6)'], 
    ]; 

    // Ciclar colores si hay más personas 
    $result = []; 
    for ($i = 0; $i < $count; $i++) { 
        $result[] = $colors[$i % count($colors)]; 
    } 
    return $result; 
} 
?> 