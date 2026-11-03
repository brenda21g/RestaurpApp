<?php
/**
 * Archivo: gerente/cliente_ver.php
 * Descripción: Vista para mostrar el detalle completo de un cliente, su consumo y sus pedidos registrados en el ERP.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: clientes.php");
    exit;
}

// 1. Obtener datos del cliente
$stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header("Location: clientes.php");
    exit;
}

// 2. Obtener las órdenes y el consumo del cliente sincronizadas desde el ERP
$stmt_pedidos = $db->prepare("
    SELECT * FROM erp_ordenes 
    WHERE cliente_id = ? 
    ORDER BY creado_en DESC
");
$stmt_pedidos->execute([$id]);
$pedidos_cliente = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);

// Calcular consumo total
$consumo_total = 0;
foreach ($pedidos_cliente as $p) {
    if ($p['estado'] === 'Procesada' || $p['estado'] === 'Completada') {
        $consumo_total += $p['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Cliente – Panel Gerencial</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --color-primary: #2563eb;
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
            align-items: flex-start;
            min-height: 100vh;
            padding: 40px 20px;
            font-size: 14px;
        }

        .container {
            width: 100%;
            max-width: 750px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            padding: 28px;
            border-radius: var(--radius);
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

        .grid-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 16px;
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
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
        }

        h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 14px;
            color: var(--text-main);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            background: #f1f5f9;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            background: #e1fdf4;
            color: #0d9488;
        }

        .consumo-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div>
        <a href="clientes.php" class="back">← Volver a clientes</a>
    </div>
    
    <!-- Tarjeta de Datos del Cliente -->
    <div class="card">
        <h2><?= htmlspecialchars($cliente['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
        <hr style="border-color: var(--border-color); margin: 16px 0; border-width: 0.5px;">
        
        <div class="grid-info">
            <div class="info-group">
                <label>Correo Electrónico</label>
                <span><?= htmlspecialchars($cliente['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-group">
                <label>Teléfono</label>
                <span><?= htmlspecialchars($cliente['telefono'] ?? 'No registrado', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-group">
                <label>Etapa CRM</label>
                <span><?= htmlspecialchars($cliente['etapa_crm'] ?? 'Prospecto', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-group">
                <label>Estado</label>
                <span><?= htmlspecialchars($cliente['estado'] ?? 'Activo', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="consumo-box">
            <div>
                <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Consumo Total Acumulado (ERP)</div>
                <div style="font-size: 20px; font-weight: 700; color: var(--color-primary); margin-top: 4px;">$<?= number_format($consumo_total, 2) ?></div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Órdenes Realizadas</div>
                <div style="font-size: 20px; font-weight: 700; color: var(--text-main); margin-top: 4px;"><?= count($pedidos_cliente) ?></div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Historial de Órdenes y Consumo -->
    <div class="card">
        <h3>📦 Historial de Pedidos del Cliente</h3>
        <?php if (empty($pedidos_cliente)): ?>
            <p style="color: var(--text-muted); font-size: 13px; padding: 10px 0;">Este cliente aún no registra órdenes de compra en el sistema.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos_cliente as $p): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($p['folio'], ENT_QUOTES, 'UTF-8') ?></b></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($p['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge"><?= htmlspecialchars($p['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td style="font-weight: 600;">$<?= number_format($p['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>