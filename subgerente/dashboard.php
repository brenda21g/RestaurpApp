```php
<?php

require_once __DIR__ . '/../config/auth_check.php';

$db = getDB();

/* Estadísticas del día */
$hoy = date('Y-m-d');

/* 1. Métricas del día */
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

$ingresos = $stats['ingresos'] ?? 0;
$total_pedidos = $stats['total_pedidos'] ?? 0;

/* 2. Pedidos por hora */
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

/* 3. Productos más vendidos */
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

/* 4. Últimos 10 pedidos */
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

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard – RestaurantApp Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <style>

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #f7f9fc;
            --card: #ffffff;
            --border: #e2e8f0;
            --accent: #ffffff;
            --text: #071b3a;
            --muted: #60708a;
            --sidebar: #03143d;
            --sidebar-hover: #092e70;
            --green: #07543f;
            --green-light: #e4f7f0;
            --blue: #1684c4;
            --red: #ff5b5b;
            --sidebar-w: 240px;

            --primary: #0788c9;
            --primary-dark: #0674ad;
            --green-dashboard: #13b77a;
            --green-bg: #e7f8f2;
            --amber: #f5a000;
            --amber-bg: #fff5dc;
            --red-bg: #fdebed;
            --light-blue: #e7f3fa;

            --text-main: #101828;
            --text-secondary: #536987;
            --text-muted: #7890ad;

            --card-border: #e1e7ef;
            --card-bg: #ffffff;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            display: flex;
            min-height: 100vh;
            font-size: 14px;
        }

        /* ================================
           SIDEBAR
        ================================= */

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
            color: var(--accent);
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

        /* ================================
           MAIN
        ================================= */

        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            padding: 28px 32px;
            max-width: calc(100% - var(--sidebar-w));
        }

        /* ================================
           TOP BAR
        ================================= */

        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .top-subtitle {
            color: var(--muted);
            font-size: 13px;
        }

        .date-badge {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 11px;
            padding: 12px 19px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            box-shadow: 0 2px 5px rgba(15,23,42,.04);
        }

        /* ================================
           LIVE DOT
        ================================= */

        .live-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green-dashboard);
            margin-right: 7px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }

            50% {
                opacity: .35;
            }
        }

        /* ================================
           STATS
        ================================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 13px;
            padding: 22px;
            position: relative;
            min-height: 128px;
            box-shadow: 0 2px 5px rgba(15,23,42,.025);
        }

        .stat-card:hover {
            box-shadow: 0 5px 14px rgba(15,23,42,.05);
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 13px;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        .stat-value.money::before {
            content: '$';
            font-size: 19px;
            color: var(--text-secondary);
            margin-right: 2px;
        }

        .stat-icon {
            position: absolute;
            top: 19px;
            right: 19px;
            font-size: 23px;
            opacity: .35;
        }

        /* ================================
           CHARTS
        ================================= */

        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .chart-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 13px;
            padding: 23px;
            box-shadow: 0 2px 5px rgba(15,23,42,.025);
        }

        .chart-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ================================
           BAR CHART
        ================================= */

        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 9px;
            height: 145px;
            padding-top: 8px;
        }

        .bar-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 7px;
            height: 100%;
            justify-content: flex-end;
        }

        .bar {
            width: 100%;
            max-width: 38px;
            background: var(--primary);
            border-radius: 5px 5px 2px 2px;
            min-height: 5px;
            transition: height .3s ease;
        }

        .bar:hover {
            background: var(--primary-dark);
        }

        .bar-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* ================================
           PRODUCT LIST
        ================================= */

        .prod-list {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .prod-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prod-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            min-width: 120px;
        }

        .prod-bar-wrap {
            flex: 1;
            height: 8px;
            background: #edf2f7;
            border-radius: 10px;
            overflow: hidden;
        }

        .prod-bar {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
        }

        .prod-count {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            width: 52px;
            text-align: right;
            flex-shrink: 0;
        }

        /* ================================
           TABLE
        ================================= */

        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(15,23,42,.025);
            margin-bottom: 25px;
        }

        .table-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .table-header a {
            color: var(--primary) !important;
        }

        .table-header a:hover {
            color: var(--primary-dark) !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px 24px;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: .7px;
            text-transform: uppercase;
            color: var(--text-secondary);
            background: #f8fafc;
            border-top: 1px solid var(--card-border);
            border-bottom: 1px solid var(--card-border);
        }

        td {
            padding: 14px 24px;
            border-bottom: 1px solid #edf1f5;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fafcff;
        }

        /* ================================
           BADGES
        ================================= */

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.pendiente {
            background: #fff4d8;
            color: #d88900;
        }

        .badge.preparando {
            background: #e3f4fb;
            color: #0788c9;
        }

        .badge.listo {
            background: #dff7ed;
            color: #079969;
        }

        .badge.entregado {
            background: #e5f8f1;
            color: #0bad77;
        }

        .badge.cancelado {
            background: #fde9eb;
            color: #dc4c55;
        }

        /* ================================
           CORTES DE CAJA
        ================================= */

        .cortes-section {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 13px;
            padding: 22px 24px;
            box-shadow: 0 2px 5px rgba(15,23,42,.025);
        }

        .cortes-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 17px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-main);
        }

        .cortes-btns {
            display: flex;
            gap: 11px;
            flex-wrap: wrap;
        }

        .corte-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            background: #ffffff;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            text-decoration: none;
        }

        .corte-btn:hover {
            background: #f7fafc;
            border-color: #c8d3df;
        }

        .corte-btn.primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .corte-btn.primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* ================================
           RESPONSIVE
        ================================= */

        @media(max-width:1200px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:800px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                padding: 24px;
                max-width: calc(100% - 210px);
            }

            .topbar {
                flex-direction: column;
                gap: 15px;
            }

            .date-badge {
                align-self: flex-start;
            }

            th,
            td {
                padding-left: 14px;
                padding-right: 14px;
            }
        }

        @media(max-width:600px) {

            .sidebar {
                width: 70px;
            }

            .sidebar-logo .name,
            .sidebar-logo .role,
            .nav-item:not(.active)::after,
            .logout-btn {
                font-size: 0;
            }

            .sidebar-logo {
                padding: 20px 10px;
                text-align: center;
            }

            .nav {
                padding: 16px 8px;
            }

            .nav-item {
                justify-content: center;
                padding: 12px 8px;
            }

            .nav-item .icon {
                font-size: 18px;
            }

            .logout-btn {
                justify-content: center;
                padding: 12px 8px;
            }

            .main {
                margin-left: 70px;
                padding: 20px 16px;
                max-width: calc(100% - 70px);
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .charts-row {
                grid-template-columns: 1fr;
            }

            .table-card {
                overflow-x: auto;
            }

            table {
                min-width: 650px;
            }
        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-logo">

            <div class="name">
                🍽️ RestaurApp
            </div>

            <div class="role">
                Subgerente
            </div>

        </div>

        <nav class="nav">

            <a class="nav-item active" href="dashboard.php">
                <span class="icon">📊</span>
                Dashboard
            </a>

            <a class="nav-item" href="pedidos.php">
                <span class="icon">📋</span>
                Pedidos
            </a>

            <a class="nav-item" href="mesas_qr.php">
                <span class="icon">🪑</span>
                Mesas & QR
            </a>

            <a class="nav-item" href="menu.php">
                <span class="icon">🍽️</span>
                Menú
            </a>

            <a class="nav-item" href="corte.php">
                <span class="icon">💵</span>
                Corte de Caja
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

        <!-- TOP BAR -->

        <div class="topbar">

            <div>

                <div class="page-title">
                    Dashboard
                </div>

                <div class="top-subtitle">

                    <span class="live-dot"></span>

                    Actualizando en tiempo real

                </div>

            </div>

            <div class="date-badge">

                📅 <?= date('d \d\e F \d\e Y') ?>

            </div>

        </div>


        <!-- STATS -->

        <div class="stats-grid">

            <div class="stat-card">

                <span class="stat-icon">
                    💰
                </span>

                <div class="stat-label">
                    Ingresos del día
                </div>

                <div class="stat-value money">

                    <?= number_format($stats['ingresos'] ?? 0, 2) ?>

                </div>

            </div>


            <div class="stat-card">

                <span class="stat-icon">
                    📋
                </span>

                <div class="stat-label">
                    Total pedidos
                </div>

                <div class="stat-value">

                    <?= $stats['total_pedidos'] ?? 0 ?>

                </div>

            </div>


            <div class="stat-card">

                <span class="stat-icon">
                    ✅
                </span>

                <div class="stat-label">
                    Completados
                </div>

                <div
                    class="stat-value"
                    style="color:var(--green-dashboard);"
                >

                    <?= $stats['completados'] ?? 0 ?>

                </div>

            </div>


            <div class="stat-card">

                <span class="stat-icon">
                    ⏳
                </span>

                <div class="stat-label">
                    En proceso
                </div>

                <div
                    class="stat-value"
                    style="color:var(--amber);"
                >

                    <?= $stats['activos'] ?? 0 ?>

                </div>

            </div>

        </div>


        <!-- CHARTS -->

        <div class="charts-row">


            <!-- PEDIDOS POR HORA -->

            <div class="chart-card">

                <div class="chart-title">
                    📊 Pedidos por hora (hoy)
                </div>

                <?php
                $max_pedidos = max(
                    array_column($pedidos_hora, 'cantidad') ?: [1]
                );
                ?>

                <div class="bar-chart">

                    <?php if (empty($pedidos_hora)): ?>

                        <div
                            style="
                                color:var(--text-muted);
                                font-size:13px;
                                width:100%;
                                text-align:center;
                                padding:40px 0;
                            "
                        >
                            Sin pedidos hoy
                        </div>

                    <?php else: ?>

                        <?php foreach ($pedidos_hora as $ph): ?>

                            <div class="bar-col">

                                <div
                                    style="
                                        font-size:10px;
                                        color:var(--text-muted);
                                    "
                                >

                                    <?= $ph['cantidad'] ?>

                                </div>

                                <div
                                    class="bar"
                                    style="
                                        height:<?= round(
                                            ($ph['cantidad'] / $max_pedidos) * 100
                                        ) ?>%;
                                    "
                                ></div>

                                <div class="bar-label">

                                    <?= str_pad(
                                        $ph['hora'],
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>h

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>


            <!-- TOP PRODUCTOS -->

            <div class="chart-card">

                <div class="chart-title">
                    🔥 Productos más vendidos (hoy)
                </div>

                <?php if (empty($top_productos)): ?>

                    <div
                        style="
                            color:var(--text-muted);
                            font-size:13px;
                            padding:20px 0;
                        "
                    >
                        Sin ventas completadas hoy
                    </div>

                <?php else: ?>

                    <?php
                    $max_v = max(
                        array_column($top_productos, 'vendidos')
                    );
                    ?>

                    <div class="prod-list">

                        <?php foreach ($top_productos as $i => $p): ?>

                            <div class="prod-item">

                                <span
                                    style="
                                        width:20px;
                                        font-size:11px;
                                        color:var(--text-muted);
                                        text-align:right;
                                        flex-shrink:0;
                                    "
                                >

                                    <?= $i + 1 ?>

                                </span>

                                <span class="prod-name">

                                    <?= htmlspecialchars($p['nombre']) ?>

                                </span>

                                <div class="prod-bar-wrap">

                                    <div
                                        class="prod-bar"
                                        style="
                                            width:<?= round(
                                                ($p['vendidos'] / $max_v) * 100
                                            ) ?>%;
                                        "
                                    ></div>

                                </div>

                                <span class="prod-count">

                                    <?= $p['vendidos'] ?> uds

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- ÚLTIMOS PEDIDOS -->

        <div class="table-card">

            <div class="table-header">

                <h3>
                    📋 Registro de pedidos recientes
                </h3>

                <a
                    href="pedidos.php"
                    style="
                        color:var(--primary);
                        font-size:13px;
                        font-weight:600;
                        text-decoration:none;
                    "
                >
                    Ver todos →
                </a>

            </div>


            <table>

                <thead>

                    <tr>

                        <th>
                            Orden
                        </th>

                        <th>
                            Mesa
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Hora
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (empty($ultimos_pedidos)): ?>

                        <tr>

                            <td
                                colspan="5"
                                style="
                                    text-align:center;
                                    color:var(--text-muted);
                                    padding:30px;
                                "
                            >
                                Sin pedidos aún
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($ultimos_pedidos as $p): ?>

                            <tr>

                                <td style="font-weight:700;">

                                    <?= htmlspecialchars(
                                        $p['numero_orden']
                                    ) ?>

                                </td>

                                <td>

                                    Mesa <?= $p['mesa_num'] ?>

                                </td>

                                <td>

                                    $<?= number_format(
                                        $p['total'],
                                        2
                                    ) ?>

                                </td>

                                <td>

                                    <span
                                        class="badge <?= htmlspecialchars(
                                            $p['estado']
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            $p['estado']
                                        ) ?>

                                    </span>

                                </td>

                                <td style="color:var(--text-muted);">

                                    <?= date(
                                        'H:i',
                                        strtotime($p['creado_en'])
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- CORTES DE CAJA -->

        <div class="cortes-section">

            <div class="cortes-title">

                🖨️ Cortes de caja e impresión

            </div>


            <div class="cortes-btns">

                <a
                    href="corte.php?tipo=dia"
                    class="corte-btn primary"
                >
                    📄 Corte del día
                </a>

                <a
                    href="corte.php?tipo=semana"
                    class="corte-btn"
                >
                    📅 Corte semanal
                </a>

                <a
                    href="corte.php?tipo=mes"
                    class="corte-btn"
                >
                    📆 Corte mensual
                </a>

            </div>

        </div>

    </main>


    <!-- ACTUALIZACIÓN Y CIERRE POR INACTIVIDAD -->

    <script>

        (function() {

            const TIEMPO_INACTIVIDAD = 3 * 60 * 1000;

            const INTERVALO_RECARGA = 30000;

            let temporizadorInactividad;

            let temporizadorRecarga;


            function cerrarSesion() {

                window.location.href =
                    'logout.php?reason=inactividad';

            }


            function reiniciarInactividad() {

                clearTimeout(
                    temporizadorInactividad
                );

                temporizadorInactividad =
                    setTimeout(
                        cerrarSesion,
                        TIEMPO_INACTIVIDAD
                    );

            }


            const eventos = [
                'mousemove',
                'mousedown',
                'keydown',
                'scroll',
                'touchstart',
                'click'
            ];


            eventos.forEach(evento => {

                window.addEventListener(
                    evento,
                    reiniciarInactividad,
                    true
                );

            });


            reiniciarInactividad();


            temporizadorRecarga = setInterval(() => {

                if (!document.hidden) {

                    location.reload();

                }

            }, INTERVALO_RECARGA);

        })();

    </script>

</body>

</html>
```
