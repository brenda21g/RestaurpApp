-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 17-11-2026 a las 22:12:16
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
-- Base de datos: `restaurante_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `rol` enum('gerente','subgerente','logistica','cocina') NOT NULL DEFAULT 'subgerente',
  `pin` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `pin_recuperacion` varchar(6) DEFAULT NULL,
  `pin_expira` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `nombre`, `email`, `rol`, `pin`, `activo`, `ultimo_login`, `pin_recuperacion`, `pin_expira`) VALUES
(1, 'Eloy', 'b0d8dc4e2b69059c760d53c7637cb44d', 'Gerente1', 'sage040621haslnla5@gmail.com', 'gerente', '81dc9bdb52d04dc20036dbd8313ed055', 1, '2026-11-17 21:05:39', NULL, NULL),
(15, 'Laca', '5ff4385bf58c47bb7ecf1cfc17364d7d', 'Lapiz Tola', 'brendaguille284@gmail.com', 'logistica', NULL, 1, '2026-11-04 18:47:11', NULL, NULL),
(17, 'Brenda', '51c75f94a84470f6ae7d4e511ea2597f', 'Brendaajska', '22151220@aguascalientes.tecnm.mx', 'subgerente', NULL, 1, '2026-11-04 18:48:09', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `icono` varchar(10) DEFAULT '?️',
  `orden` int(11) DEFAULT 0,
  `activa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `icono`, `orden`, `activa`) VALUES
(1, 'Entradas', '🥗', 1, 1),
(2, 'Platillos', '🍽️', 2, 1),
(3, 'Bebidas', '🥤', 3, 1),
(4, 'Postres', '🍰', 4, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_impuestos`
--

CREATE TABLE `configuracion_impuestos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT 'IVA',
  `porcentaje` decimal(5,2) DEFAULT 16.00,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `erp_ordenes`
--

CREATE TABLE `erp_ordenes` (
  `id` int(11) NOT NULL,
  `folio` varchar(20) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` enum('Procesada','Procesando','Cancelada','No procesado') DEFAULT 'Procesada',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_id` int(11) DEFAULT NULL,
  `origen` enum('ERP','QR') NOT NULL DEFAULT 'ERP',
  `pedido_origen_id` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `erp_ordenes`
--

INSERT INTO `erp_ordenes` (`id`, `folio`, `cliente_id`, `fecha`, `total`, `estado`, `creado_en`, `admin_id`, `origen`, `pedido_origen_id`, `observaciones`) VALUES
(1, 'QRH-1', NULL, '2026-04-29 20:42:28', 85.00, 'Procesando', '2026-04-30 02:42:28', NULL, 'QR', 1, 'Importado del historial QR. Sin movimientos retroactivos.'),
(2, 'QRH-2', NULL, '2026-04-29 20:42:49', 170.00, 'Procesando', '2026-04-30 02:42:49', NULL, 'QR', 2, 'Importado del historial QR. Sin movimientos retroactivos.'),
(3, 'QRH-3', NULL, '2026-04-29 20:44:55', 85.00, 'Procesando', '2026-04-30 02:44:55', NULL, 'QR', 3, 'Importado del historial QR. Sin movimientos retroactivos.'),
(4, 'QRH-4', NULL, '2026-08-26 01:13:50', 225.00, 'Procesada', '2026-08-26 07:13:50', NULL, 'QR', 4, 'Importado del historial QR. Sin movimientos retroactivos.'),
(5, 'QRH-5', NULL, '2026-08-26 01:23:40', 500.00, 'Procesada', '2026-08-26 07:23:40', NULL, 'QR', 5, 'Importado del historial QR. Sin movimientos retroactivos.'),
(6, 'QRH-6', NULL, '2026-08-26 01:38:10', 285.00, 'Procesada', '2026-08-26 07:38:10', NULL, 'QR', 6, 'Importado del historial QR. Sin movimientos retroactivos.'),
(7, 'QRH-7', NULL, '2026-08-26 01:44:55', 385.00, 'Procesada', '2026-08-26 07:44:55', NULL, 'QR', 7, 'Importado del historial QR. Sin movimientos retroactivos.'),
(8, 'QRH-8', NULL, '2026-08-26 18:50:56', 485.00, 'Procesada', '2026-08-27 00:50:56', NULL, 'QR', 8, 'Importado del historial QR. Sin movimientos retroactivos.'),
(9, 'QRH-9', NULL, '2026-08-26 18:52:56', 250.00, 'Procesada', '2026-08-27 00:52:56', NULL, 'QR', 9, 'Importado del historial QR. Sin movimientos retroactivos.'),
(10, 'QRH-10', NULL, '2026-08-26 18:54:09', 45.00, 'Procesada', '2026-08-27 00:54:09', NULL, 'QR', 10, 'Importado del historial QR. Sin movimientos retroactivos.'),
(11, 'QRH-11', NULL, '2026-08-27 18:30:34', 300.00, 'Procesando', '2026-08-28 00:30:34', NULL, 'QR', 11, 'Importado del historial QR. Sin movimientos retroactivos.'),
(12, 'QRH-12', NULL, '2026-08-27 18:31:16', 205.00, 'Procesando', '2026-08-28 00:31:16', NULL, 'QR', 12, 'Importado del historial QR. Sin movimientos retroactivos.'),
(16, 'OR-2026-636', 17, '2026-11-02 16:53:19', 35.00, 'Procesada', '2026-11-02 22:53:19', NULL, 'ERP', NULL, NULL),
(17, 'OR-2026-519', 20, '2026-11-02 16:55:08', 40.00, 'Procesada', '2026-11-02 22:55:08', NULL, 'ERP', NULL, NULL),
(18, 'OR-2026-322', 17, '2026-11-02 17:03:53', 30.00, 'Procesada', '2026-11-02 23:03:53', NULL, 'ERP', NULL, NULL),
(19, 'OR-2026-371', 14, '2026-11-02 17:22:09', 330.00, 'Procesada', '2026-11-02 23:22:09', NULL, 'ERP', NULL, NULL),
(20, 'OR-2026-654', 20, '2026-11-02 20:01:59', 1050.00, 'Procesada', '2026-11-03 02:01:59', NULL, 'ERP', NULL, NULL),
(21, 'OR-2026-258', 20, '2026-11-02 21:03:30', 625.00, 'Procesada', '2026-11-03 03:03:30', NULL, 'ERP', NULL, NULL),
(22, 'OR-2026-944', 20, '2026-11-02 21:21:47', 290.00, 'Procesada', '2026-11-03 03:21:47', NULL, 'ERP', NULL, NULL),
(23, 'OR-2026-821', 14, '2026-11-02 21:24:54', 45.00, 'Procesada', '2026-11-03 03:24:54', NULL, 'ERP', NULL, NULL),
(24, 'OR-2026-167', 14, '2026-11-02 21:26:37', 245.00, 'Procesada', '2026-11-03 03:26:37', NULL, 'ERP', NULL, NULL),
(25, 'OR-2026-499', 20, '2026-11-03 10:50:58', 160.00, 'Procesada', '2026-11-03 16:50:58', NULL, 'ERP', NULL, NULL),
(26, 'OR-2026-547', 20, '2026-11-03 13:04:27', 30.00, 'Procesada', '2026-11-03 19:04:27', NULL, 'ERP', NULL, NULL),
(27, 'OR-2026-582', 20, '2026-11-03 13:05:44', 55.00, 'Procesada', '2026-11-03 19:05:44', NULL, 'ERP', NULL, NULL),
(28, 'OR-2026-234', 21, '2026-11-04 12:11:30', 210.00, 'Procesada', '2026-11-04 18:11:30', NULL, 'ERP', NULL, NULL),
(29, 'OR-2026-546', 21, '2026-11-04 13:40:56', 220.00, 'Procesada', '2026-11-04 19:40:56', NULL, 'ERP', NULL, NULL),
(30, 'OR-2026-343', 20, '2026-11-04 13:44:09', 935.00, 'Procesada', '2026-11-04 19:44:09', 1, 'ERP', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `erp_ordenes_detalle`
--

CREATE TABLE `erp_ordenes_detalle` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `erp_ordenes_detalle`
--

INSERT INTO `erp_ordenes_detalle` (`id`, `orden_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 1, 85.00, 85.00),
(2, 2, 2, 1, 75.00, 75.00),
(3, 2, 3, 1, 95.00, 95.00),
(4, 3, 1, 1, 85.00, 85.00),
(5, 4, 1, 1, 85.00, 85.00),
(6, 4, 7, 1, 110.00, 110.00),
(7, 4, 10, 1, 30.00, 30.00),
(8, 5, 2, 1, 75.00, 75.00),
(9, 5, 3, 1, 95.00, 95.00),
(10, 5, 4, 1, 130.00, 130.00),
(11, 5, 8, 1, 125.00, 125.00),
(12, 5, 9, 1, 35.00, 35.00),
(13, 5, 11, 1, 40.00, 40.00),
(14, 6, 1, 1, 85.00, 85.00),
(15, 6, 2, 1, 75.00, 75.00),
(16, 6, 8, 1, 125.00, 125.00),
(17, 7, 5, 1, 120.00, 120.00),
(18, 7, 7, 1, 110.00, 110.00),
(19, 7, 12, 1, 45.00, 45.00),
(20, 7, 13, 2, 55.00, 110.00),
(21, 8, 1, 1, 85.00, 85.00),
(22, 8, 2, 1, 75.00, 75.00),
(23, 8, 3, 1, 95.00, 95.00),
(24, 8, 5, 1, 120.00, 120.00),
(25, 8, 7, 1, 110.00, 110.00),
(26, 9, 1, 1, 85.00, 85.00),
(27, 9, 3, 1, 95.00, 95.00),
(28, 9, 15, 1, 70.00, 70.00),
(29, 10, 12, 1, 45.00, 45.00),
(30, 11, 1, 1, 85.00, 85.00),
(31, 11, 7, 1, 110.00, 110.00),
(32, 11, 11, 1, 40.00, 40.00),
(33, 11, 14, 1, 65.00, 65.00),
(34, 12, 1, 1, 85.00, 85.00),
(35, 12, 3, 1, 95.00, 95.00),
(36, 12, 17, 1, 25.00, 25.00),
(64, 16, 9, 1, 35.00, 35.00),
(65, 17, 11, 1, 40.00, 40.00),
(66, 18, 10, 1, 30.00, 30.00),
(67, 19, 10, 11, 30.00, 330.00),
(68, 20, 10, 35, 30.00, 1050.00),
(69, 21, 8, 5, 125.00, 625.00),
(70, 22, 1, 1, 85.00, 85.00),
(71, 22, 3, 1, 95.00, 95.00),
(72, 22, 2, 1, 75.00, 75.00),
(73, 22, 9, 1, 35.00, 35.00),
(74, 23, 12, 1, 45.00, 45.00),
(75, 24, 3, 1, 95.00, 95.00),
(76, 24, 7, 1, 110.00, 110.00),
(77, 24, 11, 1, 40.00, 40.00),
(78, 25, 4, 1, 130.00, 130.00),
(79, 25, 10, 1, 30.00, 30.00),
(80, 26, 18, 1, 30.00, 30.00),
(81, 27, 13, 1, 55.00, 55.00),
(82, 28, 9, 6, 35.00, 210.00),
(83, 29, 13, 4, 55.00, 220.00),
(84, 30, 13, 17, 55.00, 935.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones`
--

CREATE TABLE `evaluaciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `puntuacion` int(11) DEFAULT 5,
  `tipo` varchar(50) DEFAULT 'General',
  `comentario` text DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `evaluaciones`
--

INSERT INTO `evaluaciones` (`id`, `cliente_id`, `puntuacion`, `tipo`, `comentario`, `fecha`) VALUES
(1, 14, 4, 'General', 'Muy buena, está chido', '2026-09-10 00:58:09'),
(2, 14, 1, 'Comida', 'Estaba fria', '2026-09-10 01:00:15'),
(3, 14, 5, 'Servicio', 'Muy chido', '2026-09-10 01:04:44'),
(4, 14, 5, 'Servicio', 'Super', '2026-09-10 12:47:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `interacciones`
--

CREATE TABLE `interacciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `tipo` enum('correo','llamada','reunion') NOT NULL DEFAULT 'correo',
  `asunto` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('pendiente','completada','cancelada') NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `interacciones`
--

INSERT INTO `interacciones` (`id`, `cliente_id`, `tipo`, `asunto`, `descripcion`, `fecha`, `hora`, `estado`, `creado_en`) VALUES
(1, NULL, 'llamada', 'Reclamacion por trato', 'La vida de hoy', '2026-09-12', '16:16:00', 'completada', '2026-09-03 16:13:56'),
(2, NULL, 'reunion', 'lsdkfhljshfdkl', 'sfjkdfhkjshfjlkhlkfds', '2026-09-18', '17:58:00', 'pendiente', '2026-09-03 17:56:10'),
(3, NULL, 'correo', 'gukefkegkdrj', 'dthedujrjn', '2026-09-16', '11:20:00', 'completada', '2026-09-04 08:20:37'),
(4, 20, 'correo', 'Revicion', 'Hoola', '2026-09-17', '15:30:00', 'pendiente', '2026-09-10 13:30:46'),
(5, 20, 'correo', 'Leoa', 'jhaskjdhak', '2026-09-11', '13:33:00', 'pendiente', '2026-09-10 13:31:40'),
(6, 14, 'llamada', 'trato', 'datalles', '2026-09-17', '19:40:00', 'pendiente', '2026-09-10 13:37:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `madurez_scm`
--

CREATE TABLE `madurez_scm` (
  `id` int(11) NOT NULL,
  `nivel_actual` int(11) NOT NULL DEFAULT 1,
  `descripcion_avance` text DEFAULT NULL,
  `porcentaje_implementacion` decimal(5,2) DEFAULT 0.00,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `madurez_scm`
--

INSERT INTO `madurez_scm` (`id`, `nivel_actual`, `descripcion_avance`, `porcentaje_implementacion`, `actualizado_en`) VALUES
(1, 3, 'Implementación avanzada de flujos Push/Pull y control de proveedores únicos.', 85.00, '2026-09-30 18:30:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mesas`
--

CREATE TABLE `mesas` (
  `id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `qr_token` varchar(64) NOT NULL,
  `activa` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mesas`
--

INSERT INTO `mesas` (`id`, `numero`, `nombre`, `qr_token`, `activa`, `creado_en`) VALUES
(1, 1, 'Mesa 1', 'mesa_token_001', 1, '2026-04-30 01:11:24'),
(2, 2, 'Mesa 2', 'mesa_token_002', 1, '2026-04-30 01:11:24'),
(3, 3, 'Mesa 3', 'mesa_token_003', 1, '2026-04-30 01:11:24'),
(4, 4, 'Mesa 4', 'mesa_token_004', 1, '2026-04-30 01:11:24'),
(5, 5, 'Mesa 5', 'mesa_token_005', 1, '2026-04-30 01:11:24'),
(6, 6, 'Mesa 6', 'mesa_token_006', 1, '2026-04-30 01:11:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `monedero_historial`
--

CREATE TABLE `monedero_historial` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `tipo` enum('cargo','recarga') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `saldo_resultante` decimal(10,2) NOT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `mesa_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `numero_orden` varchar(20) NOT NULL,
  `estado` enum('pendiente','preparando','listo','entregado','cancelado') DEFAULT 'pendiente',
  `total` decimal(10,2) DEFAULT 0.00,
  `tiempo_estimado` int(11) DEFAULT NULL COMMENT 'minutos',
  `notas` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `entregado_en` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `mesa_id`, `cliente_id`, `numero_orden`, `estado`, `total`, `tiempo_estimado`, `notas`, `creado_en`, `actualizado_en`, `entregado_en`) VALUES
(1, 1, NULL, 'ORD-A40B58', 'pendiente', 85.00, NULL, '', '2026-04-30 02:42:28', '2026-04-30 02:42:28', NULL),
(2, 1, NULL, 'ORD-032FCF', 'pendiente', 170.00, NULL, '', '2026-04-30 02:42:49', '2026-04-30 02:42:49', NULL),
(3, 1, NULL, 'ORD-7EA18C', 'pendiente', 85.00, NULL, '', '2026-04-30 02:44:55', '2026-04-30 02:44:55', NULL),
(4, 1, NULL, 'ORD-FEF628', 'entregado', 225.00, 20, '', '2026-08-26 07:13:50', '2026-08-26 07:28:23', '2026-08-26 07:28:23'),
(5, 2, NULL, 'ORD-A28447', 'entregado', 500.00, 25, '', '2026-08-26 07:23:40', '2026-08-26 07:28:33', '2026-08-26 07:28:33'),
(6, 2, NULL, 'ORD-CF2B88', 'entregado', 285.00, 20, '', '2026-08-26 07:38:10', '2026-08-26 07:38:40', '2026-08-26 07:38:40'),
(7, 1, NULL, 'ORD-C7CD21', 'entregado', 385.00, 24, '', '2026-08-26 07:44:55', '2026-08-26 07:45:28', '2026-08-26 07:45:28'),
(8, 1, NULL, 'ORD-EC435E', 'entregado', 485.00, 20, '', '2026-08-27 00:50:56', '2026-08-27 00:52:29', '2026-08-27 00:52:29'),
(9, 1, NULL, 'ORD-DBAC20', 'entregado', 250.00, 19, 'Chile mucho chile', '2026-08-27 00:52:56', '2026-08-27 00:53:28', '2026-08-27 00:53:28'),
(10, 1, NULL, 'ORD-B752D0', 'entregado', 45.00, 5, '', '2026-08-27 00:54:09', '2026-08-27 00:55:37', '2026-08-27 00:55:37'),
(11, 1, NULL, 'ORD-39C31A', 'pendiente', 300.00, NULL, 'Prueba', '2026-08-28 00:30:34', '2026-08-28 00:30:34', NULL),
(12, 2, NULL, 'ORD-5FCF20', 'pendiente', 205.00, NULL, '', '2026-08-28 00:31:16', '2026-08-28 00:31:16', NULL),
(13, 1, NULL, 'OR-2026-343', 'entregado', 935.00, NULL, NULL, '2026-11-04 19:44:09', '2026-11-04 19:44:09', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_items`
--

CREATE TABLE `pedido_items` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notas` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedido_items`
--

INSERT INTO `pedido_items` (`id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`, `notas`) VALUES
(1, 1, 1, 1, 85.00, 85.00, ''),
(2, 2, 3, 1, 95.00, 95.00, ''),
(3, 2, 2, 1, 75.00, 75.00, ''),
(4, 3, 1, 1, 85.00, 85.00, ''),
(5, 4, 1, 1, 85.00, 85.00, ''),
(6, 4, 7, 1, 110.00, 110.00, ''),
(7, 4, 10, 1, 30.00, 30.00, ''),
(8, 5, 3, 1, 95.00, 95.00, ''),
(9, 5, 2, 1, 75.00, 75.00, ''),
(10, 5, 8, 1, 125.00, 125.00, ''),
(11, 5, 4, 1, 130.00, 130.00, ''),
(12, 5, 9, 1, 35.00, 35.00, ''),
(13, 5, 11, 1, 40.00, 40.00, ''),
(14, 6, 1, 1, 85.00, 85.00, ''),
(15, 6, 2, 1, 75.00, 75.00, ''),
(16, 6, 8, 1, 125.00, 125.00, ''),
(17, 7, 5, 1, 120.00, 120.00, ''),
(18, 7, 7, 1, 110.00, 110.00, ''),
(19, 7, 13, 2, 55.00, 110.00, ''),
(20, 7, 12, 1, 45.00, 45.00, ''),
(21, 8, 1, 1, 85.00, 85.00, ''),
(22, 8, 3, 1, 95.00, 95.00, ''),
(23, 8, 2, 1, 75.00, 75.00, ''),
(24, 8, 5, 1, 120.00, 120.00, ''),
(25, 8, 7, 1, 110.00, 110.00, ''),
(26, 9, 1, 1, 85.00, 85.00, ''),
(27, 9, 3, 1, 95.00, 95.00, ''),
(28, 9, 15, 1, 70.00, 70.00, ''),
(29, 10, 12, 1, 45.00, 45.00, ''),
(30, 11, 1, 1, 85.00, 85.00, ''),
(31, 11, 7, 1, 110.00, 110.00, ''),
(32, 11, 11, 1, 40.00, 40.00, ''),
(33, 11, 14, 1, 65.00, 65.00, ''),
(34, 12, 1, 1, 85.00, 85.00, ''),
(35, 12, 3, 1, 95.00, 95.00, ''),
(36, 12, 17, 1, 25.00, 25.00, ''),
(37, 13, 13, 17, 55.00, 935.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `receta_confirmada` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `categoria_id`, `nombre`, `descripcion`, `precio`, `imagen`, `disponible`, `creado_en`, `receta_confirmada`) VALUES
(1, 1, 'Guacamole con totopos', 'Aguacate fresco, jitomate, cebolla, cilantro', 85.00, NULL, 1, '2026-04-30 01:11:24', 0),
(2, 1, 'Sopa de tortilla', 'Caldo de tomate, tiras de tortilla, crema, queso', 75.00, NULL, 1, '2026-04-30 01:11:24', 0),
(3, 1, 'Queso fundido', 'Queso Oaxaca gratinado con chorizo', 95.00, NULL, 1, '2026-04-30 01:11:24', 0),
(4, 2, 'Tacos de carne asada', '3 tacos con carne asada, salsa y guarnición', 130.00, NULL, 1, '2026-04-30 01:11:24', 0),
(5, 2, 'Enchiladas verdes', '3 enchiladas con pollo, salsa verde, crema', 120.00, NULL, 1, '2026-04-30 01:11:24', 0),
(6, 2, 'Pollo a la plancha', 'Pechuga a la plancha con arroz y frijoles', 145.00, NULL, 1, '2026-04-30 01:11:24', 0),
(7, 2, 'Pasta alfredo', 'Pasta con crema, queso parmesano y champiñones', 110.00, NULL, 1, '2026-04-30 01:11:24', 0),
(8, 2, 'Hamburguesa clásica', 'Carne de res, lechuga, tomate, cebolla, papas', 125.00, NULL, 1, '2026-04-30 01:11:24', 0),
(9, 3, 'Agua fresca', 'Jamaica, horchata o tamarindo', 35.00, NULL, 1, '2026-04-30 01:11:24', 0),
(10, 3, 'Coca-Cola', 'Coca-Cola de 600 ml', 30.00, NULL, 1, '2026-04-30 01:11:24', 0),
(11, 3, 'Café americano', 'Café de olla o americano', 40.00, NULL, 1, '2026-04-30 01:11:24', 0),
(12, 3, 'Jugo natural', 'Naranja, zanahoria o betabel', 45.00, NULL, 1, '2026-04-30 01:11:24', 0),
(13, 3, 'Cerveza', 'Corona, Modelo o Pacifico', 55.00, NULL, 1, '2026-04-30 01:11:24', 0),
(14, 4, 'Flan napolitano', 'Flan casero con cajeta', 65.00, NULL, 1, '2026-04-30 01:11:24', 0),
(15, 4, 'Pay de queso', 'Pay de queso con frutos rojos', 70.00, NULL, 1, '2026-04-30 01:11:24', 0),
(16, 4, 'Brownie con helado', 'Brownie de chocolate con helado de vainilla', 80.00, NULL, 1, '2026-04-30 01:11:24', 0),
(17, 2, 'Tacos de Guisado', 'Tacos de diferentes guisados (Chicharron rojo, Chicharron verde, Rajas con queso, Camaron, Caviar, Sushi)', 25.00, NULL, 1, '2026-08-26 07:48:02', 0),
(18, 3, 'Refresco Sprite 600ml', 'Botella individual de refresco sabor lima-limón', 30.00, NULL, 1, '2026-11-02 23:19:37', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_ingredientes`
--

CREATE TABLE `producto_ingredientes` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL COMMENT 'Platillo del menú (ej. Guacamole, Tacos)',
  `scm_producto_id` int(11) NOT NULL COMMENT 'Materia prima en logística (ej. Jitomate, Cebolla)',
  `cantidad_requerida` decimal(12,3) NOT NULL DEFAULT 1.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `producto_ingredientes`
--

INSERT INTO `producto_ingredientes` (`id`, `producto_id`, `scm_producto_id`, `cantidad_requerida`) VALUES
(1, 1, 9, 1.000),
(2, 2, 10, 0.300),
(3, 2, 7, 0.100),
(4, 2, 11, 0.050),
(5, 3, 8, 0.500),
(6, 3, 2, 0.100),
(7, 4, 5, 0.300),
(8, 4, 7, 3.000),
(9, 5, 12, 0.250),
(10, 5, 7, 3.000),
(11, 5, 13, 0.150),
(12, 6, 12, 0.350),
(13, 7, 14, 0.200),
(14, 7, 15, 0.150),
(15, 8, 6, 1.000),
(16, 8, 16, 0.200),
(17, 9, 17, 0.100),
(18, 10, 3, 1.000),
(19, 11, 18, 0.020),
(20, 12, 17, 0.200),
(21, 13, 19, 1.000),
(22, 14, 20, 0.100),
(23, 15, 20, 0.120),
(24, 16, 20, 0.150),
(25, 17, 7, 2.000),
(26, 18, 4, 1.000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre`, `contacto`, `correo`, `telefono`, `direccion`, `creado_en`, `activo`) VALUES
(1, 'Central de Abastos Verel S.A.', 'Carlos Ruiz', 'contacto@verel.com', '4491234567', 'Av. Central #100, Aguascalientes', '2026-09-21 16:57:03', 1),
(2, 'Bebidas del Centro (Coca-Cola / Fanta)', 'Ana Torres', 'ventas@bebidascentro.com', '4499876543', 'Zona Industrial, Aguascalientes', '2026-09-21 16:57:03', 1),
(3, 'Carnes y Derivados San Marcos', 'Miguel Ángel Garza', 'pedidos@carnesfamosa.com', '4495551234', 'Blvd. San Marcos, Aguascalientes', '2026-09-21 16:57:03', 1),
(4, 'Abarrotes y Panificadora La Esperanza', 'Lucía Méndez', 'laesperanza@abarrotes.com', '4497778900', 'Av. Universidad #400, Aguascalientes', '2026-09-21 16:57:03', 1),
(5, 'Frutas y verduras Don pancho', 'Francisco Perez', 'franciscoperez@gmail.com', '4499486547', 'mi casa #17', '2026-09-21 19:16:52', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `scm_movimientos`
--

CREATE TABLE `scm_movimientos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `tipo` enum('Entrada','Salida') NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `fecha` date NOT NULL,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Administrador que registra',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `erp_orden_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `scm_movimientos`
--

INSERT INTO `scm_movimientos` (`id`, `producto_id`, `tipo`, `cantidad`, `motivo`, `fecha`, `usuario_id`, `creado_en`, `erp_orden_id`) VALUES
(1, 1, 'Salida', 1.000, 'Preparacion del dia', '2026-09-21', 1, '2026-09-21 19:07:58', NULL),
(2, 1, 'Salida', 5.000, 'Venta de nuevo', '2026-09-21', NULL, '2026-09-21 19:11:30', NULL),
(3, 1, 'Entrada', 25.000, 'Se vendio', '2026-09-21', NULL, '2026-09-21 19:12:55', NULL),
(4, 1, 'Salida', 50.000, 'Se vendio, ahora si', '2026-09-21', NULL, '2026-09-21 19:13:27', NULL),
(5, 1, 'Salida', 11.000, 'Me la comi', '2026-09-21', NULL, '2026-09-21 19:18:20', NULL),
(6, 1, 'Entrada', 50.000, 'ya lo repuse', '2026-09-21', NULL, '2026-09-21 19:18:47', NULL),
(7, 5, 'Entrada', 11.000, 'me la comi', '2026-09-21', NULL, '2026-09-21 19:19:04', NULL),
(8, 5, 'Salida', 28.000, 'mkmkmk', '2026-09-21', NULL, '2026-09-21 19:23:50', NULL),
(9, 2, 'Entrada', 2.000, 'Recepción pedido ORD-SCM-A4F378', '2026-09-30', 1, '2026-09-30 18:01:39', NULL),
(10, 1, 'Entrada', 1.000, 'Automatización PUSH ORD-SCM-8B9CE3', '2026-10-01', 1, '2026-10-01 06:59:10', NULL),
(11, 1, 'Entrada', 15.000, 'Automatización PUSH ORD-SCM-48A058', '2026-10-01', 1, '2026-10-01 06:59:10', NULL),
(12, 2, 'Entrada', 2.000, 'Automatización PUSH ORD-SCM-A4F378', '2026-10-01', 1, '2026-10-01 06:59:10', NULL),
(13, 6, 'Salida', 5.000, 'Merma de caducidad', '2026-10-01', 1, '2026-10-01 07:00:02', NULL),
(14, 6, 'Salida', 10.000, 'Comida', '2026-10-01', 1, '2026-10-01 07:00:43', NULL),
(15, 6, 'Entrada', 9.000, 'Recepción Automática ORD-PUSH-B2277E', '2026-10-01', 1, '2026-10-01 07:07:54', NULL),
(16, 5, 'Entrada', 15.000, 'Recepción Manual Pull ORD-PULL-C82DD5', '2026-10-01', 1, '2026-10-01 07:19:45', NULL),
(17, 5, 'Salida', 17.000, 'Me la robe', '2026-10-01', 1, '2026-10-01 18:53:43', NULL),
(18, 2, 'Salida', 27.000, 'Las vendi', '2026-10-01', 1, '2026-10-01 18:56:16', NULL),
(19, 2, 'Entrada', 14.000, 'Recepción Automática ORD-PUSH-3FF3A3', '2026-10-01', 1, '2026-10-01 18:56:56', NULL),
(20, 5, 'Entrada', 15.000, 'Ya compre', '2026-10-01', 1, '2026-10-01 18:57:42', NULL),
(21, 2, 'Salida', 15.000, 'ajshdkjbasd', '2026-10-01', 1, '2026-10-01 19:38:55', NULL),
(22, 2, 'Entrada', 15.000, 'Recepción Automática ORD-PUSH-13D4F1', '2026-10-01', 1, '2026-10-01 19:39:24', NULL),
(23, 3, 'Salida', 55.000, 'asjnhdkajsd', '2026-10-01', 1, '2026-10-01 19:40:03', NULL),
(24, 3, 'Entrada', 25.000, 'Recepción Manual Pull ORD-PULL-47F4D5', '2026-10-01', 1, '2026-10-01 19:40:28', NULL),
(25, 3, 'Salida', 11.000, 'Venta Directa SCM/ERP (OR-2026-371): Coca-Cola', '2026-11-02', NULL, '2026-11-02 23:22:09', NULL),
(26, 3, 'Entrada', 10.000, 'Recepción Manual Pull ORD-PULL-1BC9A3', '2026-11-02', 1, '2026-11-02 23:23:01', NULL),
(27, 3, 'Salida', 35.000, 'Venta Directa SCM/ERP (OR-2026-654): Coca-Cola', '2026-11-02', NULL, '2026-11-03 02:01:59', NULL),
(28, 3, 'Entrada', 45.000, 'Restablecimiento', '2026-11-02', 1, '2026-11-03 02:04:44', NULL),
(29, 9, 'Salida', 1.000, 'Venta Platillo ERP: OR-2026-944', '2026-11-02', NULL, '2026-11-03 03:21:47', 22),
(30, 2, 'Salida', 0.100, 'Venta Platillo ERP: OR-2026-944', '2026-11-02', NULL, '2026-11-03 03:21:47', 22),
(31, 8, 'Salida', 0.500, 'Venta Platillo ERP: OR-2026-944', '2026-11-02', NULL, '2026-11-03 03:21:47', 22),
(32, 7, 'Salida', 0.100, 'Venta Platillo ERP: OR-2026-944', '2026-11-02', NULL, '2026-11-03 03:21:47', 22),
(33, 10, 'Salida', 0.300, 'Venta Platillo ERP: OR-2026-944', '2026-11-02', NULL, '2026-11-03 03:21:47', 22),
(34, 11, 'Salida', 0.050, 'Venta Platillo ERP: OR-2026-944', '2026-11-02', NULL, '2026-11-03 03:21:47', 22),
(35, 17, 'Salida', 0.100, 'Venta Platillo ERP: OR-2026-944', '2026-11-02', NULL, '2026-11-03 03:21:47', 22),
(36, 17, 'Salida', 0.200, 'Venta Platillo ERP: OR-2026-821', '2026-11-02', NULL, '2026-11-03 03:24:54', 23),
(37, 2, 'Salida', 0.100, 'Venta Platillo ERP: OR-2026-167', '2026-11-02', NULL, '2026-11-03 03:26:38', 24),
(38, 8, 'Salida', 0.500, 'Venta Platillo ERP: OR-2026-167', '2026-11-02', NULL, '2026-11-03 03:26:38', 24),
(39, 14, 'Salida', 0.200, 'Venta Platillo ERP: OR-2026-167', '2026-11-02', NULL, '2026-11-03 03:26:38', 24),
(40, 15, 'Salida', 0.150, 'Venta Platillo ERP: OR-2026-167', '2026-11-02', NULL, '2026-11-03 03:26:38', 24),
(41, 18, 'Salida', 0.020, 'Venta Platillo ERP: OR-2026-167', '2026-11-02', NULL, '2026-11-03 03:26:38', 24),
(42, 5, 'Salida', 0.300, 'Venta Platillo ERP: OR-2026-499', '2026-11-03', NULL, '2026-11-03 16:50:58', 25),
(43, 7, 'Salida', 3.000, 'Venta Platillo ERP: OR-2026-499', '2026-11-03', NULL, '2026-11-03 16:50:58', 25),
(44, 3, 'Salida', 1.000, 'Venta Platillo ERP: OR-2026-499', '2026-11-03', NULL, '2026-11-03 16:50:58', 25),
(45, 4, 'Salida', 1.000, 'Venta Subgerencia: OR-2026-547', '2026-11-03', NULL, '2026-11-03 19:04:27', 26),
(46, 19, 'Salida', 1.000, 'Venta Subgerencia: OR-2026-582', '2026-11-03', NULL, '2026-11-03 19:05:44', 27),
(47, 17, 'Salida', 0.600, 'Venta Platillo ERP: OR-2026-234', '2026-11-04', NULL, '2026-11-04 18:11:30', 28),
(48, 19, 'Salida', 4.000, 'Venta Platillo ERP: OR-2026-546', '2026-11-04', NULL, '2026-11-04 19:40:56', 29),
(49, 19, 'Salida', 17.000, 'Venta Orden OR-2026-343', '2026-11-04', NULL, '2026-11-04 19:44:09', 30);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `scm_pedidos`
--

CREATE TABLE `scm_pedidos` (
  `id` int(11) NOT NULL,
  `numero_orden_scm` varchar(20) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `tipo` varchar(50) DEFAULT 'Reposición',
  `estado` enum('pendiente','en_proceso','recibido','cancelado') DEFAULT 'pendiente',
  `fecha` date NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `scm_pedidos`
--

INSERT INTO `scm_pedidos` (`id`, `numero_orden_scm`, `producto_id`, `proveedor_id`, `cantidad`, `tipo`, `estado`, `fecha`, `creado_en`) VALUES
(1, 'ORD-SCM-8B9CE3', 1, 1, 1.000, 'Reposición Manual (Pull)', 'recibido', '2026-09-21', '2026-09-21 19:08:40'),
(2, 'ORD-SCM-48A058', 1, 1, 15.000, 'Reposición Manual (Pull)', 'recibido', '2026-09-20', '2026-09-21 19:15:16'),
(3, 'ORD-SCM-A4F378', 2, 1, 2.000, 'Reposición Manual (Pull)', 'recibido', '2026-09-18', '2026-09-21 19:17:46'),
(4, 'ORD-PUSH-B2277E', 6, 4, 9.000, 'Reposición Automática (Push)', 'recibido', '2026-10-01', '2026-10-01 07:07:28'),
(5, 'ORD-PULL-C82DD5', 5, 3, 15.000, 'Reposición Manual (Pull)', 'recibido', '2026-10-01', '2026-10-01 07:19:40'),
(6, 'ORD-PUSH-3FF3A3', 2, 1, 14.000, 'Reposición Automática (Push)', 'recibido', '2026-10-01', '2026-10-01 18:56:19'),
(7, 'ORD-PUSH-13D4F1', 2, 1, 15.000, 'Reposición Automática (Push)', 'recibido', '2026-10-01', '2026-10-01 19:38:59'),
(8, 'ORD-PULL-47F4D5', 3, 2, 25.000, 'Reposición Manual (Pull)', 'recibido', '2026-10-01', '2026-10-01 19:40:20'),
(9, 'ORD-PULL-1BC9A3', 3, 2, 10.000, 'Reposición Manual (Pull)', 'recibido', '2026-11-02', '2026-11-02 23:22:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `scm_productos`
--

CREATE TABLE `scm_productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `stock_actual` decimal(12,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` decimal(12,3) NOT NULL DEFAULT 5.000,
  `estrategia_logistica` enum('PUSH','PULL') DEFAULT 'PUSH',
  `proveedor_id` int(11) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT 0.00,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `unidad_medida` varchar(20) NOT NULL DEFAULT 'unidad',
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `scm_productos`
--

INSERT INTO `scm_productos` (`id`, `nombre`, `descripcion`, `stock_actual`, `stock_minimo`, `estrategia_logistica`, `proveedor_id`, `precio`, `creado_en`, `unidad_medida`, `activo`) VALUES
(1, 'Jitomate Saladette', 'Verdura fresca por kilo para salsas y guarniciones', 59.000, 10.000, 'PULL', 1, 28.50, '2026-09-21 16:57:03', 'unidad', 1),
(2, 'Cebolla Blanca', 'Insumo diario de verdura para cocina y tacos', 15.800, 8.000, 'PUSH', 1, 22.00, '2026-09-21 16:57:03', 'unidad', 1),
(3, 'Refresco Coca-Cola 600ml', 'Botella individual de refresco', 38.000, 20.000, 'PULL', 2, 18.00, '2026-09-21 16:57:03', 'unidad', 1),
(4, 'Refresco Sprite 600ml', 'Bebida embotellada sabor lima-limón', 39.000, 15.000, 'PULL', 2, 18.00, '2026-09-21 16:57:03', 'unidad', 1),
(5, 'Carne Arrachera / Bistec', 'Corte seleccionado para platillos principales', 15.700, 10.000, 'PULL', 3, 140.00, '2026-09-21 16:57:03', 'unidad', 1),
(6, 'Pan para Hamburguesa', 'Paquete con 10 piezas', 12.000, 6.000, 'PUSH', 4, 45.00, '2026-09-21 16:57:03', 'unidad', 1),
(7, 'Tortillas de Maíz', 'Kilo de tortilla fresca', 196.900, 30.000, 'PULL', 4, 22.00, '2026-11-03 03:11:13', 'unidad', 1),
(8, 'Queso Oaxaca / Asadero', 'Queso para fundir y antojitos', 29.000, 5.000, 'PUSH', 3, 110.00, '2026-11-03 03:11:13', 'unidad', 1),
(9, 'Aguacate /  Jitomate (Guacamole)', 'Insumos frescos para guacamole', 39.000, 10.000, 'PULL', 5, 35.00, '2026-11-03 03:11:13', 'unidad', 1),
(10, 'Caldo de Tomate / Base Sopa', 'Base líquida para sopas y cremas', 49.700, 10.000, 'PUSH', 1, 15.00, '2026-11-03 03:15:37', 'litros', 1),
(11, 'Crema y Queso para Sopa', 'Lácteos para decoración y sabor', 24.950, 5.000, 'PUSH', 3, 45.00, '2026-11-03 03:15:37', 'kg', 1),
(12, 'Pollo Deshebrado / Pechuga', 'Carne de ave para enchiladas y platos fuertes', 40.000, 10.000, 'PULL', 3, 95.00, '2026-11-03 03:15:37', 'kg', 1),
(13, 'Salsa Verde / Salsa Roja', 'Salsas preparadas para antojitos', 35.000, 8.000, 'PUSH', 1, 20.00, '2026-11-03 03:15:37', 'litros', 1),
(14, 'Pasta Spaghetti', 'Pasta para preparaciones italianas', 29.800, 5.000, 'PUSH', 4, 25.00, '2026-11-03 03:15:37', 'kg', 1),
(15, 'Champiñones y Crema Alfredo', 'Insumos para pasta alfredo', 19.850, 5.000, 'PUSH', 1, 50.00, '2026-11-03 03:15:37', 'kg', 1),
(16, 'Carne molida / Res para Hamburguesa', 'Carne seleccionada', 40.000, 10.000, 'PULL', 3, 110.00, '2026-11-03 03:15:37', 'kg', 1),
(17, 'Fruta Natural (Agua / Jugos)', 'Frutas de temporada para bebidas', 49.100, 15.000, 'PULL', 5, 25.00, '2026-11-03 03:15:37', 'kg', 1),
(18, 'Café Grano / Americano', 'Insumo de café', 14.980, 4.000, 'PUSH', 4, 120.00, '2026-11-03 03:15:37', 'kg', 1),
(19, 'Cerveza Botella (Almacén)', 'Cartón de cervezas', 98.000, 20.000, 'PULL', 2, 35.00, '2026-11-03 03:15:37', 'unidad', 1),
(20, 'Insumos de Repostería (Flan/Pay/Brownie)', 'Ingredientes base postres', 25.000, 5.000, 'PUSH', 4, 40.00, '2026-11-03 03:15:37', 'kg', 1),
(21, 'Refrescos y Bebidas Extras (Sprite/Coca)', 'Inventario general de refrescos', 100.000, 20.000, 'PULL', 2, 15.00, '2026-11-03 03:15:37', 'unidad', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets_soporte`
--

CREATE TABLE `tickets_soporte` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `asunto` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` varchar(50) DEFAULT 'En proceso',
  `creado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_cliente`
--

CREATE TABLE `usuarios_cliente` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(32) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `puntos` int(11) DEFAULT 0,
  `token_verificacion` varchar(100) DEFAULT NULL,
  `email_confirmado` tinyint(1) DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `etapa_crm` varchar(50) DEFAULT 'Prospecto',
  `estado` varchar(20) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios_cliente`
--

INSERT INTO `usuarios_cliente` (`id`, `nombre`, `email`, `password`, `telefono`, `puntos`, `token_verificacion`, `email_confirmado`, `creado_en`, `etapa_crm`, `estado`) VALUES
(14, 'Brenda Guillen', '22151246@aguascalientes.tecnm.mx', 'd6ff269bd9dbc6b7f612c2b254a45045', '4495651176', 0, NULL, 1, '2026-09-01 19:37:05', 'Prospecto', 'Activo'),
(17, 'Eloy', 'juanyto383@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '4498765432', 0, NULL, 1, '2026-09-08 22:45:38', 'Prospecto', 'Baja'),
(18, 'Eloy', 'juanyto38354@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '4498765432', 0, NULL, 1, '2026-09-08 22:48:18', 'Prospecto', 'Baja'),
(20, 'Prueba2', 'vane.tejeda@aguascalientes.tecnm.mx', '827ccb0eea8a706c4c34a16891f84e7b', '', 0, NULL, 1, '2026-09-10 19:22:57', 'Prospecto', 'Activo'),
(21, 'Pedro', 'hdajkhask@nsjlkan.com', '$2y$10$ly3u9D.vq3Gcv031pc12.OGVn', '226366378783', 0, NULL, 1, '2026-11-04 18:11:11', 'Cliente', 'Activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion_impuestos`
--
ALTER TABLE `configuracion_impuestos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `erp_ordenes`
--
ALTER TABLE `erp_ordenes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folio` (`folio`),
  ADD UNIQUE KEY `uq_erp_pedido_origen` (`pedido_origen_id`),
  ADD KEY `fk_erp_orden_cliente` (`cliente_id`);

--
-- Indices de la tabla `erp_ordenes_detalle`
--
ALTER TABLE `erp_ordenes_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detalle_orden` (`orden_id`),
  ADD KEY `fk_detalle_producto` (`producto_id`);

--
-- Indices de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_evaluaciones_cliente` (`cliente_id`);

--
-- Indices de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_interaccion_cliente` (`cliente_id`);

--
-- Indices de la tabla `madurez_scm`
--
ALTER TABLE `madurez_scm`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD UNIQUE KEY `qr_token` (`qr_token`);

--
-- Indices de la tabla `monedero_historial`
--
ALTER TABLE `monedero_historial`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_orden` (`numero_orden`),
  ADD KEY `mesa_id` (`mesa_id`),
  ADD KEY `fk_pedidos_cliente` (`cliente_id`);

--
-- Indices de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Indices de la tabla `producto_ingredientes`
--
ALTER TABLE `producto_ingredientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_receta_producto_insumo` (`producto_id`,`scm_producto_id`),
  ADD KEY `scm_producto_id` (`scm_producto_id`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `scm_movimientos`
--
ALTER TABLE `scm_movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_movimiento_scm_prod` (`producto_id`),
  ADD KEY `fk_movimiento_admin` (`usuario_id`),
  ADD KEY `idx_movimiento_erp` (`erp_orden_id`);

--
-- Indices de la tabla `scm_pedidos`
--
ALTER TABLE `scm_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_orden_scm` (`numero_orden_scm`),
  ADD KEY `fk_scm_pedido_prod` (`producto_id`),
  ADD KEY `fk_scm_pedido_prov` (`proveedor_id`);

--
-- Indices de la tabla `scm_productos`
--
ALTER TABLE `scm_productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_scm_prod_proveedor` (`proveedor_id`);

--
-- Indices de la tabla `tickets_soporte`
--
ALTER TABLE `tickets_soporte`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios_cliente`
--
ALTER TABLE `usuarios_cliente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `configuracion_impuestos`
--
ALTER TABLE `configuracion_impuestos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `erp_ordenes`
--
ALTER TABLE `erp_ordenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `erp_ordenes_detalle`
--
ALTER TABLE `erp_ordenes_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `madurez_scm`
--
ALTER TABLE `madurez_scm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `monedero_historial`
--
ALTER TABLE `monedero_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `producto_ingredientes`
--
ALTER TABLE `producto_ingredientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `scm_movimientos`
--
ALTER TABLE `scm_movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT de la tabla `scm_pedidos`
--
ALTER TABLE `scm_pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `scm_productos`
--
ALTER TABLE `scm_productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `tickets_soporte`
--
ALTER TABLE `tickets_soporte`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios_cliente`
--
ALTER TABLE `usuarios_cliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `erp_ordenes`
--
ALTER TABLE `erp_ordenes`
  ADD CONSTRAINT `fk_erp_orden_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios_cliente` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `erp_ordenes_detalle`
--
ALTER TABLE `erp_ordenes_detalle`
  ADD CONSTRAINT `fk_detalle_orden` FOREIGN KEY (`orden_id`) REFERENCES `erp_ordenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD CONSTRAINT `fk_evaluaciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios_cliente` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `interacciones`
--
ALTER TABLE `interacciones`
  ADD CONSTRAINT `fk_interaccion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios_cliente` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios_cliente` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pedidos_mesa` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`);

--
-- Filtros para la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD CONSTRAINT `fk_items_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `producto_ingredientes`
--
ALTER TABLE `producto_ingredientes`
  ADD CONSTRAINT `producto_ingredientes_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `producto_ingredientes_ibfk_2` FOREIGN KEY (`scm_producto_id`) REFERENCES `scm_productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `scm_movimientos`
--
ALTER TABLE `scm_movimientos`
  ADD CONSTRAINT `fk_movimiento_admin` FOREIGN KEY (`usuario_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movimiento_scm_prod` FOREIGN KEY (`producto_id`) REFERENCES `scm_productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scm_mov_erp` FOREIGN KEY (`erp_orden_id`) REFERENCES `erp_ordenes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `scm_pedidos`
--
ALTER TABLE `scm_pedidos`
  ADD CONSTRAINT `fk_scm_pedido_prod` FOREIGN KEY (`producto_id`) REFERENCES `scm_productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scm_pedido_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `scm_productos`
--
ALTER TABLE `scm_productos`
  ADD CONSTRAINT `fk_scm_prod_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
