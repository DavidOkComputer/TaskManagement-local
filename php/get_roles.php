<?php
// get_roles.php
// Devuelve los roles de tbl_roles y agrega una opcion sintetica "Supervisor"
// lo mapea a id_rol = 2 + es_supervisor = 1 antes de guardar.

header('Content-Type: application/json');

require_once 'db_config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    $query = "SELECT id_rol, nombre
              FROM tbl_roles
              ORDER BY nombre ASC";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $roles = [];

    while ($row = $result->fetch_assoc()) {
        $roles[] = [
            'id_rol'        => (int)$row['id_rol'],
            'nombre'        => $row['nombre'],
            'es_supervisor' => false
        ];
    }

    // Insertar la opcion sintetica "Supervisor" justo despues de "Gerente" (id_rol = 2)
    $supervisorOption = [
        'id_rol'        => -2,
        'nombre'        => 'Supervisor',
        'es_supervisor' => true
    ];

    $insertedSupervisor = false;
    $rolesConSupervisor = [];
    foreach ($roles as $rol) {
        $rolesConSupervisor[] = $rol;
        if (!$insertedSupervisor && $rol['id_rol'] === 2) {
            $rolesConSupervisor[] = $supervisorOption;
            $insertedSupervisor = true;
        }
    }
    // Si no existe el rol Gerente (id=2), agregarlo al final de todas formas
    if (!$insertedSupervisor) {
        $rolesConSupervisor[] = $supervisorOption;
    }

    echo json_encode([
        'success' => true,
        'roles'   => $rolesConSupervisor
    ]);

    $result->free();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar roles: ' . $e->getMessage(),
        'roles'   => []
    ]);
}

$conn->close();
