<?php
/**manager_get_departments.php para saber el departamento del usuario que ha iiciado sesion */
 
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
 
    $is_manager = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 2;
    $is_admin = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 1;
    $user_department = isset($_SESSION['id_departamento']) ? (int)$_SESSION['id_departamento'] : null;
 
    if ($is_manager && $user_department) {
        $query = "SELECT
                    td.id_departamento,
                    td.nombre,
                    td.descripcion,
                    td.id_creador,
                    CONCAT(tu.nombre, ' ', tu.apellido) as nombre_creador
                  FROM tbl_departamentos td
                  LEFT JOIN tbl_usuarios tu ON td.id_creador = tu.id_usuario
                  WHERE td.id_departamento = ?
                  ORDER BY td.nombre ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_department);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT
                    td.id_departamento,
                    td.nombre,
                    td.descripcion,
                    td.id_creador,
                    CONCAT(tu.nombre, ' ', tu.apellido) as nombre_creador
                  FROM tbl_departamentos td
                  LEFT JOIN tbl_usuarios tu ON td.id_creador = tu.id_usuario
                  ORDER BY td.nombre ASC";
        
        $result = $conn->query($query);
    }
 
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
 
    $departamentos = [];
    while ($row = $result->fetch_assoc()) {
        $departamentos[] = [
            'id_departamento' => (int)$row['id_departamento'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'id_creador' => (int)$row['id_creador'],
            'nombre_creador' => $row['nombre_creador'] ?? 'N/A'
        ];
    }
 
    echo json_encode([
        'success' => true,
        'departamentos' => $departamentos,
        'debug' => [
            'is_manager' => $is_manager,
            'filtered_by_department' => $is_manager && $user_department ? true : false,
            'id_departamento' => $user_department
        ]
    ]);
 
    $result->free();
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
 
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar departamentos: ' . $e->getMessage(),
        'departamentos' => []
    ]);
    error_log('get_departments.php Error: ' . $e->getMessage());
}
?>