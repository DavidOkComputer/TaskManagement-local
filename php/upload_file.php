<?php
// subir_archivo.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
 
// Iniciar buffer de output para hacer catch a output inesperado
ob_start();
 
$response = [
    'success' => false,
    'message' => '',
    'filePath' => ''
];
 
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud no válido');
    }
 
    // Verificar que se envió un archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No se seleccionó ningún archivo');
    }
 
    $archivo = $_FILES['archivo'];
 
    // Verificar errores de upload
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        switch ($archivo['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('El archivo excede el tamaño máximo permitido');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('El archivo solo se subió parcialmente');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Falta carpeta temporal');
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Error al escribir el archivo en disco');
            default:
                throw new Exception('Error desconocido al subir el archivo');
        }
    }
 
    // Validar tamaño del archivo (10MB máximo)
    $maxSize = 10 * 1024 * 1024; // 10MB en bytes
    if ($archivo['size'] > $maxSize) {
        throw new Exception('El archivo no debe superar los 10MB');
    }
 
    if ($archivo['size'] === 0) {
        throw new Exception('El archivo está vacío');
    }
 
    // Obtener información del archivo
    $nombreOriginal = basename($archivo['name']);
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    
    // Extensiones permitidas
    $extensionesPermitidas = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'ppt', 'pptx', 'txt', 'jpg', 'jpeg',
        'png', 'gif', 'zip', 'rar'
    ];
 
    if (!in_array($extension, $extensionesPermitidas)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', $extensionesPermitidas));
    }
 
    // Verificar MIME type para mayor seguridad
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
 
    $mimePermitidos = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/octet-stream'
    ];
 
    if (!in_array($mimeType, $mimePermitidos)) {
        throw new Exception('El tipo MIME del archivo no es válido');
    }
 
    // Crear directorio de uploads si no existe
    $uploadDir = __DIR__ . '/../uploads/proyectos/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de uploads');
        }
    }
 
    // Generar nombre único y seguro para el archivo
    // Formato: timestamp_randomstring_nombreoriginal
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    $nombreSeguro = $timestamp . '_' . $randomString . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal);
    
    // Limitar longitud del nombre
    if (strlen($nombreSeguro) > 255) {
        $nombreSeguro = substr($nombreSeguro, 0, 200) . '.' . $extension;
    }
 
    $rutaCompleta = $uploadDir . $nombreSeguro;
 
    // Verificar que el archivo no existe (por seguridad adicional)
    if (file_exists($rutaCompleta)) {
        // Generar nuevo nombre si existe
        $nombreSeguro = $timestamp . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
        $rutaCompleta = $uploadDir . $nombreSeguro;
    }
 
    // Mover archivo desde temporal a destino final
    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        throw new Exception('Error al mover el archivo a su ubicación final');
    }
 
    // Establecer permisos del archivo
    chmod($rutaCompleta, 0644);
 
    // Ruta relativa para guardar en la base de datos
    $rutaRelativa = 'uploads/proyectos/' . $nombreSeguro;
 
    $response['success'] = true;
    $response['message'] = 'Archivo subido exitosamente';
    $response['filePath'] = $rutaRelativa;
    $response['nombreOriginal'] = $nombreOriginal;
    $response['tamano'] = $archivo['size'];
 
    error_log("Archivo subido: {$rutaRelativa} (Original: {$nombreOriginal}, Tamaño: {$archivo['size']} bytes)");
 
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Error en subir_archivo.php: " . $e->getMessage());
    
    // Si el archivo se movió pero hubo error después, intentar eliminarlo
    if (isset($rutaCompleta) && file_exists($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
}
 
ob_end_clean(); // Limpiar output inesperado
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>