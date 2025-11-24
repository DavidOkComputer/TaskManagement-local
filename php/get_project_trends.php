<?php
/*get_project_trends.php para obtener datos de tendencia de proyectos completados por semana*/

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
    'mode' => 'single', // 'single' o 'comparison' 
    'departamento' => null
];

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Parámetros
    $id_departamento = isset($_GET['id_departamento']) ? intval($_GET['id_departamento']) : null;
    $weeks = isset($_GET['weeks']) ? intval($_GET['weeks']) : 12;
    
    // Validar número de semanas
    if ($weeks < 1 || $weeks > 52) {
        $weeks = 12;
    }

    // Determinar modo: solo un departamento o comparacion
    $mode = $id_departamento ? 'single' : 'comparison';
    $response['mode'] = $mode;

    if ($mode === 'single') {
        
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

        // Obtener proyectos completados por semana para este departamento
        $query = "
            SELECT 
                YEARWEEK(fecha_cumplimiento) as week_key,
                DATE_SUB(fecha_cumplimiento, INTERVAL DAYOFWEEK(fecha_cumplimiento) - 1 DAY) as week_start,
                DATE_ADD(DATE_SUB(fecha_cumplimiento, INTERVAL DAYOFWEEK(fecha_cumplimiento) - 1 DAY), INTERVAL 6 DAY) as week_end,
                COUNT(*) as proyectos_completados
            FROM tbl_proyectos
            WHERE estado = 'completado' 
                AND id_departamento = ?
                AND fecha_cumplimiento >= DATE_SUB(NOW(), INTERVAL ? WEEK)
            GROUP BY YEARWEEK(fecha_cumplimiento)
            ORDER BY week_key ASC
        ";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error preparando consulta: ' . $conn->error);
        }

        $stmt->bind_param("ii", $id_departamento, $weeks);
        if (!$stmt->execute()) {
            throw new Exception('Error ejecutando consulta: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'week_key' => $row['week_key'],
                'week_start' => $row['week_start'],
                'week_end' => $row['week_end'],
                'proyectos_completados' => (int)$row['proyectos_completados']
            ];
        }

        $stmt->close();

        // Procesar datos para crear labels y valores acumulativos
        $processed_data = processSingleDepartmentData($data, $weeks);
        $response['data'] = $processed_data;

    } else {
        
        // Obtener lista de departamentos que tienen proyectos
        $dept_list_query = "
            SELECT DISTINCT 
                td.id_departamento,
                td.nombre
            FROM tbl_departamentos td
            INNER JOIN tbl_proyectos tp ON td.id_departamento = tp.id_departamento
            WHERE tp.estado = 'completado'
                AND tp.fecha_cumplimiento >= DATE_SUB(NOW(), INTERVAL ? WEEK)
            ORDER BY td.nombre ASC
        ";

        $dept_list_stmt = $conn->prepare($dept_list_query);
        if (!$dept_list_stmt) {
            throw new Exception('Error preparando consulta de departamentos: ' . $conn->error);
        }

        $dept_list_stmt->bind_param("i", $weeks);
        $dept_list_stmt->execute();
        $dept_list_result = $dept_list_stmt->get_result();

        $departamentos = [];
        while ($dept = $dept_list_result->fetch_assoc()) {
            $departamentos[] = [
                'id' => (int)$dept['id_departamento'],
                'nombre' => $dept['nombre']
            ];
        }

        $dept_list_stmt->close();

        if (empty($departamentos)) {
            throw new Exception('No hay departamentos con proyectos completados');
        }

        // Obtener todas las semanas del rango
        $weeks_query = "
            SELECT DISTINCT 
                YEARWEEK(fecha_cumplimiento) as week_key,
                DATE_SUB(fecha_cumplimiento, INTERVAL DAYOFWEEK(fecha_cumplimiento) - 1 DAY) as week_start
            FROM tbl_proyectos
            WHERE estado = 'completado'
                AND fecha_cumplimiento >= DATE_SUB(NOW(), INTERVAL ? WEEK)
            ORDER BY week_key ASC
        ";

        $weeks_stmt = $conn->prepare($weeks_query);
        if (!$weeks_stmt) {
            throw new Exception('Error preparando consulta de semanas: ' . $conn->error);
        }

        $weeks_stmt->bind_param("i", $weeks);
        $weeks_stmt->execute();
        $weeks_result = $weeks_stmt->get_result();

        $week_keys = [];
        while ($week = $weeks_result->fetch_assoc()) {
            $week_keys[] = [
                'key' => $week['week_key'],
                'start' => $week['week_start']
            ];
        }

        $weeks_stmt->close();

        // Obtener datos para cada departamento
        $datasets = [];
        $colors = getComparisonColors(count($departamentos));

        foreach ($departamentos as $index => $dept) {
            $query = "
                SELECT 
                    YEARWEEK(fecha_cumplimiento) as week_key,
                    COUNT(*) as proyectos_completados
                FROM tbl_proyectos
                WHERE estado = 'completado' 
                    AND id_departamento = ?
                    AND fecha_cumplimiento >= DATE_SUB(NOW(), INTERVAL ? WEEK)
                GROUP BY YEARWEEK(fecha_cumplimiento)
                ORDER BY week_key ASC
            ";

            $dept_stmt = $conn->prepare($query);
            if (!$dept_stmt) {
                throw new Exception('Error preparando consulta de departamento: ' . $conn->error);
            }

            $dept_id = $dept['id'];
            $dept_stmt->bind_param("ii", $dept_id, $weeks);
            $dept_stmt->execute();
            $dept_result = $dept_stmt->get_result();

            // Crear mapa de datos para este departamento
            $dept_data_map = [];
            while ($row = $dept_result->fetch_assoc()) {
                $dept_data_map[$row['week_key']] = (int)$row['proyectos_completados'];
            }

            // Crear array de valores para todas las semanas (rellena con 0 si no hay datos)
            $dataset_values = [];
            $cumulative = 0;
            
            foreach ($week_keys as $week_info) {
                $week_key = $week_info['key'];
                $value = isset($dept_data_map[$week_key]) ? $dept_data_map[$week_key] : 0;
                $cumulative += $value;
                $dataset_values[] = $cumulative;
            }

            $datasets[] = [
                'label' => $dept['nombre'],
                'data' => $dataset_values,
                'borderColor' => $colors[$index]['border'],
                'backgroundColor' => $colors[$index]['background'],
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.4,
                'pointRadius' => 4,
                'pointBackgroundColor' => $colors[$index]['border'],
                'pointBorderColor' => '#fff',
                'pointBorderWidth' => 2
            ];

            $dept_stmt->close();
        }

        // Crear labels de semanas
        $labels = [];
        foreach ($week_keys as $week_info) {
            $start_date = new DateTime($week_info['start']);
            $end_date = clone $start_date;
            $end_date->add(new DateInterval('P6D'));
            
            $labels[] = $start_date->format('M d') . ' - ' . $end_date->format('d');
        }

        $response['data'] = [
            'labels' => $labels,
            'datasets' => $datasets,
            'departamentos' => $departamentos
        ];
    }

    if (ob_get_length()) ob_clean();
    $response['success'] = true;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    $conn->close();

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_project_trends.php: ' . $e->getMessage());
}

ob_end_flush();

function processSingleDepartmentData($data, $weeks) {
    // Crear mapa de semanas completo (últimas $weeks semanas)
    $all_weeks = [];
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $date = new DateTime();
        $date->sub(new DateInterval('P' . $i . 'W'));
        $date->modify('Monday this week');
        
        $week_key = $date->format('YW');
        $all_weeks[$week_key] = [
            'start' => $date->format('Y-m-d'),
            'completed' => 0
        ];
    }

    // Llenar datos disponibles
    foreach ($data as $row) {
        if (isset($all_weeks[$row['week_key']])) {
            $all_weeks[$row['week_key']]['completed'] = $row['proyectos_completados'];
        }
    }

    // Crear labels y valores acumulativos
    $labels = [];
    $values = [];
    $cumulative = 0;

    foreach ($all_weeks as $week_key => $week_data) {
        $start_date = new DateTime($week_data['start']);
        $end_date = clone $start_date;
        $end_date->add(new DateInterval('P6D'));
        
        $labels[] = $start_date->format('M d') . ' - ' . $end_date->format('d');
        $cumulative += $week_data['completed'];
        $values[] = $cumulative;
    }

    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Proyectos Completados (Acumulativo)',
            'data' => $values,
            'borderColor' => 'rgba(34, 139, 89, 1)',
            'backgroundColor' => 'rgba(34, 139, 89, 0.1)',
            'borderWidth' => 2,
            'fill' => true,
            'tension' => 0.4,
            'pointRadius' => 5,
            'pointBackgroundColor' => 'rgba(34, 139, 89, 1)',
            'pointBorderColor' => '#fff',
            'pointBorderWidth' => 2
        ]]
    ];
}

function getComparisonColors($count) {
    $colors = [
        // Verde primario
        ['border' => 'rgba(34, 139, 89, 1)', 'background' => 'rgba(34, 139, 89, 0.1)'],
        // Verde claro
        ['border' => 'rgba(80, 154, 108, 1)', 'background' => 'rgba(80, 154, 108, 0.1)'],
        // Verde oscuro
        ['border' => 'rgba(24, 97, 62, 1)', 'background' => 'rgba(24, 97, 62, 0.1)'],
        // Verde secundario
        ['border' => 'rgba(45, 110, 80, 1)', 'background' => 'rgba(45, 110, 80, 0.1)'],
        // Gris
        ['border' => 'rgba(130, 140, 150, 1)', 'background' => 'rgba(130, 140, 150, 0.1)'],
        // Gris claro
        ['border' => 'rgba(160, 170, 180, 1)', 'background' => 'rgba(160, 170, 180, 0.1)'],
        // Ice/Gris claro
        ['border' => 'rgba(200, 205, 210, 1)', 'background' => 'rgba(200, 205, 210, 0.1)'],
        // Negro
        ['border' => 'rgba(50, 50, 50, 1)', 'background' => 'rgba(50, 50, 50, 0.1)'],
    ];

    // Ciclar si hay más departamentos que colores
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $result[] = $colors[$i % count($colors)];
    }

    return $result;
}
?>