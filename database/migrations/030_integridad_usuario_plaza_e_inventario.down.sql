-- Revierte 030. Los índices que respaldaban las FK ya existían antes y se
-- conservan; sólo se quitan las restricciones añadidas.

ALTER TABLE `inventario_bodega`
  DROP CONSTRAINT `chk_invbod_objetivo`;

ALTER TABLE `usuario_plaza`
  DROP FOREIGN KEY `fk_usuario_plaza_usuario`,
  DROP FOREIGN KEY `fk_usuario_plaza_plaza`;
