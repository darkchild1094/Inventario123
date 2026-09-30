-- =====================================================================
--  Migración 028 · RENTEC (Renovación Tecnológica) — proyecto/folio
--  Fecha: 2026-09-30 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  No reinventa el flujo de alta/reemplazo, que ya enlaza instalado↔
--  retirado vía movimiento.grupo_id/activo_relacionado_id. Solo añade un
--  "proyecto" (folio autogenerado) para agrupar y poder listar/exportar
--  los activos y movimientos de una renovación.
--  `activo.proyecto_rentec_id`  = bajo qué proyecto entró ese equipo.
--  `movimiento.proyecto_rentec_id` = qué evento pertenece a qué proyecto
--  (alta en bodega, instalación, retiro relacionado).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE `proyecto_rentec` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folio` varchar(50) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `estado` enum('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  `usuario_id` int(10) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `cerrado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rentec_folio` (`folio`),
  KEY `fk_rentec_usuario` (`usuario_id`),
  CONSTRAINT `fk_rentec_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `activo`
  ADD COLUMN `proyecto_rentec_id` int(10) unsigned DEFAULT NULL AFTER `idempotency_key`,
  ADD KEY `fk_activo_rentec` (`proyecto_rentec_id`),
  ADD CONSTRAINT `fk_activo_rentec` FOREIGN KEY (`proyecto_rentec_id`) REFERENCES `proyecto_rentec` (`id`);

ALTER TABLE `movimiento`
  ADD COLUMN `proyecto_rentec_id` int(10) unsigned DEFAULT NULL AFTER `solicitud_traslado_id`,
  ADD KEY `fk_mov_rentec` (`proyecto_rentec_id`),
  ADD CONSTRAINT `fk_mov_rentec` FOREIGN KEY (`proyecto_rentec_id`) REFERENCES `proyecto_rentec` (`id`);
