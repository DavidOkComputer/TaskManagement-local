<?php
/*EmailConfig.php clase para cargar y gestionar la configuracion de email desde la base de datos*/

class EmailConfig {
    private $conn;
    private $config = [];
    private $encryption_key;
    private $loaded = false;
    
    public function __construct($conn) {
        $this->conn = $conn;
        //Clave de encriptación usar variable de entorno
        $this->encryption_key = getenv('EMAIL_ENCRYPTION_KEY') ?: 'task_management_email_key_2024';
        $this->loadConfig();
    }
    
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
    
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
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
    
    private function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
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
    
    public function isEnabled() {
        return $this->get('email_enabled', '0') === '1';
    }
    
    public function isTestMode() {
        return $this->get('test_mode', '1') === '1';
    }
    
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