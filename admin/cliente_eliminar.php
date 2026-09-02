<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $db->prepare("DELETE FROM usuarios_cliente WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: clientes.php");
exit;