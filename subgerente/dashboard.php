<?php
/**
 * Archivo: subgerente/dashboard.php
 * Descripción: Dashboard principal del Subgerente con métricas operativas, gráficos, cortes de caja y módulo integrado de órdenes/ventas ERP con SCM, pantalla de éxito y detalle.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['subgerente', 'gerente']);
$db = getDB();

$seccion = $_GET['seccion'] ?? 'inicio';
$paso = $_GET['paso'] ?? '1';
$error_msj = '';

/* Procesar la creación de la orden por POST */
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

                    // 1. Insertar orden ERP/Ventas
                    $stmt_o = $db->prepare("INSERT INTO erp_ordenes (folio, cliente_id, fecha, total, estado) VALUES (?, ?, NOW(), ?, 'Procesada')");
                    $stmt_o->execute([$folio, $cliente_id, $total]);
                    $orden_id = $db->lastInsertId();

                    // 2. Registrar detalle y actualizar inventario SCM exacto
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
                                   ->execute([$ing['scm_producto_id'], $cant_descontar, "Venta Subgerencia: $folio", $orden_id]);
                            }
                        } else {
                            $stmt_scm_directo = $db->prepare("SELECT id FROM scm_productos WHERE id = ? OR nombre LIKE ?");
                            $stmt_scm_directo->execute([$dv['id'], '%' . $dv['nombre'] . '%']);
                            $scm_prod = $stmt_scm_directo->fetch(PDO::FETCH_ASSOC);

                            if ($scm_prod) {
                                $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual - ? WHERE id = ?")
                                   ->execute([$dv['cantidad'], $scm_prod['id']]);

                                $db->prepare("INSERT INTO scm_movimientos (producto_id, tipo, cantidad, motivo, fecha, erp_orden_id) VALUES (?, 'Salida', ?, ?, CURDATE(), ?)")
                                   ->execute([$scm_prod['id'], $dv['cantidad'], "Venta Directa Subgerencia ($folio): {$dv['nombre']}", $orden_id]);
                            }
                        }
                    }

                    $db->commit();
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

/* Estadísticas del día y datos originales */
$hoy = date('Y-m-d');

$stats_dia = $db->prepare("
    SELECT
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN estado='entregado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado='entregado' THEN total ELSE 0 END) as ingresos,
        SUM(CASE WHEN estado='pendiente' OR estado='preparando' THEN 1 ELSE 0 END) as activos
    FROM pedidos
    WHERE DATE(creado_en) = ?
");
$stats_dia->execute([$hoy]);
$stats = $stats_dia->fetch(PDO::FETCH_ASSOC);

$por_hora = $db->prepare("
    SELECT
        HOUR(creado_en) as hora,
        COUNT(*) as cantidad,
        SUM(total) as ingresos
    FROM pedidos
    WHERE DATE(creado_en) = ?
    GROUP BY HOUR(creado_en)
    ORDER BY hora ASC
");
$por_hora->execute([$hoy]);
$pedidos_hora = $por_hora->fetchAll(PDO::FETCH_ASSOC);

$top_prod = $db->prepare("
    SELECT
        p.nombre,
        SUM(pi.cantidad) as vendidos,
        SUM(pi.subtotal) as total_ing
    FROM pedido_items pi
    JOIN productos p ON p.id = pi.producto_id
    JOIN pedidos pe ON pe.id = pi.pedido_id
    WHERE DATE(pe.creado_en) = ?
    AND pe.estado = 'entregado'
    GROUP BY p.id, p.nombre
    ORDER BY vendidos DESC
    LIMIT 8
");
$top_prod->execute([$hoy]);
$top_productos = $top_prod->fetchAll(PDO::FETCH_ASSOC);

$ultimos = $db->prepare("
    SELECT
        pe.*,
        m.numero as mesa_num
    FROM pedidos pe
    JOIN mesas m ON m.id = pe.mesa_id
    ORDER BY pe.creado_en DESC
    LIMIT 10
");
$ultimos->execute();
$ultimos_pedidos = $ultimos->fetchAll(PDO::FETCH_ASSOC);

// Alertas y datos adicionales para ventas/ERP
$alertas_stock = $db->query("SELECT * FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchAll(PDO::FETCH_ASSOC);
$total_alertas = count($alertas_stock);
$clientes_crm = $db->query("SELECT id, nombre, email, telefono FROM usuarios_cliente ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $db->query("SELECT id, nombre FROM categorias ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$productos_db = $db->query("SELECT id, categoria_id, nombre, precio, descripcion FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$todas_ordenes_erp = $db->query("SELECT o.*, c.nombre as cliente_nombre FROM erp_ordenes o LEFT JOIN usuarios_cliente c ON c.id = o.cliente_id ORDER BY o.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Subgerencia – RestaurApp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #f7f9fc;
            --card: #ffffff;
            --border: #e2e8f0;
            --accent: #ffffff;
            --text: #071b3a;
            --muted: #60708a;
            --sidebar: #03143d;
            --sidebar-hover: #092e70;
            --primary: #0788c9;
            --primary-dark: #0674ad;
            --green-dashboard: #13b77a;
            --amber: #f5a000;
            --red: #ff5b5b;
            --sidebar-w: 250px;
            --card-border: #e1e7ef;
            --card-bg: #ffffff;
        }
        body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; display: flex; min-height: 100vh; font-size: 14px; }
        
        .sidebar { width: var(--sidebar-w); background: var(--sidebar); border-right: 1px solid #0b2455; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; overflow-y: auto; }
        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid #0b2455; }
        .sidebar-logo .name { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--accent); }
        .sidebar-logo .role { font-size: 11px; color: #91a6c7; letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
        
        .nav { padding: 16px 12px; flex: 1; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; color: #9db0ce; text-decoration: none; font-size: 13px; font-weight: 500; transition: all .15s; margin-bottom: 4px; }
        .nav-item:hover, .nav-item.active { background: var(--sidebar-hover); color: #ffffff; }
        .nav-item .icon { font-size: 15px; }

        .sidebar-bottom { padding: 16px 12px; border-top: 1px solid #0b2455; }
        .logout-btn { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: #ff6868; text-decoration: none; font-size: 13px; font-weight: 500; transition: background .15s; }
        .logout-btn:hover { background: rgba(255,104,104,.1); }

        .main { margin-left: var(--sidebar-w); flex: 1; padding: 28px 32px; }
        .topbar { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
        .top-subtitle { color: var(--muted); font-size: 13px; }
        .date-badge { background: #ffffff; border: 1px solid var(--card-border); border-radius: 11px; padding: 12px 19px; font-size: 13px; font-weight: 500; color: var(--muted); box-shadow: 0 2px 5px rgba(15,23,42,.04); }

        .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--green-dashboard); margin-right: 7px; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }

        .alert-banner { background: #fdebed; border: 1px solid #f4c7cc; border-left: 4px solid var(--red); padding: 15px; border-radius: 10px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; color: #c8424c; font-weight: 500; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 13px; padding: 22px; position: relative; min-height: 128px; box-shadow: 0 2px 5px rgba(15,23,42,.025); }
        .stat-label { font-size: 11px; font-weight: 600; letter-spacing: .6px; text-transform: uppercase; color: var(--muted); margin-bottom: 13px; }
        .stat-value { font-size: 30px; font-weight: 700; color: var(--text); line-height: 1; }
        .stat-value.money::before { content: '$'; font-size: 19px; color: var(--muted); margin-right: 2px; }
        .stat-icon { position: absolute; top: 19px; right: 19px; font-size: 23px; opacity: .35; }

        .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .chart-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 13px; padding: 23px; box-shadow: 0 2px 5px rgba(15,23,42,.025); }
        .chart-title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 22px; display: flex; align-items: center; gap: 8px; }

        .bar-chart { display: flex; align-items: flex-end; gap: 9px; height: 145px; padding-top: 8px; }
        .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 7px; height: 100%; justify-content: flex-end; }
        .bar { width: 100%; max-width: 38px; background: var(--primary); border-radius: 5px 5px 2px 2px; min-height: 5px; }
        .bar-label { font-size: 10px; font-weight: 600; color: var(--muted); }

        .prod-list { display: flex; flex-direction: column; gap: 13px; }
        .prod-item { display: flex; align-items: center; gap: 10px; }
        .prod-name { font-size: 13px; font-weight: 600; color: var(--text); min-width: 120px; }
        .prod-bar-wrap { flex: 1; height: 8px; background: #edf2f7; border-radius: 10px; overflow: hidden; }
        .prod-bar { height: 100%; background: var(--primary); border-radius: 10px; }
        .prod-count { font-size: 12px; font-weight: 600; color: var(--muted); width: 52px; text-align: right; flex-shrink: 0; }

        .table-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 13px; overflow: hidden; box-shadow: 0 2px 5px rgba(15,23,42,.025); margin-bottom: 25px; }
        .table-header { padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--card-border); }
        .table-header h3 { font-size: 15px; font-weight: 700; color: var(--text); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 24px; font-size: 10.5px; font-weight: 600; letter-spacing: .7px; text-transform: uppercase; color: var(--muted); background: #f8fafc; border-bottom: 1px solid var(--card-border); }
        td { padding: 14px 24px; border-bottom: 1px solid #edf1f5; font-size: 13px; font-weight: 500; color: var(--text); }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #fafcff; }

        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 5px 11px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge.pendiente { background: #fff4d8; color: #d88900; }
        .badge.preparando { background: #e3f4fb; color: #0788c9; }
        .badge.entregado, .badge.Procesada { background: #e5f8f1; color: #0bad77; }
        .badge.cancelado { background: #fde9eb; color: #dc4c55; }

        .cortes-section { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 13px; padding: 22px 24px; box-shadow: 0 2px 5px rgba(15,23,42,.025); }
        .cortes-title { font-size: 15px; font-weight: 700; margin-bottom: 17px; display: flex; align-items: center; gap: 8px; color: var(--text); }
        .cortes-btns { display: flex; gap: 11px; flex-wrap: wrap; }
        .corte-btn { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; border: 1px solid var(--card-border); background: #ffffff; color: var(--text); font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .15s; }
        .corte-btn:hover { background: #f7fafc; border-color: #c8d3df; }
        .corte-btn.primary { background: var(--primary); color: #ffffff; border-color: var(--primary); }
        .corte-btn.primary:hover { background: var(--primary-dark); }

        .btn-primary { background: var(--primary); color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--green-dashboard); color: #fff; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-success:hover { background: #0f9e68; }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; margin-top: 6px; margin-bottom: 16px; font-size: 14px; }
        .category-title { background: #eef2f7; padding: 12px 24px; font-size: 14px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }

        /* Pantalla de Carga */
        #loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 20, 61, 0.85); z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #fff; transition: opacity 0.3s ease; }
        .spinner { width: 50px; height: 50px; border: 5px solid rgba(255,255,255,0.2); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-step { font-size: 16px; font-weight: 500; margin: 6px 0; opacity: 0.8; }
        .loading-step.active { opacity: 1; font-weight: 700; color: var(--green-dashboard); }

        /* Estilos Pantalla de Éxito (Imagen 7) */
        .success-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 40px; text-align: center; max-width: 600px; margin: 20px auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .success-icon { width: 70px; height: 70px; background: #e5f8f1; color: var(--green-dashboard); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .success-details { background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 20px; text-align: left; margin: 25px 0; }
        .success-details p { margin-bottom: 8px; font-size: 14px; color: var(--text); }
    </style>
</head>
<body>

    <!-- PANTALLA DE CARGA Y POSTERIOR PASO A ÉXITO -->
    <?php if (isset($_GET['procesando'])): ?>
    <div id="loading-overlay">
        <div class="spinner"></div>
        <h2 style="font-family: 'Playfair Display', serif; font-size: 24px; margin-bottom: 15px;">Procesando orden...</h2>
        <div id="step-1" class="loading-step active">⏳ Creando orden en el sistema...</div>
        <div id="step-2" class="loading-step">⏳ Validando stock disponible...</div>
        <div id="step-3" class="loading-step">⏳ Actualizando inventario SCM y registrando movimientos...</div>
        <div id="step-4" class="loading-step" style="display:none; color: var(--green-dashboard); font-size: 18px; font-weight: 700; margin-top: 15px;">✅ ¡Orden <?= htmlspecialchars($_GET['folio'] ?? '') ?> registrada con éxito!</div>
    </div>
    <script>
        setTimeout(() => { document.getElementById('step-1').classList.remove('active'); document.getElementById('step-2').classList.add('active'); }, 800);
        setTimeout(() => { document.getElementById('step-2').classList.remove('active'); document.getElementById('step-3').classList.add('active'); }, 1600);
        setTimeout(() => { 
            document.getElementById('step-3').classList.remove('active'); 
            document.querySelector('.spinner').style.display = 'none';
            document.getElementById('step-4').style.display = 'block';
        }, 2400);
        setTimeout(() => { window.location.href = 'dashboard.php?seccion=ordenes&paso=exito&folio=<?= $_GET['folio'] ?? '' ?>&id=<?= $_GET['id'] ?? '' ?>'; }, 3800);
    </script>
    <?php endif; ?>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="name">🍽️ RestaurApp</div>
            <div class="role">Subgerencia / Ventas</div>
        </div>

        <nav class="nav">
            <a class="nav-item <?= $seccion === 'inicio' ? 'active' : '' ?>" href="dashboard.php?seccion=inicio">
                <span class="icon">📊</span> Dashboard Principal
            </a>
            <a class="nav-item <?= $seccion === 'ordenes' && $paso !== 'nueva' ? 'active' : '' ?>" href="dashboard.php?seccion=ordenes">
                <span class="icon">🛒</span> Órdenes y Ventas
            </a>
            <a class="nav-item <?= $seccion === 'ordenes' && $paso === 'nueva' ? 'active' : '' ?>" href="dashboard.php?seccion=ordenes&paso=nueva" style="padding-left: 32px; font-size: 12px;">
                <span class="icon">➕</span> Hacer Pedido de Venta
            </a>
            <a class="nav-item" href="pedidos.php">
                <span class="icon">📋</span> Pedidos de Mesas
            </a>
            <a class="nav-item" href="mesas_qr.php">
                <span class="icon">🪑</span> Mesas & QR
            </a>
            <a class="nav-item" href="menu.php">
                <span class="icon">🍽</span> Menú
            </a>
            <a class="nav-item" href="corte.php">
                <span class="icon">💵</span> Corte de Caja
            </a>
            
        </nav>

        <div class="sidebar-bottom">
            <a class="logout-btn" href="logout.php">
                🚪 Cerrar sesión
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <div class="topbar">
            <div>
                <div class="page-title">
                    <?php 
                        if($seccion === 'ordenes' && $paso === 'nueva') echo 'Registrar Nueva Orden de Venta';
                        elseif($seccion === 'ordenes' && $paso === 'exito') echo 'Operación exitosa (Imagen 7)';
                        elseif($seccion === 'ordenes' && $paso === 'detalle') echo 'Detalle de la Orden';
                        elseif($seccion === 'ordenes') echo 'Gestión de Órdenes y Ventas';
                        else echo 'Dashboard Operativo';
                    ?>
                </div>
                <div class="top-subtitle">
                    <span class="live-dot"></span> Sincronizado en tiempo real con SCM e Inventario
                </div>
            </div>
            <div class="date-badge">
                📅 <?= htmlspecialchars(date('d \d\e F \d\e Y'), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <?php if ($total_alertas > 0): ?>
            <div class="alert-banner">
                <span>⚠️ <b>¡Atención Inventario SCM!</b> Hay <?= $total_alertas ?> insumo(s) con stock crítico por debajo del mínimo permitido.</span>
                <a href="../scm/inventario.php" style="background: #c8424c; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600;">Revisar SCM</a>
            </div>
        <?php endif; ?>

        <?php if ($error_msj): ?>
            <div style="background: #fdebed; color: var(--red); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                ⚠️ <?= htmlspecialchars($error_msj) ?>
            </div>
        <?php endif; ?>

        <!-- SECCIÓN 1: DASHBOARD PRINCIPAL -->
        <?php if ($seccion === 'inicio'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-icon">💰</span>
                    <div class="stat-label">Ingresos del día</div>
                    <div class="stat-value money"><?= number_format($stats['ingresos'] ?? 0, 2) ?></div>
                </div>

                <div class="stat-card">
                    <span class="stat-icon">📋</span>
                    <div class="stat-label">Total pedidos</div>
                    <div class="stat-value"><?= htmlspecialchars($stats['total_pedidos'] ?? 0, ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="stat-card">
                    <span class="stat-icon">✅</span>
                    <div class="stat-label">Completados</div>
                    <div class="stat-value" style="color:var(--green-dashboard);"><?= htmlspecialchars($stats['completados'] ?? 0, ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="stat-card">
                    <span class="stat-icon">⏳</span>
                    <div class="stat-label">En proceso</div>
                    <div class="stat-value" style="color:var(--amber);"><?= htmlspecialchars($stats['activos'] ?? 0, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-title">📊 Pedidos por hora (hoy)</div>
                    <?php $max_pedidos = max(array_column($pedidos_hora, 'cantidad') ?: [1]); ?>
                    <div class="bar-chart">
                        <?php if (empty($pedidos_hora)): ?>
                            <div style="color:var(--muted); font-size:13px; width:100%; text-align:center; padding:40px 0;">Sin pedidos hoy</div>
                        <?php else: foreach ($pedidos_hora as $ph): ?>
                            <div class="bar-col">
                                <div style="font-size:10px; color:var(--muted);"><?= htmlspecialchars($ph['cantidad'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="bar" style="height:<?= round(($ph['cantidad'] / $max_pedidos) * 100) ?>%;"></div>
                                <div class="bar-label"><?= htmlspecialchars(str_pad($ph['hora'], 2, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?>h</div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-title">🔥 Productos más vendidos (hoy)</div>
                    <?php if (empty($top_productos)): ?>
                        <div style="color:var(--muted); font-size:13px; padding:20px 0;">Sin ventas completadas hoy</div>
                    <?php else: ?>
                        <?php $max_v = max(array_column($top_productos, 'vendidos') ?: [1]); ?>
                        <div class="prod-list">
                            <?php foreach ($top_productos as $i => $p): ?>
                                <div class="prod-item">
                                    <span style="width:20px; font-size:11px; color:var(--muted); text-align:right; flex-shrink:0;"><?= htmlspecialchars($i + 1, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="prod-name"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="prod-bar-wrap">
                                        <div class="prod-bar" style="width:<?= round(($p['vendidos'] / $max_v) * 100) ?>%;"></div>
                                    </div>
                                    <span class="prod-count"><?= htmlspecialchars($p['vendidos'], ENT_QUOTES, 'UTF-8') ?> uds</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h3>📋 Registro de pedidos recientes</h3>
                    <a href="pedidos.php" style="color:var(--primary); font-size:13px; font-weight:600; text-decoration:none;">Ver todos →</a>
                </div>
                <table>
                    <thead>
                        <tr><th>Orden</th><th>Mesa</th><th>Total</th><th>Estado</th><th>Hora</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimos_pedidos)): ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:30px;">Sin pedidos aún</td></tr>
                        <?php else: foreach ($ultimos_pedidos as $p): ?>
                            <tr>
                                <td style="font-weight:700;"><?= htmlspecialchars($p['numero_orden'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>Mesa <?= htmlspecialchars($p['mesa_num'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>$<?= number_format($p['total'] ?? 0, 2) ?></td>
                                <td><span class="badge <?= htmlspecialchars($p['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($p['estado'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="color:var(--muted);"><?= htmlspecialchars(date('H:i', strtotime($p['creado_en'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="cortes-section">
                <div class="cortes-title">🖨️ Cortes de caja e impresión</div>
                <div class="cortes-btns">
                    <a href="corte.php?tipo=dia" class="corte-btn primary">📄 Corte del día</a>
                    <a href="corte.php?tipo=semana" class="corte-btn">📅 Corte semanal</a>
                    <a href="corte.php?tipo=mes" class="corte-btn">📆 Corte mensual</a>
                </div>
            </div>

        <!-- SECCIÓN 2: REGISTRAR NUEVA ORDEN (Con retención de datos) -->
        <?php elseif ($seccion === 'ordenes' && $paso === 'nueva'): ?>
            <div class="table-card" style="padding: 25px;">
                <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h3>🛍️ Registrar Nueva Orden (Subgerencia / SCM)</h3>
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

                    <label style="font-weight: 600; text-transform: uppercase; font-size: 11px; color: var(--muted); margin-top: 15px; display: block;">Paso 2: Seleccionar Productos por Categorías</label>
                    
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
                                        <td><input type="number" name="items[<?= $p['id'] ?>]" value="<?= $val_anterior ?>" min="0" class="form-control" style="margin: 0;"></td>
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

        <!-- SECCIÓN 3: LISTADO DE ÓRDENES ERP/VENTAS CON LUPA -->
        <?php elseif ($seccion === 'ordenes'): ?>
            <div class="table-card">
                <div class="table-header">
                    <h3>🛒 Listado de Órdenes y Ventas Registradas</h3>
                    <a href="dashboard.php?seccion=ordenes&paso=nueva" class="btn-primary">+ Nueva Orden</a>
                </div>
                <table>
                    <thead>
                        <tr><th>Folio</th><th>Fecha</th><th>Cliente (CRM)</th><th>Total</th><th>Estado</th><th style="text-align: center;">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todas_ordenes_erp)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 30px;">No hay órdenes registradas.</td></tr>
                        <?php else: foreach ($todas_ordenes_erp as $o): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($o['folio'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                <td style="color: var(--muted);"><?= htmlspecialchars($o['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($o['cliente_nombre'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>$<?= number_format($o['total'], 2) ?></td>
                                <td><span class="badge <?= htmlspecialchars($o['estado'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($o['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="text-align: center;">
                                    <a href="dashboard.php?seccion=ordenes&paso=detalle&id=<?= $o['id'] ?>" title="Ver detalle de orden" style="background: var(--primary); color: #fff; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 12px;">🔍 Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <!-- SCRIPT DE RECARGA INTELIGENTE (Pausada en formularios y éxito) -->
    <script>
        (function() {
            const INTERVALO_RECARGA = 30000;
            setInterval(() => {
                const esProcesoOrden = window.location.search.includes('paso=nueva') || window.location.search.includes('paso=exito');
                if (!document.hidden && !document.getElementById('loading-overlay') && !esProcesoOrden) {
                    location.reload();
                }
            }, INTERVALO_RECARGA);
        })();
    </script>
</body>
</html>