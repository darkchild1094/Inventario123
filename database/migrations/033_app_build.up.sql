-- 033 — Historial de APKs subidas desde el panel admin.
--
-- Hasta ahora subir una versión nueva de la app era mandar el .apk por SCP al
-- servidor a mano. Este módulo (solo admin) deja subirlo desde el navegador y
-- guarda el historial: quién la subió, cuándo, versionCode/versionName y el
-- tamaño — útil para confirmar que el archivo que la gente va a instalar es
-- el que se creía subir, y para poder volver a la anterior si algo sale mal.
--
-- El archivo vive fuera de `public/uploads/` (esa carpeta solo sirve imágenes,
-- ver public/index.php) en `storage/apks/`; se descarga por una ruta propia
-- del controlador (ApkController::descargar), pública para que un técnico la
-- abra desde el celular sin iniciar sesión en el panel web.

CREATE TABLE `app_build` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `version_code` int(10) unsigned NOT NULL,
  `version_name` varchar(20) NOT NULL,
  `archivo` varchar(150) NOT NULL,
  `tamano_bytes` bigint(20) unsigned NOT NULL,
  `notas` varchar(500) DEFAULT NULL,
  `subido_por` int(10) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_appbuild_usuario` (`subido_por`),
  CONSTRAINT `fk_appbuild_usuario` FOREIGN KEY (`subido_por`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
