<?
header('Content-Type: application/json'); 
session_start(); 

//revisar autenticacion

if (!isset($_SESSION['id_usuario'])) { 
    echo json_encode(['success' => false, 'message' => 'No autorizado']); 
    exit; 
} 

require_once('connection.php'); 

try { 
    $conn = getConnection(); 

    /** 
     * OPTION B: If objectives are stored as projects with a certain type 
     * This uses the existing tbl_proyectos table. 
     * Adapt the WHERE clause and column names to match your schema. 
     */ 

    $sql = "SELECT  
                p.id_proyecto, 
                p.nombre, 
                p.descripcion, 
                COALESCE( 
                    (SELECT CONCAT(u.nombre, ' ', u.apellido)  
                    FROM tbl_usuarios u  
                    WHERE u.id_usuario = p.id_participante), 
                    'Sin asignar' 
                ) AS responsable, 
                COALESCE(p.progreso, 0) AS progreso, 
                COALESCE(p.estado, 'pendiente') AS estado, 
                p.fecha_cumplimiento, 

                CASE  
                    WHEN p.id_tipo_proyecto = 1 THEN 'Global Objectives' 
                    WHEN p.id_tipo_proyecto = 2 THEN 'Regional Objectives' 
                    ELSE 'Global Objectives' 
                END AS tipo, 
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) AS total_tareas, 
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND LOWER(t.estado) = 'completado') AS tareas_completadas 
            FROM tbl_proyectos p 
            WHERE p.id_tipo_proyecto IS NOT NULL 
            ORDER BY p.progreso DESC"; 
    $result = $conn->query($sql); 

    if (!$result) { 
        throw new Exception('Error en consulta: ' . $conn->error); 
    } 
    $objetivos = []; 
    while ($row = $result->fetch_assoc()) { 
        $objetivos[] = [ 
            'id'                 => $row['id_proyecto'], 
            'nombre'             => $row['nombre'], 
            'descripcion'        => $row['descripcion'] ?? '', 
            'responsable'        => $row['responsable'], 
            'progreso'           => (int)$row['progreso'], 
            'estado'             => $row['estado'], 
            'fecha_cumplimiento' => $row['fecha_cumplimiento'] ?? null, 
            'tipo'               => $row['tipo'], 
            'total_tareas'       => (int)$row['total_tareas'], 
            'tareas_completadas' => (int)$row['tareas_completadas'] 
        ]; 
    } 

    echo json_encode([ 
        'success'   => true, 
        'objetivos' => $objetivos, 
        'total'     => count($objetivos) 
    ]); 
    $conn->close(); 

} catch (Exception $e) { 
    echo json_encode([ 
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage() 
    ]); 
} 
?> 