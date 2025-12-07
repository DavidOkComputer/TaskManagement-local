<?php
/**
 * EmailConfig.php
 * Clase para cargar y gestionar la configuración de email desde la base de datos
 * 
 * @package TaskManagement\Email
 * @author Sistema de Tareas
 */

class EmailConfig {
    private $conn;
    private $config = [];
    private $encryption_key;
    private $loaded = false;
    
    /**
     * Constructor
     * @param mysqli $conn Conexión a la base de datos
     */
    public function __construct($conn) {
        $this->conn = $conn;
        // Clave de encriptación - en producción usar variable de entorno
        $this->encryption_key = getenv('EMAIL_ENCRYPTION_KEY') ?: 'task_management_email_key_2024';
        $this->loadConfig();
    }
    
    /**
     * Cargar configuración desde la base de datos
     */
    private function loadConfig() {
        if ($this->loaded) {
            return;
        }
        
        $result = $this->conn->query("SELECT config_key, config_value, is_encrypted FROM tbl_email_config");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $value = $row['config_value'];
                
                // Desencriptar si es necesario
                if ($row['is_encrypted'] && !empty($value)) {
                    $value = $this->decrypt($value);
                }
                
                $this->config[$row['config_key']] = $value;
            }
            $this->loaded = true;
        }
    }
    
    /**
     * Obtener un valor de configuración
     * @param string $key Clave de configuración
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Establecer un valor de configuración
     * @param string $key Clave
     * @param mixed $value Valor
     * @param bool $encrypt Si debe encriptarse
     * @return bool
     */
    public function set($key, $value, $encrypt = false) {
        $storedValue = $encrypt ? $this->encrypt($value) : $value;
        $isEncrypted = $encrypt ? 1 : 0;
        
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_email_config (config_key, config_value, is_encrypted) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE config_value = ?, is_encrypted = ?"
        );
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("ssisi", $key, $storedValue, $isEncrypted, $storedValue, $isEncrypted);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->config[$key] = $value;
        }
        
        return $result;
    }
    
    /**
     * Encriptar un valor
     * @param string $data Datos a encriptar
     * @return string Datos encriptados en base64
     */
    private function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Desencriptar un valor
     * @param string $data Datos encriptados en base64
     * @return string Datos desencriptados
     */
    private function decrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        try {
            $data = base64_decode($data);
            if ($data === false || strlen($data) < 16) {
                return '';
            }
            
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
            
            return $decrypted !== false ? $decrypted : '';
        } catch (Exception $e) {
            error_log("Error desencriptando configuración de email: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Verificar si el servicio de email está habilitado
     * @return bool
     */
    public function isEnabled() {
        return $this->get('email_enabled', '0') === '1';
    }
    
    /**
     * Verificar si está en modo de prueba
     * @return bool
     */
    public function isTestMode() {
        return $this->get('test_mode', '1') === '1';
    }
    
    /**
     * Obtener toda la configuración (sin contraseñas)
     * @return array
     */
    public function getAll() {
        $safe_config = [];
        foreach ($this->config as $key => $value) {
            if (strpos($key, 'password') !== false) {
                $safe_config[$key] = !empty($value) ? '********' : '';
            } else {
                $safe_config[$key] = $value;
            }
        }
        return $safe_config;
    }
    
    /**
     * Validar configuración SMTP
     * @return array Array con 'valid' (bool) y 'errors' (array)
     */
    public function validateConfig() {
        $errors = [];
        
        if (empty($this->get('smtp_host'))) {
            $errors[] = 'Falta configurar el servidor SMTP (smtp_host)';
        }
        
        if (empty($this->get('smtp_port'))) {
            $errors[] = 'Falta configurar el puerto SMTP (smtp_port)';
        }
        
        if (empty($this->get('smtp_user'))) {
            $errors[] = 'Falta configurar el usuario SMTP (smtp_user)';
        }
        
        if (empty($this->get('smtp_password'))) {
            $errors[] = 'Falta configurar la contraseña SMTP (smtp_password)';
        }
        
        if (empty($this->get('smtp_from_email'))) {
            $errors[] = 'Falta configurar el email del remitente (smtp_from_email)';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Actualizar configuración de Gmail de forma rápida
     * @param string $email Email de Gmail
     * @param string $appPassword Contraseña de aplicación
     * @return bool
     */
    public function configureGmail($email, $appPassword) {
        $success = true;
        
        $success = $success && $this->set('smtp_host', 'smtp.gmail.com');
        $success = $success && $this->set('smtp_port', '587');
        $success = $success && $this->set('smtp_encryption', 'tls');
        $success = $success && $this->set('smtp_user', $email);
        $success = $success && $this->set('smtp_password', $appPassword, true);
        $success = $success && $this->set('smtp_from_email', $email);
        
        return $success;
    }
}