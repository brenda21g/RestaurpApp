<?php
// Primero cargamos la configuración de base de datos y la sesión limpia
require_once __DIR__ . '/config.php';

// 1. Verificar si hay admin logueado (bloqueo directo)
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php'); 
    exit;
}

// 2. Control de Inactividad (3 minutos = 180 segundos)
$inactividad = 180; 

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    
    if ($tiempo_transcurrido > $inactividad) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=timeout');
        exit;
    }
}

// Nota: No actualizamos $_SESSION['ultimo_acceso'] en recargas pasivas.
// Se actualiza únicamente cuando el usuario interactúa (vía frontend o clics de navegación).
if (!isset($_SESSION['ultimo_acceso'])) {
    $_SESSION['ultimo_acceso'] = time();
}
?>