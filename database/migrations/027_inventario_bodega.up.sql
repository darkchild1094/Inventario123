-- =====================================================================
--  Migración 027 · Inventario físico de bodega (auditoría por escaneo)
--  Fecha: 2026-09-30 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Un `inventario_bodega` es un conteo mensual de una bodega: al abrirlo
--  se toma snapshot de los activos que en ese momento están en_bodega
--  (inventario_bodega_detalle), y cada uno se marca `encontrado` al
--  escanearlo. Los que quedan sin escanear al cerrar el inventario
--  pueden llevar una `nota` de justificación (dónde está el activo
--  desaparecido). No toca `activo.status` ni genera `movimiento`: es
--  una auditoría, no un cambio de estado del inventario en sí.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE `inventario_bodega` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bodega_id` int(10) unsigned NOT NULL,
  `periodo` char(7) NOT NULL COMMENT 'YYYY-MM',
  `estado` enum('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  `usuario_id` int(10) unsigned NOT NULL,
  `total_esperado` int(10) unsigned NOT NULL DEFAULT 0,
  `total_encontrado` int(10) unsigned NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `cerrado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invbod_bodega_periodo` (`bodega_id`,`periodo`),
  KEY `fk_invbod_usuario` (`usuario_id`),
  CONSTRAINT `fk_invbod_bodega` FOREIGN KEY (`bodega_id`) REFERENCES `bodega` (`id`),
  CONSTRAINT `fk_invbod_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `inventario_bodega_detalle` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inventario_id` int(10) unsigned NOT NULL,
  `activo_id` int(10) unsigned NOT NULL,
  `encontrado` tinyint(1) NOT NULL DEFAULT 0,
  `escaneado_en` timestamp NULL DEFAULT NULL,
  `escaneado_por` int(10) unsigned DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invboddet_inv_activo` (`inventario_id`,`activo_id`),
  KEY `fk_invboddet_activo` (`activo_id`),
  KEY `fk_invboddet_usuario` (`escaneado_por`),
  CONSTRAINT `fk_invboddet_activo` FOREIGN KEY (`activo_id`) REFERENCES `activo` (`id`),
  CONSTRAINT `fk_invboddet_inventario` FOREIGN KEY (`inventario_id`) REFERENCES `inventario_bodega` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invboddet_usuario` FOREIGN KEY (`escaneado_por`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
