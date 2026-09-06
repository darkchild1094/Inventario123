-- =====================================================================
--  Migración 021 · Revertir PARCIALMENTE la migración 018 (Verkada / RENTEC)
--  Fecha: 2026-09-06 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Se conserva la 018 SÓLO hasta el reemplazo por garantía de Motolinia
--  (04370654 -> garantía, entra 02522819 desde bodega). Se deshace todo
--  lo posterior:
--    · retiro de las 4 BOSCH de Frontera ("previo a Verkada")
--    · retiro CCTV + instalación Verkada RENTEC en las 5 tiendas
--      (Glorieta, Motolinia, Aire, Apolo, Frontera)
--
--  Se conservan: las 11 correcciones de modelo, el ingreso de los 7
--  activos desde Bodega OXXO Valles, y el swap de garantía de Motolinia.
--
--  Idempotente. Los activos que se pusieron 'baja' sólo cambiaron de
--  status (018 no tocó stock_id/tienda_uso_id), así que basta con
--  devolverlos a 'en_uso'.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) borrar los movimientos de los pasos 4 y 5
DELETE FROM `movimiento`
 WHERE `nota` LIKE '%sustituido por AXIS M3044-V%'
    OR `nota` LIKE '%RENTEC/Verkada, folio%'
    OR `nota` LIKE '%RENTEC CCTV, folio%';

-- 2) borrar los 25 activos Verkada instalados por la 018
DELETE FROM `activo`
 WHERE `codigo_barras` LIKE '055880%' OR `codigo_barras` LIKE '055881%';

-- 3) devolver a 'en_uso' los equipos que la 018 puso en 'baja' (pasos 4 y 5)
UPDATE `activo` SET `status` = 'en_uso'
 WHERE `status` = 'baja' AND `codigo_barras` IN (
         '02753359','02753360','02753361','02753362',                 -- Frontera BOSCH (paso 4)
         '03417684','03417685','03417686','03417687','03417688',       -- Glorieta
         '04370655','04370656','04370657','04370658','02522819',       -- Motolinia
         '02753419','02753420','02753421','02753422','02753474',       -- Aire
         '04154161','04154162','04154218','04154219','04154220',       -- Apolo
         '05450273','05450274','05450275','05450276'                   -- Frontera AXIS M3044-V (desde bodega)
       );

-- Frontera QNAP TS-128 (desde bodega): la 019 dejó su serie en NULL, así que
-- se identifica por modelo (resuelto por nombre) + tienda.
SET @m_ts128_fr := (SELECT mo.id FROM `modelo` mo
                    JOIN `marca` ma ON ma.id = mo.marca_id
                    JOIN `dispositivo` d ON d.id = mo.dispositivo_id
                    WHERE d.nombre = 'DISCO DURO EXTERNO' AND ma.nombre = 'QNAP' AND mo.nombre = 'TS-128');
UPDATE `activo` SET `status` = 'en_uso'
 WHERE `status` = 'baja' AND `tienda_uso_id` = 111 AND `modelo_id` = @m_ts128_fr;

SET FOREIGN_KEY_CHECKS = 1;
