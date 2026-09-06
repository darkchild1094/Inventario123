-- =====================================================================
--  Migración 022 · Frontera: dejar sólo los CCTV que vienen del MAF
--  Fecha: 2026-09-06 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  La 018 dio de alta en Frontera 5 equipos "desde Bodega OXXO Valles"
--  (4 AXIS M3044-V cb 05450273-05450276 + 1 QNAP TS-128 sin serie/cb)
--  que NO existen en el MAF. Tras revertir el proyecto Verkada (021) el
--  usuario pide que en Frontera queden únicamente los del MAF, así que
--  se eliminan esos 5 activos y su movimiento de ingreso.
--
--  Los BOSCH y los genéricos de Frontera (del MAF) no se tocan.
--  Idempotente.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- id del modelo QNAP TS-128 (resuelto por nombre; difiere entre entornos)
SET @m_ts128 := (SELECT mo.id FROM `modelo` mo
                 JOIN `marca` ma ON ma.id = mo.marca_id
                 JOIN `dispositivo` d ON d.id = mo.dispositivo_id
                 WHERE d.nombre = 'DISCO DURO EXTERNO' AND ma.nombre = 'QNAP' AND mo.nombre = 'TS-128');

-- activos objetivo: 4 AXIS M3044-V por código + 1 QNAP TS-128 de Frontera sin identificadores
CREATE TEMPORARY TABLE `_frontera_borrar` AS
  SELECT a.id
  FROM `activo` a
  JOIN `stock` s ON s.id = a.stock_id
  WHERE (s.tienda_id = 111 OR a.tienda_uso_id = 111)
    AND (
      a.codigo_barras IN ('05450273','05450274','05450275','05450276')
      OR (a.modelo_id = @m_ts128 AND a.serie IS NULL AND a.codigo_barras IS NULL AND a.num_activo IS NULL)
    );

DELETE FROM `movimiento`
 WHERE `activo_id` IN (SELECT `id` FROM `_frontera_borrar`)
   AND `nota` LIKE '%Ingreso a tienda desde Bodega OXXO Valles%';

DELETE FROM `activo`
 WHERE `id` IN (SELECT `id` FROM `_frontera_borrar`);

DROP TEMPORARY TABLE `_frontera_borrar`;

SET FOREIGN_KEY_CHECKS = 1;
