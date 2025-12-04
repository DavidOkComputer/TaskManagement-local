<?php
/*get_users_by_department.php Obtener usuarios filtrados por departamento*/
header('Content-Type: application/json; charset=UTF-8');
require_once 'db_config.php';
 
$response = ['success' => false, 'usuarios' => []];
 
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método de solicitud inválido');
    }
 
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    // Si se proporciona un departamento, filtrar por él
    if (isset($_GET['id_departamento']) && !empty($_GET['id_departamento'])) {
        $id_departamento = intval($_GET['id_departamento']);
        
        if ($id_departamento <= 0) {
            throw new Exception('ID de departamento inválido');
        }
 
        $query = "SELECT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.e_mail,
                    u.num_empleado,
                    u.id_departamento,
                    d.nombre as nombre_departamento,
                    CONCAT(u.nombre, ' ', u.apellido) as nombre_completo
                FROM tbl_usuarios u
                LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                WHERE u.id_departamento = ?
                ORDER BY u.apellido ASC, u.nombre ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $id_departamento);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
    } else {
        // Si no se proporciona departamento, devolver todos los usuarios
        $query = "SELECT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.e_mail,
                    u.num_empleado,
                    u.id_departamento,
                    d.nombre as nombre_departamento,
                    CONCAT(u.nombre, ' ', u.apellido) as nombre_completo
                FROM tbl_usuarios u
                LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                ORDER BY u.apellido ASC, u.nombre ASC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Error en la consulta: ' . $conn->error);
        }
    }
 
    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'nombre_completo' => $row['nombre_completo'],
            'e_mail' => $row['e_mail'],
            'num_empleado' => (int)$row['num_empleado'],
            'id_departamento' => (int)$row['id_departamento'],
            'nombre_departamento' => $row['nombre_departamento'] ?? 'Sin departamento'
        ];
    }
 
    $response['success'] = true;
    $response['usuarios'] = $usuarios;
    $response['count'] = count($usuarios);
    
    if (isset($stmt)) {
        $stmt->close();
    }
    $result->free();
    $conn->close();
 
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Error in get_users_by_department.php: ' . $e->getMessage());
}
 
echo json_encode($response);
exit;
?>