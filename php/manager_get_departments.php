<?php
/*manager_get_departments.php saber el departamento del usuario basado en el id pasado por la sesion*/
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
    // Verificar que el usuario esté autenticado
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
 
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    $user_id = (int)$_SESSION['user_id'];
    $is_manager = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 2;
    $is_user = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 3;
    $is_admin = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 1;
 
    // Si es gerente o usuario normal, obtener su departamento desde la tabla de usuarios
    if ($is_manager || $is_user) {
        // Primero obtener el id_departamento del usuario
        $user_query = "SELECT id_departamento FROM tbl_usuarios WHERE id_usuario = ?";
        $user_stmt = $conn->prepare($user_query);
        
        if (!$user_stmt) {
            throw new Exception("Error al preparar consulta de usuario: " . $conn->error);
        }
        
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        
        if (!$user_data || !$user_data['id_departamento']) {
            throw new Exception("No se encontró el departamento del usuario");
        }
        
        $user_department = (int)$user_data['id_departamento'];
        $user_stmt->close();
 
        //saber la información completa del departamento
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
        
    } else if ($is_admin) {
        // Si es admin, mostrar todos los departamentos
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
    } else {
        throw new Exception("Usuario no autorizado para esta operación");
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
 
    // Verificar que se encontró al menos un departamento
    if (empty($departamentos)) {
        throw new Exception("No se encontraron departamentos para este usuario");
    }
 
    echo json_encode([
        'success' => true,
        'departamentos' => $departamentos,
        'debug' => [
            'user_id' => $user_id,
            'is_admin' => $is_admin,
            'is_manager' => $is_manager,
            'is_user' => $is_user,
            'filtered_by_department' => ($is_manager || $is_user),
            'id_departamento' => isset($user_department) ? $user_department : null
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
    error_log('manager_get_departments.php Error: ' . $e->getMessage());
}
?>