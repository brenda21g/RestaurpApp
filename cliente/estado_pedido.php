<?php
// Carga config.php subiendo un nivel desde la carpeta cliente/
require_once __DIR__ . '/../config/config.php';

session_start();
if (!isset($_SESSION['cliente_id'])) {
    header('Location: login_cliente.php');
    exit;
}

// Asegurar función de respuesta JSON en caso de no estar en config.php
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}

try {
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

} catch (Exception $e) {
    jsonResponse(['error' => 'Error en el servidor: ' . $e->getMessage()], 500);
}
?>