<?php
/**
 * Archivo: scm/logout.php
 * Descripción: Controlador de cierre de sesión seguro para el personal de Logística.
 */
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Vaciar variables de sesión
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

// 3. Destruir sesión en el servidor
session_destroy();

// 4. Redirigir al login principal en la raíz (ajusta a '../login.php' si tu acceso general se llama así)
$redirect = '../subgerente/login.php'; 

if (isset($_GET['reason']) && $_GET['reason'] === 'inactividad') {
    $redirect .= '?inactivo=1';
}

header('Location: ' . $redirect);
exit;