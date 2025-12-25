<?php
/*user_update_project.php para actualizar proyectos*/ 

ob_start(); 
header('Content-Type: application/json; charset=UTF-8'); 
require_once 'db_config.php'; 

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

$response = ['success' => false, 'message' => ''];  

try { 
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        throw new Exception('Método de solicitud inválido'); 
    } 

    // Validar ID del proyecto 
    if (!isset($_POST['id_proyecto'])) { 
        throw new Exception('ID de proyecto requerido'); 
    } 

    $id_proyecto = intval($_POST['id_proyecto']); 
    if ($id_proyecto <= 0) { 
        throw new Exception('ID de proyecto inválido'); 
    } 

    // Obtener ID de usuario desde la sesión
    $id_usuario = null; 
    if (isset($_SESSION['user_id'])) { 
        $id_usuario = intval($_SESSION['user_id']); 
    } elseif (isset($_SESSION['id_usuario'])) { 
        $id_usuario = intval($_SESSION['id_usuario']); 
    } 

    if (!$id_usuario) { 
        throw new Exception('Sesión no válida. Por favor, inicie sesión nuevamente'); 
    } 

    $conn = getDBConnection(); 
    if (!$conn) { 
        throw new Exception('Error de conexión a la base de datos'); 
    } 

    $query_user = "
        SELECT 
            ur.id_departamento,
            ur.id_rol,
            ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
        LIMIT 1
    ";
    
    $stmt_user = $conn->prepare($query_user); 
    if (!$stmt_user) { 
        throw new Exception('Error al preparar consulta de usuario: ' . $conn->error); 
    } 

    $stmt_user->bind_param("i", $id_usuario); 
    if (!$stmt_user->execute()) { 
        throw new Exception('Error al obtener datos del usuario: ' . $stmt_user->error); 
    } 

    $result_user = $stmt_user->get_result(); 
    $user_data = $result_user->fetch_assoc(); 
    $stmt_user->close(); 

    if (!$user_data) { 
        throw new Exception('Usuario no tiene roles asignados. Por favor contacte al administrador.'); 
    } 

    if (!$user_data['id_departamento'] || $user_data['id_departamento'] == 0) { 
        throw new Exception('El usuario no tiene un departamento asignado. Por favor contacte al administrador.'); 
    } 

    $id_usuario_actual = $id_usuario; 
    $id_departamento = intval($user_data['id_departamento']); 
    $id_participante = $id_usuario_actual;

    // Validar campos requeridos 
    $required_fields = [ 
        'nombre', 
        'descripcion', 
        'fecha_creacion', 
        'fecha_cumplimiento' 
    ]; 

    foreach ($required_fields as $field) { 
        if (!isset($_POST[$field])) { 
            throw new Exception("El campo {$field} es requerido"); 
        } 

        if (empty(trim($_POST[$field]))) { 
            throw new Exception("El campo {$field} no puede estar vacío"); 
        } 
    } 

    // Limpiar y validar inputs 
    $nombre = trim($_POST['nombre']); 
    $descripcion = trim($_POST['descripcion']); 
    $fecha_creacion = trim($_POST['fecha_creacion']); 
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']); 
    $progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0; 
    $ar = isset($_POST['ar']) ? trim($_POST['ar']) : ''; 
    $archivo_adjunto = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : ''; 

    // Para usuarios regulares, siempre es proyecto individual y solo el creador puede editar 
    $id_tipo_proyecto = 2;
    $puede_editar_otros = 0;

    // Validaciones de longitud 
    if (strlen($nombre) > 100) { 
        throw new Exception('El nombre no puede exceder 100 caracteres'); 
    } 

    if (strlen($descripcion) > 200) { 
        throw new Exception('La descripción no puede exceder 200 caracteres'); 
    } 

    if (strlen($archivo_adjunto) > 300) { 
        throw new Exception('La ruta del archivo no puede exceder 300 caracteres'); 
    } 

    // Formato de fecha de creación 
    if (strpos($fecha_creacion, 'T') !== false) { 
        $fecha_creacion = str_replace('T', ' ', $fecha_creacion); 
    } 

    // Validar fechas 
    if (strtotime($fecha_creacion) === false) { 
        throw new Exception('La fecha de creación no es válida'); 
    } 

    if (strtotime($fecha_cumplimiento) === false) { 
        throw new Exception('La fecha de cumplimiento no es válida'); 
    } 

    if (strtotime($fecha_cumplimiento) < strtotime($fecha_creacion)) { 
        throw new Exception('La fecha de entrega debe ser posterior o igual a la fecha de inicio'); 
    } 

    // Auto-calcular estado basado en la fecha de entrega y progreso 
    $today = date('Y-m-d'); 
    $deadline = substr($fecha_cumplimiento, 0, 10); 

    if ($progreso >= 100) { 
        $estado = 'completado'; 
    } elseif ($deadline < $today) { 
        $estado = 'vencido'; 
    } elseif ($progreso > 0) { 
        $estado = 'en proceso'; 
    } else { 
        $estado = 'pendiente'; 
    } 

    // Verificar que el proyecto pertenezca al usuario actual 
    $sql_check = "SELECT id_creador, id_participante, id_departamento FROM tbl_proyectos WHERE id_proyecto = ?"; 
    $stmt_check = $conn->prepare($sql_check); 

    if (!$stmt_check) { 
        throw new Exception('Error al verificar proyecto: ' . $conn->error); 
    } 

    $stmt_check->bind_param("i", $id_proyecto); 
    $stmt_check->execute(); 
    $result_check = $stmt_check->get_result(); 

    if ($result_check->num_rows === 0) { 
        throw new Exception('El proyecto no existe'); 
    } 

    $proyecto = $result_check->fetch_assoc(); 
    $stmt_check->close(); 

    // Verificar que el usuario sea el creador o participante del proyecto 
    if ($proyecto['id_creador'] != $id_usuario_actual && $proyecto['id_participante'] != $id_usuario_actual) { 
        throw new Exception('No tienes permisos para editar este proyecto'); 
    }
    
    // Mantener el departamento original del proyecto (no cambiarlo)
    $id_departamento_proyecto = (int)$proyecto['id_departamento'];

    // Actualizar el proyecto 
    $sql = "UPDATE tbl_proyectos SET 
        nombre = ?, 
        descripcion = ?, 
        id_departamento = ?, 
        fecha_inicio = ?, 
        fecha_cumplimiento = ?, 
        progreso = ?, 
        ar = ?,
        estado = ?, 
        archivo_adjunto = ?, 
        id_participante = ?, 
        id_tipo_proyecto = ?, 
        puede_editar_otros = ? 
    WHERE id_proyecto = ?"; 

    $stmt = $conn->prepare($sql); 

    if (!$stmt) { 
        throw new Exception('Error al preparar la consulta: ' . $conn->error); 
    } 

    $stmt->bind_param( 
        "ssissississii", 
        $nombre,
        $descripcion,
        $id_departamento_proyecto,  // Mantener departamento original
        $fecha_creacion,
        $fecha_cumplimiento,
        $progreso,
        $ar,
        $estado,
        $archivo_adjunto,
        $id_participante,
        $id_tipo_proyecto,
        $puede_editar_otros,
        $id_proyecto
    ); 

    if (!$stmt->execute()) { 
        throw new Exception('Error al actualizar el proyecto: ' . $stmt->error); 
    } 

    $stmt->close(); 
    $conn->close(); 

    $response['success'] = true; 
    $response['message'] = 'Proyecto actualizado exitosamente'; 
    $response['id_proyecto'] = $id_proyecto; 

} catch (Exception $e) { 
    $response['success'] = false; 
    $response['message'] = $e->getMessage(); 
    error_log('Error in user_update_project.php: ' . $e->getMessage()); 
} 

ob_end_clean(); 
echo json_encode($response); 
exit; 
?>