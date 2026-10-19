SET NAMES utf8mb4;

DROP TABLE IF EXISTS `transferencia_pendiente`;

ALTER TABLE `notificacion`
  MODIFY COLUMN `direccion` enum('a_bodega','a_stock') NOT NULL;
