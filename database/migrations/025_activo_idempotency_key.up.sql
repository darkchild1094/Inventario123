-- =====================================================================
--  Migración 025 · activo.idempotency_key
--  Fecha: 2026-09-07 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  La app registra altas de activo offline y las reenvía al recuperar
--  señal. Si una respuesta se pierde tras un alta exitosa, el reintento
--  crearía un duplicado. La app manda una clave única por alta; el
--  servidor la guarda aquí y, ante un reintento con la misma clave,
--  devuelve el activo ya creado en vez de insertar otro.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `activo`
  ADD COLUMN `idempotency_key` varchar(64) DEFAULT NULL,
  ADD UNIQUE KEY `uq_activo_idempotency` (`idempotency_key`);
