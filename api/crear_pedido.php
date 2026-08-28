<?php
// Carga config.php subiendo un nivel desde la carpeta api/
require_once __DIR__ . '/../config/config.php';

// Asegurar función de respuesta JSON
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}

// Asegurar función sanitize si no existe en config.php
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$mesa_id = (int)($input['mesa_id'] ?? 0);
$items   = $input['items'] ?? [];
$notas   = sanitize($input['notas'] ?? '');

// Capturar cliente_id si ha iniciado sesión en el navegador
$cliente_id = isset($_SESSION['cliente_id']) ? $_SESSION['cliente_id'] : null;

if (!$mesa_id || empty($items)) {
    jsonResponse(['error' => 'Datos incompletos'], 400);
}

try {
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

    $db->beginTransaction();

    // Crear pedido asociando el cliente_id de la sesión (si existe)
    $ins = $db->prepare("
        INSERT INTO pedidos (mesa_id, cliente_id, numero_orden, estado, total, notas)
        VALUES (?, ?, ?, 'pendiente', ?, ?)
    ");
    $ins->execute([$mesa_id, $cliente_id, $numero_orden, $total, $notas]);
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
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['error' => 'Error al crear pedido: ' . $e->getMessage()], 500);
}
?>