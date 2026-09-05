-- =====================================================================
--  Migración 013 · Tiendas del MAF que faltaban en el catálogo
--  Fecha: 2026-09-06 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  6 tiendas reales que aparecen en el Excel MAF (con activos propios)
--  pero no existían en `tienda`. Confirmadas por el usuario. Todas en la
--  región Tamaulipas: 5 en el distrito de Ciudad Valles, 1 en Matamoros
--  (según columna "Distrito" del MAF, cruzada contra tiendas ya
--  registradas de esos mismos distritos).
--  El Cr original de "Capitan Tam" viene corrupto en el Excel (Excel lo
--  guardó en notación científica, p.ej. "5.03E+21"); se usa el código
--  sintético MAF-CAPITAN-TAM en su lugar (el import de activos lo mapea
--  explícitamente, no por texto crudo).
--  El trigger trg_crear_stock_tienda crea automáticamente su stock.
--  Idempotente (no duplica si ya existe el cr_tienda).
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO `tienda` (`cr_tienda`, `nombre`, `coordenadas`, `plaza_id`, `ati_usuario_id`)
SELECT * FROM (
    SELECT 'MAF-CAPITAN-TAM' AS cr_tienda, 'Capitan Tam'          AS nombre, NULL AS coordenadas, 1 AS plaza_id, NULL AS ati_usuario_id
    UNION ALL SELECT '5020',    'Del Pino Maf',           NULL, 1, NULL
    UNION ALL SELECT '5010000', 'Estacion Cardenas Maf',  NULL, 1, NULL
    UNION ALL SELECT '50B61',   'Movil 1 Cd Valles Maf',  NULL, 1, NULL
    UNION ALL SELECT '50EE4',   'Turistico 1 Maf',        NULL, 1, NULL
    UNION ALL SELECT '50OW4',   'Movil Matamoros Maf',    NULL, 3, NULL
) v
WHERE NOT EXISTS (SELECT 1 FROM `tienda` WHERE `cr_tienda` = v.cr_tienda);
