-- 035 — Notificaciones de envío (rediseño: Bodega↔Mi stock↔usuario es
-- inmediato, sin aceptación; la notificación es solo aviso de que a alguien
-- le llegó o le salió equipo). No existía nada de esto: ni tabla, ni modelo,
-- ni referencia en el backend — se confirmó explorando todo el código.
--
-- Se escribe en el mismo movimiento que hace "Enviar" y se espeja a Firebase
-- Realtime Database para entrega inmediata; esta tabla es la que respalda la
-- pantalla "Notificaciones" aunque el destinatario haya estado offline.

CREATE TABLE `notificacion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_destino_id` int(10) unsigned NOT NULL,
  `de_usuario_id` int(10) unsigned NOT NULL,
  `direccion` enum('a_bodega','a_stock') NOT NULL,
  `lugar` varchar(150) NOT NULL,
  `grupo_id` char(36) NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notificacion_destino` (`usuario_destino_id`),
  KEY `fk_notificacion_de` (`de_usuario_id`),
  KEY `idx_notificacion_grupo` (`grupo_id`),
  CONSTRAINT `fk_notificacion_destino` FOREIGN KEY (`usuario_destino_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notificacion_de` FOREIGN KEY (`de_usuario_id`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Los activos de una notificación: una fila de `notificacion` puede avisar
-- de varios activos enviados de golpe (el "Enviar N activo(s)" del nuevo
-- diseño). `grupo_id` en `movimiento` ya los agrupa igual; se guarda también
-- aquí por id directo para no tener que reconstruir el grupo al mostrar la
-- pantalla.
CREATE TABLE `notificacion_activo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `notificacion_id` int(10) unsigned NOT NULL,
  `activo_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_notifact_notificacion` (`notificacion_id`),
  KEY `fk_notifact_activo` (`activo_id`),
  CONSTRAINT `fk_notifact_notificacion` FOREIGN KEY (`notificacion_id`) REFERENCES `notificacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifact_activo` FOREIGN KEY (`activo_id`) REFERENCES `activo` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
