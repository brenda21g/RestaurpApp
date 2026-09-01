-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-09-2026 a las 05:34:42
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
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `nombre`, `activo`, `ultimo_login`) VALUES
(4, 'admin1', 'e00cf25ad42683b3df678c61f42c6bda', 'Administrador 1', 1, '2026-09-01 02:31:24');

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
(1, 1, 'Mesa 1', 'mesa_token_001_abc123', 1, '2026-04-30 01:11:24'),
(2, 2, 'Mesa 2', 'mesa_token_002_def456', 1, '2026-04-30 01:11:24'),
(3, 3, 'Mesa 3', 'mesa_token_003_ghi789', 1, '2026-04-30 01:11:24'),
(4, 4, 'Mesa 4', 'mesa_token_004_jkl012', 1, '2026-04-30 01:11:24'),
(5, 5, 'Mesa 5', 'mesa_token_005_mno345', 1, '2026-04-30 01:11:24'),
(6, 6, 'Mesa 6', 'mesa_token_006_pqr678', 1, '2026-04-30 01:11:24');

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
(12, 2, NULL, 'ORD-5FCF20', 'pendiente', 205.00, NULL, '', '2026-08-28 00:31:16', '2026-08-28 00:31:16', NULL);

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
(36, 12, 17, 1, 25.00, 25.00, '');

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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `categoria_id`, `nombre`, `descripcion`, `precio`, `imagen`, `disponible`, `creado_en`) VALUES
(1, 1, 'Guacamole con totopos', 'Aguacate fresco, jitomate, cebolla, cilantro', 85.00, NULL, 1, '2026-04-30 01:11:24'),
(2, 1, 'Sopa de tortilla', 'Caldo de tomate, tiras de tortilla, crema, queso', 75.00, NULL, 1, '2026-04-30 01:11:24'),
(3, 1, 'Queso fundido', 'Queso Oaxaca gratinado con chorizo', 95.00, NULL, 1, '2026-04-30 01:11:24'),
(4, 2, 'Tacos de carne asada', '3 tacos con carne asada, salsa y guarnición', 130.00, NULL, 1, '2026-04-30 01:11:24'),
(5, 2, 'Enchiladas verdes', '3 enchiladas con pollo, salsa verde, crema', 120.00, NULL, 1, '2026-04-30 01:11:24'),
(6, 2, 'Pollo a la plancha', 'Pechuga a la plancha con arroz y frijoles', 145.00, NULL, 1, '2026-04-30 01:11:24'),
(7, 2, 'Pasta alfredo', 'Pasta con crema, queso parmesano y champiñones', 110.00, NULL, 1, '2026-04-30 01:11:24'),
(8, 2, 'Hamburguesa clásica', 'Carne de res, lechuga, tomate, cebolla, papas', 125.00, NULL, 1, '2026-04-30 01:11:24'),
(9, 3, 'Agua fresca', 'Jamaica, horchata o tamarindo', 35.00, NULL, 1, '2026-04-30 01:11:24'),
(10, 3, 'Refresco', 'Coca-Cola, Sprite o Fanta', 30.00, NULL, 1, '2026-04-30 01:11:24'),
(11, 3, 'Café americano', 'Café de olla o americano', 40.00, NULL, 1, '2026-04-30 01:11:24'),
(12, 3, 'Jugo natural', 'Naranja, zanahoria o betabel', 45.00, NULL, 1, '2026-04-30 01:11:24'),
(13, 3, 'Cerveza', 'Corona, Modelo o Pacifico', 55.00, NULL, 1, '2026-04-30 01:11:24'),
(14, 4, 'Flan napolitano', 'Flan casero con cajeta', 65.00, NULL, 1, '2026-04-30 01:11:24'),
(15, 4, 'Pay de queso', 'Pay de queso con frutos rojos', 70.00, NULL, 1, '2026-04-30 01:11:24'),
(16, 4, 'Brownie con helado', 'Brownie de chocolate con helado de vainilla', 80.00, NULL, 1, '2026-04-30 01:11:24'),
(17, 2, 'Tacos de Guisado', 'Tacos de diferentes guisados (Chicharron rojo, Chicharron verde, Rajas con queso, Camaron, Caviar, Sushi)', 25.00, NULL, 1, '2026-08-26 07:48:02');

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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios_cliente`
--

INSERT INTO `usuarios_cliente` (`id`, `nombre`, `email`, `password`, `telefono`, `puntos`, `token_verificacion`, `email_confirmado`, `creado_en`) VALUES
(12, 'Lalo', 'sage040621haslnla5@gmail.com', '74b87337454200d4d33f80c4663dc5e5', '4499402367', 0, NULL, 1, '2026-09-01 02:58:05');

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
-- Indices de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_interaccion_cliente` (`cliente_id`);

--
-- Indices de la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD UNIQUE KEY `qr_token` (`qr_token`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `usuarios_cliente`
--
ALTER TABLE `usuarios_cliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restricciones para tablas volcadas
--

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
