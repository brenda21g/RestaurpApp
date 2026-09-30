<?php
/**
 * Archivo: scm/inventario.php
 * Descripción: Control de stock actual y alertas de inventario bajo con Sidebar, banner global y buscador.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';

// Conteo global de alertas para la barra lateral y banner
$stmt_alertas = $db->query("SELECT * FROM scm_productos WHERE stock_actual <= stock_minimo");
$productos_criticos = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);
$num_alertas_global = count($productos_criticos);

// Filtro de búsqueda y estado
$busqueda = trim($_GET['q'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';

$sql = "SELECT p.*, pr.nombre as proveedor_nombre 
        FROM scm_productos p 
        LEFT JOIN proveedores pr ON p.proveedor_id = pr.id 
        WHERE 1=1";
$params = [];

if ($busqueda !== '') {
    $sql .= " AND (p.nombre LIKE ? OR p.categoria LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($filtro_estado === 'critico') {
    $sql .= " AND p.stock_actual <= p.stock_minimo";
} elseif ($filtro_estado === 'optimo') {
    $sql .= " AND p.stock_actual > p.stock_minimo";
}

$sql .= " ORDER BY p.stock_actual ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Inventario SCM – Restaurant App</title>
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
      z-index: 100;
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
      flex-wrap: wrap;
      gap: 16px;
    }

    .header h1 {
      font-size: 22px;
      font-weight: 700;
      color: var(--text);
    }

    .alert-banner {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-left: 4px solid var(--danger);
      padding: 16px;
      border-radius: 8px;
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

    .toolbar {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px 20px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .filters {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    input, select {
      background: var(--surface);
      color: var(--text);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 9px 12px;
      font-family: inherit;
      font-size: 13px;
      outline: none;
      transition: border-color 0.2s;
    }

    input:focus, select:focus {
      border-color: var(--primary);
    }

    .btn {
      background: var(--primary);
      color: #fff;
      padding: 9px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: none;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn:hover {
      background: var(--primary-hover);
    }

    .btn-secondary {
      background: var(--surface);
      color: var(--text);
      border: 1px solid var(--border);
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    th {
      background: #f1f5f9;
      padding: 12px 16px;
      font-size: 11px;
      text-transform: uppercase;
      color: var(--muted);
      letter-spacing: 0.5px;
      border-bottom: 1px solid var(--border);
    }

    td {
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
      color: var(--text);
      vertical-align: middle;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: #f8fafc;
    }

    .status-ok {
      color: var(--success);
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .status-low {
      color: var(--danger);
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .badge {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      background: rgba(2, 132, 199, 0.1);
      color: var(--primary);
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item">📈 Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item">📦 Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        <a href="inventario.php" class="sidebar-item active">
            📊 Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;">⚠️ <?= $num_alertas_global ?></span>
            <?php endif; ?>
        </a>
        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color:var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= $num_alertas_global ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Filtrar alertas críticas</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="header">
        <h1>📊 Control de Inventario y Alertas</h1>
        <div>
            <a href="movimientos.php" class="btn">+ Registrar Movimiento</a>
            <a href="pedidos.php" class="btn" style="background:#0f172a;">Pedir a Proveedor</a>
        </div>
    </div>

    <!-- TOOLBAR DE BÚSQUEDA Y FILTROS -->
    <div class="toolbar">
        <form method="GET" class="filters">
            <input type="text" name="q" placeholder="Buscar por insumo o categoría..." value="<?= htmlspecialchars($busqueda) ?>" style="width: 260px;">
            <select name="estado">
                <option value="">Todos los estados</option>
                <option value="critico" <?= $filtro_estado === 'critico' ? 'selected' : '' ?>>⚠️ Alerta Stock Bajo</option>
                <option value="optimo" <?= $filtro_estado === 'optimo' ? 'selected' : '' ?>>✔ Óptimo</option>
            </select>
            <button class="btn btn-secondary" type="submit">🔎 Filtrar</button>
            <?php if($busqueda !== '' || $filtro_estado !== ''): ?>
                <a href="inventario.php" class="btn btn-secondary" style="color:var(--danger);">✕ Limpiar</a>
            <?php endif; ?>
        </form>
        <div style="color: var(--muted); font-size: 13px;">
            Total registros: <b><?= count($productos) ?></b>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Materia Prima / Insumo</th>
                    <th>Categoría</th>
                    <th>Proveedor Asignado</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Estrategia</th>
                    <th>Estado</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($productos)): ?>
                    <tr><td colspan="8" style="text-align:center; color:var(--muted); padding:30px;">No se encontraron registros de inventario con los filtros seleccionados.</td></tr>
                <?php else: foreach($productos as $p): $bajo = $p['stock_actual'] <= $p['stock_minimo']; ?>
                    <tr>
                        <td><b><?= htmlspecialchars($p['nombre']) ?></b></td>
                        <td style="color: var(--muted);"><?= htmlspecialchars($p['categoria'] ?? 'General') ?></td>
                        <td style="color: var(--muted);"><?= htmlspecialchars($p['proveedor_nombre'] ?? 'Sin asignar') ?></td>
                        <td><b><?= $p['stock_actual'] ?></b></td>
                        <td><?= $p['stock_minimo'] ?></td>
                        <td><span class="badge"><?= htmlspecialchars($p['estrategia_logistica'] ?? 'PUSH') ?></span></td>
                        <td><?= $bajo ? '<span class="status-low">⚠️ Alerta Stock Bajo</span>' : '<span class="status-ok">✔ Óptimo</span>' ?></td>
                        <td style="text-align: right;">
                            <a href="productos.php?buscar=<?= urlencode($p['nombre']) ?>" title="Ver / Editar Producto" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 16px; margin-right: 8px;">👁️</a>
                            <a href="movimientos.php?producto_id=<?= $p['id'] ?>" title="Ver Movimientos" style="color: var(--muted); text-decoration: none; font-weight: 600; font-size: 16px;">🔄</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>