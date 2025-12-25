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
 
        $query = "
            SELECT DISTINCT
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.e_mail,
                u.num_empleado,
                ur.id_departamento,
                ur.id_rol,
                d.nombre as nombre_departamento,
                r.nombre as nombre_rol,
                ur.es_principal,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_completo
            FROM tbl_usuarios u
            INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario
            LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
            LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol
            WHERE ur.id_departamento = ?
                AND ur.activo = 1
            ORDER BY u.apellido ASC, u.nombre ASC
        ";
        
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
        $query = "
            SELECT DISTINCT
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.e_mail,
                u.num_empleado,
                ur.id_departamento,
                ur.id_rol,
                d.nombre as nombre_departamento,
                r.nombre as nombre_rol,
                ur.es_principal,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_completo
            FROM tbl_usuarios u
            LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario
                AND ur.es_principal = 1
                AND ur.activo = 1
            LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
            LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol
            ORDER BY u.apellido ASC, u.nombre ASC
        ";
        
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
            'id_departamento' => $row['id_departamento'] ? (int)$row['id_departamento'] : null,
            'nombre_departamento' => $row['nombre_departamento'] ?? 'Sin departamento',
            'id_rol' => $row['id_rol'] ? (int)$row['id_rol'] : null,
            'nombre_rol' => $row['nombre_rol'] ?? 'Sin rol',
            'es_principal' => isset($row['es_principal']) ? (bool)$row['es_principal'] : null
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