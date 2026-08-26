<?php
require_once 'config.php';

header('Content-Type: application/json');

// Desactivar salida de errores HTML que puedan romper el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

function enviarRespuesta($data) {
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuesta(['success' => false, 'error' => 'Método no permitido']);
}

$input = json_decode(file_get_contents('php://input'), true);

$id              = (int)($input['id'] ?? 0);
$nuevo_estado    = trim($input['estado'] ?? '');
$tiempo_estimado = isset($input['tiempo_estimado']) ? (int)$input['tiempo_estimado'] : null;

$estados_validos = ['preparando', 'listo', 'entregado', 'cancelado'];

if (!$id || !in_array($nuevo_estado, $estados_validos)) {
    enviarRespuesta(['success' => false, 'error' => 'Datos de actualización inválidos']);
}

try {
    // Usamos getDB() para mantener coherencia con cocina_pedidos.php
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM pedidos WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        enviarRespuesta(['success' => false, 'error' => 'Pedido no encontrado']);
    }

    $fields = "estado = ?, actualizado_en = NOW()";
    $params = [$nuevo_estado];

    if ($tiempo_estimado && $nuevo_estado === 'preparando') {
        $fields .= ", tiempo_estimado = ?";
        $params[] = $tiempo_estimado;
    }

    if ($nuevo_estado === 'entregado') {
        $fields .= ", entregado_en = NOW()";
    }

    $params[] = $id;

    $upd = $db->prepare("UPDATE pedidos SET $fields WHERE id = ?");
    $upd->execute($params);

    enviarRespuesta(['success' => true, 'nuevo_estado' => $nuevo_estado]);

} catch (Exception $e) {
    enviarRespuesta(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
}
?>