SET NAMES utf8mb4;

ALTER TABLE `activo`
  DROP KEY `uq_activo_idempotency`,
  DROP COLUMN `idempotency_key`;
