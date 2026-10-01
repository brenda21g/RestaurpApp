<?php
/**
 * Archivo: scm/proveedores.php
 * Descripción: Listado general de proveedores con control de roles, buscador, banner global y Sidebar institucional.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_gerente = ($rol_actual === 'gerente');

// Conteo global de stock crítico para la barra lateral y banner global
$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

// Filtro de búsqueda por nombre, contacto o correo
$buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT * FROM proveedores WHERE 1=1";
$params = [];

if ($buscar !== '') {
    $sql .= " AND (nombre LIKE ? OR contacto LIKE ? OR correo LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= " ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proveedores SCM – Restaurant App</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ==========================================================================
       1. VARIABLES Y CONFIGURACIÓN GLOBAL
       ========================================================================== */
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

    /* ==========================================================================
       2. RESET Y ESTILOS BASE
       ========================================================================== */
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

    /* ==========================================================================
       3. SIDEBAR INSTITUCIONAL AZUL
       ========================================================================== */
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

    /* ==========================================================================
       4. CONTENIDO PRINCIPAL Y BANNER DE ALERTA
       ========================================================================== */
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

    /* ==========================================================================
       5. TOOLBAR, FILTROS Y TABLA
       ========================================================================== */
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
      box-shadow: var(--shadow-sm);
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
      box-shadow: var(--shadow-sm);
      overflow-x: auto;
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
        <a href="proveedores.php" class="sidebar-item active"><span>🤝</span> Proveedores</a>
        
        <a href="inventario.php" class="sidebar-item">
            <span>📊</span> Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;"><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item"><span>🔄</span> Movimientos</a>
        <a href="pedidos.php" class="sidebar-item"><span>🛒</span> Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item"><span>⚙️</span> Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>←</span> Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Ver inventario y solicitar reposición</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="header">
        <div>
            <h1>🤝 Gestión de Proveedores</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($es_gerente): ?>
                <a href="proveedor_form.php" class="btn">+ Nuevo Proveedor</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOOLBAR DE BÚSQUEDA -->
    <div class="toolbar">
        <form method="GET" class="filters">
            <input type="text" name="buscar" placeholder="Buscar por empresa, contacto o correo..." value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" style="width: 320px;" autocomplete="off">
            <button class="btn btn-secondary" type="submit">🔎 Buscar</button>
            <?php if($buscar !== ''): ?>
                <a href="proveedores.php" class="btn btn-secondary" style="color:var(--danger);">✕ Limpiar</a>
            <?php endif; ?>
        </form>
        <div style="color: var(--muted); font-size: 13px;">
            Total proveedores: <b><?= htmlspecialchars(count($proveedores), ENT_QUOTES, 'UTF-8') ?></b>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa / Proveedor</th>
                    <th>Contacto</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($proveedores)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:30px;">No hay proveedores registrados en el sistema.</td></tr>
                <?php else: foreach($proveedores as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><b><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><?= htmlspecialchars($p['contacto'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($p['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($p['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="text-align: right;">
                            <?php if($es_gerente): ?>
                                <a href="proveedor_form.php?id=<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn" style="padding: 6px 12px; font-size:11px;">Editar</a>
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