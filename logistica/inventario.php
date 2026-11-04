<?php
/**
 * Archivo: scm/inventario.php
 * Descripción: Control de stock actual y alertas de inventario bajo, con Sidebar institucional unificado.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_editable = puedeEditar('scm'); // TRUE para Gerente y Logística; FALSE para Subgerente

$mensaje = '';
$error = '';

// Procesar acción de registro o actualización si se solicita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'baja_logica') {
    if (!$es_editable) {
        $error = "No tienes permisos de modificación en este módulo.";
    } else {
        $id_producto = (int)($_POST['producto_id'] ?? 0);
        if ($id_producto > 0) {
            try {
                $stmt_baja = $db->prepare("DELETE FROM scm_productos WHERE id = ?");
                $stmt_baja->execute([$id_producto]);
                $mensaje = "El insumo ha sido eliminado correctamente del inventario.";
            } catch (Exception $e) {
                $error = "Error al procesar la eliminación del producto.";
            }
        }
    }
}

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
    $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
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

// Definir enlace de salida en la barra lateral según el rol
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
<title>Inventario SCM – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #f7f9fc;
        --bg-surface: #ffffff;
        --sidebar-bg: #03143d;
        --sidebar-hover: #092e70;
        --sidebar-text: #cbd5e1;
        --text-main: #071b3a;
        --text-muted: #60708a;
        --border-color: #e2e8f0;
        --color-primary: #0788c9;
        --color-success: #13b77a;
        --color-danger: #ef5b63;
        --color-warning: #f5a000;
        --sidebar-w: 260px;
        --radius: 10px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background-color: var(--bg-body);
        color: var(--text-main);
        font-family: 'DM Sans', sans-serif;
        display: flex;
        min-height: 100vh;
        font-size: 14px;
    }

    /* SIDEBAR INSTITUCIONAL AZUL IDÉNTICO */
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
        font-family: 'Playfair Display', serif;
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
        font-family: 'Playfair Display', serif;
        font-size: 24px;
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
        background: #fdebed;
        border: 1px solid #f4c7cc;
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
        color: #c8424c;
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

    .alert-error { 
        background: #fdebed; 
        border: 1px solid #f4c7cc; 
        color: var(--color-danger); 
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
        flex-wrap: wrap;
    }

    .filter-input {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 10px 14px;
        color: var(--text-main);
        font-family: 'DM Sans', sans-serif;
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

    .status-ok {
        color: var(--color-success);
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-low {
        color: var(--color-danger);
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
        background: rgba(7, 136, 201, 0.1);
        color: var(--color-primary);
    }

    @media (max-width: 768px) {
        .main { margin-left: 0; padding: 20px; }
        .sidebar { display: none; }
    }
</style>
</head>
<body>

<!-- SIDEBAR INSTITUCIONAL -->
<aside class="sidebar">
        <div class="sidebar-logo">
            <div class="name">🍽️ RestaurApp</div>
            <div class="role">Logistica </div>
        </div>
        
        <nav class="nav">
            <a class="nav-item <?= $seccion === 'inicio' ? 'active' : '' ?>" href="dashboard.php?seccion=inicio"><span>📊</span> Inicio</a>
            <a class="nav-item <?= $seccion === 'ordenes' ? 'active' : '' ?>" href="dashboard.php?seccion=ordenes"><span>🛒</span> Órdenes ERP</a>
            <a class="nav-item <?= $seccion === 'reportes' ? 'active' : '' ?>" href="dashboard.php?seccion=reportes"><span>📈</span> Reportes y Métricas</a>
            <a class="nav-item"href="productos.php"><span>📦</span> Productos</a>
            <a href="inventario.php" class="nav-item active"><span>📊</span> Inventario</a>
            <a href="movimientos.php" class="nav-item"><span>🔄</span>Movimientos</a>
            <a class="nav-item" href="configuracion.php"><span>⚙️</span> Configuracion</a>


        </nav>

        <div style="padding: 16px; border-top: 1px solid #0b2455;">
            <a href="logout.php" style="color: #ff6868; text-decoration: none; font-size: 13px; font-weight: 500;">🚪 Cerrar sesión</a>
        </div>
    </aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
    <?php if (!empty($mensaje)): ?>
        <div class="alert-success">✅ <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Filtrar alertas críticas</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-title">📊 Control de Inventario y Alertas</div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($es_editable): ?>
                <div style="display: flex; gap: 10px;">
                    <a href="movimientos.php" class="btn-primary">+ Registrar Movimiento</a>
                    <a href="pedidos.php" class="btn-primary" style="background: #071b3a;">Pedir a Proveedor</a>
                </div>
            <?php else: ?>
                <div style="color: var(--text-muted); font-size: 13px; font-style: italic;">
                    🔒 Modo consulta (Solo lectura)
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOOLBAR DE BÚSQUEDA Y FILTROS -->
    <div class="filters-bar">
        <form method="GET" class="filters">
            <input class="filter-input" type="text" name="q" placeholder="Buscar por insumo o descripción..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>" style="width: 260px;" autocomplete="off">
            <select class="filter-input" name="estado">
                <option value="">Todos los estados</option>
                <option value="critico" <?= $filtro_estado === 'critico' ? 'selected' : '' ?>>⚠️ Alerta Stock Bajo</option>
                <option value="optimo" <?= $filtro_estado === 'optimo' ? 'selected' : '' ?>>✔ Óptimo</option>
            </select>
            <button class="filter-input btn-secondary" type="submit">🔎 Filtrar</button>
            <?php if($busqueda !== '' || $filtro_estado !== ''): ?>
                <a href="inventario.php" class="filter-input btn-secondary" style="color:var(--color-danger); text-decoration:none;">✕ Limpiar</a>
            <?php endif; ?>
        </form>
        <div style="color: var(--text-muted); font-size: 13px;">
            Total registros: <b><?= htmlspecialchars(count($productos), ENT_QUOTES, 'UTF-8') ?></b>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Materia Prima / Insumo</th>
                    <th>Categoría / Descripción</th>
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
                    <tr><td colspan="8" style="text-align:center; color: var(--text-muted); padding: 30px;">No se encontraron registros de inventario con los filtros seleccionados.</td></tr>
                <?php else: foreach($productos as $p): $bajo = $p['stock_actual'] <= $p['stock_minimo']; ?>
                    <tr>
                        <td><b><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($p['descripcion'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($p['proveedor_nombre'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><b><?= htmlspecialchars($p['stock_actual'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><?= htmlspecialchars($p['stock_minimo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($p['estrategia_logistica'] ?? 'PUSH', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= $bajo ? '<span class="status-low">⚠️ Alerta Stock Bajo</span>' : '<span class="status-ok">✔ Óptimo</span>' ?></td>
                        <td style="text-align: right;">
                            <a href="productos.php?buscar=<?= urlencode($p['nombre']) ?>" title="Ver Producto" style="color: var(--color-primary); text-decoration: none; font-weight: 600; font-size: 16px; margin-right: 8px;">👁️</a>
                            <a href="movimientos.php?producto_id=<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>" title="Ver Movimientos" style="color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 16px; margin-right: 8px;">🔄</a>
                            
                            <?php if ($es_editable): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este insumo del inventario?');">
                                    <input type="hidden" name="accion" value="baja_logica">
                                    <input type="hidden" name="producto_id" value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" title="Eliminar insumo" style="background:none; border:none; color: var(--color-danger); cursor:pointer; font-size: 16px;">🗑️</button>
                                </form>
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