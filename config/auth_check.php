<?php
/**
 * Archivo: config/auth_check.php
 * Descripción: Verificación de sesión y control de acceso por roles optimizado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario ha iniciado sesión y si cuenta con el rol permitido.
 * 
 * @param array $rolesPermitidos Lista de roles que pueden acceder a la vista.
 */
function verificarAcceso($rolesPermitidos = []) {
    // Validar si existe una sesión activa
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_rol'])) {
        header("Location: ../index.php");
        exit;
    }

    $rolActual = $_SESSION['admin_rol'];

    // El gerente tiene acceso universal a todo el sistema
    if ($rolActual === 'gerente') {
        return true;
    }

    // Si se especificaron roles permitidos y el usuario no está en la lista
    if (!empty($rolesPermitidos) && !in_array($rolActual, $rolesPermitidos, true)) {
        // Redirección inteligente basada en el rol corporativo/operativo
        switch ($rolActual) {
            case 'subgerente':
                header("Location: ../subgerente/dashboard.php");
                break;
            case 'logistica':
                header("Location: ../scm/dashboard.php");
                break;
            case 'cocina':
                header("Location: ../cocina/dashboard.php");
                break;
            default:
                header("Location: ../index.php");
                break;
        }
        exit;
    }
}

/**
 * Nota: La función getDB() se ha migrado al archivo central de conexión 
 * para mantener el principio de responsabilidad única (Single Responsibility Principle).
 */
?>