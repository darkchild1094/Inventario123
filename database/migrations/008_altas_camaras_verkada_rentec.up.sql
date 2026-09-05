-- =====================================================================
--  Migración 008 · Alta de 20 cámaras Verkada — instalación RENTEC CCTV
--  Fecha: 2026-09-05 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  4 tiendas de Valles recién migradas a CCTV Verkada (folios de
--  instalación 1806054, 1806055, 1806057, 1806058). 5 cámaras cada una:
--    Glorieta   (50TEJ, tienda 123) · Motolinia (50S9S, tienda 119)
--    Apolo      (50YNI, tienda 125) · Frontera  (50IMH, tienda 111)
--  No traían serie de fábrica en el reporte de instalación; se usa el
--  número de activo fijo (AF) como serie y como num_activo, igual que el
--  criterio de la migración 004 para equipos sin serie real.
--  Aplicada originalmente vía el propio flujo de la app (ActivoGuardado),
--  no con este script — se documenta aquí para que el esquema de datos
--  quede reproducible. Reversión: 008_altas_camaras_verkada_rentec.down.sql
-- =====================================================================

SET NAMES utf8mb4;

-- Modelos VERKADA (dispositivo 1 = CAMARA CCTV, marca 19 = VERKADA) que
-- no existían todavía en el catálogo.
INSERT INTO `modelo` (`id`, `nombre`, `dispositivo_id`, `marca_id`)
SELECT * FROM (SELECT 1133 AS id, 'CM42-256S-HW' AS nombre, 1 AS dispositivo_id, 19 AS marca_id
               UNION ALL SELECT 1134, 'CF83-512E-HW', 1, 19) v
WHERE NOT EXISTS (SELECT 1 FROM `modelo` m WHERE m.id = v.id);

-- Las 20 cámaras, ya EN USO en el stock de su tienda.
INSERT INTO `activo` (`id`, `serie`, `codigo_barras`, `num_activo`, `modelo_id`, `status`, `stock_id`, `tienda_uso_id`)
SELECT * FROM (
    SELECT 52852 AS id, '05588095' AS serie, NULL AS codigo_barras, '05588095' AS num_activo, 1133 AS modelo_id, 'en_uso' AS status, 134 AS stock_id, 123 AS tienda_uso_id
    UNION ALL SELECT 52853, '05588092', NULL, '05588092', 579,  'en_uso', 134, 123
    UNION ALL SELECT 52854, '05588094', NULL, '05588094', 1134, 'en_uso', 134, 123
    UNION ALL SELECT 52855, '05588091', NULL, '05588091', 579,  'en_uso', 134, 123
    UNION ALL SELECT 52856, '05588093', NULL, '05588093', 579,  'en_uso', 134, 123
    UNION ALL SELECT 52857, '05588144', NULL, '05588144', 1134, 'en_uso', 130, 119
    UNION ALL SELECT 52858, '05588142', NULL, '05588142', 579,  'en_uso', 130, 119
    UNION ALL SELECT 52859, '05588141', NULL, '05588141', 579,  'en_uso', 130, 119
    UNION ALL SELECT 52860, '05588145', NULL, '05588145', 1133, 'en_uso', 130, 119
    UNION ALL SELECT 52861, '05588143', NULL, '05588143', 579,  'en_uso', 130, 119
    UNION ALL SELECT 52862, '05588119', NULL, '05588119', 1134, 'en_uso', 136, 125
    UNION ALL SELECT 52863, '05588117', NULL, '05588117', 579,  'en_uso', 136, 125
    UNION ALL SELECT 52864, '05588118', NULL, '05588118', 579,  'en_uso', 136, 125
    UNION ALL SELECT 52865, '05588116', NULL, '05588116', 579,  'en_uso', 136, 125
    UNION ALL SELECT 52866, '05588120', NULL, '05588120', 1133, 'en_uso', 136, 125
    UNION ALL SELECT 52867, '05588035', NULL, '05588035', 1133, 'en_uso', 122, 111
    UNION ALL SELECT 52868, '05588032', NULL, '05588032', 579,  'en_uso', 122, 111
    UNION ALL SELECT 52869, '05588033', NULL, '05588033', 579,  'en_uso', 122, 111
    UNION ALL SELECT 52870, '05588034', NULL, '05588034', 1134, 'en_uso', 122, 111
    UNION ALL SELECT 52871, '05588031', NULL, '05588031', 579,  'en_uso', 122, 111
) v
WHERE NOT EXISTS (SELECT 1 FROM `activo` a WHERE a.id = v.id);

-- Movimiento 'alta' por cada cámara (si aún no existe).
INSERT INTO `movimiento` (`activo_id`, `evento`, `status_nuevo`, `stock_nuevo_id`, `tienda_id`, `plaza_id`, `usuario_id`, `nota`)
SELECT a.id, 'alta', 'en_uso', a.stock_id, a.tienda_uso_id, 1, 1, 'Instalación RENTEC CCTV (Verkada).'
FROM `activo` a
WHERE a.id BETWEEN 52852 AND 52871
  AND NOT EXISTS (SELECT 1 FROM `movimiento` m WHERE m.activo_id = a.id AND m.evento = 'alta');
