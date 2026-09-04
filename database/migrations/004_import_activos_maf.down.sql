-- =====================================================================
--  Reversión de la Migración 004
--  Restaura el catálogo original (dispositivo/modelo/area_modelo), elimina
--  la tabla `marca` y la columna `modelo.marca_id`, borra los activos
--  importados y deja los 5 activos previos como estaban.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Borrar activos importados (conservar los 5 previos)
DELETE FROM `activo` WHERE id NOT IN (61, 63, 65, 70, 110);

-- 2) Restaurar catálogo original
DELETE FROM `area_modelo`;
TRUNCATE TABLE `modelo`;
TRUNCATE TABLE `dispositivo`;

INSERT INTO `dispositivo` (`id`,`nombre`) VALUES
(1,'IMPRESORA'),(2,'ESCANER DE MESA'),(3,'CPU'),(4,'MONITOR'),(5,'ESCANER ID'),
(6,'ROUTER'),(7,'SWITCH'),(8,'ACCESS POINT'),(9,'TABLET'),(10,'HANDHELD'),
(11,'IMPRESORA PORTATIL'),(12,'GRABADOR'),(13,'CAMARA'),(14,'DECODER'),
(15,'MONITOR CCTV'),(16,'UPS'),(17,'REGULADOR');

INSERT INTO `modelo` (`id`,`nombre`,`dispositivo_id`) VALUES
(1,'EPSON TM-T88V',1),(2,'EPSON TM-T88VI',1),(3,'EPSON TM-T88VII',1),
(4,'Datalogic Magellan 3300HSi',2),(5,'Datalogic Magellan 3350HSi',2),
(6,'NEC N8910-078SABB0A',3),(7,'HP Engage One Pro AiO i3-10300E8GB/256PC',3),
(8,'HP Engage One 10t NS-T (M76394-001)',4),(9,'NEC Customer LCD N8910-07SP0BB',4),
(10,'Cisco Meraki MX67',6),(11,'Cisco Meraki MS120-24P',7),(12,'TC530E',10),
(13,'SMT750C',16),(14,'SMT750C',16),(15,'SMT1000',16),(16,'PC-4000',17),
(17,'PC-3000',17),(19,'PC-1000',17);

INSERT INTO `area_modelo` (`id`,`area_id`,`modelo_id`) VALUES
(1,6,1),(2,6,2),(3,6,3),(4,6,4),(5,6,5),(6,6,6),(7,6,7),(8,6,8),(9,6,9),
(10,10,10),(11,10,11);

-- 3) Quitar marca_id de modelo y la tabla marca
ALTER TABLE `modelo` DROP FOREIGN KEY `fk_modelo_marca`;
ALTER TABLE `modelo` DROP INDEX `fk_modelo_marca`, DROP COLUMN `marca_id`;
DROP TABLE IF EXISTS `marca`;

-- 4) Restaurar los 5 activos previos a su estado original
UPDATE `activo` SET modelo_id=2,  status='asignado', tienda_uso_id=NULL, stock_id=2, procedencia_tienda_id=NULL WHERE id=61;
UPDATE `activo` SET modelo_id=5,  status='asignado', tienda_uso_id=NULL, stock_id=2, procedencia_tienda_id=NULL WHERE id=63;
UPDATE `activo` SET modelo_id=12, status='asignado', tienda_uso_id=NULL, stock_id=2, procedencia_tienda_id=104  WHERE id=65;
UPDATE `activo` SET modelo_id=2,  status='asignado', tienda_uso_id=NULL, stock_id=2, procedencia_tienda_id=NULL WHERE id=70;
UPDATE `activo` SET modelo_id=6,  status='asignado', tienda_uso_id=NULL, stock_id=2, procedencia_tienda_id=114  WHERE id=110;

-- 5) AUTO_INCREMENT
ALTER TABLE `activo` AUTO_INCREMENT = 114;
ALTER TABLE `dispositivo` AUTO_INCREMENT = 20;
ALTER TABLE `modelo` AUTO_INCREMENT = 20;

SET FOREIGN_KEY_CHECKS = 1;
