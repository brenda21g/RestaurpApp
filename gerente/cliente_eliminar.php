<?php
/**
 * Archivo: gerente/cliente_eliminar.php
 * Descripción: Controlador para realizar la baja lógica de un cliente o prospecto en el CRM.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Obtener y sanitizar el ID asegurando que sea un número entero
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Aplicación de baja lógica (cambio de estado en lugar de borrado físico)
    $stmt = $db->prepare("UPDATE usuarios_cliente SET estado = 'Baja' WHERE id = ?");
    $stmt->execute([$id]);
}

// Redireccionar de vuelta al listado general de clientes
header("Location: clientes.php");
exit;
?>