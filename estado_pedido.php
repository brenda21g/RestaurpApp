<?php
require_once 'config.php';
$db = getDB();

$pedido_id = (int)($_GET['pedido_id'] ?? 0);
$mesa_id   = (int)($_GET['mesa_id'] ?? 0);

if ($pedido_id) {
    $stmt = $db->prepare("
        SELECT pe.*, m.numero as mesa_num
        FROM pedidos pe 
        JOIN mesas m ON m.id = pe.mesa_id
        WHERE pe.id = ?
    ");
    $stmt->execute([$pedido_id]);
} elseif ($mesa_id) {
    // Busca el último pedido de esa mesa
    $stmt = $db->prepare("
        SELECT pe.*, m.numero as mesa_num
        FROM pedidos pe 
        JOIN mesas m ON m.id = pe.mesa_id
        WHERE pe.mesa_id = ?
        ORDER BY pe.creado_en DESC LIMIT 1
    ");
    $stmt->execute([$mesa_id]);
} else {
    jsonResponse(['error' => 'Parámetros inválidos'], 400);
}

$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    jsonResponse(['pedido' => null]);
}

// Obtener platillos del pedido
$items_stmt = $db->prepare("
    SELECT pi.*, p.nombre
    FROM pedido_items pi 
    JOIN productos p ON p.id = pi.producto_id
    WHERE pi.pedido_id = ?
");
$items_stmt->execute([$pedido['id']]);
$pedido['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse(['pedido' => $pedido]);
?>