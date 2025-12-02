<?php
/**
 * manager_get_project_trends.php
 * Gets project completion trends over time for manager's department
 * Used by line chart
 */

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
    // Validate department ID
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) {
        throw new Exception('ID de departamento requerido');
    }
    
    $id_departamento = (int)$_GET['id_departamento'];
    $weeks = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 12;
    
    if ($id_departamento <= 0) {
        throw new Exception('ID de departamento inválido');
    }
    
    // Limit weeks range
    $weeks = max(4, min(52, $weeks));
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Get department name
    $dept_query = "SELECT nombre FROM tbl_departamentos WHERE id_departamento = ?";
    $dept_stmt = $conn->prepare($dept_query);
    $dept_stmt->bind_param('i', $id_departamento);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    $dept_row = $dept_result->fetch_assoc();
    $dept_name = $dept_row ? $dept_row['nombre'] : 'Departamento';
    $dept_stmt->close();
    
    // Generate weekly labels
    $labels = [];
    $weeklyData = [];
    
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $weekStart = date('Y-m-d', strtotime("-{$i} weeks monday"));
        $weekEnd = date('Y-m-d', strtotime("-{$i} weeks sunday"));
        $weekLabel = date('d/m', strtotime($weekStart));
        
        $labels[] = $weekLabel;
        $weeklyData[$weekLabel] = 0;
    }
    
    // Query to get completed projects by week
    $query = "
        SELECT 
            DATE(fecha_completado) as fecha,
            COUNT(*) as cantidad
        FROM tbl_proyectos
        WHERE id_departamento = ?
          AND estado = 'completado'
          AND fecha_completado IS NOT NULL
          AND fecha_completado >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
        GROUP BY YEARWEEK(fecha_completado, 1)
        ORDER BY fecha ASC
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        // If fecha_completado doesn't exist, try with fecha_actualizacion
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
    
    // Map results to weekly labels
    while ($row = $result->fetch_assoc()) {
        $weekLabel = date('d/m', strtotime('monday this week', strtotime($row['fecha'])));
        if (isset($weeklyData[$weekLabel])) {
            $weeklyData[$weekLabel] += (int)$row['cantidad'];
        }
    }
    
    // Calculate cumulative totals
    $cumulativeData = [];
    $cumulative = 0;
    foreach ($labels as $label) {
        $cumulative += $weeklyData[$label];
        $cumulativeData[] = $cumulative;
    }
    
    // Prepare chart data
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