-- Revertir migración 028 · RENTEC
SET NAMES utf8mb4;

ALTER TABLE `movimiento` DROP FOREIGN KEY `fk_mov_rentec`, DROP COLUMN `proyecto_rentec_id`;
ALTER TABLE `activo` DROP FOREIGN KEY `fk_activo_rentec`, DROP COLUMN `proyecto_rentec_id`;
DROP TABLE IF EXISTS `proyecto_rentec`;
