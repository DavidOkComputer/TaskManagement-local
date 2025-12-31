<?php
/*user_get_my_departments.php para saber todos los departamentos a los que pertenece el usuario 
*/

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

    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario']);

    // Obtener todos los departamentos del usuario desde la tabla junction
    $query = "
        SELECT 
            ur.id_usuario_roles,
            ur.id_departamento,
            ur.id_rol,
            ur.es_principal,
            ur.activo,
            d.nombre as nombre_departamento,
            d.descripcion as descripcion_departamento,
            r.nombre as nombre_rol,
            r.descripcion as descripcion_rol
        FROM tbl_usuario_roles ur
        INNER JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
        INNER JOIN tbl_roles r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC, d.nombre ASC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $departamentos = [];
    $is_admin = false;
    $is_manager = false;
    $departamento_principal = null;

    while ($row = $result->fetch_assoc()) {
        $dept = [
            'id_departamento' => (int)$row['id_departamento'],
            'nombre' => $row['nombre_departamento'],
            'descripcion' => $row['descripcion_departamento'],
            'id_rol' => (int)$row['id_rol'],
            'nombre_rol' => $row['nombre_rol'],
            'es_principal' => (int)$row['es_principal'],
            'puede_gestionar' => ($row['id_rol'] == 1 || $row['id_rol'] == 2) // Admin o Gerente
        ];
        
        $departamentos[] = $dept;
        
        // Identificar roles
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        } elseif ($row['id_rol'] == 2) {
            $is_manager = true;
        }
        
        // Guardar departamento principal
        if ($row['es_principal'] == 1) {
            $departamento_principal = $dept;
        }
    }
    $stmt->close();

    // Si no hay registros en junction table, hacer fallback a tbl_usuarios
    if (empty($departamentos)) {
        $legacy_query = "
            SELECT 
                u.id_departamento,
                u.id_rol,
                d.nombre as nombre_departamento,
                d.descripcion as descripcion_departamento,
                r.nombre as nombre_rol
            FROM tbl_usuarios u
            LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
            LEFT JOIN tbl_roles r ON u.id_rol = r.id_rol
            WHERE u.id_usuario = ?
        ";
        
        $legacy_stmt = $conn->prepare($legacy_query);
        $legacy_stmt->bind_param('i', $user_id);
        $legacy_stmt->execute();
        $legacy_result = $legacy_stmt->get_result();
        
        if ($row = $legacy_result->fetch_assoc()) {
            if ($row['id_departamento']) {
                $dept = [
                    'id_departamento' => (int)$row['id_departamento'],
                    'nombre' => $row['nombre_departamento'] ?? 'Sin nombre',
                    'descripcion' => $row['descripcion_departamento'] ?? '',
                    'id_rol' => (int)$row['id_rol'],
                    'nombre_rol' => $row['nombre_rol'] ?? 'Usuario',
                    'es_principal' => 1,
                    'puede_gestionar' => ($row['id_rol'] == 1 || $row['id_rol'] == 2)
                ];
                
                $departamentos[] = $dept;
                $departamento_principal = $dept;
                
                if ($row['id_rol'] == 1) $is_admin = true;
                if ($row['id_rol'] == 2) $is_manager = true;
            }
        }
        $legacy_stmt->close();
    }

    // Si es admin, puede ver todos los departamentos
    $todos_departamentos = [];
    if ($is_admin) {
        $all_query = "
            SELECT 
                d.id_departamento,
                d.nombre,
                d.descripcion
            FROM tbl_departamentos d
            ORDER BY d.nombre ASC
        ";
        $all_result = $conn->query($all_query);
        
        while ($row = $all_result->fetch_assoc()) {
            $todos_departamentos[] = [
                'id_departamento' => (int)$row['id_departamento'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion']
            ];
        }
    }

    $tiene_multiples = count($departamentos) > 1;

    echo json_encode([
        'success' => true,
        'departamentos' => $departamentos,
        'total' => count($departamentos),
        'tiene_multiples_departamentos' => $tiene_multiples,
        'departamento_principal' => $departamento_principal,
        'todos_departamentos' => $is_admin ? $todos_departamentos : [],
        'permisos' => [
            'is_admin' => $is_admin,
            'is_manager' => $is_manager,
            'puede_elegir_departamento' => $tiene_multiples || $is_admin
        ],
        'debug' => [
            'user_id' => $user_id
        ]
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'departamentos' => []
    ]);
    error_log('user_get_my_departments.php Error: ' . $e->getMessage());
}
?>