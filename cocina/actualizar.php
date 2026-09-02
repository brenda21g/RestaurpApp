<?php
// Carga la configuración subiendo un nivel desde la carpeta cocina/
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Desactivar salida de errores HTML que puedan romper la respuesta JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

function enviarRespuesta($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuesta(['success' => false, 'error' => 'Método no permitido']);
}

// Recibir los datos enviados por fetch/AJAX en formato JSON
$inputData = file_get_contents('php://input');
$input = json_decode($inputData, true);

if (!$input) {
    enviarRespuesta(['success' => false, 'error' => 'Formato JSON inválido']);
}

$id              = (int)($input['id'] ?? 0);
$nuevo_estado    = trim($input['estado'] ?? '');
$tiempo_estimado = isset($input['tiempo_estimado']) ? (int)$input['tiempo_estimado'] : null;

$estados_validos = ['pendiente', 'preparando', 'listo', 'entregado', 'cancelado'];

if (!$id || !in_array($nuevo_estado, $estados_validos, true)) {
    enviarRespuesta(['success' => false, 'error' => 'Datos de actualización inválidos']);
}

try {
    $db = getDB();

    // Obtener información y estado actual del pedido
    $stmt = $db->prepare("SELECT id, cliente_id, total, estado FROM pedidos WHERE id = ?");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        enviarRespuesta(['success' => false, 'error' => 'Pedido no encontrado']);
    }

    $estado_anterior = $pedido['estado'];

    // Iniciar transacción de BD para asegurar inconsistencias
    $db->beginTransaction();

    // Armar consulta SQL según los campos a actualizar
    $fields = ["estado = ?", "actualizado_en = NOW()"];
    $params = [$nuevo_estado];

    if ($tiempo_estimado !== null && $nuevo_estado === 'preparando') {
        $fields[] = "tiempo_estimado = ?";
        $params[] = $tiempo_estimado;
    }

    if ($nuevo_estado === 'entregado') {
        $fields[] = "entregado_en = NOW()";
    }

    $params[] = $id;

    // 1. Actualizar el estado del pedido
    $sqlUpd = "UPDATE pedidos SET " . implode(', ', $fields) . " WHERE id = ?";
    $upd = $db->prepare($sqlUpd);
    $upd->execute($params);

    // 2. SISTEMA DE PUNTOS: Otorgar puntos si pasa a 'entregado' y pertenece a un cliente
    $puntos_ganados = 0;
    if ($nuevo_estado === 'entregado' && $estado_anterior !== 'entregado' && !empty($pedido['cliente_id'])) {
        // Regla: 1 punto por cada $10 consumidos
        $puntos_ganados = (int)floor($pedido['total'] / 10);

        if ($puntos_ganados > 0) {
            $updPuntos = $db->prepare("UPDATE usuarios_cliente SET puntos = puntos + ? WHERE id = ?");
            $updPuntos->execute([$puntos_ganados, $pedido['cliente_id']]);
        }
    }

    // Confirmar la transacción
    $db->commit();

    enviarRespuesta([
        'success'        => true,
        'nuevo_estado'   => $nuevo_estado,
        'puntos_ganados' => $puntos_ganados
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    enviarRespuesta(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
}
?>