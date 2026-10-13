-- =====================================================================
--  Migración 036 · Inventario (checklist) de tienda
--  Base: femsa_assets
-- ---------------------------------------------------------------------
--  Igual espíritu que inventario_bodega (027) pero NO es lo mismo: aquí
--  no hay período mensual, es por-visita, y el snapshot es "los activos
--  instalados en ESTA tienda ahora mismo" (no una bodega). Al abrirlo se
--  toma ese snapshot; cada uno se marca encontrado/no-encontrado al
--  escanear o tocar en la app (✓/✗), con nota opcional de justificación
--  en los no encontrados. Al cerrar, la tienda guarda el resumen
--  (fecha, encontrados, no encontrados) — eso es lo que el Dashboard y
--  la lista de Tiendas usan para mostrar "Pendiente" / "completo" / "N
--  no encontrados". No toca activo.status ni genera movimiento: es una
--  auditoría, no un cambio de estado.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE `inventario_tienda` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tienda_id` int(10) unsigned NOT NULL,
  `estado` enum('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  `usuario_id` int(10) unsigned NOT NULL,
  `total_esperado` int(10) unsigned NOT NULL DEFAULT 0,
  `total_encontrado` int(10) unsigned NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `cerrado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_invtienda_tienda` (`tienda_id`),
  KEY `fk_invtienda_usuario` (`usuario_id`),
  CONSTRAINT `fk_invtienda_tienda` FOREIGN KEY (`tienda_id`) REFERENCES `tienda` (`id`),
  CONSTRAINT `fk_invtienda_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Un activo "nuevo" (encontrado en la tienda sin estar en el snapshot
-- original) se agrega vía el FAB "Agregar activo" del modo inventario y
-- entra ya marcado encontrado=1 con fila propia aquí (no viene del
-- snapshot, se inserta directo al crearlo).
CREATE TABLE `inventario_tienda_detalle` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inventario_id` int(10) unsigned NOT NULL,
  `activo_id` int(10) unsigned NOT NULL,
  `encontrado` tinyint(1) NOT NULL DEFAULT 0,
  `escaneado_en` timestamp NULL DEFAULT NULL,
  `escaneado_por` int(10) unsigned DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invtiendadet_inv_activo` (`inventario_id`,`activo_id`),
  KEY `fk_invtiendadet_activo` (`activo_id`),
  KEY `fk_invtiendadet_usuario` (`escaneado_por`),
  CONSTRAINT `fk_invtiendadet_activo` FOREIGN KEY (`activo_id`) REFERENCES `activo` (`id`),
  CONSTRAINT `fk_invtiendadet_inventario` FOREIGN KEY (`inventario_id`) REFERENCES `inventario_tienda` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invtiendadet_usuario` FOREIGN KEY (`escaneado_por`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Resumen del último inventario, directo en la tienda — evita un JOIN
-- costoso para pintar la lista de Tiendas / la card del Dashboard.
ALTER TABLE `tienda`
  ADD COLUMN `ultimo_inv_fecha` date DEFAULT NULL AFTER `ati_usuario_id`,
  ADD COLUMN `ultimo_inv_encontrados` int(10) unsigned DEFAULT NULL AFTER `ultimo_inv_fecha`,
  ADD COLUMN `ultimo_inv_no_encontrados` int(10) unsigned DEFAULT NULL AFTER `ultimo_inv_encontrados`;
