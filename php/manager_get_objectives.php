<?php
// get_objectives.php - Adapted for department filtering

session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Check user role and department
    $is_manager = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 2;
    $is_admin = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 1;
    $user_department = isset($_SESSION['id_departamento']) ? (int)$_SESSION['id_departamento'] : null;

    // Build query based on role
    if ($is_admin) {
        // Admins see all objectives
        $query = "SELECT 
                    o.id_objetivo,
                    o.nombre,
                    o.descripcion,
                    o.fecha_cumplimiento,
                    o.estado,
                    o.archivo_adjunto,
                    d.nombre as area
                  FROM tbl_objetivos o
                  LEFT JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento
                  ORDER BY o.fecha_cumplimiento ASC";
        
        $result = $conn->query($query);
        
    } elseif ($is_manager && $user_department) {
        // Managers see only their department's objectives
        $query = "SELECT 
                    o.id_objetivo,
                    o.nombre,
                    o.descripcion,
                    o.fecha_cumplimiento,
                    o.estado,
                    o.archivo_adjunto,
                    d.nombre as area
                  FROM tbl_objetivos o
                  INNER JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento
                  WHERE o.id_departamento = ?
                  ORDER BY o.fecha_cumplimiento ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_department);
        $stmt->execute();
        $result = $stmt->get_result();
        
    } else {
        // Regular users see objectives from their department
        if (!$user_department) {
            echo json_encode([
                'success' => false,
                'message' => 'Usuario sin departamento asignado',
                'objetivos' => []
            ]);
            exit;
        }
        
        $query = "SELECT 
                    o.id_objetivo,
                    o.nombre,
                    o.descripcion,
                    o.fecha_cumplimiento,
                    o.estado,
                    o.archivo_adjunto,
                    d.nombre as area
                  FROM tbl_objetivos o
                  INNER JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento
                  WHERE o.id_departamento = ?
                  ORDER BY o.fecha_cumplimiento ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_department);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $objetivos = [];
    
    while ($row = $result->fetch_assoc()) {
        $objetivos[] = [
            'id_objetivo' => (int)$row['id_objetivo'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'archivo_adjunto' => $row['archivo_adjunto'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'objetivos' => $objetivos,
        'total' => count($objetivos),
        'debug' => [
            'is_admin' => $is_admin,
            'is_manager' => $is_manager,
            'filtered_by_department' => ($is_manager || !$is_admin) && $user_department ? true : false,
            'id_departamento' => $user_department
        ]
    ]);
    
    $result->free();
    if (isset($stmt)) {
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar objetivos: ' . $e->getMessage(),
        'objetivos' => []
    ]);
    error_log('get_objectives.php Error: ' . $e->getMessage());
}

$conn->close();
?>