-- =====================================================================
--  Migración 014 · Reinicio del historial de movimientos
--  Fecha: 2026-09-06 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Pedido explícito del usuario: vacía `movimiento` (los 5 registros
--  'alta' retroactivos de la migración 002b, que ya no corresponden a
--  ningún activo real) antes de recargar el catálogo con el import
--  completo del MAF (migración 015), que vuelve a poblar el historial
--  con un 'alta' por cada activo importado.
--
--  ESTA MIGRACIÓN NO TIENE DOWN FUNCIONAL: un TRUNCATE no es reversible.
-- =====================================================================

TRUNCATE TABLE `movimiento`;
