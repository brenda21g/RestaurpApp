<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['cliente_id'])) { header('Location: login.php'); exit; }

$db = getDB();
$cliente_id = (int)$_SESSION['cliente_id'];
$mensaje_exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_ticket'])) {
    $asunto = sanitize($_POST['asunto'] ?? '');
    $mensaje_t = sanitize($_POST['mensaje'] ?? '');
    
    if (!empty($asunto) && !empty($mensaje_t)) {
        $stmt = $db->prepare("INSERT INTO tickets_soporte (cliente_id, asunto, mensaje, estado, creado_en) VALUES (?, ?, ?, 'En proceso', NOW())");
        $stmt->execute([$cliente_id, $asunto, $mensaje_t]);
        $mensaje_exito = "¡Ticket enviado con éxito! Soporte le dará seguimiento pronto.";
    }
}

$stmt_t = $db->prepare("SELECT * FROM tickets_soporte WHERE cliente_id = ? ORDER BY creado_en DESC");
$stmt_t->execute([$cliente_id]);
$mis_tickets = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Atención a Clientes (Tickets) – RestaurApp</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body { background: #fdfdfd; color: #1a1510; font-family: 'DM Sans', sans-serif; padding: 20px; max-width: 500px; margin: 0 auto; }
.card { background: #fff; border: 1px solid #ede8e0; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
input, select, textarea { width: 100%; padding: 10px; margin-top: 6px; margin-bottom: 14px; border: 1px solid #ede8e0; border-radius: 8px; font-family: 'DM Sans', sans-serif; }
button { background: #c8502a; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: 600; cursor: pointer; }
.ticket-item { background: #f9f9f9; border-left: 4px solid #c8502a; padding: 10px; margin-bottom: 8px; border-radius: 6px; font-size: 13px; }
</style>
</head>
<body>
<a href="index.php" style="color:#c8502a; text-decoration:none; font-size:14px;">← Volver al Menú</a>
<h2 style="margin: 16px 0;">💬 Soporte y Tickets</h2>

<?php if($mensaje_exito): ?><div style="background:#e4f7f0; color:#07543f; padding:10px; border-radius:8px; margin-bottom:12px;"><?= $mensaje_exito ?></div><?php endif; ?>

<div class="card">
    <h3>Nuevo Ticket de Ayuda</h3>
    <form method="POST">
        <input type="hidden" name="crear_ticket" value="1">
        <label>Asunto / Duda</label>
        <select name="asunto">
            <option value="Duda sobre mi pedido">Duda sobre mi pedido</option>
            <option value="Cambio de producto">Cambio de producto</option>
            <option value="Facturación">Facturación</option>
            <option value="Problema con monedero">Problema con monedero</option>
        </select>
        <label>Mensaje / Consulta</label>
        <textarea name="mensaje" placeholder="Escribe tu consulta detallada..." required rows="3"></textarea>
        <button type="submit">Enviar Ticket (Historial Permanente)</button>
    </form>
</div>

<div class="card">
    <h3>Mis Tickets Registrados</h3>
    <?php if(empty($mis_tickets)): ?>
        <p style="color:#8a7a6a; font-size:13px; margin-top:10px;">No tienes tickets abiertos.</p>
    <?php else: foreach($mis_tickets as $t): ?>
        <div class="ticket-item">
            <b>#<?= $t['id'] ?> - <?= htmlspecialchars($t['asunto']) ?></b><br>
            <span style="color:#8a7a6a;"><?= htmlspecialchars($t['mensaje']) ?></span><br>
            <small style="color: #c8502a; font-weight:bold;">Estado: <?= $t['estado'] ?></small>
        </div>
    <?php endforeach; endif; ?>
</div>

</body>
</html>