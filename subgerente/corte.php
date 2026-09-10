<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$tipo = $_GET['tipo'] ?? 'dia';

// Rango de fechas según tipo de corte
switch ($tipo) {
    case 'semana':
        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d', strtotime('sunday this week'));
        $label = 'Semanal (' . date('d/m', strtotime($desde)) . ' – ' . date('d/m/Y', strtotime($hasta)) . ')';
        break;

    case 'mes':
        $desde = date('Y-m-01');
        $hasta = date('Y-m-t');
        $label = 'Mensual – ' . date('F Y');
        break;

    default:
        $tipo = 'dia';
        $desde = date('Y-m-d');
        $hasta = date('Y-m-d');
        $label = 'Diario – ' . date('d/m/Y');
}

// 1. Resumen general
$resumen = $db->prepare("
    SELECT
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN estado='entregado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado='cancelado' THEN 1 ELSE 0 END) as cancelados,
        SUM(CASE WHEN estado='entregado' THEN total ELSE 0 END) as ingresos_totales,
        AVG(CASE WHEN estado='entregado' THEN total ELSE NULL END) as ticket_promedio
    FROM pedidos
    WHERE DATE(creado_en) BETWEEN ? AND ?
");

$resumen->execute([$desde, $hasta]);
$res = $resumen->fetch(PDO::FETCH_ASSOC);

// 2. Ingresos por día
$ingresos_dia = [];

if ($tipo !== 'dia') {

    $q_dias = $db->prepare("
        SELECT
            DATE(creado_en) as fecha,
            COUNT(*) as pedidos,
            SUM(CASE WHEN estado='entregado' THEN total ELSE 0 END) as ingresos
        FROM pedidos
        WHERE DATE(creado_en) BETWEEN ? AND ?
        GROUP BY DATE(creado_en)
        ORDER BY fecha ASC
    ");

    $q_dias->execute([$desde, $hasta]);
    $ingresos_dia = $q_dias->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Detalle de productos vendidos
$q_prod = $db->prepare("
    SELECT
        pr.nombre,
        COALESCE(c.nombre, 'General') as categoria,
        pi.precio_unitario,
        SUM(pi.cantidad) as cantidad_total,
        SUM(pi.subtotal) as total_generado
    FROM pedido_items pi
    JOIN pedidos p ON pi.pedido_id = p.id
    JOIN productos pr ON pi.producto_id = pr.id
    LEFT JOIN categorias c ON pr.categoria_id = c.id
    WHERE DATE(p.creado_en) BETWEEN ? AND ?
    AND p.estado = 'entregado'
    GROUP BY pr.id, pr.nombre, c.nombre, pi.precio_unitario
    ORDER BY cantidad_total DESC
");

$q_prod->execute([$desde, $hasta]);
$prod_vendidos = $q_prod->fetchAll(PDO::FETCH_ASSOC);

// 4. Registro completo de pedidos
$q_pedidos = $db->prepare("
    SELECT
        p.id,
        p.numero_orden,
        m.numero as mesa,
        p.total,
        p.estado,
        p.creado_en
    FROM pedidos p
    JOIN mesas m ON p.mesa_id = m.id
    WHERE DATE(p.creado_en) BETWEEN ? AND ?
    ORDER BY p.creado_en DESC
");

$q_pedidos->execute([$desde, $hasta]);
$lista_pedidos = $q_pedidos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Corte <?= ucfirst($tipo) ?> – RestaurApp</title>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --bg: #f7f9fc;
    --card: #ffffff;
    --border: #e2e8f0;

    --accent: #1684c4;

    --text: #071b3a;
    --muted: #60708a;

    --sidebar: #03143d;
    --sidebar-hover: #092e70;

    --green: #07543f;
    --green-light: #e4f7f0;

    --blue-light: #eaf5fb;

    --red: #e07070;
    --red-light: #fff0f0;

    --yellow: #d99a24;
    --yellow-light: #fff6df;

    --sidebar-w: 240px;
}

/* =========================
   BODY
========================= */

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    min-height: 100vh;
    margin-left: var(--sidebar-w);
}

/* =========================
   SIDEBAR
========================= */

.sidebar {
    width: var(--sidebar-w);
    background: var(--sidebar);
    border-right: 1px solid #0b2455;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 100;
}

.sidebar-logo {
    padding: 24px 20px;
    border-bottom: 1px solid #0b2455;
}

.sidebar-logo .name {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: #ffffff;
}

.sidebar-logo .role {
    font-size: 11px;
    color: #91a6c7;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: 2px;
}

.nav {
    padding: 16px 12px;
    flex: 1;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    color: #9db0ce;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all .15s;
    margin-bottom: 4px;
}

.nav-item:hover {
    background: rgba(255,255,255,.06);
    color: #ffffff;
}

.nav-item.active {
    background: var(--sidebar-hover);
    color: #ffffff;
}

.nav-item .icon {
    font-size: 15px;
}

.sidebar-bottom {
    padding: 16px 12px;
    border-top: 1px solid #0b2455;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #ff6868;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background .15s;
}

.logout-btn:hover {
    background: rgba(255,104,104,.1);
}

/* =========================
   CONTENIDO
========================= */

.main {
    padding: 28px 32px;
    max-width: 1250px;
    margin: 0 auto;
}

/* =========================
   NAVEGACIÓN SUPERIOR
========================= */

.topnav {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.back-btn {
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color .15s;
}

.back-btn:hover {
    color: var(--text);
}

.tipo-btns {
    display: flex;
    gap: 8px;
    margin-left: auto;
}

.tipo-btn {
    padding: 7px 15px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #ffffff;
    color: var(--muted);
    font-family: 'DM Sans', sans-serif;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
}

.tipo-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: var(--blue-light);
}

.tipo-btn.active {
    border-color: var(--accent);
    background: var(--blue-light);
    color: var(--accent);
    font-weight: 600;
}

/* =========================
   BOTÓN IMPRIMIR
========================= */

.print-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    background: var(--green);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 28px;
    transition: background .15s, transform .15s;
}

.print-btn:hover {
    background: #063f30;
    transform: translateY(-1px);
}

/* =========================
   ENCABEZADO
========================= */

.report-header {
    text-align: center;
    padding: 28px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(7,27,58,.04);
}

.report-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    color: var(--text);
}

.report-header .sub {
    color: var(--muted);
    font-size: 13px;
    margin-top: 6px;
}

/* =========================
   SECCIONES
========================= */

.section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(7,27,58,.04);
}

.section h2 {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

/* =========================
   ESTADÍSTICAS
========================= */

.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.stat-box {
    text-align: center;
    padding: 16px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid var(--border);
    transition: transform .15s, box-shadow .15s;
}

.stat-box:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(7,27,58,.06);
}

.stat-box .val {
    font-size: 24px;
    font-weight: 600;
    color: var(--green);
}

.stat-box .lbl {
    font-size: 11px;
    color: var(--muted);
    margin-top: 4px;
}

/* =========================
   TABLAS
========================= */

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 10px 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    background: #f8fafc;
}

td {
    padding: 11px 12px;
    border-top: 1px solid var(--border);
    font-size: 13px;
    color: var(--text);
}

tbody tr {
    transition: background .15s;
}

tbody tr:hover {
    background: #f8fafc;
}

/* =========================
   BADGES
========================= */

.badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.badge.entregado {
    background: var(--green-light);
    color: #0aa878;
}

.badge.cancelado {
    background: var(--red-light);
    color: var(--red);
}

.badge.pendiente {
    background: var(--yellow-light);
    color: var(--yellow);
}

.badge.preparando {
    background: var(--blue-light);
    color: var(--accent);
}

.badge.listo {
    background: var(--green-light);
    color: #0aa878;
}

/* =========================
   TOTALES
========================= */

.total-row td {
    font-weight: 600;
    color: var(--green);
    border-top: 2px solid var(--border);
}

/* =========================
   MENSAJES SIN DATOS
========================= */

.section p {
    color: var(--muted);
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 900px) {

    body {
        margin-left: 210px;
    }

    .sidebar {
        width: 210px;
    }

    .main {
        padding: 24px;
    }

    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }

    table {
        min-width: 650px;
    }

    .section {
        overflow-x: auto;
    }
}

@media (max-width: 600px) {

    body {
        margin-left: 70px;
    }

    .sidebar {
        width: 70px;
    }

    .sidebar-logo {
        padding: 20px 10px;
        text-align: center;
    }

    .sidebar-logo .name,
    .sidebar-logo .role,
    .logout-btn {
        font-size: 0;
    }

    .nav {
        padding: 16px 8px;
    }

    .nav-item {
        justify-content: center;
        padding: 12px 8px;
    }

    .main {
        padding: 20px 16px;
    }

    .topnav {
        flex-direction: column;
        align-items: stretch;
    }

    .tipo-btns {
        margin-left: 0;
        width: 100%;
    }

    .tipo-btn {
        flex: 1;
        text-align: center;
    }

    .print-btn {
        width: 100%;
        justify-content: center;
    }

    .stats-row {
        grid-template-columns: 1fr;
    }

    .report-header {
        padding: 22px 15px;
    }

    .report-header h1 {
        font-size: 24px;
    }

    .section {
        padding: 16px;
    }

}

/* =========================
   IMPRESIÓN
========================= */

@media print {

    body {
        background: white;
        color: #111;
        margin: 0;
        padding: 20px;
    }

    .sidebar,
    .topnav,
    .print-btn {
        display: none !important;
    }

    .main {
        margin: 0;
        padding: 0;
        max-width: none;
    }

    .section,
    .report-header {
        background: white;
        border: 1px solid #ddd;
        box-shadow: none;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .stat-box {
        background: #f8f8f8 !important;
        box-shadow: none;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .stat-box .val,
    .total-row td {
        color: #07543f;
    }

    .badge.entregado,
    .badge.cancelado,
    .badge.pendiente,
    .badge.preparando,
    .badge.listo {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    :root {
        --text: #111111;
        --muted: #666666;
        --border: #dddddd;
    }

}

</style>

</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="sidebar-logo">

        <div class="name">🍽️ RestaurApp</div>

        <div class="role">Subgerente</div>

    </div>

    <nav class="nav">

        <a class="nav-item" href="dashboard.php">
            <span class="icon">📊</span> Dashboard
        </a>

        <a class="nav-item" href="pedidos.php">
            <span class="icon">📋</span> Pedidos
        </a>

        <a class="nav-item" href="mesas_qr.php">
            <span class="icon">🪑</span> Mesas & QR
        </a>

        <a class="nav-item" href="menu.php">
            <span class="icon">🍽️</span> Menú
        </a>

        <a class="nav-item active" href="corte.php">
            <span>💵</span> Corte de Caja
        </a>

    </nav>

    <div class="sidebar-bottom">

        <a class="logout-btn" href="logout.php">
            🚪 Cerrar sesión
        </a>

    </div>

</aside>

<main class="main">

    <!-- NAVEGACIÓN -->

    <div class="topnav">

        <a class="back-btn" href="dashboard.php">
            ← Regresar
        </a>

        <div class="tipo-btns">

            <a
                href="corte.php?tipo=dia"
                class="tipo-btn <?= $tipo === 'dia' ? 'active' : '' ?>"
            >
                Día
            </a>

            <a
                href="corte.php?tipo=semana"
                class="tipo-btn <?= $tipo === 'semana' ? 'active' : '' ?>"
            >
                Semana
            </a>

            <a
                href="corte.php?tipo=mes"
                class="tipo-btn <?= $tipo === 'mes' ? 'active' : '' ?>"
            >
                Mes
            </a>

        </div>

    </div>

    <!-- BOTÓN IMPRIMIR -->

    <button class="print-btn" onclick="window.print()">
        🖨️ Imprimir / Guardar PDF
    </button>

    <!-- ENCABEZADO -->

    <div class="report-header">

        <h1>🍽️ RestaurApp</h1>

        <div class="sub">
            Corte de caja — <?= $label ?>
        </div>

        <div class="sub" style="margin-top:4px;">
            Generado el <?= date('d/m/Y H:i') ?>
        </div>

    </div>

    <!-- RESUMEN GENERAL -->

    <div class="section">

        <h2>
            📊 Resumen general
        </h2>

        <div class="stats-row">

            <div class="stat-box">

                <div class="val">
                    $<?= number_format($res['ingresos_totales'] ?? 0, 2) ?>
                </div>

                <div class="lbl">
                    Ingresos totales
                </div>

            </div>

            <div class="stat-box">

                <div class="val">
                    <?= $res['total_pedidos'] ?? 0 ?>
                </div>

                <div class="lbl">
                    Pedidos totales
                </div>

            </div>

            <div class="stat-box">

                <div class="val">
                    <?= $res['completados'] ?? 0 ?>
                </div>

                <div class="lbl">
                    Completados
                </div>

            </div>

            <div class="stat-box">

                <div class="val">
                    $<?= number_format($res['ticket_promedio'] ?? 0, 2) ?>
                </div>

                <div class="lbl">
                    Ticket promedio
                </div>

            </div>

            <div class="stat-box">

                <div class="val">
                    <?= $res['cancelados'] ?? 0 ?>
                </div>

                <div class="lbl">
                    Cancelados
                </div>

            </div>

            <div class="stat-box">

                <div class="val">

                    <?= $tipo === 'dia'
                        ? date('d/m/Y')
                        : ($desde != $hasta
                            ? date('d/m', strtotime($desde)) . '–' . date('d/m', strtotime($hasta))
                            : date('d/m/Y', strtotime($desde))
                        )
                    ?>

                </div>

                <div class="lbl">
                    Período
                </div>

            </div>

        </div>

    </div>

    <?php if ($tipo !== 'dia' && !empty($ingresos_dia)): ?>

    <!-- INGRESOS POR DÍA -->

    <div class="section">

        <h2>
            📅 Ingresos por día
        </h2>

        <table>

            <thead>

                <tr>
                    <th>Fecha</th>
                    <th>Pedidos</th>
                    <th>Ingresos</th>
                </tr>

            </thead>

            <tbody>

                <?php

                $gran_total = 0;

                foreach ($ingresos_dia as $d):

                    $gran_total += $d['ingresos'];

                ?>

                <tr>

                    <td>
                        <?= date('d/m/Y (l)', strtotime($d['fecha'])) ?>
                    </td>

                    <td>
                        <?= $d['pedidos'] ?>
                    </td>

                    <td>
                        $<?= number_format($d['ingresos'], 2) ?>
                    </td>

                </tr>

                <?php endforeach; ?>

                <tr class="total-row">

                    <td>
                        TOTAL
                    </td>

                    <td></td>

                    <td>
                        $<?= number_format($gran_total, 2) ?>
                    </td>

                </tr>

            </tbody>

        </table>

    </div>

    <?php endif; ?>

    <!-- PRODUCTOS VENDIDOS -->

    <div class="section">

        <h2>
            🏆 Detalle de productos vendidos
        </h2>

        <?php if (empty($prod_vendidos)): ?>

            <p style="font-size:13px;">
                Sin ventas en este período.
            </p>

        <?php else: ?>

        <table>

            <thead>

                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Precio Unit.</th>
                    <th>Cant.</th>
                    <th>Total</th>
                </tr>

            </thead>

            <tbody>

                <?php

                $total_gen = 0;

                foreach ($prod_vendidos as $pv):

                    $total_gen += $pv['total_generado'];

                ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($pv['nombre']) ?>
                    </td>

                    <td style="color:var(--muted);">
                        <?= htmlspecialchars($pv['categoria']) ?>
                    </td>

                    <td>
                        $<?= number_format($pv['precio_unitario'], 2) ?>
                    </td>

                    <td>
                        <?= $pv['cantidad_total'] ?>
                    </td>

                    <td>
                        $<?= number_format($pv['total_generado'], 2) ?>
                    </td>

                </tr>

                <?php endforeach; ?>

                <tr class="total-row">

                    <td colspan="4">
                        TOTAL INGRESOS
                    </td>

                    <td>
                        $<?= number_format($total_gen, 2) ?>
                    </td>

                </tr>

            </tbody>

        </table>

        <?php endif; ?>

    </div>

    <!-- REGISTRO DE PEDIDOS -->

    <div class="section">

        <h2>
            📋 Registro completo de pedidos
        </h2>

        <?php if (empty($lista_pedidos)): ?>

            <p style="font-size:13px;">
                Sin pedidos en este período.
            </p>

        <?php else: ?>

        <table>

            <thead>

                <tr>
                    <th>#Orden</th>
                    <th>Mesa</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Fecha/Hora</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($lista_pedidos as $lp): ?>

                <tr>

                    <td style="font-weight:500;">
                        <?= htmlspecialchars($lp['numero_orden']) ?>
                    </td>

                    <td>
                        Mesa <?= $lp['mesa'] ?>
                    </td>

                    <td>
                        $<?= number_format($lp['total'], 2) ?>
                    </td>

                    <td>

                        <span class="badge <?= $lp['estado'] ?>">
                            <?= ucfirst($lp['estado']) ?>
                        </span>

                    </td>

                    <td style="color:var(--muted);font-size:12px;">

                        <?= date(
                            'd/m H:i',
                            strtotime($lp['creado_en'])
                        ) ?>

                    </td>

                </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php endif; ?>

    </div>

</main>

</body>

</html>