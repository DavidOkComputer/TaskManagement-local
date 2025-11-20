<?php

    function getDBConnection(){
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root'); 
        define('DB_PASS', ''); 
        define('DB_NAME', 'task_management_db');

        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            error_log("Connection failed: " . $conn->connect_error);
            die(json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos'
            ]));
        }

        $conn->set_charset("utf8mb4");
        return $conn;
    }
?>