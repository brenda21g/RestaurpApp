<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Dashboard Principal (Resumen CRM)
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Estadísticas del día / CRM
$hoy = date('Y-m-d');

// 1. Total de clientes registrados
$stmt_clientes = $db->query("SELECT COUNT(*) FROM usuarios_cliente");
$total_clientes = $stmt_clientes->fetchColumn();

// Clientes activos
$stmt_activos = $db->query("SELECT COUNT(*) FROM usuarios_cliente WHERE estado = 'Activo'");
$clientes_activos = $stmt_activos->fetchColumn();

// Interacciones de este mes
$inicio_mes = date('Y-m-01');
$fin_mes = date('Y-m-t');
$stmt_int = $db->prepare("SELECT COUNT(*) FROM interacciones WHERE fecha BETWEEN ? AND ?");
$stmt_int->execute([$inicio_mes, $fin_mes]);
$interacciones_mes = $stmt_int->fetchColumn();

// Clientes sin interacción en los últimos 30 días
$stmt_sin_int = $db->query("
    SELECT COUNT(*) FROM usuarios_cliente uc 
    WHERE uc.id NOT IN (
        SELECT DISTINCT cliente_id FROM interacciones WHERE cliente_id IS NOT NULL AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    )
");
$clientes_sin_interaccion = $stmt_sin_int->fetchColumn();

// Clientes en riesgo (última interacción más antigua o nula)
$stmt_riesgo = $db->query("
    SELECT uc.nombre, MAX(i.fecha) as ultima_fecha 
    FROM usuarios_cliente uc 
    LEFT JOIN interacciones i ON uc.id = i.cliente_id 
    GROUP BY uc.id, uc.nombre 
    ORDER BY ultima_fecha ASC LIMIT 3
");
$clientes_riesgo = $stmt_riesgo->fetchAll(PDO::FETCH_ASSOC);

// Desglose general de clientes por estado
$stmt_clientes_estado = $db->query("SELECT estado, COUNT(*) as cantidad FROM usuarios_cliente GROUP BY estado");
$clientes_por_estado = $stmt_clientes_estado->fetchAll(PDO::FETCH_ASSOC);

$conteo_estados = ['Activos' => 0, 'Inactivos' => 0];
foreach ($clientes_por_estado as $ce) {
    $estado_db = $ce['estado'] ?: 'Activos';
    if ($estado_db == 'Activo' || $estado_db == 'Activos') {
        $conteo_estados['Activos'] += $ce['cantidad'];
    } else {
        $conteo_estados['Inactivos'] += $ce['cantidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Restaurant App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ==========================================================================
            VARIABLES Y RESET GENERAL (TEMA VERDE Y BLANCO - SEGÚN IMAGEN)
            ========================================================================== */
        :root {
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --sidebar-bg: #011139;
            --sidebar-hover: #002056;
            --sidebar-text: #94a3b8;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --color-primary: #0d3b2c;
            --color-success: #10b981;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --sidebar-w: 250px;
            --radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
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

        /* ==========================================================================
            SIDEBAR DE NAVEGACIÓN
            ========================================================================== */
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
        }

        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-logo .name {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }

        .sidebar-logo .role {
            font-size: 11px;
            color: var(--sidebar-text);
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
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            color: #f87171;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .logout-btn:hover {
            background-color: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
        }

        /* ==========================================================================
            CONTENIDO PRINCIPAL
            ========================================================================== */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            padding: 32px 40px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
        }

        .date-badge {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 8px 16px;
            font-size: 13px;
            color: var(--text-muted);
            box-shadow: var(--shadow-sm);
        }

        /* ==========================================================================
            TARJETAS DE MÉTRICAS (STATS GRID)
            ========================================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.1;
        }

        .stat-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* ==========================================================================
            FILAS DE GRÁFICAS Y COMPONENTES VISUALES
            ========================================================================== */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }

        .chart-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Simulación de gráfica de dona / circular para Clientes activos vs inactivos */
        .donut-container {
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 20px 0;
        }

        .donut-hole {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(#10b981 0% 75%, #f59e0b 75% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .donut-hole::after {
            content: "";
            position: absolute;
            width: 60px;
            height: 60px;
            background: var(--bg-surface);
            border-radius: 50%;
        }

        .donut-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-main);
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        /* Clientes en Riesgo */
        .risk-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .risk-item:last-child { border-bottom: none; }

        .risk-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-main);
        }

        .risk-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .risk-link {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
        }

        /* Indicador en tiempo real */
        .live-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--color-success);
            margin-right: 6px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .3; }
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="name">Restaurant App</div>
        <div class="role">Gerente</div>
    </div>
    <nav class="nav">
        <a class="nav-item active" href="dashboard.php"><span>📊</span> Dashboard</a>
        <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
        <a class="nav-item" href="interacciones.php"><span>💬</span> Interacciones</a>
        <a class="nav-item" href="evaluaciones.php"><span>📋</span> Evaluaciones</a>
        <a class="nav-item" href="usuarios.php"><span>🛡️</span> Usuarios</a>
        <a class="nav-item" href="miactividad.php"><span>⏱️</span> Mi actividad</a>
        <a class="nav-item" href="configuracion.php"><span>⚙️</span> Configuración</a>
    </nav>
    <div class="sidebar-bottom">
        <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
    <div class="topbar">
        <div>
            <div class="page-title">Resumen CRM</div>
            <div style="color:var(--text-muted);font-size:13px;margin-top:2px;">
                <span class="live-dot"></span>Vista general de indicadores clave
            </div>
        </div>
        <div class="date-badge">📅 <?= date('d \d\e F \d\e Y') ?></div>
    </div>

    <!-- 1. TARJETAS DE MÉTRICAS (RESUMEN CRM) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total de clientes</div>
            <div class="stat-value"><?= $total_clientes ?></div>
            <div class="stat-sub">Todos los clientes</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Clientes activos</div>
            <div class="stat-value"><?= $clientes_activos ?></div>
            <div class="stat-sub"><?= ($total_clientes > 0) ? round(($clientes_activos / $total_clientes) * 100) : 0 ?>% del total</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Interacciones (este mes)</div>
            <div class="stat-value"><?= $interacciones_mes ?></div>
            <div class="stat-sub">+12% vs mes anterior</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Clientes sin interacción</div>
            <div class="stat-value" style="color: var(--color-warning);"><?= $clientes_sin_interaccion ?></div>
            <div class="stat-sub">Últimos 30 días</div>
        </div>
    </div>

    <!-- 2. FILA DE GRÁFICAS (Clientes Activos vs Inactivos & Clientes en Riesgo) -->
    <div class="charts-row">
        <!-- Clientes activos vs inactivos -->
        <div class="chart-card">
            <div class="chart-title">Clientes activos vs inactivos</div>
            <div class="donut-container">
                <div class="donut-hole"></div>
                <div class="donut-legend">
                    <div class="legend-item">
                        <span class="legend-dot" style="background: var(--color-success);"></span>
                        <span>Activos: <strong><?= $conteo_estados['Activos'] ?></strong> (<?= ($total_clientes > 0) ? round(($conteo_estados['Activos'] / $total_clientes) * 100) : 0 ?>%)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: var(--color-warning);"></span>
                        <span>Inactivos: <strong><?= $conteo_estados['Inactivos'] ?></strong> (<?= ($total_clientes > 0) ? round(($conteo_estados['Inactivos'] / $total_clientes) * 100) : 0 ?>%)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clientes en Riesgo -->
        <div class="chart-card">
            <div class="chart-title">
                <span>Clientes en riesgo</span>
                <a href="clientes.php" style="color:var(--color-primary); font-size:12px; text-decoration:none;">Ver todos →</a>
            </div>
            <div>
                <?php if (empty($clientes_riesgo)): ?>
                    <div style="color: var(--text-muted); text-align: center; padding: 30px;">No hay clientes en riesgo actualmente.</div>
                <?php else: ?>
                    <?php foreach ($clientes_riesgo as $cr): ?>
                        <div class="risk-item">
                            <div>
                                <div class="risk-name"><?= htmlspecialchars($cr['nombre']) ?></div>
                                <div class="risk-desc">Sin interacción desde hace <?= $cr['ultima_fecha'] ? max(0, round((time() - strtotime($cr['ultima_fecha'])) / 86400)) : '35+' ?> días</div>
                            </div>
                            <a href="clientes.php" class="risk-link">›</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

</body>
</html>