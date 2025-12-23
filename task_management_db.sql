-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 23, 2025 at 03:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `task_management_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_asignar_rol_usuario` (IN `p_id_usuario` INT, IN `p_id_departamento` INT, IN `p_id_rol` INT, IN `p_es_principal` TINYINT)   BEGIN
    DECLARE v_exists INT DEFAULT 0;
    
    -- Check if assignment already exists
    SELECT COUNT(*) INTO v_exists
    FROM `tbl_usuario_roles`
    WHERE id_usuario = p_id_usuario 
      AND id_departamento = p_id_departamento;
    
    IF v_exists > 0 THEN
        -- Update existing assignment
        UPDATE `tbl_usuario_roles`
        SET id_rol = p_id_rol,
            es_principal = p_es_principal,
            activo = 1
        WHERE id_usuario = p_id_usuario 
          AND id_departamento = p_id_departamento;
    ELSE
        -- If setting as principal, remove principal flag from other assignments
        IF p_es_principal = 1 THEN
            UPDATE `tbl_usuario_roles`
            SET es_principal = 0
            WHERE id_usuario = p_id_usuario;
        END IF;
        
        -- Insert new assignment
        INSERT INTO `tbl_usuario_roles` 
            (id_usuario, id_departamento, id_rol, es_principal, activo)
        VALUES 
            (p_id_usuario, p_id_departamento, p_id_rol, p_es_principal, 1);
    END IF;
    
    SELECT 'Rol asignado correctamente' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_establecer_rol_principal` (IN `p_id_usuario` INT, IN `p_id_departamento` INT)   BEGIN
    -- Remove principal flag from all user's assignments
    UPDATE `tbl_usuario_roles`
    SET es_principal = 0
    WHERE id_usuario = p_id_usuario;
    
    -- Set the specified assignment as principal
    UPDATE `tbl_usuario_roles`
    SET es_principal = 1
    WHERE id_usuario = p_id_usuario 
      AND id_departamento = p_id_departamento;
    
    SELECT 'Rol principal actualizado' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_roles_usuario` (IN `p_id_usuario` INT)   BEGIN
    SELECT 
        ur.id,
        ur.id_departamento,
        d.nombre AS departamento,
        ur.id_rol,
        r.nombre AS rol,
        ur.es_principal,
        ur.activo
    FROM `tbl_usuario_roles` ur
    JOIN `tbl_departamentos` d ON ur.id_departamento = d.id_departamento
    JOIN `tbl_roles` r ON ur.id_rol = r.id_rol
    WHERE ur.id_usuario = p_id_usuario
      AND ur.activo = 1
    ORDER BY ur.es_principal DESC, d.nombre;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_departamentos`
--

CREATE TABLE `tbl_departamentos` (
  `id_departamento` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `id_creador` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_departamentos`
--

INSERT INTO `tbl_departamentos` (`id_departamento`, `nombre`, `descripcion`, `id_creador`) VALUES
(1, 'IT', 'Departamento de tecnologias de la información y soluciones tecnológicas', 1),
(6, 'Departamento de recursos humanos', 'Departamento encargado de la gestion de recursos humanos y relacionaods', 1),
(8, 'Desarrollo de soluciones tecnologicas', 'Especializado en el desarrollo de soluciones tecnologicas', 1),
(9, 'Seguridad', 'Descripcion de departamento de seguridad', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_config`
--

CREATE TABLE `tbl_email_config` (
  `id_config` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_log`
--

CREATE TABLE `tbl_email_log` (
  `id_log` int(11) NOT NULL,
  `id_email` int(11) DEFAULT NULL,
  `evento` enum('queued','processing','sent','failed','opened','clicked','bounced') NOT NULL,
  `detalle` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_email_log`
--

INSERT INTO `tbl_email_log` (`id_log`, `id_email`, `evento`, `detalle`, `ip_address`, `user_agent`, `fecha_creacion`) VALUES
(1, 1, 'queued', 'Email en cola para: francisco.valdez@nidec.com', NULL, NULL, '2025-12-10 01:25:48');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_queue`
--

CREATE TABLE `tbl_email_queue` (
  `id_email` int(11) NOT NULL,
  `destinatario_email` varchar(255) NOT NULL,
  `destinatario_nombre` varchar(255) DEFAULT NULL,
  `asunto` varchar(255) NOT NULL,
  `cuerpo_html` text NOT NULL,
  `cuerpo_texto` text DEFAULT NULL,
  `tipo_notificacion` enum('tarea_asignada','tarea_vencimiento','tarea_vencida','tarea_completada','proyecto_asignado','proyecto_completado','objetivo_asignado','recordatorio_diario','resumen_semanal','prueba') NOT NULL,
  `prioridad` tinyint(4) DEFAULT 5 COMMENT '1=más alta, 10=más baja',
  `estado` enum('pendiente','enviado','fallido','cancelado') DEFAULT 'pendiente',
  `intentos` int(11) DEFAULT 0,
  `max_intentos` int(11) DEFAULT 3,
  `ultimo_error` text DEFAULT NULL,
  `referencia_tipo` enum('tarea','proyecto','objetivo','usuario') DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `programado_para` datetime DEFAULT current_timestamp(),
  `enviado_at` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_email_queue`
--

INSERT INTO `tbl_email_queue` (`id_email`, `destinatario_email`, `destinatario_nombre`, `asunto`, `cuerpo_html`, `cuerpo_texto`, `tipo_notificacion`, `prioridad`, `estado`, `intentos`, `max_intentos`, `ultimo_error`, `referencia_tipo`, `referencia_id`, `programado_para`, `enviado_at`, `fecha_creacion`) VALUES
(1, 'francisco.valdez@nidec.com', 'Francisco Valdez', '📋 Nueva tarea asignada: de nuevo otra prueba de tarea despue de notificacion', '\r\n<!DOCTYPE html>\r\n<html lang=\"es\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n    <title>Nueva tarea asignada: de nuevo otra prueba de tarea despue de notificacion</title>\r\n    <style>\r\n        * {\r\n            margin: 0;\r\n            padding: 0;\r\n            box-sizing: border-box;\r\n        }\r\n        body { \r\n            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n            line-height: 1.6;\r\n            color: #333333;\r\n            background-color: #f5f5f5;\r\n            padding: 20px;\r\n        }\r\n        .email-wrapper {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n        }\r\n        .email-container {\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n        }\r\n        .header {\r\n            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);\r\n            color: #ffffff;\r\n            padding: 25px 30px;\r\n            text-align: center;\r\n        }\r\n        .header h1 {\r\n            margin: 0;\r\n            font-size: 22px;\r\n            font-weight: 600;\r\n        }\r\n        .header .subtitle {\r\n            font-size: 14px;\r\n            opacity: 0.9;\r\n            margin-top: 5px;\r\n        }\r\n        .content {\r\n            padding: 30px;\r\n        }\r\n        .greeting {\r\n            font-size: 18px;\r\n            margin-bottom: 20px;\r\n        }\r\n        .task-card {\r\n            background: #f8f9fa;\r\n            border-left: 4px solid #4CAF50;\r\n            padding: 20px;\r\n            margin: 20px 0;\r\n            border-radius: 0 8px 8px 0;\r\n        }\r\n        .task-card.urgent {\r\n            border-left-color: #f44336;\r\n            background: #fff5f5;\r\n        }\r\n        .task-card.warning {\r\n            border-left-color: #ff9800;\r\n            background: #fff8e1;\r\n        }\r\n        .task-card h3 {\r\n            margin: 0 0 10px 0;\r\n            color: #333;\r\n            font-size: 18px;\r\n        }\r\n        .task-card p {\r\n            margin: 0 0 10px 0;\r\n            color: #666;\r\n        }\r\n        .meta-info {\r\n            font-size: 14px;\r\n            color: #666;\r\n        }\r\n        .meta-info strong {\r\n            color: #333;\r\n        }\r\n        .btn {\r\n            display: inline-block;\r\n            padding: 12px 28px;\r\n            background-color: #4CAF50;\r\n            color: #ffffff !important;\r\n            text-decoration: none;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            font-weight: 500;\r\n            font-size: 14px;\r\n        }\r\n        .btn:hover {\r\n            background-color: #45a049;\r\n        }\r\n        .btn-center {\r\n            text-align: center;\r\n            margin: 25px 0;\r\n        }\r\n        .stats-container {\r\n            display: table;\r\n            width: 100%;\r\n            margin: 20px 0;\r\n        }\r\n        .stat-box {\r\n            display: table-cell;\r\n            width: 33.33%;\r\n            padding: 15px;\r\n            text-align: center;\r\n            border-radius: 8px;\r\n        }\r\n        .stat-box.success { background: #e8f5e9; }\r\n        .stat-box.warning { background: #fff3e0; }\r\n        .stat-box.danger { background: #ffebee; }\r\n        .stat-number {\r\n            font-size: 32px;\r\n            font-weight: bold;\r\n            display: block;\r\n        }\r\n        .stat-box.success .stat-number { color: #4CAF50; }\r\n        .stat-box.warning .stat-number { color: #ff9800; }\r\n        .stat-box.danger .stat-number { color: #f44336; }\r\n        .stat-label {\r\n            font-size: 12px;\r\n            color: #666;\r\n            text-transform: uppercase;\r\n        }\r\n        .footer {\r\n            margin-top: 30px;\r\n            padding: 20px 30px;\r\n            background: #f8f9fa;\r\n            border-top: 1px solid #eee;\r\n            text-align: center;\r\n            font-size: 12px;\r\n            color: #999;\r\n        }\r\n        .footer a {\r\n            color: #4CAF50;\r\n            text-decoration: none;\r\n        }\r\n        .divider {\r\n            height: 1px;\r\n            background: #eee;\r\n            margin: 20px 0;\r\n        }\r\n        .upcoming-list {\r\n            margin: 15px 0;\r\n            padding: 0;\r\n            list-style: none;\r\n        }\r\n        .upcoming-list li {\r\n            padding: 10px 0;\r\n            border-bottom: 1px solid #eee;\r\n        }\r\n        .upcoming-list li:last-child {\r\n            border-bottom: none;\r\n        }\r\n        .date-badge {\r\n            display: inline-block;\r\n            background: #e3f2fd;\r\n            color: #1976D2;\r\n            padding: 2px 8px;\r\n            border-radius: 4px;\r\n            font-size: 12px;\r\n            margin-left: 10px;\r\n        }\r\n        @media only screen and (max-width: 600px) {\r\n            .content { padding: 20px; }\r\n            .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n        }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-wrapper\">\r\n        <div class=\"email-container\">\r\n            <div class=\"header\">\r\n                <h1>Sistema de Gestión de Tareas</h1>\r\n                <div class=\"subtitle\">Nueva tarea asignada</div>\r\n            </div>\r\n            <div class=\"content\">\r\n                \r\n            <p class=\"greeting\">Hola <strong>Francisco</strong>,</p>\r\n            <p>Se te ha asignado una nueva tarea en el sistema:</p>\r\n            \r\n            <div class=\"task-card\">\r\n                <h3>📋 de nuevo otra prueba de tarea despue de notificacion</h3>\r\n                <p>descripcion de denuevo otra prueba de tarea despues de notificacion</p>\r\n                <div class=\"divider\"></div>\r\n                <div class=\"meta-info\">\r\n                    <p><strong>Proyecto:</strong> Prueba de creacion de proyectos con edicion restringida</p>\r\n                    <p><strong>Fecha límite:</strong> 11/12/2025</p>\r\n                    <p><strong>Asignado por:</strong> David Barreto</p>\r\n                </div>\r\n            </div>\r\n            \r\n            <div class=\"btn-center\">\r\n                <a href=\"http://localhost/task_management\" class=\"btn\">Ver Tarea</a>\r\n            </div>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>Este es un mensaje automático del Sistema de Gestión de Tareas.</p>\r\n                <p>Por favor no responda directamente a este correo.</p>\r\n                <p style=\"margin-top: 10px;\">\r\n                    <a href=\"http://localhost/task_management\">Acceder al Sistema</a>\r\n                </p>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Nueva tarea asignada: de nuevo otra prueba de tarea despue de notificacion\r\n \r\n * {\r\n margin: 0;\r\n padding: 0;\r\n box-sizing: border-box;\r\n }\r\n body { \r\n font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n line-height: 1.6;\r\n color: #333333;\r\n background-color: #f5f5f5;\r\n padding: 20px;\r\n }\r\n .email-wrapper {\r\n max-width: 600px;\r\n margin: 0 auto;\r\n }\r\n .email-container {\r\n background: #ffffff;\r\n border-radius: 8px;\r\n overflow: hidden;\r\n box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n }\r\n .header {\r\n background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);\r\n color: #ffffff;\r\n padding: 25px 30px;\r\n text-align: center;\r\n }\r\n .header h1 {\r\n margin: 0;\r\n font-size: 22px;\r\n font-weight: 600;\r\n }\r\n .header .subtitle {\r\n font-size: 14px;\r\n opacity: 0.9;\r\n margin-top: 5px;\r\n }\r\n .content {\r\n padding: 30px;\r\n }\r\n .greeting {\r\n font-size: 18px;\r\n margin-bottom: 20px;\r\n }\r\n .task-card {\r\n background: #f8f9fa;\r\n border-left: 4px solid #4CAF50;\r\n padding: 20px;\r\n margin: 20px 0;\r\n border-radius: 0 8px 8px 0;\r\n }\r\n .task-card.urgent {\r\n border-left-color: #f44336;\r\n background: #fff5f5;\r\n }\r\n .task-card.warning {\r\n border-left-color: #ff9800;\r\n background: #fff8e1;\r\n }\r\n .task-card h3 {\r\n margin: 0 0 10px 0;\r\n color: #333;\r\n font-size: 18px;\r\n }\r\n .task-card p {\r\n margin: 0 0 10px 0;\r\n color: #666;\r\n }\r\n .meta-info {\r\n font-size: 14px;\r\n color: #666;\r\n }\r\n .meta-info strong {\r\n color: #333;\r\n }\r\n .btn {\r\n display: inline-block;\r\n padding: 12px 28px;\r\n background-color: #4CAF50;\r\n color: #ffffff !important;\r\n text-decoration: none;\r\n border-radius: 6px;\r\n margin-top: 20px;\r\n font-weight: 500;\r\n font-size: 14px;\r\n }\r\n .btn:hover {\r\n background-color: #45a049;\r\n }\r\n .btn-center {\r\n text-align: center;\r\n margin: 25px 0;\r\n }\r\n .stats-container {\r\n display: table;\r\n width: 100%;\r\n margin: 20px 0;\r\n }\r\n .stat-box {\r\n display: table-cell;\r\n width: 33.33%;\r\n padding: 15px;\r\n text-align: center;\r\n border-radius: 8px;\r\n }\r\n .stat-box.success { background: #e8f5e9; }\r\n .stat-box.warning { background: #fff3e0; }\r\n .stat-box.danger { background: #ffebee; }\r\n .stat-number {\r\n font-size: 32px;\r\n font-weight: bold;\r\n display: block;\r\n }\r\n .stat-box.success .stat-number { color: #4CAF50; }\r\n .stat-box.warning .stat-number { color: #ff9800; }\r\n .stat-box.danger .stat-number { color: #f44336; }\r\n .stat-label {\r\n font-size: 12px;\r\n color: #666;\r\n text-transform: uppercase;\r\n }\r\n .footer {\r\n margin-top: 30px;\r\n padding: 20px 30px;\r\n background: #f8f9fa;\r\n border-top: 1px solid #eee;\r\n text-align: center;\r\n font-size: 12px;\r\n color: #999;\r\n }\r\n .footer a {\r\n color: #4CAF50;\r\n text-decoration: none;\r\n }\r\n .divider {\r\n height: 1px;\r\n background: #eee;\r\n margin: 20px 0;\r\n }\r\n .upcoming-list {\r\n margin: 15px 0;\r\n padding: 0;\r\n list-style: none;\r\n }\r\n .upcoming-list li {\r\n padding: 10px 0;\r\n border-bottom: 1px solid #eee;\r\n }\r\n .upcoming-list li:last-child {\r\n border-bottom: none;\r\n }\r\n .date-badge {\r\n display: inline-block;\r\n background: #e3f2fd;\r\n color: #1976D2;\r\n padding: 2px 8px;\r\n border-radius: 4px;\r\n font-size: 12px;\r\n margin-left: 10px;\r\n }\r\n @media only screen and (max-width: 600px) {\r\n .content { padding: 20px; }\r\n .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n }\r\n \r\n\r\n\r\n \r\n \r\n \r\n Sistema de Gestión de Tareas\r\n Nueva tarea asignada\n\n\r\n \n\n\r\n \r\n \r\n Hola Francisco,\n\n\r\n Se te ha asignado una nueva tarea en el sistema:\n\n\r\n \r\n \r\n 📋 de nuevo otra prueba de tarea despue de notificacion\r\n descripcion de denuevo otra prueba de tarea despues de notificacion\n\n\r\n \n\n\r\n \r\n Proyecto: Prueba de creacion de proyectos con edicion restringida\n\n\r\n Fecha límite: 11/12/2025\n\n\r\n Asignado por: David Barreto\n\n\r\n \n\n\r\n \n\n\r\n \r\n \r\n Ver Tarea\r\n \n\n\r\n \n\n\r\n \r\n Este es un mensaje automático del Sistema de Gestión de Tareas.\n\n\r\n Por favor no responda directamente a este correo.\n\n\r\n \r\n Acceder al Sistema', 'tarea_asignada', 2, 'pendiente', 0, 3, NULL, 'tarea', 40, '2025-12-09 20:25:48', NULL, '2025-12-10 01:25:48');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notificaciones`
--

CREATE TABLE `tbl_notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Usuario que recibe la notificación',
  `tipo` enum('tarea_asignada','proyecto_asignado','proyecto_vencido','tarea_vencida','inactividad_proyecto','inactividad_tarea') NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `id_referencia` int(11) DEFAULT NULL COMMENT 'ID del proyecto/tarea relacionado',
  `tipo_referencia` enum('proyecto','tarea','objetivo') DEFAULT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_lectura` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_notificaciones`
--

INSERT INTO `tbl_notificaciones` (`id_notificacion`, `id_usuario`, `tipo`, `titulo`, `mensaje`, `id_referencia`, `tipo_referencia`, `leido`, `fecha_creacion`, `fecha_lectura`) VALUES
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Proyecto desarrollo de inteligencia artificial\' ha superado su fecha de entrega.', 1, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'bbbbbbbbbbbbbb\' ha superado su fecha de entrega.', 4, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de vencimiento de proyecto\' ha superado su fecha de entrega.', 9, 'proyecto', 0, '2025-12-08 22:00:43', NULL);
INSERT INTO `tbl_notificaciones` (`id_notificacion`, `id_usuario`, `tipo`, `titulo`, `mensaje`, `id_referencia`, `tipo_referencia`, `leido`, `fecha_creacion`, `fecha_lectura`) VALUES
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de index para proyecto\' ha superado su fecha de entrega.', 10, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Prueba de creacion de proyectos con edicion restringida\' ha superado su fecha de entrega.', 11, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 6, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'Ingreso de robots a planta\' ha superado su fecha de entrega.', 14, 'proyecto', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Desarrollo de conciencia\' ha superado su fecha de entrega.', 12, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 1, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 1, '2025-12-08 22:00:43', '2025-12-09 01:39:59'),
(0, 3, 'tarea_vencida', 'Tarea vencida', 'La tarea \'Segunda tarea de prueba para vencimiento de proyecto\' ha superado su fecha de entrega.', 19, 'tarea', 0, '2025-12-08 22:00:43', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 17 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 17 días pendiente sin cambios.', 21, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 1, '2025-12-08 22:04:08', '2025-12-09 01:39:59'),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 12 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-08 22:04:08', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de notificacion de tostada\' en el proyecto \'prueba\'.', 31, 'tarea', 0, '2025-12-09 20:36:32', NULL),
(0, 6, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de fecha de tarea\' en el proyecto \'Ingreso de robots a planta\'.', 32, 'tarea', 0, '2025-12-09 20:45:15', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'a\' en el proyecto \'prueba\'.', 33, 'tarea', 0, '2025-12-09 21:08:47', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha de vencimiento adecuada\' en el proyecto \'prueba\'.', 34, 'tarea', 0, '2025-12-09 21:50:39', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de tarea con fecha buena\' en el proyecto \'prueba\'.', 35, 'tarea', 0, '2025-12-09 21:52:41', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Prueba de creacion de tarea con fecha correspondiente\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 36, 'tarea', 0, '2025-12-09 23:54:59', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva prueba de fecha\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 37, 'tarea', 0, '2025-12-10 00:07:38', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'prueba de creacion de tarea despues de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 38, 'tarea', 0, '2025-12-10 01:22:06', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'nueva pruebva de tarea\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 39, 'tarea', 0, '2025-12-10 01:23:57', NULL),
(0, 3, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'de nuevo otra prueba de tarea despue de notificacion\' en el proyecto \'Prueba de creacion de proyectos con edicion restringida\'.', 40, 'tarea', 0, '2025-12-10 01:25:48', NULL),
(0, 1, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 3, 'proyecto_vencido', 'Proyecto vencido', 'El proyecto \'prueba\' ha superado su fecha de entrega.', 8, 'proyecto', 0, '2025-12-11 01:57:39', NULL),
(0, 7, 'inactividad_proyecto', 'Proyecto sin actividad', 'El proyecto \'prueba de proyecto para usuario normal\' lleva 11 días sin actividad.', 18, 'proyecto', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_proyecto', 'Proyecto sin actividad', 'El proyecto \'prueba de creacion de proyecto despues de notificacion\' lleva 12 días sin actividad.', 19, 'proyecto', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_proyecto', 'Proyecto sin actividad', 'El proyecto \'prueba de creacion de proyecto despues de notificacion\' lleva 12 días sin actividad.', 20, 'proyecto', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_proyecto', 'Proyecto sin actividad', 'El proyecto \'Prueba de creacion de proyecto como usuario\' lleva 10 días sin actividad.', 21, 'proyecto', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de red neuronal\' lleva 13 días pendiente sin cambios.', 2, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'ejemplo\' lleva 13 días pendiente sin cambios.', 7, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de automatizacion\' lleva 13 días pendiente sin cambios.', 8, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de presentacion de informacin\' lleva 13 días pendiente sin cambios.', 9, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Desarrollo de procesamiento eficiente\' lleva 13 días pendiente sin cambios.', 10, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 13 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de asignacion de tarea con acceso restringido\' lleva 13 días pendiente sin cambios.', 20, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de fecha de tarea\' lleva 13 días pendiente sin cambios.', 21, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 13 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de complecion de tarea\' lleva 13 días pendiente sin cambios.', 27, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Path finding para robots\' lleva 13 días pendiente sin cambios.', 28, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 6, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Path finding para robots\' lleva 13 días pendiente sin cambios.', 28, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 7, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Completacion de prueba\' lleva 16 días pendiente sin cambios.', 30, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'prueba de notificacion de tostada\' lleva 13 días pendiente sin cambios.', 31, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'prueba de notificacion de tostada\' lleva 13 días pendiente sin cambios.', 31, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'prueba de fecha de tarea\' lleva 13 días pendiente sin cambios.', 32, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 6, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'prueba de fecha de tarea\' lleva 13 días pendiente sin cambios.', 32, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'a\' lleva 13 días pendiente sin cambios.', 33, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'a\' lleva 13 días pendiente sin cambios.', 33, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de tarea con fecha de vencimiento adecuada\' lleva 13 días pendiente sin cambios.', 34, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de tarea con fecha de vencimiento adecuada\' lleva 13 días pendiente sin cambios.', 34, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de tarea con fecha buena\' lleva 13 días pendiente sin cambios.', 35, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de tarea con fecha buena\' lleva 13 días pendiente sin cambios.', 35, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de actualizacion de tareas nuevas\' lleva 13 días pendiente sin cambios.', 36, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'Prueba de actualizacion de tareas nuevas\' lleva 13 días pendiente sin cambios.', 36, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'prueba de creacion de tarea despues de notificacion\' lleva 13 días pendiente sin cambios.', 38, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'prueba de creacion de tarea despues de notificacion\' lleva 13 días pendiente sin cambios.', 38, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'nueva pruebva de tarea\' lleva 13 días pendiente sin cambios.', 39, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'nueva pruebva de tarea\' lleva 13 días pendiente sin cambios.', 39, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 1, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'de nuevo otra prueba de tarea despue de notificacion\' lleva 13 días pendiente sin cambios.', 40, 'tarea', 0, '2025-12-22 14:00:01', NULL),
(0, 3, 'inactividad_tarea', 'Tarea pendiente sin actividad', 'La tarea \'de nuevo otra prueba de tarea despue de notificacion\' lleva 13 días pendiente sin cambios.', 40, 'tarea', 0, '2025-12-22 14:00:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notificaciones_config`
--

CREATE TABLE `tbl_notificaciones_config` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `notif_tarea_asignada` tinyint(1) NOT NULL DEFAULT 1,
  `notif_proyecto_asignado` tinyint(1) NOT NULL DEFAULT 1,
  `notif_vencimiento` tinyint(1) NOT NULL DEFAULT 1,
  `notif_inactividad` tinyint(1) NOT NULL DEFAULT 1,
  `dias_aviso_vencimiento` int(11) NOT NULL DEFAULT 3,
  `dias_inactividad` int(11) NOT NULL DEFAULT 7
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notificaciones_enviadas`
--

CREATE TABLE `tbl_notificaciones_enviadas` (
  `id` int(11) NOT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `id_referencia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_notificaciones_enviadas`
--

INSERT INTO `tbl_notificaciones_enviadas` (`id`, `tipo_evento`, `id_referencia`, `id_usuario`, `fecha_envio`) VALUES
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 1, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 4, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 9, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 10, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 11, 3, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 1, '2025-12-08 22:00:43'),
(0, 'proyecto_vencido', 14, 6, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 12, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 1, '2025-12-08 22:00:43'),
(0, 'tarea_vencida', 19, 3, '2025-12-08 22:00:43'),
(0, 'inactividad_tarea_50', 2, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 7, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 8, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 9, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 10, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 20, 3, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 21, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 1, '2025-12-08 22:04:08'),
(0, 'inactividad_tarea_50', 27, 3, '2025-12-08 22:04:08'),
(0, 'proyecto_vencido', 8, 1, '2025-12-11 01:57:39'),
(0, 'proyecto_vencido', 8, 3, '2025-12-11 01:57:39'),
(0, 'inactividad_proyecto_52', 18, 7, '2025-12-22 14:00:01'),
(0, 'inactividad_proyecto_52', 19, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_proyecto_52', 20, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_proyecto_52', 21, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 2, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 7, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 8, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 9, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 10, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 20, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 20, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 21, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 27, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 27, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 28, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 28, 6, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 30, 7, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 31, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 31, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 32, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 32, 6, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 33, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 33, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 34, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 34, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 35, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 35, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 36, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 36, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 38, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 38, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 39, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 39, 3, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 40, 1, '2025-12-22 14:00:01'),
(0, 'inactividad_tarea_52', 40, 3, '2025-12-22 14:00:01');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notificacion_preferencias`
--

CREATE TABLE `tbl_notificacion_preferencias` (
  `id_preferencia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `notif_tarea_asignada` tinyint(1) DEFAULT 1 COMMENT 'Notificar cuando se asigna una tarea',
  `notif_tarea_vencimiento` tinyint(1) DEFAULT 1 COMMENT 'Notificar antes del vencimiento',
  `notif_tarea_vencida` tinyint(1) DEFAULT 1 COMMENT 'Notificar tareas vencidas',
  `notif_tarea_completada` tinyint(1) DEFAULT 1 COMMENT 'Notificar cuando se completa una tarea',
  `notif_proyecto_asignado` tinyint(1) DEFAULT 1 COMMENT 'Notificar asignación a proyecto',
  `notif_resumen_diario` tinyint(1) DEFAULT 0 COMMENT 'Recibir resumen diario',
  `notif_resumen_semanal` tinyint(1) DEFAULT 1 COMMENT 'Recibir resumen semanal',
  `hora_preferida` time DEFAULT '09:00:00' COMMENT 'Hora preferida para recibir notificaciones',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_notificacion_preferencias`
--

INSERT INTO `tbl_notificacion_preferencias` (`id_preferencia`, `id_usuario`, `notif_tarea_asignada`, `notif_tarea_vencimiento`, `notif_tarea_vencida`, `notif_tarea_completada`, `notif_proyecto_asignado`, `notif_resumen_diario`, `notif_resumen_semanal`, `hora_preferida`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, 1, 1, 1, 1, 0, 1, '09:00:00', '2025-12-08 21:14:46', '2025-12-08 21:14:46'),
(2, 3, 1, 1, 1, 1, 1, 0, 1, '09:00:00', '2025-12-08 21:14:46', '2025-12-08 21:14:46'),
(3, 6, 1, 1, 1, 1, 1, 0, 1, '09:00:00', '2025-12-08 21:14:46', '2025-12-08 21:14:46'),
(4, 7, 1, 1, 1, 1, 1, 0, 1, '09:00:00', '2025-12-08 21:14:46', '2025-12-08 21:14:46'),
(5, 8, 1, 1, 1, 1, 1, 0, 1, '09:00:00', '2025-12-08 21:14:46', '2025-12-08 21:14:46');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_objetivos`
--

CREATE TABLE `tbl_objetivos` (
  `id_objetivo` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `id_departamento` int(11) NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_cumplimiento` date NOT NULL,
  `estado` enum('pendiente','en proceso','vencido','completado') NOT NULL,
  `ar` varbinary(200) NOT NULL,
  `archivo_adjunto` varchar(300) NOT NULL,
  `id_creador` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_objetivos`
--

INSERT INTO `tbl_objetivos` (`id_objetivo`, `nombre`, `descripcion`, `id_departamento`, `fecha_inicio`, `fecha_cumplimiento`, `estado`, `ar`, `archivo_adjunto`, `id_creador`, `fecha_creacion`) VALUES
(1, 'Desarrollo de sistema de tareas', 'Desarrollo e implementación de aplicación web para la creación y manejo de proyectos', 1, '2025-11-07 11:43:37', '2025-12-15', 'pendiente', 0x31323334353637383839, '../uploads/objetivos/obj_690e2fc9a317a_1762537417.pdf', 1, '2025-11-08 19:38:54'),
(6, 'Proyecto desarrollo de inteligencia artificial', 'prueba de objetivo', 6, '2025-11-13 13:03:04', '2025-11-25', 'pendiente', '', '', 1, '2025-11-14 01:03:04'),
(7, 'Completacion de app de tareas', 'Descripcion de completacion de app de tareas', 6, '2025-12-04 07:48:26', '2025-12-26', 'pendiente', '', '', 6, '2025-12-04 19:48:26'),
(8, 'Terminacion de proyecto de tareas', 'Descripcion de proyecto de tareas', 8, '2025-12-04 08:03:06', '2025-12-20', 'pendiente', '', '', 6, '2025-12-04 20:03:06'),
(9, 'Completar año nuevo', 'Descripcion de completar año nuevo', 9, '2025-12-04 11:14:49', '2025-12-25', 'pendiente', '', '', 7, '2025-12-04 23:14:49'),
(10, 'Prueba de edicion de nuevo objetivo', 'Descripcion de prueba de nuevo objetivo despues de notificaciones', 1, '2025-12-09 15:16:47', '2025-12-25', 'pendiente', '', '', 1, '2025-12-10 03:16:47'),
(11, 'Prueba de creacion de objetivo como usuario', 'Descripcion de prueba de creacion de objetivo como usuario', 6, '2025-12-10 10:57:07', '2025-12-13', 'completado', '', '', 3, '2025-12-10 22:57:07'),
(12, 'Prueba de creacion de objetivo como gerente', 'Descripcion de prueba de creacion de objetivo como gerente', 8, '2025-12-10 11:29:30', '2025-12-13', 'pendiente', '', '', 6, '2025-12-10 23:29:30');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_proyectos`
--

CREATE TABLE `tbl_proyectos` (
  `id_proyecto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `id_departamento` int(11) NOT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_cumplimiento` date NOT NULL,
  `progreso` int(11) NOT NULL DEFAULT 0,
  `ar` varbinary(200) NOT NULL,
  `estado` enum('pendiente','en proceso','vencido','completado') NOT NULL DEFAULT 'pendiente',
  `archivo_adjunto` varchar(300) NOT NULL,
  `id_creador` int(11) NOT NULL,
  `id_participante` int(11) NOT NULL,
  `id_tipo_proyecto` int(11) DEFAULT NULL,
  `puede_editar_otros` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_proyectos`
--

INSERT INTO `tbl_proyectos` (`id_proyecto`, `nombre`, `descripcion`, `id_departamento`, `fecha_inicio`, `fecha_cumplimiento`, `progreso`, `ar`, `estado`, `archivo_adjunto`, `id_creador`, `id_participante`, `id_tipo_proyecto`, `puede_editar_otros`, `fecha_creacion`) VALUES
(1, 'Proyecto desarrollo de inteligencia artificial', 'Desarrollo e implementacion de inteligencia artificia autonoma capaz de realizar tareas de limpiesa autonomas', 1, '2025-11-25 23:27:09', '2025-11-17', 14, 0x31323334353637383839, 'vencido', 'uploads/proyectos/1762799642_f3314399a73845f2_Manual_de_usuario_para_sistema_de_pir__mide_3Q6S.pdf', 1, 1, 2, 0, '2025-11-15 00:33:00'),
(4, 'bbbbbbbbbbbbbb', 'bbbbbbbbbbbbbbbbb', 6, '2025-11-26 19:29:45', '2025-11-25', 83, '', 'vencido', '0', 1, 0, 1, 0, '2025-11-16 01:09:00'),
(8, 'prueba', 'prueba', 6, '2025-12-09 21:52:41', '2025-11-30', 33, 0x30, 'vencido', '', 1, 3, 2, 0, '2025-11-14 03:02:01'),
(9, 'Prueba de vencimiento de proyecto', 'Descripcion de prueba de vencimiento de proyecto', 6, '2025-12-08 22:00:43', '2025-11-30', 50, 0x30, 'vencido', '', 1, 3, 2, 0, '2025-11-19 21:37:50'),
(10, 'Prueba de index para proyecto', 'Descripcion de prueba de index para proyecto', 6, '2025-12-08 22:00:43', '2025-11-30', 0, 0x30, 'vencido', '', 1, 1, 2, 0, '2025-11-20 03:16:34'),
(11, 'Prueba de creacion de proyectos con edicion restringida', 'Descripcion de prueba de creacion de proyectos con edicion restringida', 6, '2025-12-10 01:25:48', '2025-11-24', 17, '', 'vencido', '0', 1, 3, 2, 0, '2025-11-20 21:50:09'),
(12, 'Estadisticas desarrolladas', 'Descripcion de estadisticas desarrolladas', 8, '2025-12-04 01:57:55', '2025-11-28', 100, '', 'completado', '0', 1, 6, 2, 0, '2025-11-24 23:17:28'),
(13, 'Instalacion de camaras de seguridad', 'Descripcion del proyecto de camaras de seguridad', 9, '2025-11-26 00:29:01', '2025-12-05', 100, '', 'completado', '0', 1, 7, 2, 0, '2025-11-26 00:28:19'),
(14, 'Ingreso de robots a planta', 'Descripcion de ingreso de robots a planta', 8, '2025-12-09 20:45:15', '2025-12-06', 33, '', 'vencido', '0', 1, 6, 2, 0, '2025-11-28 21:38:59'),
(18, 'prueba de proyecto para usuario normal', 'Descripcion de prueba para usuario normal', 9, '2025-12-12 01:35:00', '2025-12-18', 0, '', 'pendiente', '0', 7, 7, 2, 0, '2025-12-05 01:35:55'),
(19, 'prueba de creacion de proyecto despues de notificacion', 'descripcion de prueba de creacion de proyecto despues de notificacion', 1, '2025-12-11 01:18:00', '2026-01-01', 0, '', 'pendiente', '0', 1, 0, 2, 0, '2025-12-10 01:19:01'),
(20, 'prueba de creacion de proyecto despues de notificacion', 'Descripcion de prueba de proyecto despues de notificacion', 1, '2025-12-11 01:20:00', '2026-01-01', 0, '', 'pendiente', '0', 1, 1, 2, 0, '2025-12-10 01:20:16'),
(21, 'Prueba de creacion de proyecto como usuario', 'Descripcion de prueba de creacion de proyecto como usuario', 6, '2025-12-12 22:52:00', '2025-12-13', 0, '', 'pendiente', '0', 3, 3, 2, 0, '2025-12-10 22:52:34'),
(22, 'Prueba de creacion de proyecto como gerente', 'Descripcion de prueba de creacion de proyecto como gerente', 8, '2025-12-10 23:41:55', '2025-12-13', 100, '', 'completado', '0', 6, 6, 2, 0, '2025-12-10 23:26:06');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_proyecto_usuarios`
--

CREATE TABLE `tbl_proyecto_usuarios` (
  `id` int(11) NOT NULL,
  `id_proyecto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_proyecto_usuarios`
--

INSERT INTO `tbl_proyecto_usuarios` (`id`, `id_proyecto`, `id_usuario`, `fecha_asignacion`) VALUES
(14, 4, 1, '2025-11-20 23:25:25'),
(15, 4, 3, '2025-11-20 23:25:25');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roles`
--

CREATE TABLE `tbl_roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_roles`
--

INSERT INTO `tbl_roles` (`id_rol`, `nombre`, `descripcion`) VALUES
(1, 'administrador', 'usuario con privilegios de creacion de usuarios y departamentos'),
(2, 'gerente', 'usuario con privilegios de asignacion de proyctos, objetivos y tareas'),
(3, 'usuario', 'usuario con privilegios de creacion de tareas');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_tareas`
--

CREATE TABLE `tbl_tareas` (
  `id_tarea` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(250) NOT NULL,
  `id_proyecto` int(11) NOT NULL,
  `id_creador` int(11) NOT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_cumplimiento` date DEFAULT NULL,
  `estado` enum('pendiente','vencido','completado') NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_participante` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_tareas`
--

INSERT INTO `tbl_tareas` (`id_tarea`, `nombre`, `descripcion`, `id_proyecto`, `id_creador`, `fecha_inicio`, `fecha_cumplimiento`, `estado`, `fecha_creacion`, `id_participante`) VALUES
(1, 'desarrollar correccion de modal', 'desarrollar e implementar la correccion para el modal de tareas donde se queda la pantalla oscura', 4, 1, '2025-12-09 21:49:01', NULL, 'completado', '2025-11-11 20:42:14', NULL),
(2, 'Desarrollo de red neuronal', 'Desarrollo e implementacion de red neuronal a traves de python', 1, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-11 20:43:31', NULL),
(4, 'nueva tarea para el proyecto', 'desarrollo de tareas para proyectos', 4, 1, '2025-12-09 21:49:01', NULL, 'completado', '2025-11-11 23:03:47', NULL),
(7, 'ejemplo', 'ejemplo', 1, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-11 23:43:42', NULL),
(8, 'Desarrollo de automatizacion', 'Desarrollo e implementacion de pensamiento automatico', 1, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-13 00:51:19', NULL),
(9, 'Desarrollo de presentacion de informacin', 'Desarrollar el procesamiento de la informacion y presentacion de la misma', 1, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-13 00:52:59', NULL),
(10, 'Desarrollo de procesamiento eficiente', 'Desarrollo e implementacion de procesamiento eficiente de datos', 1, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-13 00:57:46', NULL),
(11, 'Desarrollo de deteccion de errores', 'Desarrollo e implementacion de deteccion de errores en procesamiento', 1, 1, '2025-11-25 23:26:29', '2025-11-30', 'completado', '2025-11-13 01:15:38', NULL),
(12, 'Desarrollo de conciencia', 'Desarrollo de sistema de conciencia de la red neuronal', 1, 1, '2025-12-08 22:00:43', '2025-11-28', 'vencido', '2025-11-13 20:42:41', NULL),
(13, 'Prueba de desarrollo de tarea', 'Descripcion de prueba de desarrollo de tarea', 4, 1, '2025-11-18 21:47:59', '2025-11-20', 'completado', '2025-11-18 21:47:55', 1),
(14, 'Segunda prueba de tarea', 'Descripcion de segunda prueba de tarea', 4, 1, '2025-11-19 20:55:58', '2025-11-20', 'completado', '2025-11-18 21:48:48', 1),
(15, 'Prueba de actualizacion automatica', 'Descripcion de prueba de actualizacion automatica', 8, 1, '2025-11-19 20:52:44', '2025-11-22', 'completado', '2025-11-19 20:52:36', 1),
(16, 'Prueba de actualizacion automatica', 'Descripcion de prueba de actualizacion automatica', 8, 1, '2025-11-20 01:58:31', '2025-11-21', 'completado', '2025-11-19 21:12:56', 3),
(17, 'Prueba de asignacion a cierto empleado', 'Descripcion de asignacion a cierto empleado', 4, 1, '2025-11-21 00:34:02', '2025-11-21', 'completado', '2025-11-19 21:24:19', 3),
(18, 'Prueba de tarea para vencimiento de proyecto', 'Descripcion de prueba de tarea para vencimiento de proyecto', 9, 1, '2025-11-19 21:42:37', '2025-11-30', 'completado', '2025-11-19 21:39:14', 3),
(19, 'Segunda tarea de prueba para vencimiento de proyecto', 'Descripcion de segunda tarea de prueba para vencimiento de proyecto.', 9, 1, '2025-12-08 22:00:43', '2025-11-30', 'vencido', '2025-11-19 21:40:18', 3),
(20, 'Prueba de asignacion de tarea con acceso restringido', 'Descripcion de prueba de asignacion de tarea con acceso restringido', 11, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-20 21:55:35', 3),
(21, 'Prueba de fecha de tarea', 'Descripcion para prueba de fecha de tarea', 10, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-21 19:49:37', 1),
(23, 'Grafica lineal', 'Descripcion de grafica lineal', 12, 1, '2025-12-09 21:49:01', NULL, 'completado', '2025-11-24 23:33:04', NULL),
(24, 'grafica de barras', 'descripcion de grafica de barras', 12, 1, '2025-12-09 21:49:01', NULL, 'completado', '2025-11-25 20:40:20', 6),
(25, 'Grafica de puntos dispersos', 'Descripcion de grafica de puntos dispersos', 12, 1, '2025-12-09 21:49:01', NULL, 'completado', '2025-11-25 23:29:11', 6),
(26, 'Instalacion de camaras en caseta', 'Descripcion de instalacion de camaras en caseta', 13, 1, '2025-12-09 21:49:01', NULL, 'completado', '2025-11-26 00:28:56', 7),
(27, 'Prueba de complecion de tarea', 'Descricpion de prueba de complecion de tarea', 4, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-26 19:27:54', 3),
(28, 'Path finding para robots', 'Instalar path finding en los robots', 14, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-11-28 21:43:29', 6),
(29, 'Cargadores', 'Instalacion de cargadores de robots', 14, 1, '2025-12-09 21:49:01', NULL, 'completado', '2025-11-28 21:44:02', 6),
(30, 'Completacion de prueba', 'Descripcion de completacion de prueba', 18, 7, '2025-12-07 00:23:17', '2025-12-20', 'pendiente', '2025-12-07 00:23:17', 7),
(31, 'prueba de notificacion de tostada', 'Descripcion de notificacion de tostada', 8, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-12-09 20:36:32', 3),
(32, 'prueba de fecha de tarea', 'descripcion de prueba de fecha de tarea', 14, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-12-09 20:45:15', 6),
(33, 'a', 'a', 8, 1, '2025-12-09 21:49:01', NULL, 'pendiente', '2025-12-09 21:08:47', 3),
(34, 'Prueba de tarea con fecha de vencimiento adecuada', 'descripcion de tarea con fecha de vencimiento adecuada', 8, 1, '2025-12-09 21:50:39', '0000-00-00', 'pendiente', '2025-12-09 21:50:39', 3),
(35, 'Prueba de tarea con fecha buena', 'Descripcion de tarea con fecha buena', 8, 1, '2025-12-09 21:52:41', '0000-00-00', 'pendiente', '2025-12-09 21:52:41', 3),
(36, 'Prueba de actualizacion de tareas nuevas', 'Descripcion de prueba de actualizacion de tareas', 11, 1, '2025-12-10 01:10:08', '2025-12-16', 'pendiente', '2025-12-09 23:54:59', 3),
(37, 'nueva prueba de fecha', 'descripcion de neuva prueba de fecha', 11, 1, '2025-12-10 00:49:02', '2025-12-17', 'completado', '2025-12-10 00:07:38', 3),
(38, 'prueba de creacion de tarea despues de notificacion', 'descripcion de prueba de notificacion despues de tarea', 11, 1, '2025-12-10 01:22:06', '2025-12-18', 'pendiente', '2025-12-10 01:22:06', 3),
(39, 'nueva pruebva de tarea', 'descripcion de neva prueba de tarea', 11, 1, '2025-12-10 01:23:57', '2025-12-11', 'pendiente', '2025-12-10 01:23:57', 3),
(40, 'de nuevo otra prueba de tarea despue de notificacion', 'descripcion de denuevo otra prueba de tarea despues de notificacion', 11, 1, '2025-12-10 01:25:48', '2025-12-11', 'pendiente', '2025-12-10 01:25:48', 3),
(41, 'Prueba de creacion de tarea como gerente', 'Descripcion de prueba de creacion de tarea como gerente', 22, 6, '2025-12-10 23:41:55', '2025-12-13', 'completado', '2025-12-10 23:41:48', 6);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_tipo_proyecto`
--

CREATE TABLE `tbl_tipo_proyecto` (
  `id_tipo_proyecto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_tipo_proyecto`
--

INSERT INTO `tbl_tipo_proyecto` (`id_tipo_proyecto`, `nombre`, `descripcion`) VALUES
(1, 'Proyecto grupal', 'Proyecto que se realiza con más de dos usuarios en conjunto'),
(2, 'Proyecto individual', 'Proyecto que se realiza uno o máximo dos usuarios asignados');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_usuarios`
--

CREATE TABLE `tbl_usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `num_empleado` int(11) NOT NULL,
  `acceso` varchar(100) NOT NULL,
  `id_departamento` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_superior` int(11) NOT NULL,
  `e_mail` varchar(200) NOT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL COMMENT 'Ruta relativa de la foto de perfil del usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_usuarios`
--

INSERT INTO `tbl_usuarios` (`id_usuario`, `nombre`, `apellido`, `usuario`, `num_empleado`, `acceso`, `id_departamento`, `id_rol`, `id_superior`, `e_mail`, `foto_perfil`) VALUES
(1, 'David', 'Barreto', 'NMC10DB', 1858, '$2y$12$aGtk1WQgiTS78EolU.N10Ozp7mUV0ui58orx0outXxhY9yqJy0X.e', 1, 1, 0, 'francisco.barreto@nidec.com', NULL),
(3, 'Francisco', 'Valdez', 'fram', 1959, '$2y$12$1A48sLCpaQNaIWPuobLreuboG0NVHWGWhvPQD/8AT9X63U6v3WK7G', 6, 3, 0, 'francisco.valdez@nidec.com', NULL),
(6, 'Jhon', 'Doe', 'JhonDoe', 1010, '$2y$12$pLqIjGxJAy6hbfbcH5TD/uhDlzNcuC7CDMpSO8CGSC12Booz2AKMa', 8, 2, 0, 'jhon.doe@nidec.com', NULL),
(7, 'Juan', 'Dou', 'JuanDou', 2222, '113a7f0c601f3d56b2cf4c9cca5ce636', 9, 3, 0, 'juan.dou@nidec-motor.com', NULL),
(8, 'Frenkie', 'DeJong', 'FKDJ20NMC', 21, '113a7f0c601f3d56b2cf4c9cca5ce636', 9, 3, 0, 'frenkie.dejong@nidec-motor.com', NULL),
(9, 'Luis', 'Ortiz', 'NMC56LO', 56, '$2y$12$dE2hoNRW.xcIQI64CsZxW.IHIB8bbIExHq/5ocrKRQP9d2Y4oof.y', 9, 2, 0, 'luis.ortiz@nidec-motor.com', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_usuario_roles`
--

CREATE TABLE `tbl_usuario_roles` (
  `id_usuario_roles` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_departamento` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Rol/departamento principal para redirección inicial al login',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Permite desactivar sin eliminar',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabla de relación para permitir múltiples roles por usuario en diferentes departamentos';

--
-- Dumping data for table `tbl_usuario_roles`
--

INSERT INTO `tbl_usuario_roles` (`id_usuario_roles`, `id_usuario`, `id_departamento`, `id_rol`, `es_principal`, `activo`, `fecha_asignacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, 1, 1, 1, '2025-12-22 13:40:13', '2025-12-22 13:40:13'),
(2, 3, 6, 3, 1, 1, '2025-12-22 13:40:13', '2025-12-22 13:40:13'),
(3, 6, 8, 2, 1, 1, '2025-12-22 13:40:13', '2025-12-22 13:40:13'),
(4, 7, 9, 3, 1, 1, '2025-12-22 13:40:13', '2025-12-22 13:40:13'),
(5, 8, 9, 3, 1, 1, '2025-12-22 13:40:13', '2025-12-22 13:40:13'),
(6, 9, 9, 2, 1, 1, '2025-12-22 13:40:13', '2025-12-22 13:40:13');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_emails_pendientes`
-- (See below for the actual view)
--
CREATE TABLE `v_emails_pendientes` (
`id_email` int(11)
,`destinatario_email` varchar(255)
,`destinatario_nombre` varchar(255)
,`asunto` varchar(255)
,`cuerpo_html` text
,`cuerpo_texto` text
,`tipo_notificacion` enum('tarea_asignada','tarea_vencimiento','tarea_vencida','tarea_completada','proyecto_asignado','proyecto_completado','objetivo_asignado','recordatorio_diario','resumen_semanal','prueba')
,`prioridad` tinyint(4)
,`estado` enum('pendiente','enviado','fallido','cancelado')
,`intentos` int(11)
,`max_intentos` int(11)
,`ultimo_error` text
,`referencia_tipo` enum('tarea','proyecto','objetivo','usuario')
,`referencia_id` int(11)
,`programado_para` datetime
,`enviado_at` datetime
,`fecha_creacion` timestamp
,`minutos_en_cola` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_estadisticas_email`
-- (See below for the actual view)
--
CREATE TABLE `v_estadisticas_email` (
`fecha` date
,`tipo_notificacion` enum('tarea_asignada','tarea_vencimiento','tarea_vencida','tarea_completada','proyecto_asignado','proyecto_completado','objetivo_asignado','recordatorio_diario','resumen_semanal','prueba')
,`total` bigint(21)
,`enviados` decimal(22,0)
,`fallidos` decimal(22,0)
,`pendientes` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_usuario_roles_completo`
-- (See below for the actual view)
--
CREATE TABLE `v_usuario_roles_completo` (
`id_asignacion` int(11)
,`id_usuario` int(11)
,`nombre_usuario` varchar(100)
,`apellido_usuario` varchar(100)
,`username` varchar(100)
,`e_mail` varchar(200)
,`id_departamento` int(11)
,`nombre_departamento` varchar(200)
,`id_rol` int(11)
,`nombre_rol` varchar(100)
,`descripcion_rol` varchar(100)
,`es_principal` tinyint(1)
,`activo` tinyint(1)
,`fecha_asignacion` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `v_emails_pendientes`
--
DROP TABLE IF EXISTS `v_emails_pendientes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_emails_pendientes`  AS SELECT `eq`.`id_email` AS `id_email`, `eq`.`destinatario_email` AS `destinatario_email`, `eq`.`destinatario_nombre` AS `destinatario_nombre`, `eq`.`asunto` AS `asunto`, `eq`.`cuerpo_html` AS `cuerpo_html`, `eq`.`cuerpo_texto` AS `cuerpo_texto`, `eq`.`tipo_notificacion` AS `tipo_notificacion`, `eq`.`prioridad` AS `prioridad`, `eq`.`estado` AS `estado`, `eq`.`intentos` AS `intentos`, `eq`.`max_intentos` AS `max_intentos`, `eq`.`ultimo_error` AS `ultimo_error`, `eq`.`referencia_tipo` AS `referencia_tipo`, `eq`.`referencia_id` AS `referencia_id`, `eq`.`programado_para` AS `programado_para`, `eq`.`enviado_at` AS `enviado_at`, `eq`.`fecha_creacion` AS `fecha_creacion`, timestampdiff(MINUTE,`eq`.`programado_para`,current_timestamp()) AS `minutos_en_cola` FROM `tbl_email_queue` AS `eq` WHERE `eq`.`estado` = 'pendiente' AND `eq`.`programado_para` <= current_timestamp() AND `eq`.`intentos` < `eq`.`max_intentos` ORDER BY `eq`.`prioridad` ASC, `eq`.`programado_para` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_estadisticas_email`
--
DROP TABLE IF EXISTS `v_estadisticas_email`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estadisticas_email`  AS SELECT cast(`tbl_email_queue`.`fecha_creacion` as date) AS `fecha`, `tbl_email_queue`.`tipo_notificacion` AS `tipo_notificacion`, count(0) AS `total`, sum(case when `tbl_email_queue`.`estado` = 'enviado' then 1 else 0 end) AS `enviados`, sum(case when `tbl_email_queue`.`estado` = 'fallido' then 1 else 0 end) AS `fallidos`, sum(case when `tbl_email_queue`.`estado` = 'pendiente' then 1 else 0 end) AS `pendientes` FROM `tbl_email_queue` GROUP BY cast(`tbl_email_queue`.`fecha_creacion` as date), `tbl_email_queue`.`tipo_notificacion` ORDER BY cast(`tbl_email_queue`.`fecha_creacion` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_usuario_roles_completo`
--
DROP TABLE IF EXISTS `v_usuario_roles_completo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_usuario_roles_completo`  AS SELECT `ur`.`id_usuario_roles` AS `id_asignacion`, `ur`.`id_usuario` AS `id_usuario`, `u`.`nombre` AS `nombre_usuario`, `u`.`apellido` AS `apellido_usuario`, `u`.`usuario` AS `username`, `u`.`e_mail` AS `e_mail`, `ur`.`id_departamento` AS `id_departamento`, `d`.`nombre` AS `nombre_departamento`, `ur`.`id_rol` AS `id_rol`, `r`.`nombre` AS `nombre_rol`, `r`.`descripcion` AS `descripcion_rol`, `ur`.`es_principal` AS `es_principal`, `ur`.`activo` AS `activo`, `ur`.`fecha_asignacion` AS `fecha_asignacion` FROM (((`tbl_usuario_roles` `ur` join `tbl_usuarios` `u` on(`ur`.`id_usuario` = `u`.`id_usuario`)) join `tbl_departamentos` `d` on(`ur`.`id_departamento` = `d`.`id_departamento`)) join `tbl_roles` `r` on(`ur`.`id_rol` = `r`.`id_rol`)) WHERE `ur`.`activo` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_departamentos`
--
ALTER TABLE `tbl_departamentos`
  ADD PRIMARY KEY (`id_departamento`);

--
-- Indexes for table `tbl_email_config`
--
ALTER TABLE `tbl_email_config`
  ADD PRIMARY KEY (`id_config`),
  ADD UNIQUE KEY `unique_config_key` (`config_key`);

--
-- Indexes for table `tbl_email_log`
--
ALTER TABLE `tbl_email_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_email_evento` (`id_email`,`evento`),
  ADD KEY `idx_fecha` (`fecha_creacion`);

--
-- Indexes for table `tbl_email_queue`
--
ALTER TABLE `tbl_email_queue`
  ADD PRIMARY KEY (`id_email`),
  ADD KEY `idx_estado_programado` (`estado`,`programado_para`),
  ADD KEY `idx_tipo_notificacion` (`tipo_notificacion`),
  ADD KEY `idx_referencia` (`referencia_tipo`,`referencia_id`),
  ADD KEY `idx_destinatario` (`destinatario_email`);

--
-- Indexes for table `tbl_notificacion_preferencias`
--
ALTER TABLE `tbl_notificacion_preferencias`
  ADD PRIMARY KEY (`id_preferencia`),
  ADD UNIQUE KEY `unique_usuario` (`id_usuario`);

--
-- Indexes for table `tbl_objetivos`
--
ALTER TABLE `tbl_objetivos`
  ADD PRIMARY KEY (`id_objetivo`);

--
-- Indexes for table `tbl_proyectos`
--
ALTER TABLE `tbl_proyectos`
  ADD PRIMARY KEY (`id_proyecto`);

--
-- Indexes for table `tbl_proyecto_usuarios`
--
ALTER TABLE `tbl_proyecto_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_proyecto_usuario` (`id_proyecto`,`id_usuario`),
  ADD KEY `idx_proyecto_usuarios` (`id_proyecto`),
  ADD KEY `idx_usuario_proyectos` (`id_usuario`);

--
-- Indexes for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indexes for table `tbl_tareas`
--
ALTER TABLE `tbl_tareas`
  ADD PRIMARY KEY (`id_tarea`);

--
-- Indexes for table `tbl_tipo_proyecto`
--
ALTER TABLE `tbl_tipo_proyecto`
  ADD PRIMARY KEY (`id_tipo_proyecto`);

--
-- Indexes for table `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Indexes for table `tbl_usuario_roles`
--
ALTER TABLE `tbl_usuario_roles`
  ADD PRIMARY KEY (`id_usuario_roles`),
  ADD UNIQUE KEY `unique_usuario_depto` (`id_usuario`,`id_departamento`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_departamento` (`id_departamento`),
  ADD KEY `idx_rol` (`id_rol`),
  ADD KEY `idx_principal` (`id_usuario`,`es_principal`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_departamentos`
--
ALTER TABLE `tbl_departamentos`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_email_config`
--
ALTER TABLE `tbl_email_config`
  MODIFY `id_config` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_email_log`
--
ALTER TABLE `tbl_email_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_email_queue`
--
ALTER TABLE `tbl_email_queue`
  MODIFY `id_email` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_notificacion_preferencias`
--
ALTER TABLE `tbl_notificacion_preferencias`
  MODIFY `id_preferencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_objetivos`
--
ALTER TABLE `tbl_objetivos`
  MODIFY `id_objetivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_proyectos`
--
ALTER TABLE `tbl_proyectos`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tbl_proyecto_usuarios`
--
ALTER TABLE `tbl_proyecto_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_tareas`
--
ALTER TABLE `tbl_tareas`
  MODIFY `id_tarea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `tbl_tipo_proyecto`
--
ALTER TABLE `tbl_tipo_proyecto`
  MODIFY `id_tipo_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_usuario_roles`
--
ALTER TABLE `tbl_usuario_roles`
  MODIFY `id_usuario_roles` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_email_log`
--
ALTER TABLE `tbl_email_log`
  ADD CONSTRAINT `fk_email_log_queue` FOREIGN KEY (`id_email`) REFERENCES `tbl_email_queue` (`id_email`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_notificacion_preferencias`
--
ALTER TABLE `tbl_notificacion_preferencias`
  ADD CONSTRAINT `fk_preferencias_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_proyecto_usuarios`
--
ALTER TABLE `tbl_proyecto_usuarios`
  ADD CONSTRAINT `tbl_proyecto_usuarios_ibfk_1` FOREIGN KEY (`id_proyecto`) REFERENCES `tbl_proyectos` (`id_proyecto`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_proyecto_usuarios_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_usuario_roles`
--
ALTER TABLE `tbl_usuario_roles`
  ADD CONSTRAINT `fk_usuario_roles_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `tbl_departamentos` (`id_departamento`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usuario_roles_rol` FOREIGN KEY (`id_rol`) REFERENCES `tbl_roles` (`id_rol`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usuario_roles_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_usuarios` (`id_usuario`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
