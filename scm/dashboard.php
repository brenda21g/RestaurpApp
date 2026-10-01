<?php
/**
 * Archivo: scm/dashboard.php
 * Descripción: Panel de métricas globales y nivel de madurez con resumen, gráficos de estado y control de roles.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';

// Definir permisos: Gerentes, Subgerentes y Logística tienen acceso al SCM
verificarAcceso(['gerente', 'subgerente', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';

// Definir la ruta de salida según el rol actual
$url_salida = '../index.php';
if ($rol_actual === 'gerente') {
    $url_salida = '../gerente/dashboard.php';
} elseif ($rol_actual === 'subgerente') {
    $url_salida = '../subgerente/dashboard.php';
}

// Consultas para resumen general
$total_prod = $db->query("SELECT COUNT(*) FROM scm_productos")->fetchColumn();
$total_prov = $db->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();
$total_ped = $db->query("SELECT COUNT(*) FROM scm_pedidos")->fetchColumn();
$alertas = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

// Consultas adicionales para métricas y gráficos rápidos de resumen
$total_movimientos = $db->query("SELECT COUNT(*) FROM scm_movimientos")->fetchColumn();
$estrategia_push = $db->query("SELECT COUNT(*) FROM scm_productos WHERE estrategia_logistica = 'PUSH'")->fetchColumn();
$estrategia_pull = $db->query("SELECT COUNT(*) FROM scm_productos WHERE estrategia_logistica = 'PULL'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard SCM – Panel de Logística</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --secondary: #000049;
      --sidebar-hover: #0369a1;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
      --sidebar-w: 260px;
      --radius: 10px;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
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
      width: var(--sidebar-w);
      background: var(--secondary);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      z-index: 100;
      overflow-y: auto;
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
      flex: 1;
    }

    .sidebar-item {
      padding: 12px 16px;
      border-radius: var(--radius);
      color: #94a3b8;
      text-decoration: none;
      font-weight: 500;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: 0.2s;
    }

    .sidebar-item:hover, .sidebar-item.active {
      background: var(--sidebar-hover);
      color: #ffffff;
    }

    .sidebar-item.active {
      font-weight: 600;
    }

    .main-content {
      margin-left: var(--sidebar-w);
      flex: 1;
      padding: 32px 40px;
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

    .alert-banner {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-left: 4px solid var(--danger);
      padding: 16px;
      border-radius: var(--radius);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alert-banner span.icon {
      font-size: 20px;
    }

    .alert-banner .content {
      color: #991b1b;
      font-weight: 500;
    }

    .metrics {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 25px;
    }

    .metric-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      box-shadow: var(--shadow-sm);
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

    .grid-dashboard {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: var(--shadow-sm);
    }

    .card h3 {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 16px;
      color: var(--text);
    }

    /* Simulación de barras de progreso / gráficas analíticas */
    .progress-item {
      margin-bottom: 14px;
    }
    .progress-label {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 6px;
    }
    .progress-bar-bg {
      background: var(--border);
      border-radius: 6px;
      height: 10px;
      width: 100%;
      overflow: hidden;
    }
    .progress-bar-fill {
      height: 100%;
      border-radius: 6px;
    }

    @media (max-width: 900px) {
      .grid-dashboard { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 20px; }
      .sidebar { display: none; }
    }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item active">
            <span>📈</span> Dashboard SCM
        </a>
        <a href="productos.php" class="sidebar-item"><span>📦</span> Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item"><span>🤝</span> Proveedores</a>
        <a href="inventario.php" class="sidebar-item">
            <span>📊</span> Inventario / Alertas 
            <?php if($alertas > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;"><?= htmlspecialchars($alertas, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>
        <a href="movimientos.php" class="sidebar-item"><span>🔄</span> Movimientos</a>
        <a href="pedidos.php" class="sidebar-item"><span>🛒</span> Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item"><span>⚙</span> Logística Push/Pull</a>
        
        <!-- Salida dinámica condicional -->
        <?php if ($rol_actual === 'logistica'): ?>
            <a href="logout.php" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>🚪</span> Cerrar sesión</a>
        <?php else: ?>
            <a href="<?= htmlspecialchars($url_salida, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>←</span> Salir al Panel</a>
        <?php endif; ?>
    </div>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content">
    <?php if ($alertas > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= htmlspecialchars($alertas, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Ver inventario crítico</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="header">
        <h1>📈 Dashboard y Métricas SCM</h1>
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <!-- TARJETAS DE MÉTRICAS -->
    <div class="metrics">
        <div class="metric-card">
            <div class="metric-title">Productos SCM</div>
            <div class="metric-value"><?= htmlspecialchars($total_prod, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Proveedores</div>
            <div class="metric-value"><?= htmlspecialchars($total_prov, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Movimientos</div>
            <div class="metric-value"><?= htmlspecialchars($total_movimientos, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Alertas Stock</div>
            <div class="metric-value" style="color:var(--danger);"><?= htmlspecialchars($alertas, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <!-- SECCIÓN DE GRÁFICAS Y RESUMEN ANALÍTICO -->
    <div class="grid-dashboard">
        <div class="card">
            <h3>📊 Resumen de Estrategias Logísticas (Push vs Pull)</h3>
            <p style="color:var(--muted); font-size:13px; margin-bottom: 20px;">Distribución porcentual de los productos activos dentro de la cadena de suministro según su modelo de reposición.</p>
            
            <?php 
                $total_estrategias = max(1, ($estrategia_push + $estrategia_pull));
                $porcentaje_push = round(($estrategia_push / $total_estrategias) * 100);
                $porcentaje_pull = round(($estrategia_pull / $total_estrategias) * 100);
            ?>

            <div class="progress-item">
                <div class="progress-label">
                    <span>Estrategia PUSH (Empuje / Stock Fijo)</span>
                    <span><b><?= $estrategia_push ?></b> productos (<?= $porcentaje_push ?>%)</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?= $porcentaje_push ?>%; background: var(--primary);"></div>
                </div>
            </div>

            <div class="progress-item" style="margin-bottom: 0;">
                <div class="progress-label">
                    <span>Estrategia PULL (Tracción / Demanda Real)</span>
                    <span><b><?= $estrategia_pull ?></b> productos (<?= $porcentaje_pull ?>%)</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?= $porcentaje_pull ?>%; background: var(--success);"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>⚙️ Estado del Sistema</h3>
            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--muted);">Módulo Activo:</span>
                    <b>Supply Chain Management</b>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--muted);">Pedidos SCM:</span>
                    <b><?= htmlspecialchars($total_ped, ENT_QUOTES, 'UTF-8') ?> órdenes</b>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--muted);">Nivel de Madurez:</span>
                    <b style="color: var(--success);">Nivel 3 (Optimizado)</b>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>ℹ️ Información de Acceso y Permisos</h3>
        <p style="color:var(--muted); line-height:1.6; margin-bottom: 10px;">Estado actual: <b>En Producción</b>. El sistema cuenta con control integrado de inventario, estrategias Push y Pull configuradas, trazabilidad de movimientos y gestión directa de órdenes de reposición a proveedores.</p>
        
        <?php if ($rol_actual === 'logistica'): ?>
            <p style="color: var(--warning); font-size: 13px; font-weight: 500;">⚠️ Estás ingresando con permisos de <b>Logística</b>. Tienes acceso completo de edición y control sobre la cadena de suministro.</p>
        <?php elseif ($rol_actual === 'subgerente'): ?>
            <p style="color: var(--warning); font-size: 13px; font-weight: 500;">🔒 Estás ingresando en modo de consulta (Subgerente). Puedes revisar existencias y métricas del SCM.</p>
        <?php else: ?>
            <p style="color: var(--success); font-size: 13px; font-weight: 500;">✓ Acceso administrativo completo habilitado para la gestión de la cadena de suministro.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>