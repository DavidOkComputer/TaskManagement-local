<?php 

/*user_create_project.php para crear proyectos para usuarios*/ 

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

    // Obtener ID de usuario desde la sesión (soportar múltiples nombres de variable) 
    $id_usuario = null; 
    if (isset($_SESSION['user_id'])) { 
        $id_usuario = intval($_SESSION['user_id']); 
    } elseif (isset($_SESSION['id_usuario'])) { 
        $id_usuario = intval($_SESSION['id_usuario']); 
    } 

    // Validar que tenemos un ID de usuario 
    if (!$id_usuario) { 
        throw new Exception('Sesión no válida. Por favor, inicie sesión nuevamente'); 
    } 

    // Conectar a la base de datos PRIMERO para obtener el departamento del usuario 
    $conn = getDBConnection(); 
    if (!$conn) { 
        throw new Exception('Error de conexión a la base de datos'); 
    } 

    $query_user = "
        SELECT 
            ur.id_departamento,
            ur.id_rol,
            d.nombre as departamento_nombre
        FROM tbl_usuario_roles ur
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
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

    // Ahora tenemos el departamento del usuario desde tbl_usuario_roles
    $id_creador = $id_usuario; 
    $id_departamento = intval($user_data['id_departamento']); 
    $id_participante = $id_creador; // El usuario se asigna a sí mismo 

    if (isset($_POST['id_departamento']) && !empty($_POST['id_departamento'])) {
        $id_departamento_solicitado = intval($_POST['id_departamento']);
        
        // Verificar que el usuario tiene acceso a este departamento
        $verify_dept = $conn->prepare("
            SELECT 1 FROM tbl_usuario_roles 
            WHERE id_usuario = ? AND id_departamento = ? AND activo = 1
        ");
        $verify_dept->bind_param("ii", $id_usuario, $id_departamento_solicitado);
        $verify_dept->execute();
        
        if ($verify_dept->get_result()->num_rows > 0) {
            $id_departamento = $id_departamento_solicitado;
        }
        $verify_dept->close();
    }

    // Validar campos requeridos 
    $required_fields = [
        'nombre', 
        'descripcion', 
        'fecha_creacion', 
        'fecha_cumplimiento', 
        'estado' 
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
    $estado = trim($_POST['estado']); 
    $archivo_adjunto = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : ''; 

    // Para usuarios regulares, siempre es proyecto individual y solo el creador puede editar 
    $id_tipo_proyecto = 2; // Individual 
    $puede_editar_otros = 0; // Solo el creador puede editar 

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

    // Validar estado 
    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado']; 
    if (!in_array($estado, $estados_validos)) { 
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado'); 
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

    // Insertar proyecto 
    $sql = "INSERT INTO tbl_proyectos ( 
        nombre, 
        descripcion, 
        id_departamento, 
        fecha_inicio, 
        fecha_cumplimiento, 
        progreso, 
        ar, 
        estado, 
        archivo_adjunto, 
        id_creador, 
        id_participante, 
        id_tipo_proyecto, 
        puede_editar_otros 
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 

    $stmt = $conn->prepare($sql); 
    if (!$stmt) { 
        throw new Exception('Error al preparar la consulta: ' . $conn->error); 
    } 

    $stmt->bind_param( 
        "ssissississii", 
        $nombre,                // s-1 
        $descripcion,           // s-2 
        $id_departamento,       // i-3 (desde tbl_usuario_roles) 
        $fecha_creacion,        // s-4 
        $fecha_cumplimiento,    // s-5 
        $progreso,              // i-6 
        $ar,                    // s-7 
        $estado,                // s-8 
        $archivo_adjunto,       // s-9 
        $id_creador,            // i-10 (desde sesión) 
        $id_participante,       // i-11 (mismo que creador) 
        $id_tipo_proyecto,      // i-12 (siempre 2 - individual) 
        $puede_editar_otros     // i-13 (siempre 0 - solo creador) 
    ); 

    if (!$stmt->execute()) { 
        throw new Exception('Error al crear el proyecto: ' . $stmt->error); 
    } 

    $id_proyecto = $stmt->insert_id; 
    $stmt->close(); 
    $conn->close(); 
    
    $response['success'] = true; 
    $response['message'] = 'Proyecto creado exitosamente'; 
    $response['id_proyecto'] = $id_proyecto;
    $response['id_departamento'] = $id_departamento;

} catch (Exception $e) { 
    $response['success'] = false; 
    $response['message'] = $e->getMessage(); 
    error_log('Error in user_create_project.php: ' . $e->getMessage()); 
} 

ob_end_clean(); 
echo json_encode($response); 
exit; 
?>