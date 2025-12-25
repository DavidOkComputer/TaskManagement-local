<?php
/*manager_get_departments.php para saber los departamentos que el usuario puede gestionar*/

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
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['id_usuario'])) {
        throw new Exception('Usuario no autenticado');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario']);

    // Obtener todos los roles del usuario desde la tabla de junction
    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento,
            ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC, ur.id_rol ASC
    ";

    $role_stmt = $conn->prepare($role_query);
    if (!$role_stmt) {
        throw new Exception("Error al preparar consulta de roles: " . $conn->error);
    }
    
    $role_stmt->bind_param('i', $user_id);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();

    $is_admin = false;
    $is_manager = false;
    $is_user = false;
    $departamentos_usuario = [];      // Todos los departamentos del usuario
    $departamentos_gerente = [];      // Departamentos donde es gerente

    while ($row = $role_result->fetch_assoc()) {
        $dept_id = (int)$row['id_departamento'];
        
        // Agregar a la lista general si no está ya
        if (!in_array($dept_id, $departamentos_usuario)) {
            $departamentos_usuario[] = $dept_id;
        }
        
        // Identificar roles
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        } elseif ($row['id_rol'] == 2) {
            $is_manager = true;
            if (!in_array($dept_id, $departamentos_gerente)) {
                $departamentos_gerente[] = $dept_id;
            }
        } elseif ($row['id_rol'] == 3) {
            $is_user = true;
        }
    }
    $role_stmt->close();

    // Si no tiene roles en la junction table, intentar fallback a la tabla de usuarios legacy
    if (empty($departamentos_usuario) && !$is_admin) {
        $legacy_query = "SELECT id_departamento, id_rol FROM tbl_usuarios WHERE id_usuario = ?";
        $legacy_stmt = $conn->prepare($legacy_query);
        $legacy_stmt->bind_param('i', $user_id);
        $legacy_stmt->execute();
        $legacy_result = $legacy_stmt->get_result();
        
        if ($legacy_row = $legacy_result->fetch_assoc()) {
            if ($legacy_row['id_departamento']) {
                $departamentos_usuario[] = (int)$legacy_row['id_departamento'];
                
                if ($legacy_row['id_rol'] == 1) {
                    $is_admin = true;
                } elseif ($legacy_row['id_rol'] == 2) {
                    $is_manager = true;
                    $departamentos_gerente[] = (int)$legacy_row['id_departamento'];
                } elseif ($legacy_row['id_rol'] == 3) {
                    $is_user = true;
                }
            }
        }
        $legacy_stmt->close();
    }

    if (empty($departamentos_usuario) && !$is_admin) {
        throw new Exception("No se encontraron departamentos para este usuario");
    }

    $departamentos = [];

    if ($is_admin) {
        // Admin ve todos los departamentos
        $query = "
            SELECT
                td.id_departamento,
                td.nombre,
                td.descripcion,
                td.id_creador,
                CONCAT(tu.nombre, ' ', tu.apellido) as nombre_creador
            FROM tbl_departamentos td
            LEFT JOIN tbl_usuarios tu ON td.id_creador = tu.id_usuario
            ORDER BY td.nombre ASC
        ";
        $result = $conn->query($query);

        if (!$result) {
            throw new Exception("Error en la consulta: " . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $departamentos[] = [
                'id_departamento' => (int)$row['id_departamento'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'id_creador' => (int)$row['id_creador'],
                'nombre_creador' => $row['nombre_creador'] ?? 'N/A',
                'is_managed' => true // Admin puede gestionar todos
            ];
        }

    } elseif ($is_manager && !empty($departamentos_gerente)) {
        // Gerente ve los departamentos que gestiona
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        $query = "
            SELECT
                td.id_departamento,
                td.nombre,
                td.descripcion,
                td.id_creador,
                CONCAT(tu.nombre, ' ', tu.apellido) as nombre_creador
            FROM tbl_departamentos td
            LEFT JOIN tbl_usuarios tu ON td.id_creador = tu.id_usuario
            WHERE td.id_departamento IN ($placeholders)
            ORDER BY td.nombre ASC
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }
        
        $types = str_repeat('i', count($departamentos_gerente));
        $stmt->bind_param($types, ...$departamentos_gerente);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $departamentos[] = [
                'id_departamento' => (int)$row['id_departamento'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'id_creador' => (int)$row['id_creador'],
                'nombre_creador' => $row['nombre_creador'] ?? 'N/A',
                'is_managed' => true
            ];
        }
        $stmt->close();

    } else {
        // Usuario normal ve solo sus departamentos (sin capacidad de gestión)
        $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
        $query = "
            SELECT
                td.id_departamento,
                td.nombre,
                td.descripcion,
                td.id_creador,
                CONCAT(tu.nombre, ' ', tu.apellido) as nombre_creador
            FROM tbl_departamentos td
            LEFT JOIN tbl_usuarios tu ON td.id_creador = tu.id_usuario
            WHERE td.id_departamento IN ($placeholders)
            ORDER BY td.nombre ASC
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }
        
        $types = str_repeat('i', count($departamentos_usuario));
        $stmt->bind_param($types, ...$departamentos_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $dept_id = (int)$row['id_departamento'];
            $departamentos[] = [
                'id_departamento' => $dept_id,
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'id_creador' => (int)$row['id_creador'],
                'nombre_creador' => $row['nombre_creador'] ?? 'N/A',
                'is_managed' => in_array($dept_id, $departamentos_gerente)
            ];
        }
        $stmt->close();
    }

    if (empty($departamentos)) {
        throw new Exception("No se encontraron departamentos para este usuario");
    }

    echo json_encode([
        'success' => true,
        'departamentos' => $departamentos,
        'total' => count($departamentos),
        'debug' => [
            'user_id' => $user_id,
            'is_admin' => $is_admin,
            'is_manager' => $is_manager,
            'is_user' => $is_user,
            'managed_departments' => $departamentos_gerente,
            'all_departments' => $departamentos_usuario
        ]
    ]);

    if (isset($result) && $result instanceof mysqli_result) {
        $result->free();
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