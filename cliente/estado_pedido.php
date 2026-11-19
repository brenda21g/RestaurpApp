<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['cliente_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$mesa_id = (int)($_GET['mesa_id'] ?? 0);
$cliente_id = (int)$_SESSION['cliente_id'];

try {
    $db = getDB();
    // Consultar el pedido más reciente de la mesa para el cliente en el día actual
    $stmt = $db->prepare("
        SELECT id, numero_orden, estado, total, creado_en 
        FROM pedidos 
        WHERE mesa_id = ? AND cliente_id = ? 
          AND DATE(creado_en) = CURDATE()
        ORDER BY creado_en DESC 
        LIMIT 1
    ");
    $stmt->execute([$mesa_id, $cliente_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pedido'  => $pedido ? $pedido : null
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
?>