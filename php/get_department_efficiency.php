<?php
/*get_department_efficiency.php para obtener matriz de eficiencia departamental*/ 

ob_start(); 

header('Content-Type: application/json; charset=utf-8'); 

error_reporting(E_ALL); 

ini_set('display_errors', 0); 

ini_set('log_errors', 1); 

 

require_once('db_config.php'); 

 

$response = [ 

    'success' => false, 

    'message' => '', 

    'data' => null 

]; 

 

try { 

    $conn = getDBConnection(); 

    if (!$conn) { 

        throw new Exception('Error de conexión a la base de datos'); 

    } 

     

    // Query para obtener eficiencia departamental 

    // X-axis: Total de tareas 

    // Y-axis: Porcentaje completado 

    $query = " 

        SELECT  

            d.id_departamento, 

            d.nombre as nombre_departamento, 

            COUNT(t.id_tarea) as total_tareas, 

            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas, 

            SUM(CASE WHEN t.estado = 'en proceso' OR t.estado = 'en-progreso' THEN 1 ELSE 0 END) as tareas_en_proceso, 

            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as tareas_pendientes, 

            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) as tareas_vencidas 

        FROM tbl_departamentos d 

        LEFT JOIN tbl_proyectos p ON d.id_departamento = p.id_departamento 

        LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto 

        GROUP BY d.id_departamento, d.nombre 

        HAVING total_tareas > 0 

        ORDER BY total_tareas DESC 

    "; 

     

    $stmt = $conn->prepare($query); 

    if (!$stmt) { 

        throw new Exception('Error preparando consulta: ' . $conn->error); 

    } 

     

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

            'id_departamento' => (int)$row['id_departamento'], 

            'nombre_departamento' => $row['nombre_departamento'], 

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

        throw new Exception('No hay datos de eficiencia departamental disponibles'); 

    } 

     

    // Procesar datos para scatter chart 

    $processed_data = processEfficiencyData($efficiency_data); 

     

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

    error_log('Error en get_department_efficiency.php: ' . $e->getMessage()); 

} 

 

ob_end_flush(); 

 

function processEfficiencyData($efficiency_data) { 

    $scatterPoints = []; 

    $colors = getOfficialDashboardColors(count($efficiency_data)); 

    $details = []; 

     

    foreach ($efficiency_data as $index => $dept) { 

        $scatterPoints[] = [ 

            'x' => $dept['total_tareas'], 

            'y' => $dept['tasa_completacion'], 

            'r' => max(8, min(15, $dept['total_tareas'] / 3)), 

            'label' => $dept['nombre_departamento'], 

            'backgroundColor' => $colors[$index]['background'], 

            'borderColor' => $colors[$index]['border'] 

        ]; 

         

        $details[] = [ 

            'id_departamento' => $dept['id_departamento'], 

            'nombre' => $dept['nombre_departamento'], 

            'total_tareas' => $dept['total_tareas'], 

            'completadas' => $dept['tareas_completadas'], 

            'en_proceso' => $dept['tareas_en_proceso'], 

            'pendientes' => $dept['tareas_pendientes'], 

            'vencidas' => $dept['tareas_vencidas'], 

            'tasa_completacion' => $dept['tasa_completacion'], 

            'color' => $colors[$index]['border'] 

        ]; 

    } 

     

    return [ 

        'datasets' => [[ 

            'label' => 'Eficiencia Departamental', 

            'data' => $scatterPoints, 

            'borderWidth' => 2, 

            'pointHoverRadius' => 9, 

            'hoverBorderWidth' => 3 

        ]], 

        'details' => $details, 

        'max_tasks' => max(array_map(fn($d) => $d['total_tareas'], $efficiency_data)), 

        'avg_completion' => round(array_sum(array_map(fn($d) => $d['tasa_completacion'], $efficiency_data)) / count($efficiency_data), 1) 

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

        // Variaciones adicionales con opacidad diferente para más departamentos 
        ['border' => 'rgba(34, 139, 89, 0.9)', 'background' => 'rgba(34, 139, 89, 0.6)'], 
        ['border' => 'rgba(80, 154, 108, 0.9)', 'background' => 'rgba(80, 154, 108, 0.6)'], 
        ['border' => 'rgba(24, 97, 62, 0.9)', 'background' => 'rgba(24, 97, 62, 0.6)'], 
        ['border' => 'rgba(45, 110, 80, 0.9)', 'background' => 'rgba(45, 110, 80, 0.6)'], 
    ]; 

    // Ciclar colores si hay más departamentos 
    $result = []; 
    for ($i = 0; $i < $count; $i++) { 
        $result[] = $colors[$i % count($colors)]; 
    } 
    return $result; 
} 
?>