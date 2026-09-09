<?php
// ==========================================================================
// CONTROL DE AUTENTICACIÓN, INACTIVIDAD Y ROLES (Restaurant_App)
// ==========================================================================
require_once __DIR__ . '/config.php';

// 1. Asegurar que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir ruta base de login de manera segura y directa para el panel
$loginUrl = defined('SITE_URL') ? SITE_URL . '/gerente/login.php' : 'login.php';

// 2. Verificar si hay admin logueado
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . $loginUrl);
    exit;
}

// 3. Control de Inactividad (300 segundos = 5 minutos)
$inactividad = 300; 

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

// 4. Control Jerárquico de Roles
// El 'gerente' tiene acceso total por defecto a todo. 
// El 'subgerente' solo accederá si su rol se incluye en los parámetros permitidos.
function verificarAcceso(array $rolesPermitidos = []) {
    global $loginUrl;
    
    if (!isset($_SESSION['admin_rol'])) {
        header('Location: ' . $loginUrl);
        exit;
    }

    // El gerente entra a cualquier sección sin restricciones
    if ($_SESSION['admin_rol'] === 'gerente') {
        return true;
    }

    // Validar si el rol actual del usuario está autorizado para esta vista específica
    if (!in_array($_SESSION['admin_rol'], $rolesPermitidos)) {
        header('Location: dashboard.php?error=no_autorizado');
        exit;
    }
}
?>