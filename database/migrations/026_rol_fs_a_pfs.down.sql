-- =====================================================================
--  Down 026 · Revertir el rol 'pfs' -> 'fs'
-- =====================================================================

SET NAMES utf8mb4;

-- 1) Ampliar el enum para permitir ambos valores
ALTER TABLE `usuario`
  MODIFY `tipo` enum('admin','fs','pfs','coordinador','ati') NOT NULL DEFAULT 'fs';

-- 2) Revertir las filas
UPDATE `usuario` SET `tipo` = 'fs' WHERE `tipo` = 'pfs';

-- 3) Reducir el enum a su forma original
ALTER TABLE `usuario`
  MODIFY `tipo` enum('admin','fs','coordinador','ati') NOT NULL DEFAULT 'fs';
