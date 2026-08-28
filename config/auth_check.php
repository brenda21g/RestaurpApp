<?php
// Carga config.php que está en su misma carpeta (config/)
require_once __DIR__ . '/config.php';

// 1. Verificar si hay admin logueado
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin/login.php'); 
    exit;
}

// 2. Control de Inactividad (3 minutos = 180 segundos)
$inactividad = 180; 

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    
    if ($tiempo_transcurrido > $inactividad) {
        session_unset();
        session_destroy();
        header('Location: ../admin/login.php?error=timeout');
        exit;
    }
}

if (!isset($_SESSION['ultimo_acceso'])) {
    $_SESSION['ultimo_acceso'] = time();
}
?>