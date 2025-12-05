<?php
/*user_get_projects.php saber los proyectos del usuario actual */
 
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';
 
// Iniciar buffer de salida para evitar problemas con JSON
ob_start();
 
$response = [
    'success' => false,
    'message' => '',
    'proyectos' => [],
    'total' => 0
];
 
try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_department'])) {
        throw new Exception('Usuario no autenticado');
    }
 
    $id_usuario = (int)$_SESSION['user_id'];
    $id_departamento = (int)$_SESSION['user_department'];
 
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
 
    //Solo saber proyectos donde es creador o participante o miembro de grupo
    $query = "
        SELECT DISTINCT
            p.id_proyecto,
            p.nombre,
            p.descripcion,
            p.fecha_cumplimiento,
            p.progreso,
            p.estado,
            p.id_tipo_proyecto,
            p.id_participante,
            p.id_creador,
            d.nombre as area,
            u.nombre as participante_nombre,
            u.apellido as participante_apellido
        FROM tbl_proyectos p
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
        LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
        LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
        WHERE (
            p.id_creador = ?
            OR p.id_participante = ?
            OR (p.id_tipo_proyecto = 1 AND pu.id_usuario = ?)
        )
        AND p.id_departamento = ?
        ORDER BY p.fecha_cumplimiento ASC
    ";
 
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
 
    //id_usuario tres veces (creador, participante, grupo) y departamento
    $stmt->bind_param("iiii", $id_usuario, $id_usuario, $id_usuario, $id_departamento);
 
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
 
    $result = $stmt->get_result();
    $proyectos = [];
 
    while ($row = $result->fetch_assoc()) {
        // Determinar el texto del participante según el tipo de proyecto
        if ((int)$row['id_tipo_proyecto'] === 1) {
            // Proyecto grupal
            $participante_text = 'Grupo';
        } elseif ($row['participante_nombre']) {
            // Proyecto individual con participante asignado
            $participante_text = $row['participante_nombre'] . ' ' . $row['participante_apellido'];
        } else {
            // Sin asignar
            $participante_text = 'Sin asignar';
        }
 
        // Verificar si el usuario actual es participante de este proyecto
        $es_mi_proyecto = (
            (int)$row['id_participante'] === $id_usuario ||
            (int)$row['id_creador'] === $id_usuario
        );
 
        // Para proyectos grupales, verificar si el usuario está en el grupo
        if ((int)$row['id_tipo_proyecto'] === 1 && !$es_mi_proyecto) {
            $queryGrupo = "SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?";
            $stmtGrupo = $conn->prepare($queryGrupo);
            if ($stmtGrupo) {
                $stmtGrupo->bind_param("ii", $row['id_proyecto'], $id_usuario);
                $stmtGrupo->execute();
                $resultGrupo = $stmtGrupo->get_result();
                $es_mi_proyecto = $resultGrupo->num_rows > 0;
                $stmtGrupo->close();
            }
        }
 
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado'],
            'participante' => $participante_text,
            'id_participante' => (int)$row['id_participante'],
            'id_tipo_proyecto' => (int)$row['id_tipo_proyecto'],
            'id_creador' => (int)$row['id_creador'],
            'es_mi_proyecto' => $es_mi_proyecto
        ];
    }
 
    $stmt->close();
    $conn->close();
 
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['total'] = count($proyectos);
    $response['id_usuario'] = $id_usuario;
    $response['id_departamento'] = $id_departamento;
    $response['message'] = 'Proyectos cargados exitosamente';
 
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar proyectos: ' . $e->getMessage();
    error_log('user_get_projects.php Error: ' . $e->getMessage());
}
 
// Limpiar el buffer y enviar respuesta JSON
ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();
?>