<?php
/**
 * Archivo: scm/dashboard.php
 * Descripción: Panel de métricas globales y nivel de madurez con control de roles (Gerencia vs Logística/Encargado).
 */
require_once __DIR__ . '/../config/auth_check.php';

// Definir permisos: Gerentes, Subgerentes y Encargados de logística tienen acceso al SCM
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_gerente_o_subgerente = in_array($rol_actual, ['gerente', 'subgerente'], true);

$total_prod = $db->query("SELECT COUNT(*) FROM scm_productos")->fetchColumn();
$total_prov = $db->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();
$total_ped = $db->query("SELECT COUNT(*) FROM scm_pedidos")->fetchColumn();
$alertas = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard SCM – Restaurant App</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --secondary: #000049;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
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
      background: var(--secondary);
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
      color: #ffffff;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-brand span {
      color: var(--primary);
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
      color: #94a3b8;
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.2s;
    }

    .sidebar-item:hover, .sidebar-item.active {
      background: rgba(2, 132, 199, 0.15);
      color: #ffffff;
    }

    .sidebar-item.active {
      color: var(--primary);
      font-weight: 600;
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

    .role-badge {
      background: rgba(2, 132, 199, 0.1);
      color: var(--primary);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
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
      letter-spacing: 0.5px;
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
      margin-bottom: 12px;
      color: var(--text);
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
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
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual)) ?></div>
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
        <h3>Nivel de Madurez SCM y Permisos</h3>
        <p style="color:var(--muted); line-height:1.6; margin-bottom: 10px;">Estado actual: <b>Optimizado / En Producción</b>. El sistema cuenta con control integrado de inventario, estrategias Push y Pull configuradas, trazabilidad de movimientos y gestión directa de órdenes de reposición a proveedores.</p>
        <?php if ($rol_actual === 'encargado'): ?>
            <p style="color: var(--warning); font-size: 13px; font-weight: 500;">⚠️ Estás ingresando con permisos de <b>Encargado de Logística</b>. Puedes consultar y registrar movimientos de stock, pero algunas configuraciones globales están reservadas para Gerencia.</p>
        <?php else: ?>
            <p style="color: var(--success); font-size: 13px; font-weight: 500;">✓ Acceso administrativo completo habilitado para la gestión de la cadena de suministro.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>