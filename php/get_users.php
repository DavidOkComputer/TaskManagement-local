<?php
/*get_users.php - obtener lista de usuarios para asignacion de tareas*/

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

    //obtener parametro de filtro de rol (opcional)
    $filter_rol = isset($_GET['id_rol']) ? intval($_GET['id_rol']) : null;
    
    //construir query con filtro opcional
    if ($filter_rol !== null && $filter_rol > 0) {
        $query = "SELECT id_usuario, nombre, apellido, num_empleado, id_rol 
                  FROM tbl_usuarios 
                  WHERE id_rol = ? 
                  ORDER BY apellido ASC, nombre ASC";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("i", $filter_rol);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        //sin filtro, obtener todos los usuarios activos
        $query = "SELECT id_usuario, nombre, apellido, num_empleado 
                  FROM tbl_usuarios 
                  ORDER BY apellido ASC, nombre ASC";
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
            'num_empleado' => (int)$row['num_empleado'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'nombre_empleado' => $row['nombre'] . ' ' . $row['apellido'] . ' (#' . $row['num_empleado'] . ')'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios
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