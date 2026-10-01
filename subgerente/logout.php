<?php
/**
 * Archivo: subgerente/logout.php
 * Descripción: Controlador de cierre de sesión seguro para el panel del Subgerente.
 */
require_once __DIR__ . '/../config/config.php';

// Asegurar que la sesión esté iniciada para poder destruirla correctamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Vaciar todas las variables de sesión
$_SESSION = [];

// 2. Borrar la cookie de sesión del servidor
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Destruir la sesión en el servidor
session_destroy();

// 4. Redirigir explícitamente al login administrativo de la raíz (ajusta 'index.php' o 'login.php' según tu archivo de acceso de administradores)
$redirect = 'login.php'; 

if (isset($_GET['reason']) && $_GET['reason'] === 'inactividad') {
    $redirect .= '?inactivo=1';
}

header('Location: ' . $redirect);
exit;