<?php
require_once __DIR__ . '/../config/auth_check.php'; 
// getDB() ya viene disponible gracias a que auth_check incluye a config.php
$db = getDB();

$tipo = $_GET['tipo'] ?? 'dia';

// Rango de fechas según tipo de corte
switch ($tipo) {
    case 'semana':
        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d', strtotime('sunday this week'));
        $label = 'Semanal (' . date('d/m', strtotime($desde)) . ' – ' . date('d/m/Y', strtotime($hasta)) . ')';
        break;
    case 'mes':
        $desde = date('Y-m-01');
        $hasta = date('Y-m-t');
        $label = 'Mensual – ' . date('F Y');
        break;
    default:
        $tipo  = 'dia';
        $desde = date('Y-m-d');
        $hasta = date('Y-m-d');
        $label = 'Diario – ' . date('d/m/Y');
}

// Resumen general
$resumen = $db->prepare("
    SELECT 
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN estado='entregado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado='cancelado' THEN 1 ELSE 0 END) as cancelados,
        SUM(CASE WHEN estado='entregado' THEN total ELSE 0 END) as ingresos_totales,
        AVG(CASE WHEN estado='entregado' THEN total ELSE NULL END) as ticket_promedio
    FROM pedidos WHERE DATE(creado_en) BETWEEN ? AND ?
");
$resumen->execute([$desde, $hasta]);
$res = $resumen->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Corte <?= ucfirst($tipo) ?> – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #0b0b0b;
  --card: #1c1c1c;
  --border: #2a2a2a;
  --accent: #e8b86d;
  --text: #f0ede8;
  --muted: #7a7060;
}

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  padding: 32px;
  max-width: 900px;
  margin: 0 auto;
}

.topnav {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.back-btn {
  color: var(--muted);
  text-decoration: none;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.back-btn:hover { color: var(--text); }

.tipo-btns {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.tipo-btn {
  padding: 6px 14px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--muted);
  font-family: 'DM Sans', sans-serif;
  font-size: 12px;
  cursor: pointer;
  text-decoration: none;
  transition: all .15s;
}

.tipo-btn.active, .tipo-btn:hover {
  border-color: var(--accent);
  color: var(--accent);
}

/* Print button */
.print-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  background: linear-gradient(135deg, #e8b86d, #c9956a);
  color: #0f0f0f;
  border: none;
  border-radius: 8px;
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  margin-bottom: 28px;
}

.print-btn:hover { opacity: .9; }

/* Report styling */
.report-header {
  text-align: center;
  padding: 28px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 20px;
}

.report-header h1 {
  font-family: 'Playfair Display', serif;
  font-size: 28px;
  color: var(--accent);
}

.report-header .sub {
  color: var(--muted);
  font-size: 13px;
  margin-top: 6px;
}

.section {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
}

.section h2 {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.stat-box {
  text-align: center;
  padding: 16px;
  background: rgba(255,255,255,0.02);
  border-radius: 8px;
  border: 1px solid var(--border);
}

.stat-box .val {
  font-size: 24px;
  font-weight: 600;
  color: var(--accent);
}

.stat-box .lbl {
  font-size: 11px;
  color: var(--muted);
  margin-top: 4px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  text-align: left;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--muted);
  background: rgba(255,255,255,0.02);
}

td {
  padding: 10px 12px;
  border-top: 1px solid var(--border);
  font-size: 13px;
}

.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
}

.badge.entregado { background: rgba(109,191,138,0.15); color: #6dbf8a; }
.badge.cancelado { background: rgba(224,112,112,0.1); color: #e07070; }
.badge.pendiente { background: rgba(232,184,109,0.15); color: #e8b86d; }
.badge.preparando{ background: rgba(109,176,232,0.15); color: #6db0e8; }
.badge.listo     { background: rgba(109,191,138,0.2); color: #5fb87a; }

.total-row td {
  font-weight: 600;
  color: var(--accent);
  border-top: 2px solid var(--border);
}

@media print {
  body { background: white; color: #111; padding: 20px; }
  .topnav, .print-btn { display: none; }
  .section, .report-header {
    background: white;
    border: 1px solid #ddd;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .stat-box { background: #f8f8f8 !important; }
  :root {
    --text: #111;
    --muted: #666;
    --accent: #b5822a;
    --border: #ddd;
  }
}
</style>
</head>
<body>

<div class="topnav">
  <a class="back-btn" href="dashboard.php">← Regresar</a>
  <div class="tipo-btns">
    <a href="corte.php?tipo=dia" class="tipo-btn <?= $tipo==='dia'?'active':'' ?>">Día</a>
    <a href="corte.php?tipo=semana" class="tipo-btn <?= $tipo==='semana'?'active':'' ?>">Semana</a>
    <a href="corte.php?tipo=mes" class="tipo-btn <?= $tipo==='mes'?'active':'' ?>">Mes</a>
  </div>
</div>

<button class="print-btn" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>

<!-- ENCABEZADO -->
<div class="report-header">
  <h1>🍽️ RestaurApp</h1>
  <div class="sub">Corte de caja — <?= $label ?></div>
  <div class="sub" style="margin-top:4px;">Generado el <?= date('d/m/Y H:i') ?></div>
</div>

<!-- RESUMEN GENERAL -->
<div class="section">
  <h2>📊 Resumen general</h2>
  <div class="stats-row">
    <div class="stat-box">
      <div class="val">$<?= number_format($res['ingresos_totales'] ?? 0, 2) ?></div>
      <div class="lbl">Ingresos totales</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $res['total_pedidos'] ?? 0 ?></div>
      <div class="lbl">Pedidos totales</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $res['completados'] ?? 0 ?></div>
      <div class="lbl">Completados</div>
    </div>
    <div class="stat-box">
      <div class="val">$<?= number_format($res['ticket_promedio'] ?? 0, 2) ?></div>
      <div class="lbl">Ticket promedio</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $res['cancelados'] ?? 0 ?></div>
      <div class="lbl">Cancelados</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $tipo === 'dia' ? date('d/m/Y') : ($desde != $hasta ? date('d/m', strtotime($desde)) . '–' . date('d/m', strtotime($hasta)) : date('d/m/Y', strtotime($desde))) ?></div>
      <div class="lbl">Período</div>
    </div>
  </div>
</div>

<?php if ($tipo !== 'dia' && !empty($ingresos_dia)): ?>
<!-- INGRESOS POR DÍA -->
<div class="section">
  <h2>📅 Ingresos por día</h2>
  <table>
    <thead><tr><th>Fecha</th><th>Pedidos</th><th>Ingresos</th></tr></thead>
    <tbody>
      <?php $gran_total = 0; foreach ($ingresos_dia as $d): $gran_total += $d['ingresos']; ?>
      <tr>
        <td><?= date('d/m/Y (l)', strtotime($d['fecha'])) ?></td>
        <td><?= $d['pedidos'] ?></td>
        <td>$<?= number_format($d['ingresos'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td>TOTAL</td>
        <td></td>
        <td>$<?= number_format($gran_total, 2) ?></td>
      </tr>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- PRODUCTOS VENDIDOS -->
<div class="section">
  <h2>🏆 Detalle de productos vendidos</h2>
  <?php if (empty($prod_vendidos)): ?>
    <p style="color:var(--muted);font-size:13px;">Sin ventas en este período.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Producto</th><th>Categoría</th><th>Precio Unit.</th><th>Cant.</th><th>Total</th></tr></thead>
    <tbody>
      <?php $total_gen = 0; foreach ($prod_vendidos as $pv): $total_gen += $pv['total_generado']; ?>
      <tr>
        <td><?= htmlspecialchars($pv['nombre']) ?></td>
        <td style="color:var(--muted);"><?= htmlspecialchars($pv['categoria']) ?></td>
        <td>$<?= number_format($pv['precio_unitario'], 2) ?></td>
        <td><?= $pv['cantidad_total'] ?></td>
        <td>$<?= number_format($pv['total_generado'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td colspan="4">TOTAL INGRESOS</td>
        <td>$<?= number_format($total_gen, 2) ?></td>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- REGISTRO DE PEDIDOS -->
<div class="section">
  <h2>📋 Registro completo de pedidos</h2>
  <?php if (empty($lista_pedidos)): ?>
    <p style="color:var(--muted);font-size:13px;">Sin pedidos en este período.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>#Orden</th><th>Mesa</th><th>Total</th><th>Estado</th><th>Fecha/Hora</th></tr></thead>
    <tbody>
      <?php foreach ($lista_pedidos as $lp): ?>
      <tr>
        <td style="font-weight:500;"><?= htmlspecialchars($lp['numero_orden']) ?></td>
        <td>Mesa <?= $lp['mesa'] ?></td>
        <td>$<?= number_format($lp['total'], 2) ?></td>
        <td><span class="badge <?= $lp['estado'] ?>"><?= ucfirst($lp['estado']) ?></span></td>
        <td style="color:var(--muted);font-size:12px;"><?= date('d/m H:i', strtotime($lp['creado_en'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

</body>
</html>