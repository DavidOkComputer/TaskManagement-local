<?php
/**
 * ProfilePictureHandler.php - VERSION SIN GD
 * Versión simplificada que no requiere la librería GD
 * Solo almacena las imágenes sin redimensionar
 * 
 * @author Sistema de Gestión
 * @version 1.1 (No GD)
 */

class ProfilePictureHandler {
    
    // Configuración de uploads
    private const UPLOAD_DIR = '../uploads/profile_pictures/';
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    /**
     * Procesa y guarda una foto de perfil (sin redimensionar)
     * 
     * @param array $file Array $_FILES del archivo subido
     * @param int $userId ID del usuario
     * @return array Resultado con success, message y filename
     */
    public static function uploadProfilePicture($file, $userId) {
        $result = [
            'success' => false,
            'message' => '',
            'filename' => null,
            'thumbnail' => null
        ];
        
        try {
            // Validar que se subió un archivo
            if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
                $result['message'] = 'No se seleccionó ningún archivo';
                return $result;
            }
            
            // Verificar errores de upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $result['message'] = self::getUploadErrorMessage($file['error']);
                return $result;
            }
            
            // Validar tamaño
            if ($file['size'] > self::MAX_FILE_SIZE) {
                $result['message'] = 'El archivo excede el tamaño máximo permitido (5MB)';
                return $result;
            }
            
            // Validar tipo MIME
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            if (!in_array($mimeType, self::ALLOWED_TYPES)) {
                $result['message'] = 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP';
                return $result;
            }
            
            // Validar extensión
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
                $result['message'] = 'Extensión de archivo no permitida';
                return $result;
            }
            
            // Crear directorio si no existe
            $uploadPath = self::UPLOAD_DIR;
            if (!file_exists($uploadPath)) {
                if (!mkdir($uploadPath, 0755, true)) {
                    $result['message'] = 'Error al crear el directorio de uploads';
                    return $result;
                }
            }
            
            // Crear subdirectorio para thumbnails (aunque no los generemos, mantenemos estructura)
            $thumbnailPath = $uploadPath . 'thumbnails/';
            if (!file_exists($thumbnailPath)) {
                mkdir($thumbnailPath, 0755, true);
            }
            
            // Generar nombre único para el archivo
            $timestamp = time();
            $randomStr = bin2hex(random_bytes(8));
            $newFilename = "profile_{$userId}_{$timestamp}_{$randomStr}.{$extension}";
            $fullPath = $uploadPath . $newFilename;
            
            // Mover archivo subido al directorio de destino
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                $result['message'] = 'Error al guardar la imagen';
                return $result;
            }
            
            // Copiar la misma imagen como "thumbnail" (sin redimensionar)
            $thumbnailFullPath = $thumbnailPath . "thumb_{$newFilename}";
            if (!copy($fullPath, $thumbnailFullPath)) {
                // Si falla la copia del thumbnail, no es crítico
                error_log("Warning: No se pudo crear thumbnail para {$newFilename}");
            }
            
            $result['success'] = true;
            $result['message'] = 'Imagen subida exitosamente';
            $result['filename'] = $newFilename;
            $result['thumbnail'] = "thumb_{$newFilename}";
            $result['path'] = 'uploads/profile_pictures/' . $newFilename;
            $result['thumbnail_path'] = 'uploads/profile_pictures/thumbnails/thumb_' . $newFilename;
            
        } catch (Exception $e) {
            $result['message'] = 'Error al procesar la imagen: ' . $e->getMessage();
            error_log("ProfilePictureHandler Error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Elimina la foto de perfil de un usuario
     */
    public static function deleteProfilePicture($filename) {
        if (empty($filename)) {
            return true;
        }
        
        $mainPath = self::UPLOAD_DIR . $filename;
        $thumbPath = self::UPLOAD_DIR . 'thumbnails/thumb_' . $filename;
        
        $success = true;
        
        if (file_exists($mainPath)) {
            $success = unlink($mainPath) && $success;
        }
        
        if (file_exists($thumbPath)) {
            $success = unlink($thumbPath) && $success;
        }
        
        return $success;
    }
    
    /**
     * Obtiene la URL de la foto de perfil o una imagen por defecto
     */
    public static function getProfilePictureUrl($filename, $thumbnail = false) {
        if (empty($filename)) {
            return '../images/default-avatar.png';
        }
        
        if ($thumbnail) {
            $path = 'uploads/profile_pictures/thumbnails/thumb_' . $filename;
        } else {
            $path = 'uploads/profile_pictures/' . $filename;
        }
        
        // Verificar si el archivo existe
        if (file_exists('../' . $path)) {
            return '../' . $path;
        }
        
        return '../images/default-avatar.png';
    }
    
    /**
     * Obtiene el mensaje de error de upload
     */
    private static function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: directorio temporal no encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se puede escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la carga del archivo'
        ];
        
        return $errors[$errorCode] ?? 'Error desconocido al subir el archivo';
    }
    
    /**
     * Valida si un archivo es una imagen válida (para uso en validación previa)
     */
    public static function validateImageFile($file) {
        $errors = [];
        
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['valid' => true, 'errors' => []]; // No file is OK
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }
        
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = 'El archivo excede el tamaño máximo de 5MB';
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            $errors[] = 'Solo se permiten imágenes JPG, PNG, GIF o WebP';
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $errors[] = 'Extensión de archivo no permitida';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Verifica si la librería GD está disponible
     */
    public static function isGDAvailable() {
        return extension_loaded('gd') && function_exists('imagecreatefromjpeg');
    }
}
?>