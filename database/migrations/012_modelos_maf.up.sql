-- =====================================================================
--  Migración 012 · Catálogo de modelos homologado del Excel MAF
--  Fecha: 2026-09-06 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Agrega la marca NORTH y 155 modelos canónicos (homologación de 1,464
--  combinaciones marca/modelo crudas del Excel MAF AL 17 DE AGOSTO, ya
--  verificadas contra el catálogo real de cada fabricante). Los que no
--  traen número de modelo real en el dato quedan como 'GENERICO' por
--  marca, en vez de inventar un modelo que el dato no sostiene.
--  Idempotente (no duplica si ya existen).
-- =====================================================================

SET NAMES utf8mb4;

-- 1) marca nueva
INSERT INTO `marca` (`nombre`)
SELECT 'NORTH' WHERE NOT EXISTS (SELECT 1 FROM `marca` WHERE `nombre` = 'NORTH');

-- 2) modelos (dispositivo, marca, modelo)
INSERT INTO `modelo` (`nombre`, `dispositivo_id`, `marca_id`)
SELECT v.nombre, v.dispositivo_id, ma.id
FROM (
    SELECT 'GENERICO' AS nombre, 19 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 19 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MR36H' AS nombre, 19 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MR46-HW' AS nombre, 19 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MR36H' AS nombre, 19 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'MR30H' AS nombre, 19 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 19 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'MR36H' AS nombre, 14 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 14 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 1 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 1 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'NUC-51022-F2' AS nombre, 1 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'NDN-50022-A3' AS nombre, 1 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'M3004-V' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'M3085-V' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'P3915-R' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'CM42-256-HW' AS nombre, 1 AS dispositivo_id, 'VERKADA' AS marca UNION ALL
    SELECT 'M4216-V' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'P3265-LV' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'NDE-3502-AL' AS nombre, 1 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'NDV-3502-F03' AS nombre, 1 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'CF83-512E-HW' AS nombre, 1 AS dispositivo_id, 'VERKADA' AS marca UNION ALL
    SELECT 'M3065-V' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'M3066-V' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'M3245-LV' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 1 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'M3044-V' AS nombre, 1 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 1 AS dispositivo_id, 'VERKADA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 15 AS dispositivo_id, 'DANFOSS' AS marca UNION ALL
    SELECT 'AK-SM820' AS nombre, 15 AS dispositivo_id, 'DANFOSS' AS marca UNION ALL
    SELECT 'AK-SC255' AS nombre, 15 AS dispositivo_id, 'DANFOSS' AS marca UNION ALL
    SELECT 'AK-SM820A' AS nombre, 15 AS dispositivo_id, 'DANFOSS' AS marca UNION ALL
    SELECT 'AK-SM820' AS nombre, 15 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'Magellan 3550HSi' AS nombre, 3 AS dispositivo_id, 'DATALOGIC' AS marca UNION ALL
    SELECT 'Magellan 3300HSi' AS nombre, 3 AS dispositivo_id, 'DATALOGIC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 3 AS dispositivo_id, 'DATALOGIC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 3 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 3 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 3 AS dispositivo_id, 'NEC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 21 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 21 AS dispositivo_id, 'DATALOGIC' AS marca UNION ALL
    SELECT 'Q5M2130' AS nombre, 21 AS dispositivo_id, 'DATALOGIC' AS marca UNION ALL
    SELECT 'GBT4500' AS nombre, 21 AS dispositivo_id, 'DATALOGIC' AS marca UNION ALL
    SELECT 'D620' AS nombre, 7 AS dispositivo_id, 'PLUSTEK' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 7 AS dispositivo_id, 'PLUSTEK' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 23 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 23 AS dispositivo_id, 'NORTH' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 17 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 17 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 17 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'S3008' AS nombre, 17 AS dispositivo_id, 'AXIS' AS marca UNION ALL
    SELECT 'DIVAR' AS nombre, 17 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'DDN-2516' AS nombre, 17 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 17 AS dispositivo_id, 'GHIA' AS marca UNION ALL
    SELECT 'TC52' AS nombre, 11 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 11 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'TC58B1' AS nombre, 11 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'TC58' AS nombre, 11 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 11 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'TC58B1' AS nombre, 11 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'ZQ320' AS nombre, 10 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 10 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'M382C' AS nombre, 10 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 10 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'TM-P80II' AS nombre, 10 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 10 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'M338A' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'TM-T88VII' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'M371A' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 4 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'M372A' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'M244A' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'M388A' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'TM-T88VI' AS nombre, 4 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 4 AS dispositivo_id, 'DATALOGIC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 4 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 24 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 22 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 20 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 20 AS dispositivo_id, 'LOUROE' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 20 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'LE-070' AS nombre, 20 AS dispositivo_id, 'LOUROE' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 6 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT 'Engage One 10T (mini)' AS nombre, 6 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT 'Engage One Pro (pantalla secundaria)' AS nombre, 6 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 6 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'L7010t (T6N30AA)' AS nombre, 6 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT '3F1W9AA (Engage One)' AS nombre, 6 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT 'Engage One 10T (mini)' AS nombre, 6 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 6 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'N8910' AS nombre, 2 AS dispositivo_id, 'NEC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 2 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT 'Engage One Pro' AS nombre, 2 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT 'Engage One' AS nombre, 2 AS dispositivo_id, 'HP' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 2 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 2 AS dispositivo_id, 'NEC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 2 AS dispositivo_id, 'INTEL' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 2 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'PC3000' AS nombre, 8 AS dispositivo_id, 'REASA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 8 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'PC4000' AS nombre, 8 AS dispositivo_id, 'REASA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 8 AS dispositivo_id, 'REASA' AS marca UNION ALL
    SELECT 'PC3000' AS nombre, 8 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'PC4000' AS nombre, 8 AS dispositivo_id, 'ISB' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 8 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 8 AS dispositivo_id, 'ISB' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 16 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'SMX3000LV' AS nombre, 16 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'SMX3000L' AS nombre, 16 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 16 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'SMX3000HVT' AS nombre, 16 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 16 AS dispositivo_id, 'SCHNEIDER' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 16 AS dispositivo_id, 'REASA' AS marca UNION ALL
    SELECT '9110140A' AS nombre, 16 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'SMX3000L' AS nombre, 16 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'SMX3000LV' AS nombre, 16 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 16 AS dispositivo_id, 'ISB' AS marca UNION ALL
    SELECT 'SMX3000LV' AS nombre, 16 AS dispositivo_id, 'SCHNEIDER' AS marca UNION ALL
    SELECT 'MX67' AS nombre, 13 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 13 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 13 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'MX67' AS nombre, 13 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 12 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MS120' AS nombre, 12 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 12 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'MS120-24P' AS nombre, 12 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MS130-24P' AS nombre, 12 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'C9200L-24P-4G' AS nombre, 12 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MS120' AS nombre, 12 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'MS120-24P' AS nombre, 12 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 12 AS dispositivo_id, 'BOSCH' AS marca UNION ALL
    SELECT 'ET51CE' AS nombre, 9 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'ET51' AS nombre, 9 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 9 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'ET45' AS nombre, 9 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'ET45CB' AS nombre, 9 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 9 AS dispositivo_id, 'ZEBRA' AS marca UNION ALL
    SELECT 'ET45' AS nombre, 9 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'ET45CB' AS nombre, 9 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'MX67' AS nombre, 18 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 18 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 18 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MS120-24P' AS nombre, 18 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MS120' AS nombre, 18 AS dispositivo_id, 'CISCO' AS marca UNION ALL
    SELECT 'MX67' AS nombre, 18 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'SMT750C' AS nombre, 5 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'SMT1000' AS nombre, 5 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'Kit soporte + cadena (accesorio SMX3000LV)' AS nombre, 5 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 5 AS dispositivo_id, 'SIN MARCA' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 5 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'SUA1000' AS nombre, 5 AS dispositivo_id, 'APC' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 5 AS dispositivo_id, 'EPSON' AS marca UNION ALL
    SELECT 'GENERICO' AS nombre, 5 AS dispositivo_id, 'DATALOGIC' AS marca
) v
JOIN `marca` ma ON ma.nombre = v.marca
WHERE NOT EXISTS (
    SELECT 1 FROM `modelo` mo
    WHERE mo.dispositivo_id = v.dispositivo_id AND mo.marca_id = ma.id AND mo.nombre = v.nombre
);
