<?php
/*get_users.php  Obtener lista de usuarios */
 
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
 
    // Verificar si la columna foto_perfil existe (para compatibilidad)
    $checkColumn = $conn->query("SHOW COLUMNS FROM tbl_usuarios LIKE 'foto_perfil'");
    $hasFotoColumn = $checkColumn && $checkColumn->num_rows > 0;
 
    // Campo adicional para foto de perfil (solo si existe la columna)
    $fotoField = $hasFotoColumn ? ", u.foto_perfil" : "";
 
    // Obtener filtros
    $filter_rol = isset($_GET['id_rol']) ? intval($_GET['id_rol']) : null;
    $filter_departamento = isset($_GET['id_departamento']) ? intval($_GET['id_departamento']) : null;
    $solo_principal = isset($_GET['solo_principal']) ? filter_var($_GET['solo_principal'], FILTER_VALIDATE_BOOLEAN) : true;
 
    // Base query usando tbl_usuario_roles para obtener información de rol/departamento
    // Por defecto usa el rol principal (es_principal = 1)
    
    if ($filter_rol !== null && $filter_rol > 0 && $filter_departamento !== null && $filter_departamento > 0) {
        // Filtrar por rol Y departamento específico en tbl_usuario_roles
        $query = "SELECT DISTINCT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.usuario,
                    u.num_empleado,
                    u.acceso,
                    u.id_superior,
                    u.e_mail,
                    ur.id_departamento,
                    ur.id_rol,
                    ur.es_principal,
                    d.nombre as area,
                    r.nombre as nombre_rol
                    {$fotoField}
                FROM tbl_usuarios u
                INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario AND ur.activo = 1
                LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
                LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol
                WHERE ur.id_rol = ? AND ur.id_departamento = ?
                ORDER BY u.apellido ASC, u.nombre ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("ii", $filter_rol, $filter_departamento);
        $stmt->execute();
        $result = $stmt->get_result();
 
    } elseif ($filter_rol !== null && $filter_rol > 0) {
        // Filtrar solo por rol (usuarios que tengan este rol en cualquier departamento)
        $query = "SELECT DISTINCT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.usuario,
                    u.num_empleado,
                    u.acceso,
                    u.id_superior,
                    u.e_mail,
                    ur.id_departamento,
                    ur.id_rol,
                    ur.es_principal,
                    d.nombre as area,
                    r.nombre as nombre_rol
                    {$fotoField}
                FROM tbl_usuarios u
                INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario AND ur.activo = 1
                LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
                LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol
                WHERE ur.id_rol = ?
                ORDER BY u.apellido ASC, u.nombre ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("i", $filter_rol);
        $stmt->execute();
        $result = $stmt->get_result();
 
    } elseif ($filter_departamento !== null && $filter_departamento > 0) {
        // Filtrar solo por departamento (usuarios que pertenezcan a este departamento)
        $query = "SELECT DISTINCT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.usuario,
                    u.num_empleado,
                    u.acceso,
                    u.id_superior,
                    u.e_mail,
                    ur.id_departamento,
                    ur.id_rol,
                    ur.es_principal,
                    d.nombre as area,
                    r.nombre as nombre_rol
                    {$fotoField}
                FROM tbl_usuarios u
                INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario AND ur.activo = 1
                LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
                LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol
                WHERE ur.id_departamento = ?
                ORDER BY u.apellido ASC, u.nombre ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param("i", $filter_departamento);
        $stmt->execute();
        $result = $stmt->get_result();
 
    } else {
        // Sin filtros: obtener todos los usuarios con su rol principal
        $query = "SELECT DISTINCT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.usuario,
                    u.num_empleado,
                    u.acceso,
                    u.id_superior,
                    u.e_mail,
                    COALESCE(ur.id_departamento, u.id_departamento) as id_departamento,
                    COALESCE(ur.id_rol, u.id_rol) as id_rol,
                    COALESCE(ur.es_principal, 1) as es_principal,
                    d.nombre as area,
                    r.nombre as nombre_rol
                    {$fotoField}
                FROM tbl_usuarios u
                LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario
                    AND ur.activo = 1
                    AND ur.es_principal = 1
                LEFT JOIN tbl_departamentos d ON COALESCE(ur.id_departamento, u.id_departamento) = d.id_departamento
                LEFT JOIN tbl_roles r ON COALESCE(ur.id_rol, u.id_rol) = r.id_rol
                ORDER BY u.apellido ASC, u.nombre ASC";
        
        $result = $conn->query($query);
    }
 
    if (!$result) {
        throw new Exception('Error en la consulta: ' . $conn->error);
    }
 
    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        $usuario = [
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
            'nombre_rol' => $row['nombre_rol'] ?? '',
            'es_principal' => (bool)($row['es_principal'] ?? true),
            'e_mail' => $row['e_mail'] ?? '',
            'area' => $row['area'] ?? ''
        ];
 
        // Agregar campos de foto de perfil
        if ($hasFotoColumn && isset($row['foto_perfil'])) {
            $fotoPerfil = $row['foto_perfil'];
            $usuario['foto_perfil'] = $fotoPerfil;
 
            if (!empty($fotoPerfil)) {
                $usuario['foto_url'] = 'uploads/profile_pictures/' . $fotoPerfil;
                $usuario['foto_thumbnail'] = 'uploads/profile_pictures/thumbnails/thumb_' . $fotoPerfil;
            } else {
                $usuario['foto_url'] = null;
                $usuario['foto_thumbnail'] = null;
            }
        } else {
            $usuario['foto_perfil'] = null;
            $usuario['foto_url'] = null;
            $usuario['foto_thumbnail'] = null;
        }
 
        $usuarios[] = $usuario;
    }
 
    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'total' => count($usuarios),
        'filters_applied' => [
            'id_rol' => $filter_rol,
            'id_departamento' => $filter_departamento,
            'solo_principal' => $solo_principal
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