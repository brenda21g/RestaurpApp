<?php
/**
 * Archivo: scm/productos.php
 * Descripción: Catálogo de materias primas e insumos con control de roles, buscador, bajas lógicas, banner global y Sidebar institucional idéntico a clientes.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_gerente = ($rol_actual === 'gerente');
$es_editable = puedeEditar('scm'); // TRUE para Gerente y Logística; FALSE para Subgerente

// Manejo de baja lógica (si se solicita por GET y tiene permisos de edición)
if (isset($_GET['eliminar']) && $es_editable) {
    $id_eliminar = intval($_GET['eliminar']);
    try {
        $stmt_del = $db->prepare("UPDATE scm_productos SET activo = 0 WHERE id = ?");
        $stmt_del->execute([$id_eliminar]);
    } catch (Exception $e) {
        // Si la columna activo no existe en la BD, eliminamos físicamente de forma segura
        try {
            $db->prepare("DELETE FROM scm_productos WHERE id = ?")->execute([$id_eliminar]);
        } catch (Exception $ex) {}
    }
    header("Location: productos.php?success=baja");
    exit;
}

// Conteo global de stock crítico para la barra lateral y banner global
$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

// Filtro de búsqueda por nombre o descripción (filtrando también los activos si aplica)
$buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT p.*, pr.nombre as proveedor_nombre FROM scm_productos p LEFT JOIN proveedores pr ON p.proveedor_id = pr.id WHERE 1=1";
$params = [];

// Si la tabla cuenta con el campo activo, filtramos solo los activos
try {
    $check_col = $db->query("SHOW COLUMNS FROM scm_productos LIKE 'activo'")->fetch();
    if ($check_col) {
        $sql .= " AND (p.activo = 1 OR p.activo IS NULL)";
    }
} catch (Exception $e) {}

if ($buscar !== '') {
    $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
<title>Materias Primas SCM – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #f8fafc;
        --bg-surface: #ffffff;
        --sidebar-bg: #03143d;
        --sidebar-hover: #092e70;
        --sidebar-text: #cbd5e1;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-primary: #0788c9;
        --color-success: #13b77a;
        --color-danger: #ef4444;
        --color-warning: #f59e0b;
        --sidebar-w: 260px;
        --radius: 10px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background-color: var(--bg-body);
        color: var(--text-main);
        font-family: 'Inter', sans-serif;
        display: flex;
        min-height: 100vh;
        font-size: 14px;
    }

    /* SIDEBAR INSTITUCIONAL AZUL IDÉNTICO A CLIENTES */
    .sidebar {
        width: var(--sidebar-w);
        background-color: var(--sidebar-bg);
        color: #fff;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 100;
        overflow-y: auto;
    }

    .sidebar-logo {
        padding: 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-logo .name {
        font-size: 18px;
        font-weight: 700;
        color: #ffffff;
    }

    .sidebar-logo .role {
        font-size: 11px;
        color: #94a3b8;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .nav {
        padding: 20px 12px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: var(--radius);
        color: var(--sidebar-text);
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
    }

    .nav-item:hover, .nav-item.active {
        background-color: var(--sidebar-hover);
        color: #fff;
    }

    .sidebar-bottom {
        padding: 16px 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: var(--radius);
        color: #fca5a5;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: background 0.2s;
    }

    .logout-btn:hover {
        background-color: rgba(239, 68, 68, 0.1);
        color: #f87171;
    }

    /* CONTENIDO PRINCIPAL */
    .main {
        margin-left: var(--sidebar-w);
        flex: 1;
        padding: 32px 40px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .page-title {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-main);
    }

    .role-badge {
        background: rgba(7, 136, 201, 0.1);
        color: var(--color-primary);
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .alert-banner {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-left: 4px solid var(--color-danger);
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

    .alert-success { 
        background: #dcfce7; 
        border: 1px solid #bbf7d0; 
        color: #15803d; 
        padding: 12px 16px; 
        border-radius: var(--radius); 
        margin-bottom: 20px; 
        font-weight: 500; 
    }

    .filters-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 16px 20px;
        margin-bottom: 24px;
        gap: 12px;
        flex-wrap: wrap;
        box-shadow: var(--shadow-sm);
    }

    .filters {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .filter-input {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 10px 14px;
        color: var(--text-main);
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        outline: none;
        box-shadow: var(--shadow-sm);
        transition: border-color 0.2s;
    }

    .filter-input:focus {
        border-color: var(--color-primary);
    }

    .btn-primary {
        background: var(--color-primary);
        color: #fff;
        padding: 10px 18px;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: var(--shadow-sm);
        border: none;
        cursor: pointer;
    }

    .btn-primary:hover {
        background: #0674ad;
    }

    .btn-secondary {
        background: var(--bg-surface);
        color: var(--text-main);
        border: 1px solid var(--border-color);
    }

    .btn-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--color-danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .btn-danger:hover {
        background: var(--color-danger);
        color: #fff;
    }

    .table-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    th {
        background: #f1f5f9;
        padding: 14px 18px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-color);
    }

    td {
        padding: 16px 18px;
        border-bottom: 1px solid var(--border-color);
        font-size: 14px;
        vertical-align: middle;
        color: var(--text-main);
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
        background: rgba(7, 136, 201, 0.1);
        color: var(--color-primary);
        display: inline-block;
    }

    @media (max-width: 768px) {
        .main { margin-left: 0; padding: 20px; }
        .sidebar { display: none; }
    }
</style>
</head>
<body>

<!-- SIDEBAR INSTITUCIONAL IDÉNTICO A CLIENTES -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="name">🍽️ RestaurApp</div>
    <div class="role">Subgerencia</div>
  </div>
  <nav class="nav">
    <a class="nav-item" href="dashboard.php?seccion=inicio"><span>📊</span> Dashboard</a>
    <a class="nav-item" href="dashboard.php?seccion=ordenes"><span>🛒</span> Órdenes ERP</a>
    <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item active" href="productos.php"><span>📦</span> Productos</a>
    <a class="nav-item" href="pedidos.php"><span>📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span>🪑</span> Mesas QR</a>
    <a class="nav-item" href="menu.php"><span>🍲</span> Menú</a>
    <a class="nav-item" href="corte.php"><span>💰</span> Corte</a>
    <a class="nav-item" href="miactividad.php"><span>📈</span> Mi Actividad</a>
    <a class="nav-item" href="configuracion.php"><span>⚙️</span>Configuracion</a>
  </nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Ver inventario crítico</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'baja'): ?>
        <div class="alert-success">✔ Materia prima dada de baja lógicamente con éxito del sistema.</div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-title">📦 Catálogo de Materias Primas e Insumos</div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($es_editable): ?>
                <a href="producto_form.php" class="btn-primary">+ Nueva Materia Prima</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOOLBAR DE BÚSQUEDA -->
    <div class="filters-bar">
        <form method="GET" class="filters">
            <input class="filter-input" type="text" name="buscar" placeholder="Buscar por insumo..." value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" style="width: 280px;" autocomplete="off">
            <button class="filter-input btn-secondary" type="submit">🔎 Buscar</button>
            <?php if($buscar !== ''): ?>
                <a href="productos.php" class="filter-input btn-secondary" style="color:var(--color-danger); text-decoration:none;">✕ Limpiar</a>
            <?php endif; ?>
        </form>
        <div style="color: var(--text-muted); font-size: 13px;">
            Total insumos activos: <b><?= htmlspecialchars(count($productos), ENT_QUOTES, 'UTF-8') ?></b>
        </div>
    </div>

    <div class="table-card">
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
                    <tr><td colspan="7" style="text-align:center; color: var(--text-muted); padding: 30px;">No hay materias primas registradas en el catálogo.</td></tr>
                <?php else: foreach($productos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span style="color: var(--color-primary); font-weight: 500;"><?= htmlspecialchars($p['proveedor_nombre'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><b><?= htmlspecialchars($p['stock_actual'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><?= htmlspecialchars($p['stock_minimo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($p['estrategia_logistica'] ?? 'PUSH', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td style="text-align: right;">
                            <?php if($es_editable): ?>
                                <a href="producto_form.php?id=<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn-primary" style="padding: 6px 12px; font-size:11px;">Editar</a>
                                <a href="productos.php?eliminar=<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>" class="filter-input btn-danger" style="padding: 6px 12px; font-size:11px; text-decoration:none;" onclick="return confirm('¿Estás seguro de dar de baja lógica este insumo del catálogo?');">Dar de baja</a>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size:12px;">Solo lectura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>