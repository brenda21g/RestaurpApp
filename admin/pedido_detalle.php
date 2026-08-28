<?php
// Corregimos la ruta al archivo de configuración en la raíz
require_once __DIR__ . '/../config/config.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    jsonResponse(['error' => 'ID de pedido requerido'], 400);
}

// Consultamos el pedido y unimos con la mesa para saber el número físico
$pedido_stmt = $db->prepare("
    SELECT pe.*, m.numero as mesa_num 
    FROM pedidos pe 
    JOIN mesas m ON m.id = pe.mesa_id 
    WHERE pe.id = ?
");
$pedido_stmt->execute([$id]);
$p = $pedido_stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    jsonResponse(['error' => 'Pedido no encontrado'], 404);
}

// Consultamos los productos (items) vinculados a este pedido
$items_stmt = $db->prepare("
    SELECT pi.*, pr.nombre 
    FROM pedido_items pi 
    JOIN productos pr ON pr.id = pi.producto_id 
    WHERE pi.pedido_id = ?
");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Enviamos la respuesta combinada
jsonResponse([
    'pedido' => $p, 
    'items'  => $items
]);