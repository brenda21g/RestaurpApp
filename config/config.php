<?php
// Configurar duración de la sesión antes de iniciarla
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 300);
    session_start();
}

// Función para sanitizar entradas
function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str ?? '')), ENT_QUOTES, 'UTF-8');
}

// =============================================
// CONFIGURACIÓN DE BASE DE DATOS
// Modifica estos valores según tu XAMPP
// =============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP por defecto no tiene contraseña
define('DB_NAME', 'restaurante_db');
define('DB_PORT', 3306);



define('SITE_URL', 'http://10.0.13.166/restaurapp');
define('SITE_NAME', 'RestaurApp');

// Zona horaria México
date_default_timezone_set('America/Mexico_City');

// Conectar a la base de datos mediante PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Error de conexión a la base de datos: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $pdo;
}

// Función helper para respuestas JSON
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $code = 200) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>