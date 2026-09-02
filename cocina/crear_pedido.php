<?php
// Carga config.php subiendo un nivel desde la carpeta cocina/
require_once __DIR__ . '/../config/config.php';

// Desactivar impresión de errores HTML para evitar romper la respuesta JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Función auxiliar para responder en formato JSON
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Función de sanitización básica
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
}

// Recibir datos de JSON o $_POST
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$mesa_input = $input['mesa_id'] ?? $input['mesa'] ?? 0;
$items      = $input['items'] ?? [];
$notas      = sanitize($input['notas'] ?? '');

// Capturar cliente_id si ha iniciado sesión
$cliente_id = $_SESSION['cliente_id'] ?? null;

if (empty($mesa_input) || empty($items) || !is_array($items)) {
    jsonResponse(['success' => false, 'error' => 'Datos de pedido incompletos o inválidos'], 400);
}

try {
    $db = getDB();

    // 1. Verificar existencia y estado de la mesa (por ID numérico o por Token/Código)
    if (is_numeric($mesa_input)) {
        $stmtMesa = $db->prepare("SELECT id FROM mesas WHERE id = ? AND activa = 1");
        $stmtMesa->execute([(int)$mesa_input]);
    } else {
        $stmtMesa = $db->prepare("SELECT id FROM mesas WHERE token = ? AND activa = 1");
        $stmtMesa->execute([sanitize($mesa_input)]);
    }

    $mesa = $stmtMesa->fetch(PDO::FETCH_ASSOC);

    if (!$mesa) {
        jsonResponse(['success' => false, 'error' => 'La mesa seleccionada no es válida o está inactiva'], 400);
    }

    $real_mesa_id = $mesa['id'];

    // 2. Calcular total real y validar los productos en base de datos
    $total = 0;
    $validated_items = [];

    foreach ($items as $item) {
        $prod_id = (int)($item['producto_id'] ?? $item['id'] ?? 0);
        $qty     = max(1, (int)($item['cantidad'] ?? 1));

        $prod = $db->prepare("SELECT id, nombre, precio FROM productos WHERE id = ? AND disponible = 1");
        $prod->execute([$prod_id]);
        $producto = $prod->fetch(PDO::FETCH_ASSOC);

        if (!$producto) continue;

        $subtotal = $producto['precio'] * $qty;
        $total += $subtotal;

        $validated_items[] = [
            'producto_id'     => $prod_id,
            'cantidad'        => $qty,
            'precio_unitario' => $producto['precio'],
            'subtotal'        => $subtotal,
            'notas'           => sanitize($item['notas'] ?? ''),
        ];
    }

    if (empty($validated_items)) {
        jsonResponse(['success' => false, 'error' => 'No hay productos válidos o disponibles en el pedido'], 400);
    }

    // 3. Generar un número de orden único
    $numero_orden = 'ORD-' . strtoupper(substr(md5(uniqid((string)rand(), true)), 0, 6));

    // Iniciar transacción SQL
    $db->beginTransaction();

    // 4. Crear el pedido
    $ins = $db->prepare("
        INSERT INTO pedidos (mesa_id, cliente_id, numero_orden, estado, total, notas, creado_en)
        VALUES (?, ?, ?, 'pendiente', ?, ?, NOW())
    ");
    $ins->execute([$real_mesa_id, $cliente_id, $numero_orden, $total, $notas]);
    $pedido_id = $db->lastInsertId();

    // 5. Insertar los detalles del pedido (pedido_items)
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

    // Confirmar transacción
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
    jsonResponse(['success' => false, 'error' => 'Error al procesar el pedido: ' . $e->getMessage()], 500);
}
?>