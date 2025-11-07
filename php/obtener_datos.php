<?php
session_start();
require_once('db_config.php');
/*obtner datos php para creacion de proyecto*/ 
header('Content-Type: application/json');

$conn = getDBConnection();

$tipo = $_GET['tipo'] ?? '';

try {
    switch ($tipo) {
        case 'empleados':
            $sql = "SELECT id_usuario, nombre, apellido, num_empleado 
                    FROM tbl_usuarios 
                    ORDER BY nombre, apellido";
            $result = $conn->query($sql);
            
            $empleados = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $empleados[] = [
                        'id' => $row['id_usuario'],
                        'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
                        'num_empleado' => $row['num_empleado']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $empleados
            ]);
            break;
            
        case 'departamentos':
            $sql = "SELECT id_departamento, nombre 
                    FROM tbl_departamentos 
                    ORDER BY nombre";
            $result = $conn->query($sql);
            
            $departamentos = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $departamentos[] = [
                        'id' => $row['id_departamento'],
                        'nombre' => $row['nombre']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $departamentos
            ]);
            break;
            
        case 'tipos_proyecto':
            $sql = "SELECT id_tipo_proyecto, nombre, descripcion 
                    FROM tbl_tipo_proyecto 
                    ORDER BY nombre";
            $result = $conn->query($sql);
            
            $tipos = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $tipos[] = [
                        'id' => $row['id_tipo_proyecto'],
                        'nombre' => $row['nombre'],
                        'descripcion' => $row['descripcion']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $tipos
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Tipo de dato no válido'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}

$conn->close();
?>