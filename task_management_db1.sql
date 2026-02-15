-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-02-2026 a las 22:07:55
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `task_management_db`
--

DELIMITER $$
--
-- Procedimientos
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_desactivar_rol_usuario` (IN `p_id_usuario` INT, IN `p_id_departamento` INT)   BEGIN
    UPDATE `tbl_usuario_roles`
    SET activo = 0
    WHERE id_usuario = p_id_usuario 
      AND id_departamento = p_id_departamento;
    
    SELECT 'Rol desactivado correctamente' AS mensaje;
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
-- Estructura de tabla para la tabla `tbl_departamentos`
--

CREATE TABLE `tbl_departamentos` (
  `id_departamento` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `id_creador` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_departamentos`
--

INSERT INTO `tbl_departamentos` (`id_departamento`, `nombre`, `descripcion`, `id_creador`) VALUES
(1, 'IT', 'Departamento de tecnologias de la información y soluciones tecnológicas', 1),
(2, 'Deportes', 'Equipo competitivo', 1),
(3, 'Recursos humanos', 'recursos humanos', 1),
(4, 'entrenamiento', 'Entrenamiento', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_email_config`
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
-- Estructura de tabla para la tabla `tbl_email_log`
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
-- Volcado de datos para la tabla `tbl_email_log`
--

INSERT INTO `tbl_email_log` (`id_log`, `id_email`, `evento`, `detalle`, `ip_address`, `user_agent`, `fecha_creacion`) VALUES
(0, 0, 'queued', 'Email en cola para: hola@nidec-motor.com', NULL, NULL, '2025-12-25 16:44:09'),
(0, 0, 'queued', 'Email en cola para: hola@nidec-motor.com', NULL, NULL, '2025-12-25 17:44:57'),
(0, 0, 'queued', 'Email en cola para: hola@nidec-motor.com', NULL, NULL, '2025-12-25 18:16:33'),
(0, 0, 'queued', 'Email en cola para: jesus.dominguez@nidec-motor.com', NULL, NULL, '2025-12-27 15:18:22'),
(0, 0, 'queued', 'Email en cola para: jesus.dominguez@nidec-motor.com', NULL, NULL, '2025-12-27 15:18:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_email_queue`
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
-- Volcado de datos para la tabla `tbl_email_queue`
--

INSERT INTO `tbl_email_queue` (`id_email`, `destinatario_email`, `destinatario_nombre`, `asunto`, `cuerpo_html`, `cuerpo_texto`, `tipo_notificacion`, `prioridad`, `estado`, `intentos`, `max_intentos`, `ultimo_error`, `referencia_tipo`, `referencia_id`, `programado_para`, `enviado_at`, `fecha_creacion`) VALUES
(0, 'hola@nidec-motor.com', 'prueba de foto', 'Asignado a proyecto: prueba', '\r\n<!DOCTYPE html>\r\n<html lang=\"es\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n    <title>Asignado a proyecto: prueba</title>\r\n    <style>\r\n        * {\r\n            margin: 0;\r\n            padding: 0;\r\n            box-sizing: border-box;\r\n        }\r\n        body { \r\n            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n            line-height: 1.6;\r\n            color: #333333;\r\n            background-color: #f5f5f5;\r\n            padding: 20px;\r\n        }\r\n        .email-wrapper {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n        }\r\n        .email-container {\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n        }\r\n        .header {\r\n            background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n            color: #ffffff;\r\n            padding: 25px 30px;\r\n            text-align: center;\r\n        }\r\n        .header h1 {\r\n            margin: 0;\r\n            font-size: 22px;\r\n            font-weight: 600;\r\n        }\r\n        .header .subtitle {\r\n            font-size: 14px;\r\n            opacity: 0.9;\r\n            margin-top: 5px;\r\n        }\r\n        .content {\r\n            padding: 30px;\r\n        }\r\n        .greeting {\r\n            font-size: 18px;\r\n            margin-bottom: 20px;\r\n        }\r\n        .task-card {\r\n            background: #f8f9fa;\r\n            border-left: 4px solid #009B4A;\r\n            padding: 20px;\r\n            margin: 20px 0;\r\n            border-radius: 0 8px 8px 0;\r\n        }\r\n        .task-card.urgent {\r\n            border-left-color: #000000;\r\n            background: #fff5f5;\r\n        }\r\n        .task-card.warning {\r\n            border-left-color: #666666;\r\n            background: #fff8e1;\r\n        }\r\n        .task-card h3 {\r\n            margin: 0 0 10px 0;\r\n            color: #333;\r\n            font-size: 18px;\r\n        }\r\n        .task-card p {\r\n            margin: 0 0 10px 0;\r\n            color: #666;\r\n        }\r\n        .meta-info {\r\n            font-size: 14px;\r\n            color: #666;\r\n        }\r\n        .meta-info strong {\r\n            color: #333;\r\n        }\r\n        .btn {\r\n            display: inline-block;\r\n            padding: 12px 28px;\r\n            background-color: #009B4A;\r\n            color: #ffffff !important;\r\n            text-decoration: none;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            font-weight: 500;\r\n            font-size: 14px;\r\n        }\r\n        .btn:hover {\r\n            background-color: #009B4A;\r\n        }\r\n        .btn-center {\r\n            text-align: center;\r\n            margin: 25px 0;\r\n        }\r\n        .stats-container {\r\n            display: table;\r\n            width: 100%;\r\n            margin: 20px 0;\r\n        }\r\n        .stat-box {\r\n            display: table-cell;\r\n            width: 33.33%;\r\n            padding: 15px;\r\n            text-align: center;\r\n            border-radius: 8px;\r\n        }\r\n        .stat-box.success { background: #e8f5e9; }\r\n        .stat-box.warning { background: #fff3e0; }\r\n        .stat-box.danger { background: #ffebee; }\r\n        .stat-number {\r\n            font-size: 32px;\r\n            font-weight: bold;\r\n            display: block;\r\n        }\r\n        .stat-box.success .stat-number { color: #009B4A; }\r\n        .stat-box.warning .stat-number { color: #666666; }\r\n        .stat-box.danger .stat-number { color: #000000; }\r\n        .stat-label {\r\n            font-size: 12px;\r\n            color: #666;\r\n            text-transform: uppercase;\r\n        }\r\n        .footer {\r\n            margin-top: 30px;\r\n            padding: 20px 30px;\r\n            background: #f8f9fa;\r\n            border-top: 1px solid #eee;\r\n            text-align: center;\r\n            font-size: 12px;\r\n            color: #999;\r\n        }\r\n        .footer a {\r\n            color: #009B4A;\r\n            text-decoration: none;\r\n        }\r\n        .divider {\r\n            height: 1px;\r\n            background: #eee;\r\n            margin: 20px 0;\r\n        }\r\n        .upcoming-list {\r\n            margin: 15px 0;\r\n            padding: 0;\r\n            list-style: none;\r\n        }\r\n        .upcoming-list li {\r\n            padding: 10px 0;\r\n            border-bottom: 1px solid #eee;\r\n        }\r\n        .upcoming-list li:last-child {\r\n            border-bottom: none;\r\n        }\r\n        .date-badge {\r\n            display: inline-block;\r\n            background: #e3f2fd;\r\n            color: #009B4A;\r\n            padding: 2px 8px;\r\n            border-radius: 4px;\r\n            font-size: 12px;\r\n            margin-left: 10px;\r\n        }\r\n        @media only screen and (max-width: 600px) {\r\n            .content { padding: 20px; }\r\n            .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n        }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-wrapper\">\r\n        <div class=\"email-container\">\r\n            <div class=\"header\">\r\n                <h1>Sistema de Gestión de Tareas</h1>\r\n                <div class=\"subtitle\">Nuevo proyecto asignado</div>\r\n            </div>\r\n            <div class=\"content\">\r\n                \r\n            <p class=\"greeting\">Hola <strong>prueba</strong>,</p>\r\n            <p>Has sido asignado a un nuevo proyecto:</p>\r\n            \r\n            <div class=\"task-card\" style=\"border-left-color: #009B4A;\">\r\n                <h3>prueba</h3>\r\n                <p>prueba descripcion</p>\r\n                <div class=\"divider\"></div>\r\n                <div class=\"meta-info\">\r\n                    <p><strong>Departamento:</strong> Deportes</p>\r\n                    <p><strong>Fecha límite:</strong> 27/12/2025</p>\r\n                    <p><strong>Creado por:</strong> David Barreto</p>\r\n                </div>\r\n            </div>\r\n            \r\n            <div class=\"btn-center\">\r\n                <a href=\"http://localhost/task_management\" class=\"btn\" style=\"background-color: #009B4A;\">Ver Proyecto</a>\r\n            </div>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>Este es un mensaje automático del Sistema de Gestión de Tareas.</p>\r\n                <p>Por favor no responda directamente a este correo.</p>\r\n                <p style=\"margin-top: 10px;\">\r\n                    <a href=\"http://localhost/task_management\">Acceder al Sistema</a>\r\n                </p>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Asignado a proyecto: prueba\r\n \r\n * {\r\n margin: 0;\r\n padding: 0;\r\n box-sizing: border-box;\r\n }\r\n body { \r\n font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n line-height: 1.6;\r\n color: #333333;\r\n background-color: #f5f5f5;\r\n padding: 20px;\r\n }\r\n .email-wrapper {\r\n max-width: 600px;\r\n margin: 0 auto;\r\n }\r\n .email-container {\r\n background: #ffffff;\r\n border-radius: 8px;\r\n overflow: hidden;\r\n box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n }\r\n .header {\r\n background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n color: #ffffff;\r\n padding: 25px 30px;\r\n text-align: center;\r\n }\r\n .header h1 {\r\n margin: 0;\r\n font-size: 22px;\r\n font-weight: 600;\r\n }\r\n .header .subtitle {\r\n font-size: 14px;\r\n opacity: 0.9;\r\n margin-top: 5px;\r\n }\r\n .content {\r\n padding: 30px;\r\n }\r\n .greeting {\r\n font-size: 18px;\r\n margin-bottom: 20px;\r\n }\r\n .task-card {\r\n background: #f8f9fa;\r\n border-left: 4px solid #009B4A;\r\n padding: 20px;\r\n margin: 20px 0;\r\n border-radius: 0 8px 8px 0;\r\n }\r\n .task-card.urgent {\r\n border-left-color: #000000;\r\n background: #fff5f5;\r\n }\r\n .task-card.warning {\r\n border-left-color: #666666;\r\n background: #fff8e1;\r\n }\r\n .task-card h3 {\r\n margin: 0 0 10px 0;\r\n color: #333;\r\n font-size: 18px;\r\n }\r\n .task-card p {\r\n margin: 0 0 10px 0;\r\n color: #666;\r\n }\r\n .meta-info {\r\n font-size: 14px;\r\n color: #666;\r\n }\r\n .meta-info strong {\r\n color: #333;\r\n }\r\n .btn {\r\n display: inline-block;\r\n padding: 12px 28px;\r\n background-color: #009B4A;\r\n color: #ffffff !important;\r\n text-decoration: none;\r\n border-radius: 6px;\r\n margin-top: 20px;\r\n font-weight: 500;\r\n font-size: 14px;\r\n }\r\n .btn:hover {\r\n background-color: #009B4A;\r\n }\r\n .btn-center {\r\n text-align: center;\r\n margin: 25px 0;\r\n }\r\n .stats-container {\r\n display: table;\r\n width: 100%;\r\n margin: 20px 0;\r\n }\r\n .stat-box {\r\n display: table-cell;\r\n width: 33.33%;\r\n padding: 15px;\r\n text-align: center;\r\n border-radius: 8px;\r\n }\r\n .stat-box.success { background: #e8f5e9; }\r\n .stat-box.warning { background: #fff3e0; }\r\n .stat-box.danger { background: #ffebee; }\r\n .stat-number {\r\n font-size: 32px;\r\n font-weight: bold;\r\n display: block;\r\n }\r\n .stat-box.success .stat-number { color: #009B4A; }\r\n .stat-box.warning .stat-number { color: #666666; }\r\n .stat-box.danger .stat-number { color: #000000; }\r\n .stat-label {\r\n font-size: 12px;\r\n color: #666;\r\n text-transform: uppercase;\r\n }\r\n .footer {\r\n margin-top: 30px;\r\n padding: 20px 30px;\r\n background: #f8f9fa;\r\n border-top: 1px solid #eee;\r\n text-align: center;\r\n font-size: 12px;\r\n color: #999;\r\n }\r\n .footer a {\r\n color: #009B4A;\r\n text-decoration: none;\r\n }\r\n .divider {\r\n height: 1px;\r\n background: #eee;\r\n margin: 20px 0;\r\n }\r\n .upcoming-list {\r\n margin: 15px 0;\r\n padding: 0;\r\n list-style: none;\r\n }\r\n .upcoming-list li {\r\n padding: 10px 0;\r\n border-bottom: 1px solid #eee;\r\n }\r\n .upcoming-list li:last-child {\r\n border-bottom: none;\r\n }\r\n .date-badge {\r\n display: inline-block;\r\n background: #e3f2fd;\r\n color: #009B4A;\r\n padding: 2px 8px;\r\n border-radius: 4px;\r\n font-size: 12px;\r\n margin-left: 10px;\r\n }\r\n @media only screen and (max-width: 600px) {\r\n .content { padding: 20px; }\r\n .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n }\r\n \r\n\r\n\r\n \r\n \r\n \r\n Sistema de Gestión de Tareas\r\n Nuevo proyecto asignado\n\n\r\n \n\n\r\n \r\n \r\n Hola prueba,\n\n\r\n Has sido asignado a un nuevo proyecto:\n\n\r\n \r\n \r\n prueba\r\n prueba descripcion\n\n\r\n \n\n\r\n \r\n Departamento: Deportes\n\n\r\n Fecha límite: 27/12/2025\n\n\r\n Creado por: David Barreto\n\n\r\n \n\n\r\n \n\n\r\n \r\n \r\n Ver Proyecto\r\n \n\n\r\n \n\n\r\n \r\n Este es un mensaje automático del Sistema de Gestión de Tareas.\n\n\r\n Por favor no responda directamente a este correo.\n\n\r\n \r\n Acceder al Sistema', 'proyecto_asignado', 3, 'pendiente', 0, 3, NULL, 'proyecto', 17, '2025-12-25 17:44:09', NULL, '2025-12-25 16:44:09'),
(0, 'hola@nidec-motor.com', 'prueba de foto', 'Asignado a proyecto: ganar al spanyol', '\r\n<!DOCTYPE html>\r\n<html lang=\"es\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n    <title>Asignado a proyecto: ganar al spanyol</title>\r\n    <style>\r\n        * {\r\n            margin: 0;\r\n            padding: 0;\r\n            box-sizing: border-box;\r\n        }\r\n        body { \r\n            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n            line-height: 1.6;\r\n            color: #333333;\r\n            background-color: #f5f5f5;\r\n            padding: 20px;\r\n        }\r\n        .email-wrapper {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n        }\r\n        .email-container {\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n        }\r\n        .header {\r\n            background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n            color: #ffffff;\r\n            padding: 25px 30px;\r\n            text-align: center;\r\n        }\r\n        .header h1 {\r\n            margin: 0;\r\n            font-size: 22px;\r\n            font-weight: 600;\r\n        }\r\n        .header .subtitle {\r\n            font-size: 14px;\r\n            opacity: 0.9;\r\n            margin-top: 5px;\r\n        }\r\n        .content {\r\n            padding: 30px;\r\n        }\r\n        .greeting {\r\n            font-size: 18px;\r\n            margin-bottom: 20px;\r\n        }\r\n        .task-card {\r\n            background: #f8f9fa;\r\n            border-left: 4px solid #009B4A;\r\n            padding: 20px;\r\n            margin: 20px 0;\r\n            border-radius: 0 8px 8px 0;\r\n        }\r\n        .task-card.urgent {\r\n            border-left-color: #000000;\r\n            background: #fff5f5;\r\n        }\r\n        .task-card.warning {\r\n            border-left-color: #666666;\r\n            background: #fff8e1;\r\n        }\r\n        .task-card h3 {\r\n            margin: 0 0 10px 0;\r\n            color: #333;\r\n            font-size: 18px;\r\n        }\r\n        .task-card p {\r\n            margin: 0 0 10px 0;\r\n            color: #666;\r\n        }\r\n        .meta-info {\r\n            font-size: 14px;\r\n            color: #666;\r\n        }\r\n        .meta-info strong {\r\n            color: #333;\r\n        }\r\n        .btn {\r\n            display: inline-block;\r\n            padding: 12px 28px;\r\n            background-color: #009B4A;\r\n            color: #ffffff !important;\r\n            text-decoration: none;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            font-weight: 500;\r\n            font-size: 14px;\r\n        }\r\n        .btn:hover {\r\n            background-color: #009B4A;\r\n        }\r\n        .btn-center {\r\n            text-align: center;\r\n            margin: 25px 0;\r\n        }\r\n        .stats-container {\r\n            display: table;\r\n            width: 100%;\r\n            margin: 20px 0;\r\n        }\r\n        .stat-box {\r\n            display: table-cell;\r\n            width: 33.33%;\r\n            padding: 15px;\r\n            text-align: center;\r\n            border-radius: 8px;\r\n        }\r\n        .stat-box.success { background: #e8f5e9; }\r\n        .stat-box.warning { background: #fff3e0; }\r\n        .stat-box.danger { background: #ffebee; }\r\n        .stat-number {\r\n            font-size: 32px;\r\n            font-weight: bold;\r\n            display: block;\r\n        }\r\n        .stat-box.success .stat-number { color: #009B4A; }\r\n        .stat-box.warning .stat-number { color: #666666; }\r\n        .stat-box.danger .stat-number { color: #000000; }\r\n        .stat-label {\r\n            font-size: 12px;\r\n            color: #666;\r\n            text-transform: uppercase;\r\n        }\r\n        .footer {\r\n            margin-top: 30px;\r\n            padding: 20px 30px;\r\n            background: #f8f9fa;\r\n            border-top: 1px solid #eee;\r\n            text-align: center;\r\n            font-size: 12px;\r\n            color: #999;\r\n        }\r\n        .footer a {\r\n            color: #009B4A;\r\n            text-decoration: none;\r\n        }\r\n        .divider {\r\n            height: 1px;\r\n            background: #eee;\r\n            margin: 20px 0;\r\n        }\r\n        .upcoming-list {\r\n            margin: 15px 0;\r\n            padding: 0;\r\n            list-style: none;\r\n        }\r\n        .upcoming-list li {\r\n            padding: 10px 0;\r\n            border-bottom: 1px solid #eee;\r\n        }\r\n        .upcoming-list li:last-child {\r\n            border-bottom: none;\r\n        }\r\n        .date-badge {\r\n            display: inline-block;\r\n            background: #e3f2fd;\r\n            color: #009B4A;\r\n            padding: 2px 8px;\r\n            border-radius: 4px;\r\n            font-size: 12px;\r\n            margin-left: 10px;\r\n        }\r\n        @media only screen and (max-width: 600px) {\r\n            .content { padding: 20px; }\r\n            .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n        }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-wrapper\">\r\n        <div class=\"email-container\">\r\n            <div class=\"header\">\r\n                <h1>Sistema de Gestión de Tareas</h1>\r\n                <div class=\"subtitle\">Nuevo proyecto asignado</div>\r\n            </div>\r\n            <div class=\"content\">\r\n                \r\n            <p class=\"greeting\">Hola <strong>prueba</strong>,</p>\r\n            <p>Has sido asignado a un nuevo proyecto:</p>\r\n            \r\n            <div class=\"task-card\" style=\"border-left-color: #009B4A;\">\r\n                <h3>ganar al spanyol</h3>\r\n                <p>ganar al spanyol en enero</p>\r\n                <div class=\"divider\"></div>\r\n                <div class=\"meta-info\">\r\n                    <p><strong>Departamento:</strong> Deportes</p>\r\n                    <p><strong>Fecha límite:</strong> 31/12/2025</p>\r\n                    <p><strong>Creado por:</strong> Jhon Doe</p>\r\n                </div>\r\n            </div>\r\n            \r\n            <div class=\"btn-center\">\r\n                <a href=\"http://localhost/task_management\" class=\"btn\" style=\"background-color: #009B4A;\">Ver Proyecto</a>\r\n            </div>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>Este es un mensaje automático del Sistema de Gestión de Tareas.</p>\r\n                <p>Por favor no responda directamente a este correo.</p>\r\n                <p style=\"margin-top: 10px;\">\r\n                    <a href=\"http://localhost/task_management\">Acceder al Sistema</a>\r\n                </p>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Asignado a proyecto: ganar al spanyol\r\n \r\n * {\r\n margin: 0;\r\n padding: 0;\r\n box-sizing: border-box;\r\n }\r\n body { \r\n font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n line-height: 1.6;\r\n color: #333333;\r\n background-color: #f5f5f5;\r\n padding: 20px;\r\n }\r\n .email-wrapper {\r\n max-width: 600px;\r\n margin: 0 auto;\r\n }\r\n .email-container {\r\n background: #ffffff;\r\n border-radius: 8px;\r\n overflow: hidden;\r\n box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n }\r\n .header {\r\n background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n color: #ffffff;\r\n padding: 25px 30px;\r\n text-align: center;\r\n }\r\n .header h1 {\r\n margin: 0;\r\n font-size: 22px;\r\n font-weight: 600;\r\n }\r\n .header .subtitle {\r\n font-size: 14px;\r\n opacity: 0.9;\r\n margin-top: 5px;\r\n }\r\n .content {\r\n padding: 30px;\r\n }\r\n .greeting {\r\n font-size: 18px;\r\n margin-bottom: 20px;\r\n }\r\n .task-card {\r\n background: #f8f9fa;\r\n border-left: 4px solid #009B4A;\r\n padding: 20px;\r\n margin: 20px 0;\r\n border-radius: 0 8px 8px 0;\r\n }\r\n .task-card.urgent {\r\n border-left-color: #000000;\r\n background: #fff5f5;\r\n }\r\n .task-card.warning {\r\n border-left-color: #666666;\r\n background: #fff8e1;\r\n }\r\n .task-card h3 {\r\n margin: 0 0 10px 0;\r\n color: #333;\r\n font-size: 18px;\r\n }\r\n .task-card p {\r\n margin: 0 0 10px 0;\r\n color: #666;\r\n }\r\n .meta-info {\r\n font-size: 14px;\r\n color: #666;\r\n }\r\n .meta-info strong {\r\n color: #333;\r\n }\r\n .btn {\r\n display: inline-block;\r\n padding: 12px 28px;\r\n background-color: #009B4A;\r\n color: #ffffff !important;\r\n text-decoration: none;\r\n border-radius: 6px;\r\n margin-top: 20px;\r\n font-weight: 500;\r\n font-size: 14px;\r\n }\r\n .btn:hover {\r\n background-color: #009B4A;\r\n }\r\n .btn-center {\r\n text-align: center;\r\n margin: 25px 0;\r\n }\r\n .stats-container {\r\n display: table;\r\n width: 100%;\r\n margin: 20px 0;\r\n }\r\n .stat-box {\r\n display: table-cell;\r\n width: 33.33%;\r\n padding: 15px;\r\n text-align: center;\r\n border-radius: 8px;\r\n }\r\n .stat-box.success { background: #e8f5e9; }\r\n .stat-box.warning { background: #fff3e0; }\r\n .stat-box.danger { background: #ffebee; }\r\n .stat-number {\r\n font-size: 32px;\r\n font-weight: bold;\r\n display: block;\r\n }\r\n .stat-box.success .stat-number { color: #009B4A; }\r\n .stat-box.warning .stat-number { color: #666666; }\r\n .stat-box.danger .stat-number { color: #000000; }\r\n .stat-label {\r\n font-size: 12px;\r\n color: #666;\r\n text-transform: uppercase;\r\n }\r\n .footer {\r\n margin-top: 30px;\r\n padding: 20px 30px;\r\n background: #f8f9fa;\r\n border-top: 1px solid #eee;\r\n text-align: center;\r\n font-size: 12px;\r\n color: #999;\r\n }\r\n .footer a {\r\n color: #009B4A;\r\n text-decoration: none;\r\n }\r\n .divider {\r\n height: 1px;\r\n background: #eee;\r\n margin: 20px 0;\r\n }\r\n .upcoming-list {\r\n margin: 15px 0;\r\n padding: 0;\r\n list-style: none;\r\n }\r\n .upcoming-list li {\r\n padding: 10px 0;\r\n border-bottom: 1px solid #eee;\r\n }\r\n .upcoming-list li:last-child {\r\n border-bottom: none;\r\n }\r\n .date-badge {\r\n display: inline-block;\r\n background: #e3f2fd;\r\n color: #009B4A;\r\n padding: 2px 8px;\r\n border-radius: 4px;\r\n font-size: 12px;\r\n margin-left: 10px;\r\n }\r\n @media only screen and (max-width: 600px) {\r\n .content { padding: 20px; }\r\n .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n }\r\n \r\n\r\n\r\n \r\n \r\n \r\n Sistema de Gestión de Tareas\r\n Nuevo proyecto asignado\n\n\r\n \n\n\r\n \r\n \r\n Hola prueba,\n\n\r\n Has sido asignado a un nuevo proyecto:\n\n\r\n \r\n \r\n ganar al spanyol\r\n ganar al spanyol en enero\n\n\r\n \n\n\r\n \r\n Departamento: Deportes\n\n\r\n Fecha límite: 31/12/2025\n\n\r\n Creado por: Jhon Doe\n\n\r\n \n\n\r\n \n\n\r\n \r\n \r\n Ver Proyecto\r\n \n\n\r\n \n\n\r\n \r\n Este es un mensaje automático del Sistema de Gestión de Tareas.\n\n\r\n Por favor no responda directamente a este correo.\n\n\r\n \r\n Acceder al Sistema', 'proyecto_asignado', 3, 'pendiente', 0, 3, NULL, 'proyecto', 18, '2025-12-25 18:44:57', NULL, '2025-12-25 17:44:57'),
(0, 'hola@nidec-motor.com', 'prueba de foto', 'Asignado a proyecto: 2005 2006', '\r\n<!DOCTYPE html>\r\n<html lang=\"es\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n    <title>Asignado a proyecto: 2005 2006</title>\r\n    <style>\r\n        * {\r\n            margin: 0;\r\n            padding: 0;\r\n            box-sizing: border-box;\r\n        }\r\n        body { \r\n            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n            line-height: 1.6;\r\n            color: #333333;\r\n            background-color: #f5f5f5;\r\n            padding: 20px;\r\n        }\r\n        .email-wrapper {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n        }\r\n        .email-container {\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n        }\r\n        .header {\r\n            background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n            color: #ffffff;\r\n            padding: 25px 30px;\r\n            text-align: center;\r\n        }\r\n        .header h1 {\r\n            margin: 0;\r\n            font-size: 22px;\r\n            font-weight: 600;\r\n        }\r\n        .header .subtitle {\r\n            font-size: 14px;\r\n            opacity: 0.9;\r\n            margin-top: 5px;\r\n        }\r\n        .content {\r\n            padding: 30px;\r\n        }\r\n        .greeting {\r\n            font-size: 18px;\r\n            margin-bottom: 20px;\r\n        }\r\n        .task-card {\r\n            background: #f8f9fa;\r\n            border-left: 4px solid #009B4A;\r\n            padding: 20px;\r\n            margin: 20px 0;\r\n            border-radius: 0 8px 8px 0;\r\n        }\r\n        .task-card.urgent {\r\n            border-left-color: #000000;\r\n            background: #fff5f5;\r\n        }\r\n        .task-card.warning {\r\n            border-left-color: #666666;\r\n            background: #fff8e1;\r\n        }\r\n        .task-card h3 {\r\n            margin: 0 0 10px 0;\r\n            color: #333;\r\n            font-size: 18px;\r\n        }\r\n        .task-card p {\r\n            margin: 0 0 10px 0;\r\n            color: #666;\r\n        }\r\n        .meta-info {\r\n            font-size: 14px;\r\n            color: #666;\r\n        }\r\n        .meta-info strong {\r\n            color: #333;\r\n        }\r\n        .btn {\r\n            display: inline-block;\r\n            padding: 12px 28px;\r\n            background-color: #009B4A;\r\n            color: #ffffff !important;\r\n            text-decoration: none;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            font-weight: 500;\r\n            font-size: 14px;\r\n        }\r\n        .btn:hover {\r\n            background-color: #009B4A;\r\n        }\r\n        .btn-center {\r\n            text-align: center;\r\n            margin: 25px 0;\r\n        }\r\n        .stats-container {\r\n            display: table;\r\n            width: 100%;\r\n            margin: 20px 0;\r\n        }\r\n        .stat-box {\r\n            display: table-cell;\r\n            width: 33.33%;\r\n            padding: 15px;\r\n            text-align: center;\r\n            border-radius: 8px;\r\n        }\r\n        .stat-box.success { background: #e8f5e9; }\r\n        .stat-box.warning { background: #fff3e0; }\r\n        .stat-box.danger { background: #ffebee; }\r\n        .stat-number {\r\n            font-size: 32px;\r\n            font-weight: bold;\r\n            display: block;\r\n        }\r\n        .stat-box.success .stat-number { color: #009B4A; }\r\n        .stat-box.warning .stat-number { color: #666666; }\r\n        .stat-box.danger .stat-number { color: #000000; }\r\n        .stat-label {\r\n            font-size: 12px;\r\n            color: #666;\r\n            text-transform: uppercase;\r\n        }\r\n        .footer {\r\n            margin-top: 30px;\r\n            padding: 20px 30px;\r\n            background: #f8f9fa;\r\n            border-top: 1px solid #eee;\r\n            text-align: center;\r\n            font-size: 12px;\r\n            color: #999;\r\n        }\r\n        .footer a {\r\n            color: #009B4A;\r\n            text-decoration: none;\r\n        }\r\n        .divider {\r\n            height: 1px;\r\n            background: #eee;\r\n            margin: 20px 0;\r\n        }\r\n        .upcoming-list {\r\n            margin: 15px 0;\r\n            padding: 0;\r\n            list-style: none;\r\n        }\r\n        .upcoming-list li {\r\n            padding: 10px 0;\r\n            border-bottom: 1px solid #eee;\r\n        }\r\n        .upcoming-list li:last-child {\r\n            border-bottom: none;\r\n        }\r\n        .date-badge {\r\n            display: inline-block;\r\n            background: #e3f2fd;\r\n            color: #009B4A;\r\n            padding: 2px 8px;\r\n            border-radius: 4px;\r\n            font-size: 12px;\r\n            margin-left: 10px;\r\n        }\r\n        @media only screen and (max-width: 600px) {\r\n            .content { padding: 20px; }\r\n            .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n        }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-wrapper\">\r\n        <div class=\"email-container\">\r\n            <div class=\"header\">\r\n                <h1>Sistema de Gestión de Tareas</h1>\r\n                <div class=\"subtitle\">Nuevo proyecto asignado</div>\r\n            </div>\r\n            <div class=\"content\">\r\n                \r\n            <p class=\"greeting\">Hola <strong>prueba</strong>,</p>\r\n            <p>Has sido asignado a un nuevo proyecto:</p>\r\n            \r\n            <div class=\"task-card\" style=\"border-left-color: #009B4A;\">\r\n                <h3>2005 2006</h3>\r\n                <p>descripcion 2005 2006</p>\r\n                <div class=\"divider\"></div>\r\n                <div class=\"meta-info\">\r\n                    <p><strong>Departamento:</strong> Deportes</p>\r\n                    <p><strong>Fecha límite:</strong> 28/12/2025</p>\r\n                    <p><strong>Creado por:</strong> Jhon Doe</p>\r\n                </div>\r\n            </div>\r\n            \r\n            <div class=\"btn-center\">\r\n                <a href=\"http://localhost/task_management\" class=\"btn\" style=\"background-color: #009B4A;\">Ver Proyecto</a>\r\n            </div>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>Este es un mensaje automático del Sistema de Gestión de Tareas.</p>\r\n                <p>Por favor no responda directamente a este correo.</p>\r\n                <p style=\"margin-top: 10px;\">\r\n                    <a href=\"http://localhost/task_management\">Acceder al Sistema</a>\r\n                </p>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Asignado a proyecto: 2005 2006\r\n \r\n * {\r\n margin: 0;\r\n padding: 0;\r\n box-sizing: border-box;\r\n }\r\n body { \r\n font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n line-height: 1.6;\r\n color: #333333;\r\n background-color: #f5f5f5;\r\n padding: 20px;\r\n }\r\n .email-wrapper {\r\n max-width: 600px;\r\n margin: 0 auto;\r\n }\r\n .email-container {\r\n background: #ffffff;\r\n border-radius: 8px;\r\n overflow: hidden;\r\n box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n }\r\n .header {\r\n background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n color: #ffffff;\r\n padding: 25px 30px;\r\n text-align: center;\r\n }\r\n .header h1 {\r\n margin: 0;\r\n font-size: 22px;\r\n font-weight: 600;\r\n }\r\n .header .subtitle {\r\n font-size: 14px;\r\n opacity: 0.9;\r\n margin-top: 5px;\r\n }\r\n .content {\r\n padding: 30px;\r\n }\r\n .greeting {\r\n font-size: 18px;\r\n margin-bottom: 20px;\r\n }\r\n .task-card {\r\n background: #f8f9fa;\r\n border-left: 4px solid #009B4A;\r\n padding: 20px;\r\n margin: 20px 0;\r\n border-radius: 0 8px 8px 0;\r\n }\r\n .task-card.urgent {\r\n border-left-color: #000000;\r\n background: #fff5f5;\r\n }\r\n .task-card.warning {\r\n border-left-color: #666666;\r\n background: #fff8e1;\r\n }\r\n .task-card h3 {\r\n margin: 0 0 10px 0;\r\n color: #333;\r\n font-size: 18px;\r\n }\r\n .task-card p {\r\n margin: 0 0 10px 0;\r\n color: #666;\r\n }\r\n .meta-info {\r\n font-size: 14px;\r\n color: #666;\r\n }\r\n .meta-info strong {\r\n color: #333;\r\n }\r\n .btn {\r\n display: inline-block;\r\n padding: 12px 28px;\r\n background-color: #009B4A;\r\n color: #ffffff !important;\r\n text-decoration: none;\r\n border-radius: 6px;\r\n margin-top: 20px;\r\n font-weight: 500;\r\n font-size: 14px;\r\n }\r\n .btn:hover {\r\n background-color: #009B4A;\r\n }\r\n .btn-center {\r\n text-align: center;\r\n margin: 25px 0;\r\n }\r\n .stats-container {\r\n display: table;\r\n width: 100%;\r\n margin: 20px 0;\r\n }\r\n .stat-box {\r\n display: table-cell;\r\n width: 33.33%;\r\n padding: 15px;\r\n text-align: center;\r\n border-radius: 8px;\r\n }\r\n .stat-box.success { background: #e8f5e9; }\r\n .stat-box.warning { background: #fff3e0; }\r\n .stat-box.danger { background: #ffebee; }\r\n .stat-number {\r\n font-size: 32px;\r\n font-weight: bold;\r\n display: block;\r\n }\r\n .stat-box.success .stat-number { color: #009B4A; }\r\n .stat-box.warning .stat-number { color: #666666; }\r\n .stat-box.danger .stat-number { color: #000000; }\r\n .stat-label {\r\n font-size: 12px;\r\n color: #666;\r\n text-transform: uppercase;\r\n }\r\n .footer {\r\n margin-top: 30px;\r\n padding: 20px 30px;\r\n background: #f8f9fa;\r\n border-top: 1px solid #eee;\r\n text-align: center;\r\n font-size: 12px;\r\n color: #999;\r\n }\r\n .footer a {\r\n color: #009B4A;\r\n text-decoration: none;\r\n }\r\n .divider {\r\n height: 1px;\r\n background: #eee;\r\n margin: 20px 0;\r\n }\r\n .upcoming-list {\r\n margin: 15px 0;\r\n padding: 0;\r\n list-style: none;\r\n }\r\n .upcoming-list li {\r\n padding: 10px 0;\r\n border-bottom: 1px solid #eee;\r\n }\r\n .upcoming-list li:last-child {\r\n border-bottom: none;\r\n }\r\n .date-badge {\r\n display: inline-block;\r\n background: #e3f2fd;\r\n color: #009B4A;\r\n padding: 2px 8px;\r\n border-radius: 4px;\r\n font-size: 12px;\r\n margin-left: 10px;\r\n }\r\n @media only screen and (max-width: 600px) {\r\n .content { padding: 20px; }\r\n .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n }\r\n \r\n\r\n\r\n \r\n \r\n \r\n Sistema de Gestión de Tareas\r\n Nuevo proyecto asignado\n\n\r\n \n\n\r\n \r\n \r\n Hola prueba,\n\n\r\n Has sido asignado a un nuevo proyecto:\n\n\r\n \r\n \r\n 2005 2006\r\n descripcion 2005 2006\n\n\r\n \n\n\r\n \r\n Departamento: Deportes\n\n\r\n Fecha límite: 28/12/2025\n\n\r\n Creado por: Jhon Doe\n\n\r\n \n\n\r\n \n\n\r\n \r\n \r\n Ver Proyecto\r\n \n\n\r\n \n\n\r\n \r\n Este es un mensaje automático del Sistema de Gestión de Tareas.\n\n\r\n Por favor no responda directamente a este correo.\n\n\r\n \r\n Acceder al Sistema', 'proyecto_asignado', 3, 'pendiente', 0, 3, NULL, 'proyecto', 19, '2025-12-25 19:16:33', NULL, '2025-12-25 18:16:33'),
(0, 'jesus.dominguez@nidec-motor.com', 'Jesus Dominguez', 'Asignado a proyecto: Proyecto para jesus', '\r\n<!DOCTYPE html>\r\n<html lang=\"es\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n    <title>Asignado a proyecto: Proyecto para jesus</title>\r\n    <style>\r\n        * {\r\n            margin: 0;\r\n            padding: 0;\r\n            box-sizing: border-box;\r\n        }\r\n        body { \r\n            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n            line-height: 1.6;\r\n            color: #333333;\r\n            background-color: #f5f5f5;\r\n            padding: 20px;\r\n        }\r\n        .email-wrapper {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n        }\r\n        .email-container {\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n        }\r\n        .header {\r\n            background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n            color: #ffffff;\r\n            padding: 25px 30px;\r\n            text-align: center;\r\n        }\r\n        .header h1 {\r\n            margin: 0;\r\n            font-size: 22px;\r\n            font-weight: 600;\r\n        }\r\n        .header .subtitle {\r\n            font-size: 14px;\r\n            opacity: 0.9;\r\n            margin-top: 5px;\r\n        }\r\n        .content {\r\n            padding: 30px;\r\n        }\r\n        .greeting {\r\n            font-size: 18px;\r\n            margin-bottom: 20px;\r\n        }\r\n        .task-card {\r\n            background: #f8f9fa;\r\n            border-left: 4px solid #009B4A;\r\n            padding: 20px;\r\n            margin: 20px 0;\r\n            border-radius: 0 8px 8px 0;\r\n        }\r\n        .task-card.urgent {\r\n            border-left-color: #000000;\r\n            background: #fff5f5;\r\n        }\r\n        .task-card.warning {\r\n            border-left-color: #666666;\r\n            background: #fff8e1;\r\n        }\r\n        .task-card h3 {\r\n            margin: 0 0 10px 0;\r\n            color: #333;\r\n            font-size: 18px;\r\n        }\r\n        .task-card p {\r\n            margin: 0 0 10px 0;\r\n            color: #666;\r\n        }\r\n        .meta-info {\r\n            font-size: 14px;\r\n            color: #666;\r\n        }\r\n        .meta-info strong {\r\n            color: #333;\r\n        }\r\n        .btn {\r\n            display: inline-block;\r\n            padding: 12px 28px;\r\n            background-color: #009B4A;\r\n            color: #ffffff !important;\r\n            text-decoration: none;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            font-weight: 500;\r\n            font-size: 14px;\r\n        }\r\n        .btn:hover {\r\n            background-color: #009B4A;\r\n        }\r\n        .btn-center {\r\n            text-align: center;\r\n            margin: 25px 0;\r\n        }\r\n        .stats-container {\r\n            display: table;\r\n            width: 100%;\r\n            margin: 20px 0;\r\n        }\r\n        .stat-box {\r\n            display: table-cell;\r\n            width: 33.33%;\r\n            padding: 15px;\r\n            text-align: center;\r\n            border-radius: 8px;\r\n        }\r\n        .stat-box.success { background: #e8f5e9; }\r\n        .stat-box.warning { background: #fff3e0; }\r\n        .stat-box.danger { background: #ffebee; }\r\n        .stat-number {\r\n            font-size: 32px;\r\n            font-weight: bold;\r\n            display: block;\r\n        }\r\n        .stat-box.success .stat-number { color: #009B4A; }\r\n        .stat-box.warning .stat-number { color: #666666; }\r\n        .stat-box.danger .stat-number { color: #000000; }\r\n        .stat-label {\r\n            font-size: 12px;\r\n            color: #666;\r\n            text-transform: uppercase;\r\n        }\r\n        .footer {\r\n            margin-top: 30px;\r\n            padding: 20px 30px;\r\n            background: #f8f9fa;\r\n            border-top: 1px solid #eee;\r\n            text-align: center;\r\n            font-size: 12px;\r\n            color: #999;\r\n        }\r\n        .footer a {\r\n            color: #009B4A;\r\n            text-decoration: none;\r\n        }\r\n        .divider {\r\n            height: 1px;\r\n            background: #eee;\r\n            margin: 20px 0;\r\n        }\r\n        .upcoming-list {\r\n            margin: 15px 0;\r\n            padding: 0;\r\n            list-style: none;\r\n        }\r\n        .upcoming-list li {\r\n            padding: 10px 0;\r\n            border-bottom: 1px solid #eee;\r\n        }\r\n        .upcoming-list li:last-child {\r\n            border-bottom: none;\r\n        }\r\n        .date-badge {\r\n            display: inline-block;\r\n            background: #e3f2fd;\r\n            color: #009B4A;\r\n            padding: 2px 8px;\r\n            border-radius: 4px;\r\n            font-size: 12px;\r\n            margin-left: 10px;\r\n        }\r\n        @media only screen and (max-width: 600px) {\r\n            .content { padding: 20px; }\r\n            .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n        }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-wrapper\">\r\n        <div class=\"email-container\">\r\n            <div class=\"header\">\r\n                <h1>Sistema de Gestión de Tareas</h1>\r\n                <div class=\"subtitle\">Nuevo proyecto asignado</div>\r\n            </div>\r\n            <div class=\"content\">\r\n                \r\n            <p class=\"greeting\">Hola <strong>Jesus</strong>,</p>\r\n            <p>Has sido asignado a un nuevo proyecto:</p>\r\n            \r\n            <div class=\"task-card\" style=\"border-left-color: #009B4A;\">\r\n                <h3>Proyecto para jesus</h3>\r\n                <p>descripcion de proyecto para jesus</p>\r\n                <div class=\"divider\"></div>\r\n                <div class=\"meta-info\">\r\n                    <p><strong>Departamento:</strong> Recursos humanos</p>\r\n                    <p><strong>Fecha límite:</strong> 31/12/2025</p>\r\n                    <p><strong>Creado por:</strong> Margarita Barbosa</p>\r\n                </div>\r\n            </div>\r\n            \r\n            <div class=\"btn-center\">\r\n                <a href=\"http://localhost/task_management\" class=\"btn\" style=\"background-color: #009B4A;\">Ver Proyecto</a>\r\n            </div>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>Este es un mensaje automático del Sistema de Gestión de Tareas.</p>\r\n                <p>Por favor no responda directamente a este correo.</p>\r\n                <p style=\"margin-top: 10px;\">\r\n                    <a href=\"http://localhost/task_management\">Acceder al Sistema</a>\r\n                </p>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Asignado a proyecto: Proyecto para jesus\r\n \r\n * {\r\n margin: 0;\r\n padding: 0;\r\n box-sizing: border-box;\r\n }\r\n body { \r\n font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n line-height: 1.6;\r\n color: #333333;\r\n background-color: #f5f5f5;\r\n padding: 20px;\r\n }\r\n .email-wrapper {\r\n max-width: 600px;\r\n margin: 0 auto;\r\n }\r\n .email-container {\r\n background: #ffffff;\r\n border-radius: 8px;\r\n overflow: hidden;\r\n box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n }\r\n .header {\r\n background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n color: #ffffff;\r\n padding: 25px 30px;\r\n text-align: center;\r\n }\r\n .header h1 {\r\n margin: 0;\r\n font-size: 22px;\r\n font-weight: 600;\r\n }\r\n .header .subtitle {\r\n font-size: 14px;\r\n opacity: 0.9;\r\n margin-top: 5px;\r\n }\r\n .content {\r\n padding: 30px;\r\n }\r\n .greeting {\r\n font-size: 18px;\r\n margin-bottom: 20px;\r\n }\r\n .task-card {\r\n background: #f8f9fa;\r\n border-left: 4px solid #009B4A;\r\n padding: 20px;\r\n margin: 20px 0;\r\n border-radius: 0 8px 8px 0;\r\n }\r\n .task-card.urgent {\r\n border-left-color: #000000;\r\n background: #fff5f5;\r\n }\r\n .task-card.warning {\r\n border-left-color: #666666;\r\n background: #fff8e1;\r\n }\r\n .task-card h3 {\r\n margin: 0 0 10px 0;\r\n color: #333;\r\n font-size: 18px;\r\n }\r\n .task-card p {\r\n margin: 0 0 10px 0;\r\n color: #666;\r\n }\r\n .meta-info {\r\n font-size: 14px;\r\n color: #666;\r\n }\r\n .meta-info strong {\r\n color: #333;\r\n }\r\n .btn {\r\n display: inline-block;\r\n padding: 12px 28px;\r\n background-color: #009B4A;\r\n color: #ffffff !important;\r\n text-decoration: none;\r\n border-radius: 6px;\r\n margin-top: 20px;\r\n font-weight: 500;\r\n font-size: 14px;\r\n }\r\n .btn:hover {\r\n background-color: #009B4A;\r\n }\r\n .btn-center {\r\n text-align: center;\r\n margin: 25px 0;\r\n }\r\n .stats-container {\r\n display: table;\r\n width: 100%;\r\n margin: 20px 0;\r\n }\r\n .stat-box {\r\n display: table-cell;\r\n width: 33.33%;\r\n padding: 15px;\r\n text-align: center;\r\n border-radius: 8px;\r\n }\r\n .stat-box.success { background: #e8f5e9; }\r\n .stat-box.warning { background: #fff3e0; }\r\n .stat-box.danger { background: #ffebee; }\r\n .stat-number {\r\n font-size: 32px;\r\n font-weight: bold;\r\n display: block;\r\n }\r\n .stat-box.success .stat-number { color: #009B4A; }\r\n .stat-box.warning .stat-number { color: #666666; }\r\n .stat-box.danger .stat-number { color: #000000; }\r\n .stat-label {\r\n font-size: 12px;\r\n color: #666;\r\n text-transform: uppercase;\r\n }\r\n .footer {\r\n margin-top: 30px;\r\n padding: 20px 30px;\r\n background: #f8f9fa;\r\n border-top: 1px solid #eee;\r\n text-align: center;\r\n font-size: 12px;\r\n color: #999;\r\n }\r\n .footer a {\r\n color: #009B4A;\r\n text-decoration: none;\r\n }\r\n .divider {\r\n height: 1px;\r\n background: #eee;\r\n margin: 20px 0;\r\n }\r\n .upcoming-list {\r\n margin: 15px 0;\r\n padding: 0;\r\n list-style: none;\r\n }\r\n .upcoming-list li {\r\n padding: 10px 0;\r\n border-bottom: 1px solid #eee;\r\n }\r\n .upcoming-list li:last-child {\r\n border-bottom: none;\r\n }\r\n .date-badge {\r\n display: inline-block;\r\n background: #e3f2fd;\r\n color: #009B4A;\r\n padding: 2px 8px;\r\n border-radius: 4px;\r\n font-size: 12px;\r\n margin-left: 10px;\r\n }\r\n @media only screen and (max-width: 600px) {\r\n .content { padding: 20px; }\r\n .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n }\r\n \r\n\r\n\r\n \r\n \r\n \r\n Sistema de Gestión de Tareas\r\n Nuevo proyecto asignado\n\n\r\n \n\n\r\n \r\n \r\n Hola Jesus,\n\n\r\n Has sido asignado a un nuevo proyecto:\n\n\r\n \r\n \r\n Proyecto para jesus\r\n descripcion de proyecto para jesus\n\n\r\n \n\n\r\n \r\n Departamento: Recursos humanos\n\n\r\n Fecha límite: 31/12/2025\n\n\r\n Creado por: Margarita Barbosa\n\n\r\n \n\n\r\n \n\n\r\n \r\n \r\n Ver Proyecto\r\n \n\n\r\n \n\n\r\n \r\n Este es un mensaje automático del Sistema de Gestión de Tareas.\n\n\r\n Por favor no responda directamente a este correo.\n\n\r\n \r\n Acceder al Sistema', 'proyecto_asignado', 3, 'pendiente', 0, 3, NULL, 'proyecto', 21, '2025-12-27 16:18:22', NULL, '2025-12-27 15:18:22');
INSERT INTO `tbl_email_queue` (`id_email`, `destinatario_email`, `destinatario_nombre`, `asunto`, `cuerpo_html`, `cuerpo_texto`, `tipo_notificacion`, `prioridad`, `estado`, `intentos`, `max_intentos`, `ultimo_error`, `referencia_tipo`, `referencia_id`, `programado_para`, `enviado_at`, `fecha_creacion`) VALUES
(0, 'jesus.dominguez@nidec-motor.com', 'Jesus Dominguez', 'Nueva tarea asignada: Tarea para jesus', '\r\n<!DOCTYPE html>\r\n<html lang=\"es\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n    <title>Nueva tarea asignada: Tarea para jesus</title>\r\n    <style>\r\n        * {\r\n            margin: 0;\r\n            padding: 0;\r\n            box-sizing: border-box;\r\n        }\r\n        body { \r\n            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n            line-height: 1.6;\r\n            color: #333333;\r\n            background-color: #f5f5f5;\r\n            padding: 20px;\r\n        }\r\n        .email-wrapper {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n        }\r\n        .email-container {\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n        }\r\n        .header {\r\n            background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n            color: #ffffff;\r\n            padding: 25px 30px;\r\n            text-align: center;\r\n        }\r\n        .header h1 {\r\n            margin: 0;\r\n            font-size: 22px;\r\n            font-weight: 600;\r\n        }\r\n        .header .subtitle {\r\n            font-size: 14px;\r\n            opacity: 0.9;\r\n            margin-top: 5px;\r\n        }\r\n        .content {\r\n            padding: 30px;\r\n        }\r\n        .greeting {\r\n            font-size: 18px;\r\n            margin-bottom: 20px;\r\n        }\r\n        .task-card {\r\n            background: #f8f9fa;\r\n            border-left: 4px solid #009B4A;\r\n            padding: 20px;\r\n            margin: 20px 0;\r\n            border-radius: 0 8px 8px 0;\r\n        }\r\n        .task-card.urgent {\r\n            border-left-color: #000000;\r\n            background: #fff5f5;\r\n        }\r\n        .task-card.warning {\r\n            border-left-color: #666666;\r\n            background: #fff8e1;\r\n        }\r\n        .task-card h3 {\r\n            margin: 0 0 10px 0;\r\n            color: #333;\r\n            font-size: 18px;\r\n        }\r\n        .task-card p {\r\n            margin: 0 0 10px 0;\r\n            color: #666;\r\n        }\r\n        .meta-info {\r\n            font-size: 14px;\r\n            color: #666;\r\n        }\r\n        .meta-info strong {\r\n            color: #333;\r\n        }\r\n        .btn {\r\n            display: inline-block;\r\n            padding: 12px 28px;\r\n            background-color: #009B4A;\r\n            color: #ffffff !important;\r\n            text-decoration: none;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            font-weight: 500;\r\n            font-size: 14px;\r\n        }\r\n        .btn:hover {\r\n            background-color: #009B4A;\r\n        }\r\n        .btn-center {\r\n            text-align: center;\r\n            margin: 25px 0;\r\n        }\r\n        .stats-container {\r\n            display: table;\r\n            width: 100%;\r\n            margin: 20px 0;\r\n        }\r\n        .stat-box {\r\n            display: table-cell;\r\n            width: 33.33%;\r\n            padding: 15px;\r\n            text-align: center;\r\n            border-radius: 8px;\r\n        }\r\n        .stat-box.success { background: #e8f5e9; }\r\n        .stat-box.warning { background: #fff3e0; }\r\n        .stat-box.danger { background: #ffebee; }\r\n        .stat-number {\r\n            font-size: 32px;\r\n            font-weight: bold;\r\n            display: block;\r\n        }\r\n        .stat-box.success .stat-number { color: #009B4A; }\r\n        .stat-box.warning .stat-number { color: #666666; }\r\n        .stat-box.danger .stat-number { color: #000000; }\r\n        .stat-label {\r\n            font-size: 12px;\r\n            color: #666;\r\n            text-transform: uppercase;\r\n        }\r\n        .footer {\r\n            margin-top: 30px;\r\n            padding: 20px 30px;\r\n            background: #f8f9fa;\r\n            border-top: 1px solid #eee;\r\n            text-align: center;\r\n            font-size: 12px;\r\n            color: #999;\r\n        }\r\n        .footer a {\r\n            color: #009B4A;\r\n            text-decoration: none;\r\n        }\r\n        .divider {\r\n            height: 1px;\r\n            background: #eee;\r\n            margin: 20px 0;\r\n        }\r\n        .upcoming-list {\r\n            margin: 15px 0;\r\n            padding: 0;\r\n            list-style: none;\r\n        }\r\n        .upcoming-list li {\r\n            padding: 10px 0;\r\n            border-bottom: 1px solid #eee;\r\n        }\r\n        .upcoming-list li:last-child {\r\n            border-bottom: none;\r\n        }\r\n        .date-badge {\r\n            display: inline-block;\r\n            background: #e3f2fd;\r\n            color: #009B4A;\r\n            padding: 2px 8px;\r\n            border-radius: 4px;\r\n            font-size: 12px;\r\n            margin-left: 10px;\r\n        }\r\n        @media only screen and (max-width: 600px) {\r\n            .content { padding: 20px; }\r\n            .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n        }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-wrapper\">\r\n        <div class=\"email-container\">\r\n            <div class=\"header\">\r\n                <h1>Sistema de Gestión de Tareas</h1>\r\n                <div class=\"subtitle\">Nueva tarea asignada</div>\r\n            </div>\r\n            <div class=\"content\">\r\n                \r\n            <p class=\"greeting\">Hola <strong>Jesus</strong>,</p>\r\n            <p>Se te ha asignado una nueva tarea en el sistema:</p>\r\n            \r\n            <div class=\"task-card\">\r\n                <h3>Tarea para jesus</h3>\r\n                <p>Descripcion de tarea para jesus</p>\r\n                <div class=\"divider\"></div>\r\n                <div class=\"meta-info\">\r\n                    <p><strong>Proyecto:</strong> Proyecto para jesus</p>\r\n                    <p><strong>Fecha límite:</strong> 30/12/2025</p>\r\n                    <p><strong>Asignado por:</strong> Margarita Barbosa</p>\r\n                </div>\r\n            </div>\r\n            \r\n            <div class=\"btn-center\">\r\n                <a href=\"http://localhost/task_management\" class=\"btn\">Ver Tarea</a>\r\n            </div>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>Este es un mensaje automático del Sistema de Gestión de Tareas.</p>\r\n                <p>Por favor no responda directamente a este correo.</p>\r\n                <p style=\"margin-top: 10px;\">\r\n                    <a href=\"http://localhost/task_management\">Acceder al Sistema</a>\r\n                </p>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Nueva tarea asignada: Tarea para jesus\r\n \r\n * {\r\n margin: 0;\r\n padding: 0;\r\n box-sizing: border-box;\r\n }\r\n body { \r\n font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\r\n line-height: 1.6;\r\n color: #333333;\r\n background-color: #f5f5f5;\r\n padding: 20px;\r\n }\r\n .email-wrapper {\r\n max-width: 600px;\r\n margin: 0 auto;\r\n }\r\n .email-container {\r\n background: #ffffff;\r\n border-radius: 8px;\r\n overflow: hidden;\r\n box-shadow: 0 2px 8px rgba(0,0,0,0.1);\r\n }\r\n .header {\r\n background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);\r\n color: #ffffff;\r\n padding: 25px 30px;\r\n text-align: center;\r\n }\r\n .header h1 {\r\n margin: 0;\r\n font-size: 22px;\r\n font-weight: 600;\r\n }\r\n .header .subtitle {\r\n font-size: 14px;\r\n opacity: 0.9;\r\n margin-top: 5px;\r\n }\r\n .content {\r\n padding: 30px;\r\n }\r\n .greeting {\r\n font-size: 18px;\r\n margin-bottom: 20px;\r\n }\r\n .task-card {\r\n background: #f8f9fa;\r\n border-left: 4px solid #009B4A;\r\n padding: 20px;\r\n margin: 20px 0;\r\n border-radius: 0 8px 8px 0;\r\n }\r\n .task-card.urgent {\r\n border-left-color: #000000;\r\n background: #fff5f5;\r\n }\r\n .task-card.warning {\r\n border-left-color: #666666;\r\n background: #fff8e1;\r\n }\r\n .task-card h3 {\r\n margin: 0 0 10px 0;\r\n color: #333;\r\n font-size: 18px;\r\n }\r\n .task-card p {\r\n margin: 0 0 10px 0;\r\n color: #666;\r\n }\r\n .meta-info {\r\n font-size: 14px;\r\n color: #666;\r\n }\r\n .meta-info strong {\r\n color: #333;\r\n }\r\n .btn {\r\n display: inline-block;\r\n padding: 12px 28px;\r\n background-color: #009B4A;\r\n color: #ffffff !important;\r\n text-decoration: none;\r\n border-radius: 6px;\r\n margin-top: 20px;\r\n font-weight: 500;\r\n font-size: 14px;\r\n }\r\n .btn:hover {\r\n background-color: #009B4A;\r\n }\r\n .btn-center {\r\n text-align: center;\r\n margin: 25px 0;\r\n }\r\n .stats-container {\r\n display: table;\r\n width: 100%;\r\n margin: 20px 0;\r\n }\r\n .stat-box {\r\n display: table-cell;\r\n width: 33.33%;\r\n padding: 15px;\r\n text-align: center;\r\n border-radius: 8px;\r\n }\r\n .stat-box.success { background: #e8f5e9; }\r\n .stat-box.warning { background: #fff3e0; }\r\n .stat-box.danger { background: #ffebee; }\r\n .stat-number {\r\n font-size: 32px;\r\n font-weight: bold;\r\n display: block;\r\n }\r\n .stat-box.success .stat-number { color: #009B4A; }\r\n .stat-box.warning .stat-number { color: #666666; }\r\n .stat-box.danger .stat-number { color: #000000; }\r\n .stat-label {\r\n font-size: 12px;\r\n color: #666;\r\n text-transform: uppercase;\r\n }\r\n .footer {\r\n margin-top: 30px;\r\n padding: 20px 30px;\r\n background: #f8f9fa;\r\n border-top: 1px solid #eee;\r\n text-align: center;\r\n font-size: 12px;\r\n color: #999;\r\n }\r\n .footer a {\r\n color: #009B4A;\r\n text-decoration: none;\r\n }\r\n .divider {\r\n height: 1px;\r\n background: #eee;\r\n margin: 20px 0;\r\n }\r\n .upcoming-list {\r\n margin: 15px 0;\r\n padding: 0;\r\n list-style: none;\r\n }\r\n .upcoming-list li {\r\n padding: 10px 0;\r\n border-bottom: 1px solid #eee;\r\n }\r\n .upcoming-list li:last-child {\r\n border-bottom: none;\r\n }\r\n .date-badge {\r\n display: inline-block;\r\n background: #e3f2fd;\r\n color: #009B4A;\r\n padding: 2px 8px;\r\n border-radius: 4px;\r\n font-size: 12px;\r\n margin-left: 10px;\r\n }\r\n @media only screen and (max-width: 600px) {\r\n .content { padding: 20px; }\r\n .stat-box { display: block; width: 100%; margin-bottom: 10px; }\r\n }\r\n \r\n\r\n\r\n \r\n \r\n \r\n Sistema de Gestión de Tareas\r\n Nueva tarea asignada\n\n\r\n \n\n\r\n \r\n \r\n Hola Jesus,\n\n\r\n Se te ha asignado una nueva tarea en el sistema:\n\n\r\n \r\n \r\n Tarea para jesus\r\n Descripcion de tarea para jesus\n\n\r\n \n\n\r\n \r\n Proyecto: Proyecto para jesus\n\n\r\n Fecha límite: 30/12/2025\n\n\r\n Asignado por: Margarita Barbosa\n\n\r\n \n\n\r\n \n\n\r\n \r\n \r\n Ver Tarea\r\n \n\n\r\n \n\n\r\n \r\n Este es un mensaje automático del Sistema de Gestión de Tareas.\n\n\r\n Por favor no responda directamente a este correo.\n\n\r\n \r\n Acceder al Sistema', 'tarea_asignada', 2, 'pendiente', 0, 3, NULL, 'tarea', 13, '2025-12-27 16:18:53', NULL, '2025-12-27 15:18:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_notificaciones`
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
-- Volcado de datos para la tabla `tbl_notificaciones`
--

INSERT INTO `tbl_notificaciones` (`id_notificacion`, `id_usuario`, `tipo`, `titulo`, `mensaje`, `id_referencia`, `tipo_referencia`, `leido`, `fecha_creacion`, `fecha_lectura`) VALUES
(2, 7, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'prueba\'.', 13, 'proyecto', 0, '2025-12-25 16:39:43', NULL),
(3, 7, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'prueba\'.', 14, 'proyecto', 0, '2025-12-25 16:42:42', NULL),
(4, 7, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'prueba\'.', 15, 'proyecto', 0, '2025-12-25 16:43:20', NULL),
(5, 7, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'prueba\'.', 16, 'proyecto', 0, '2025-12-25 16:43:41', NULL),
(6, 7, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'prueba\'.', 17, 'proyecto', 0, '2025-12-25 16:44:09', NULL),
(7, 7, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'ganar al spanyol\'.', 18, 'proyecto', 0, '2025-12-25 17:44:57', NULL),
(8, 7, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'2005 2006\'.', 19, 'proyecto', 0, '2025-12-25 18:16:33', NULL),
(9, 9, 'proyecto_asignado', 'Nuevo proyecto asignado', 'Se te ha asignado el proyecto \'Proyecto para jesus\'.', 21, 'proyecto', 0, '2025-12-27 15:18:22', NULL),
(10, 9, 'tarea_asignada', 'Nueva tarea asignada', 'Se te ha asignado la tarea \'Tarea para jesus\' en el proyecto \'Proyecto para jesus\'.', 13, 'tarea', 0, '2025-12-27 15:18:52', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_notificaciones_config`
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
-- Estructura de tabla para la tabla `tbl_notificaciones_enviadas`
--

CREATE TABLE `tbl_notificaciones_enviadas` (
  `id` int(11) NOT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `id_referencia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_notificacion_preferencias`
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
-- Volcado de datos para la tabla `tbl_notificacion_preferencias`
--

INSERT INTO `tbl_notificacion_preferencias` (`id_preferencia`, `id_usuario`, `notif_tarea_asignada`, `notif_tarea_vencimiento`, `notif_tarea_vencida`, `notif_tarea_completada`, `notif_proyecto_asignado`, `notif_resumen_diario`, `notif_resumen_semanal`, `hora_preferida`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(0, 8, 1, 1, 1, 1, 1, 0, 1, '09:00:00', '2025-12-27 15:14:24', '2025-12-27 15:14:24'),
(0, 9, 1, 1, 1, 1, 1, 0, 1, '09:00:00', '2025-12-27 15:15:20', '2025-12-27 15:15:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_objetivos`
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
-- Volcado de datos para la tabla `tbl_objetivos`
--

INSERT INTO `tbl_objetivos` (`id_objetivo`, `nombre`, `descripcion`, `id_departamento`, `fecha_inicio`, `fecha_cumplimiento`, `estado`, `ar`, `archivo_adjunto`, `id_creador`, `fecha_creacion`) VALUES
(1, 'Desarrollo de sistema de tareas', 'Desarrollo e implementación de aplicación web para la creación y manejo de proyectos', 1, '2025-11-07 11:43:37', '2025-11-20', 'pendiente', 0x31323334353637383839, '', 1, '2025-11-09 14:44:11'),
(2, 'prueba', 'prueba de objetivo', 1, '2025-11-17 10:31:52', '2025-11-21', 'completado', '', '', 1, '2025-11-17 16:31:52'),
(3, 'Ganar la champions', 'Descripcion de ganar la champions', 2, '2025-11-29 20:04:40', '2025-12-05', 'completado', '', '', 4, '2025-11-30 02:04:40'),
(5, 'Objetivo de relleno', 'Descripcion de objetivo de relleno', 2, '2025-12-09 18:00:54', '2025-12-16', 'pendiente', '', '', 1, '2025-12-10 00:00:54'),
(6, 'Otro objetivo de relleno', 'Descripcion del otro objetivo de relleno', 2, '2025-12-09 18:01:23', '2025-12-14', 'pendiente', '', '', 1, '2025-12-10 00:01:23'),
(7, 'endrick boby', 'madrid lyon', 1, '2025-12-25 14:04:45', '2025-12-27', 'pendiente', '', '', 3, '2025-12-25 20:04:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_proyectos`
--

CREATE TABLE `tbl_proyectos` (
  `id_proyecto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `id_departamento` int(11) NOT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
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
-- Volcado de datos para la tabla `tbl_proyectos`
--

INSERT INTO `tbl_proyectos` (`id_proyecto`, `nombre`, `descripcion`, `id_departamento`, `fecha_inicio`, `fecha_cumplimiento`, `progreso`, `ar`, `estado`, `archivo_adjunto`, `id_creador`, `id_participante`, `id_tipo_proyecto`, `puede_editar_otros`, `fecha_creacion`) VALUES
(1, 'Prueba de proyecto', 'Descripcion para prueba de proyecto', 1, '2025-11-20 15:10:00', '2025-11-21', 100, 0x30, 'completado', '', 1, 3, 2, 0, '2025-11-17 21:30:59'),
(7, 'Prueba de proyecto grupal', 'Descripcion de prueba de proyecto grupal', 1, '2025-11-19 13:07:00', '2025-11-21', 100, 0x30, 'completado', '', 1, 0, 1, 0, '2025-11-17 22:25:40'),
(8, 'Prueba de asignacion de tareas', 'Descripcion de prueba de asignacion de tareas', 1, '2025-11-21 08:02:00', '2025-11-22', 0, '', 'vencido', '0', 1, 3, 2, 0, '2025-11-20 00:02:37'),
(9, 'Equipo competitivo en europa', 'Descripcion de equipo competitivo en europa', 2, '2025-12-06 14:08:00', '2025-12-16', 0, '', 'pendiente', '0', 1, 4, 2, 0, '2025-11-30 00:25:40'),
(10, 'Prueba de creacion de tareas', 'Descripcion de prueba de creacion de tareas', 2, '2025-12-19 13:07:00', '2025-12-21', 100, '', 'completado', '0', 1, 4, 2, 1, '2025-12-03 23:56:22'),
(11, 'Ganar la liga', 'Campeones de la liga', 2, '2025-12-25 06:00:00', '2025-12-31', 0, '', 'pendiente', '0', 4, 4, 2, 0, '2025-12-04 00:11:20'),
(12, 'Proyecto de muestra en dashboard', 'Descripción de proyecto de muestra en dashboard', 1, '2025-12-20 11:05:00', '2025-12-26', 100, '', 'completado', '0', 3, 3, 2, 0, '2025-12-06 23:50:38'),
(16, 'prueba', 'prueba descripcion', 2, '2025-12-26 10:04:00', '2025-12-27', 100, '', 'completado', '0', 1, 7, 2, 0, '2025-12-25 16:43:41'),
(18, 'ganar al spanyol', 'ganar al spanyol en enero', 2, '2025-12-27 10:04:00', '2025-12-31', 0, '', 'pendiente', '0', 4, 7, 2, 0, '2025-12-25 17:44:57'),
(19, '2005 2006', 'descripcion 2005 2006', 2, '2025-12-26 10:04:00', '2025-12-28', 0, '', 'pendiente', '0', 4, 7, 2, 0, '2025-12-25 18:16:33'),
(20, 'vitor roque palmeiras', 'palmeiras', 1, '2025-12-26 10:04:00', '2025-12-28', 0, '', 'pendiente', '0', 3, 3, 2, 0, '2025-12-25 19:53:22'),
(21, 'Proyecto para jesus', 'descripcion de proyecto para jesus', 3, '2025-12-29 10:04:00', '2025-12-31', 0, '', 'pendiente', '0', 8, 9, 2, 0, '2025-12-27 15:18:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_proyecto_usuarios`
--

CREATE TABLE `tbl_proyecto_usuarios` (
  `id` int(11) NOT NULL,
  `id_proyecto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_proyecto_usuarios`
--

INSERT INTO `tbl_proyecto_usuarios` (`id`, `id_proyecto`, `id_usuario`, `fecha_asignacion`) VALUES
(0, 5, 1, '2025-11-17 21:46:56'),
(0, 5, 3, '2025-11-17 21:46:56'),
(0, 6, 1, '2025-11-17 22:08:31'),
(0, 6, 3, '2025-11-17 22:08:31'),
(0, 7, 1, '2025-11-17 22:25:40'),
(0, 7, 3, '2025-11-17 22:25:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_roles`
--

CREATE TABLE `tbl_roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_roles`
--

INSERT INTO `tbl_roles` (`id_rol`, `nombre`, `descripcion`) VALUES
(1, 'administrador', 'Usuario con privilegios de creación de departamentos y usuarios'),
(2, 'gerente', 'Usuario con privilegios de creación y asignación de proyectos'),
(3, 'usuario', 'Usuario con privilegios de creación de tareas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_tareas`
--

CREATE TABLE `tbl_tareas` (
  `id_tarea` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(250) NOT NULL,
  `id_proyecto` int(11) DEFAULT NULL,
  `id_creador` int(11) NOT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_cumplimiento` date NOT NULL,
  `estado` enum('pendiente','en proceso','vencido','completado') NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_participante` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_tareas`
--

INSERT INTO `tbl_tareas` (`id_tarea`, `nombre`, `descripcion`, `id_proyecto`, `id_creador`, `fecha_inicio`, `fecha_cumplimiento`, `estado`, `fecha_creacion`, `id_participante`) VALUES
(1, 'prueba', 'prueba', NULL, 1, '2025-11-16 22:52:23', '2025-11-25', 'pendiente', '2025-11-16 22:52:23', NULL),
(2, 'Prueba de tarea', 'Descripcion de prueba de tarea', 1, 1, '2025-11-17 23:06:08', '2025-11-20', 'completado', '2025-11-17 23:06:08', 1),
(3, 'prueba de complecion', 'descripcion de prueba de complecion', 7, 1, '2025-11-17 23:23:15', '2025-11-20', 'completado', '2025-11-17 23:23:15', 1),
(4, 'Prueba de usuario en complecion', 'Descripcion de prueba de usuario en complecion', 7, 1, '2025-11-17 23:24:16', '2025-11-19', 'completado', '2025-11-17 23:24:16', 3),
(5, 'Prueba de media complecion', 'Descripcion de prueba de media complecion', 7, 1, '2025-11-17 23:25:07', '2025-11-19', 'completado', '2025-11-17 23:25:07', 1),
(6, 'Prueba de checkbox', 'Descripcion de prueba de checkbox', 8, 1, '2025-11-21 00:10:28', '0000-00-00', 'pendiente', '2025-11-21 00:10:28', 3),
(7, 'Prueba de tarea', 'Descripcion de prueba de tareas', 1, 1, '2025-11-23 14:32:03', '0000-00-00', 'completado', '2025-11-23 14:32:03', 3),
(8, 'Entrenamiento', 'Entrenamiento por la maniana', 9, 1, '2025-11-30 00:26:17', '0000-00-00', 'pendiente', '2025-11-30 00:26:17', 4),
(9, 'Ganarle al atletico de madrid', 'En el nou camp nou', 11, 4, '2025-12-04 00:18:20', '0000-00-00', 'pendiente', '2025-12-04 00:18:20', 4),
(10, 'Ganarle al frankfurt', 'En el nou camp nou', 9, 1, '2025-12-07 17:01:31', '0000-00-00', 'pendiente', '2025-12-07 17:01:31', 4),
(11, 'prueba de notificaciones', 'Descripcion de prueba de notificaciones', 8, 1, '2025-12-07 20:48:02', '0000-00-00', 'pendiente', '2025-12-07 20:48:02', 3),
(12, 'Nueva prueba de notificacion', 'Descripcion de la nueva prueba de notificacion', 8, 1, '2025-12-07 21:00:03', '0000-00-00', 'pendiente', '2025-12-07 21:00:03', 3),
(13, 'Tarea para jesus', 'Descripcion de tarea para jesus', 21, 8, '2025-12-27 15:18:52', '2025-12-30', 'pendiente', '2025-12-27 15:18:52', 9);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_tipo_proyecto`
--

CREATE TABLE `tbl_tipo_proyecto` (
  `id_tipo_proyecto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_tipo_proyecto`
--

INSERT INTO `tbl_tipo_proyecto` (`id_tipo_proyecto`, `nombre`, `descripcion`) VALUES
(1, 'Proyecto grupal', 'Proyecto que se realiza con más de dos usuarios en conjunto'),
(2, 'Proyecto individual', 'Proyecto que se realiza uno o máximo dos usuarios asignados');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_usuarios`
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
-- Volcado de datos para la tabla `tbl_usuarios`
--

INSERT INTO `tbl_usuarios` (`id_usuario`, `nombre`, `apellido`, `usuario`, `num_empleado`, `acceso`, `id_departamento`, `id_rol`, `id_superior`, `e_mail`, `foto_perfil`) VALUES
(1, 'David', 'Barreto', 'NMC10DB', 1858, '$2y$12$M/9PV/M4o7Q79NAyRHXr5e8AmqXGxYXxUFlQSBfhi/aDTvvk9yRVe', 1, 1, 0, 'david.barreto@nidec.com\r\n', NULL),
(3, 'Francisco', 'Valdez', 'FV1912', 1959, '$2y$12$OITMKo7rM5etQGp0xfXd8O2UVyZG1EGiEw.p6c5VEWWLjTcC5lFJy', 1, 3, 0, 'francisco.valdez@nidec.com', NULL),
(4, 'Jhon', 'Doe', 'JhonDoe', 9, '$2y$12$YlFaIvs6P6hT/9M55L7N1.Hloj9KVe04nC/PH7vF0wOmuEOGy3SN6', 2, 2, 0, 'JhonDoe@nidec-motor.com', NULL),
(7, 'prueba', 'de foto', 'nmc11pdf', 11, '$2y$12$D4jMR3l5JBDW.ngqs0js1.EkN3EePmp/agU9129ZXO/ybCe3sERLy', 2, 3, 4, 'hola@nidec-motor.com', 'profile_7_1766103003_08c57c614f63812f.jpg'),
(8, 'Margarita', 'Barbosa', 'NMC30MB', 30, '$2y$12$NSR8AcIcYrynMGuWIs6BL.GSC08V29JKiiK5ABnnUJdycMXO7/Xs6', 3, 2, 0, 'margarita.barbosa@nidec-motor.com', NULL),
(9, 'Jesus', 'Dominguez', 'NMC40JD', 40, '$2y$12$Z1qD8E9bjt9b3xfcHqbGweXxx8z7jLxYPMRi7rw4y80bsfnbW8/uK', 4, 2, 8, 'jesus.dominguez@nidec-motor.com', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_usuario_roles`
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
-- Volcado de datos para la tabla `tbl_usuario_roles`
--

INSERT INTO `tbl_usuario_roles` (`id_usuario_roles`, `id_usuario`, `id_departamento`, `id_rol`, `es_principal`, `activo`, `fecha_asignacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, 1, 1, 1, '2025-12-25 16:35:03', '2025-12-25 16:35:03'),
(2, 3, 1, 3, 1, 1, '2025-12-25 16:35:03', '2025-12-25 16:35:03'),
(3, 4, 2, 2, 1, 1, '2025-12-25 16:35:03', '2025-12-25 16:35:03'),
(4, 7, 2, 3, 1, 1, '2025-12-25 16:35:03', '2025-12-25 16:35:03'),
(5, 8, 3, 2, 1, 1, '2025-12-27 15:14:24', '2025-12-27 15:14:24'),
(6, 9, 4, 2, 1, 1, '2025-12-27 15:15:20', '2025-12-27 15:15:20'),
(7, 9, 3, 3, 0, 1, '2025-12-27 15:15:34', '2025-12-27 15:15:34');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_usuario_roles_completo`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_usuario_roles_completo` (
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_usuario_roles_completo`
--
DROP TABLE IF EXISTS `v_usuario_roles_completo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_usuario_roles_completo`  AS SELECT `ur`.`id` AS `id_asignacion`, `ur`.`id_usuario` AS `id_usuario`, `u`.`nombre` AS `nombre_usuario`, `u`.`apellido` AS `apellido_usuario`, `u`.`usuario` AS `username`, `u`.`e_mail` AS `e_mail`, `ur`.`id_departamento` AS `id_departamento`, `d`.`nombre` AS `nombre_departamento`, `ur`.`id_rol` AS `id_rol`, `r`.`nombre` AS `nombre_rol`, `r`.`descripcion` AS `descripcion_rol`, `ur`.`es_principal` AS `es_principal`, `ur`.`activo` AS `activo`, `ur`.`fecha_asignacion` AS `fecha_asignacion` FROM (((`tbl_usuario_roles` `ur` join `tbl_usuarios` `u` on(`ur`.`id_usuario` = `u`.`id_usuario`)) join `tbl_departamentos` `d` on(`ur`.`id_departamento` = `d`.`id_departamento`)) join `tbl_roles` `r` on(`ur`.`id_rol` = `r`.`id_rol`)) WHERE `ur`.`activo` = 1 ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `tbl_departamentos`
--
ALTER TABLE `tbl_departamentos`
  ADD PRIMARY KEY (`id_departamento`);

--
-- Indices de la tabla `tbl_notificaciones`
--
ALTER TABLE `tbl_notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_usuario_leido` (`id_usuario`,`leido`),
  ADD KEY `idx_fecha_creacion` (`fecha_creacion`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indices de la tabla `tbl_notificaciones_config`
--
ALTER TABLE `tbl_notificaciones_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_usuario` (`id_usuario`);

--
-- Indices de la tabla `tbl_notificaciones_enviadas`
--
ALTER TABLE `tbl_notificaciones_enviadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_notificacion` (`tipo_evento`,`id_referencia`,`id_usuario`),
  ADD KEY `idx_tipo_referencia` (`tipo_evento`,`id_referencia`);

--
-- Indices de la tabla `tbl_objetivos`
--
ALTER TABLE `tbl_objetivos`
  ADD PRIMARY KEY (`id_objetivo`);

--
-- Indices de la tabla `tbl_proyectos`
--
ALTER TABLE `tbl_proyectos`
  ADD PRIMARY KEY (`id_proyecto`);

--
-- Indices de la tabla `tbl_roles`
--
ALTER TABLE `tbl_roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `tbl_tareas`
--
ALTER TABLE `tbl_tareas`
  ADD PRIMARY KEY (`id_tarea`);

--
-- Indices de la tabla `tbl_tipo_proyecto`
--
ALTER TABLE `tbl_tipo_proyecto`
  ADD PRIMARY KEY (`id_tipo_proyecto`);

--
-- Indices de la tabla `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Indices de la tabla `tbl_usuario_roles`
--
ALTER TABLE `tbl_usuario_roles`
  ADD PRIMARY KEY (`id_usuario_roles`),
  ADD UNIQUE KEY `unique_usuario_depto` (`id_usuario`,`id_departamento`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_departamento` (`id_departamento`),
  ADD KEY `idx_rol` (`id_rol`),
  ADD KEY `idx_principal` (`id_usuario`,`es_principal`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `tbl_departamentos`
--
ALTER TABLE `tbl_departamentos`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tbl_notificaciones`
--
ALTER TABLE `tbl_notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `tbl_notificaciones_config`
--
ALTER TABLE `tbl_notificaciones_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_notificaciones_enviadas`
--
ALTER TABLE `tbl_notificaciones_enviadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_objetivos`
--
ALTER TABLE `tbl_objetivos`
  MODIFY `id_objetivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `tbl_proyectos`
--
ALTER TABLE `tbl_proyectos`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tbl_tareas`
--
ALTER TABLE `tbl_tareas`
  MODIFY `id_tarea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `tbl_tipo_proyecto`
--
ALTER TABLE `tbl_tipo_proyecto`
  MODIFY `id_tipo_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `tbl_usuario_roles`
--
ALTER TABLE `tbl_usuario_roles`
  MODIFY `id_usuario_roles` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `tbl_notificaciones`
--
ALTER TABLE `tbl_notificaciones`
  ADD CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tbl_notificaciones_config`
--
ALTER TABLE `tbl_notificaciones_config`
  ADD CONSTRAINT `fk_config_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tbl_usuario_roles`
--
ALTER TABLE `tbl_usuario_roles`
  ADD CONSTRAINT `fk_usuario_roles_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `tbl_departamentos` (`id_departamento`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usuario_roles_rol` FOREIGN KEY (`id_rol`) REFERENCES `tbl_roles` (`id_rol`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usuario_roles_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_usuarios` (`id_usuario`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
