<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Estadísticas del día
$hoy = date('Y-m-d');

// 1. Métricas del día
$stats_dia = $db->prepare("
    SELECT 
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN estado='entregado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado='entregado' THEN total ELSE 0 END) as ingresos,
        SUM(CASE WHEN estado='pendiente' OR estado='preparando' THEN 1 ELSE 0 END) as activos
    FROM pedidos WHERE DATE(creado_en) = ?
");
$stats_dia->execute([$hoy]);
$stats = $stats_dia->fetch(PDO::FETCH_ASSOC);

$ingresos = $stats['ingresos'] ?? 0;
$total_pedidos = $stats['total_pedidos'] ?? 0;

// 2. Pedidos por hora
$por_hora = $db->prepare("
    SELECT HOUR(creado_en) as hora, COUNT(*) as cantidad, SUM(total) as ingresos
    FROM pedidos WHERE DATE(creado_en) = ?
    GROUP BY HOUR(creado_en) ORDER BY hora ASC
");
$por_hora->execute([$hoy]);
$pedidos_hora = $por_hora->fetchAll(PDO::FETCH_ASSOC);

// 3. Productos más vendidos (Usando pedido_items y subtotal real)
$top_prod = $db->prepare("
    SELECT p.nombre, SUM(pi.cantidad) as vendidos, SUM(pi.subtotal) as total_ing
    FROM pedido_items pi
    JOIN productos p ON p.id = pi.producto_id
    JOIN pedidos pe ON pe.id = pi.pedido_id
    WHERE DATE(pe.creado_en) = ? AND pe.estado = 'entregado'
    GROUP BY p.id, p.nombre 
    ORDER BY vendidos DESC LIMIT 8
");
$top_prod->execute([$hoy]);
$top_productos = $top_prod->fetchAll(PDO::FETCH_ASSOC);

// 4. Últimos 10 pedidos con relación a la tabla mesas
$ultimos = $db->prepare("
    SELECT pe.*, m.numero as mesa_num
    FROM pedidos pe 
    JOIN mesas m ON m.id = pe.mesa_id
    ORDER BY pe.creado_en DESC LIMIT 10
");
$ultimos->execute();
$ultimos_pedidos = $ultimos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – RestaurantApp Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg-app: #f8fafc;
  --sidebar-bg: #031038;
  --sidebar-w: 240px;
  --card-bg: #ffffff;
  --card-border: #e2e8f0;
  --text-main: #0f172a;
  --text-muted: #64748b;
  --text-light: #94a3b8;
  --primary: #0052cc;
  --green: #10b981;
  --amber: #f59e0b;
  --red: #ef4444;
}

body {
  background: var(--bg-app);
  color: var(--text-main);
  font-family: 'Plus Jakarta Sans', sans-serif;
  display: flex;
  min-height: 100vh;
  font-size: 14px;
}

/* Sidebar */
.sidebar {
  width: var(--sidebar-w);
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0;
  height: 100vh;
  z-index: 100;
  padding: 24px 16px;
}

.sidebar-logo {
  padding: 0 8px 24px 8px;
}

.sidebar-logo .name {
  font-size: 20px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.5px;
}

.sidebar-logo .role {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-light);
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-top: 4px;
}

.nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  color: #94a3b8;
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 600;
  transition: all .2s ease;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
}

.nav-item:hover {
  color: #ffffff;
}

.nav-item.active {
  background: #0d286d;
  color: #ffffff;
}

.nav-item span.icon { 
  font-size: 16px; 
}

.sidebar-bottom {
  padding-top: 16px;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  color: #ef4444;
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 600;
  transition: background .2s;
}

.logout-btn:hover { 
  background: rgba(239, 68, 68, 0.1); 
}

/* Main Content */
.main {
  margin-left: var(--sidebar-w);
  flex: 1;
  padding: 32px 40px;
  max-width: calc(100% - var(--sidebar-w));
}

.topbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 28px;
}

.page-title {
  font-size: 26px;
  font-weight: 800;
  color: var(--text-main);
  letter-spacing: -0.5px;
}

.date-badge {
  background: #ffffff;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-muted);
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

/* Stats grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 28px;
}

.stat-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 20px;
  position: relative;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.stat-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 10px;
}

.stat-value {
  font-size: 28px;
  font-weight: 800;
  color: var(--text-main);
  line-height: 1;
}

.stat-value.money::before { 
  content: '$'; 
  font-size: 18px; 
  color: var(--text-muted); 
  margin-right: 2px; 
}

.stat-icon {
  position: absolute;
  top: 16px; 
  right: 16px;
  font-size: 24px;
  opacity: .3;
}

/* Charts row */
.charts-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 28px;
}

.chart-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 22px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.chart-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Bar chart */
.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  height: 130px;
}

.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  height: 100%;
  justify-content: flex-end;
}

.bar {
  width: 100%;
  background: var(--primary);
  border-radius: 4px 4px 0 0;
  min-height: 4px;
  transition: height .3s ease;
}

.bar-label {
  font-size: 10px;
  font-weight: 600;
  color: var(--text-muted);
}

/* Productos list */
.prod-list { 
  display: flex; 
  flex-direction: column; 
  gap: 12px; 
}

.prod-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.prod-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-main);
  min-width: 110px;
}

.prod-bar-wrap {
  flex: 1;
  height: 8px;
  background: #f1f5f9;
  border-radius: 10px;
  overflow: hidden;
}

.prod-bar {
  height: 100%;
  background: var(--primary);
  border-radius: 10px;
}

.prod-count {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  width: 50px;
  text-align: right;
  flex-shrink: 0;
}

/* Table */
.table-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  margin-bottom: 28px;
}

.table-header {
  padding: 18px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.table-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-main);
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  text-align: left;
  padding: 12px 24px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--text-muted);
  background: #f8fafc;
  border-top: 1px solid var(--card-border);
  border-bottom: 1px solid var(--card-border);
}

td {
  padding: 14px 24px;
  border-bottom: 1px solid var(--card-border);
  font-size: 13px;
  font-weight: 500;
  color: var(--text-main);
}

tr:last-child td {
  border-bottom: none;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
}

.badge.pendiente  { background: #fef3c7; color: #d97706; }
.badge.preparando { background: #e0f2fe; color: #0284c7; }
.badge.listo      { background: #d1fae5; color: #059669; }
.badge.entregado  { background: #ecfdf5; color: #10b981; }
.badge.cancelado  { background: #fee2e2; color: #dc2626; }

/* Cortes section */
.cortes-section {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 22px 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  margin-bottom: 24px;
}

.cortes-title {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-main);
}

.cortes-btns {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.corte-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 8px;
  border: 1px solid var(--card-border);
  background: #ffffff;
  color: var(--text-main);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
  text-decoration: none;
}

.corte-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.corte-btn.primary {
  background: var(--primary);
  color: #ffffff;
  border-color: transparent;
}

.corte-btn.primary:hover { 
  background: #0043a8; 
}

/* Live badge */
.live-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--green);
  margin-right: 6px;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .3; }
}

@media (max-width: 1100px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .charts-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="name">🍽️ RestaurApp</div>
    <div class="role">Subgerente</div>
  </div>
  <nav class="nav">
    <a class="nav-item active" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a class="nav-item" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span class="icon">🪑</span> Mesas & QR</a>
    <a class="nav-item" href="menu.php"><span class="icon">🍽️</span> Menú</a>
    <a class="nav-item" href="corte.php"><span class="icon">💵</span> Corte de Caja</a>
  </nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div>
      <div class="page-title">Dashboard</div>
      <div style="color:var(--text-muted);font-size:13px;margin-top:2px;">
        <span class="live-dot"></span>Actualizando en tiempo real
      </div>
    </div>
    <div class="date-badge">📅 <?= date('d \d\e F \d\e Y') ?></div>
  </div>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <span class="stat-icon">💰</span>
      <div class="stat-label">Ingresos del día</div>
      <div class="stat-value money"><?= number_format($stats['ingresos'] ?? 0, 2) ?></div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">📋</span>
      <div class="stat-label">Total pedidos</div>
      <div class="stat-value"><?= $stats['total_pedidos'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">✅</span>
      <div class="stat-label">Completados</div>
      <div class="stat-value" style="color: var(--green);"><?= $stats['completados'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">⏳</span>
      <div class="stat-label">En proceso</div>
      <div class="stat-value" style="color: var(--amber);"><?= $stats['activos'] ?? 0 ?></div>
    </div>
  </div>

  <!-- CHARTS ROW 1: Pedidos por hora & Top Productos -->
  <div class="charts-row">
    <!-- Pedidos por hora -->
    <div class="chart-card">
      <div class="chart-title">📊 Pedidos por hora (hoy)</div>
      <?php $max_pedidos = max(array_column($pedidos_hora, 'cantidad') ?: [1]); ?>
      <div class="bar-chart">
        <?php if (empty($pedidos_hora)): ?>
          <div style="color:var(--text-muted);font-size:13px;width:100%;text-align:center;padding:40px 0;">Sin pedidos hoy</div>
        <?php else: ?>
          <?php foreach ($pedidos_hora as $ph): ?>
            <div class="bar-col">
              <div style="font-size:10px;color:var(--text-muted);"><?= $ph['cantidad'] ?></div>
              <div class="bar" style="height:<?= round(($ph['cantidad']/$max_pedidos)*100) ?>%;"></div>
              <div class="bar-label"><?= str_pad($ph['hora'],2,'0',STR_PAD_LEFT) ?>h</div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top productos -->
    <div class="chart-card">
      <div class="chart-title">🔥 Productos más vendidos (hoy)</div>
      <?php if (empty($top_productos)): ?>
        <div style="color:var(--text-muted);font-size:13px;padding:20px 0;">Sin ventas completadas hoy</div>
      <?php else: ?>
        <?php $max_v = max(array_column($top_productos, 'vendidos')); ?>
        <div class="prod-list">
          <?php foreach ($top_productos as $i => $p): ?>
          <div class="prod-item">
            <span style="width: 20px; font-size: 11px; color: var(--text-muted); text-align: right; flex-shrink: 0;"><?= $i+1 ?></span>
            <span class="prod-name" style="min-width: 120px;"><?= htmlspecialchars($p['nombre']) ?></span>
            <div class="prod-bar-wrap">
              <div class="prod-bar" style="width:<?= round(($p['vendidos']/$max_v)*100) ?>%;"></div>
            </div>
            <span class="prod-count"><?= $p['vendidos'] ?> uds</span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ÚLTIMOS PEDIDOS -->
  <div class="table-card">
    <div class="table-header">
      <h3>📋 Registro de pedidos recientes</h3>
      <a href="pedidos.php" style="color:var(--primary);font-size:13px;font-weight:600;text-decoration:none;">Ver todos →</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>Orden</th>
          <th>Mesa</th>
          <th>Total</th>
          <th>Estado</th>
          <th>Hora</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ultimos_pedidos)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px;">Sin pedidos aún</td></tr>
        <?php else: ?>
          <?php foreach ($ultimos_pedidos as $p): ?>
          <tr>
            <td style="font-weight:700;"><?= htmlspecialchars($p['numero_orden']) ?></td>
            <td>Mesa <?= $p['mesa_num'] ?></td>
            <td>$<?= number_format($p['total'], 2) ?></td>
            <td><span class="badge <?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
            <td style="color:var(--text-muted);"><?= date('H:i', strtotime($p['creado_en'])) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- CORTES DE CAJA -->
  <div class="cortes-section">
    <div class="cortes-title">🖨️ Cortes de caja e impresión</div>
    <div class="cortes-btns">
      <a href="corte.php?tipo=dia" class="corte-btn primary">📄 Corte del día</a>
      <a href="corte.php?tipo=semana" class="corte-btn">📅 Corte semanal</a>
      <a href="corte.php?tipo=mes" class="corte-btn">📆 Corte mensual</a>
    </div>
  </div>
</main>

<script>
(function() {
    const TIEMPO_INACTIVIDAD = 3 * 60 * 1000;
    const INTERVALO_RECARGA = 30000;
    
    let temporizadorInactividad;
    let temporizadorRecarga;

    function cerrarSesion() {
        window.location.href = 'logout.php?reason=inactividad';
    }

    function reiniciarInactividad() {
        clearTimeout(temporizadorInactividad);
        temporizadorInactividad = setTimeout(cerrarSesion, TIEMPO_INACTIVIDAD);
    }

    const eventos = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
    eventos.forEach(evento => {
        window.addEventListener(evento, reiniciarInactividad, true);
    });

    reiniciarInactividad();

    temporizadorRecarga = setInterval(() => {
        if (!document.hidden) {
            location.reload();
        }
    }, INTERVALO_RECARGA);
})();
</script>
</body>
</html>