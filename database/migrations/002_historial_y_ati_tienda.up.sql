-- =====================================================================
--  Migración 002 · Historial de movimientos + ATI responsable por tienda
--  Fecha: 2026-09-03  ·  Base: femsa_assets  ·  Motor: MariaDB 10.4 / InnoDB
-- ---------------------------------------------------------------------
--  Habilita en la aplicación el "stock por tienda" (migración 001) más:
--    · tienda.ati_usuario_id  -> ATI responsable de garantía/baja de esa tienda
--    · tabla `movimiento`     -> bitácora completa de altas, cambios de
--      estatus/stock, reemplazos, bajas y eliminaciones de activos.
--
--  Ejecutar UNA vez.  Reversión: 002_historial_y_ati_tienda.down.sql
--  El seed inicial de la bitácora está en 002b_seed_historial.sql
-- =====================================================================

-- 1) ATI responsable por tienda ----------------------------------------
ALTER TABLE `tienda`
  ADD COLUMN `ati_usuario_id` int(10) UNSIGNED DEFAULT NULL AFTER `plaza_id`,
  ADD KEY `fk_tienda_ati` (`ati_usuario_id`),
  ADD CONSTRAINT `fk_tienda_ati` FOREIGN KEY (`ati_usuario_id`)
      REFERENCES `usuario` (`id`) ON DELETE SET NULL;

-- 2) Bitácora de movimientos -----------------------------------------
CREATE TABLE `movimiento` (
  `id`                    int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `activo_id`             int(10) UNSIGNED DEFAULT NULL,
  `evento` enum('alta','cambio_status','cambio_stock','reemplazo_entra',
                'reemplazo_sale','edicion','baja','eliminacion') NOT NULL,
  `status_anterior`       enum('en_bodega','en_uso','baja','garantia','asignado') DEFAULT NULL,
  `status_nuevo`          enum('en_bodega','en_uso','baja','garantia','asignado') DEFAULT NULL,
  `stock_anterior_id`     int(10) UNSIGNED DEFAULT NULL,
  `stock_nuevo_id`        int(10) UNSIGNED DEFAULT NULL,
  `tienda_id`             int(10) UNSIGNED DEFAULT NULL,
  `plaza_id`              int(10) UNSIGNED DEFAULT NULL,
  `activo_relacionado_id` int(10) UNSIGNED DEFAULT NULL,
  `grupo_id`              char(36) DEFAULT NULL,
  `usuario_id`            int(10) UNSIGNED DEFAULT NULL,
  `nota`                  varchar(255) DEFAULT NULL,
  `datos_json`            longtext DEFAULT NULL,
  `creado_en`             timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mov_activo`  (`activo_id`,`creado_en`),
  KEY `idx_mov_tienda`  (`tienda_id`,`creado_en`),
  KEY `idx_mov_plaza`   (`plaza_id`,`creado_en`),
  KEY `idx_mov_usuario` (`usuario_id`),
  KEY `idx_mov_evento`  (`evento`),
  KEY `idx_mov_grupo`   (`grupo_id`),
  KEY `fk_mov_relacionado` (`activo_relacionado_id`),
  KEY `fk_mov_stock_ant`   (`stock_anterior_id`),
  KEY `fk_mov_stock_new`   (`stock_nuevo_id`),
  CONSTRAINT `fk_mov_activo`      FOREIGN KEY (`activo_id`)             REFERENCES `activo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_relacionado` FOREIGN KEY (`activo_relacionado_id`) REFERENCES `activo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_stock_ant`   FOREIGN KEY (`stock_anterior_id`)     REFERENCES `stock` (`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_mov_stock_new`   FOREIGN KEY (`stock_nuevo_id`)        REFERENCES `stock` (`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_mov_tienda`      FOREIGN KEY (`tienda_id`)             REFERENCES `tienda` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_plaza`       FOREIGN KEY (`plaza_id`)              REFERENCES `plaza` (`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_mov_usuario`     FOREIGN KEY (`usuario_id`)            REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
