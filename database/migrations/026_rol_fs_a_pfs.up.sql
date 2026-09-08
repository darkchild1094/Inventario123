-- =====================================================================
--  Migración 026 · Renombrar el rol de usuario 'fs' -> 'pfs'
--  Fecha: 2026-09-07 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  El rol de campo pasa a llamarse PFS (antes "FS — Field Service").
--  Se amplía el enum para admitir ambos valores, se migran las filas y
--  se reduce el enum a su forma final con 'pfs' como default.
--  Ningún trigger referencia 'fs' (verificado). El código PHP y la app
--  Android usan 'pfs' a partir de este cambio; el login tolera 'fs'
--  como red de seguridad durante el rollout.
-- =====================================================================

SET NAMES utf8mb4;

-- 1) Ampliar el enum para permitir el valor nuevo sin perder el viejo
ALTER TABLE `usuario`
  MODIFY `tipo` enum('admin','fs','pfs','coordinador','ati') NOT NULL DEFAULT 'pfs';

-- 2) Migrar las filas existentes
UPDATE `usuario` SET `tipo` = 'pfs' WHERE `tipo` = 'fs';

-- 3) Reducir el enum a su forma final
ALTER TABLE `usuario`
  MODIFY `tipo` enum('admin','pfs','coordinador','ati') NOT NULL DEFAULT 'pfs';
