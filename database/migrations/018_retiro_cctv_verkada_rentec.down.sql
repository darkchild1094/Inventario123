-- =====================================================================
--  Reversión de la Migración 018
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- modelo_id resuelto POR NOMBRE (los ids difieren entre local y producción)
SET @m_m3044 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='AXIS' AND mo.nombre='M3044-V');
SET @m_m3066 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='AXIS' AND mo.nombre='M3066-V');
SET @m_m4216 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='AXIS' AND mo.nombre='M4216-V');
SET @m_p3265 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='AXIS' AND mo.nombre='P3265-LV');
SET @m_ndn := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='BOSCH' AND mo.nombre='NDN-50022-A3');
SET @m_nuc := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='BOSCH' AND mo.nombre='NUC-51022-F2');
SET @m_ndi := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='BOSCH' AND mo.nombre='NDI-4502-A');
SET @m_s3008 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='GRABADOR CCTV' AND ma.nombre='AXIS' AND mo.nombre='S3008');
SET @m_cm42 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='VERKADA' AND mo.nombre='CM42-256-HW');
SET @m_cm42s := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='VERKADA' AND mo.nombre='CM42-256S-HW');
SET @m_cf83 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='VERKADA' AND mo.nombre='CF83-512E-HW');
SET @m_ts131p := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='DISCO DURO EXTERNO' AND ma.nombre='QNAP' AND mo.nombre='TS-131P');
SET @m_ts128 := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='DISCO DURO EXTERNO' AND ma.nombre='QNAP' AND mo.nombre='TS-128');
SET @m_bosch_gen := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='BOSCH' AND mo.nombre='GENERICO');
SET @m_sm_cam_gen := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='SIN MARCA' AND mo.nombre='GENERICO');
SET @m_axis_cam_gen := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='CAMARA CCTV' AND ma.nombre='AXIS' AND mo.nombre='GENERICO');
SET @m_sm_grab_gen := (SELECT mo.id FROM `modelo` mo JOIN `marca` ma ON ma.id=mo.marca_id JOIN `dispositivo` d ON d.id=mo.dispositivo_id WHERE d.nombre='GRABADOR CCTV' AND ma.nombre='SIN MARCA' AND mo.nombre='GENERICO');

-- borrar movimientos generados por la 018
DELETE FROM `movimiento` WHERE `nota` IN (
  'Ingreso a tienda desde Bodega OXXO Valles',
  'Retiro por garantía — entra AXIS M3044-V 02522819',
  'Entra en reemplazo de 04370654 (garantía)',
  'Retiro CCTV — sustituido por AXIS M3044-V (previo a Verkada)'
) OR `nota` LIKE 'Retiro CCTV — proyecto RENTEC/Verkada, folio %'
  OR `nota` LIKE 'Instalación Verkada — RENTEC CCTV, folio %';

-- borrar los 32 activos nuevos (25 Verkada + 5 M3044-V + 1 TS-131P + 1 TS-128)
DELETE FROM `activo` WHERE
     (`codigo_barras` LIKE '055880%' OR `codigo_barras` LIKE '055881%')
  OR (`codigo_barras` IN ('05450273','05450274','05450275','05450276','02522819','02753474')
      AND `serie` = `codigo_barras`)
  OR `serie` = 'MAF-NA-FRONTERA-TS128';

-- revertir correcciones de modelo
UPDATE `activo` SET `modelo_id`=@m_axis_cam_gen WHERE `codigo_barras` IN ('04370655','04370656','04370657');
UPDATE `activo` SET `modelo_id`=@m_bosch_gen WHERE `codigo_barras` IN ('02753420','02753421','02753422','03417685','03417686','03417688');
UPDATE `activo` SET `modelo_id`=@m_sm_cam_gen WHERE `codigo_barras` IN ('03417687');
UPDATE `activo` SET `modelo_id`=@m_sm_grab_gen WHERE `codigo_barras` IN ('04370658');

-- restaurar estatus en_uso
UPDATE `activo` SET `status`='en_uso' WHERE `codigo_barras`='04370654' AND `tienda_uso_id`=119;
UPDATE `activo` SET `status`='en_uso' WHERE `codigo_barras` IN ('02753359','02753360','02753361','02753362') AND `tienda_uso_id`=111;
UPDATE `activo` SET `status`='en_uso' WHERE `codigo_barras` IN ('02753419','02753420','02753421','02753422','02753474','03417684','03417685','03417686','03417687','03417688','04154161','04154162','04154218','04154219','04154220','04370655','04370656','04370657','04370658');

SET FOREIGN_KEY_CHECKS = 1;
