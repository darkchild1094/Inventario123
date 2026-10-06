-- Revierte 032. La visibilidad vuelve a deducirse de los movimientos.

ALTER TABLE `proyecto_rentec`
  DROP FOREIGN KEY `fk_rentec_plaza`;

ALTER TABLE `proyecto_rentec`
  DROP COLUMN `plaza_id`;
