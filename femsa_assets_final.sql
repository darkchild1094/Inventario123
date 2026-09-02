-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-08-2026 a las 10:08:39
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `femsa_assets`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activo`
--

CREATE TABLE `activo` (
  `id` int(10) UNSIGNED NOT NULL,
  `serie` varchar(100) NOT NULL,
  `placa` varchar(100) DEFAULT NULL,
  `modelo_id` int(10) UNSIGNED NOT NULL,
  `status` enum('en_bodega','en_uso','baja','garantia','asignado') NOT NULL DEFAULT 'en_bodega',
  `procedencia_tienda_id` int(10) UNSIGNED DEFAULT NULL,
  `tienda_uso_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha_alta` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `stock_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `area`
--

CREATE TABLE `area` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `area`
--

INSERT INTO `area` (`id`, `nombre`) VALUES
(6, 'PUNTO DE VENTA'),
(10, 'TELECOMUNICACIONES'),
(11, 'OFICINAS'),
(12, 'CCTV'),
(13, 'MOVILIDAD');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `area_modelo`
--

CREATE TABLE `area_modelo` (
  `id` int(10) UNSIGNED NOT NULL,
  `area_id` int(10) UNSIGNED NOT NULL,
  `modelo_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `area_modelo`
--

INSERT INTO `area_modelo` (`id`, `area_id`, `modelo_id`) VALUES
(1, 6, 1),
(2, 6, 2),
(3, 6, 3),
(4, 6, 4),
(5, 6, 5),
(6, 6, 6),
(7, 6, 7),
(8, 6, 8),
(9, 6, 9),
(10, 10, 10),
(11, 10, 11);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bodega`
--

CREATE TABLE `bodega` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bodega`
--

INSERT INTO `bodega` (`id`, `nombre`, `usuario_id`) VALUES
(1, 'Bodega OXXO Valles', 2),
(2, 'Bodega BARA Valles', 2),
(3, 'Bodega OXXO Tampico', 2);

--
-- Disparadores `bodega`
--
DELIMITER $$
CREATE TRIGGER `trg_crear_stock_bodega` AFTER INSERT ON `bodega` FOR EACH ROW BEGIN
    INSERT INTO `stock` (`tipo`, `usuario_id`, `bodega_id`, `plaza_id`)
    VALUES ('bodega', NULL, NEW.id, NULL);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bodega_acceso_plaza`
--

CREATE TABLE `bodega_acceso_plaza` (
  `id` int(10) UNSIGNED NOT NULL,
  `bodega_id` int(10) UNSIGNED NOT NULL,
  `plaza_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bodega_acceso_plaza`
--

INSERT INTO `bodega_acceso_plaza` (`id`, `bodega_id`, `plaza_id`) VALUES
(1, 1, 1),
(3, 2, 5),
(4, 3, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dispositivo`
--

CREATE TABLE `dispositivo` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `dispositivo`
--

INSERT INTO `dispositivo` (`id`, `nombre`) VALUES
(1, 'IMPRESORA'),
(2, 'ESCANER DE MESA'),
(3, 'CPU'),
(4, 'MONITOR'),
(5, 'ESCANER ID'),
(6, 'ROUTER'),
(7, 'SWITCH'),
(8, 'ACCESS POINT'),
(9, 'TABLET'),
(10, 'HANDHELD'),
(11, 'IMPRESORA PORTATIL'),
(12, 'GRABADOR'),
(13, 'CAMARA'),
(14, 'DECODER'),
(15, 'MONITOR CCTV');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modelo`
--

CREATE TABLE `modelo` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `dispositivo_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `modelo`
--

INSERT INTO `modelo` (`id`, `nombre`, `dispositivo_id`) VALUES
(1, 'EPSON TM-T88V', 1),
(2, 'EPSON TM-T88VI', 1),
(3, 'EPSON TM-T88VII', 1),
(4, 'Datalogic Magellan 3300HSi', 2),
(5, 'Datalogic Magellan 3350HSi', 2),
(6, 'NEC N8910-078SABB0A', 3),
(7, 'HP Engage One Pro AiO i3-10300E8GB/256PC', 3),
(8, 'HP Engage One 10t NS-T (M76394-001)', 4),
(9, 'NEC Customer LCD N8910-07SP0BB', 4),
(10, 'Cisco Meraki MX67', 6),
(11, 'Cisco Meraki MS120-24P', 7),
(12, 'TC530E', 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `negocio`
--

CREATE TABLE `negocio` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `negocio`
--

INSERT INTO `negocio` (`id`, `nombre`) VALUES
(1, 'OXXO'),
(2, 'BARA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plaza`
--

CREATE TABLE `plaza` (
  `id` int(10) UNSIGNED NOT NULL,
  `cr_plaza` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `region_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `plaza`
--

INSERT INTO `plaza` (`id`, `cr_plaza`, `nombre`, `region_id`) VALUES
(1, '32YXH', 'Ciudad Valles', 1),
(2, '32HJR', 'Ciudad Victoria', 1),
(3, '32WPF', 'Matamoros', 1),
(4, '32RNA', 'Tampico', 1),
(5, 'L3ONX', 'León', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `region`
--

CREATE TABLE `region` (
  `id` int(10) UNSIGNED NOT NULL,
  `cr_region` varchar(5) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `negocio_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `region`
--

INSERT INTO `region` (`id`, `cr_region`, `nombre`, `negocio_id`) VALUES
(1, '10UMI', 'Tamaulipas', 1),
(2, 'L3ON1', 'León', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock`
--

CREATE TABLE `stock` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('usuario','bodega') NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `bodega_id` int(10) UNSIGNED DEFAULT NULL,
  `plaza_id` int(10) UNSIGNED DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `stock`
--

INSERT INTO `stock` (`id`, `tipo`, `usuario_id`, `bodega_id`, `plaza_id`) VALUES
(1, 'bodega', NULL, 1, NULL),
(2, 'usuario', 1, NULL, 1),
(3, 'usuario', 3, NULL, 1),
(4, 'usuario', 4, NULL, 1),
(5, 'bodega', NULL, 2, NULL),
(7, 'bodega', NULL, 3, NULL),
(8, 'usuario', 1, NULL, 5),
(9, 'usuario', 2, NULL, 1),
(10, 'usuario', 2, NULL, 5),
(11, 'usuario', 3, NULL, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tienda`
--

CREATE TABLE `tienda` (
  `id` int(10) UNSIGNED NOT NULL,
  `cr_tienda` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `coordenadas` varchar(100) DEFAULT NULL,
  `plaza_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tienda`
--

INSERT INTO `tienda` (`id`, `cr_tienda`, `nombre`, `coordenadas`, `plaza_id`) VALUES
(1, '500FM', 'Paso del Humo', '22.2056497949317, -97.8439231806242', 1),
(2, '50D30', 'El Sion Tam', '22.19330247, -97.8346104', 1),
(3, '50DRN', 'Los Cocos Tam', '22.20804661, -97.8611097', 1),
(4, '50DT8', 'El Rio Tam', '22.20742644, -97.8395522', 1),
(5, '50GZU', 'California Tam', '22.20248271, -97.84243814', 1),
(6, '50HXA', 'Anahuac Tam', '22.20551652, -97.85349206', 1),
(7, '50IM5', 'Carretera Nacional Maf', '22.1982263231056, -97.8382790173805', 1),
(8, '50JCL', 'La Loma Tam', '22.1976155, -97.855557', 1),
(9, '50O56', 'Independencia Tam', '22.20398, -97.83711', 1),
(10, '501IO', 'Matinchild Tam', '21.6630747035171, -97.8517972034664', 1),
(11, '5022Z', 'El Mango Tam', '22.2237704, -97.820142', 1),
(12, '506WR', 'Gas Cuauhtemoc Tam', '22.19294469, -97.82523184', 1),
(13, '5084Z', 'Veteranos Tam', '22.1786047606645, -97.8361448766853', 1),
(14, '50C7P', 'Horconcitos Tam', '21.8498591925569, -97.7493161273065', 1),
(15, '50CXO', 'Ozuluama Centro Tam', '21.65991762, -97.85018057', 1),
(16, '50EG5', 'Villa Tampico Tam', '22.1098653, -97.803602', 1),
(17, '50K2P', 'Abasolo Tam', '22.1879, -97.8342', 1),
(18, '50K5T', 'Mata Redonda Tam', '22.2288193079756, -97.8277805674854', 1),
(19, '50KOJ', 'Edarsi Tam', '21.7981573, -97.77270558', 1),
(20, '50LDJ', 'Pueblo Viejo Tam', '22.18459026, -97.8362164', 1),
(21, '50OPF', 'Tampico Alto II Tam', '22.111403, -97.801137', 1),
(22, '50OZL', 'Ozuluama Ii Tam', '21.6750403, -97.85983944', 1),
(23, '50OZU', 'Ozuluama Tam', '21.6845983, -97.8549675', 1),
(24, '50SNN', 'Congreg Hidalgo Tam', '22.23673974, -97.83000327', 1),
(25, '5080M', 'Cruz Tam', '22.04970903, -98.17859252', 1),
(26, '50AGD', 'Burocrata Tam', '22.05786188, -98.18696648', 1),
(27, '50DGC', 'Flores Magon Tam', '22.05352561, -98.18233043', 1),
(28, '50IPO', 'Tempoal Centro Tam', '21.5209546, -98.395215', 1),
(29, '50JWA', 'Santos Tam', '21.52213752, -98.39140508', 1),
(30, '50TI2', 'Llave Tam', '21.5197, -98.3914', 1),
(31, '50TPY', 'Tempoal Tam', '21.52313344, -98.37982785', 1),
(32, '50WM8', 'La Gloria Tam', '21.5161449, -98.382496', 1),
(33, '50X0E', 'Gas Tempoal Tam', '21.4930342801403, -98.3569611816903', 1),
(34, '501W1', 'Gas Jaibo Tam', '22.04608985, -98.19116864', 1),
(35, '50BDR', 'Olmecas Tam', '22.04145948, -98.18240951', 1),
(36, '50JBU', 'Panuco Dif Tam', '22.05001769, -98.18990438', 1),
(37, '50MT3', '21 De Abril Tam', '22.0379611, -98.1868', 1),
(38, '50OS4', '05 De Febrero Tam', '22.0532770699477, -98.1888534489816', 1),
(39, '50PUC', 'Panuco II Tam', '22.04564663, -98.192034', 1),
(40, '50QFG', 'Panuco Iv Tam', '22.04958059, -98.18274066', 1),
(41, '5004C', 'Aquiles Serdan Tam', '21.7139845520945, -98.5859814043524', 1),
(42, '50AGP', 'San Vicente Tam', '21.7181165, -98.58860666', 1),
(43, '50D37', 'Tanquian Escobedo Maf', '21.60911861, -98.66119233', 1),
(44, '50GUB', 'Tanquian Tam', '21.59880003, -98.66344226', 1),
(45, '500BP', 'Ebano Carretera Tam', '22.2176034, -98.377298', 1),
(46, '50264', 'Corregidora Tam', '22.0050418931199, -98.7690208978051', 1),
(47, '506A4', 'Miguel Hidalgo  Tam', '22.2121752, -98.387967', 1),
(48, '506ZZ', 'Yucatan Tam', '22.2128112, -98.396414', 1),
(49, '50ARP', 'Larraga Tam', '22.22316521, -98.37184336', 1),
(50, '50EWK', 'Ebano Tam', '22.21431, -98.37463', 1),
(51, '50F4Z', 'La Estacion Tmp', '22.22787505, -98.36928529', 1),
(52, '50IMG', 'Tamuin Centro Tam', '22.00551064, -98.77467749', 1),
(53, '50M3P', 'Pujal Coy Tam', '22.1520340376898, -98.5058330080579', 1),
(54, '50O3Y', 'Huasteca Tmp', '22.00835654, -98.78199014', 1),
(55, '50QFS', 'Tres Filos Tam', '21.97039679, -98.81884295', 1),
(56, '50ST3', 'Boulevard Tamuin Tam', '22.0112033503008, -98.7885989959068', 1),
(57, '50TUI', 'Tamuin Tam', '22.07750387, -98.65254031', 1),
(58, '50VOG', 'Ebano Centro Tam', '22.21031066, -98.37876879', 1),
(59, '50XDY', 'Iturbide Tam', '22.00256187, -98.77986186', 1),
(60, '500HQ', 'Santa Maria Tam', '22.0055, -99.030859', 1),
(61, '502AS', 'Soto Y Gama Tam', '22.0102, -99.015958', 1),
(62, '505GO', 'Tambaca Tam', '21.9614, -99.302206', 1),
(63, '507QF', 'Mexico Tam', '22.00070944, -99.0189598', 1),
(64, '50916', 'La Curva Tam', '21.9989687053626, -99.024361671655', 1),
(65, '50993', 'Emiliano Zapata Tam', '22.0110927394995, -99.0261899031261', 1),
(66, '509YH', 'Agua Buena Tam', '21.9572721, -99.392411', 1),
(67, '509YS', 'Carretera Tamasopo Tam', '21.9272, -99.3915', 1),
(68, '50APO', 'Praderas Del Rio Tam', '22.01109839, -99.05374668', 1),
(69, '50BMT', 'Santa Lucia Tam', '22.0089943, -99.03775811', 1),
(70, '50GEC', 'El Consuelo Tam', '22.02704264, -99.02600798', 1),
(71, '50GSH', 'Valles Tam', '21.99242067, -99.03023684', 1),
(72, '50JZ9', 'Tamasopo Tam', '21.92216972, -99.39249495', 1),
(73, '50K9Q', 'De Las Rosas Tam', '22.00607245, -99.01287187', 1),
(74, '50NFH', 'La Pimienta Tam', '22.00203551, -98.99806149', 1),
(75, '50OR1', 'Avance  Tam', '21.995847, -99.0192', 1),
(76, '50SFL', 'Doracely Tam', '22.005501, -99.01957636', 1),
(77, '50UT9', 'Vista Hermosa Tam', '22.0195523, -99.027368', 1),
(78, '50WXT', 'Aire Tam', '21.99343957, -99.02366484', 1),
(79, '50380', 'El Cafetal Maf', '21.9232216243142, -99.3991512759197', 1),
(80, '50ANI', 'Panuco I Tam', '22.05746179, -98.17933943', 1),
(81, '50F31', 'Villa Cacalilao Tam', '22.1522554, -98.175312', 1),
(82, '50O58', 'Caseta Panuco Tam', '22.1504734228822, -98.1467688620679', 1),
(83, '50TDF', 'Xalapa Tam', '22.0660101, -98.183707', 1),
(84, '50TT2', 'Mercado Panuco Tam', '22.05928982, -98.18089367', 1),
(85, '50UCT', 'Panuco Iii Tam', '22.0636679, -98.16969902', 1),
(86, '50ZMJ', 'Malecon Tam', '22.05671199, -98.17753613', 1),
(87, '50EFS', 'Tampico Valles Tam', '22.22415, -97.90795', 1),
(88, '50H3I', 'Tamos Tam', '22.21951336, -98.00026322', 1),
(89, '50L80', 'Calentadores Tam', '22.1729300961603, -98.0887500493186', 1),
(90, '50OMU', 'Moralillo Ii Tam', '22.22516548, -97.90182928', 1),
(91, '50OOM', 'Moralillo Tam', '22.22423191, -97.90320106', 1),
(92, '50SAQ', 'Sabalo Tam', '22.22273774, -97.91663713', 1),
(93, '5006G', 'Las Puentes Tam', '21.7733752822543, -98.419575399524', 1),
(94, '502AO', 'Ejercito Nacional Tmp', '21.7684, -98.4498', 1),
(95, '502J3', 'Miguel Aleman Tam', '21.7618727, -98.452529', 1),
(96, '508ZZ', 'Ribera Tam', '21.77280283, -98.45566984', 1),
(97, '50JWC', 'El Higo Tam', '21.76839695, -98.45297066', 1),
(98, '50NFI', 'Gas Higo Tam', '21.77704381, -98.44624706', 1),
(99, '50M20', 'Televalles Tam', '21.976704496928, -99.0036979068082', 1),
(100, '500EP', 'Rotarios Tam', '21.9808362, -99.013257', 1),
(101, '500H3', '16 De Septiembre  Tam', '21.9889434, -99.013683', 1),
(102, '500S1', 'Eco Grande Tam', '21.98277659, -99.01504889', 1),
(103, '505WE', 'Tercer Mundo Tam', '22.0003, -99.0132', 1),
(104, '5073X', 'Mendez Tam', '21.98441044, -99.00148717', 1),
(105, '508K0', 'Comonfort Tam', '21.9904, -99.0176', 1),
(106, '50AGE', 'Salazar Tam', '21.99298217, -99.01383386', 1),
(107, '50BDQ', 'Lerdo De Tejada Tam', '21.98577407, -99.00645597', 1),
(108, '50C7Y', 'Unidad Norte Maf', '21.6110573066054, -98.663561109249', 1),
(109, '50E1G', 'Ferrocarril', '21.98875112, -98.99618882', 1),
(110, '50EFO', 'Pedregal Tam', '22.00985566, -99.00153644', 1),
(111, '50IMH', 'Frontera Tam', '21.99306977, -99.00193581', 1),
(112, '50IMJ', 'Ecocentralita Tam', '21.98433141, -99.01607058', 1),
(113, '50J2L', 'Fray Andres Tam', '21.97676324, -99.00594281', 1),
(114, '50K58', 'El Carmen Tam', '21.99603712, -98.99443878', 1),
(115, '50L5A', 'Aurora Tam', '21.9801702, -98.997978', 1),
(116, '50LCB', 'General Anaya Tam', '21.97544548, -99.01018109', 1),
(117, '50QV1', 'Villa Huasteca Tam', '22.0172, -98.9972', 1),
(118, '50R68', 'Acuario Tam', '21.9861561, -99.01323', 1),
(119, '50S9S', 'Motolinia', '21.99275221, -99.00949415', 1),
(120, '50SFT', 'Linares Tam', '22.00053473, -99.0042936', 1),
(121, '50T0H', 'Zaragoza Tam', '21.99026, -99.005046', 1),
(122, '50TBI', 'Tampaon Tam', '21.99758697, -99.01056429', 1),
(123, '50TEJ', 'Glorieta Tam', '21.9821213, -99.005528', 1),
(124, '50VBC', 'Valles Centro Tam', '21.98644632, -99.01834277', 1),
(125, '50YNI', 'Apolo Tam', '22.00945526, -99.00909943', 1),
(126, '50DHN', 'Las Aguilas Tam', '21.97675617, -98.97176097', 1),
(127, '50LFI', 'San Felipe Tam', '21.97688343, -98.95650666', 1),
(128, '50Z98', 'Del Campo Tam', '21.9816283, -98.978283', 1),
(129, '50U2F', 'Galeana Valles Maf', '21.9907751387887, -99.0123331212716', 1),
(130, '500Q5', 'Servando Tam', '22.7449, -98.9723', 1),
(131, '500X5', 'Ciudad Del Maiz Tam', '22.4038794, -99.602867', 1),
(132, '5012J', 'Xico Mercado Tam', '22.9978879, -98.938377', 1),
(133, '502E1', 'Del Pino Maf', '22.7345778419057, -98.9642885655776', 1),
(134, '503BF', 'Loma Alta Tam', '22.8826157, -99.02723', 1),
(135, '5081M', 'El Abra Maf', '22.6282696427617, -99.0229962110598', 1),
(136, '50947', 'Blvd Colosio Tam', '22.755984401927, -98.9691049944622', 1),
(137, '5095M', 'Guillermo Prieto', '22.7402652697216, -98.9827835422666', 1),
(138, '509DV', 'Luis Echeverria Tam', '22.7383714172963, -98.9635837311798', 1),
(139, '50A7Z', 'Ganadera Tam', '22.8456001, -99.33235', 1),
(140, '50EEN', 'Rio Mante Tam', '22.73238828, -98.9712167', 1),
(141, '50GFU', 'Escobedo Tam', '22.74228342, -98.96831406', 1),
(142, '50GG2', 'Nuevo Morelos Maf', '22.533850291752, -99.2212434132896', 1),
(143, '50GMH', 'Mante Tam', '22.70880934, -98.9923335', 1),
(144, '50HN6', 'Ciudad Ocampo Tam', '22.84391305, -99.33654214', 1),
(145, '50NML', 'Limon Tam', '22.82185022, -99.0103359', 1),
(146, '50NTK', 'Autopark Mante Tam', '22.74740751, -98.99212419', 1),
(147, '50NWL', 'Bernal Tam', '22.75785497, -98.96182098', 1),
(148, '50OR5', 'El Martillo Tam', '22.74866485, -98.96174382', 1),
(149, '50PGW', 'Paniagua Tam', '22.74986268, -98.97134784', 1),
(150, '50Q0M', 'Condueños Maf', '22.7469111493116, -98.9765904591954', 1),
(151, '50Q7H', 'Naranjo Centro Tam', '22.5224433, -99.3259', 1),
(152, '50RD4', 'Aviacion Mante Tam', '22.75340365, -99.00533588', 1),
(153, '50RG8', 'Carretera Del Maiz Tam', '22.4006041, -99.607625', 1),
(154, '50SM7', 'Carretera Mante Tam', '22.72604806, -98.96226266', 1),
(155, '50U5K', 'Mante Platino Maf', '22.8034638719584, -99.0134389471306', 1),
(156, '50U9L', 'Mante Centro Tam', '22.7377535, -98.972629', 1),
(157, '50UR4', 'Nicolas Moreno Tam', '22.7336489, -98.981613', 1),
(158, '50V1D', 'Chicoasen Maf', '22.7326240013198, -98.9598331038685', 1),
(159, '50V37', 'Antiguo Morelos Tam', '22.5543, -99.078566', 1),
(160, '50VMJ', 'Olivo Tam', '22.74535502, -98.98371755', 1),
(161, '50W7N', 'La Esperanza Tam', '22.5236675955026, -99.3332986204267', 1),
(162, '50XIJ', 'Xico Tam', '22.98898163, -98.94677598', 1),
(163, '501E4', 'Estacion Cardenas Maf', '22.7455713, -98.98653957', 1),
(164, '502RQ', 'Loma Bonita', '21.4378530441205, -98.8682726028592', 1),
(165, '503HS', 'Aquismon Tam', '21.6233499, -98.986998', 1),
(166, '5068M', 'Centro Tampacan', '21.402136, -98.72858', 1),
(167, '506F8', 'Deportiva Valles Tam', '21.9710406, -98.993996', 1),
(168, '506J3', 'San Miguel Tam', '21.2541425, -98.786914', 1),
(169, '5077X', 'Tetlama Tam', '21.26294915, -98.79278028', 1),
(170, '5083G', 'Tampacan Tam', '21.3289, -98.8199', 1),
(171, '5089E', 'Chapulhuacanito Tam', '21.20836611, -98.67126249', 1),
(172, '50AGG', 'Xolol Tam', '21.59344013, -98.99285636', 1),
(173, '50ARH', 'Tencaxapa Tam', '21.24808681, -98.78069248', 1),
(174, '50BPR', 'Lomasdesantiago Tam', '21.9586, -98.9939', 1),
(175, '50CLC', 'Entronquexilitla Tam', '21.4449578, -98.9280247', 1),
(176, '50FBM', 'Hospital Tam', '21.94887267, -98.99435847', 1),
(177, '50FCQ', 'Matlapa Tam', '21.33536592, -98.82695953', 1),
(178, '50GQU', 'Tampamolon Tam', '21.55978095, -98.81665747', 1),
(179, '50HXF', 'Providencia Tam', '21.93254, -98.97274', 1),
(180, '50IMI', 'Auto Park Valles Tam', '21.96510518, -98.99902417', 1),
(181, '50IUD', 'Villavicencio Tam', '21.96932633, -98.99960868', 1),
(182, '50K35', 'Centro Aquismon Maf', '21.6217906559226, -99.020522619969', 1),
(183, '50L8J', 'La Purisima Tam', '21.4409, -98.8776', 1),
(184, '50L9W', 'Plaza Tancanhuitz Tam', '21.5976, -98.9671', 1),
(185, '50MDN', 'Zacatipan Tam', '21.2481979, -98.774982', 1),
(186, '50O9V', 'Huehuetlan Tam', '21.4814, -98.9667', 1),
(187, '50PJW', 'Pujal Tam', '21.85432697, -98.94248694', 1),
(188, '50Q27', 'Buenos Aires Tam', '21.25210819, -98.74948533', 1),
(189, '50RF7', 'San Martin Tam', '21.37052535, -98.65813821', 1),
(190, '50TES', 'Xilitla Tam', '21.3849662, -98.989808', 1),
(191, '50THJ', 'Centro Tamanzuchale Tam', '21.2605796, -98.789369', 1),
(192, '50TTN', 'Crucero Tam', '21.2593876, -98.788015', 1),
(193, '50U1U', 'Coxcatlan Tam', '21.54088005, -98.90580999', 1),
(194, '50W6J', 'Ahuacatlan Tam', '21.321117, -99.05204', 1),
(195, '50XXC', 'Axtla Tam', '21.436076, -98.8746615', 1),
(196, '509Q2', 'Azalea Maf', '21.3875846083563, -98.9853256613262', 1),
(197, '50PZ5', 'Huichihuayan Maf', '21.4836677459535, -98.9709729741483', 1),
(198, '507LE', 'Gas Tamazunchale Maf', '21.2662873663467, -98.7756414997744', 1),
(199, '50Y75', 'Palmira Maf', '21.67761547, -98.97131874', 1),
(200, '50EW2', 'Comoca Maf', '21.42484482, -98.89015767', 1),
(201, '506LS', 'Rascon 2 Maf', '21.96662433, -99.25589546', 1),
(202, '506RG', 'Llera Centro Tam', '23.3183449, -99.0234087', 2),
(203, '50AU4', 'Villa Llera Maf', '23.3080895810379, -99.0212745146032', 2),
(204, '50LFF', 'Llera Tam', '23.1164182308368, -98.7507025592985', 2),
(205, '50Y9K', 'Jose Silva Tam', '23.313093, -99.0217376', 2),
(206, '50SO2', '14 Carrera Maf', '23.7381528258915, -99.1492969308464', 2),
(207, '50UXE', 'Estadio Maf', '23.73948689, -99.15368164', 2),
(208, '50VDI', '16 Veracruz Maf', '23.7469388, -99.15027678', 2),
(209, '50CRW', '8 Carrera Maf', '23.7371, -99.1436', 2),
(210, '50JZU', '9 Y Berriozabal Maf', '23.74014314, -99.1447991', 2),
(211, '502CG', '31 Berriozabal Maf', '23.7422, -99.1683', 2),
(212, '50BAA', 'Refineria Maf', '23.74344475, -99.16451852', 2),
(213, '50D9A', 'Keppler Maf', '23.7505900651358, -99.1705524352368', 2),
(214, '50FEY', 'Nacozary Maf', '23.7379479, -99.16299869', 2),
(215, '50JZV', 'Oaxaca Maf', '23.75503172, -99.1519488', 2),
(216, '50OUK', 'Adelitas Maf', '23.74707325, -99.15944537', 2),
(217, '50OUW', 'La Escondida Maf', '23.74088878, -99.15997357', 2),
(218, '50SNQ', 'San Luisito Maf', '23.75944424, -99.15228823', 2),
(219, '50YJH', 'Conrado Maf', '23.74482549, -99.15274707', 2),
(220, '50YM7', 'Almendros Maf', '23.75935103, -99.15848471', 2),
(221, '50YTC', 'Tec Maf', '23.7522734, -99.16491024', 2),
(222, '50VJE', 'Eje Vial Maf', '23.75654, -99.16034', 2),
(223, '50HJB', '15 Berriozabal Maf', '23.74147544, -99.14951477', 2),
(224, '5065F', 'La Estrella Maf', '23.7715, -99.1732', 2),
(225, '506AR', 'El Barretal Maf', '24.085475, -99.124411', 2),
(226, '507SX', 'Monte Alto Umi', '23.7814, -99.1675', 2),
(227, '50BRK', 'Laborcitas Maf', '23.8214, -99.1225', 2),
(228, '50DPJ', 'Zeferino 2 Maf', '23.75636, -99.16447', 2),
(229, '50DQV', 'La Presita Maf', '23.77498013, -99.16719738', 2),
(230, '50DQZ', 'Zeferino Maf', '23.76272271, -99.16769922', 2),
(231, '50GFO', 'Nacional Maf', '23.79201048, -99.15715229', 2),
(232, '50GZK', 'Guemez Maf', '23.79003884, -99.15064703', 2),
(233, '50HVX', 'Las Americas Maf', '23.76608989, -99.16176884', 2),
(234, '50K6D', 'Los Troncones Maf', '23.798963, -99.178051', 2),
(235, '50MY5', 'Tomaseno Maf', '24.2605, -99.4366', 2),
(236, '50NUD', 'Naciones Unidas II Maf', '23.77324045, -99.16108266', 2),
(237, '50SDQ', 'Naciones Unidas Maf', '23.77301478, -99.13911552', 2),
(238, '50TMK', 'Oralia Guerra Maf', '23.77333709, -99.15301673', 2),
(239, '50VE7', 'Subida Alta Maf', '23.912202, -99.113523', 2),
(240, '50W7M', 'Colinas Del Valle Maf', '23.7673, -99.1706', 2),
(241, '50WU2', 'Villagran Maf', '24.4689359696567, -99.4931637533588', 2),
(242, '50OSD', '16 Sierra de Casas Maf', '23.7658021, -99.14752563', 2),
(243, '50XIQ', 'Naciones Unidas III Maf', '23.77388769, -99.14388476', 2),
(244, '50WM4', 'La Salle Victoria Maf', '23.77924831, -99.14614789', 2),
(245, '508QJ', 'La Libertad Maf', NULL, 2),
(246, '5029T', 'Gas Altamira Tam', '22.4648285, -97.9034906', 2),
(247, '503OF', 'Tres Marias Maf', '22.4823692868653, -98.015535970364', 2),
(248, '506L1', 'Santa Fe Tam', '22.8220313, -98.4281815', 2),
(249, '5074G', 'De Los Rios Tam', '22.4530549, -97.8998904', 2),
(250, '5081E', 'Esteros Tam', '22.52174, -98.1245533', 2),
(251, '509KX', 'Gas El 40 Tam', '22.4978707, -98.0602239', 2),
(252, '50CGZ', 'Gonzalez Centro Tam', '22.8284285, -98.4264394', 2),
(253, '50ESH', 'Est. Cuauhtemoc Tam', '22.5528278, -98.1496564', 2),
(254, '50KHB', 'Manuel Centro Tam', '22.7280785, -98.3216801', 2),
(255, '50LFD', 'Lib. Manuel Tam', '22.7437762, -98.2984855', 2),
(256, '50LO8', 'Agropecuarios Maf', '22.4497569894777, -97.9768308453131', 2),
(257, '50M73', 'Gas Manuel Tmp', '22.7613613902605, -98.3154910655951', 2),
(258, '50NF7', 'Seis Tam', '22.7294424, -98.3121644', 2),
(259, '50PV1', 'Jose Maria Tam', '22.732478960653275, -98.3243281846578', 2),
(260, '50QDV', 'Cuauhtemoc Centro Tam', '22.5425223, -98.150451', 2),
(261, '50QLK', 'Puerto Altamira Tam', '22.4336497, -97.8914126', 2),
(262, '50RA5', 'Estacion Colonias Tam', '22.44125782, -98.01668039', 2),
(263, '50TS7', 'Puente Roto Maf', '22.4591588944852, -97.9816274730827', 2),
(264, '50WIY', 'Villa Cuauhtemoc Tam', '22.5559397, -98.1511849', 2),
(265, '50ZBK', 'Gonzalez Tam', '22.8235155, -98.4377925', 2),
(266, '505Q5', 'Rio Tamesi Maf', '22.66056701, -98.55134126', 2),
(267, '50H2A', 'Villa Manuel Maf', '22.7285973276126, -98.3170771619383', 2),
(268, '505EV', 'Gonzalitos Maf', '22.83226138, -98.4347266', 2),
(269, '50FE7', 'Santa Amalia Tam', '22.4320309, -97.9668336', 2),
(270, '501XQ', 'Vialidad Pd Tam', '22.3971, -97.899041', 2),
(271, '5096D', 'Electricistas Tam', '22.4195635, -97.9345376', 2),
(272, '50GKB', 'Ornato Tam', '22.4159374, -97.9229252', 2),
(273, '50181', 'Camaleon Tam', '22.404228, -97.929408', 2),
(274, '501HK', 'Santa Elena', '22.3252571154578, -97.8492715448179', 2),
(275, '502R0', 'C-5 Maf', '22.3904766295646, -97.907875638172', 2),
(276, '506IK', 'Valle Dorado Tam', '22.3244244791886, -97.8704158034888', 2),
(277, '509UI', 'Gas Monte Alto Tam', '22.3813595, -97.914404', 2),
(278, '50ABO', 'Arboledas Tam', '22.3875104, -97.9123526', 2),
(279, '50AV6', 'Valle Esmeralda Tam', '22.3996579, -97.9135001', 2),
(280, '50BAM', 'Nuevo Madero Tam', '22.347583, -97.8548613', 2),
(281, '50BXH', 'Deportivo Sur Tam', '22.40338792, -97.91060142', 2),
(282, '50CZK', 'Vidal Tam', '22.3287594, -97.874419', 2),
(283, '50D8L', 'Avenida Cuarta Tam', '22.3417435, -97.868931', 2),
(284, '50DCO', 'Crit Tam', '22.40258612, -97.9271686', 2),
(285, '50DVR', 'Divisoria Tam', '22.3236853, -97.876026', 2),
(286, '50HVY', 'Durazno Tam', '22.40936637, -97.91924206', 2),
(287, '50NVK', 'Pedrera Tam', '22.3885761, -97.8847904', 2),
(288, '50OP3', 'Retama Tam', '22.381951, -97.9112913', 2),
(289, '50SVA', 'Los Olivos Tam', '22.39634151, -97.90315202', 2),
(290, '50VIA', 'Villas Tam', '22.3959173, -97.8863698', 2),
(291, '50WXF', 'Bateria 7 Tam', '22.4160926, -97.9315814', 2),
(292, '50YUO', 'Ficus Tam', '22.3928603, -97.9099713', 2),
(293, '50YUV', 'Arrecifes Tam', '22.3954222, -97.8877749', 2),
(294, '50YUX', 'Sauce II Tam', '22.39526542, -97.90160432', 2),
(295, '50973', 'Sector 3 Tam', '22.408907792203, -97.9381230296367', 2),
(296, '50H6R', 'Madrid Maf', '22.330146, -97.862873', 2),
(297, '5008G', 'Palmillas Maf', '23.3083459, -99.55684289', 2),
(298, '501EA', 'Baez Maf', '23.7052814372409, -99.1190102108256', 2),
(299, '502K2', 'Jaumave Plaza Maf', '23.4053852, -99.3750376', 2),
(300, '5060E', 'Pedro Sosa 1 Maf', '23.7166691859089, -99.1327118025699', 2),
(301, '507EU', 'Rio Blanco Maf', '23.72494084, -99.1351195', 2),
(302, '508HH', 'Clinica Imss Maf', '22.9917444, -99.7181107', 2),
(303, '50GZW', '8 Y Garza Maf', '23.72740954, -99.14426387', 2),
(304, '50JQ5', 'Lomas De Guadalupe Maf', '23.71266592, -99.12857817', 2),
(305, '50JUM', 'Jaumave Maf', '23.4083813, -99.3857221', 2),
(306, '50JYD', 'Santuario 2 Maf', '23.7181, -99.1453', 2),
(307, '50JZW', 'San Luis Maf', '23.66333322, -99.10759128', 2),
(308, '50P0X', 'Arroyo Loco Maf', '22.9994383, -99.7120471', 2),
(309, '50PD1', 'Jaumave 2 Maf', '23.3951258, -99.4141151', 2),
(310, '50SUO', 'Santuario Maf', '23.71737, -99.14441', 2),
(311, '50W07', 'Tula Centro Maf', '22.9967716, -99.711808', 2),
(312, '50Y4M', 'Jaumave Centro Maf', '23.4059013, -99.3832764', 2),
(313, '50YLO', 'La Loma Maf', '23.71936642, -99.12881111', 2),
(314, '50DY4', '4 Boulevard Maf', '23.7291731837917, -99.1404330593458', 2),
(315, '50FMY', '8 Boulevard Maf', '23.72986093, -99.14408924', 2),
(316, '50SA8', '12 de Septiembre Maf', '23.711173, -99.142641', 2),
(317, '50401', 'Revolucion Verde Maf', '23.7487025, -99.13040424', 2),
(318, '509AD', 'Del Norte Maf', '23.7498650032712, -99.1390893839451', 2),
(319, '501XB', 'Martires Maf', '23.7888, -99.1334', 2),
(320, '503CP', 'Sulaiman Maf', '23.747196, -99.126357', 2),
(321, '505CV', 'Hombres Ilustres 2 Maf', '23.75127824, -99.12928462', 2),
(322, '507Q4', 'Teosuchil Maf', '23.755607382744383, -99.1283171428355', 2),
(323, '50BSX', 'Abasolo Maf', '23.73500645, -99.13003166', 2),
(324, '50CSB', 'Fidel Velazquez II Maf', '23.73116742, -99.12989762', 2),
(325, '50DQC', '14 Boulevard Maf', '23.75271338, -99.1471809', 2),
(326, '50FDV', 'Fidel Velazquez Maf', '23.74141765, -99.13339424', 2),
(327, '50FYA', 'Arguelles Maf', '23.73211688, -99.14243989', 2),
(328, '50HII', 'Hombres Ilustres Maf', '23.75238347, -99.13622959', 2),
(329, '50HVZ', '5 Ceros Maf', '23.73571945, -99.13260407', 2),
(330, '50RRA', '18 De Julio Maf', '23.73793824, -99.12986479', 2),
(331, '50SAC', 'Salubridad Maf', '23.73543615, -99.15172936', 2),
(332, '50TGU', '13 Yucatan Maf', '23.75704805, -99.14627403', 2),
(333, '50TWQ', 'Tenochtitlan Maf', '23.75355781, -99.12746087', 2),
(334, '50UXB', 'Berriozabal Maf', '23.74010386, -99.13722454', 2),
(335, '50VGY', 'Valle De Aguayo Maf', '23.75296114, -99.14343708', 2),
(336, '50WMI', 'Ocho Michoacan Maf', '23.75912963, -99.14008788', 2),
(337, '50WQN', 'Castaneda Maf', '23.73724894, -99.12355359', 2),
(338, '50XDG', '1 Matamoros Maf', '23.73244601, -99.13779459', 2),
(339, '50XJL', 'Rectoria Maf', '23.7332193436423, -99.1449377125494', 2),
(340, '50YCR', '3 Carrera Maf', '23.73684326, -99.13915573', 2),
(341, '50YHI', '14 Hidalgo Maf', '23.73194596, -99.14968878', 2),
(342, '50YJF', 'Rotario Maf', '23.75074659, -99.12392922', 2),
(343, '50YJI', 'Olivia Maf', '23.74235697, -99.14125988', 2),
(344, '50YJN', 'Hospital General Maf', '23.74908882, -99.13797803', 2),
(345, '50YNX', 'Colon Maf', '23.74670066, -99.14445324', 2),
(346, '5085F', 'Tamaulipas II Tam', '22.3933805548022, -97.9338671017171', 2),
(347, '50ALR', 'Altamira Tam', '22.41453513, -97.9374062', 2),
(348, '50BAX', 'Fco I. Madero Tam', '22.3582522, -97.8830196', 2),
(349, '50CMP', 'Floresta Tam', '22.323662, -97.8875965', 2),
(350, '50DDL', 'Santa Anita Tam', '22.3912605, -97.9463695', 2),
(351, '50E20', 'Capitan Tam', '22.3936427, -97.9413116', 2),
(352, '50EJN', 'Allende Tam', '22.41363608, -97.94086717', 2),
(353, '50IFF', 'Florida Tam', '22.3995165, -97.9438036', 2),
(354, '50IOX', 'El Eden Tam', '22.3957319, -97.9279432', 2),
(355, '50LB3', 'Ejido Miramar Tam', '22.3308851, -97.8668281', 2),
(356, '50M82', 'Osa Mayor Tam', '22.3618817, -97.8825664', 2),
(357, '50MBJ', 'Altamira Mercado Tam', '22.3965613, -97.9358785', 2),
(358, '50NXH', '18 De Marzo Tam', '22.3356332, -97.8662991', 2),
(359, '50O53', 'Tec Monterrey Tam', '22.3815732, -97.9022013', 2),
(360, '50OAT', 'Altamira Centro Tam', '22.3917731, -97.935776', 2),
(361, '50ODK', 'Gaviotas Tam', '22.3915643, -97.930048', 2),
(362, '50RRG', 'Benito Juarez Tam', '22.40560788, -97.94239426', 2),
(363, '50RXV', 'Revolucion Tam', '22.3873778, -97.9450338', 2),
(364, '50TY6', 'Santos Degollado Tam', '22.3264677, -97.8827363', 2),
(365, '50U0F', 'Movil Victoria Maf', '23.7322, -99.1498', 2),
(366, '50V28', 'El Faro', '22.3875552, -97.9500173', 2),
(367, '50VJ7', 'Secundaria 1 Maf', '22.3903272947391, -97.9389727401894', 2),
(368, '50XBK', 'Mina Tam', '22.3941532623068, -97.9373321203026', 2),
(369, '50YNE', 'Argentina Tam', '22.3267327, -97.8622229', 2),
(370, '50ZMH', 'Guerrero II Tam', '22.3332118300919, -97.8811296237424', 2),
(371, '50F4P', 'Fundo Legal Maf', '22.39566157, -97.94073968', 2),
(372, '50V7Z', 'Bahia Tam', '22.3338463, -97.8605004', 2),
(373, '503OG', 'Barquito Tam', '22.342733, -97.8756858', 2),
(374, '50KH9', 'Avenida De La Industria Tam', '22.3475251, -97.8776009', 2),
(375, '502O6', 'Colinas Tam', '22.3682037, -97.8813073', 2),
(376, '503DX', 'Encinos Tam', '22.3533329, -97.8866309', 2),
(377, '504W9', 'Moroncito Tam', '22.9178003824243, -98.0779754926595', 2),
(378, '505MH', 'Lagunas De Miralta Tam', '22.3554675848075, -97.8893215688025', 2),
(379, '507OV', 'Villas Del Sol Tam', '22.3681362867014, -97.8857603452195', 2),
(380, '508SU', 'Canarios Tam', '22.3760534, -97.9141274', 2),
(381, '50956', 'Luis Caballero Tam', '22.93131, -98.07897', 2),
(382, '50APC', 'Lirios Tam', '22.3691866, -97.9061922', 2),
(383, '50BDP', 'Azteca Tam', '22.3636059, -97.9101294', 2),
(384, '50DMW', 'Aldama II Tam', '22.9229804, -98.0809626', 2),
(385, '50KAD', 'Aldama Tam', '22.9210753, -98.0848413', 2),
(386, '50LCI', 'Constitucion Tam', '22.9126553, -98.0755837', 2),
(387, '50MXY', 'Monte Alto Tam', '22.3777662, -97.9101116', 2),
(388, '50PHB', 'Brownsville Tam', '22.9260023665541, -98.074094950001', 2),
(389, '50SIR', 'Satelite Tam', '22.3619408, -97.8876417', 2),
(390, '50TG3', 'Rio Blanco Tam', '22.3629130050399, -97.9154931843707', 2),
(391, '50TML', 'Miraltas Tam', '22.3591184, -97.8913691', 2),
(392, '50UUM', 'Aldama Centro Tam', '22.9199130656537, -98.0734627921004', 2),
(393, '50W74', 'Carmin Tam', '22.3646636, -97.9046563', 2),
(394, '50YOB', 'Arecas Tam', '22.3794245, -97.9065431', 2),
(395, '50CQ8', 'Club de Leones Maf', '22.9170097263162, -98.0705718435118', 2),
(396, '50EUP', 'La Paz II Maf', '23.74767106, -99.1202239', 2),
(397, '504AD', 'Marina Centro', '23.76633223, -98.20602278', 2),
(398, '505CO', 'Ejido la Pesca Maf', '23.7875994917364, -97.77556564412', 2),
(399, '508DH', 'Vicente Guerrero Maf', '23.73743518, -99.11370526', 2),
(400, '508FV', 'Coplamar Maf', '23.775, -98.205', 2),
(401, '5097Z', 'Marte Maf', '23.7336594, -99.09170697', 2),
(402, '509YX', 'Aptiv Maf', '23.7256, -99.0831', 2),
(403, '50C2U', 'Valle del Sol', '23.74323789831659, -99.11301767407356', 2),
(404, '50D60', 'Todos Por Tamaulipas Maf', '23.73693749, -99.09760174', 2),
(405, '50DM5', 'Lindavista Maf', '23.735, -99.1064', 2),
(406, '50DQT', 'La Moderna Maf', '23.73286801, -99.11290948', 2),
(407, '50E2Q', 'Aviles Maf', '23.7197845357962, -99.1047526820546', 2),
(408, '50GFP', 'La Paz Maf', '23.74155477, -99.11686136', 2),
(409, '50GZA', 'Zaragoza Maf', '23.70705268, -98.99292168', 2),
(410, '50JYC', 'Chapultepec Maf', '23.71899055, -99.11663258', 2),
(411, '50JZT', 'Zaragoza 2 Maf', '23.7226587, -98.9984358', 2),
(412, '50KD1', 'Marina Vieja II Maf', '23.7309564116347, -98.2199910863279', 2),
(413, '50MRJ', 'Marina Vieja Maf', '23.77037274, -98.20359088', 2),
(414, '50RNV', 'Rumbo Nuevo Maf', '23.71834185, -99.09496713', 2),
(415, '50S1T', 'Alta Vista Maf', '23.7195, -99.1078', 2),
(416, '50Z3O', 'San Felipe II Maf', '23.7164546699515, -99.1086899574159', 2),
(417, '50A92', 'Zurita Maf', '23.74923677, -99.12204001', 2),
(418, '504VH', 'Malitzin Maf', '23.75915, -99.125434', 2),
(419, '50UDG', 'Campestre Maf', '23.76068, -99.12994', 2),
(420, '5025Q', 'Royal Country Maf', '23.7614244080052, -99.1335385024354', 2),
(421, '504XP', 'Valle de Pajaritos Maf', '23.7623553558799, -99.1089449899341', 2),
(422, '505C7', 'Sierra Gorda Maf', '24.21371406, -98.48301535', 2),
(423, '507JI', 'Nuevo Santander Umi', '23.7508536300001, -99.111516519', 2),
(424, '50B9S', 'Olivin Maf', '23.77221395, -99.10839418', 2),
(425, '50C6M', 'Agronomos Maf', '23.7697561466864, -99.1232227106689', 2),
(426, '50C8A', 'Privanzas Maf', '23.7657373626813, -99.117178265817', 2),
(427, '50DWQ', 'Nuevo Padilla II Maf', '24.0579770600138, -98.8909635951513', 2),
(428, '50JJZ', 'Jimenez II Maf', '24.22146896, -98.49282389', 2),
(429, '50K4E', 'Guemez Centro Maf', '23.91852747, -99.00796882', 2),
(430, '50N72', 'Servitrail Maf', '23.853947, -99.055408', 2),
(431, '50NPD', 'Nuevo Padilla Maf', '24.04417531, -98.89786531', 2),
(432, '50S7R', 'Azteca Maf', '23.74472471, -99.10880341', 2),
(433, '50T2X', 'Benito Sierra 1 Maf', '24.05892741551008, -98.3725794889005', 2),
(434, '50U2L', 'Padilla Centro Maf', '24.05019965, -98.90096418', 2),
(435, '50V8G', 'Mirlos Maf', '23.7711868286132, -99.1035537719726', 2),
(436, '50VYB', 'Especialidades Maf', '23.76157563, -99.1093386', 2),
(437, '50XQZ', 'Pajaritos Maf', '23.75407198, -99.10650683', 2),
(438, '50D83', 'Olivin Centro Maf', '23.784804, -99.100177', 2),
(439, '50BBG', 'Cuartel Maf', '23.75587313, -99.17387853', 2),
(440, '500N0', 'Relaciones Exteriores Maf', '23.7321, -99.1519', 2),
(441, '50QOL', '17 Y Juarez Maf', '23.73079605, -99.1527349', 2),
(442, '508UF', '33 Juarez Maf', '23.7306, -99.169', 2),
(443, '50ASH', 'Asent. Humanos Maf', '23.71464507, -99.16163595', 2),
(444, '50CP6', 'Lara Maf', '23.7238728095586, -99.1694974279136', 2),
(445, '50DQF', '22 Rosales Maf', '23.72629269, -99.15787707', 2),
(446, '50HBI', 'Paseo Mendez Maf', '23.72422751, -99.15365631', 2),
(447, '50IKT', 'Tamatan Maf', '23.72046313, -99.16504827', 2),
(448, '50ISP', 'Americo Maf', '23.71857473, -99.17923932', 2),
(449, '50JZY', 'Sierra Maf', '23.74590459, -99.17223039', 2),
(450, '50KMT', 'Del Maestro Maf', '23.72168001, -99.16416601', 2),
(451, '50MC0', 'Alvaro Obregon Maf', '23.7259451241369, -99.1802319816546', 2),
(452, '50N7H', 'Laurel Maf', '23.72710226, -99.1695991', 2),
(453, '50NBQ', '7 De Noviembre Maf', '23.71493, -99.17812', 2),
(454, '50PA0', '32 Y Matamoros Maf', '23.73420981, -99.16901934', 2),
(455, '50RW8', 'Casas Blancas 2 Maf', '23.709211, -99.157429', 2),
(456, '50TKE', 'Libramiento II Maf', '23.73381967, -99.1768403', 2),
(457, '50U7A', 'Amalia G Maf', '23.70783231, -99.16125218', 2),
(458, '50UQX', 'Sierra Madre Maf', '23.71048419, -99.18066509', 2),
(459, '50WSR', '28 Juarez Maf', '23.7306691, -99.1632478', 2),
(460, '50YIT', '22 Iturbide Maf', '23.73203492, -99.15758943', 2),
(461, '50AZQ', '14 Diagonal Maf', '25.87420468, -97.51031001', 3),
(462, '50BIR', 'Laguna Salada Maf', '25.8693195, -97.5052897', 3),
(463, '50D98', 'Laguneta Maf', '25.880416, -97.518361', 3),
(464, '50FXY', '8 Abasolo Maf', '25.88099346, -97.50665874', 3),
(465, '50HYA', 'Ayala Maf', '25.87057785, -97.51716719', 3),
(466, '50JZL', 'Centro Historico Maf', '25.88023967, -97.50496737', 3),
(467, '50KDE', 'Teran Maf', '25.87485983, -97.50230507', 3),
(468, '50KTV', 'Mercado Juarez Maf', '25.8815, -97.5084', 3),
(469, '50NAQ', 'Laguna Jasso Maf', '25.86742354, -97.5038458', 3),
(470, '50NKV', '20 Y Gonzalez Maf', '25.87803612, -97.51663361', 3),
(471, '50OTU', 'Triangulo Maf', '25.8669576, -97.51061381', 3),
(472, '50PAY', 'Plan De Ayutla Maf', '25.8651374, -97.50476258', 3),
(473, '50SGZ', 'Seguro Social Maf', '25.87331803, -97.50444448', 3),
(474, '50UCA', 'San Francisco Maf', '25.86909366, -97.5146428', 3),
(475, '50UGZ', 'Gonzalez Maf', '25.87919712, -97.51058558', 3),
(476, '50UOM', 'Morelos Maf', '25.87834909, -97.51102642', 3),
(477, '50UOV', 'Diagonal Y 20 Maf', '25.87593065, -97.51703139', 3),
(478, '50USC', 'Canales Maf', '25.87116672, -97.50369118', 3),
(479, '50ZPZ', 'Plaza Allende Maf', '25.87887593, -97.50791528', 3),
(480, '50ZYU', 'Zaragoza Maf', '25.87532596, -97.50406647', 3),
(481, '502BE', 'Sat Maf', '25.8752, -97.5203', 3),
(482, '50UOA', 'Aguila Maf', '25.87587644, -97.52532701', 3),
(483, '505JL', 'Puente Viejo Maf', '25.88641145, -97.50841261', 3),
(484, '500OX', 'Brecha 119 Maf', '25.6792, -97.8257', 3),
(485, '50MDW', 'Madero Maf', '25.66956976, -97.8156647', 3),
(486, '50DNS', 'Cardenas Maf', '25.67969494, -97.8163557', 3),
(487, '5000G', 'Alameda Maf', '25.666799, -97.817137', 3),
(488, '501TH', 'Paso Real Maf', '24.84048215, -98.16472285', 3),
(489, '506BJ', 'Rosalinda Guerrero Maf', '25.67865805, -97.80992997', 3),
(490, '50805', '6 Y Juarez Maf', '25.67214335, -97.80741423', 3),
(491, '50CHF', 'Chavez Maf', '25.66647444, -97.81246669', 3),
(492, '50CHV', 'Echeverria Maf', '25.66705843, -97.800432', 3),
(493, '50DBK', 'Dos De Abril Maf', '25.67209594, -97.82027187', 3),
(494, '50DZJ', '15 Y Juarez Maf', '25.67204589, -97.79590148', 3),
(495, '50EUE', 'Echeverria III Maf', '25.66629497, -97.79631385', 3),
(496, '50GHE', 'Echeverria Iimaf', '25.66625981, -97.78417673', 3),
(497, '50HQW', 'Hidalgo Maf', '25.67299957, -97.8130822', 3),
(498, '50ICT', 'Castillo Maf', '25.6673187, -97.82564693', 3),
(499, '50KCS', 'Cardenas Ii Maf', '25.6933696, -97.81529134', 3),
(500, '50L32', 'Cosme Santos Maf', '25.67418837, -97.82596463', 3),
(501, '50Q58', 'Independencia Maf', '25.67333405, -97.83573562', 3),
(502, '50RLT', 'El Realito Maf', '25.6670082, -97.86769606', 3),
(503, '50SFS', 'Los Fresnos Maf', '25.65124031, -97.81671465', 3),
(504, '50V6D', 'Brecha 82 Maf', '25.666554360664, -97.8401281787857', 3),
(505, '50YME', 'America Maf', '25.67549631, -97.81628883', 3),
(506, '50ZZZ', 'Cipres Maf', '25.66080929, -97.81530689', 3),
(507, '502GT', 'Lopez Mateos Maf', '25.68592074, -97.80754429', 3),
(508, '50L92', 'Soberon Maf', '25.6668511379221, -97.7892826903123', 3),
(509, '505UA', 'Las Rusias Maf', '25.907932, -97.551815', 3),
(510, '50BNK', 'Luicio Blanco Ii Maf', '25.93594697, -97.77774595', 3),
(511, '50BYS', 'Las Brisas Maf', '25.86439682, -97.55686306', 3),
(512, '50DEW', 'Sendero Iii Maf', '25.86377577, -97.57781044', 3),
(513, '50EIB', 'El Capote Maf', '25.97163046, -97.69624326', 3),
(514, '50HWC', 'Anahuac Maf', '25.77544678, -97.79554332', 3),
(515, '50J3R', 'Anahuac Centro Maf', '25.7750744, -97.7773583', 3),
(516, '50JZI', 'Las Brisas 3 Maf', '25.86485228, -97.56347549', 3),
(517, '50QOO', 'Lucio Blanco Maf', '25.95436223, -97.76862361', 3),
(518, '50T77', 'Inteva Maf', '25.88296, -97.547027', 3),
(519, '50TBO', 'Tres Moras Maf', '25.86231411, -97.58703597', 3),
(520, '50WKI', 'Los Presidentes Maf', '25.85822054, -97.58362043', 3),
(521, '50XFN', 'Las Brisas Ii Maf', '25.85816557, -97.55942618', 3),
(522, '50YHF', 'Empalme Maf', '25.90547428, -97.84303364', 3),
(523, '50060', 'Delphi Maf', '25.884485, -97.552902', 3),
(524, '50423', 'El Caracol Maf', '25.87011677, -97.56570965', 3),
(525, '50A35', 'Santa Maria Maf', '25.89311899, -97.53271477', 3),
(526, '50BBH', 'Villa Del Parque Mam', '25.87869554, -97.55645723', 3),
(527, '50BIY', 'Ejido Los Arados Mam', '25.86878953, -97.55754216', 3),
(528, '50EBA', 'El Ebanito Maf', '25.93889588, -97.62000357', 3),
(529, '50EKE', 'Del Valle Maf', '25.87337629, -97.55150795', 3),
(530, '50IBF', 'Uniones Maf', '25.8870743, -97.54413359', 3),
(531, '50SFY', 'Sendero Ii Maf', '25.86564933, -97.57219289', 3),
(532, '50SYN', 'Sendero I Maf', '25.8702, -97.5521', 3),
(533, '50UJD', 'Industrial Maf', '25.87773028, -97.5284113', 3),
(534, '50XLF', 'Las Fuentes Maf', '25.88907027, -97.53894859', 3),
(535, '50GGG', 'Brisas Del Valle Maf', '25.8509, -97.5621', 3),
(536, '506GX', 'Diego Rivera Maf', '25.823307430915, -97.494335579905', 3),
(537, '50CAU', 'Curacao Maf', '25.82401911, -97.50604431', 3),
(538, '50GPL', 'Nicolas Guerra Maf', '25.840081, -97.50118666', 3),
(539, '50II0', 'Jardines De San Juan Maf', '25.8259, -97.4829', 3),
(540, '50IUX', 'El Saucito Mam', '25.83778021, -97.48919731', 3),
(541, '50JD7', 'Expofiesta Oriente Maf', '25.82793748, -97.50500849', 3),
(542, '50JZD', 'Juarez Ii Maf', '25.84214447, -97.49016895', 3),
(543, '50JZH', 'San Miguel Maf', '25.82887287, -97.48148012', 3),
(544, '50LTR', 'Las Torres Maf', '25.82159925, -97.50538325', 3),
(545, '50MJN', 'San Juan Maf', '25.8241887, -97.49006855', 3),
(546, '50NDN', 'Del Nino Maf', '25.83634708, -97.49797329', 3),
(547, '50SFN', 'San Fernando Maf', '25.83364448, -97.49903911', 3),
(548, '50SJP', 'Solidaridad Ii Maf', '25.84530958, -97.49216664', 3),
(549, '50SLD', 'Solidaridad Maf', '25.84840997, -97.48322601', 3),
(550, '50UBJ', 'Benito Juarez Maf', '25.82905332, -97.48782636', 3),
(551, '50UXJ', 'Lomas De San Juan Maf', '25.83011443, -97.4875399', 3),
(552, '50VVN', '20 Noviembre Maf', '25.83470447, -97.50319899', 3),
(553, '50WPR', 'Tampico Maf', '25.84433964, -97.49666647', 3),
(554, '50F4I', 'Nogalar Maf', '25.8401416319369, -97.4818287000036', 3),
(555, '5003W', 'Tercera Maf', '25.84942236, -97.50084847', 3),
(556, '50KJB', 'Satelite Maf', '25.85064617, -97.49607104', 3),
(557, '50UML', 'Longoria Maf', '25.847836, -97.50115348', 3),
(558, '5055Q', 'Benjamin Gaona Maf', '25.8062911485822, -97.5102307678452', 3),
(559, '50OSU', 'Suriname Maf', '25.81723542, -97.50522585', 3),
(560, '50ZMI', 'La Amistad Maf', '25.81176331, -97.51023962', 3),
(561, '50Z8B', 'Tianguis del Niño Maf', '25.83705272, -97.49545043', 3),
(562, '508DX', 'Electricistas Maf', '25.82689482, -97.50065291', 3),
(563, '50182', 'Guadalupe Mainero Maf', '25.8529508621562, -97.4641130001352', 3),
(564, '50ABD', 'Arboledas Maf', '25.86749412, -97.47200127', 3),
(565, '50AQ1', 'Mainero Maf', '25.85422185, -97.46240979', 3),
(566, '50AXJ', 'Alianza Maf', '25.86581445, -97.48850094', 3),
(567, '50CRE', 'Central Maf', '25.87092069, -97.49939792', 3),
(568, '50IDZ', 'Alvaro Obregon Maf', '25.89044142, -97.50268828', 3),
(569, '50JS8', 'El Laguito Maf', '25.86509481, -97.49450805', 3),
(570, '50MPO', 'Ocampo Maf', '25.87139996, -97.49437313', 3),
(571, '50NSF', 'Pumarejo Maf', '25.86970651, -97.49588589', 3),
(572, '50OEM', 'El Moro Maf', '25.87855505, -97.49950205', 3),
(573, '50P8O', 'Del Cambio Maf', '25.8648705903856, -97.4845455070704', 3),
(574, '50PMA', 'Primera Maf', '25.88138781, -97.49918998', 3),
(575, '50SQC', 'San Carlos Maf', '25.86776486, -97.48536791', 3),
(576, '50UDN', 'Division Del Norte Maf', '25.86284086, -97.47188435', 3),
(577, '50UGO', 'Gobernacion Maf', '25.87088762, -97.48663493', 3),
(578, '50UOJ', 'Jardin Maf', '25.89366858, -97.4991829', 3),
(579, '50UOL', 'Lauro Villar Maf', '25.86259509, -97.47848778', 3),
(580, '50UOR', 'Rio Maf', '25.87884091, -97.49398556', 3),
(581, '50URR', 'Arrese Maf', '25.87168041, -97.48988406', 3),
(582, '50VPA', 'Las Palmas Maf', '25.86863235, -97.48120721', 3),
(583, '50XWF', 'Imss Maf', '25.86089449, -97.47504443', 3),
(584, '50A2A', 'Molino Del Rey 2 Maf', '25.8501, -97.5865', 3),
(585, '50A4S', 'Arecas Maf', '25.84369122, -97.5930637', 3),
(586, '50JZK', 'Molino Del Rey Maf', '25.84598918, -97.58499129', 3),
(587, '50WPP', 'Pueblitos Maf', '25.82241025, -97.58135624', 3),
(588, '50T5W', 'Washington Maf', '25.874662, -97.49599337', 3),
(589, '506ZG', 'Nuevo Milenio Maf', '25.85553608, -97.54240149', 3),
(590, '509CO', 'Porfirio Diaz Maf', '25.867, -97.5278', 3),
(591, '50ACI', 'Acuario Maf', '25.86259319, -97.5288622', 3),
(592, '50COW', 'Constituyentes Maf', '25.8626046012892, -97.5421662295388', 3),
(593, '50CSA', 'Casa Blanca Maf', '25.86136889, -97.54080326', 3),
(594, '50DQY', 'Mexicali Maf', '25.84975451, -97.52054176', 3),
(595, '50F3Z', 'Rigo Tovar Maf', '25.87480526, -97.53074693', 3),
(596, '50JYE', 'Villa Azteca Maf', '25.845312, -97.52104206', 3),
(597, '50LXF', 'Leyes De Reforma Maf', '25.86245278, -97.52338155', 3),
(598, '50MFU', 'Magisterio Maf', '25.85683562, -97.53027169', 3),
(599, '50MYV', '12 De Marzo Maf', '25.86996626, -97.5373839', 3),
(600, '50OEG', 'Egipto Maf', '25.86429232, -97.54475423', 3),
(601, '50QEN', 'Quinta Real Maf', '25.85841105, -97.53058414', 3),
(602, '50SFW', 'San Felipe Maf', '25.85126888, -97.53996234', 3),
(603, '50UOP', 'Puerto Rico Maf', '25.85239291, -97.52850522', 3),
(604, '50URF', 'San Rafael Maf', '25.86970464, -97.5228201', 3),
(605, '50VWR', 'Valle Real Maf', '25.85303429, -97.53358014', 3),
(606, '50ZDK', 'Quinta Real 2 Maf', '25.85855898, -97.5354061', 3),
(607, '505YL', 'Crucero Sendero Maf', '25.8708134937102, -97.5492455015096', 3),
(608, '50MSI', 'Santa Anita Maf', '25.84539744, -97.53503017', 3),
(609, '50QQM', 'Bagdad Maf', '25.8418613, -97.52766937', 3),
(610, '50X92', 'Puerto Rico 2 Maf', '25.85028486, -97.53197268', 3),
(611, '501DD', 'Bella Vista Sur', '24.84232348, -98.14591786', 3),
(612, '503D1', 'Libramiento San Fer Maf', '24.8468955303069, -98.1082556787211', 3),
(613, '505DK', 'Loma Alta Maf', '24.85564138, -98.15732595', 3),
(614, '505UT', 'Las Yescas Maf', '25.61200104, -97.81659906', 3),
(615, '506VP', 'Padre Mier Maf', '24.85023978, -98.14579727', 3),
(616, '50E7Y', 'Ignacio Allende Maf', '24.841666, -98.132153', 3),
(617, '50I03', 'San German Maf', '25.2048404482604, -97.9332290889451', 3),
(618, '50JZN', 'Moquetito Maf', '25.51471103, -97.73292001', 3),
(619, '50MWZ', 'Bella Vista Maf', '24.84445237, -98.13882642', 3),
(620, '50NRW', 'Las Norias Maf', '24.69942946, -98.26329575', 3),
(621, '50NXE', 'Ruiz Cortinez Maf', '24.8508859, -98.15347915', 3),
(622, '50QKJ', 'Plaza Maf', '24.84757078, -98.16019754', 3),
(623, '50QW7', 'Carretera San Fernando Maf', '24.859365078852, -98.1418532253864', 3),
(624, '50RJO', 'Rancho Viejo Maf', '25.06610927, -98.07004795', 3),
(625, '50RYI', '2Do Centenario Maf', '24.85277405, -98.15565308', 3),
(626, '50TUU', 'Ruiz Cortinez Ii Maf', '24.84774777, -98.15401752', 3),
(627, '50XLJ', 'Loma Colorada Maf', '24.83804922, -98.12375414', 3),
(628, '50YNO', 'Pino Suarez Maf', '24.84747804, -98.15162111', 3),
(629, '506BY', 'Ignacio Allende 2 Maf', '24.84676179, -98.14629248', 3),
(630, '50R1K', 'Juan de la Barrera Maf', '24.837124, -98.146332', 3),
(631, '5017X', 'Las Culturas Maf', '25.843965, -97.455436', 3),
(632, '503JN', 'Vancouver Maf', '25.8298, -97.4248', 3),
(633, '50DCW', 'Canada Maf', '25.83402437, -97.42313463', 3),
(634, '50ESC', 'Escandon Maf', '25.8369178, -97.43694882', 3),
(635, '50EUQ', 'Campestre Del Lago Maf', '25.84276625, -97.45000065', 3),
(636, '50FIW', 'Finsa Maf', '25.83593019, -97.42993929', 3),
(637, '50FUI', 'Fue. Industrialesmaf', '25.83776653, -97.44336296', 3),
(638, '50GV9', 'Ciudad Industrial Maf', '25.8392, -97.4307', 3),
(639, '50IFX', 'Palmas De Mar Maf', '25.82283784, -97.45761062', 3),
(640, '50MHX', 'Camino Real Maf', '25.84295659, -97.45805618', 3),
(641, '50NU8', 'Fundadores Maf', '25.83516317, -97.45212896', 3),
(642, '50SJW', 'San Jeronimo 2 Maf', '25.8286417, -97.43687242', 3),
(643, '50SJX', 'San Jeronimo Maf', '25.82812138, -97.4344354', 3),
(644, '50TEH', 'Teotihuacan Maf', '25.83076401, -97.45264763', 3),
(645, '50UOY', 'Playa Maf', '25.84679016, -97.45707109', 3),
(646, '50UPV', 'Palo Verde Maf', '25.84111068, -97.45778035', 3),
(647, '50XCI', 'La Cima Maf', '25.83323678, -97.44451164', 3),
(648, '50XGZ', 'Magnolias Maf', '25.84967512, -97.45967496', 3),
(649, '50YDK', 'Longoreno Maf', '25.83258587, -97.37352513', 3),
(650, '50Z6S', 'Taxquena Maf', '25.8285, -97.4575', 3),
(651, '50AZP', 'Realdelas Palmas Maf', '25.84178236, -97.55426941', 3),
(652, '50G9W', 'Palmares Norte Maf', '25.84813336, -97.55394548', 3),
(653, '50J3M', 'Tianguis Palmares Maf', '25.83699, -97.54309', 3),
(654, '50LXP', 'Los Palmares Maf', '25.83981148, -97.54785953', 3),
(655, '50NE6', 'Paseo De Los Palmares Maf', '25.84351881, -97.55954636', 3),
(656, '508QZ', 'Popular Maf', '25.85113929, -97.48008419', 3),
(657, '50YXC', 'Mexico Maf', '25.83499088, -97.46181134', 3),
(658, '50AIE', 'Emiliano Zapata Maf', '25.84398731, -97.48029764', 3),
(659, '50AKV', 'Accion Civica Maf', '25.86306144, -97.48056636', 3),
(660, '50AWC', 'Avellano Maf', '25.85741579, -97.4896494', 3),
(661, '50D8C', 'Alamo Maf', '25.83419, -97.48772', 3),
(662, '50EUV', 'Trevino Zapata Maf', '25.8618212, -97.48064036', 3),
(663, '50KXW', 'Paraiso Maf', '25.84271535, -97.47051259', 3),
(664, '50M5R', 'Republica de Argentina Maf', '25.8371594276607, -97.4657845388007', 3),
(665, '50MCF', 'Cantinflas Maf', '25.8599725, -97.47661538', 3),
(666, '50MPP', 'Durazno Maf', '25.83600976, -97.47030183', 3),
(667, '50O0A', 'Oceano Pacifico Sur Maf', '25.8349091836087, -97.4828988379215', 3),
(668, '50OBR', 'Valle Verde Maf', '25.83836863, -97.47800412', 3),
(669, '50OL6', 'El Porvenir Maf', '25.8282, -97.4755', 3),
(670, '50Q0N', 'Jilgueros Maf', '25.83471304, -97.47773311', 3),
(671, '50SOY', 'Playa Sol Maf', '25.85395987, -97.47501047', 3),
(672, '50UOG', 'Guerra I Maf', '25.86084077, -97.48656218', 3),
(673, '50UTR', 'Tarahumara Maf', '25.84853501, -97.46454427', 3),
(674, '50WK3', 'Democracia Social Maf', '25.84488667, -97.47597114', 3),
(675, '50XTF', 'Roberto Guerra Maf', '25.85701712, -97.47929551', 3),
(676, '50YZT', 'Playa Hornos Maf', '25.84756899, -97.47141617', 3),
(677, '507UH', 'Las Palmitas Maf', '25.83105107, -97.46909365', 3),
(678, '50B9K', 'Portillo Maf', '25.871861, -97.54238', 3),
(679, '501XK', 'Mariano Matamoros Maf', '25.85358379, -97.51715949', 3),
(680, '50AWW', 'Espana Maf', '25.86253453, -97.50998038', 3),
(681, '50DQE', '18 Y Espana Maf', '25.86275323, -97.51529911', 3),
(682, '50DQQ', 'El Roble Maf', '25.85680592, -97.51310702', 3),
(683, '50FD8', 'Carlos Salazar Maf', '25.8522981691562, -97.5099408660668', 3),
(684, '50FYT', 'Nafarrete Maf', '25.8589764012207, -97.5042256716712', 3),
(685, '50LPW', 'La Salle Maf', '25.85891808, -97.50743866', 3),
(686, '50LTO', 'Valle Alto Maf', '25.84813059, -97.5121054', 3),
(687, '50Q9L', 'Carmen Serdan Maf', '25.85336371, -97.48860764', 3),
(688, '50QLC', 'Tlaxcala Maf', '25.8543441, -97.50118432', 3),
(689, '50RUO', 'La Aurora Maf', '25.85936383, -97.50094905', 3),
(690, '50SQW', 'Solernau Maf', '25.85948595, -97.49491753', 3),
(691, '50UVI', 'Vizcaya Maf', '25.86497228, -97.49854052', 3),
(692, '50XAD', 'Paseoresidencial Maf', '25.85876212, -97.51651534', 3),
(693, '50XER', 'Periferico Maf', '25.85661093, -97.4918255', 3),
(694, '50XYV', 'Virgo Maf', '25.85061819, -97.49155244', 3),
(695, '50Y52', 'Mediterraneo Maf', '25.8573751212145, -97.4853534084826', 3),
(696, '501UA', 'Seccion 16 Maf', '25.83437322, -97.51948614', 3),
(697, '50DQG', 'Agapito Gonzalez Maf', '25.83407516, -97.51789103', 3),
(698, '50FVN', 'Valle Dorado Maf', '25.8395689333881, -97.5127494177997', 3),
(699, '50I79', 'Pedro Cardenas Maf', '25.840351, -97.508213', 3),
(700, '50QFA', 'Santa Cecilia Maf', '25.83490509, -97.51000592', 3),
(701, '50UGI', 'Gimnasio Maf', '25.82579577, -97.51305879', 3),
(702, '50UMN', 'Mundo Nuevo Maf', '25.82862535, -97.51174457', 3),
(703, '50UMZ', 'Mezquital Maf', '25.71594623, -97.57188758', 3),
(704, '50UOO', 'Rago Maf', '25.84406812, -97.50774751', 3),
(705, '50WQX', 'Portes Gil  Maf', '25.81092292, -97.51920359', 3),
(706, '50YJL', 'Misiones Maf', '25.82638782, -97.54169512', 3),
(707, '5006I', 'El Galañero Maf', '25.760361882612, -97.5451446482987', 3),
(708, '50AWU', 'Expo Fiesta Maf', '25.82676978, -97.52008173', 3),
(709, '50BAG', 'Voluntad y trabajo Mam', '25.83286411, -97.52824518', 3),
(710, '50FBV', 'Fco I. Madero Maf', '25.82535779, -97.52682946', 3),
(711, '50HGQ', 'Marte R. Gomez Maf', '25.82755956, -97.53152779', 3),
(712, '50IG0', 'Joaquin Pardave Maf', '25.823824161465, -97.5233188159956', 3),
(713, '50UMA', 'Aeropuerto Maf', '25.78224853, -97.53298351', 3),
(714, '50UOZ', 'Ragoz Maf', '25.81843259, -97.51725587', 3),
(715, '50XRT', 'Las Flores Maf', '25.81570073, -97.52377891', 3),
(716, '5025A', 'Virgilio Garza Maf', '25.833199, -97.534854', 3),
(717, '50MJW', 'Misiones 2 Maf', '25.82645, -97.55624', 3),
(718, '50WHT', 'Martha Rita Maf', '25.82004369, -97.54713064', 3),
(719, '50YJG', '12 De Marzo 2 Maf', '25.8271638, -97.54579435', 3),
(720, '50C4W', 'Aguas Subterraneas Maf', '25.843883, -97.50472659', 3),
(721, '506NE', 'Centenario Tam', '22.2400917707705, -97.8414734734342', 4),
(722, '50APH', 'Imss Tam', '22.24741504, -97.85485896', 4),
(723, '50CHT', 'Cuauhtemoc Tam', '22.24025541, -97.8636529', 4),
(724, '50FGM', 'Frente Democratico Tam', '22.24468229, -97.85795248', 4),
(725, '50HVH', 'Santo Nino Tam', '22.24534967, -97.85512919', 4),
(726, '50QVW', 'Colonias Tam', '22.24538151, -97.86366798', 4),
(727, '50SIT', 'Central Tam', '22.24948304, -97.85791789', 4),
(728, '50TRO', 'Rosalio Tam', '22.25215127, -97.85809682', 4),
(729, '50VR7', 'Zapotal Tam', '22.250097743424, -97.8551042594593', 4),
(730, '50NPC', 'Servando Canales Tam', '22.24615204, -97.8324409', 4),
(731, '5072G', 'Victoria Tam', '22.2244, -97.8493', 4),
(732, '50AJH', 'Alameda Tam', '22.22414853, -97.84450839', 4),
(733, '50ASA', 'Tula Tam', '22.22968645, -97.84190406', 4),
(734, '50AYN', 'Ayuntamiento Tam', '22.23190972, -97.86476402', 4),
(735, '50FH3', 'Simon Bolivar', '22.2325059873481, -97.8401123648934', 4),
(736, '50GKT', 'Canseco Tam', '22.22355655, -97.85856125', 4),
(737, '50IMW', 'Volantin Tam', '22.22541714, -97.86298726', 4),
(738, '50VJP', 'Torreon Tam', '22.22661877, -97.86487803', 4),
(739, '50Y5Z', 'Tampico Tam', '22.22782751, -97.84628071', 4),
(740, '50YE9', 'Bella Vista Tam', '22.2234953, -97.864993', 4),
(741, '50ZMW', 'Metropolitano Tam', '22.22932018, -97.85953223', 4),
(742, '5023U', 'Reforma Tam', '22.24278483, -97.8504512', 4),
(743, '50BTC', 'Tolteca Tam', '22.23615861, -97.86025407', 4),
(744, '50ENP', 'Rio Verde Tam', '22.23765324, -97.85105028', 4),
(745, '50YZD', 'Obrera Tam', '22.23919045, -97.84718239', 4),
(746, '50ELO', 'Leones Tam', '22.25730281, -97.85703037', 4),
(747, '50GIC', 'Tecnologico Tam', '22.25404582, -97.84898302', 4),
(748, '50OGO', 'Obregon Tam', '22.24409017, -97.83888364', 4),
(749, '50PHW', 'Pachuca Tam', '22.24654276, -97.84549726', 4),
(750, '50WPU', 'Libertad Tam', '22.24020575, -97.84002173', 4),
(751, '50WXC', 'Canaco Tam', '22.24285976, -97.83631065', 4),
(752, '50ZJG', 'Los Mangos Tam', '22.25516547, -97.85088691', 4),
(753, '505P0', 'Mercado Madero Tam', '22.249059, -97.835417', 4),
(754, '509SJ', 'Via Monterrey', '22.2504311, -97.835369', 4),
(755, '50A5F', 'Talleres Tam', '22.25057228, -97.82724095', 4),
(756, '50ARV', 'Quintero Tam', '22.26123832, -97.82885923', 4),
(757, '50CMW', 'Civil Madero Tam', '22.25409434, -97.82168276', 4),
(758, '50DAY', '5 De Mayo Tam', '22.24869405, -97.8389068', 4),
(759, '50FIJ', 'Fraile Tam', '22.24517525, -97.83591339', 4),
(760, '50GTE', 'Guatemala Tam', '22.25306266, -97.8307192', 4),
(761, '50IKY', 'Leon Tam', '22.25540115, -97.82867436', 4),
(762, '50POM', '1o. De Mayo Tam', '22.25122518, -97.84364605', 4),
(763, '50SBA', 'Sarabia Tam', '22.25169584, -97.84748045', 4),
(764, '50TJB', 'Auditorio Tam', '22.2472613, -97.83980468', 4),
(765, '50XTN', 'Necaxa Tam', '22.25164044, -97.8404364', 4),
(766, '50ZAX', 'Plaza Madero Tam', '22.24804829, -97.83744021', 4),
(767, '50DM3', 'Gas Sunoco Tam', '22.2770598, -97.8416', 4),
(768, '50HHJ', 'Ocampo Tam', '22.27701205, -97.83957338', 4),
(769, '50IQD', 'Insurgentes Tam', '22.27541653, -97.84162569', 4),
(770, '50RIM', 'Miramar Tam', '22.27673152, -97.84131557', 4),
(771, '50Y6Q', 'Brasil Maf', '22.252723, -97.83662636', 4),
(772, '50EM0', 'Kehoe Tam', '22.2867, -97.8363', 4),
(773, '50YPV', 'Sahop Tam', '22.28141551, -97.82918116', 4),
(774, '508CF', 'Pakistan Tam', '22.3212131, -97.8553975', 4),
(775, '50HHP', 'Palafox Tam', '22.30057263, -97.84416665', 4),
(776, '50I2V', 'Calle 7 Tam', '22.30362504, -97.85118016', 4);
INSERT INTO `tienda` (`id`, `cr_tienda`, `nombre`, `coordenadas`, `plaza_id`) VALUES
(777, '50IKW', 'Borreguera Tam', '22.31649978, -97.85926177', 4),
(778, '50RAK', 'Cardenas Tam', '22.30821902, -97.84948464', 4),
(779, '50TA9', 'Australia Tam', '22.3115968, -97.85341', 4),
(780, '50TH1', 'Juan Pablo II Tam', '22.3078944293951, -97.8560615907281', 4),
(781, '502MB', 'Burgos Tam', '22.2942939, -97.851496', 4),
(782, '502YC', 'Nicolas Bravo Tam', '22.28635167, -97.84802755', 4),
(783, '504EC', 'Bujanos Tam', '22.2948053, -97.845401', 4),
(784, '504KG', 'Las Chacas Tam', '22.2921267, -97.852984', 4),
(785, '50IKH', 'Lopez Portillo Tam', '22.29448252, -97.85554561', 4),
(786, '50LUL', 'Luna Luna Tam', '22.29011419, -97.85147799', 4),
(787, '50N7U', 'Lucio Blanco Tam', '22.264874, -97.8340256', 4),
(788, '50OOT', 'Ampliacion II Tam', '22.28502748, -97.84373705', 4),
(789, '50THR', 'Pescador Tam', '22.2662122, -97.836996', 4),
(790, '50WYR', 'Flores Tam', '22.29062045, -97.8466264', 4),
(791, '50YNB', 'Campo Faja De Oro Tam', '22.2989154, -97.84876521', 4),
(792, '501O5', 'La Perla Maf', '22.3147055285019, -97.8517788899894', 4),
(793, '5071I', 'Isauro Alfaro Tam', '22.21369815, -97.85416811', 4),
(794, '50AMH', 'Isleta Tam', '22.2122033360001, -97.8485309979999', 4),
(795, '50EDK', 'Carranza Tam', '22.21527036, -97.85576106', 4),
(796, '50ENT', 'Centro Tam', '22.21527051, -97.8580172', 4),
(797, '50FV5', 'General San Martin Maf', '22.2142574932207, -97.8519707417154', 4),
(798, '50JWE', 'Zona Centro Tam', '22.21732042, -97.85818502', 4),
(799, '50KFI', 'Fiscal Tam', '22.21415164, -97.85294015', 4),
(800, '50LAO', 'Centralita Tam', '22.21505032, -97.85961075', 4),
(801, '50N4D', 'Pedro J. Mendez Tam', '22.21331133, -97.85845346', 4),
(802, '50O4H', 'Aquiles Tam', '22.2155732, -97.85205', 4),
(803, '50OPW', 'El Chorro Tam', '22.2146085, -97.8609575', 4),
(804, '50TTI', 'Imperial Tam', '22.21475453, -97.85457129', 4),
(805, '50VWI', 'Diaz Miron Tam', '22.214174, -97.85550302', 4),
(806, '50XOP', 'Plaza Tam', '22.21280169, -97.85678277', 4),
(807, '50CYH', '2 De Enero Tam', '22.2150315, -97.848236', 4),
(808, '50ESN', 'Escandon Tam', '22.21963318, -97.85420454', 4),
(809, '50JKJ', 'Mainero Tam', '22.21957718, -97.8495296', 4),
(810, '50OOG', 'Golfo Tam', '22.22046264, -97.84895804', 4),
(811, '50SIW', 'Nautica Tam', '22.22126193, -97.85088135', 4),
(812, '50UUV', 'Plaza Golfo Tam', '22.21796183, -97.8434409', 4),
(813, '5058M', 'Alvaro Tam', '22.2617348, -97.820961', 4),
(814, '505Y9', 'Corredor Urbano Tam', '22.2888478, -97.814031', 4),
(815, '508R4', 'Jimenez Tam', '22.2753, -97.8338', 4),
(816, '50AEV', 'Maeva Tam', '22.2868238, -97.80320036', 4),
(817, '50BEH', 'Real Del Mar Tam', '22.29061496, -97.80956363', 4),
(818, '50EZL', 'Escolleras Tam', '22.26407507, -97.7866984', 4),
(819, '50GZP', 'Nardos Tam', '22.2917995002922, -97.8368209572777', 4),
(820, '50HGM', 'Calle 15 Tam', '22.28247453, -97.834806', 4),
(821, '50HOO', 'Hipodromo Tam', '22.26530725, -97.82469467', 4),
(822, '50ILQ', 'Barriles Tam', '22.27262017, -97.80404881', 4),
(823, '50LGS', '8 Leguas Tam', '22.27680572, -97.83668854', 4),
(824, '50MAK', 'Miramapolis Tam', '22.29351454, -97.82018294', 4),
(825, '50MWF', 'Mirador Tam', '22.26982847, -97.79034725', 4),
(826, '50OUC', 'Tercera Avenida Tam', '22.28559709, -97.82151385', 4),
(827, '50QX2', 'Francisco Villa Tam', '22.2726238, -97.827548', 4),
(828, '50RFI', 'Refineria Tam', '22.25835425, -97.82449189', 4),
(829, '50RTV', 'Recreativo II Tam', '22.30178413, -97.81661651', 4),
(830, '50RXA', 'Mar Tam', '22.27977294, -97.79775536', 4),
(831, '50SRW', 'Sirenas Tam', '22.27532915, -97.79435554', 4),
(832, '50V3Z', 'Vela Maria Tam', '22.3037888, -97.817803', 4),
(833, '50BXI', 'Arenal Tam', '22.29281317, -97.87738181', 4),
(834, '505ER', 'Torres Norte Tam', '22.3178, -97.8764', 4),
(835, '50AIC', 'Revolucion Verde Tam', '22.31769173, -97.85134811', 4),
(836, '50BKE', 'Del Bosque Tam', '22.3233451, -97.8762085', 4),
(837, '50DNY', 'Germinal Tam', '22.30484983, -97.85844675', 4),
(838, '50DOQ', 'Pino Tam', '22.31428302, -97.87908087', 4),
(839, '50GVI', 'Villahermosa Tam', '22.32167895, -97.8696383', 4),
(840, '50JFT', 'Josefa Ortiz Tam', '22.310622, -97.869078', 4),
(841, '50KGA', 'Del Valle Tam', '22.30736748, -97.86313645', 4),
(842, '50KSX', 'Sexta Avenida Tam', '22.30723355, -97.86885483', 4),
(843, '50QOU', 'Nuevo Progreso Tam', '22.31097502, -97.87378532', 4),
(844, '50RZR', 'Laguna Puerta Tam', '22.31708289, -97.87144471', 4),
(845, '50SKS', 'Enrique Cardenas Tam', '22.30023726, -97.85557176', 4),
(846, '50TMC', 'Curva Texas Tam', '22.30976732, -97.88071211', 4),
(847, '50K0A', 'Bravos Maf', '22.3071407444583, -97.8737006302514', 4),
(848, '505MR', 'Colegio Militar Tam', '22.3188924, -97.886993', 4),
(849, '505XR', 'Chairel Tam', '22.2987, -97.898512', 4),
(850, '50893', 'Petroquimicas Tam', '22.30932, -97.89072', 4),
(851, '50AER', 'Las Americas Tam', '22.30863995, -97.88127058', 4),
(852, '50BMS', 'Colombia Tam', '22.3072687, -97.88583323', 4),
(853, '50CWO', 'Chapultepec Tam', '22.32287922, -97.88278341', 4),
(854, '50HX3', 'Colosio II', '22.3232773, -97.89574745', 4),
(855, '50IJL', 'Campanula Tam', '22.31647632, -97.89066668', 4),
(856, '50IKL', 'La Paz Ii Tam', '22.30446097, -97.89318019', 4),
(857, '50SHY', 'Champayan Tam', '22.29964896, -97.89384032', 4),
(858, '50TFX', 'Las Torres Tam', '22.30998687, -97.88768829', 4),
(859, '50ULW', 'Colosio Tam', '22.31975755, -97.89573614', 4),
(860, '50XO5', 'Canada Tam', '22.3121, -97.8822', 4),
(861, '50XPT', 'San Pedro Tam', '22.30134411, -97.87951108', 4),
(862, '50YOK', 'Haiti Tam', '22.31620515, -97.88250277', 4),
(863, '50WU4', 'Jaibos Maf', '22.30795958, -97.87906284', 4),
(864, '507HY', 'El Navegante Maf', '22.30265763, -97.8638509', 4),
(865, '502UP', 'San Antonio Tam', '22.2991778, -97.886278', 4),
(866, '50AET', 'Aeropuerto Tam', '22.2870284, -97.86910548', 4),
(867, '50TNK', 'Tancol Tam', '22.29510374, -97.88167306', 4),
(868, '50TXP', 'Palmas Tam', '22.28661259, -97.87308444', 4),
(869, '501LU', 'Sierra Morena Tam', '22.2529434, -97.8756376', 4),
(870, '503HL', 'Hydros Tam', '22.2905255, -97.894246', 4),
(871, '506OI', 'Vista Bella Tam', '22.2869254838099, -97.8907975218485', 4),
(872, '50AGW', 'Agua Dulce Tam', '22.25718351, -97.87408948', 4),
(873, '50BTM', 'Bolitam Tam', '22.27659732, -97.87759872', 4),
(874, '50GRD', 'San Gerardo Tam', '22.28627138, -97.8872238', 4),
(875, '50H7X', 'Flamingo Maf', '22.2490268774475, -97.8743294640424', 4),
(876, '50ITV', 'Infonavit Tam', '22.28355387, -97.88292137', 4),
(877, '50KCH', 'Charro Tam', '22.27104561, -97.87540417', 4),
(878, '50LLR', 'Lomas Tam', '22.26776639, -97.86484383', 4),
(879, '50N0O', 'Jaguar Tam', '22.2822140185489, -97.8726301251427', 4),
(880, '50OFO', 'Faja De Oro Tam', '22.2567939, -97.86826774', 4),
(881, '50OFY', 'Flamboyanes Tam', '22.27520025, -97.87328345', 4),
(882, '50RAE', 'Rosales Tam', '22.26532705, -97.87285661', 4),
(883, '50SFV', 'Wisconsin Tam', '22.27039641, -97.86310657', 4),
(884, '50TPI', 'Unidad Modelo Tam', '22.28141311, -97.88687288', 4),
(885, '50TQP', 'Herradura Tam', '22.27217559, -97.87495864', 4),
(886, '50VS6', 'Calle A Tam', '22.2794, -97.8916', 4),
(887, '50ZDA', 'Calzada Tam', '22.28633791, -97.87724858', 4),
(888, '50ZGP', 'Guadalupe Tam', '22.25307979, -97.87277486', 4),
(889, '5012S', 'Cultural Tam', '22.2665, -97.8597', 4),
(890, '50ARO', 'Oro Tam', '22.27951986, -97.86838513', 4),
(891, '50KDB', 'Dos Bocas Tam', '22.25651816, -97.86410664', 4),
(892, '50PTB', 'Uni. Poniente Tam', '22.28213503, -97.8639651', 4),
(893, '50UDI', 'Universidad Tam', '22.26328463, -97.86055391', 4),
(894, '503RS', 'Plaza Dorada Maf', '22.26036636, -97.87520904', 4),
(895, '508OK', 'El Dorado Tam', '22.2954365075093, -97.8793837961791', 4),
(896, '50839', 'Grecia Maf', '22.28356254, -97.88071786', 4),
(897, '50BDK', 'San Luis Tam', '22.26766128, -97.84933394', 4),
(898, '50DUI', 'Dona Cecilia Tam', '22.25662566, -97.84381563', 4),
(899, '50ESO', 'Estadio Tam', '22.27288186, -97.85193996', 4),
(900, '50FEG', 'Regional Tam', '22.26621006, -97.85583496', 4),
(901, '50GRF', 'Grafer Tam', '22.26161692, -97.85037326', 4),
(902, '50KC4', 'Calle 9 Tam', '22.262937049036, -97.8503016050343', 4),
(903, '50KMA', 'Madero Tam', '22.2711918, -97.84883423', 4),
(904, '50NYR', 'Nayarit Tam', '22.26348987, -97.84704317', 4),
(905, '50PXQ', 'Monteverde Tam', '22.27038959, -97.85822334', 4),
(906, '50VNV', '20 De Noviembre Tam', '22.26165426, -97.85585709', 4),
(907, '50YVY', 'Lopez Mateos Tam', '22.25738529, -97.8523001', 4),
(908, '50ZNL', 'Sinaloa Tam', '22.2717, -97.849996', 4),
(909, '5072E', 'Sonora Tam', '22.273851096, -97.845994128', 4),
(910, '50AUN', 'Ampliacion Tam', '22.28256655, -97.84292254', 4),
(911, '50QDS', 'Oma Tam', '22.28355867, -97.86456607', 4),
(912, '50TOC', 'Cedros Tam', '22.28377189, -97.84963132', 4),
(913, '50TTJ', 'Jalisco Tam', '22.27691867, -97.85067853', 4),
(914, '50UNA', 'Unidad Nacional Tam', '22.26699538, -97.84447849', 4),
(915, '50ZZM', 'Monterrey Ii Tam', '22.27797887, -97.84651677', 4),
(916, '50IIL', 'Saltillo Ii Tam', '22.26205643, -97.8399369', 4),
(917, '50SFR', 'Honduras Tam', '22.25980101, -97.83616339', 4),
(918, '50IOY', 'Movil Tam', '22.2926, -97.877', 4),
(919, '5038G', 'Campbell Tam', '22.2243607, -97.86929', 4),
(920, '5055S', 'Calzada Blanca Maf', '22.2234478283704, -97.8877076359294', 4),
(921, '507KD', 'Plaza Altavista Maf', '22.2398917474519, -97.8694065906978', 4),
(922, '507VU', 'Petrolera Tam', '22.25189633748677, -97.86648323618914', 4),
(923, '50ALC', 'Alarcon Tam', '22.21992268, -97.86148481', 4),
(924, '50EJE', 'Ejercito Tam', '22.24909236, -97.86799411', 4),
(925, '50HDL', 'Hidalgo Tam', '22.23881899, -97.86979538', 4),
(926, '50I2L', 'Gaona Tam', '22.23332591, -97.86714941', 4),
(927, '50KHI', 'Avenida Hidalgo Tam', '22.2459905, -97.8726474', 4),
(928, '50LIG', 'Aguila Tam', '22.2430801, -97.8716188', 4),
(929, '50LRH', 'Moscu Tam', '22.22688841, -97.89475112', 4),
(930, '50LRV', 'Lauro Aguirre Tam', '22.24760479, -97.8676397', 4),
(931, '50M1V', 'Smith Tam', '22.24229727, -97.8670517', 4),
(932, '50MEV', 'Morelos Tam', '22.22005409, -97.87739097', 4),
(933, '50N0P', 'La Isla Tam', '22.22490158, -97.89339852', 4),
(934, '50QVO', 'Camelia Tam', '22.2467148, -97.8734627', 4),
(935, '50RGN', 'Alijadores Tam', '22.22970079, -97.8705262', 4),
(936, '50WAY', 'Alemanes Tam', '22.2552657, -97.86157731', 4),
(937, '50WMZ', 'Morelos Ii Tam', '22.22173616, -97.88129736', 4),
(938, '50YWJ', 'Cascajal Tam', '22.2186, -97.8651', 4),
(939, '50YZF', 'Otomi Tam', '22.23681489, -97.86621839', 4),
(940, '506QT', 'Matienzo Tam', '22.2182443, -97.860107', 4),
(941, '50CJN', 'Colon Tam', '22.2179068, -97.85725039', 4),
(942, '50KJU', 'Juarez Tam', '22.21717089, -97.85532865', 4),
(943, '50QLJ', 'Sor Juana Tam', '22.21961539, -97.85852774', 4),
(944, '50U6I', 'Plaza Covadonga Maf', '22.251928, -97.863632', 4),
(945, '504NY', 'Movil 2 Tam', '22.2926, -97.877', 4),
(946, '5049H', 'Movil 3 Tam', '22.2926, -97.877', 4),
(947, '50NS8', '21 de Marzo Maf', NULL, 2),
(948, '50K87', 'Villa Padilla Maf', NULL, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `plaza_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('admin','fs','coordinador','ati') NOT NULL DEFAULT 'fs'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id`, `nombre`, `email`, `password`, `foto`, `plaza_id`, `tipo`) VALUES
(1, 'Raul Huerta Aguilar', 'huag1994@gmail.com', '$2y$10$8G138ZGjXnRFjI2Gx6CGj.b7xan0Q31N7pC.z6lCQB3usiB1988IG', 'usuario_1_6a27cffcdc691.jpg', 1, 'admin'),
(2, 'Roberto Carlos Patiño Martinez', 'rcarlos09@hotmail.com', '$2y$10$2WVOiKswdLZTgqsUUv58u.nxsmCZtIp9pqYVFGqpsOSOMYLcsR2Yu', NULL, 1, 'coordinador'),
(3, 'Erik Aquilino Cruz Ramirez', 'erikcruz9314@gmail.com', '$2y$10$af5LhhApXVvCEPBRn.ToAe3SJXM7g1ShWegDAX1u/GGF/eXGOw36G', 'usuario_3_6a5bdd1e2d828.webp', 1, 'fs'),
(4, 'Rosa Martha Ramírez Castillo', 'rosa.ramirez@oxxo.com.mx', '$2y$10$tirf19Qt1pFsTol5h0V.GuJLFo6PW6wHiV.T9QboRrQBjftiMIfk2', 'usuario_6a27cf5643375.jpg', 1, 'ati');

--
-- Disparadores `usuario`
--
DELIMITER $$
CREATE TRIGGER `trg_crear_stock_usuario` AFTER INSERT ON `usuario` FOR EACH ROW BEGIN
    
    INSERT INTO `stock` (`tipo`, `usuario_id`, `plaza_id`, `bodega_id`)
    VALUES ('usuario', NEW.id, NEW.plaza_id, NULL);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_usuario_insert_plaza` AFTER INSERT ON `usuario` FOR EACH ROW BEGIN
    INSERT IGNORE INTO usuario_plaza (usuario_id, plaza_id)
    VALUES (NEW.id, NEW.plaza_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_usuario_update_plaza` AFTER UPDATE ON `usuario` FOR EACH ROW BEGIN
    INSERT IGNORE INTO usuario_plaza (usuario_id, plaza_id)
    VALUES (NEW.id, NEW.plaza_id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_plaza`
--

CREATE TABLE `usuario_plaza` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `plaza_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_plaza`
--

INSERT INTO `usuario_plaza` (`id`, `usuario_id`, `plaza_id`) VALUES
(23, 1, 1),
(25, 1, 4),
(24, 1, 5),
(38, 2, 1),
(39, 2, 5),
(30, 3, 1),
(31, 3, 5),
(4, 4, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activo`
--
ALTER TABLE `activo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activo_modelo` (`modelo_id`),
  ADD KEY `fk_activo_procedencia` (`procedencia_tienda_id`),
  ADD KEY `fk_activo_tienda_uso` (`tienda_uso_id`),
  ADD KEY `fk_activo_stock` (`stock_id`);

--
-- Indices de la tabla `area`
--
ALTER TABLE `area`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `area_modelo`
--
ALTER TABLE `area_modelo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_area_modelo_area` (`area_id`),
  ADD KEY `fk_area_modelo_modelo` (`modelo_id`);

--
-- Indices de la tabla `bodega`
--
ALTER TABLE `bodega`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bodega_usuario` (`usuario_id`);

--
-- Indices de la tabla `bodega_acceso_plaza`
--
ALTER TABLE `bodega_acceso_plaza`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bap_bodega_plaza` (`bodega_id`,`plaza_id`),
  ADD KEY `fk_bap_plaza` (`plaza_id`);

--
-- Indices de la tabla `dispositivo`
--
ALTER TABLE `dispositivo`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `modelo`
--
ALTER TABLE `modelo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_modelo_dispositivo` (`dispositivo_id`);

--
-- Indices de la tabla `negocio`
--
ALTER TABLE `negocio`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `plaza`
--
ALTER TABLE `plaza`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_plaza_region` (`region_id`);

--
-- Indices de la tabla `region`
--
ALTER TABLE `region`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_region_negocio` (`negocio_id`);

--
-- Indices de la tabla `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_usuario_plaza` (`usuario_id`,`plaza_id`,`tipo`),
  ADD KEY `fk_stock_bodega` (`bodega_id`),
  ADD KEY `fk_stock_plaza` (`plaza_id`);

--
-- Indices de la tabla `tienda`
--
ALTER TABLE `tienda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tienda_plaza` (`plaza_id`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_usuario_plaza` (`plaza_id`);

--
-- Indices de la tabla `usuario_plaza`
--
ALTER TABLE `usuario_plaza`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuario_plaza_usuario_plaza` (`usuario_id`,`plaza_id`),
  ADD KEY `fk_usuario_plaza_usuario` (`usuario_id`),
  ADD KEY `fk_usuario_plaza_plaza` (`plaza_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activo`
--
ALTER TABLE `activo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de la tabla `area`
--
ALTER TABLE `area`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `area_modelo`
--
ALTER TABLE `area_modelo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `bodega`
--
ALTER TABLE `bodega`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `bodega_acceso_plaza`
--
ALTER TABLE `bodega_acceso_plaza`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `dispositivo`
--
ALTER TABLE `dispositivo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `modelo`
--
ALTER TABLE `modelo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `negocio`
--
ALTER TABLE `negocio`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `plaza`
--
ALTER TABLE `plaza`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `region`
--
ALTER TABLE `region`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tienda`
--
ALTER TABLE `tienda`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=949;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuario_plaza`
--
ALTER TABLE `usuario_plaza`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `activo`
--
ALTER TABLE `activo`
  ADD CONSTRAINT `fk_activo_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `modelo` (`id`),
  ADD CONSTRAINT `fk_activo_procedencia` FOREIGN KEY (`procedencia_tienda_id`) REFERENCES `tienda` (`id`),
  ADD CONSTRAINT `fk_activo_stock` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`id`),
  ADD CONSTRAINT `fk_activo_tienda_uso` FOREIGN KEY (`tienda_uso_id`) REFERENCES `tienda` (`id`);

--
-- Filtros para la tabla `area_modelo`
--
ALTER TABLE `area_modelo`
  ADD CONSTRAINT `fk_area_modelo_area` FOREIGN KEY (`area_id`) REFERENCES `area` (`id`),
  ADD CONSTRAINT `fk_area_modelo_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `modelo` (`id`);

--
-- Filtros para la tabla `bodega`
--
ALTER TABLE `bodega`
  ADD CONSTRAINT `fk_bodega_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`);

--
-- Filtros para la tabla `bodega_acceso_plaza`
--
ALTER TABLE `bodega_acceso_plaza`
  ADD CONSTRAINT `fk_bap_bodega` FOREIGN KEY (`bodega_id`) REFERENCES `bodega` (`id`),
  ADD CONSTRAINT `fk_bap_plaza` FOREIGN KEY (`plaza_id`) REFERENCES `plaza` (`id`);

--
-- Filtros para la tabla `modelo`
--
ALTER TABLE `modelo`
  ADD CONSTRAINT `fk_modelo_dispositivo` FOREIGN KEY (`dispositivo_id`) REFERENCES `dispositivo` (`id`);

--
-- Filtros para la tabla `plaza`
--
ALTER TABLE `plaza`
  ADD CONSTRAINT `fk_plaza_region` FOREIGN KEY (`region_id`) REFERENCES `region` (`id`);

--
-- Filtros para la tabla `region`
--
ALTER TABLE `region`
  ADD CONSTRAINT `fk_region_negocio` FOREIGN KEY (`negocio_id`) REFERENCES `negocio` (`id`);

--
-- Filtros para la tabla `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_bodega` FOREIGN KEY (`bodega_id`) REFERENCES `bodega` (`id`),
  ADD CONSTRAINT `fk_stock_plaza` FOREIGN KEY (`plaza_id`) REFERENCES `plaza` (`id`),
  ADD CONSTRAINT `fk_stock_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`);

--
-- Filtros para la tabla `tienda`
--
ALTER TABLE `tienda`
  ADD CONSTRAINT `fk_tienda_plaza` FOREIGN KEY (`plaza_id`) REFERENCES `plaza` (`id`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `fk_usuario_plaza` FOREIGN KEY (`plaza_id`) REFERENCES `plaza` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
