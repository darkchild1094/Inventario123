-- =====================================================================
--  Migración 020 · Solicitud de traslado a bodega con doble firma
--  Fecha: 2026-09-06 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Nuevo flujo: un ingeniero (fs) manda activos de su stock personal
--  (status 'asignado') a la bodega. Requiere aprobación del coordinador
--  y firma digital dibujada de AMBOS. Mientras está 'pendiente' los
--  activos quedan bloqueados para otros cambios de estatus.
--
--    solicitud_traslado         → cabecera (estado, firmas, plaza, bodega)
--    solicitud_traslado_activo  → N activos por solicitud
--    movimiento.solicitud_traslado_id → enlaza la bitácora al ejecutar
--
--  Solo esquema: se versiona el .up completo.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `solicitud_traslado` (
  `id`                     int(10) unsigned NOT NULL AUTO_INCREMENT,
  `estado`                 enum('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  `plaza_id`               int(10) unsigned NOT NULL,
  `origen_usuario_id`      int(10) unsigned NOT NULL,
  `destino_bodega_id`      int(10) unsigned NOT NULL,
  `solicitante_id`         int(10) unsigned NOT NULL,
  `aprobador_id`           int(10) unsigned DEFAULT NULL,
  `firma_solicitante`      varchar(255) DEFAULT NULL,
  `firma_aprobador`        varchar(255) DEFAULT NULL,
  `firmado_solicitante_en` timestamp NULL DEFAULT NULL,
  `firmado_aprobador_en`   timestamp NULL DEFAULT NULL,
  `nota`                   varchar(255) DEFAULT NULL,
  `motivo_rechazo`         varchar(255) DEFAULT NULL,
  `grupo_id`               char(36) DEFAULT NULL,
  `creado_en`              timestamp NOT NULL DEFAULT current_timestamp(),
  `resuelto_en`            timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sol_estado_plaza` (`estado`,`plaza_id`),
  KEY `idx_sol_origen` (`origen_usuario_id`),
  KEY `fk_sol_bodega` (`destino_bodega_id`),
  KEY `fk_sol_solic` (`solicitante_id`),
  KEY `fk_sol_aprob` (`aprobador_id`),
  CONSTRAINT `fk_sol_plaza`  FOREIGN KEY (`plaza_id`)          REFERENCES `plaza` (`id`),
  CONSTRAINT `fk_sol_origen` FOREIGN KEY (`origen_usuario_id`) REFERENCES `usuario` (`id`),
  CONSTRAINT `fk_sol_bodega` FOREIGN KEY (`destino_bodega_id`) REFERENCES `bodega` (`id`),
  CONSTRAINT `fk_sol_solic`  FOREIGN KEY (`solicitante_id`)    REFERENCES `usuario` (`id`),
  CONSTRAINT `fk_sol_aprob`  FOREIGN KEY (`aprobador_id`)      REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `solicitud_traslado_activo` (
  `solicitud_id` int(10) unsigned NOT NULL,
  `activo_id`    int(10) unsigned NOT NULL,
  PRIMARY KEY (`solicitud_id`,`activo_id`),
  KEY `idx_sta_activo` (`activo_id`),
  CONSTRAINT `fk_sta_sol`    FOREIGN KEY (`solicitud_id`) REFERENCES `solicitud_traslado` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sta_activo` FOREIGN KEY (`activo_id`)    REFERENCES `activo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `movimiento`
  ADD COLUMN `solicitud_traslado_id` int(10) unsigned DEFAULT NULL AFTER `grupo_id`,
  ADD KEY `idx_mov_solicitud` (`solicitud_traslado_id`),
  ADD CONSTRAINT `fk_mov_solicitud` FOREIGN KEY (`solicitud_traslado_id`)
      REFERENCES `solicitud_traslado` (`id`) ON DELETE SET NULL;
