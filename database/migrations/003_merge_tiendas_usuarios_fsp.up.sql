-- =====================================================================
--  Migración 003 · Alta de tiendas, ATI responsables y FS de Valles
--  Fecha: 2026-09-04  ·  Base: femsa_assets  ·  Fuente: fieldserviceplus_nps
-- ---------------------------------------------------------------------
--  Fusiona el catálogo del sistema NPS (fieldserviceplus_nps03092026.sql,
--  cargado en la BD auxiliar `fsp_src`) con `femsa_assets`:
--
--    1. Usuarios nuevos:
--         · ATI  : Enrique Gil Zarate  (Rosa ya existía como usuario 4)
--         · FS   : todos los del dominio @getic.com.mx de la plaza Valles
--                  que aún no estaban (José Arcos, José Flores, Óscar Dueñez).
--                  Roberto Patiño NO se agrega como FS: es el coordinador (usuario 2).
--    2. Tiendas faltantes (57) de las plazas 1-4, con sus coordenadas.
--    3. `tienda.ati_usuario_id`: se asigna SOLO cuando el asesor TI de la
--       fuente es Rosa (fsp id 128 -> femsa 4) o Enrique Gil (fsp id 129 ->
--       el usuario recién creado). Cualquier otro asesor queda en NULL.
--
--  Requiere que `fsp_src` exista (import del dump NPS). Ejecutar UNA vez.
--  Reversión: 003_merge_tiendas_usuarios_fsp.down.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1) Usuarios nuevos ------------------------------------------------
--    (los triggers de `usuario` crean su stock y su fila en usuario_plaza)
INSERT INTO `usuario` (`nombre`, `email`, `password`, `foto`, `plaza_id`, `tipo`)
SELECT s.nombre_completo, s.correo, s.password_hash, NULL, 1,
       CASE WHEN s.rol_id = 1 THEN 'ati' ELSE 'fs' END
FROM `fsp_src`.`usuario` s
WHERE s.id IN (129, 101, 102, 104)
  AND s.correo COLLATE utf8mb4_general_ci NOT IN (SELECT email FROM `usuario`);

SET @enrique_id := (SELECT id FROM `usuario` WHERE email = 'enrique.gil@oxxo.com' LIMIT 1);
SET @rosa_id    := 4;

-- 2) Tiendas faltantes -------------------------------------------
INSERT INTO `tienda` (`cr_tienda`, `nombre`, `coordenadas`, `plaza_id`, `ati_usuario_id`)
SELECT s.codigo, s.nombre,
       CASE WHEN s.latitud IS NULL OR s.longitud IS NULL THEN NULL
            ELSE CONCAT(s.latitud, ', ', s.longitud) END,
       s.plaza_id,
       CASE s.asesor_ti_usuario_id
            WHEN 128 THEN @rosa_id
            WHEN 129 THEN @enrique_id
            ELSE NULL END
FROM `fsp_src`.`tienda` s
LEFT JOIN `tienda` f ON f.cr_tienda = s.codigo COLLATE utf8mb4_general_ci
WHERE f.id IS NULL
  AND s.plaza_id BETWEEN 1 AND 4;

-- 3) ATI responsable en las tiendas YA existentes ----------------
UPDATE `tienda` f
JOIN `fsp_src`.`tienda` s ON s.codigo COLLATE utf8mb4_general_ci = f.cr_tienda
SET f.ati_usuario_id = CASE s.asesor_ti_usuario_id
                            WHEN 128 THEN @rosa_id
                            WHEN 129 THEN @enrique_id
                            ELSE f.ati_usuario_id END
WHERE s.asesor_ti_usuario_id IN (128, 129);
