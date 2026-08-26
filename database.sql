-- =============================================
-- SISTEMA DE PEDIDOS CON ROLES
-- Base de Datos: restaurante_db
-- =============================================

CREATE DATABASE IF NOT EXISTS restaurante_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurante_db;

-- Tabla de mesas
CREATE TABLE IF NOT EXISTS mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero INT NOT NULL UNIQUE,
    nombre VARCHAR(50),
    qr_token VARCHAR(64) UNIQUE NOT NULL,
    activa TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de categorías del menú
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(10) DEFAULT '🍽️',
    orden INT DEFAULT 0,
    activa TINYINT(1) DEFAULT 1
);

-- Tabla de productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    imagen VARCHAR(255),
    disponible TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

-- Tabla de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mesa_id INT NOT NULL,
    numero_orden VARCHAR(20) NOT NULL UNIQUE,
    estado ENUM('pendiente','preparando','listo','entregado','cancelado') DEFAULT 'pendiente',
    total DECIMAL(10,2) DEFAULT 0.00,
    tiempo_estimado INT DEFAULT NULL COMMENT 'minutos',
    notas TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    entregado_en TIMESTAMP NULL,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id)
);

-- Tabla de detalle de pedidos
CREATE TABLE IF NOT EXISTS pedido_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    notas VARCHAR(255),
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- Tabla de administradores
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100),
    activo TINYINT(1) DEFAULT 1,
    ultimo_login TIMESTAMP NULL
);

-- =============================================
-- DATOS INICIALES
-- =============================================

-- Admin por defecto (password: admin123)
INSERT INTO admins (username, password_hash, nombre) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador')
ON DUPLICATE KEY UPDATE id=id;

-- Mesas
INSERT INTO mesas (numero, nombre, qr_token) VALUES 
(1, 'Mesa 1', 'mesa_token_001_abc123'),
(2, 'Mesa 2', 'mesa_token_002_def456'),
(3, 'Mesa 3', 'mesa_token_003_ghi789'),
(4, 'Mesa 4', 'mesa_token_004_jkl012'),
(5, 'Mesa 5', 'mesa_token_005_mno345'),
(6, 'Mesa 6', 'mesa_token_006_pqr678')
ON DUPLICATE KEY UPDATE id=id;

-- Categorías
INSERT INTO categorias (nombre, icono, orden) VALUES 
('Entradas', '🥗', 1),
('Platillos', '🍽️', 2),
('Bebidas', '🥤', 3),
('Postres', '🍰', 4)
ON DUPLICATE KEY UPDATE id=id;

-- Productos
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES 
(1, 'Guacamole con totopos', 'Aguacate fresco, jitomate, cebolla, cilantro', 85.00),
(1, 'Sopa de tortilla', 'Caldo de tomate, tiras de tortilla, crema, queso', 75.00),
(1, 'Queso fundido', 'Queso Oaxaca gratinado con chorizo', 95.00),
(2, 'Tacos de carne asada', '3 tacos con carne asada, salsa y guarnición', 130.00),
(2, 'Enchiladas verdes', '3 enchiladas con pollo, salsa verde, crema', 120.00),
(2, 'Pollo a la plancha', 'Pechuga a la plancha con arroz y frijoles', 145.00),
(2, 'Pasta alfredo', 'Pasta con crema, queso parmesano y champiñones', 110.00),
(2, 'Hamburguesa clásica', 'Carne de res, lechuga, tomate, cebolla, papas', 125.00),
(3, 'Agua fresca', 'Jamaica, horchata o tamarindo', 35.00),
(3, 'Refresco', 'Coca-Cola, Sprite o Fanta', 30.00),
(3, 'Café americano', 'Café de olla o americano', 40.00),
(3, 'Jugo natural', 'Naranja, zanahoria o betabel', 45.00),
(3, 'Cerveza', 'Corona, Modelo o Pacifico', 55.00),
(4, 'Flan napolitano', 'Flan casero con cajeta', 65.00),
(4, 'Pay de queso', 'Pay de queso con frutos rojos', 70.00),
(4, 'Brownie con helado', 'Brownie de chocolate con helado de vainilla', 80.00);