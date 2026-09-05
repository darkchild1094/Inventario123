-- =====================================================================
--  Reversión de la Migración 019 (parcial / lossy)
--  No se puede saber qué serie tenía cada activo antes; se restaura el
--  mismo respaldo que ponía el import (num_activo, o codigo_barras) y se
--  vuelve a marcar NOT NULL. Los activos sin ninguno de los dos quedan
--  con un marcador para poder re-aplicar NOT NULL.
-- =====================================================================

UPDATE `activo`
   SET `serie` = COALESCE(`num_activo`, `codigo_barras`, CONCAT('activo-', `id`))
 WHERE `serie` IS NULL;

ALTER TABLE `activo` MODIFY COLUMN `serie` varchar(100) NOT NULL;
