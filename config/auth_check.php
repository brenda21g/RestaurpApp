<?php
// Carga config.php (se encuentra en esta misma carpeta config/)
require_once __DIR__ . '/config.php';

// 1. Asegurar que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir ruta base de login usando SITE_URL de config.php
$loginUrl = defined('SITE_URL') ? SITE_URL . '/admin/login.php' : '/admin/login.php';

// 2. Verificar si hay admin logueado
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . $loginUrl);
    exit;
}

// 3. Control de Inactividad (3 minutos = 180 segundos)
$inactividad = 180; 

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    
    if ($tiempo_transcurrido > $inactividad) {
        // Destruir sesión por timeout
        session_unset();
        session_destroy();
        header('Location: ' . $loginUrl . '?error=timeout');
        exit;
    }
}

// Actualizar la hora del último acceso en cada petición válida
$_SESSION['ultimo_acceso'] = time();
?>