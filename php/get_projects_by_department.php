<?php
/* get_projects_by_department.php  para saber los proyectos filtrados por departamento */
header('Content-Type: application/json');
require_once 'db_config.php';
 
error_reporting(E_ALL);
ini_set('display_errors', 0);
 
$response = ['success' => false, 'proyectos' => []];
 
try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
 
    // Validar que se recibió el ID del departamento
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) {
        throw new Exception('El ID del departamento es requerido');
    }
 
    $id_departamento = intval($_GET['id_departamento']);
    
    if ($id_departamento <= 0) {
        throw new Exception('El ID del departamento no es válido');
    }
 
    // Consulta para obtener proyectos del departamento específico
    $query = "SELECT p.id_proyecto,
                     p.nombre,
                     p.descripcion,
                     p.fecha_cumplimiento,
                     p.progreso,
                     p.estado,
                     p.id_tipo_proyecto,
                     p.id_departamento,
                     d.nombre as area,
                     u.nombre as participante_nombre,
                     u.apellido as participante_apellido,
                     p.id_participante
              FROM tbl_proyectos p
              LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
              LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
              WHERE p.id_departamento = ?
              ORDER BY p.fecha_cumplimiento ASC";
 
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
 
    $stmt->bind_param("i", $id_departamento);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
 
    $result = $stmt->get_result();
    $proyectos = [];
 
    while ($row = $result->fetch_assoc()) {
        // Determinar qué tipo de participante mostrar basado en el tipo de proyecto
        // id_tipo_proyecto = 1 grupo, 2 individual
        if ((int)$row['id_tipo_proyecto'] === 1) {
            $participante_text = 'Grupo';
        } elseif ($row['participante_nombre']) {
            $participante_text = $row['participante_nombre'] . ' ' . $row['participante_apellido'];
        } else {
            $participante_text = 'Sin asignar';
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
            'id_departamento' => (int)$row['id_departamento']
        ];
    }
 
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['total'] = count($proyectos);
    $response['id_departamento'] = $id_departamento;
 
    $result->free();
    $stmt->close();
 
} catch (Exception $e) {
    $response['message'] = 'Error al cargar proyectos del departamento: ' . $e->getMessage();
    error_log('get_projects_by_department.php Error: ' . $e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
 
echo json_encode($response);
?>