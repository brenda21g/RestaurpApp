<?php
/**
 * Archivo: cliente/actualizar.php
 * Descripción: Procesa la actualización del estatus del pedido y el pago por parte del cliente.
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['mesa_token'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado o sesión expirada.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nuevo_estado = $input['estado'] ?? '';
$metodo_pago = $input['metodo_pago'] ?? 'efectivo';

if ($nuevo_estado !== 'pagado') {
    echo json_encode(['success' => false, 'error' => 'Estado no válido.']);
    exit;
}

$db = getDB();
$cliente_id = (int)$_SESSION['cliente_id'];

try {
    $db->beginTransaction();

    // 1. Obtener los datos reales del cliente y su monedero
    $stmtC = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
    $stmtC->execute([$cliente_id]);
    $cliente = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        throw new Exception('Cliente no encontrado.');
    }

    $saldo_actual = floatval($cliente['monedero']);

    // 2. Buscar la mesa activa
    $stmtMesa = $db->prepare("SELECT id FROM mesas WHERE qr_token = ? AND activa = 1");
    $stmtMesa->execute([$_SESSION['mesa_token']]);
    $mesa = $stmtMesa->fetch(PDO::FETCH_ASSOC);

    if (!$mesa) {
        throw new Exception('Mesa no válida o inactiva.');
    }

    // 3. Buscar el pedido activo o entregado de la mesa
    $stmtPedido = $db->prepare("SELECT * FROM pedidos WHERE mesa_id = ? AND estado IN ('entregado', 'listo', 'preparando', 'pendiente') ORDER BY id DESC LIMIT 1");
    $stmtPedido->execute([$mesa['id']]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('No se encontró un pedido activo para pagar.');
    }

    $total_pagar = floatval($pedido['total']);

    // 4. Si el pago es por monedero, validar saldo y descontar
    if ($metodo_pago === 'monedero') {
        if ($saldo_actual < $total_pagar) {
            throw new Exception('Saldo insuficiente en el monedero para cubrir la cuenta.');
        }

        $nuevo_saldo = $saldo_actual - $total_pagar;
        
        // Actualizar monedero en la tabla usuarios_cliente
        $stmtUpdMon = $db->prepare("UPDATE usuarios_cliente SET monedero = ? WHERE id = ?");
        $stmtUpdMon->execute([$nuevo_saldo, $cliente_id]);
        
        // Registrar el movimiento en el historial del monedero
        $stmtHist = $db->prepare("INSERT INTO monedero_historial (cliente_id, tipo, monto, saldo_resultante, concepto, fecha) VALUES (?, 'cargo', ?, ?, ?, NOW())");
        $stmtHist->execute([$cliente_id, $total_pagar, $nuevo_saldo, 'Pago de pedido #' . $pedido['numero_orden']]);

        // Actualizar sesión
        $_SESSION['saldo_monedero'] = $nuevo_saldo;
    }

    // 5. Actualizar el estatus del pedido a pagado (sin afectar columnas inexistentes)
    $stmtUpdPedido = $db->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = ?");
    $stmtUpdPedido->execute([$pedido['id']]);

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}