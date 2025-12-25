<?php
/*manager_get_users.php - Obtener usuarios de un departamento específico*/

session_start();
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

    // Verificar autenticación
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['id_usuario'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario']);

    // Verificar columna foto_perfil
    $checkColumn = $conn->query("SHOW COLUMNS FROM tbl_usuarios LIKE 'foto_perfil'");
    $hasFotoColumn = $checkColumn && $checkColumn->num_rows > 0;
    $fotoField = $hasFotoColumn ? ", u.foto_perfil" : "";

    // Obtener los departamentos que el usuario puede gestionar
    $departamentos_permitidos = [];
    
    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
    ";
    
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param('i', $user_id);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    $is_admin = false;
    
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        if ($row['id_rol'] == 1 || $row['id_rol'] == 2) {
            // Admin o gerente puede gestionar este departamento
            $departamentos_permitidos[] = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
    
    // Determinar el departamento a filtrar
    $id_departamento = null;
    
    // Prioridad 1: Parámetro GET (si es un departamento permitido)
    if (isset($_GET['id_departamento']) && !empty($_GET['id_departamento'])) {
        $requested_dept = (int)$_GET['id_departamento'];
        
        // Admin puede ver cualquier departamento
        if ($is_admin) {
            $id_departamento = $requested_dept;
        } 
        // Gerente solo puede ver departamentos que gestiona
        elseif (in_array($requested_dept, $departamentos_permitidos)) {
            $id_departamento = $requested_dept;
        } else {
            throw new Exception('No tienes permisos para ver usuarios de este departamento');
        }
    }
    // Prioridad 2: Usar el primer departamento permitido
    elseif (!empty($departamentos_permitidos)) {
        $id_departamento = $departamentos_permitidos[0];
    }
    // Prioridad 3: Fallback a sesión
    elseif (isset($_SESSION['id_departamento']) && $_SESSION['id_departamento'] > 0) {
        $id_departamento = (int)$_SESSION['id_departamento'];
    }
    
    if (!$id_departamento && !$is_admin) {
        throw new Exception('No se pudo determinar el departamento del usuario');
    }
    
    $filter_rol = isset($_GET['id_rol']) ? intval($_GET['id_rol']) : null;

    // Construir query base
    $baseFields = "
        u.id_usuario,
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
        {$fotoField}
    ";

    // Para admin sin departamento específico, mostrar todos
    if ($is_admin && !$id_departamento) {
        if ($filter_rol !== null && $filter_rol > 0) {
            $query = "SELECT {$baseFields}
                      FROM tbl_usuarios u
                      LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                      WHERE u.id_rol = ?
                      ORDER BY u.apellido ASC, u.nombre ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $filter_rol);
        } else {
            $query = "SELECT {$baseFields}
                      FROM tbl_usuarios u
                      LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                      ORDER BY u.apellido ASC, u.nombre ASC";
            
            $stmt = $conn->prepare($query);
        }
    } else {
        // Filtrar por departamento
        if ($filter_rol !== null && $filter_rol > 0) {
            $query = "SELECT {$baseFields}
                      FROM tbl_usuarios u
                      LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                      WHERE u.id_departamento = ? AND u.id_rol = ?
                      ORDER BY u.apellido ASC, u.nombre ASC";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Error al preparar la consulta: ' . $conn->error);
            }
            
            $stmt->bind_param("ii", $id_departamento, $filter_rol);
        } else {
            $query = "SELECT {$baseFields}
                      FROM tbl_usuarios u
                      LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                      WHERE u.id_departamento = ?
                      ORDER BY u.apellido ASC, u.nombre ASC";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Error al preparar la consulta: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $id_departamento);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception('Error en la consulta: ' . $conn->error);
    }

    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        $usuario = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'usuario' => $row['usuario'],
            'num_empleado' => (int)$row['num_empleado'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'nombre_empleado' => $row['nombre'] . ' ' . $row['apellido'] . ' (#' . $row['num_empleado'] . ')',
            'acceso' => $row['acceso'],
            'id_departamento' => (int)$row['id_departamento'],
            'id_superior' => (int)$row['id_superior'],
            'id_rol' => (int)$row['id_rol'],
            'e_mail' => $row['e_mail'],
            'area' => $row['area']
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
        'debug' => [
            'id_departamento_filtro' => $id_departamento,
            'departamentos_permitidos' => $departamentos_permitidos,
            'is_admin' => $is_admin,
            'total_usuarios' => count($usuarios)
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
    error_log('manager_get_users.php Error: ' . $e->getMessage());
}
?>