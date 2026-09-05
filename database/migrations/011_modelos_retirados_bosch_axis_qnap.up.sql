-- =====================================================================
--  Migración 011 · Modelos "retirados" (Bosch/AXIS S3008/QNAP) del
--  proyecto RENTEC CCTV, para dejar registro del equipo que las
--  cámaras Verkada sustituyeron.
--  Fecha: 2026-09-05 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Agrega:
--   - marca QNAP (no existía)
--   - modelo BOSCH (CAMARA CCTV): NDI-4502-A, NUC-51022-F2, NDN-50022-A3
--   - modelo AXIS  (GRABADOR CCTV, NVR 2TB): S3008
--   - modelo QNAP  (GRABADOR CCTV, NAS): TS-131K, TS-131P, TS-128
--  Idempotente (no duplica si ya existen).
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO `marca` (`nombre`)
SELECT 'QNAP' WHERE NOT EXISTS (SELECT 1 FROM `marca` WHERE `nombre` = 'QNAP');

INSERT INTO `modelo` (`nombre`, `dispositivo_id`, `marca_id`)
SELECT v.nombre, v.dispositivo_id, m.id
FROM (
    -- BOSCH · CAMARA CCTV (dispositivo_id=1)
    SELECT 'NDI-4502-A'    AS nombre, 1  AS dispositivo_id, 'BOSCH' AS marca
    UNION ALL SELECT 'NUC-51022-F2', 1,  'BOSCH'
    UNION ALL SELECT 'NDN-50022-A3', 1,  'BOSCH'
    -- AXIS · GRABADOR CCTV (dispositivo_id=17), NVR 2TB
    UNION ALL SELECT 'S3008',        17, 'AXIS'
    -- QNAP · GRABADOR CCTV (dispositivo_id=17), NAS
    UNION ALL SELECT 'TS-131K',      17, 'QNAP'
    UNION ALL SELECT 'TS-131P',      17, 'QNAP'
    UNION ALL SELECT 'TS-128',       17, 'QNAP'
) v
JOIN `marca` m ON m.nombre = v.marca
WHERE NOT EXISTS (
    SELECT 1 FROM `modelo` mo
    WHERE mo.dispositivo_id = v.dispositivo_id AND mo.marca_id = m.id AND mo.nombre = v.nombre
);
