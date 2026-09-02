<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $etapa_crm = $_POST['etapa_crm'] ?? 'Prospecto';
    $estado = $_POST['estado'] ?? 'Activo';
    $password = md5($_POST['password'] ?? '123456'); // Password por defecto para altas manuales

    if (!empty($nombre) && !empty($email)) {
        $stmt = $db->prepare("INSERT INTO usuarios_cliente (nombre, email, password, telefono, etapa_crm, estado, email_confirmado) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $email, $password, $telefono, $etapa_crm, $estado]);
        header("Location: clientes.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Nuevo Cliente – RestaurApp Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root { --bg: #0b0b0b; --surface: #141414; --card: #1c1c1c; --border: #2a2a2a; --accent: #e8b86d; --text: #f0ede8; --muted: #7a7060; }
    body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    .form-card { background: var(--card); border: 1px solid var(--border); padding: 30px; border-radius: 12px; width: 100%; max-width: 450px; }
    h2 { font-size: 20px; margin-bottom: 20px; color: var(--accent); }
    .field { margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px; }
    label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); }
    input, select { background: var(--surface); border: 1px solid var(--border); color: var(--text); padding: 10px 12px; border-radius: 8px; font-size: 13px; outline: none; }
    .btn-submit { width: 100%; background: var(--accent); color: #000; padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px; }
    .back { display: inline-block; margin-bottom: 15px; color: var(--muted); text-decoration: none; font-size: 13px; }
    .back:hover { color: var(--accent); }
</style>
</head>
<body>
<div class="form-card">
    <a href="clientes.php" class="back">← Volver a clientes</a>
    <h2>Registrar Nuevo Cliente / Prospecto</h2>
    <form method="POST">
        <div class="field">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required placeholder="Ej. Juan Pérez">
        </div>
        <div class="field">
            <label>Correo electrónico</label>
            <input type="email" name="email" required placeholder="ejemplo@correo.com">
        </div>
        <div class="field">
            <label>Teléfono</label>
            <input type="text" name="telefono" placeholder="4491234567">
        </div>
        <div class="field">
            <label>Etapa CRM</label>
            <select name="etapa_crm">
                <option value="Prospecto">Prospecto</option>
                <option value="Cliente">Cliente</option>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="Activo">Activo</option>
                <option value="Inactivo">Inactivo</option>
                <option value="Baja">Baja</option>
            </select>
        </div>
        <button type="submit" class="btn-submit">Guardar Registro</button>
    </form>
</div>
</body>
</html>