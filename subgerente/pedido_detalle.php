<?php
// Carga de configuración y verificación de sesión del administrador
require_once __DIR__ . '/../config/auth_check.php';

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID de pedido inválido o no proporcionado'], 400);
}

// Consultar el pedido junto con los datos de la mesa
$pedido_stmt = $db->prepare("
    SELECT pe.id, pe.mesa_id, pe.estado, pe.total, pe.notas, pe.creado_en, m.numero as mesa_num 
    FROM pedidos pe 
    JOIN mesas m ON m.id = pe.mesa_id 
    WHERE pe.id = ?
");
$pedido_stmt->execute([$id]);
$pedido = $pedido_stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    jsonResponse(['error' => 'Pedido no encontrado'], 404);
}

// Convertir total a tipo float explícito
$pedido['total'] = (float)$pedido['total'];

// Consultar los ítems del pedido
$items_stmt = $db->prepare("
    SELECT pi.id, pi.producto_id, pi.cantidad, pi.precio_unitario, pi.subtotal, pr.nombre 
    FROM pedido_items pi 
    JOIN productos pr ON pr.id = pi.producto_id 
    WHERE pi.pedido_id = ?
");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatear numéricos de los ítems
foreach ($items as &$item) {
    $item['cantidad']        = (int)$item['cantidad'];
    $item['precio_unitario'] = (float)$item['precio_unitario'];
    $item['subtotal']        = (float)$item['subtotal'];
}
unset($item);

// Responder con la información estructurada
jsonResponse([
    'success' => true,
    'pedido'  => $pedido, 
    'items'   => $items
]);