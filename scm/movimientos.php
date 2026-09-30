<?php
/**
 * Archivo: scm/movimientos.php
 * Descripción: Historial trazable de entradas/salidas con Sidebar azul, alerta global de stock crítico y soporte para filtros por producto.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';

// Conteo global de stock crítico para mostrar la alerta en el menú lateral
$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

// Filtrar opcionalmente por producto si se pasa por GET
$producto_id_filtro = intval($_GET['producto_id'] ?? 0);

$sql = "SELECT m.*, p.nombre as producto_nombre, a.username as admin_nombre 
        FROM scm_movimientos m 
        JOIN scm_productos p ON m.producto_id = p.id 
        LEFT JOIN admins a ON m.usuario_id = a.id";
$params = [];

if ($producto_id_filtro > 0) {
    $sql .= " WHERE m.producto_id = ?";
    $params[] = $producto_id_filtro;
}

$sql .= " ORDER BY m.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$movs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Movimientos SCM – Restaurant App</title>
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
      border-bottom: 1px solid rgba(255,255,255,0.1);
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
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
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

    .badge-tipo {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .badge-entrada { background: rgba(16, 185, 129, 0.1); color: var(--success); }
    .badge-salida { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item">📈 Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item">📦 Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        
        <a href="inventario.php" class="sidebar-item">
            📊 Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;">⚠️ <?= $num_alertas_global ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item active">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color: var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h1>🔄 Movimientos de Inventario</h1>
            <?php if($producto_id_filtro > 0): ?>
                <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">Filtrando por el insumo seleccionado (<a href="movimientos.php" style="color: var(--primary); text-decoration: none;">Ver todos</a>)</div>
            <?php endif; ?>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual)) ?></div>
            <a href="movimiento_form.php" class="btn">+ Registrar Movimiento</a>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Producto / Insumo</th>
                    <th>Cantidad</th>
                    <th>Motivo / Razón</th>
                    <th>Registró</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($movs)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:30px;">No hay movimientos registrados en el sistema.</td></tr>
                <?php else: foreach($movs as $m): 
                    $es_entrada = (strtolower($m['tipo']) === 'entrada');
                ?>
                    <tr>
                        <td><?= htmlspecialchars($m['fecha']) ?></td>
                        <td>
                            <span class="badge-tipo <?= $es_entrada ? 'badge-entrada' : 'badge-salida' ?>">
                                <?= htmlspecialchars($m['tipo']) ?>
                            </span>
                        </td>
                        <td><b><?= htmlspecialchars($m['producto_nombre']) ?></b></td>
                        <td><b><?= $m['cantidad'] ?></b></td>
                        <td style="color: var(--muted);"><?= htmlspecialchars($m['motivo']) ?></td>
                        <td><?= htmlspecialchars($m['admin_nombre'] ?? 'Sistema') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>