<?php
/**
 * Archivo: subgerente/pedido_detalle.php
 * Descripción: Endpoint JSON para consultar el detalle completo de un pedido, sus ítems y la trazabilidad exacta de autoría y cliente.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['subgerente', 'gerente']);

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID de pedido inválido o no proporcionado'], 400);
}

// Consultar el pedido relacionando de forma robusta al cliente y al administrador (vía orden ERP o ID directo)
$pedido_stmt = $db->prepare("
    SELECT 
        pe.id, 
        pe.mesa_id, 
        pe.estado, 
        pe.total, 
        pe.notas, 
        pe.creado_en, 
        pe.numero_orden,
        m.numero as mesa_num,
        COALESCE(c.nombre, c_eo.nombre, 'Cliente General / Mostrador') as cliente_nombre,
        a.nombre as admin_nombre,
        a.rol as admin_rol
    FROM pedidos pe 
    JOIN mesas m ON m.id = pe.mesa_id 
    LEFT JOIN usuarios_cliente c ON c.id = pe.cliente_id
    LEFT JOIN erp_ordenes eo ON eo.folio = pe.numero_orden OR eo.id = pe.numero_orden
    LEFT JOIN usuarios_cliente c_eo ON c_eo.id = eo.cliente_id
    LEFT JOIN admins a ON a.id = eo.admin_id
    WHERE pe.id = ?
");
$pedido_stmt->execute([$id]);
$pedido = $pedido_stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    jsonResponse(['error' => 'Pedido no encontrado'], 404);
}

// Convertir total a tipo float explícito
$pedido['total'] = (float)$pedido['total'];

// Asegurar campos limpios para la respuesta JSON
$pedido['cliente_nombre'] = !empty($pedido['cliente_nombre']) ? $pedido['cliente_nombre'] : 'Cliente General / Mostrador';
$pedido['admin_nombre']   = $pedido['admin_nombre'] ?? null;
$pedido['admin_rol']      = $pedido['admin_rol'] ?? null;

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

// Responder con la información estructurada en JSON
jsonResponse([
    'success' => true,
    'pedido'  => $pedido, 
    'items'   => $items
]);