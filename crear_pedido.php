<?php
// Corregimos la ruta al config.php que está en la misma carpeta
require_once 'config.php';
$db = getDB();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$mesa_id = (int)($input['mesa_id'] ?? 0);
$items   = $input['items'] ?? [];
$notas   = sanitize($input['notas'] ?? '');

if (!$mesa_id || empty($items)) {
    jsonResponse(['error' => 'Datos incompletos'], 400);
}

$db = getDB();

// Verificar mesa
$mesa = $db->prepare("SELECT id FROM mesas WHERE id = ? AND activa = 1");
$mesa->execute([$mesa_id]);
if (!$mesa->fetch()) {
    jsonResponse(['error' => 'Mesa no válida'], 400);
}

// Calcular total y validar productos
$total = 0;
$validated_items = [];

foreach ($items as $item) {
    $prod_id = (int)($item['producto_id'] ?? 0);
    $qty     = max(1, (int)($item['cantidad'] ?? 1));

    $prod = $db->prepare("SELECT id, nombre, precio FROM productos WHERE id = ? AND disponible = 1");
    $prod->execute([$prod_id]);
    $producto = $prod->fetch();

    if (!$producto) continue;

    $subtotal = $producto['precio'] * $qty;
    $total += $subtotal;

    $validated_items[] = [
        'producto_id'    => $prod_id,
        'cantidad'       => $qty,
        'precio_unitario'=> $producto['precio'],
        'subtotal'       => $subtotal,
        'notas'          => sanitize($item['notas'] ?? ''),
    ];
}

if (empty($validated_items)) {
    jsonResponse(['error' => 'No hay productos válidos'], 400);
}

// Generar número de orden único
$numero_orden = 'ORD-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

try {
    $db->beginTransaction();

    // Crear pedido
    $ins = $db->prepare("
        INSERT INTO pedidos (mesa_id, numero_orden, estado, total, notas)
        VALUES (?, ?, 'pendiente', ?, ?)
    ");
    $ins->execute([$mesa_id, $numero_orden, $total, $notas]);
    $pedido_id = $db->lastInsertId();

    // Insertar items
    $ins_item = $db->prepare("
        INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario, subtotal, notas)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($validated_items as $vi) {
        $ins_item->execute([
            $pedido_id,
            $vi['producto_id'],
            $vi['cantidad'],
            $vi['precio_unitario'],
            $vi['subtotal'],
            $vi['notas'],
        ]);
    }

    $db->commit();

    jsonResponse([
        'success'      => true,
        'pedido_id'    => $pedido_id,
        'numero_orden' => $numero_orden,
        'total'        => $total,
    ]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => 'Error al crear pedido: ' . $e->getMessage()], 500);
}