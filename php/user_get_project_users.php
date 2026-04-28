<?php
//user_get_project_users.php to get all of the projects of the user
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

$response = ['success' => false, 'message' => '', 'usuarios' => [], 'es_libre' => 0];

try {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) throw new Exception('No autenticado');

    $id_proyecto = isset($_GET['id']) ? intval($_GET['id']) : 0;
    // ... similar permission check as above, then fetch users from tbl_proyecto_usuarios or individual participant
    // Return list with id_usuario, nombre, apellido, num_empleado, and also es_libre flag from project
    // Implementation similar to get_project_user.php but with access check
    // ...
} catch (Exception $e) { ... }