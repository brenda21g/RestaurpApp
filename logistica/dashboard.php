<?php
/**
 * Archivo: erp/dashboard.php
 * Descripción: Centro ERP sincronizado con SCM, pantalla de carga, validación estricta, pantalla de éxito (Imagen 7) y detalle de órdenes.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['logistica']);
$db = getDB();

$seccion = $_GET['seccion'] ?? 'inicio';
$paso = $_GET['paso'] ?? '1';
$error_msj = '';

// Procesar la creación de la orden por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_orden') {
        $cliente_id = $_POST['cliente_id'] ?? null;
        $items = $_POST['items'] ?? [];

        if (!empty($cliente_id) && !empty($items)) {
            try {
                $db->beginTransaction();
                $folio = "OR-" . date('Y') . "-" . rand(100, 999);
                
                $total = 0;
                $detalle_valido = [];

                foreach ($items as $prod_id => $cant) {
                    $cant = (int)$cant;
                    if ($cant > 0) {
                        $stmt_p = $db->prepare("SELECT id, nombre, precio FROM productos WHERE id = ?");
                        $stmt_p->execute([$prod_id]);
                        $prod = $stmt_p->fetch(PDO::FETCH_ASSOC);
                        if ($prod) {
                            $subtotal = $prod['precio'] * $cant;
                            $total += $subtotal;
                            $detalle_valido[] = ['id' => $prod_id, 'nombre' => $prod['nombre'], 'cantidad' => $cant, 'precio' => $prod['precio'], 'subtotal' => $subtotal];
                        }
                    }
                }

                if (!empty($detalle_valido)) {
                    
                    // ========================================================
                    // VALIDACIÓN ESTRICTA DE STOCK ANTES DE PROCESAR LA VENTA
                    // ========================================================
                    foreach ($detalle_valido as $dv) {
                        $stmt_ing = $db->prepare("
                            SELECT pi.scm_producto_id, pi.cantidad_requerida, sp.nombre as insumo_nombre, sp.stock_actual 
                            FROM producto_ingredientes pi 
                            JOIN scm_productos sp ON sp.id = pi.scm_producto_id 
                            WHERE pi.producto_id = ?
                        ");
                        $stmt_ing->execute([$dv['id']]);
                        $ingredientes = $stmt_ing->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($ingredientes)) {
                            foreach ($ingredientes as $ing) {
                                $cant_requerida_total = $ing['cantidad_requerida'] * $dv['cantidad'];
                                if ($ing['stock_actual'] < $cant_requerida_total) {
                                    throw new Exception("Stock insuficiente para el insumo '{$ing['insumo_nombre']}' (Requerido: {$cant_requerida_total}, Disponible: {$ing['stock_actual']}). Venta cancelada.");
                                }
                            }
                        } else {
                            $stmt_scm = $db->prepare("SELECT id, nombre, stock_actual FROM scm_productos WHERE id = ? OR nombre LIKE ?");
                            $stmt_scm->execute([$dv['id'], '%' . $dv['nombre'] . '%']);
                            $scm_prod = $stmt_scm->fetch(PDO::FETCH_ASSOC);

                            if ($scm_prod) {
                                if ($scm_prod['stock_actual'] < $dv['cantidad']) {
                                    throw new Exception("Stock insuficiente para el producto directo '{$scm_prod['nombre']}' (Requerido: {$dv['cantidad']}, Disponible: {$scm_prod['stock_actual']}). Venta cancelada.");
                                }
                            }
                        }
                    }
                    // ========================================================

                    // 1. Insertar orden ERP
                    $stmt_o = $db->prepare("INSERT INTO erp_ordenes (folio, cliente_id, fecha, total, estado) VALUES (?, ?, NOW(), ?, 'Procesada')");
                    $stmt_o->execute([$folio, $cliente_id, $total]);
                    $orden_id = $db->lastInsertId();

                    // 2. Registrar detalle y actualizar inventario SCM exacto con movimientos
                    foreach ($detalle_valido as $dv) {
                        $stmt_d = $db->prepare("INSERT INTO erp_ordenes_detalle (orden_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                        $stmt_d->execute([$orden_id, $dv['id'], $dv['cantidad'], $dv['precio'], $dv['subtotal']]);

                        $stmt_ing = $db->prepare("SELECT scm_producto_id, cantidad_requerida FROM producto_ingredientes WHERE producto_id = ?");
                        $stmt_ing->execute([$dv['id']]);
                        $ingredientes = $stmt_ing->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($ingredientes)) {
                            foreach ($ingredientes as $ing) {
                                $cant_descontar = $ing['cantidad_requerida'] * $dv['cantidad'];
                                
                                $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual - ? WHERE id = ?")
                                   ->execute([$cant_descontar, $ing['scm_producto_id']]);
                                
                                $db->prepare("INSERT INTO scm_movimientos (producto_id, tipo, cantidad, motivo, fecha, erp_orden_id) VALUES (?, 'Salida', ?, ?, CURDATE(), ?)")
                                   ->execute([$ing['scm_producto_id'], $cant_descontar, "Venta Platillo ERP: $folio", $orden_id]);
                            }
                        } else {
                            $stmt_scm_directo = $db->prepare("SELECT id FROM scm_productos WHERE id = ? OR nombre LIKE ?");
                            $stmt_scm_directo->execute([$dv['id'], '%' . $dv['nombre'] . '%']);
                            $scm_prod = $stmt_scm_directo->fetch(PDO::FETCH_ASSOC);

                            if ($scm_prod) {
                                $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual - ? WHERE id = ?")
                                   ->execute([$dv['cantidad'], $scm_prod['id']]);

                                $db->prepare("INSERT INTO scm_movimientos (producto_id, tipo, cantidad, motivo, fecha, erp_orden_id) VALUES (?, 'Salida', ?, ?, CURDATE(), ?)")
                                   ->execute([$scm_prod['id'], $dv['cantidad'], "Venta Directa SCM/ERP ($folio): {$dv['nombre']}", $orden_id]);
                            }
                        }
                    }

                    $db->commit();
                    // Redirigir activando la pantalla de carga y posterior éxito (Imagen 7)
                    header("Location: dashboard.php?seccion=ordenes&procesando=1&folio=$folio&id=$orden_id");
                    exit;
                } else {
                    $error_msj = "Selecciona al menos un producto con cantidad mayor a 0.";
                }
            } catch (Exception $e) {
                $db->rollBack();
                $error_msj = "Error al procesar la orden: " . $e->getMessage();
            }
        } else {
            $error_msj = "Debe seleccionar un cliente y productos válidos.";
        }
    }
}

// Consultas y métricas
$total_ventas = $db->query("SELECT SUM(total) FROM erp_ordenes WHERE estado = 'Procesada'")->fetchColumn() ?: 0;
$total_ordenes = $db->query("SELECT COUNT(*) FROM erp_ordenes")->fetchColumn() ?: 0;
$clientes_activos = $db->query("SELECT COUNT(*) FROM usuarios_cliente")->fetchColumn() ?: 0;

$alertas_stock = $db->query("SELECT * FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchAll(PDO::FETCH_ASSOC);
$total_alertas = count($alertas_stock);

$ordenes_recientes = $db->query("SELECT o.*, c.nombre as cliente_nombre FROM erp_ordenes o LEFT JOIN usuarios_cliente c ON c.id = o.cliente_id ORDER BY o.creado_en DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$todas_ordenes = $db->query("SELECT o.*, c.nombre as cliente_nombre FROM erp_ordenes o LEFT JOIN usuarios_cliente c ON c.id = o.cliente_id ORDER BY o.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$clientes_crm = $db->query("SELECT id, nombre, email, telefono FROM usuarios_cliente ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$categorias = $db->query("SELECT id, nombre FROM categorias ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$productos_db = $db->query("SELECT id, categoria_id, nombre, precio, descripcion FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$evaluaciones = $db->query("SELECT e.*, c.nombre as cliente_nombre FROM evaluaciones e LEFT JOIN usuarios_cliente c ON c.id = e.cliente_id ORDER BY e.fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
$usuarios_sistema = $db->query("SELECT id, username, nombre, email, rol, activo FROM admins ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Centro ERP – RestaurApp</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --bg: #f7f9fc;
        --card: #ffffff;
        --border: #e2e8f0;
        --text: #071b3a;
        --muted: #60708a;
        --sidebar: #03143d;
        --sidebar-hover: #092e70;
        --primary: #0788c9;
        --green: #13b77a;
        --danger: #ef5b63;
        --sidebar-w: 260px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'DM Sans', sans-serif; }
    body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; font-size: 14px; }
    
    .sidebar { width: var(--sidebar-w); background: var(--sidebar); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; overflow-y: auto; }
    .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid #0b2455; }
    .sidebar-logo .name { font-family: 'Playfair Display', serif; font-size: 20px; color: #fff; }
    .sidebar-logo .role { font-size: 11px; color: #91a6c7; text-transform: uppercase; margin-top: 2px; }
    
    .nav { padding: 16px 12px; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; color: #9db0ce; text-decoration: none; font-size: 13px; font-weight: 500; margin-bottom: 4px; transition: .15s; }
    .nav-item:hover, .nav-item.active { background: var(--sidebar-hover); color: #fff; }

    .sidebar-modules { padding: 16px 12px; border-top: 1px solid #0b2455; background: #020f2e; display: flex; flex-direction: column; gap: 8px; }
    .sidebar-modules-title { font-size: 10px; text-transform: uppercase; color: #7a94be; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px; }
    .sub-module-btn { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; color: #cbd5e1; background: rgba(255,255,255,0.04); text-decoration: none; font-size: 12px; font-weight: 500; transition: all .2s; border: 1px solid rgba(255,255,255,0.06); }
    .sub-module-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
    
    .main { margin-left: var(--sidebar-w); flex: 1; padding: 32px; }
    .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 700; }
    
    .alert-banner { background: #fdebed; border: 1px solid #f4c7cc; border-left: 4px solid var(--danger); padding: 15px; border-radius: 10px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; color: #c8424c; font-weight: 500; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
    .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 13px; padding: 22px; box-shadow: 0 2px 5px rgba(15,23,42,.025); }
    .stat-label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
    .stat-value { font-size: 26px; font-weight: 700; color: var(--text); }
    .stat-value.money::before { content: '$'; font-size: 18px; color: var(--muted); }

    .table-card { background: var(--card); border: 1px solid var(--border); border-radius: 13px; overflow: hidden; box-shadow: 0 2px 5px rgba(15,23,42,.025); margin-bottom: 25px; }
    .table-header { padding: 20px 24px; font-size: 15px; font-weight: 700; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 12px 24px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--muted); background: #f8fafc; border-bottom: 1px solid var(--border); }
    td { padding: 14px 24px; border-bottom: 1px solid #edf1f5; font-size: 13px; }
    
    .badge { padding: 4px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #e5f8f1; color: #0bad77; }
    .btn-primary { background: var(--primary); color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .btn-primary:hover { background: #0674ad; }
    .btn-success { background: var(--green); color: #fff; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-success:hover { background: #0f9e68; }
    .form-control { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; margin-top: 6px; margin-bottom: 16px; font-size: 14px; }
    
    .category-title { background: #eef2f7; padding: 12px 24px; font-size: 14px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }

    /* Pantalla de Carga */
    #loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 20, 61, 0.85); z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #fff; transition: opacity 0.3s ease; }
    .spinner { width: 50px; height: 50px; border: 5px solid rgba(255,255,255,0.2); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 20px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-step { font-size: 16px; font-weight: 500; margin: 6px 0; opacity: 0.8; }
    .loading-step.active { opacity: 1; font-weight: 700; color: #13b77a; }

    /* Estilos Pantalla de Éxito (Imagen 7) */
    .success-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 40px; text-align: center; max-width: 600px; margin: 20px auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .success-icon { width: 70px; height: 70px; background: #e5f8f1; color: var(--green); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
    .success-details { background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 20px; text-align: left; margin: 25px 0; }
    .success-details p { margin-bottom: 8px; font-size: 14px; color: var(--text); }
</style>
</head>
<body>

    <!-- PANTALLA DE CARGA Y POSTERIOR PASO A ÉXITO (Imagen 7) -->
    <?php if (isset($_GET['procesando'])): ?>
    <div id="loading-overlay">
        <div class="spinner"></div>
        <h2 style="font-family: 'Playfair Display', serif; font-size: 24px; margin-bottom: 15px;">Procesando orden...</h2>
        <div id="step-1" class="loading-step active">⏳ Creando orden en el sistema...</div>
        <div id="step-2" class="loading-step">⏳ Validando stock disponible...</div>
        <div id="step-3" class="loading-step">⏳ Actualizando inventario SCM y registrando movimientos...</div>
        <div id="step-4" class="loading-step" style="display:none; color: #13b77a; font-size: 18px; font-weight: 700; margin-top: 15px;">✅ ¡Orden <?= htmlspecialchars($_GET['folio'] ?? '') ?> registrada con éxito!</div>
    </div>
    <script>
        setTimeout(() => { document.getElementById('step-1').classList.remove('active'); document.getElementById('step-2').classList.add('active'); }, 800);
        setTimeout(() => { document.getElementById('step-2').classList.remove('active'); document.getElementById('step-3').classList.add('active'); }, 1600);
        setTimeout(() => { 
            document.getElementById('step-3').classList.remove('active'); 
            document.querySelector('.spinner').style.display = 'none';
            document.getElementById('step-4').style.display = 'block';
        }, 2400);
        // Redirigir a la pantalla de éxito (Imagen 7) después de la carga
        setTimeout(() => { window.location.href = 'dashboard.php?seccion=ordenes&paso=exito&folio=<?= $_GET['folio'] ?? '' ?>&id=<?= $_GET['id'] ?? '' ?>'; }, 3800);
    </script>
    <?php endif; ?>

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
            <a href="inventario.php" class="nav-item"><span>📊</span> Inventario</a>
            <a href="movimientos.php" class="nav-item"><span>🔄</span>Movimientos</a>
            <a class="nav-item" href="configuracion.php"><span>⚙️</span> Configuracion</a>


        </nav>

        <div style="padding: 16px; border-top: 1px solid #0b2455;">
            <a href="logout.php" style="color: #ff6868; text-decoration: none; font-size: 13px; font-weight: 500;">🚪 Cerrar sesión</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <div class="page-title">
                    <?php 
                        if($seccion === 'ordenes' && $paso === 'nueva') echo 'Nueva Orden de Venta (ERP)';
                        elseif($seccion === 'ordenes' && $paso === 'exito') echo 'Operación exitosa (Imagen 7)';
                        elseif($seccion === 'ordenes' && $paso === 'detalle') echo 'Detalle de la Orden';
                        elseif($seccion === 'ordenes') echo 'Listado de Órdenes (ERP)';
                        elseif($seccion === 'reportes') echo 'Reportes del Negocio';
                        elseif($seccion === 'usuarios') echo 'Usuarios del Sistema';
                        else echo 'Dashboard General ERP';
                    ?>
                </div>
                <div style="color: var(--muted); font-size: 13px; margin-top: 4px;">Sincronización en tiempo real: Las ventas actualizan el inventario SCM</div>
            </div>
            <div style="background: #fff; border: 1px solid var(--border); padding: 10px 16px; border-radius: 10px; font-size: 13px;">
                📅 <?= date('d/m/Y H:i') ?>
            </div>
        </div>

        <?php if ($total_alertas > 0): ?>
            <div class="alert-banner">
                <span>⚠️ <b>¡Atención Inventario SCM!</b> Hay <?= $total_alertas ?> insumo(s) con stock crítico por debajo del mínimo permitido.</span>
                <a href="../scm/inventario.php" style="background: #c8424c; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600;">Revisar SCM</a>
            </div>
        <?php endif; ?>

        <?php if ($error_msj): ?>
            <div style="background: #fdebed; color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                ⚠️ <?= htmlspecialchars($error_msj) ?>
            </div>
        <?php endif; ?>

        <!-- SECCIÓN 1: INICIO ERP -->
        <?php if ($seccion === 'inicio'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Ventas Totales ERP</div>
                    <div class="stat-value money"><?= number_format($total_ventas, 2) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Órdenes Registradas</div>
                    <div class="stat-value"><?= $total_ordenes ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Clientes Activos (CRM)</div>
                    <div class="stat-value" style="color: var(--green);"><?= $clientes_activos ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Alertas Críticas SCM</div>
                    <div class="stat-value" style="color: var(--danger);"><?= $total_alertas ?></div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <span>📋 Órdenes Recientes (ERP)</span>
                    <a href="dashboard.php?seccion=ordenes" style="font-size: 12px; color: var(--primary); text-decoration: none;">Ver todas →</a>
                </div>
                <table>
                    <thead>
                        <tr><th>Folio</th><th>Fecha</th><th>Cliente (CRM)</th><th>Total</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ordenes_recientes)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 30px;">No hay órdenes recientes.</td></tr>
                        <?php else: foreach ($ordenes_recientes as $o): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($o['folio'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                <td style="color: var(--muted);"><?= htmlspecialchars($o['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($o['cliente_nombre'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>$<?= number_format($o['total'], 2) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($o['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- SECCIÓN 2: NUEVA ORDEN (Directa con retención de datos y botón "Hacer pedido") -->
        <?php elseif ($seccion === 'ordenes' && $paso === 'nueva'): ?>
            <div class="table-card" style="padding: 25px;">
                <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h3>🛍️ Registrar Nueva Orden (Venta ERP)</h3>
                    <a href="dashboard.php?seccion=ordenes" style="color: var(--muted); text-decoration: none; font-size: 13px;">← Cancelar y regresar</a>
                </div>

                <form method="POST">
                    <input type="hidden" name="accion" value="guardar_orden">
                    
                    <label style="font-weight: 600; text-transform: uppercase; font-size: 11px; color: var(--muted);">Paso 1: Seleccionar Cliente (CRM)</label>
                    <select name="cliente_id" class="form-control" required>
                        <option value="">-- Seleccionar cliente del CRM --</option>
                        <?php foreach ($clientes_crm as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label style="font-weight: 600; text-transform: uppercase; font-size: 11px; color: var(--muted); margin-top: 15px; display: block;">Paso 2: Seleccionar Productos por Categorías de la BD</label>
                    
                    <div style="border: 1px solid var(--border); border-radius: 10px; overflow: hidden; margin-top: 10px; margin-bottom: 20px;">
                        <table>
                            <thead>
                                <tr><th>Producto / Platillo</th><th>Precio Unitario</th><th style="width: 150px;">Cantidad</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $cat): ?>
                                    <tr><td colspan="3" class="category-title">📁 <?= htmlspecialchars($cat['nombre']) ?></td></tr>
                                    <?php 
                                        $hay_prod = false;
                                        foreach ($productos_db as $p): 
                                            if ($p['categoria_id'] == $cat['id']):
                                                $hay_prod = true;
                                                $val_anterior = isset($_POST['items'][$p['id']]) ? (int)$_POST['items'][$p['id']] : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <b><?= htmlspecialchars($p['nombre']) ?></b>
                                            <?php if(!empty($p['descripcion'])): ?>
                                                <div style="font-size: 11px; color: var(--muted);"><?= htmlspecialchars($p['descripcion']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>$<?= number_format($p['precio'], 2) ?></td>
                                        <td>
                                            <input type="number" name="items[<?= $p['id'] ?>]" value="<?= $val_anterior ?>" min="0" class="form-control" style="margin: 0;">
                                        </td>
                                    </tr>
                                    <?php 
                                            endif;
                                        endforeach; 
                                        if (!$hay_prod):
                                    ?>
                                        <tr><td colspan="3" style="color: var(--muted); text-align: center; padding: 10px; font-style: italic;">No hay productos en esta categoría.</td></tr>
                                    <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px; font-size: 15px;">Hacer pedido</button>
                </form>
            </div>

        <!-- SECCIÓN 2.1: OPERACIÓN EXITOSA (Imagen 7) -->
        <?php elseif ($seccion === 'ordenes' && $paso === 'exito'): ?>
            <?php 
                $orden_id_exito = $_GET['id'] ?? 0;
                $stmt_ex = $db->prepare("SELECT o.*, c.nombre as cliente_nombre FROM erp_ordenes o LEFT JOIN usuarios_cliente c ON c.id = o.cliente_id WHERE o.id = ?");
                $stmt_ex->execute([$orden_id_exito]);
                $orden_exitosa = $stmt_ex->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="success-card">
                <div class="success-icon">✓</div>
                <h2 style="font-family: 'Playfair Display', serif; font-size: 24px; color: var(--text);">¡Orden registrada con éxito!</h2>
                
                <div class="success-details">
                    <p><b>Folio:</b> <?= htmlspecialchars($orden_exitosa['folio'] ?? 'OR-000') ?></p>
                    <p><b>Cliente:</b> <?= htmlspecialchars($orden_exitosa['cliente_nombre'] ?? 'General') ?></p>
                    <p><b>Total:</b> $<?= number_format($orden_exitosa['total'] ?? 0, 2) ?></p>
                    <p><b>Fecha:</b> <?= htmlspecialchars($orden_exitosa['fecha'] ?? date('Y-m-d H:i')) ?></p>
                </div>

                <div style="display: flex; gap: 15px; justify-content: center;">
                    <a href="dashboard.php?seccion=ordenes&paso=detalle&id=<?= $orden_id_exito ?>" class="btn-primary" style="padding: 12px 24px;">Ver orden</a>
                    <a href="dashboard.php?seccion=ordenes" class="btn-success" style="padding: 12px 24px; background: var(--sidebar);">Ir a órdenes</a>
                </div>
            </div>

        <!-- SECCIÓN 2.2: DETALLE DE UNA ORDEN ESPECÍFICA (Ver orden / Lupa) -->
        <?php elseif ($seccion === 'ordenes' && $paso === 'detalle'): ?>
            <?php 
                $id_det = $_GET['id'] ?? 0;
                $stmt_ord = $db->prepare("SELECT o.*, c.nombre as cliente_nombre, c.email as cliente_email, c.telefono as cliente_tel FROM erp_ordenes o LEFT JOIN usuarios_cliente c ON c.id = o.cliente_id WHERE o.id = ?");
                $stmt_ord->execute([$id_det]);
                $ord_info = $stmt_ord->fetch(PDO::FETCH_ASSOC);

                $stmt_det = $db->prepare("SELECT od.*, p.nombre as producto_nombre FROM erp_ordenes_detalle od JOIN productos p ON p.id = od.producto_id WHERE od.orden_id = ?");
                $stmt_det->execute([$id_det]);
                $items_det = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="table-card" style="padding: 25px; max-width: 800px; margin: 0 auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>🔍 Detalle de la Orden: <b><?= htmlspecialchars($ord_info['folio'] ?? '') ?></b></h3>
                    <a href="dashboard.php?seccion=ordenes" style="color: var(--primary); text-decoration: none;">← Volver al listado</a>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                    <div>
                        <p style="margin-bottom: 6px;"><b>📅 Fecha:</b> <?= htmlspecialchars($ord_info['fecha'] ?? '') ?></p>
                        <p style="margin-bottom: 6px;"><b>📌 Estado:</b> <span class="badge"><?= htmlspecialchars($ord_info['estado'] ?? '') ?></span></p>
                    </div>
                    <div>
                        <p style="margin-bottom: 6px;"><b>👤 Cliente:</b> <?= htmlspecialchars($ord_info['cliente_nombre'] ?? 'General') ?></p>
                        <p style="margin-bottom: 6px;"><b>✉️ Correo:</b> <?= htmlspecialchars($ord_info['cliente_email'] ?? 'N/A') ?></p>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr><th>Producto</th><th>Cantidad</th><th>Precio Unitario</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items_det as $item): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($item['producto_nombre']) ?></b></td>
                            <td><?= $item['cantidad'] ?></td>
                            <td>$<?= number_format($item['precio_unitario'], 2) ?></td>
                            <td>$<?= number_format($item['subtotal'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" style="text-align: right; font-weight: 700; font-size: 15px;">Total de la Orden:</td>
                            <td style="font-weight: 700; font-size: 16px; color: var(--primary);">$<?= number_format($ord_info['total'] ?? 0, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <!-- SECCIÓN 2.3: LISTADO GENERAL DE ÓRDENES CON LUPA (Imagen 2) -->
        <?php elseif ($seccion === 'ordenes'): ?>
            <div class="table-card">
                <div class="table-header">
                    <span>🛒 Listado de Órdenes Registradas (ERP)</span>
                    <a href="dashboard.php?seccion=ordenes&paso=nueva" class="btn-primary">+ Nueva Orden</a>
                </div>
                <table>
                    <thead>
                        <tr><th>Folio</th><th>Fecha</th><th>Cliente (CRM)</th><th>Total</th><th>Estado</th><th style="text-align: center;">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todas_ordenes)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 30px;">No hay órdenes registradas.</td></tr>
                        <?php else: foreach ($todas_ordenes as $o): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($o['folio'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                <td style="color: var(--muted);"><?= htmlspecialchars($o['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($o['cliente_nombre'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>$<?= number_format($o['total'], 2) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($o['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="text-align: center;">
                                    <a href="dashboard.php?seccion=ordenes&paso=detalle&id=<?= $o['id'] ?>" title="Ver detalle de orden" style="background: var(--primary); color: #fff; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 12px;">🔍 Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- SECCIÓN 3: REPORTES -->
        <?php elseif ($seccion === 'reportes'): ?>
            <div class="table-card">
                <div class="table-header">📈 Reporte General de Evaluaciones (CRM)</div>
                <table>
                    <thead>
                        <tr><th>Fecha</th><th>Cliente</th><th>Puntuación</th><th>Tipo</th><th>Comentario</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($evaluaciones)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 30px;">Sin evaluaciones registradas.</td></tr>
                        <?php else: foreach ($evaluaciones as $ev): ?>
                            <tr>
                                <td style="color: var(--muted);"><?= htmlspecialchars($ev['fecha']) ?></td>
                                <td><b><?= htmlspecialchars($ev['cliente_nombre'] ?? 'Anónimo') ?></b></td>
                                <td style="color: #f5a000; font-weight: 700;"><?= $ev['puntuacion'] ?> ★</td>
                                <td><?= htmlspecialchars($ev['tipo']) ?></td>
                                <td style="color: var(--muted);"><?= htmlspecialchars($ev['comempotario'] ?? $ev['comentario']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- SECCIÓN 4: USUARIOS -->
        <?php elseif ($seccion === 'usuarios'): ?>
            <div class="table-card">
                <div class="table-header">⚙️ Control de Usuarios del Sistema</div>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios_sistema as $usr): ?>
                            <tr>
                                <td><?= $usr['id'] ?></td>
                                <td><b><?= htmlspecialchars($usr['username']) ?></b></td>
                                <td><?= htmlspecialchars($usr['nombre'] ?? 'N/A') ?></td>
                                <td style="color: var(--muted);"><?= htmlspecialchars($usr['email']) ?></td>
                                <td><span style="text-transform:uppercase; font-weight:600; color:var(--primary);"><?= htmlspecialchars($usr['rol']) ?></span></td>
                                <td><span class="badge"><?= $usr['activo'] == 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </main>

    <!-- RECARGA AUTOMÁTICA INTELIGENTE -->
    <script>
        setInterval(() => {
            const esProcesoOrden = window.location.search.includes('paso=nueva') || window.location.search.includes('paso=exito');
            if (!document.hidden && !document.getElementById('loading-overlay') && !esProcesoOrden) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>