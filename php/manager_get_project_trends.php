<?php
/*manager_get_project_trends.php para saber las tendecnais de completacion de datos, se usa por la grafica lineal*/

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
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) {
        throw new Exception('ID de departamento requerido');
    }
    
    $id_departamento = (int)$_GET['id_departamento'];
    $weeks = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 12;
    
    if ($id_departamento <= 0) {
        throw new Exception('ID de departamento inválido');
    }
    
    $weeks = max(4, min(52, $weeks));
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $dept_query = "SELECT nombre FROM tbl_departamentos WHERE id_departamento = ?";
    $dept_stmt = $conn->prepare($dept_query);
    $dept_stmt->bind_param('i', $id_departamento);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    $dept_row = $dept_result->fetch_assoc();
    $dept_name = $dept_row ? $dept_row['nombre'] : 'Departamento';
    $dept_stmt->close();
    
    $labels = [];
    $weeklyData = [];
    
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $weekStart = date('Y-m-d', strtotime("-{$i} weeks monday"));
        $weekEnd = date('Y-m-d', strtotime("-{$i} weeks sunday"));
        $weekLabel = date('d/m', strtotime($weekStart));
        
        $labels[] = $weekLabel;
        $weeklyData[$weekLabel] = 0;
    }
    
    //query para tener los proyectos completados por semana
    $query = "
        SELECT 
            DATE(fecha_inicio) as fecha,
            COUNT(*) as cantidad
        FROM tbl_proyectos
        WHERE id_departamento = ?
          AND estado = 'completado'
          AND fecha_inicio IS NOT NULL
          AND fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
        GROUP BY YEARWEEK(fecha_inicio, 1)
        ORDER BY fecha ASC
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        $query = "
            SELECT 
                DATE(fecha_actualizacion) as fecha,
                COUNT(*) as cantidad
            FROM tbl_proyectos
            WHERE id_departamento = ?
              AND estado = 'completado'
              AND fecha_actualizacion >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
            GROUP BY YEARWEEK(fecha_actualizacion, 1)
            ORDER BY fecha ASC
        ";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Error preparando consulta: ' . $conn->error);
        }
    }
    
    $stmt->bind_param('ii', $id_departamento, $weeks);
    
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $weekLabel = date('d/m', strtotime('monday this week', strtotime($row['fecha'])));
        if (isset($weeklyData[$weekLabel])) {
            $weeklyData[$weekLabel] += (int)$row['cantidad'];
        }
    }
    
    $cumulativeData = [];
    $cumulative = 0;
    foreach ($labels as $label) {
        $cumulative += $weeklyData[$label];
        $cumulativeData[] = $cumulative;
    }
    
    $response['success'] = true;
    $response['data'] = [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => $dept_name,
                'data' => $cumulativeData,
                'fill' => true,
                'tension' => 0.4
            ]
        ]
    ];
    $response['id_departamento'] = $id_departamento;
    $response['weeks'] = $weeks;
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_project_trends.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>