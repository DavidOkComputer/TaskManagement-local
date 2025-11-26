-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2025 at 03:36 PM
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
(1, 'Desarrollo de sistema de tareas', 'Desarrollo e implementación de aplicación web para la creación y manejo de proyectos', 1, '2025-11-07 11:43:37', '2025-12-15', 'pendiente', 0x31323334353637383839, '../uploads/objetivos/obj_690e2fc9a317a_1762537417.pdf', 1, '2025-11-08 13:38:54'),
(6, 'Proyecto desarrollo de inteligencia artificial', 'prueba de objetivo', 6, '2025-11-13 13:03:04', '2025-11-25', 'pendiente', '', '', 1, '2025-11-13 19:03:04');

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
(1, 'Proyecto desarrollo de inteligencia artificial', 'Desarrollo e implementacion de inteligencia artificia autonoma capaz de realizar tareas de limpiesa autonomas', 1, '2025-11-25 17:27:09', '2025-11-17', 14, 0x31323334353637383839, 'vencido', 'uploads/proyectos/1762799642_f3314399a73845f2_Manual_de_usuario_para_sistema_de_pir__mide_3Q6S.pdf', 1, 1, 2, 0, '2025-11-14 18:33:00'),
(4, 'bbbbbbbbbbbbbb', 'bbbbbbbbbbbbbbbbb', 6, '2025-11-26 13:29:45', '2025-11-25', 83, '', 'vencido', '0', 1, 0, 1, 0, '2025-11-15 19:09:00'),
(8, 'prueba', 'prueba', 6, '2025-11-19 19:58:31', '2025-11-30', 100, 0x30, 'completado', '', 1, 3, 2, 0, '2025-11-13 21:02:01'),
(9, 'Prueba de vencimiento de proyecto', 'Descripcion de prueba de vencimiento de proyecto', 6, '2025-11-19 15:44:26', '2025-11-30', 50, 0x30, 'en proceso', '', 1, 3, 2, 0, '2025-11-19 15:37:50'),
(10, 'Prueba de index para proyecto', 'Descripcion de prueba de index para proyecto', 6, '2025-11-28 21:16:00', '2025-11-30', 0, 0x30, 'pendiente', '', 1, 1, 2, 0, '2025-11-19 21:16:34'),
(11, 'Prueba de creacion de proyectos con edicion restringida', 'Descripcion de prueba de creacion de proyectos con edicion restringida', 6, '2025-11-21 16:55:39', '2025-11-24', 0, '', 'pendiente', '0', 1, 3, 2, 0, '2025-11-20 15:50:09'),
(12, 'Estadisticas desarrolladas', 'Descripcion de estadisticas desarrolladas', 8, '2025-11-25 17:29:38', '2025-11-28', 100, '', 'completado', '0', 1, 6, 2, 0, '2025-11-24 17:17:28'),
(13, 'Instalacion de camaras de seguridad', 'Descripcion del proyecto de camaras de seguridad', 9, '2025-11-25 18:29:01', '2025-12-05', 100, '', 'completado', '0', 1, 7, 2, 0, '2025-11-25 18:28:19');

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
(14, 4, 1, '2025-11-20 17:25:25'),
(15, 4, 3, '2025-11-20 17:25:25');

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
  `fecha_cumplimiento` date NOT NULL,
  `estado` enum('pendiente','vencido','completado') NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_participante` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_tareas`
--

INSERT INTO `tbl_tareas` (`id_tarea`, `nombre`, `descripcion`, `id_proyecto`, `id_creador`, `fecha_inicio`, `fecha_cumplimiento`, `estado`, `fecha_creacion`, `id_participante`) VALUES
(1, 'desarrollar correccion de modal', 'desarrollar e implementar la correccion para el modal de tareas donde se queda la pantalla oscura', 4, 1, '2025-11-11 14:42:14', '0000-00-00', 'completado', '2025-11-11 14:42:14', NULL),
(2, 'Desarrollo de red neuronal', 'Desarrollo e implementacion de red neuronal a traves de python', 1, 1, '2025-11-25 15:53:12', '0000-00-00', 'pendiente', '2025-11-11 14:43:31', NULL),
(4, 'nueva tarea para el proyecto', 'desarrollo de tareas para proyectos', 4, 1, '2025-11-11 17:03:47', '0000-00-00', 'completado', '2025-11-11 17:03:47', NULL),
(7, 'ejemplo', 'ejemplo', 1, 1, '2025-11-25 15:53:11', '0000-00-00', 'pendiente', '2025-11-11 17:43:42', NULL),
(8, 'Desarrollo de automatizacion', 'Desarrollo e implementacion de pensamiento automatico', 1, 1, '2025-11-25 15:53:13', '0000-00-00', 'pendiente', '2025-11-12 18:51:19', NULL),
(9, 'Desarrollo de presentacion de informacin', 'Desarrollar el procesamiento de la informacion y presentacion de la misma', 1, 1, '2025-11-25 17:25:10', '0000-00-00', 'pendiente', '2025-11-12 18:52:59', NULL),
(10, 'Desarrollo de procesamiento eficiente', 'Desarrollo e implementacion de procesamiento eficiente de datos', 1, 1, '2025-11-25 17:25:09', '0000-00-00', 'pendiente', '2025-11-12 18:57:46', NULL),
(11, 'Desarrollo de deteccion de errores', 'Desarrollo e implementacion de deteccion de errores en procesamiento', 1, 1, '2025-11-25 17:26:29', '2025-11-30', 'completado', '2025-11-12 19:15:38', NULL),
(12, 'Desarrollo de conciencia', 'Desarrollo de sistema de conciencia de la red neuronal', 1, 1, '2025-11-25 17:27:09', '2025-11-28', 'pendiente', '2025-11-13 14:42:41', NULL),
(13, 'Prueba de desarrollo de tarea', 'Descripcion de prueba de desarrollo de tarea', 4, 1, '2025-11-18 15:47:59', '2025-11-20', 'completado', '2025-11-18 15:47:55', 1),
(14, 'Segunda prueba de tarea', 'Descripcion de segunda prueba de tarea', 4, 1, '2025-11-19 14:55:58', '2025-11-20', 'completado', '2025-11-18 15:48:48', 1),
(15, 'Prueba de actualizacion automatica', 'Descripcion de prueba de actualizacion automatica', 8, 1, '2025-11-19 14:52:44', '2025-11-22', 'completado', '2025-11-19 14:52:36', 1),
(16, 'Prueba de actualizacion automatica', 'Descripcion de prueba de actualizacion automatica', 8, 1, '2025-11-19 19:58:31', '2025-11-21', 'completado', '2025-11-19 15:12:56', 3),
(17, 'Prueba de asignacion a cierto empleado', 'Descripcion de asignacion a cierto empleado', 4, 1, '2025-11-20 18:34:02', '2025-11-21', 'completado', '2025-11-19 15:24:19', 3),
(18, 'Prueba de tarea para vencimiento de proyecto', 'Descripcion de prueba de tarea para vencimiento de proyecto', 9, 1, '2025-11-19 15:42:37', '2025-11-30', 'completado', '2025-11-19 15:39:14', 3),
(19, 'Segunda tarea de prueba para vencimiento de proyecto', 'Descripcion de segunda tarea de prueba para vencimiento de proyecto.', 9, 1, '2025-11-19 15:44:26', '2025-11-30', 'pendiente', '2025-11-19 15:40:18', 3),
(20, 'Prueba de asignacion de tarea con acceso restringido', 'Descripcion de prueba de asignacion de tarea con acceso restringido', 11, 1, '2025-11-21 16:55:39', '0000-00-00', 'pendiente', '2025-11-20 15:55:35', 3),
(21, 'Prueba de fecha de tarea', 'Descripcion para prueba de fecha de tarea', 10, 1, '2025-11-21 13:49:37', '0000-00-00', 'pendiente', '2025-11-21 13:49:37', 1),
(23, 'Grafica lineal', 'Descripcion de grafica lineal', 12, 1, '2025-11-25 17:26:47', '0000-00-00', 'completado', '2025-11-24 17:33:04', NULL),
(24, 'grafica de barras', 'descripcion de grafica de barras', 12, 1, '2025-11-25 14:40:24', '0000-00-00', 'completado', '2025-11-25 14:40:20', 6),
(25, 'Grafica de puntos dispersos', 'Descripcion de grafica de puntos dispersos', 12, 1, '2025-11-25 17:29:38', '0000-00-00', 'completado', '2025-11-25 17:29:11', 6),
(26, 'Instalacion de camaras en caseta', 'Descripcion de instalacion de camaras en caseta', 13, 1, '2025-11-25 18:29:01', '0000-00-00', 'completado', '2025-11-25 18:28:56', 7),
(27, 'Prueba de complecion de tarea', 'Descricpion de prueba de complecion de tarea', 4, 1, '2025-11-26 13:29:45', '0000-00-00', 'pendiente', '2025-11-26 13:27:54', 3);

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
  `e_mail` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_usuarios`
--

INSERT INTO `tbl_usuarios` (`id_usuario`, `nombre`, `apellido`, `usuario`, `num_empleado`, `acceso`, `id_departamento`, `id_rol`, `id_superior`, `e_mail`) VALUES
(1, 'David', 'Barreto', 'NMC10DB', 1858, 'admin', 1, 1, 0, 'francisco.barreto@nidec.com'),
(3, 'Francisco', 'Valdez', 'fram', 1959, '113a7f0c601f3d56b2cf4c9cca5ce636', 6, 3, 0, 'francisco.valdez@nidec.com'),
(6, 'Jhon', 'Doe', 'JhonDoe', 1010, '113a7f0c601f3d56b2cf4c9cca5ce636', 8, 2, 0, 'jhon.doe@nidec.com'),
(7, 'Juan', 'Dou', 'JuanDou', 2222, '113a7f0c601f3d56b2cf4c9cca5ce636', 9, 3, 0, 'juan.dou@nidec-motor.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_departamentos`
--
ALTER TABLE `tbl_departamentos`
  ADD PRIMARY KEY (`id_departamento`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_departamentos`
--
ALTER TABLE `tbl_departamentos`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_objetivos`
--
ALTER TABLE `tbl_objetivos`
  MODIFY `id_objetivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_proyectos`
--
ALTER TABLE `tbl_proyectos`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
  MODIFY `id_tarea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `tbl_tipo_proyecto`
--
ALTER TABLE `tbl_tipo_proyecto`
  MODIFY `id_tipo_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_proyecto_usuarios`
--
ALTER TABLE `tbl_proyecto_usuarios`
  ADD CONSTRAINT `tbl_proyecto_usuarios_ibfk_1` FOREIGN KEY (`id_proyecto`) REFERENCES `tbl_proyectos` (`id_proyecto`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_proyecto_usuarios_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `tbl_usuarios` (`id_usuario`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
