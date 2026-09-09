<?php
// ==========================================================================
// VISTA: Detalle Completo de Cliente
// ==========================================================================
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
    <title>Detalle de Cliente – Restaurant App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ==========================================================================
           VARIABLES Y RESET GENERAL
           ========================================================================== */
        :root {
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-text: #94a3b8;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --color-primary: #2563eb;
            --color-success: #10b981;
            --color-danger: #ef4444;
            --sidebar-w: 250px;
            --radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            font-size: 14px;
        }

        /* ==========================================================================
           TARJETA DE DETALLE
           ========================================================================== */
        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            padding: 32px;
            border-radius: var(--radius);
            width: 100%;
            max-width: 450px;
            box-shadow: var(--shadow-sm);
        }

        .back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 16px;
            transition: color 0.2s;
        }

        .back:hover {
            color: var(--color-primary);
        }

        .info-group {
            margin-bottom: 16px;
        }

        .info-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-group span {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }

        h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-main);
        }
    </style>
</head>
<body>
<div class="card">
    <a href="clientes.php" class="back">← Volver a clientes</a>
    <h2><?= htmlspecialchars($cliente['nombre']) ?></h2>
    <hr style="border-color: var(--border-color); margin: 16px 0; border-width: 0.5px;">
    
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