<?php
/*manager_get_objectives.php obtiene objetivos del departamento del usuario logeado*/

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
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

    $id_usuario = null;
    
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = (int)$_SESSION['id_usuario'];
    } elseif (isset($_SESSION['user_id'])) {
        $id_usuario = (int)$_SESSION['user_id'];
    }
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }

    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento,
            ur.es_principal,
            d.nombre as departamento_nombre
        FROM tbl_usuario_roles ur
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    
    $role_stmt = $conn->prepare($role_query);
    if (!$role_stmt) {
        throw new Exception('Error preparando consulta de roles: ' . $conn->error);
    }
    
    $role_stmt->bind_param('i', $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    $is_manager = false;
    $is_admin = false;
    $departamentos_gerente = [];
    $departamento_principal = null;
    
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        
        if ($row['id_rol'] == 2) {
            $is_manager = true;
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
        
        if ($row['es_principal'] == 1 || $departamento_principal === null) {
            $departamento_principal = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
    
    // Verificar que sea gerente o admin
    if (!$is_manager && !$is_admin) {
        throw new Exception('Acceso no autorizado - Solo gerentes o administradores');
    }
    
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('Usuario sin departamentos asignados como gerente');
    }

    if ($is_admin) {
        // Admin ve todos los objetivos
        $query = "
            SELECT 
                o.id_objetivo,
                o.nombre,
                o.descripcion,
                o.fecha_cumplimiento,
                o.estado,
                o.archivo_adjunto,
                o.id_departamento,
                d.nombre as area
            FROM tbl_objetivos o
            INNER JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento
            ORDER BY o.fecha_cumplimiento ASC
        ";
        $stmt = $conn->prepare($query);
    } else {
        // Gerente ve objetivos de sus departamentos
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        $query = "
            SELECT 
                o.id_objetivo,
                o.nombre,
                o.descripcion,
                o.fecha_cumplimiento,
                o.estado,
                o.archivo_adjunto,
                o.id_departamento,
                d.nombre as area
            FROM tbl_objetivos o
            INNER JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento
            WHERE o.id_departamento IN ($placeholders)
            ORDER BY o.fecha_cumplimiento ASC
        ";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $types = str_repeat('i', count($departamentos_gerente));
            $stmt->bind_param($types, ...$departamentos_gerente);
        }
    }
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $objetivos = [];
    
    while ($row = $result->fetch_assoc()) {
        $is_managed_dept = in_array((int)$row['id_departamento'], $departamentos_gerente);
        
        $objetivos[] = [
            'id_objetivo' => (int)$row['id_objetivo'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'archivo_adjunto' => $row['archivo_adjunto'] ?? null,
            'id_departamento' => (int)$row['id_departamento'],
            'is_managed_department' => $is_managed_dept
        ];
    }
    
    echo json_encode([
        'success' => true,
        'objetivos' => $objetivos,
        'total' => count($objetivos),
        'id_departamento' => $departamento_principal,
        'managed_departments' => $departamentos_gerente,
        'managed_departments_count' => count($departamentos_gerente),
        'is_admin' => $is_admin
    ], JSON_UNESCAPED_UNICODE);
    
    $result->free();
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'objetivos' => []
    ]);
    error_log('manager_get_objectives.php Error: ' . $e->getMessage());
}
?>