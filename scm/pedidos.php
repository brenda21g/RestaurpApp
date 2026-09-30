<?php
/**
 * Archivo: scm/pedidos.php
 * Descripción: Listado de pedidos internos diferenciando Push (automático) y Pull (manual) con control de roles y Sidebar institucional.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';

// Simulación de actualización automática: cambia estado a entregado, suma stock y registra movimiento
if (isset($_GET['entregar'])) {
    $id_pedido = intval($_GET['entregar']);
    $stmt_p = $db->prepare("SELECT * FROM scm_pedidos WHERE id = ? AND estado != 'entregado'");
    $stmt_p->execute([$id_pedido]);
    $ped = $stmt_p->fetch(PDO::FETCH_ASSOC);
    
    if ($ped) {
        // Cambiar estado a entregado
        $db->prepare("UPDATE scm_pedidos SET estado = 'entregado' WHERE id = ?")->execute([$id_pedido]);
        // Sumar al inventario del producto correspondiente
        $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual + ? WHERE id = ?")->execute([$ped['cantidad'], $ped['producto_id']]);
        
        // Registrar el movimiento de entrada automático
        $db->prepare("INSERT INTO scm_movimientos (producto_id, tipo, cantidad, motivo, fecha, usuario_id) VALUES (?, 'Entrada', ?, ?, CURDATE(), ?)")
           ->execute([$ped['producto_id'], $ped['cantidad'], 'Recepción pedido ' . $ped['numero_orden_scm'], $_SESSION['admin_id'] ?? null]);
    }
    header("Location: pedidos.php");
    exit;
}

// Conteo global de stock crítico para la barra lateral
$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

$pedidos = $db->query("SELECT p.*, pr.nombre as proveedor_nombre, sp.nombre as producto_nombre FROM scm_pedidos p JOIN proveedores pr ON p.proveedor_id = pr.id JOIN scm_productos sp ON p.producto_id = sp.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Pedidos SCM – Push vs Pull</title>
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

    .badge-push {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      background: #e0f2fe;
      color: #0369a1;
    }

    .badge-pull {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      background: #fef3c7;
      color: #d97706;
    }

    .status-procesando {
      color: #d97706;
      font-weight: 600;
    }

    .status-entregado {
      color: var(--success);
      font-weight: 600;
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
        
        <a href="inventario.php" class="sidebar-item">
            📊 Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;">⚠️ <?= $num_alertas_global ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item active">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color: var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h1>🛒 Pedidos Internos (Gestión Push vs Pull)</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual)) ?></div>
            <a href="pedido_form.php" class="btn">+ Generar Pedido Pull Manual</a>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Orden #</th>
                    <th>Insumo</th>
                    <th>Proveedor</th>
                    <th>Cantidad</th>
                    <th>Estrategia / Tipo</th>
                    <th>Estado Actual</th>
                    <th style="text-align: right;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pedidos)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:30px;">No hay pedidos internos registrados en el sistema.</td></tr>
                <?php else: foreach($pedidos as $pe): 
                    $es_push = (strpos($pe['tipo'], 'Push') !== false || strpos($pe['tipo'], 'Automática') !== false);
                ?>
                    <tr>
                        <td><b><?= htmlspecialchars($pe['numero_orden_scm']) ?></b></td>
                        <td><?= htmlspecialchars($pe['producto_nombre']) ?></td>
                        <td><?= htmlspecialchars($pe['proveedor_nombre']) ?></td>
                        <td><b><?= $pe['cantidad'] ?></b></td>
                        <td>
                            <?php if($es_push): ?>
                                <span class="badge-push">PUSH (Automático)</span>
                            <?php else: ?>
                                <span class="badge-pull">PULL (Bajo Demanda)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($pe['estado'] == 'procesando' || $pe['estado'] == 'pendiente'): ?>
                                <span class="status-procesando">⏳ Procesando</span>
                            <?php else: ?>
                                <span class="status-entregado">✔ Entregado (+ Stock)</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if($pe['estado'] != 'entregado'): ?>
                                <a href="pedidos.php?entregar=<?= $pe['id'] ?>" class="btn" style="padding: 6px 12px; font-size:11px; background:var(--success);">Simular Entrega</a>
                            <?php else: ?>
                                <span style="color:var(--muted); font-size:12px;">Completado</span>
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