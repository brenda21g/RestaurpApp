<?php
/**
 * Archivo: config/auth_check.php
 * Descripción: Control de acceso centralizado con soporte para Gerente, Subgerente y Logística.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica el acceso y redirige automáticamente según el rol.
 * 
 * @param array $rolesPermitidos Roles autorizados para ver la página actual.
 */
function verificarAcceso($rolesPermitidos = []) {
    // Si no hay sesión activa, mandamos al login general
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_rol'])) {
        header("Location: ../index.php");
        exit;
    }

    $rolActual = $_SESSION['admin_rol'];

    // 1. El gerente tiene acceso universal a todo el sistema
    if ($rolActual === 'gerente') {
        return true;
    }

    $rutaActual = $_SERVER['PHP_SELF'];
    $estaEnScm = (strpos($rutaActual, '/scm/') !== false);
    $estaEnLogistica = (strpos($rutaActual, '/logistica/') !== false);

    // 2. REGLA PARA LOGÍSTICA:
    // Logística puede estar en su módulo de Logística o en SCM. Si intenta ir a otro lado, lo mandamos al dashboard de Logística.
    if ($rolActual === 'logistica') {
        if (!$estaEnLogistica && !$estaEnScm) {
            header("Location: ../logistica/dashboard.php");
            exit;
        }
        return true;
    }

    // 3. REGLA PARA SCM / LOGISTICA:
    // Si la página actual es del SCM o Logística, permitimos la entrada a los roles autorizados.
    if ($estaEnScm || $estaEnLogistica) {
        if (in_array($rolActual, ['gerente', 'subgerente', 'logistica'], true)) {
            return true;
        }
    }

    // 4. Validación normal de roles permitidos para el resto del sistema
    if (!empty($rolesPermitidos) && !in_array($rolActual, $rolesPermitidos, true)) {
        switch ($rolActual) {
            case 'subgerente':
                header("Location: ../subgerente/dashboard.php");
                break;
            case 'logistica':
                header("Location: ../logistica/dashboard.php");
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
 * Control de permisos de escritura (Modificación vs Solo Lectura).
 */
function puedeEditar($modulo = '') {
    $rol = $_SESSION['admin_rol'] ?? '';

    if ($rol === 'gerente') return true;

    if ($modulo === 'scm') {
        // Permitir edición en SCM solo a Logística y Gerente. Subgerente queda excluido (solo lectura).
        return ($rol === 'logistica');
    }

    if ($modulo === 'subgerente') {
        return $rol === 'subgerente';
    }

    return false;
}
?>