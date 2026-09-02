<?php
// Carga config.php subiendo un nivel desde la carpeta cocina/
require_once __DIR__ . '/../config/config.php';

// Desactivar impresión de errores HTML directos
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Asegurar función de respuesta JSON
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $db = getDB();

    // 1. Obtener todos los pedidos activos del día
    $stmt = $db->query("
        SELECT 
            pe.id, 
            pe.mesa_id, 
            pe.cliente_id, 
            pe.numero_orden, 
            pe.estado, 
            pe.total, 
            pe.notas, 
            pe.tiempo_estimado, 
            pe.creado_en,
            COALESCE(m.numero, m.id) as mesa_num
        FROM pedidos pe
        JOIN mesas m ON m.id = pe.mesa_id
        WHERE pe.estado IN ('pendiente', 'preparando', 'listo')
          AND DATE(pe.creado_en) = CURDATE()
        ORDER BY pe.creado_en ASC
    ");

    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pedidos)) {
        jsonResponse(['success' => true, 'pedidos' => []]);
    }

    // 2. Extraer todos los IDs de pedidos para consultar sus items en una sola consulta SQL
    $pedidoIds = array_column($pedidos, 'id');
    $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));

    $stmtItems = $db->prepare("
        SELECT 
            pi.pedido_id, 
            pi.cantidad, 
            pi.notas, 
            pr.nombre
        FROM pedido_items pi
        JOIN productos pr ON pr.id = pi.producto_id
        WHERE pi.pedido_id IN ($placeholders)
    ");
    $stmtItems->execute($pedidoIds);
    $todosLosItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Agrupar items por pedido_id
    $itemsPorPedido = [];
    foreach ($todosLosItems as $item) {
        $itemsPorPedido[$item['pedido_id']][] = [
            'nombre'   => $item['nombre'],
            'cantidad' => (int)$item['cantidad'],
            'notas'    => $item['notas']
        ];
    }

    // 4. Mapear los items a sus respectivos pedidos
    foreach ($pedidos as &$p) {
        $p['id'] = (int)$p['id'];
        $p['items'] = $itemsPorPedido[$p['id']] ?? [];
    }
    unset($p);

    jsonResponse([
        'success' => true,
        'pedidos' => $pedidos
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error'   => 'Error al consultar pedidos: ' . $e->getMessage()
    ], 500);
}
?>