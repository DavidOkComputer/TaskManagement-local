<?php
// get_managers.php para obtener lista de gerentes para asignar como superiores

header('Content-Type: application/json');
require_once('db_config.php');

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

    // Parámetro opcional para filtrar por departamento
    $filter_departamento = isset($_GET['id_departamento']) ? intval($_GET['id_departamento']) : null;
    
    // Parámetro opcional para excluir un usuario específico (el usuario que se está editando)
    $exclude_user = isset($_GET['exclude_user']) ? intval($_GET['exclude_user']) : null;

    // Obtener usuarios que tengan rol de gerente (id_rol = 2) en cualquier departamento
    // Usando la tabla tbl_usuario_roles para el sistema multi-rol
    $query = "SELECT DISTINCT
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.num_empleado,
                u.e_mail,
                ur.id_departamento,
                d.nombre as nombre_departamento
              FROM tbl_usuarios u
              INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario 
                AND ur.activo = 1 
                AND ur.id_rol = 2
              LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Filtrar por departamento si se especifica
    if ($filter_departamento !== null && $filter_departamento > 0) {
        $query .= " AND ur.id_departamento = ?";
        $params[] = $filter_departamento;
        $types .= "i";
    }
    
    // Excluir usuario específico si se especifica
    if ($exclude_user !== null && $exclude_user > 0) {
        $query .= " AND u.id_usuario != ?";
        $params[] = $exclude_user;
        $types .= "i";
    }
    
    $query .= " ORDER BY u.apellido ASC, u.nombre ASC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    if (!$result) {
        throw new Exception('Error en la consulta: ' . $conn->error);
    }

    $managers = [];
    while ($row = $result->fetch_assoc()) {
        $managers[] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'num_empleado' => (int)$row['num_empleado'],
            'e_mail' => $row['e_mail'],
            'id_departamento' => (int)$row['id_departamento'],
            'nombre_departamento' => $row['nombre_departamento'] ?? 'Sin departamento'
        ];
    }

    echo json_encode([
        'success' => true,
        'managers' => $managers,
        'total' => count($managers)
    ]);

    $result->free();
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar gerentes: ' . $e->getMessage(),
        'managers' => []
    ]);
    error_log('get_managers.php Error: ' . $e->getMessage());
}
?>