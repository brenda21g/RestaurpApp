<?php
/**
 * Archivo: config/config.php
 * Descripción: Configuración global, constantes, sanitización y conexión PDO robusta.
 */

// Configurar duración de la sesión antes de iniciarla
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 300);
    session_start();
}

// Función para sanitizar entradas de texto de forma segura
function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str ?? '')), ENT_QUOTES, 'UTF-8');
}

// =============================================
// CONFIGURACIÓN DE BASE DE DATOS
// =============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP por defecto sin contraseña
define('DB_NAME', 'restaurante_db');
define('DB_PORT', 3306);

// Parámetros del Sitio (Modificables según tu entorno local o producción)
define('SITE_URL', 'http://173.16.18.95/restaurant_app');
define('SITE_NAME', 'RestaurApp');

// Zona horaria configurada para México
date_default_timezone_set('America/Mexico_City');

/**
 * Conexión única a la base de datos mediante PDO (Patrón Singleton estático)
 * 
 * @return PDO
 */
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
            
            // Si la petición es AJAX / API, devolver JSON; de lo contrario, mostrar error controlado
            $esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($esAjax || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Error de conexión a la base de datos: ' . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo "<div style='font-family: Arial; padding: 20px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;'>";
                echo "<h3>Error crítico de Sistema</h3>";
                echo "<p>No se pudo establecer conexión con la base de datos. Por favor, verifique los parámetros de configuración.</p>";
                echo "</div>";
            }
            exit;
        }
    }
    return $pdo;
}

// Función helper para respuestas JSON estandarizadas
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $code = 200) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>