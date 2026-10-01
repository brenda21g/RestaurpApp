<?php
/**
 * Archivo: gerente/logout.php
 * Descripción: Controlador de cierre de sesión seguro para el panel gerencial.
 */
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rol = $_SESSION['admin_rol'] ?? '';

// 1. Vaciar variables de sesión
$_SESSION = [];

// 2. Borrar cookie de sesión
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

// 3. Destruir sesión
session_destroy();

// 4. Redirección al login principal en la raíz
$redirect = 'login.php'; // Cambiar a '../login.php' si tu acceso administrativo se llama login.php en la raíz

if (isset($_GET['reason']) && $_GET['reason'] === 'inactividad') {
    $redirect .= '?inactivo=1';
}

header('Location: ' . $redirect);
exit;