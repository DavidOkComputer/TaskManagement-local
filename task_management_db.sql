-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 07-12-2025 a las 21:53:25
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
(2, 'Deportes', 'Equipo competitivo', 1);

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
(4, 'Ganar la champions', 'queremos la champions', 2, '2025-12-03 17:13:19', '2025-12-31', 'pendiente', '', '', 4, '2025-12-03 23:13:19');

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
(1, 'Prueba de proyecto', 'Descripcion para prueba de proyecto', 1, '2025-11-20 15:10:00', '2025-11-21', 0, 0x30, 'vencido', '', 1, 3, 2, 0, '2025-11-17 21:30:59'),
(7, 'Prueba de proyecto grupal', 'Descripcion de prueba de proyecto grupal', 1, '2025-11-19 13:07:00', '2025-11-21', 100, 0x30, 'completado', '', 1, 0, 1, 0, '2025-11-17 22:25:40'),
(8, 'Prueba de asignacion de tareas', 'Descripcion de prueba de asignacion de tareas', 1, '2025-11-21 08:02:00', '2025-11-22', 0, '', 'pendiente', '0', 1, 3, 2, 0, '2025-11-20 00:02:37'),
(9, 'Equipo competitivo en europa', 'Descripcion de equipo competitivo en europa', 2, '2025-12-06 14:08:00', '2025-12-16', 0, '', 'pendiente', '0', 1, 4, 2, 0, '2025-11-30 00:25:40'),
(10, 'Prueba de creacion de tareas', 'Descripcion de prueba de creacion de tareas', 2, '2025-12-19 13:07:00', '2025-12-21', 0, '', 'pendiente', '0', 1, 4, 2, 1, '2025-12-03 23:56:22'),
(11, 'Ganar la liga', 'Campeones de la liga', 2, '2025-12-25 06:00:00', '2025-12-31', 0, '', 'pendiente', '0', 4, 4, 2, 0, '2025-12-04 00:11:20'),
(12, 'Proyecto de muestra en dashboard', 'Descripción de proyecto de muestra en dashboard', 1, '2025-12-20 11:05:00', '2025-12-26', 0, '', 'pendiente', '0', 3, 3, 2, 0, '2025-12-06 23:50:38');

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
(2, 'Prueba de tarea', 'Descripcion de prueba de tarea', 1, 1, '2025-11-17 23:06:08', '2025-11-20', 'pendiente', '2025-11-17 23:06:08', 1),
(3, 'prueba de complecion', 'descripcion de prueba de complecion', 7, 1, '2025-11-17 23:23:15', '2025-11-20', 'completado', '2025-11-17 23:23:15', 1),
(4, 'Prueba de usuario en complecion', 'Descripcion de prueba de usuario en complecion', 7, 1, '2025-11-17 23:24:16', '2025-11-19', 'completado', '2025-11-17 23:24:16', 3),
(5, 'Prueba de media complecion', 'Descripcion de prueba de media complecion', 7, 1, '2025-11-17 23:25:07', '2025-11-19', 'completado', '2025-11-17 23:25:07', 1),
(6, 'Prueba de checkbox', 'Descripcion de prueba de checkbox', 8, 1, '2025-11-21 00:10:28', '0000-00-00', 'pendiente', '2025-11-21 00:10:28', 3),
(7, 'Prueba de tarea', 'Descripcion de prueba de tareas', 1, 1, '2025-11-23 14:32:03', '0000-00-00', 'pendiente', '2025-11-23 14:32:03', 3),
(8, 'Entrenamiento', 'Entrenamiento por la maniana', 9, 1, '2025-11-30 00:26:17', '0000-00-00', 'pendiente', '2025-11-30 00:26:17', 4),
(9, 'Ganarle al atletico de madrid', 'En el nou camp nou', 11, 4, '2025-12-04 00:18:20', '0000-00-00', 'pendiente', '2025-12-04 00:18:20', 4),
(10, 'Ganarle al frankfurt', 'En el nou camp nou', 9, 1, '2025-12-07 17:01:31', '0000-00-00', 'pendiente', '2025-12-07 17:01:31', 4),
(11, 'prueba de notificaciones', 'Descripcion de prueba de notificaciones', 8, 1, '2025-12-07 20:48:02', '0000-00-00', 'pendiente', '2025-12-07 20:48:02', 3);

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
  `e_mail` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tbl_usuarios`
--

INSERT INTO `tbl_usuarios` (`id_usuario`, `nombre`, `apellido`, `usuario`, `num_empleado`, `acceso`, `id_departamento`, `id_rol`, `id_superior`, `e_mail`) VALUES
(1, 'David', 'Barreto', 'NMC10DB', 1858, '$2y$12$M/9PV/M4o7Q79NAyRHXr5e8AmqXGxYXxUFlQSBfhi/aDTvvk9yRVe', 1, 1, 0, 'david.barreto@nidec.com\r\n'),
(3, 'Francisco', 'Valdez', 'FV1912', 1959, '113a7f0c601f3d56b2cf4c9cca5ce636', 1, 3, 0, 'francisco.valdez@nidec.com'),
(4, 'Jhon', 'Doe', 'JhonDoe', 9, '$2y$12$YlFaIvs6P6hT/9M55L7N1.Hloj9KVe04nC/PH7vF0wOmuEOGy3SN6', 2, 2, 0, 'JhonDoe@nidec-motor.com');

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
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `tbl_departamentos`
--
ALTER TABLE `tbl_departamentos`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tbl_notificaciones`
--
ALTER TABLE `tbl_notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_objetivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tbl_proyectos`
--
ALTER TABLE `tbl_proyectos`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tbl_tareas`
--
ALTER TABLE `tbl_tareas`
  MODIFY `id_tarea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `tbl_tipo_proyecto`
--
ALTER TABLE `tbl_tipo_proyecto`
  MODIFY `id_tipo_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
