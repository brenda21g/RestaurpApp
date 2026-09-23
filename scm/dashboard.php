<?php
/**
 * Archivo: scm/dashboard.php
 * Descripción: Panel de métricas globales y nivel de madurez con Sidebar.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente']);
$db = getDB();

$total_prod = $db->query("SELECT COUNT(*) FROM scm_productos")->fetchColumn();
$total_prov = $db->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();
$total_ped = $db->query("SELECT COUNT(*) FROM scm_pedidos")->fetchColumn();
$alertas = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard SCM</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --seconfdary: #000049;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --danger: #ef4444;
      --success: #10b981;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', sans-serif;
    }

    body {
      background: var(--bg);
      color: var(--text);
      display: flex;
      min-height: 100vh;
      font-size: 14px;
    }

    .sidebar {
      width: 260px;
      background: var(--seconfdary);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      position: fixed;
      height: 100vh;
    }

    .sidebar-brand {
      padding: 24px;
      font-size: 18px;
      font-weight: 700;
      color: var(--primary);
      border-bottom: 1px solid var(--border);
    }

    .sidebar-menu {
      padding: 20px 10px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .sidebar-item {
      padding: 12px 16px;
      border-radius: 8px;
      color: var(--muted);
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.2s;
    }

    .sidebar-item:hover, .sidebar-item.active {
      background: #e0f2fe;
      color: var(--primary);
    }

    .main-content {
      margin-left: 260px;
      flex: 1;
      padding: 30px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }

    .header h1 {
      font-size: 22px;
      font-weight: 700;
      color: var(--text);
    }

    .metrics {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-bottom: 25px;
    }

    .metric-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .metric-title {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
    }

    .metric-value {
      font-size: 28px;
      font-weight: 700;
      color: var(--primary);
      margin-top: 8px;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .card h3 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--text);
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant App SCM</div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item active">📈 Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item">📦 Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        <a href="inventario.php" class="sidebar-item">📊 Inventario / Alertas</a>
        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color:var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1>📈 Dashboard y Métricas SCM</h1>
    </div>

    <div class="metrics">
        <div class="metric-card">
            <div class="metric-title">Productos SCM</div>
            <div class="metric-value"><?= $total_prod ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Proveedores</div>
            <div class="metric-value"><?= $total_prov ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Pedidos Reposición</div>
            <div class="metric-value"><?= $total_ped ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Alertas Stock</div>
            <div class="metric-value" style="color:var(--danger);"><?= $alertas ?></div>
        </div>
    </div>

    <div class="card">
        <h3>Nivel de Madurez SCM</h3>
        <p style="color:var(--muted); line-height:1.6;">Estado actual: <b>Optimizado / En Producción</b>. El sistema cuenta con control integrado de inventario, estrategias Push y Pull configuradas, trazabilidad de movimientos con motivo y gestión directa de órdenes de reposición a proveedores.</p>
    </div>
</div>

</body>
</html>