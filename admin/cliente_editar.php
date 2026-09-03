<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $etapa = $_POST['etapa_crm'];
    $estado = $_POST['estado'];

    $update = $db->prepare("UPDATE usuarios_cliente SET nombre = ?, email = ?, telefono = ?, etapa_crm = ?, estado = ? WHERE id = ?");
    $update->execute([$nombre, $email, $telefono, $etapa, $estado, $id]);
    header("Location: clientes.php");
    exit;
}

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
<title>Editar Cliente</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root { --bg: #0b0b0b; --surface: #141414; --card: #1c1c1c; --border: #2a2a2a; --accent: #e8b86d; --text: #f0ede8; --muted: #7a7060; }
    body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    form { background: var(--card); border: 1px solid var(--border); padding: 30px; border-radius: 12px; width: 100%; max-width: 450px; display: flex; flex-direction: column; gap: 16px; }
    input, select { background: var(--surface); border: 1px solid var(--border); color: var(--text); padding: 10px 12px; border-radius: 8px; font-size: 13px; outline: none; }
    button { background: var(--accent); color: #000; padding: 12px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 5px; }
    .back { color: var(--muted); text-decoration: none; font-size: 13px; }
    .back:hover { color: var(--accent); }
    h2 { font-size: 20px; color: var(--accent); }
</style>
</head>
<body>
<form method="POST">
    <a href="clientes.php" class="back">← Volver</a>
    <h2>Editar Cliente / Prospecto</h2>
    <input type="text" name="nombre" value="<?= htmlspecialchars($cliente['nombre']) ?>" required placeholder="Nombre">
    <input type="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>" required placeholder="Correo">
    <input type="text" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>" placeholder="Teléfono">
    <select name="etapa_crm">
        <option value="Prospecto" <?= ($cliente['etapa_crm'] ?? '') === 'Prospecto' ? 'selected' : '' ?>>Prospecto</option>
        <option value="Cliente" <?= ($cliente['etapa_crm'] ?? '') === 'Cliente' ? 'selected' : '' ?>>Cliente</option>
    </select>
    <select name="estado">
        <option value="Activo" <?= ($cliente['estado'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo</option>
        <option value="Inactivo" <?= ($cliente['estado'] ?? '') === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
        <option value="Baja" <?= ($cliente['estado'] ?? '') === 'Baja' ? 'selected' : '' ?>>Baja</option>
    </select>
    <button type="submit">Actualizar Cambios</button>
</form>
</body>
</html>