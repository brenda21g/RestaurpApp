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

// 5. Total de clientes registrados y desglose por estado para la gráfica
$stmt_clientes = $db->query("SELECT COUNT(*) FROM usuarios_cliente");
$totalClientes = $stmt_clientes->fetchColumn();

$stmt_clientes_estado = $db->query("SELECT estado, COUNT(*) as cantidad FROM usuarios_cliente GROUP BY estado");
$clientes_por_estado = $stmt_clientes_estado->fetchAll(PDO::FETCH_ASSOC);

// Mapeamos los resultados a un arreglo asociativo para usarlos fácilmente
$conteo_estados = ['Activo' => 0, 'Inactivo' => 0, 'Baja' => 0, 'Prospecto' => 0];
foreach ($clientes_por_estado as $ce) {
    $estado_db = $ce['estado'] ?: 'Activo'; // Por si viene nulo
    if (array_key_exists($estado_db, $conteo_estados)) {
        $conteo_estados[$estado_db] = $ce['cantidad'];
    } else {
        $conteo_estados[$estado_db] = $ce['cantidad']; // Por si hay algún estado personalizado adicional
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – RestaurantApp Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #0b0b0b;
  --surface: #141414;
  --card: #1c1c1c;
  --border: #2a2a2a;
  --accent: #e8b86d;
  --accent2: #c9956a;
  --green: #6dbf8a;
  --red: #e07070;
  --blue: #6db0e8;
  --text: #f0ede8;
  --muted: #7a7060;
  --sidebar-w: 240px;
}

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  display: flex;
  min-height: 100vh;
  font-size: 14px;
}

/* Sidebar */
.sidebar {
  width: var(--sidebar-w);
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0;
  height: 100vh;
  z-index: 100;
}

.sidebar-logo {
  padding: 24px 20px;
  border-bottom: 1px solid var(--border);
}

.sidebar-logo .name {
  font-family: 'Playfair Display', serif;
  font-size: 20px;
  color: var(--accent);
}

.sidebar-logo .role {
  font-size: 11px;
  color: var(--muted);
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-top: 2px;
}

.nav {
  padding: 16px 12px;
  flex: 1;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  color: var(--muted);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: all .15s;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  margin-bottom: 2px;
}

.nav-item:hover, .nav-item.active {
  background: rgba(232,184,109,0.08);
  color: var(--accent);
}

.nav-item span.icon { font-size: 16px; }

.sidebar-bottom {
  padding: 16px 12px;
  border-top: 1px solid var(--border);
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  color: var(--red);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: background .15s;
}
.logout-btn:hover { background: rgba(224,112,112,0.1); }

/* Main */
.main {
  margin-left: var(--sidebar-w);
  flex: 1;
  padding: 28px 32px;
  max-width: calc(100% - var(--sidebar-w));
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
}

.page-title {
  font-family: 'Playfair Display', serif;
  font-size: 26px;
  color: var(--text);
}

.date-badge {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 16px;
  font-size: 13px;
  color: var(--muted);
}

/* Stats grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 28px;
}

.stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
}

.stat-card.green::before { background: var(--green); }
.stat-card.gold::before  { background: var(--accent); }
.stat-card.blue::before  { background: var(--blue); }
.stat-card.orange::before{ background: var(--accent2); }

.stat-label {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 8px;
}

.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: var(--text);
  line-height: 1;
}

.stat-value.money::before { content: '$'; font-size: 16px; color: var(--muted); margin-right: 2px; }

.stat-icon {
  position: absolute;
  top: 16px; right: 16px;
  font-size: 28px;
  opacity: .15;
}

/* Charts row */
.charts-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 28px;
}

.chart-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
}

.chart-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Bar chart */
.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  height: 120px;
}

.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  height: 100%;
  justify-content: flex-end;
}

.bar {
  width: 100%;
  background: linear-gradient(to top, var(--accent), var(--accent2));
  border-radius: 4px 4px 0 0;
  min-height: 4px;
  transition: height .3s ease;
}

.bar-label {
  font-size: 10px;
  color: var(--muted);
}

/* Productos / Clientes list (Barras horizontales) */
.prod-list { display: flex; flex-direction: column; gap: 10px; }

.prod-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.prod-name {
  font-size: 12px;
  color: var(--text);
  min-width: 90px;
}

.prod-bar-wrap {
  flex: 1;
  height: 8px;
  background: var(--border);
  border-radius: 4px;
  overflow: hidden;
}

.prod-bar {
  height: 100%;
  border-radius: 4px;
}

.prod-count {
  font-size: 12px;
  color: var(--muted);
  width: 45px;
  text-align: right;
  flex-shrink: 0;
}

/* Table */
.table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 24px;
}

.table-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.table-header h3 {
  font-size: 13px;
  font-weight: 600;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  text-align: left;
  padding: 10px 20px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--muted);
  background: rgba(255,255,255,0.02);
}

td {
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  font-size: 13px;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}

.badge.pendiente  { background: rgba(232,184,109,0.15); color: var(--accent); }
.badge.preparando { background: rgba(109,176,232,0.15); color: var(--blue); }
.badge.listo      { background: rgba(109,191,138,0.2);  color: var(--green); }
.badge.entregado  { background: rgba(109,191,138,0.1);  color: #4a9a67; }
.badge.cancelado  { background: rgba(224,112,112,0.1);  color: var(--red); }

/* Cortes section */
.cortes-section {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
}

.cortes-title {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
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
  padding: 10px 20px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all .15s;
  text-decoration: none;
}

.corte-btn:hover {
  border-color: var(--accent);
  color: var(--accent);
  background: rgba(232,184,109,0.05);
}

.corte-btn.primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: #0f0f0f;
  border-color: transparent;
}

.corte-btn.primary:hover { opacity: .9; color: #0f0f0f; }

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
    <div class="role">Administrador</div>
  </div>
<nav class="nav">
    <a class="nav-item active" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="interacciones.php"><span class="icon">💬</span> Interacciones</a>
    <a class="nav-item" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span class="icon">🪑</span> Mesas & QR</a>
    <a class="nav-item" href="menu.php"><span class="icon">🍽️</span> Menú</a>
    <a class="nav-item" href="corte.php"><span>💵</span> Corte de Caja</a>

    <?php if (isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] === 'super_admin'): ?>
        <a class="nav-item" href="usuarios.php"><span class="icon">🛡️</span> Administradores</a>
    <?php endif; ?>
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
      <div style="color:var(--muted);font-size:13px;margin-top:2px;">
        <span class="live-dot"></span>Actualizando en tiempo real
      </div>
    </div>
    <div class="date-badge">📅 <?= date('d \d\e F \d\e Y') ?></div>
  </div>

<!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card gold">
      <span class="stat-icon">💰</span>
      <div class="stat-label">Ingresos del día</div>
      <div class="stat-value money"><?= number_format($stats['ingresos'] ?? 0, 2) ?></div>
    </div>
    <div class="stat-card blue">
      <span class="stat-icon">📋</span>
      <div class="stat-label">Total pedidos</div>
      <div class="stat-value"><?= $stats['total_pedidos'] ?? 0 ?></div>
    </div>
    <div class="stat-card green">
      <span class="stat-icon">✅</span>
      <div class="stat-label">Completados</div>
      <div class="stat-value"><?= $stats['completados'] ?? 0 ?></div>
    </div>
    <div class="stat-card orange">
      <span class="stat-icon">⏳</span>
      <div class="stat-label">En proceso</div>
      <div class="stat-value"><?= $stats['activos'] ?? 0 ?></div>
    </div>
  </div>

  <!-- CHARTS ROW 1: Pedidos por hora & Top Productos -->
  <div class="charts-row">
    <!-- Pedidos por hora -->
    <div class="chart-card">
      <div class="chart-title">📊 Pedidos por hora (hoy)</div>
      <?php
        $max_pedidos = max(array_column($pedidos_hora, 'cantidad') ?: [1]);
      ?>
      <div class="bar-chart">
        <?php if (empty($pedidos_hora)): ?>
          <div style="color:var(--muted);font-size:13px;width:100%;text-align:center;padding:40px 0;">Sin pedidos hoy</div>
        <?php else: ?>
          <?php foreach ($pedidos_hora as $ph): ?>
            <div class="bar-col">
              <div style="font-size:10px;color:var(--muted);margin-bottom:4px;"><?= $ph['cantidad'] ?></div>
              <div class="bar" style="height:<?= round(($ph['cantidad']/$max_pedidos)*100) ?>px;"></div>
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
        <div style="color:var(--muted);font-size:13px;padding:20px 0;">Sin ventas completadas hoy</div>
      <?php else: ?>
        <?php $max_v = max(array_column($top_productos, 'vendidos')); ?>
        <div class="prod-list">
          <?php foreach ($top_productos as $i => $p): ?>
          <div class="prod-item">
            <span style="width: 20px; font-size: 11px; color: var(--muted); text-align: right; flex-shrink: 0;"><?= $i+1 ?></span>
            <span class="prod-name" style="min-width: 120px;"><?= htmlspecialchars($p['nombre']) ?></span>
            <div class="prod-bar-wrap">
              <div class="prod-bar" style="width:<?= round(($p['vendidos']/$max_v)*100) ?>%; background: linear-gradient(to right, var(--accent), var(--accent2));"></div>
            </div>
            <span style="font-size: 12px; color: var(--muted); width: 40px; text-align: right; flex-shrink: 0;"><?= $p['vendidos'] ?> uds</span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- CHARTS ROW 2: Desglose de Clientes por Estado -->
  <div class="charts-row" style="grid-template-columns: 1fr;">
    <div class="chart-card">
      <div class="chart-title" style="justify-content: space-between;">
        <span>👥 Estado general de Clientes (Total: <?= $totalClientes ?>)</span>
        <a href="clientes.php" style="color:var(--accent); font-size:12px; text-decoration:none;">Gestionar clientes →</a>
      </div>
      
      <?php 
        $max_clientes = max(array_values($conteo_estados)) ?: 1;
        // Definimos colores para cada estado
        $colores_estados = [
            'Activo'    => 'var(--green)',
            'Inactivo'  => 'var(--muted)',
            'Baja'      => 'var(--red)',
            'Prospecto' => 'var(--accent)'
        ];
      ?>
      
      <div class="prod-list" style="margin-top: 10px;">
        <?php foreach ($conteo_estados as $estado_nombre => $cantidad): ?>
          <?php 
            $porcentaje = ($totalClientes > 0) ? round(($cantidad / $totalClientes) * 100) : 0;
            $color_barra = $colores_estados[$estado_nombre] ?? 'var(--blue)';
          ?>
          <div class="prod-item">
            <span class="prod-name" style="width: 110px; font-weight: 500;"><?= htmlspecialchars($estado_nombre) ?></span>
            <div class="prod-bar-wrap">
              <div class="prod-bar" style="width: <?= round(($cantidad / $max_clientes) * 100) ?>%; background: <?= $color_barra ?>;"></div>
            </div>
            <span class="prod-count" style="width: 80px;"><?= $cantidad ?> (<?= $porcentaje ?>%)</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ÚLTIMOS PEDIDOS -->
  <div class="table-card">
    <div class="table-header">
      <h3>📋 Registro de pedidos recientes</h3>
      <a href="pedidos.php" style="color:var(--accent);font-size:12px;text-decoration:none;">Ver todos →</a>
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
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px;">Sin pedidos aún</td></tr>
        <?php else: ?>
          <?php foreach ($ultimos_pedidos as $p): ?>
          <tr>
            <td style="font-weight:500;"><?= htmlspecialchars($p['numero_orden']) ?></td>
            <td>Mesa <?= $p['mesa_num'] ?></td>
            <td>$<?= number_format($p['total'], 2) ?></td>
            <td><span class="badge <?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
            <td style="color:var(--muted);"><?= date('H:i', strtotime($p['creado_en'])) ?></td>
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
    // 3 minutos de inactividad (3 * 60 * 1000 ms)
    const TIEMPO_INACTIVIDAD = 3 * 60 * 1000;
    const INTERVALO_RECARGA = 30000; // 30 segundos
    
    let temporizadorInactividad;
    let temporizadorRecarga;

    function cerrarSesion() {
        window.location.href = 'logout.php?reason=inactividad';
    }

    function reiniciarInactividad() {
        clearTimeout(temporizadorInactividad);
        temporizadorInactividad = setTimeout(cerrarSesion, TIEMPO_INACTIVIDAD);
    }

    // Monitorear clicks, teclas y movimiento del cursor
    const eventos = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
    eventos.forEach(evento => {
        window.addEventListener(evento, reiniciarInactividad, true);
    });

    // Iniciar temporizador de cierre por inactividad
    reiniciarInactividad();

    // Recarga automática controlada (solo refresca datos si la pestaña está activa)
    temporizadorRecarga = setInterval(() => {
        if (!document.hidden) {
            location.reload();
        }
    }, INTERVALO_RECARGA);
})();
</script>
</body>
</html>