-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 10, 2025 at 08:56 PM
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
(6, 'Departamento de recursos humanos', 'Departamento encargado de la gestion de recursos humanos y relacionaods', 1);

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
  `progreso` int(11) NOT NULL,
  `estado` enum('pendiente','en proceso','vencido','completado') NOT NULL,
  `ar` varbinary(200) NOT NULL,
  `archivo_adjunto` varchar(300) NOT NULL,
  `id_creador` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_objetivos`
--

INSERT INTO `tbl_objetivos` (`id_objetivo`, `nombre`, `descripcion`, `id_departamento`, `fecha_inicio`, `fecha_cumplimiento`, `progreso`, `estado`, `ar`, `archivo_adjunto`, `id_creador`, `fecha_creacion`) VALUES
(1, 'Desarrollo de sistema de tareas', 'Desarrollo e implementación de aplicación web para la creación y manejo de proyectos', 1, '2025-11-07 11:43:37', '2025-12-15', 0, 'pendiente', 0x31323334353637383839, '../uploads/objetivos/obj_690e2fc9a317a_1762537417.pdf', 1, '2025-11-08 13:38:54'),
(2, 'Objetivo de prueba', 'objetivo de prueba descripcion de prueba', 1, '2025-11-10 11:40:15', '2025-11-16', 0, 'pendiente', 0x31323334353637383839, '../uploads/objetivos/obj_6912237f651a3_1762796415.pdf', 1, '2025-11-10 17:40:15');

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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_proyectos`
--

INSERT INTO `tbl_proyectos` (`id_proyecto`, `nombre`, `descripcion`, `id_departamento`, `fecha_inicio`, `fecha_cumplimiento`, `progreso`, `ar`, `estado`, `archivo_adjunto`, `id_creador`, `id_participante`, `id_tipo_proyecto`, `fecha_creacion`) VALUES
(1, 'Proyecto desarrollo de inteligencia artificial', 'Desarrollo e implementacion de inteligencia artificia autonoma capaz de realizar tareas de limpiesa autonomas', 1, '2025-11-10 18:34:02', '2025-11-17', 0, 0x31323334353637383839, 'pendiente', 'uploads/proyectos/1762799642_f3314399a73845f2_Manual_de_usuario_para_sistema_de_pir__mide_3Q6S.pdf', 1, 1, 2, '2025-11-14 18:33:00'),
(3, 'aaaaaaaaaaaaaaaaaaaaa', 'aaaaaaaaaaaaaaaaaaaaa', 1, '2025-11-10 19:07:26', '2025-11-17', 0, 0x30, 'pendiente', 'uploads/proyectos/1762801646_af0b0481da49ab3b_Manual_de_usuario_para_sistema_de_pir__mide_3Q6S.pdf', 1, 1, 2, '2025-11-15 19:07:00'),
(4, 'bbbbbbbbbbbbbb', 'bbbbbbbbbbbbbbbbb', 1, '2025-11-10 19:09:28', '2025-11-17', 0, 0x30, 'pendiente', 'uploads/proyectos/1762801768_6504ea49ce75b556_Manual_de_usuario_para_sistema_de_pir__mide_3Q6S.pdf', 1, 0, 1, '2025-11-15 19:09:00'),
(5, 'qqqqqqqqqqqqqqqqqqq', 'qqqqqqqqqqqqqqqqq', 1, '2025-11-24 19:10:00', '2025-11-28', 0, 0x30, 'pendiente', 'uploads/proyectos/1762801867_ec31b353e3355b5f_Manual_de_usuario_para_sistema_de_pir__mide_3Q6S.pdf', 1, 0, 1, '2025-11-10 19:11:07');

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
  `fecha_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_cumplimiento` date NOT NULL,
  `estado` enum('pendiente','en proceso','vencido','completado') NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'David', 'Barreto', 'NMC10DB', 1858, 'admin', 1, 1, 0, 'francisco.barreto@nidec.com');

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
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_objetivos`
--
ALTER TABLE `tbl_objetivos`
  MODIFY `id_objetivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_proyectos`
--
ALTER TABLE `tbl_proyectos`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_tareas`
--
ALTER TABLE `tbl_tareas`
  MODIFY `id_tarea` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_tipo_proyecto`
--
ALTER TABLE `tbl_tipo_proyecto`
  MODIFY `id_tipo_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/**
 * SQL QUERIES - Run these in your database to support group projects
 */

-- 1. Create junction table for project-user relationships (if not exists)
CREATE TABLE IF NOT EXISTS tbl_proyecto_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_proyecto INT NOT NULL,
    id_usuario INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_proyecto) REFERENCES tbl_proyectos(id_proyecto) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES tbl_usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY unique_proyecto_usuario (id_proyecto, id_usuario)
);

-- 2. Create index for faster queries
CREATE INDEX idx_proyecto_usuarios ON tbl_proyecto_usuarios(id_proyecto);
CREATE INDEX idx_usuario_proyectos ON tbl_proyecto_usuarios(id_usuario);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
