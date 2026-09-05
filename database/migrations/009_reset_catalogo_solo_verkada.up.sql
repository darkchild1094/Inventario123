-- =====================================================================
--  Migración 009 · Reinicio del catálogo: borra TODOS los activos y
--  vacía `modelo`, dejando solo los 3 modelos de cámaras Verkada.
--  Fecha: 2026-09-05 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Acción destructiva pedida explícitamente por el usuario. Borra los
--  26 374 activos (el import del MAF + lo agregado después) y los 1132
--  modelos del catálogo; deja únicamente:
--    CM42-256-HW, CM42-256S-HW, CF83-512E-HW  (dispositivo=CAMARA CCTV,
--    marca=VERKADA)
--  `movimiento.activo_id` queda en NULL por el ON DELETE SET NULL de su
--  FK (el snapshot en datos_json se conserva).
--
--  ESTA MIGRACIÓN NO TIENE DOWN FUNCIONAL: no hay forma de reconstruir
--  26 374 activos ni el catálogo completo de 1132 modelos a partir de la
--  nada. Antes de aplicarla se tomó respaldo completo (mysqldump) de
--  ambas bases (local y producción); restaurar desde ahí es la única
--  reversión posible.
-- =====================================================================

SET NAMES utf8mb4;

DELETE FROM `activo`;
ALTER TABLE `activo` AUTO_INCREMENT = 1;

DELETE FROM `area_modelo`;
DELETE FROM `modelo`;
ALTER TABLE `modelo` AUTO_INCREMENT = 1;

INSERT INTO `modelo` (`nombre`, `dispositivo_id`, `marca_id`) VALUES
('CM42-256-HW',  1, 19),
('CM42-256S-HW', 1, 19),
('CF83-512E-HW', 1, 19);
