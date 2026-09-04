-- =====================================================================
--  Seed 002b · Poblado inicial de la bitácora `movimiento`
--  Ejecutar UNA vez, DESPUÉS de 002_historial_y_ati_tienda.up.sql
-- ---------------------------------------------------------------------
--  · Salvaguarda: cualquier activo 'en_uso' cuyo stock NO sea de tienda
--    se mueve al stock de su tienda_uso_id (hoy 0 filas).
--  · Registra un movimiento 'alta' por cada activo ya existente, con un
--    snapshot en datos_json, para que las líneas de tiempo no nazcan vacías.
-- =====================================================================

-- Fijar la sesión a UTC para que creado_en conserve el instante real de
-- fecha_alta (las columnas TIMESTAMP se guardan en UTC). Igual criterio
-- que el volcado de phpMyAdmin.
SET time_zone = '+00:00';
-- Asegurar UTF-8 para que los acentos de `nota` se guarden bien aunque el
-- cliente de consola tenga otro charset por defecto.
SET NAMES utf8mb4;

-- 1) Salvaguarda de coherencia en_uso -> stock de tienda ------------
UPDATE `activo` a
JOIN `stock` s        ON s.id = a.stock_id
JOIN `stock` st       ON st.tipo = 'tienda' AND st.tienda_id = a.tienda_uso_id
SET a.stock_id = st.id
WHERE a.status = 'en_uso'
  AND a.tienda_uso_id IS NOT NULL
  AND s.tipo <> 'tienda';

-- 2) Movimiento 'alta' retroactivo por activo existente -------------
INSERT INTO `movimiento`
    (`activo_id`, `evento`, `status_nuevo`, `stock_nuevo_id`, `tienda_id`, `plaza_id`,
     `usuario_id`, `nota`, `datos_json`, `creado_en`)
SELECT
    a.id, 'alta', a.status, a.stock_id, a.tienda_uso_id,
    COALESCE(s.plaza_id, u.plaza_id, tsp.plaza_id),
    NULL, 'Alta registrada retroactivamente por la migración 002b.',
    JSON_OBJECT(
        'serie', a.serie, 'codigo_barras', a.codigo_barras,
        'modelo_id', a.modelo_id, 'modelo', mo.nombre,
        'dispositivo', d.nombre, 'status', a.status
    ),
    a.fecha_alta
FROM `activo` a
LEFT JOIN `stock`  s   ON s.id = a.stock_id
LEFT JOIN `usuario` u  ON u.id = s.usuario_id
LEFT JOIN `tienda` tsp ON tsp.id = s.tienda_id
LEFT JOIN `modelo` mo  ON mo.id = a.modelo_id
LEFT JOIN `dispositivo` d ON d.id = mo.dispositivo_id
WHERE NOT EXISTS (
    SELECT 1 FROM `movimiento` m WHERE m.activo_id = a.id AND m.evento = 'alta'
);
