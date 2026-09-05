-- =====================================================================
--  Reversión de la Migración 013
--  Requiere que ya no haya activos con stock en estas tiendas (revertir
--  primero la migración 015_import_activos_maf).
-- =====================================================================

DELETE s FROM `stock` s
JOIN `tienda` t ON t.id = s.tienda_id
WHERE t.`cr_tienda` IN ('MAF-CAPITAN-TAM', '5020', '5010000', '50B61', '50EE4', '50OW4');

DELETE FROM `tienda`
WHERE `cr_tienda` IN ('MAF-CAPITAN-TAM', '5020', '5010000', '50B61', '50EE4', '50OW4');
