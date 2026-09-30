<?php
/**
 * Archivo: scm/productos.php
 * Descripción: Catálogo de materias primas e insumos con control de roles, buscador y Sidebar institucional.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_gerente = ($rol_actual === 'gerente');

// Conteo global de stock crítico para la barra lateral
$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

// Filtro de búsqueda por nombre o categoría
$buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT p.*, pr.nombre as proveedor_nombre FROM scm_productos p LEFT JOIN proveedores pr ON p.proveedor_id = pr.id WHERE 1=1";
$params = [];

if ($buscar !== '') {
    $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Materias Primas SCM – Restaurant App</title>
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

    .role-badge {
      background: rgba(2, 132, 199, 0.1);
      color: var(--primary);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
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
    }

    input {
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

    input:focus {
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
      margin-top: 5px;
    }

    th {
      background: #f1f5f9;
      padding: 12px 16px;
      text-align: left;
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
        <a href="productos.php" class="sidebar-item active">📦 Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        
        <a href="inventario.php" class="sidebar-item">
            📊 Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;">⚠️ <?= $num_alertas_global ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color: var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h1>📦 Catálogo de Materias Primas e Insumos</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual)) ?></div>
            <?php if ($es_gerente): ?>
                <a href="producto_form.php" class="btn">+ Nueva Materia Prima</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOOLBAR DE BÚSQUEDA -->
    <div class="toolbar">
        <form method="GET" class="filters">
            <input type="text" name="buscar" placeholder="Buscar por insumo..." value="<?= htmlspecialchars($buscar) ?>" style="width: 280px;">
            <button class="btn btn-secondary" type="submit">🔎 Buscar</button>
            <?php if($buscar !== ''): ?>
                <a href="productos.php" class="btn btn-secondary" style="color:var(--danger);">✕ Limpiar</a>
            <?php endif; ?>
        </form>
        <div style="color: var(--muted); font-size: 13px;">
            Total insumos: <b><?= count($productos) ?></b>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Insumo</th>
                    <th>Proveedor Enlazado</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Estrategia</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($productos)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:30px;">No hay materias primas registradas en el catálogo.</td></tr>
                <?php else: foreach($productos as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><b><?= htmlspecialchars($p['nombre']) ?></b></td>
                        <td><span style="color: var(--primary); font-weight: 500;"><?= htmlspecialchars($p['proveedor_nombre'] ?? 'Sin asignar') ?></span></td>
                        <td><b><?= $p['stock_actual'] ?></b></td>
                        <td><?= $p['stock_minimo'] ?></td>
                        <td><span class="badge"><?= htmlspecialchars($p['estrategia_logistica']) ?></span></td>
                        <td style="text-align: right;">
                            <?php if($es_gerente): ?>
                                <a href="producto_form.php?id=<?= $p['id'] ?>" class="btn" style="padding: 6px 12px; font-size:11px;">Editar</a>
                            <?php else: ?>
                                <span style="color:var(--muted); font-size:12px;">Solo lectura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>