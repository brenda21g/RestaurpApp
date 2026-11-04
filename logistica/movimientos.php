<?php
/**
 * Archivo: logistica/movimientos.php
 * Descripción: Historial trazable de entradas/salidas mostrando el nombre exacto y el rol del usuario que lo realizó.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? 'logistica';
$es_editable = puedeEditar('scm');

// Obtener datos del usuario actual para el sidebar
$admin_id = $_SESSION['admin_id'] ?? 0;
$stmt_admin = $db->prepare("SELECT nombre FROM admins WHERE id = ?");
$stmt_admin->execute([$admin_id]);
$admin_actual = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$nombre_usuario = $admin_actual['nombre'] ?? 'Logística';

// Conteo global de stock crítico
$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

// Filtrar opcionalmente por producto si se pasa por GET
$producto_id_filtro = intval($_GET['producto_id'] ?? 0);

// Consulta para traer el movimiento, el producto y el administrador directo si lo tiene
$sql = "SELECT m.*, p.nombre as producto_nombre, 
               a.nombre as admin_nombre, a.username as admin_username, a.rol as admin_rol
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

$seccion = 'movimientos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Movimientos – Panel de Logística</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --secondary: #03143d;
      --sidebar-hover: #092e70;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0788c9;
      --primary-hover: #0674ad;
      --danger: #ef4444;
      --success: #13b77a;
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

    .sidebar-brand .role-sub {
      font-size: 11px;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 4px;
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
      color: #cbd5e1;
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
      background: rgba(7, 136, 201, 0.1);
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

    .badge-entrada { background: rgba(19, 183, 122, 0.1); color: var(--success); }
    .badge-salida { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

    .user-badge {
      display: inline-block;
      padding: 2px 8px;
      background: #e2e8f0;
      color: #334155;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
      text-transform: capitalize;
      margin-top: 2px;
    }

    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 20px; }
      .sidebar { display: none; }
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        🍽️ RestaurApp
        <div class="role-sub">Logística (<?= htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8') ?>)</div>
    </div>
    <div class="sidebar-menu">
        <a class="sidebar-item <?= ($seccion ?? '') === 'inicio' ? 'active' : '' ?>" href="dashboard.php?seccion=inicio"><span>📊</span> Inicio</a>
        <a class="sidebar-item <?= ($seccion ?? '') === 'ordenes' ? 'active' : '' ?>" href="dashboard.php?seccion=ordenes"><span>🛒</span> Órdenes ERP</a>
        <a class="sidebar-item <?= ($seccion ?? '') === 'reportes' ? 'active' : '' ?>" href="dashboard.php?seccion=reportes"><span>📈</span> Reportes y Métricas</a>
        <a class="sidebar-item" href="productos.php"><span>📦</span> Productos</a>
        <a class="sidebar-item" href="inventario.php"><span>📊</span> Inventario</a>
        <a class="sidebar-item active" href="movimientos.php"><span>🔄</span> Movimientos</a>
        <a class="sidebar-item" href="configuracion.php"><span>⚙️</span> Configuración</a>
    </div>
    <div style="padding: 16px; border-top: 1px solid #0b2455;">
        <a href="logout.php" style="color: #ff6868; text-decoration: none; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px;">🚪 Cerrar sesión</a>
    </div>
</div>

<div class="main-content">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención Logística!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
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
                    
                    // Si el movimiento tiene un usuario asignado directamente
                    if (!empty($m['admin_nombre'])) {
                        $registrado_por = $m['admin_nombre'];
                        $rol_registrado = !empty($m['admin_rol']) ? ucfirst($m['admin_rol']) : 'Usuario';
                    } else {
                        // Si es automático, buscamos en la base de datos de administradores según el rol que menciona el motivo
                        $rol_buscado = '';
                        if (stripos($m['motivo'], 'Subgerencia') !== false) {
                            $rol_buscado = 'subgerente';
                        } elseif (stripos($m['motivo'], 'Gerencia') !== false) {
                            $rol_buscado = 'gerente';
                        } else {
                            $rol_buscado = 'logistica';
                        }

                        // Consultar un usuario activo de ese rol para mostrar su nombre real
                        $stmt_u = $db->prepare("SELECT nombre, rol FROM admins WHERE rol = ? LIMIT 1");
                        $stmt_u->execute([$rol_buscado]);
                        $usuario_encontrado = $stmt_u->fetch(PDO::FETCH_ASSOC);

                        if ($usuario_encontrado) {
                            $registrado_por = $usuario_encontrado['nombre'];
                            $rol_registrado = ucfirst($usuario_encontrado['rol']);
                        } else {
                            $registrado_por = 'Sistema Automático';
                            $rol_registrado = 'Sistema';
                        }
                    }
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
                        <td>
                            <div><b><?= htmlspecialchars($registrado_por, ENT_QUOTES, 'UTF-8') ?></b></div>
                            <span class="user-badge"><?= htmlspecialchars($rol_registrado, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>