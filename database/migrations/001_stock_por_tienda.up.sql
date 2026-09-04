-- =====================================================================
--  Migración 001 · Stock por tienda                      (2026-09-03)
--  Base: femsa_assets  ·  Motor: MariaDB 10.4 / InnoDB
-- ---------------------------------------------------------------------
--  Extiende el modelo polimórfico de `stock` para que cada TIENDA
--  tenga su propio stock, igual que hoy lo tienen usuarios y bodegas.
--
--    · stock.tipo gana el valor 'tienda'
--    · stock.tienda_id  ->  FK a tienda(id)
--    · 1 fila de stock por cada tienda (backfill + trigger)
--    · Integridad reforzada:
--        - UNIQUE reales por tipo (el anterior no impedía duplicados)
--        - CHECK de coherencia  tipo  <->  columna de dueño
--
--  Ejecutar UNA vez.  Reversión:  001_stock_por_tienda.down.sql
--  Nota: cada ALTER/CREATE hace COMMIT implícito en MariaDB; por eso
--        se toma respaldo antes y existe el script .down.
-- =====================================================================

-- 1) Ampliar el enum de tipo -----------------------------------------
ALTER TABLE `stock`
  MODIFY `tipo` enum('usuario','bodega','tienda') NOT NULL;

-- 2) Nueva columna de dueño: tienda_id -----------------------------
ALTER TABLE `stock`
  ADD COLUMN `tienda_id` int(10) UNSIGNED DEFAULT NULL AFTER `bodega_id`;

-- 3) Backfill: una fila de stock por cada tienda existente ----------
INSERT INTO `stock` (`tipo`, `usuario_id`, `bodega_id`, `tienda_id`, `plaza_id`)
SELECT 'tienda', NULL, NULL, t.`id`, t.`plaza_id`
FROM   `tienda` t
LEFT   JOIN `stock` s
       ON  s.`tipo` = 'tienda' AND s.`tienda_id` = t.`id`
WHERE  s.`id` IS NULL;

-- 4) Índices + FK + CHECK en una sola operación --------------------
--    · Se sustituye uq_stock_usuario_plaza(usuario_id,plaza_id,tipo)
--      —que NO impedía duplicados de bodega/tienda porque sus
--      columnas quedan NULL y los NULL no colisionan en un UNIQUE—
--      por tres UNIQUE reales, uno por tipo de dueño.
--    · La FK a tienda se apoya en uq_stock_tienda (sin índice extra).
ALTER TABLE `stock`
  DROP INDEX  `uq_stock_usuario_plaza`,
  ADD  UNIQUE KEY `uq_stock_usuario_plaza` (`usuario_id`,`plaza_id`),
  ADD  UNIQUE KEY `uq_stock_bodega`        (`bodega_id`),
  ADD  UNIQUE KEY `uq_stock_tienda`        (`tienda_id`),
  ADD  CONSTRAINT `fk_stock_tienda` FOREIGN KEY (`tienda_id`) REFERENCES `tienda` (`id`),
  ADD  CONSTRAINT `chk_stock_owner` CHECK (
         (`tipo` = 'usuario' AND `usuario_id` IS NOT NULL AND `bodega_id` IS NULL AND `tienda_id` IS NULL)
      OR (`tipo` = 'bodega'  AND `bodega_id`  IS NOT NULL AND `usuario_id` IS NULL AND `tienda_id` IS NULL)
      OR (`tipo` = 'tienda'  AND `tienda_id`  IS NOT NULL AND `usuario_id` IS NULL AND `bodega_id` IS NULL)
  );

-- 5) Limpieza: el KEY fk_stock_bodega quedó redundante -------------
--    (uq_stock_bodega ya cubre bodega_id y respalda la FK).
ALTER TABLE `stock`
  DROP INDEX `fk_stock_bodega`;

-- 6) Trigger: toda tienda nueva nace con su stock ------------------
SET NAMES utf8mb4;
SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
DROP TRIGGER IF EXISTS `trg_crear_stock_tienda`;
DELIMITER $$
CREATE TRIGGER `trg_crear_stock_tienda` AFTER INSERT ON `tienda` FOR EACH ROW
BEGIN
    INSERT INTO `stock` (`tipo`, `usuario_id`, `bodega_id`, `tienda_id`, `plaza_id`)
    VALUES ('tienda', NULL, NULL, NEW.`id`, NEW.`plaza_id`);
END$$
DELIMITER ;
