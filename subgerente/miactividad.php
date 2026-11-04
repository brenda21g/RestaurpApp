<?php
/**
 * Archivo: subgerente/miactividad.php
 * Descripción: Historial de actividad y operaciones con Sidebar institucional para Subgerencia.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['subgerente', 'gerente']);
$db = getDB();

$admin_id = $_SESSION['admin_id'] ?? ($_SESSION['id'] ?? 0);

// Filtros de fecha (por defecto se puede mostrar un rango o todas)
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Construir consulta para ver las interacciones o acciones registradas por el usuario actual
$sql = "
    SELECT i.*, c.nombre AS cliente_nombre 
    FROM interacciones i
    LEFT JOIN usuarios_cliente c ON c.id = i.cliente_id
    WHERE 1=1
";
$params = [];

$sql .= " ORDER BY i.fecha DESC, i.hora DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales para métricas de actividad
$totalActividades = count($actividades);

// Control de alertas de stock crítico para la barra lateral
$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();
$rol_actual = $_SESSION['rol'] ?? ($_SESSION['tipo'] ?? 'Subgerente');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi actividad – Panel Subgerencia</title>
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
        --color-danger: #ef4444;
        --sidebar-w: 260px;
        --radius: 10px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        background-color: var(--bg-body);
        color: var(--text-main);
        font-family: 'Inter', sans-serif;
        display: flex;
        min-height: 100vh;
        font-size: 14px;
    }

    /* SIDEBAR INSTITUCIONAL AZUL */
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

    /* CONTENIDO PRINCIPAL Y TOPBAR */
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

    .subtitle {
        color: var(--text-muted);
        font-size: 13px;
        margin-top: 2px;
    }

    /* TARJETAS Y TABLAS DE DATOS */
    .card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        margin-bottom: 24px;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .card-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-main);
    }

    .date-filter-badge {
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 13px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13px;
    }

    th {
        background: #f1f5f9;
        color: var(--text-muted);
        font-weight: 600;
        padding: 12px 24px;
        border-bottom: 1px solid var(--border-color);
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
    }

    td {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main);
    }

    tr:last-child td { border-bottom: none; }

    tr:hover td { background-color: #f8fafc; }

    .badge-tipo {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
        text-transform: capitalize;
    }

    .empty-state {
        text-align: center;
        padding: 48px;
        color: var(--text-muted);
    }

    .table-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border-color);
        color: var(--text-muted);
        font-size: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    @media (max-width: 768px) {
        .main { margin-left: 0; padding: 20px; }
        .sidebar { display: none; }
    }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="name">🍽️ RestaurApp</div>
        <div class="role">Subgerencia</div>
    </div>
    <nav class="nav">
        <a class="nav-item" href="dashboard.php?seccion=inicio"><span>📊</span> Dashboard</a>
        <a class="nav-item" href="dashboard.php?seccion=ordenes"><span>🛒</span> Órdenes ERP</a>
        <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
        <a class="nav-item" href="productos.php"><span>📦</span> Productos</a>
        <a class="nav-item" href="pedidos.php"><span>📋</span> Pedidos</a>
        <a class="nav-item" href="mesas_qr.php"><span>🪑</span> Mesas QR</a>
        <a class="nav-item" href="menu.php"><span>🍲</span> Menú</a>
        <a class="nav-item" href="corte.php"><span>💰</span> Corte</a>
        <a class="nav-item active" href="miactividad.php"><span>📈</span> Mi Actividad</a>
        <a class="nav-item" href="configuracion.php"><span>⚙️</span>Configuracion</a>
    </nav>
    <div class="sidebar-bottom">
        <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
    <div class="topbar">
        <div>
            <div class="page-title">Mi actividad</div>
            <div class="subtitle">Historial de gestiones y acciones realizadas en el sistema</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Registro de operaciones</div>
            <div class="date-filter-badge">
                <span>📅</span> <?= htmlspecialchars(date('01/m/Y'), ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars(date('d/m/Y'), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($actividades)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <div style="font-size:32px;margin-bottom:8px;">⏱️</div>
                                    <div style="font-weight:600;margin-bottom:2px;">No hay actividades registradas</div>
                                    <div>Tus acciones e interacciones recientes aparecerán aquí.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($actividades as $act): ?>
                            <?php
                            $iconoTipo = match(strtolower($act['tipo'] ?? '')) {
                                'correo' => '📧',
                                'llamada' => '📞',
                                'reunion' => '🤝',
                                default => '💬'
                            };
                            ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($act['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-weight:500;"><?= htmlspecialchars($act['cliente_nombre'] ?? 'Sin cliente específico', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge-tipo">
                                        <span><?= htmlspecialchars($iconoTipo, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?= htmlspecialchars(ucfirst($act['tipo'] ?? 'Acción'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-muted);"><?= htmlspecialchars($act['descripcion'] ?? $act['asunto'] ?? 'Sin detalles', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div>Mostrando <?= htmlspecialchars($totalActividades, ENT_QUOTES, 'UTF-8') ?> de <?= htmlspecialchars($totalActividades, ENT_QUOTES, 'UTF-8') ?> actividades</div>
        </div>
    </div>
</main>

</body>
</html>