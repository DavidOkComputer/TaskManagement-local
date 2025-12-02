<?php
/**
 * manager_get_project_status.php
 * Gets project status distribution for manager's department
 * Used by doughnut chart
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
    
    if ($id_departamento <= 0) {
        throw new Exception('ID de departamento inválido');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Count projects by status for the department
    $query = "
        SELECT 
            estado,
            COUNT(*) as cantidad
        FROM tbl_proyectos
        WHERE id_departamento = ?
        GROUP BY estado
        ORDER BY 
            CASE estado
                WHEN 'completado' THEN 1
                WHEN 'en proceso' THEN 2
                WHEN 'pendiente' THEN 3
                WHEN 'vencido' THEN 4
                ELSE 5
            END
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
    
    // Initialize status counts
    $statusCounts = [
        'completado' => 0,
        'en proceso' => 0,
        'pendiente' => 0,
        'vencido' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $estado = strtolower($row['estado']);
        if (isset($statusCounts[$estado])) {
            $statusCounts[$estado] = (int)$row['cantidad'];
        }
    }
    
    // Prepare chart data
    $response['success'] = true;
    $response['data'] = [
        'labels' => ['Completados', 'En Proceso', 'Pendientes', 'Vencidos'],
        'data' => [
            $statusCounts['completado'],
            $statusCounts['en proceso'],
            $statusCounts['pendiente'],
            $statusCounts['vencido']
        ]
    ];
    $response['total'] = array_sum($statusCounts);
    $response['id_departamento'] = $id_departamento;
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_project_status.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>