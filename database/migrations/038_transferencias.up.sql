-- =====================================================================
--  Migración 038 · Transferencias pendientes (alta administrativa FEMSA)
--  Base: femsa_assets
-- ---------------------------------------------------------------------
--  Cuando al "Agregar activo" durante una auditoría de tienda (checklist
--  de InventarioTienda) resulta que el equipo YA existía en el sistema
--  (otra tienda/bodega/stock) y además tiene num_activo (la placa oficial
--  de FEMSA), el activo se mueve de inmediato a la tienda donde se
--  encontró — pero FEMSA todavía no sabe de ese movimiento en su propio
--  registro. Esta tabla es el pendiente de "avisarle a FEMSA" (alta
--  administrativa fuera del sistema): el ATI/admin la marca completada
--  cuando ya hizo ese trámite externo, no cuando se movió el activo (eso
--  ya pasó solo).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `notificacion`
  MODIFY COLUMN `direccion` enum('a_bodega','a_stock','transferencia_pendiente') NOT NULL;

CREATE TABLE `transferencia_pendiente` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activo_id` int(10) unsigned NOT NULL,
  `tienda_destino_id` int(10) unsigned NOT NULL,
  `ubicacion_anterior` varchar(150) DEFAULT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `completado_en` timestamp NULL DEFAULT NULL,
  `completado_por_usuario_id` int(10) unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_transfpend_activo` (`activo_id`),
  KEY `fk_transfpend_tienda` (`tienda_destino_id`),
  KEY `fk_transfpend_usuario` (`completado_por_usuario_id`),
  CONSTRAINT `fk_transfpend_activo` FOREIGN KEY (`activo_id`) REFERENCES `activo` (`id`),
  CONSTRAINT `fk_transfpend_tienda` FOREIGN KEY (`tienda_destino_id`) REFERENCES `tienda` (`id`),
  CONSTRAINT `fk_transfpend_usuario` FOREIGN KEY (`completado_por_usuario_id`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
