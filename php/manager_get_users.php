<?php
/*manager_get_users.php obtener losusuarios del mismo departamento que el gerente*/
 
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

    // Verificar si la columna foto_perfil existe
    $checkColumn = $conn->query("SHOW COLUMNS FROM tbl_usuarios LIKE 'foto_perfil'");
    $hasFotoColumn = $checkColumn && $checkColumn->num_rows > 0;
    
    // Campo adicional para foto de perfil (solo si existe la columna)
    $fotoField = $hasFotoColumn ? ", u.foto_perfil" : "";
 
    $id_departamento = null;
    
    if (isset($_SESSION['id_departamento']) && $_SESSION['id_departamento'] > 0) {
        $id_departamento = (int)$_SESSION['id_departamento'];
    } else if (isset($_SESSION['user_id'])) {
        $user_query = "SELECT id_departamento FROM tbl_usuarios WHERE id_usuario = ?";
        $user_stmt = $conn->prepare($user_query);
        $user_id = (int)$_SESSION['user_id'];
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_row = $user_result->fetch_assoc()) {
            $id_departamento = (int)$user_row['id_departamento'];
            $_SESSION['id_departamento'] = $id_departamento; 
        }
        $user_stmt->close();
    }
    
    if (!$id_departamento) {
        throw new Exception('No se pudo determinar el departamento del usuario');
    }
    
    $filter_rol = isset($_GET['id_rol']) ? intval($_GET['id_rol']) : null;
 
    if ($filter_rol !== null && $filter_rol > 0) {
        //filtrar por departmaento y rol
        $query = "SELECT
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
                  FROM tbl_usuarios u
                  LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                  WHERE u.id_departamento = ? AND u.id_rol = ?
                  ORDER BY u.apellido ASC, u.nombre ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        
        $stmt->bind_param("ii", $id_departamento, $filter_rol);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT
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
                  FROM tbl_usuarios u
                  LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
                  WHERE u.id_departamento = ?
                  ORDER BY u.apellido ASC, u.nombre ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $id_departamento);
        $stmt->execute();
        $result = $stmt->get_result();
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