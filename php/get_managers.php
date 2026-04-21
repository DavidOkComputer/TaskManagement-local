<?php
// get_managers.php Devuelve la lista de usuarios con rol de gerente id_rol = 2

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer para evitar que warnings contaminen el JSON
ob_start();

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    $conn->set_charset('utf8mb4');

    // Leer parametros
    $es_supervisor = isset($_GET['es_supervisor'])
        ? (intval($_GET['es_supervisor']) === 1 ? 1 : 0)
        : 0;

    $filter_departamento = isset($_GET['id_departamento'])
        ? intval($_GET['id_departamento'])
        : null;

    $exclude_user = isset($_GET['exclude_user'])
        ? intval($_GET['exclude_user'])
        : null;

    // Verificar que la columna es_supervisor exista
    $checkColumn = $conn->query("SHOW COLUMNS FROM tbl_usuarios LIKE 'es_supervisor'");
    $hasSupervisorColumn = $checkColumn && $checkColumn->num_rows > 0;
    if ($checkColumn) {
        $checkColumn->free();
    }
    if (!$hasSupervisorColumn) {
        throw new Exception('La columna es_supervisor no existe en tbl_usuarios. Ejecute migration_es_supervisor.sql primero.');
    }

    // Verificar si la columna foto_perfil existe
    $checkFoto = $conn->query("SHOW COLUMNS FROM tbl_usuarios LIKE 'foto_perfil'");
    $hasFotoColumn = $checkFoto && $checkFoto->num_rows > 0;
    if ($checkFoto) {
        $checkFoto->free();
    }
    $fotoField = $hasFotoColumn ? ", u.foto_perfil" : "";

    // Query base es usuarios con rol de gerente activo
    $query = "SELECT DISTINCT
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.num_empleado,
                u.e_mail,
                u.es_supervisor,
                ur.id_departamento,
                d.nombre AS nombre_departamento
                {$fotoField}
              FROM tbl_usuarios u
              INNER JOIN tbl_usuario_roles ur
                  ON u.id_usuario = ur.id_usuario
                  AND ur.activo = 1
                  AND ur.id_rol = 2
              LEFT JOIN tbl_departamentos d
                  ON ur.id_departamento = d.id_departamento
              WHERE u.es_supervisor = ?";

    $params = [$es_supervisor];
    $types  = "i";

    if ($filter_departamento !== null && $filter_departamento > 0) {
        $query .= " AND ur.id_departamento = ?";
        $params[] = $filter_departamento;
        $types   .= "i";
    }

    if ($exclude_user !== null && $exclude_user > 0) {
        $query .= " AND u.id_usuario != ?";
        $params[] = $exclude_user;
        $types   .= "i";
    }

    $query .= " ORDER BY u.apellido ASC, u.nombre ASC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $managers = [];
    while ($row = $result->fetch_assoc()) {
        $nombreCompleto = $row['nombre'] . ' ' . $row['apellido'];
        $esSupervisorRow = ((int)$row['es_supervisor'] === 1);
        $labelSuffix = $esSupervisorRow ? ' (Supervisor)' : '';

        $manager = [
            'id_usuario'          => (int)$row['id_usuario'],
            'nombre'              => $row['nombre'],
            'apellido'            => $row['apellido'],
            'nombre_completo'     => $nombreCompleto,
            'nombre_etiqueta'     => $nombreCompleto . $labelSuffix,
            'num_empleado'        => (int)$row['num_empleado'],
            'e_mail'              => $row['e_mail'],
            'es_supervisor'       => $esSupervisorRow,
            'id_departamento'     => (int)$row['id_departamento'],
            'nombre_departamento' => $row['nombre_departamento'] ?? 'Sin departamento'
        ];

        // Agregar informacion de foto de perfil
        if ($hasFotoColumn && isset($row['foto_perfil']) && !empty($row['foto_perfil'])) {
            $manager['foto_perfil']    = $row['foto_perfil'];
            $manager['foto_url']       = 'uploads/profile_pictures/' . $row['foto_perfil'];
            $manager['foto_thumbnail'] = 'uploads/profile_pictures/thumbnails/thumb_' . $row['foto_perfil'];
        } else {
            $manager['foto_perfil']    = null;
            $manager['foto_url']       = null;
            $manager['foto_thumbnail'] = null;
        }

        $managers[] = $manager;
    }

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'         => true,
        'managers'        => $managers,
        'total'           => count($managers),
        'filtro_aplicado' => [
            'es_supervisor'   => $es_supervisor,
            'id_departamento' => $filter_departamento,
            'exclude_user'    => $exclude_user
        ]
    ], JSON_UNESCAPED_UNICODE);

    $result->free();
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'  => false,
        'message'  => 'Error al cargar gerentes: ' . $e->getMessage(),
        'managers' => []
    ], JSON_UNESCAPED_UNICODE);
    error_log('get_managers.php Error: ' . $e->getMessage());
}
