<?php
/**
 * Archivo: config/auth_check.php
 * Descripción: Verificación de sesión y control de acceso por roles.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarAcceso($rolesPermitidos = []) {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_rol'])) {
        header("Location: ../index.php");
        exit;
    }

    // El gerente tiene acceso total a todo
    if ($_SESSION['admin_rol'] === 'gerente') {
        return true;
    }

    // Verificar si el rol actual está permitido en la página actual
    if (!in_array($_SESSION['admin_rol'], $rolesPermitidos)) {
        // Redirección inteligente según el rol del usuario
        if ($_SESSION['admin_rol'] === 'subgerente') {
            header("Location: ../gerente/dashboard.php");
        } elseif ($_SESSION['admin_rol'] === 'logistica') {
            header("Location: ../scm/dashboard.php");
        } else {
            header("Location: ../index.php");
        }
        exit;
    }
}

function getDB() {
    $host = 'localhost';
    $db   = 'restaurante_db';
    $user = 'root';
    $pass = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}
?>