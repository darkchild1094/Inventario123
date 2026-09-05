-- =====================================================================
--  Migración 008 · Alta de 20 cámaras Verkada — instalación RENTEC CCTV
--  Fecha: 2026-09-05 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  4 tiendas de Valles recién migradas a CCTV Verkada (folios de
--  instalación 1806054, 1806055, 1806057, 1806058). 5 cámaras cada una:
--    Glorieta   (50TEJ, tienda 123) · Motolinia (50S9S, tienda 119)
--    Apolo      (50YNI, tienda 125) · Frontera  (50IMH, tienda 111)
--  `serie` = número de serie real del equipo (S/N); `num_activo` = folio
--  de activo fijo (AF) del reporte de instalación.
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
    SELECT 52852 AS id, 'D7XW-D3FT-PTG9' AS serie, NULL AS codigo_barras, '05588095' AS num_activo, 1133 AS modelo_id, 'en_uso' AS status, 134 AS stock_id, 123 AS tienda_uso_id
    UNION ALL SELECT 52853, 'KC7N-DGPD-ALJH', NULL, '05588092', 579,  'en_uso', 134, 123
    UNION ALL SELECT 52854, 'XPDM-A3TT-NWFQ', NULL, '05588094', 1134, 'en_uso', 134, 123
    UNION ALL SELECT 52855, 'KC7K-AD7N-4LA6', NULL, '05588091', 579,  'en_uso', 134, 123
    UNION ALL SELECT 52856, 'KC7A-77EK-DN7C', NULL, '05588093', 579,  'en_uso', 134, 123
    UNION ALL SELECT 52857, 'XPD6-D7KR-HPRH', NULL, '05588144', 1134, 'en_uso', 130, 119
    UNION ALL SELECT 52858, 'KC7L-9KMD-XCCX', NULL, '05588142', 579,  'en_uso', 130, 119
    UNION ALL SELECT 52859, 'KC7R-GCNP-XMNG', NULL, '05588141', 579,  'en_uso', 130, 119
    UNION ALL SELECT 52860, 'D7XL-X9P7-3CMM', NULL, '05588145', 1133, 'en_uso', 130, 119
    UNION ALL SELECT 52861, 'KC7K-RXJK-FR4P', NULL, '05588143', 579,  'en_uso', 130, 119
    UNION ALL SELECT 52862, 'XPDX-L3YR-DYN4', NULL, '05588119', 1134, 'en_uso', 136, 125
    UNION ALL SELECT 52863, 'KC79-MYF4-4MKM', NULL, '05588117', 579,  'en_uso', 136, 125
    UNION ALL SELECT 52864, 'KC7J-XK46-DDF6', NULL, '05588118', 579,  'en_uso', 136, 125
    UNION ALL SELECT 52865, 'KC7E-TGK7-C7RR', NULL, '05588116', 579,  'en_uso', 136, 125
    UNION ALL SELECT 52866, 'D7XD-HJDQ-96WE', NULL, '05588120', 1133, 'en_uso', 136, 125
    UNION ALL SELECT 52867, 'D7XT-FTGJ-LG9F', NULL, '05588035', 1133, 'en_uso', 122, 111
    UNION ALL SELECT 52868, 'KC7X-Y99J-6MXP', NULL, '05588032', 579,  'en_uso', 122, 111
    UNION ALL SELECT 52869, 'KC77-AHQK-PTYQ', NULL, '05588033', 579,  'en_uso', 122, 111
    UNION ALL SELECT 52870, 'XPDP-ML7K-TEF6', NULL, '05588034', 1134, 'en_uso', 122, 111
    UNION ALL SELECT 52871, 'KC7P-YHWG-JTLJ', NULL, '05588031', 579,  'en_uso', 122, 111
) v
WHERE NOT EXISTS (SELECT 1 FROM `activo` a WHERE a.id = v.id);

-- Corrección auto-aplicable: si esta migración ya se había corrido antes
-- de tener el reporte con S/N real (con el folio AF como serie temporal),
-- corrige la serie sin tocar num_activo. En una instalación nueva el
-- INSERT de arriba ya deja la serie correcta, así que esto no hace nada.
UPDATE `activo` SET `serie` = CASE `num_activo`
    WHEN '05588095' THEN 'D7XW-D3FT-PTG9' WHEN '05588092' THEN 'KC7N-DGPD-ALJH'
    WHEN '05588094' THEN 'XPDM-A3TT-NWFQ' WHEN '05588091' THEN 'KC7K-AD7N-4LA6'
    WHEN '05588093' THEN 'KC7A-77EK-DN7C' WHEN '05588144' THEN 'XPD6-D7KR-HPRH'
    WHEN '05588142' THEN 'KC7L-9KMD-XCCX' WHEN '05588141' THEN 'KC7R-GCNP-XMNG'
    WHEN '05588145' THEN 'D7XL-X9P7-3CMM' WHEN '05588143' THEN 'KC7K-RXJK-FR4P'
    WHEN '05588119' THEN 'XPDX-L3YR-DYN4' WHEN '05588117' THEN 'KC79-MYF4-4MKM'
    WHEN '05588118' THEN 'KC7J-XK46-DDF6' WHEN '05588116' THEN 'KC7E-TGK7-C7RR'
    WHEN '05588120' THEN 'D7XD-HJDQ-96WE' WHEN '05588035' THEN 'D7XT-FTGJ-LG9F'
    WHEN '05588032' THEN 'KC7X-Y99J-6MXP' WHEN '05588033' THEN 'KC77-AHQK-PTYQ'
    WHEN '05588034' THEN 'XPDP-ML7K-TEF6' WHEN '05588031' THEN 'KC7P-YHWG-JTLJ'
    ELSE `serie` END
WHERE `id` BETWEEN 52852 AND 52871 AND `serie` = `num_activo`;

-- Movimiento 'alta' por cada cámara (si aún no existe).
INSERT INTO `movimiento` (`activo_id`, `evento`, `status_nuevo`, `stock_nuevo_id`, `tienda_id`, `plaza_id`, `usuario_id`, `nota`)
SELECT a.id, 'alta', 'en_uso', a.stock_id, a.tienda_uso_id, 1, 1, 'Instalación RENTEC CCTV (Verkada).'
FROM `activo` a
WHERE a.id BETWEEN 52852 AND 52871
  AND NOT EXISTS (SELECT 1 FROM `movimiento` m WHERE m.activo_id = a.id AND m.evento = 'alta');
