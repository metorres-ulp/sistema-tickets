-- Sistema de Tickets - ULP Comunicación
-- Base de datos MySQL

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `sistema_tickets` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistema_tickets`;

-- --------------------------------------------------------
-- Tabla: areas
-- Departamentos del área de Comunicación de la ULP
-- --------------------------------------------------------
CREATE TABLE `areas` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `areas` (`nombre`, `descripcion`) VALUES
('Multimedia - Web', 'Desarrollo y mantenimiento de sitios web y contenido multimedia'),
('Diseño', 'Diseño gráfico e identidad visual'),
('Prensa', 'Redacción, comunicación institucional y prensa'),
('Producción Audiovisual', 'Producción de video, fotografía y contenido audiovisual'),
('Estudio de Grabación', 'Grabación, edición y producción de audio');

-- --------------------------------------------------------
-- Tabla: tipos_trabajo
-- Tipos de trabajos que puede realizar cada área
-- --------------------------------------------------------
CREATE TABLE `tipos_trabajo` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `area_id` INT(11) UNSIGNED NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tipos_trabajo_area` (`area_id`),
  CONSTRAINT `fk_tipos_trabajo_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_trabajo` (`area_id`, `nombre`) VALUES
-- Multimedia - Web (area_id=1)
(1, 'Diseño y desarrollo de sitio web'),
(1, 'Actualización de contenido web'),
(1, 'Landing page'),
(1, 'Banner web / publicitario'),
(1, 'Edición de video para web'),
(1, 'Newsletter / email marketing'),
-- Diseño (area_id=2)
(2, 'Diseño de flyer'),
(2, 'Diseño de afiche / poster'),
(2, 'Diseño de banner impreso'),
(2, 'Diseño de infografía'),
(2, 'Diseño de logo / marca'),
(2, 'Diseño de material institucional'),
-- Prensa (area_id=3)
(3, 'Redacción de gacetilla de prensa'),
(3, 'Nota periodística'),
(3, 'Comunicado institucional'),
(3, 'Cobertura de evento'),
-- Producción Audiovisual (area_id=4)
(4, 'Grabación de video'),
(4, 'Edición de video'),
(4, 'Fotografía de evento'),
(4, 'Transmisión en vivo'),
(4, 'Producción de spot publicitario'),
-- Estudio de Grabación (area_id=5)
(5, 'Grabación de audio'),
(5, 'Edición y mezcla de audio'),
(5, 'Podcast'),
(5, 'Locución institucional');

-- --------------------------------------------------------
-- Tabla: usuarios
-- Usuarios del sistema privado
-- --------------------------------------------------------
CREATE TABLE `usuarios` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `area_id` INT(11) UNSIGNED DEFAULT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `rol` ENUM('admin','referente','usuario') NOT NULL DEFAULT 'usuario',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `ultimo_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `fk_usuarios_area` (`area_id`),
  CONSTRAINT `fk_usuarios_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial admin user (IMPORTANT: change the password immediately after first login)
-- Default password: Admin1234!
INSERT INTO `usuarios` (`area_id`, `nombre`, `apellido`, `email`, `password`, `rol`) VALUES
(NULL, 'Administrador', 'Sistema', 'admin@ulp.edu.ar', '$2y$10$qmPJ/DuS9/6xCY01bW.Zt.P0KEjPEVzE/CwW37ddem4vzBPUQOvQS', 'admin');

-- --------------------------------------------------------
-- Tabla: tickets
-- Requerimientos/tickets enviados desde el formulario público
-- --------------------------------------------------------
CREATE TABLE `tickets` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero` VARCHAR(20) NOT NULL,
  `solicitante_nombre` VARCHAR(100) NOT NULL,
  `solicitante_apellido` VARCHAR(100) NOT NULL,
  `solicitante_area` VARCHAR(150) NOT NULL,
  `solicitante_email` VARCHAR(150) NOT NULL,
  `solicitante_telefono` VARCHAR(50) DEFAULT NULL,
  `descripcion` TEXT NOT NULL,
  `fecha_entrega_solicitada` DATE DEFAULT NULL,
  `urgente` TINYINT(1) NOT NULL DEFAULT 0,
  `estado` ENUM('ingresada','asignada','iniciada','en_proceso','resuelta','marcada') NOT NULL DEFAULT 'ingresada',
  `prioridad` ENUM('baja','normal','alta','urgente') NOT NULL DEFAULT 'normal',
  `observaciones` TEXT DEFAULT NULL,
  `asignado_a` INT(11) UNSIGNED DEFAULT NULL,
  `asignado_por` INT(11) UNSIGNED DEFAULT NULL,
  `fecha_asignacion` TIMESTAMP NULL DEFAULT NULL,
  `fecha_inicio` TIMESTAMP NULL DEFAULT NULL,
  `fecha_resolucion` TIMESTAMP NULL DEFAULT NULL,
  `email_enviado` TINYINT(1) NOT NULL DEFAULT 0,
  `notificado` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero` (`numero`),
  KEY `fk_tickets_asignado` (`asignado_a`),
  KEY `fk_tickets_asignado_por` (`asignado_por`),
  KEY `idx_estado` (`estado`),
  KEY `idx_urgente` (`urgente`),
  CONSTRAINT `fk_tickets_asignado` FOREIGN KEY (`asignado_a`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tickets_asignado_por` FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: ticket_areas
-- Áreas solicitadas en cada ticket (un ticket puede involucrar varias áreas)
-- --------------------------------------------------------
CREATE TABLE `ticket_areas` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) UNSIGNED NOT NULL,
  `area_id` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_area` (`ticket_id`, `area_id`),
  KEY `fk_ta_ticket` (`ticket_id`),
  KEY `fk_ta_area` (`area_id`),
  CONSTRAINT `fk_ta_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: ticket_tipos_trabajo
-- Tipos de trabajo solicitados en cada ticket
-- --------------------------------------------------------
CREATE TABLE `ticket_tipos_trabajo` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) UNSIGNED NOT NULL,
  `tipo_trabajo_id` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_tipo` (`ticket_id`, `tipo_trabajo_id`),
  KEY `fk_ttt_ticket` (`ticket_id`),
  KEY `fk_ttt_tipo` (`tipo_trabajo_id`),
  CONSTRAINT `fk_ttt_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ttt_tipo` FOREIGN KEY (`tipo_trabajo_id`) REFERENCES `tipos_trabajo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: ticket_archivos
-- Archivos adjuntos por ticket
-- --------------------------------------------------------
CREATE TABLE `ticket_archivos` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) UNSIGNED NOT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `nombre_almacenado` VARCHAR(255) NOT NULL,
  `tipo_mime` VARCHAR(100) DEFAULT NULL,
  `tamanio` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_archivos_ticket` (`ticket_id`),
  CONSTRAINT `fk_archivos_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: ticket_historial
-- Historial de cambios de estado y acciones sobre tickets
-- --------------------------------------------------------
CREATE TABLE `ticket_historial` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) UNSIGNED NOT NULL,
  `usuario_id` INT(11) UNSIGNED DEFAULT NULL,
  `estado_anterior` VARCHAR(50) DEFAULT NULL,
  `estado_nuevo` VARCHAR(50) DEFAULT NULL,
  `accion` VARCHAR(100) NOT NULL,
  `comentario` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_historial_ticket` (`ticket_id`),
  KEY `fk_historial_usuario` (`usuario_id`),
  CONSTRAINT `fk_historial_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: notificaciones
-- Notificaciones internas para usuarios del sistema
-- --------------------------------------------------------
CREATE TABLE `notificaciones` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) UNSIGNED DEFAULT NULL,
  `ticket_id` INT(11) UNSIGNED DEFAULT NULL,
  `tipo` ENUM('nuevo_ticket','asignacion','cambio_estado','resolucion','mensaje') NOT NULL DEFAULT 'nuevo_ticket',
  `mensaje` TEXT NOT NULL,
  `leida` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_usuario` (`usuario_id`),
  KEY `fk_notif_ticket` (`ticket_id`),
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: links_referencia
-- Links de referencia adjuntos en el formulario público
-- --------------------------------------------------------
CREATE TABLE `links_referencia` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) UNSIGNED NOT NULL,
  `url` TEXT NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_links_ticket` (`ticket_id`),
  CONSTRAINT `fk_links_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
