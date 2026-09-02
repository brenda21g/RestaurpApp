<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();
$id = $_GET['id'] ?? null;

$stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header("Location: clientes.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle de Cliente</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root { --bg: #0b0b0b; --surface: #141414; --card: #1c1c1c; --border: #2a2a2a; --accent: #e8b86d; --text: #f0ede8; --muted: #7a7060; }
    body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
    .card { background: var(--card); border: 1px solid var(--border); padding: 30px; border-radius: 12px; width: 100%; max-width: 450px; }
    .back { color: var(--accent); text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 15px; }
    .info-group { margin-bottom: 16px; }
    .info-group label { display: block; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
    .info-group span { font-size: 15px; font-weight: 500; }
    h2 { font-size: 22px; margin-bottom: 5px; }
</style>
</head>
<body>
<div class="card">
    <a href="clientes.php" class="back">← Volver a clientes</a>
    <h2><?= htmlspecialchars($cliente['nombre']) ?></h2>
    <hr style="border-color: var(--border); margin: 15px 0; border-width: 0.5px;">
    
    <div class="info-group">
        <label>Correo Electrónico</label>
        <span><?= htmlspecialchars($cliente['email']) ?></span>
    </div>
    <div class="info-group">
        <label>Teléfono</label>
        <span><?= htmlspecialchars($cliente['telefono'] ?? 'No registrado') ?></span>
    </div>
    <div class="info-group">
        <label>Etapa CRM</label>
        <span><?= htmlspecialchars($cliente['etapa_crm'] ?? 'Prospecto') ?></span>
    </div>
    <div class="info-group">
        <label>Estado</label>
        <span><?= htmlspecialchars($cliente['estado'] ?? 'Activo') ?></span>
    </div>
    <div class="info-group">
        <label>Fecha de Registro</label>
        <span><?= htmlspecialchars($cliente['creado_en']) ?></span>
    </div>
</div>
</body>
</html>