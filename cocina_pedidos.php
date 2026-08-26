<?php
// Corregimos la ruta para que coincida con tu carpeta en htdocs
require_once 'config.php';

// Verificamos que las funciones existan (si no están en config.php)
if (!function_exists('getDB')) {
    function getDB() {
        global $pdo; // Suponiendo que tu conexión en config.php se llama $pdo
        return $pdo;
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

$db = getDB();

// Obtener pedidos activos
$stmt = $db->query("
    SELECT pe.*, m.numero as mesa_num
    FROM pedidos pe
    JOIN mesas m ON m.id = pe.mesa_id
    WHERE pe.estado IN ('pendiente', 'preparando', 'listo')
    AND DATE(pe.creado_en) = CURDATE()
    ORDER BY pe.creado_en ASC
");

$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pedidos as &$p) {
    $items = $db->prepare("
        SELECT pi.cantidad, pi.notas, pr.nombre
        FROM pedido_items pi
        JOIN productos pr ON pr.id = pi.producto_id
        WHERE pi.pedido_id = ?
    ");
    $items->execute([$p['id']]);
    $p['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
}

jsonResponse(['pedidos' => $pedidos]);