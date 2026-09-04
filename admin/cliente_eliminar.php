<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();
$id = $_GET['id'] ?? null;

if ($id) {
    // Actualizamos el estado del cliente a 'Baja' de forma lógica
    $stmt = $db->prepare("UPDATE usuarios_cliente SET estado = 'Baja' WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: clientes.php");
exit;