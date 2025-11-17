<?php
header('Content-Type: application/json');
require_once 'db_config.php';

//reporte de errores de debg
error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => ''];

try {
    //revisar si el metodo es post
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    //validar los campos requeridos
    $required_fields = ['nombre', 
                        'descripcion', 
                        'fecha_cumplimiento', 
                        'id_departamento', 
                        'id_creador'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    //limpiar inputs
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $id_departamento = intval($_POST['id_departamento']);
    $id_creador = intval($_POST['id_creador']);
    $ar = isset($_POST['ar']) ? trim($_POST['ar']) : '';

    //validar longitud 
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres');
    }

    //validar fortmato de fecha
    $date = DateTime::createFromFormat('Y-m-d', $fecha_cumplimiento);
    if (!$date || $date->format('Y-m-d') !== $fecha_cumplimiento) {
        throw new Exception('Formato de fecha inválido');
    }

    //tipo de documentos que se suben
    $archivo_adjunto = '';
    $upload_dir = '../uploads/objetivos/';
    
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            //crear directorio para subir archivos probablementoe cambiar despues
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $max_size = 10 * 1024 * 1024; // 10MB
            if ($_FILES['archivo']['size'] > $max_size) {
                throw new Exception('El archivo no puede exceder 10MB');
            }
            
            //extension del archivo
            $file_name = $_FILES['archivo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            //extensiones permititdas
            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Tipo de archivo no permitido');
            }

            //generar un nombre de archivo unico
            $new_filename = uniqid('obj_') . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            //mover archivo subido
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $upload_path)) {
                $archivo_adjunto = $upload_path;
            } else {
                throw new Exception('Error al subir el archivo');
            }
        } else {
            throw new Exception('Error en la carga del archivo: ' . $_FILES['archivo']['error']);
        }
    }

    $conn = getDBConnection();//conexion a base de datos
    //query sql
    $sql = "INSERT INTO tbl_objetivos (
                nombre, 
                descripcion, 
                id_departamento, 
                fecha_cumplimiento, 
                estado, 
                ar, 
                archivo_adjunto, 
                id_creador
            ) VALUES (?, ?, ?, ?, 'pendiente', ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    //bind para parametroz
    $stmt->bind_param(
        "ssisssi",
        $nombre,
        $descripcion,
        $id_departamento,
        $fecha_cumplimiento,
        $ar,
        $archivo_adjunto,
        $id_creador
    );

    //ejecutar la consulta
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Objetivo creado exitosamente';
        $response['id_objetivo'] = $stmt->insert_id;
    } else {
        throw new Exception('Error al crear el objetivo: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    //eliminar los archivos si la base de datos no puede insertar 
    if (isset($archivo_adjunto) && !empty($archivo_adjunto) && file_exists($archivo_adjunto)) {
        unlink($archivo_adjunto);
    }
}

echo json_encode($response);
?>