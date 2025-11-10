<?php
// get_users.php 

header('Content-Type: application/json');

require_once 'db_config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener parámetro de filtro de rol (opcional)
    $filter_rol = isset($_GET['id_rol']) ? intval($_GET['id_rol']) : null;
    
    // Construir query con filtro opcional
    if ($filter_rol !== null && $filter_rol > 0) {
        $query = "SELECT id_usuario, nombre, apellido, usuario, num_empleado, id_departamento, id_rol, id_superior, e_mail 
                  FROM tbl_usuarios 
                  WHERE id_rol = ?
                  ORDER BY apellido ASC, nombre ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $filter_rol);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // Sin filtro, obtener todos los usuarios
        $query = "SELECT id_usuario, nombre, apellido, usuario, num_empleado, id_departamento, id_rol, id_superior, e_mail 
                  FROM tbl_usuarios 
                  ORDER BY apellido ASC, nombre ASC";
        $result = $conn->query($query);
    }
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $usuarios = [];
    
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre' => $row['nombre'], 
            'apellido' => $row['apellido'],
            'usuario' => $row['usuario'],
            'num_empleado' => (int)$row['num_empleado'],
            'id_departamento' => (int)$row['id_departamento'],
            'id_rol' => (int)$row['id_rol'],
            'id_superior' => (int)$row['id_superior'],
            'e_mail' => $row['e_mail'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido']
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
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar usuarios: ' . $e->getMessage(),
        'usuarios' => []
    ]);
}

$conn->close();
?>