<?php
/**
 * Archivo: scm/movimientos.php
 * Descripción: Historial trazable de entradas/salidas con Sidebar azul, banner global de alerta de stock crítico y soporte para filtros por producto.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_editable = puedeEditar('scm'); // TRUE para Gerente y Logística; FALSE para Subgerente

// Conteo global de stock crítico para la barra lateral y banner global
$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

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

// Definir enlace de salida según el rol actual
$url_salida = '../index.php';
if ($rol_actual === 'gerente') {
    $url_salida = '../gerente/dashboard.php';
} elseif ($rol_actual === 'subgerente') {
    $url_salida = '../subgerente/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Movimientos SCM – Restaurant App</title>
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
      box-shadow: var(--shadow-sm);
      margin-bottom: 20px;
      overflow-x: auto;
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

    .badge-tipo {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      display: inline-block;
    }

    .badge-entrada { background: rgba(16, 185, 129, 0.1); color: var(--success); }
    .badge-salida { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 20px; }
      .sidebar { display: none; }
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item"><span>📈</span> Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item"><span>📦</span> Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item"><span>🤝</span> Proveedores</a>
        
        <a href="inventario.php" class="sidebar-item">
            <span>📊</span> Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;"><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item active"><span>🔄</span> Movimientos</a>
        <a href="pedidos.php" class="sidebar-item"><span>🛒</span> Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item"><span>⚙</span> Logística Push/Pull</a>
        
        <!-- Salida condicional: Cerrar sesión para Logística, Salir al Panel para Gerente/Subgerente -->
        <?php if ($rol_actual === 'logistica'): ?>
            <a href="logout.php" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>🚪</span> Cerrar sesión</a>
        <?php else: ?>
            <a href="<?= htmlspecialchars($url_salida, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>←</span> Salir al Panel</a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Ver inventario crítico</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="header">
        <div>
            <h1>🔄 Movimientos de Inventario</h1>
            <?php if($producto_id_filtro > 0): ?>
                <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">Filtrando por el insumo seleccionado (<a href="movimientos.php" style="color: var(--primary); text-decoration: none; font-weight:600;">Ver todos</a>)</div>
            <?php endif; ?>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($es_editable): ?>
                <a href="movimiento_form.php" class="btn">+ Registrar Movimiento</a>
            <?php else: ?>
                <span style="color: var(--muted); font-size: 13px; font-style: italic;">Modo consulta (Lectura)</span>
            <?php endif; ?>
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
                    
                    // Determinar si fue automático o manual
                    $es_automatico = empty($m['usuario_id']) || stripos($m['motivo'], 'automátic') !== false || stripos($m['motivo'], 'push') !== false;
                    $registrado_por = $es_automatico ? 'Sistema' : htmlspecialchars($m['admin_nombre'] ?? 'Sistema', ENT_QUOTES, 'UTF-8');
                ?>
                    <tr>
                        <td><?= htmlspecialchars($m['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge-tipo <?= $es_entrada ? 'badge-entrada' : 'badge-salida' ?>">
                                <?= htmlspecialchars($m['tipo'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><b><?= htmlspecialchars($m['producto_nombre'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><b><?= htmlspecialchars($m['cantidad'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td style="color: var(--muted);"><?= htmlspecialchars($m['motivo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $registrado_por ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>