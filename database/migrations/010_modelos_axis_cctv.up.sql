-- =====================================================================
--  Migración 010 · Modelos AXIS (CAMARA CCTV)
--  Fecha: 2026-09-05 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Agrega 5 modelos de cámara AXIS al catálogo (dispositivo 1 = CAMARA
--  CCTV, marca 2 = AXIS): M3044-V, M3065-V, M3066-V, M4216-V, P3265-LV.
--  Idempotente (no duplica si ya existen).
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO `modelo` (`nombre`, `dispositivo_id`, `marca_id`)
SELECT * FROM (
    SELECT 'M3044-V'  AS nombre, 1 AS dispositivo_id, 2 AS marca_id
    UNION ALL SELECT 'M3065-V',  1, 2
    UNION ALL SELECT 'M3066-V',  1, 2
    UNION ALL SELECT 'M4216-V',  1, 2
    UNION ALL SELECT 'P3265-LV', 1, 2
) v
WHERE NOT EXISTS (
    SELECT 1 FROM `modelo` m
    WHERE m.dispositivo_id = v.dispositivo_id AND m.marca_id = v.marca_id AND m.nombre = v.nombre
);
