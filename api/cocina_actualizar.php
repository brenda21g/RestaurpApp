<?php
// Carga config.php subiendo un nivel desde la carpeta api/
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

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
    $db = getDB();

    $stmt = $db->prepare("SELECT id, cliente_id, total FROM pedidos WHERE id = ?");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
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

    // 1. Actualizar el pedido en la base de datos
    $upd = $db->prepare("UPDATE pedidos SET $fields WHERE id = ?");
    $upd->execute($params);

    // 2. SISTEMA DE PUNTOS: Si pasa a 'entregado' y pertenece a un cliente registrado
    if ($nuevo_estado === 'entregado' && !empty($pedido['cliente_id'])) {
        // Regla: 1 punto por cada $10 consumidos
        $puntos_ganados = floor($pedido['total'] / 10);

        if ($puntos_ganados > 0) {
            $updPuntos = $db->prepare("UPDATE usuarios_cliente SET puntos = puntos + ? WHERE id = ?");
            $updPuntos->execute([$puntos_ganados, $pedido['cliente_id']]);
        }
    }

    enviarRespuesta(['success' => true, 'nuevo_estado' => $nuevo_estado]);

} catch (Exception $e) {
    enviarRespuesta(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
}
?>