<?php 
/* api_get_pending_projects.php Obtener proyectos pendientes según el rol del usuario */ 

ob_start(); 
session_start(); 
header("Content-Type: application/json"); 
ob_end_clean(); 

set_error_handler(function ($errno, $errstr, $errfile, $errline) { 
    error_log("PHP Error: $errstr in $errfile on line $errline"); 
}); 
 
require_once "db_config.php"; 

$conexion = getDBConnection(); 

if (!$conexion) { 
    http_response_code(500); 
    echo json_encode([ 
        "success" => false, 
        "message" => "Error de conexión a la base de datos", 
    ]); 
    exit(); 
} 

$usuario_id = $_SESSION['user_id']; 
$proyectos = []; 

try { 
    $stmt_user = $conexion->prepare(" 
        SELECT  
            ur.id_rol,  
            ur.id_departamento, 
            r.nombre as rol_nombre, 
            d.nombre as departamento_nombre 
        FROM tbl_usuario_roles ur 
        JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        WHERE ur.id_usuario = ?  
            AND ur.es_principal = 1  
            AND ur.activo = 1 
    "); 

    $stmt_user->bind_param("i", $usuario_id); 
    $stmt_user->execute(); 
    $user_info = $stmt_user->get_result()->fetch_assoc(); 
    $stmt_user->close(); 

    if (!$user_info) { 
        throw new Exception("Usuario no encontrado o sin rol asignado"); 
    } 

    $id_rol = $user_info['id_rol']; 
    $id_departamento = $user_info['id_departamento']; 

    $departamentos_gerente = []; 

    if ($id_rol == 2) { 
        $stmt_deptos = $conexion->prepare(" 
            SELECT id_departamento  
            FROM tbl_usuario_roles  
            WHERE id_usuario = ?  
                AND id_rol = 2  
                AND activo = 1 
        "); 

        $stmt_deptos->bind_param("i", $usuario_id); 
        $stmt_deptos->execute(); 
        $result_deptos = $stmt_deptos->get_result(); 
        while ($row = $result_deptos->fetch_assoc()) { 
            $departamentos_gerente[] = $row['id_departamento']; 
        } 
        $stmt_deptos->close(); 
    } 

    // Construir query según el rol del usuario 
    $query = " 
        SELECT DISTINCT 
            p.id_proyecto, 
            p.nombre, 
            p.descripcion, 
            p.id_departamento, 
            p.fecha_inicio, 
            p.fecha_cumplimiento, 
            p.progreso, 
            p.estado, 
            p.id_creador, 
            p.id_tipo_proyecto, 
            p.puede_editar_otros, 
            d.nombre AS nombre_departamento, 
            tp.nombre AS tipo_proyecto, 
            u_creador.nombre AS creador_nombre, 
            u_creador.apellido AS creador_apellido 
        FROM tbl_proyectos p 
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
        LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto 
        LEFT JOIN tbl_usuarios u_creador ON p.id_creador = u_creador.id_usuario 
    "; 

    // Aplicar filtros según el rol 
    if ($id_rol == 1) { 
        // ADMINISTRADOR: Ve todos los proyectos pendientes 
        $query .= " WHERE p.estado = 'pendiente'"; 
        $stmt = $conexion->prepare($query . " ORDER BY p.fecha_cumplimiento ASC, p.fecha_creacion DESC"); 
    } elseif ($id_rol == 2) { 

        $query .= " 
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto 
            WHERE p.estado = 'pendiente' 
            AND ( 
                p.id_departamento IN (" . implode(',', array_map('intval', $departamentos_gerente)) . ") 
                OR p.id_creador = ? 
                OR pu.id_usuario = ? 
            ) 
            ORDER BY p.fecha_cumplimiento ASC, p.fecha_creacion DESC 
        "; 

        $stmt = $conexion->prepare($query); 
        $stmt->bind_param("ii", $usuario_id, $usuario_id); 

    } else { 
        // USUARIO: Solo ve proyectos donde está asignado o creó 
        $query .= " 
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto 
            WHERE p.estado = 'pendiente' 
            AND ( 
                p.id_creador = ? 
                OR pu.id_usuario = ? 
            ) 
            ORDER BY p.fecha_cumplimiento ASC, p.fecha_creacion DESC 
        "; 

        $stmt = $conexion->prepare($query); 
        $stmt->bind_param("ii", $usuario_id, $usuario_id); 
    } 

    if (!$stmt) { 
        throw new Exception("Error preparando consulta: " . $conexion->error); 
    } 

    if (!$stmt->execute()) { 
        throw new Exception("Error ejecutando consulta: " . $stmt->error); 
    } 

    $result = $stmt->get_result(); 
    // Formatear resultados 
    while ($proyecto = $result->fetch_assoc()) { 
        $estado_display = match ($proyecto["estado"]) { 
            "pendiente" => "Pendiente", 
            "en proceso" => "En Progreso", 
            "vencido" => "Vencido", 
            "completado" => "Completado", 
            default => $proyecto["estado"], 
        }; 

        $estado_style = match ($proyecto["estado"]) { 
            "pendiente" => "badge-danger", 
            "en proceso" => "badge-warning", 
            "vencido" => "badge-danger", 
            "completado" => "badge-success", 
            default => "badge-secondary", 
        }; 

        $progreso_color = match ($proyecto["estado"]) { 
            "pendiente" => "bg-danger", 
            "en proceso" => "bg-warning", 
            "vencido" => "bg-danger", 
            "completado" => "bg-success", 
            default => "bg-secondary", 
        }; 

        $puede_editar = false; 
        if ($id_rol == 1) { 
            // Administrador puede editar todo 
            $puede_editar = true; 
        } elseif ($id_rol == 2) { 
            // Gerente puede editar proyectos de sus departamentos o que creó 
            $puede_editar = in_array($proyecto['id_departamento'], $departamentos_gerente) || 
                $proyecto['id_creador'] == $usuario_id || 
                $proyecto['puede_editar_otros'] == 1; 
        } else { 
            // Usuario solo puede editar proyectos que creó 
            $puede_editar = ($proyecto['id_creador'] == $usuario_id); 
        } 

        $proyectos[] = [ 
            "id_proyecto" => $proyecto["id_proyecto"], 
            "nombre" => htmlspecialchars($proyecto["nombre"]), 
            "descripcion" => htmlspecialchars($proyecto["descripcion"]), 
            "departamento" => htmlspecialchars($proyecto["nombre_departamento"] ?? "N/A"), 
            "tipo_proyecto" => htmlspecialchars($proyecto["tipo_proyecto"] ?? "N/A"), 
            "creador" => htmlspecialchars( 
                ($proyecto["creador_nombre"] ?? "N/A") . 
                    " " . 
                    ($proyecto["creador_apellido"] ?? "") 
            ), 
            "fecha_cumplimiento" => $proyecto["fecha_cumplimiento"], 
            "progreso" => (int) $proyecto["progreso"], 
            "estado" => $proyecto["estado"], 
            "estado_display" => $estado_display, 
            "estado_style" => $estado_style, 
            "progreso_color" => $progreso_color, 
            "puede_editar" => $puede_editar, 
            "puede_editar_otros" => (bool) $proyecto["puede_editar_otros"] 
        ]; 
    } 

    $stmt->close(); 

    echo json_encode([ 
        "success" => true, 
        "data" => $proyectos, 
        "total" => count($proyectos), 
        "user_role" => $id_rol, 
        "user_department" => $id_departamento, 
        "user_departments_count" => $id_rol == 2 ? count($departamentos_gerente) : 1, 
    ]); 

} catch (Exception $e) { 
    error_log("Error en api_get_pending_projects.php: " . $e->getMessage()); 
    http_response_code(500); 
    echo json_encode([ 
        "success" => false, 
        "message" => "Error al obtener proyectos pendientes", 
        "error" => $e->getMessage(), 
    ]); 
} 

restore_error_handler(); 
?> 