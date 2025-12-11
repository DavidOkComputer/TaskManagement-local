<?php
/*get_users.php saber la lista de usuarios para asignacion de tareas*/

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

    // Obtener filtros
    $filter_rol = isset($_GET['id_rol']) ? intval($_GET['id_rol']) : null;
    $filter_departamento = isset($_GET['id_departamento']) ? intval($_GET['id_departamento']) : null;
    
    // Construir query según los filtros proporcionados
    if ($filter_rol !== null && $filter_rol > 0 && $filter_departamento !== null && $filter_departamento > 0) {
        // Filtrar por rol Y departamento (para superiores del mismo departamento)
        $query = "SELECT u.id_usuario, 
                         u.nombre, 
                         u.apellido, 
                         u.usuario,
                         u.num_empleado, 
                         u.acceso,
                         u.id_departamento, 
                         u.id_rol, 
                         u.id_superior, 
                         u.e_mail,
                         d.nombre as area
                  FROM tbl_usuarios u
                  LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
                  WHERE u.id_rol = ? AND u.id_departamento = ?
                  ORDER BY u.apellido ASC, u.nombre ASC";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("ii", $filter_rol, $filter_departamento);
        $stmt->execute();
        $result = $stmt->get_result();
        
    } elseif ($filter_rol !== null && $filter_rol > 0) {
        // Filtrar solo por rol
        $query = "SELECT u.id_usuario, 
                         u.nombre, 
                         u.apellido, 
                         u.usuario,
                         u.num_empleado, 
                         u.acceso,
                         u.id_departamento, 
                         u.id_rol, 
                         u.id_superior, 
                         u.e_mail,
                         d.nombre as area
                  FROM tbl_usuarios u
                  LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
                  WHERE u.id_rol = ? 
                  ORDER BY u.apellido ASC, u.nombre ASC";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("i", $filter_rol);
        $stmt->execute();
        $result = $stmt->get_result();
        
    } elseif ($filter_departamento !== null && $filter_departamento > 0) {
        // Filtrar solo por departamento
        $query = "SELECT u.id_usuario, 
                         u.nombre, 
                         u.apellido, 
                         u.usuario,
                         u.num_empleado, 
                         u.acceso,
                         u.id_departamento, 
                         u.id_rol, 
                         u.id_superior, 
                         u.e_mail,
                         d.nombre as area
                  FROM tbl_usuarios u
                  LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
                  WHERE u.id_departamento = ?
                  ORDER BY u.apellido ASC, u.nombre ASC";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("i", $filter_departamento);
        $stmt->execute();
        $result = $stmt->get_result();
        
    } else {
        // Sin filtros  obtener todos los usuarios
        $query = "SELECT u.id_usuario, 
                         u.nombre, 
                         u.apellido, 
                         u.usuario,
                         u.num_empleado, 
                         u.acceso, 
                         u.id_departamento, 
                         u.id_rol, 
                         u.id_superior, 
                         u.e_mail,
                         d.nombre as area
                  FROM tbl_usuarios u
                  LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
                  ORDER BY u.apellido ASC, u.nombre ASC";
        $result = $conn->query($query);
    }
    
    if (!$result) {
        throw new Exception('Error en la consulta: ' . $conn->error);
    }
    
    $usuarios = [];
    
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'usuario' => $row['usuario'] ?? '',
            'num_empleado' => (int)$row['num_empleado'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'nombre_empleado' => $row['nombre'] . ' ' . $row['apellido'] . ' (#' . $row['num_empleado'] . ')',
            'acceso' => $row['acceso'] ?? '',
            'id_departamento' => (int)($row['id_departamento'] ?? 0),
            'id_superior' => (int)($row['id_superior'] ?? 0),
            'id_rol' => (int)($row['id_rol'] ?? 0),
            'e_mail' => $row['e_mail'] ?? '',
            'area' => $row['area'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'filters_applied' => [
            'id_rol' => $filter_rol,
            'id_departamento' => $filter_departamento
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
        'message' => 'Error al cargar usuarios: ' . $e->getMessage(),
        'usuarios' => []
    ]);
    error_log('get_users.php Error: ' . $e->getMessage());
}
?>