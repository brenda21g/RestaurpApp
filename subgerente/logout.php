<?php
require_once __DIR__ . '/../config/config.php';

// 1. Vaciar todas las variables de sesión
$_SESSION = array();

// 2. Si se desea destruir la sesión completamente, también se debe borrar la cookie de sesión
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

// 4. Redirigir al formulario de login
header('Location: login.php');
exit;