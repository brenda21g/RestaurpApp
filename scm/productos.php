<?php
/**
 * Archivo: scm/productos.php
 * Descripción: Catálogo de materias primas e insumos enlazados directamente con sus proveedores específicos.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente']);
$db = getDB();

// Consulta que une los productos con su respectivo proveedor mediante proveedor_id
$sql = "SELECT p.*, pr.nombre as proveedor_nombre FROM scm_productos p LEFT JOIN proveedores pr ON p.proveedor_id = pr.id ORDER BY p.id DESC";
$productos = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$es_gerente = ($_SESSION['admin_rol'] === 'gerente');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Materias Primas SCM</title>
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

    .btn {
      background: var(--primary);
      color: #fff;
      padding: 10px 16px;
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

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    h2 {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--text);
    }

    .field {
      margin-bottom: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .field label {
      font-weight: 600;
      font-size: 12px;
      color: var(--muted);
      text-transform: uppercase;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    th {
      background: #f1f5f9;
      padding: 12px;
      text-align: left;
      font-size: 11px;
      text-transform: uppercase;
      color: var(--muted);
      letter-spacing: 0.5px;
    }

    td {
      padding: 14px 12px;
      border-bottom: 1px solid var(--border);
      color: var(--text);
    }

    tr:hover td {
      background: #fdfdfd;
    }

    .badge {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      background: #e0f2fe;
      color: #0369a1;
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant App SCM</div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item">📈 Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item active">📦 Materias Primas</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        <a href="inventario.php" class="sidebar-item">📊 Inventario / Alertas</a>
        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color:var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1>📦 Catálogo de Materias Primas e Insumos</h1>
        <?php if ($es_gerente): ?>
            <a href="producto_form.php" class="btn">+ Nueva Materia Prima</a>
        <?php endif; ?>
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
                    <?php if($es_gerente): ?><th>Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($productos)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:20px;">No hay materias primas registradas.</td></tr>
                <?php else: foreach($productos as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><b><?= htmlspecialchars($p['nombre']) ?></b></td>
                        <td><span style="color: var(--primary); font-weight: 500;"><?= htmlspecialchars($p['proveedor_nombre'] ?? 'Sin asignar') ?></span></td>
                        <td><?= $p['stock_actual'] ?></td>
                        <td><?= $p['stock_minimo'] ?></td>
                        <td><span class="badge"><?= $p['estrategia_logistica'] ?></span></td>
                        <?php if($es_gerente): ?>
                            <td><a href="producto_form.php?id=<?= $p['id'] ?>" style="color:var(--primary); text-decoration:none; font-weight:600;">Editar</a></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>