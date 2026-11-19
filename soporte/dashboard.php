<?php
/**
 * Archivo: soporte/dashboard.php
 * Descripción: Panel de control de Soporte con vista modular por secciones (Inicio / Resumen, Tickets de la App, Interacciones y Evaluaciones).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';

// Verificar acceso permitido exclusivamente para soporte o gerente
verificarAcceso(['soporte', 'gerente']);

$db = getDB();
$admin_id = $_SESSION['admin_id'] ?? ($_SESSION['id'] ?? 0);

// Obtener datos del usuario de soporte logueado
$stmtAdmin = $db->prepare("SELECT nombre, rol FROM admins WHERE id = ?");
$stmtAdmin->execute([$admin_id]);
$soporte_user = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

// Sección activa (por defecto 'inicio')
$seccion = $_GET['seccion'] ?? 'inicio';

// Consultas a la base de datos
try {
    $stmtTickets = $db->query("
        SELECT t.*, c.nombre AS cliente_nombre, c.email AS cliente_correo, c.telefono AS cliente_tel
        FROM tickets_soporte t
        LEFT JOIN usuarios_cliente c ON c.id = t.cliente_id
        ORDER BY t.id DESC
    ");
    $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tickets = [];
}

try {
    $stmtInteracciones = $db->query("
        SELECT i.*, c.nombre AS cliente_nombre, c.email AS cliente_correo, c.telefono AS cliente_tel
        FROM interacciones i
        LEFT JOIN usuarios_cliente c ON c.id = i.cliente_id
        ORDER BY i.fecha DESC, i.hora DESC
    ");
    $interacciones = $stmtInteracciones->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $interacciones = [];
}

try {
    $stmtEvaluaciones = $db->query("
        SELECT e.*, c.nombre AS cliente_nombre, c.email AS cliente_correo
        FROM evaluaciones e
        LEFT JOIN usuarios_cliente c ON c.id = e.cliente_id
        ORDER BY e.id DESC
    ");
    $evaluaciones = $stmtEvaluaciones->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $evaluaciones = [];
}

// Métricas generales para el inicio
$totalTickets = count($tickets);
$totalInteracciones = count($interacciones);
$totalEvaluaciones = count($evaluaciones);
$ticketsPendientes = count(array_filter($tickets, fn($t) => strtolower($t['estado'] ?? '') === 'pendiente'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Soporte y Atención al Cliente – RestaurApp</title>
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
        --primary: #d97706; /* Ámbar / Amarillo institucional de soporte */
        --green: #13b77a;
        --warning: #f59e0b;
        --danger: #ef5b63;
        --sidebar-w: 260px;
        --radius: 13px;
        --shadow-sm: 0 2px 5px rgba(15,23,42,.025);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; font-family: 'DM Sans', sans-serif; }

    body {
        background: var(--bg);
        color: var(--text);
        display: flex;
        min-height: 100vh;
        font-size: 14px;
    }

    /* SIDEBAR INSTITUCIONAL */
    .sidebar {
        width: var(--sidebar-w);
        background: var(--sidebar);
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
        border-bottom: 1px solid #0b2455;
    }

    .sidebar-logo .name {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        color: #fff;
    }

    .sidebar-logo .role {
        font-size: 11px;
        color: #fde68a;
        text-transform: uppercase;
        margin-top: 4px;
        letter-spacing: 0.5px;
        font-weight: 600;
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
        margin-bottom: 4px;
        transition: .15s;
    }

    .nav-item:hover, .nav-item.active {
        background: var(--sidebar-hover);
        color: #fff;
    }

    /* CONTENIDO PRINCIPAL */
    .main {
        margin-left: var(--sidebar-w);
        flex: 1;
        padding: 32px;
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
    }

    .page-title {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        font-weight: 700;
    }

    .subtitle {
        color: var(--muted);
        font-size: 13px;
        margin-top: 4px;
    }

    /* ESTADÍSTICAS */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 28px;
    }

    .stat-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 22px;
        box-shadow: var(--shadow-sm);
    }

    .stat-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--text);
    }

    /* TABLAS Y CONTENEDORES */
    .table-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
    }

    .table-header {
        padding: 20px 24px;
        font-size: 15px;
        font-weight: 700;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 12px 24px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--muted);
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
    }

    td {
        padding: 14px 24px;
        border-bottom: 1px solid #edf1f5;
        font-size: 13px;
    }

    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .badge-pendiente { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
    .badge-completada, .badge-resuelto { background: rgba(19, 183, 122, 0.1); color: var(--green); }
    .badge-estrellas { background: rgba(217, 119, 6, 0.1); color: var(--primary); font-weight: 700; }
    .cliente-badge { background: #f3e8ff; color: #9333ea; }

    @media (max-width: 900px) {
        .stats-grid { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="name">🍽️ RestaurApp</div>
        <div class="role"><?= htmlspecialchars(ucfirst($soporte_user['rol'] ?? 'Soporte')) ?>: <?= htmlspecialchars($soporte_user['nombre'] ?? '') ?></div>
    </div>
    
    <nav class="nav">
        <a class="nav-item <?= $seccion === 'inicio' ? 'active' : '' ?>" href="dashboard.php?seccion=inicio"><span>📊</span> Resumen General</a>
        <a class="nav-item <?= $seccion === 'tickets' ? 'active' : '' ?>" href="dashboard.php?seccion=tickets"><span>📱</span> Tickets de la App</a>
        <a class="nav-item <?= $seccion === 'interacciones' ? 'active' : '' ?>" href="dashboard.php?seccion=interacciones"><span>💬</span> Interacciones CRM</a>
        <a class="nav-item <?= $seccion === 'evaluaciones' ? 'active' : '' ?>" href="dashboard.php?seccion=evaluaciones"><span>⭐</span> Evaluaciones Clientes</a>
    </nav>

    <div style="padding: 16px; border-top: 1px solid #0b2455;">
        <a href="../logout.php" style="color: #ff6868; text-decoration: none; font-size: 13px; font-weight: 500;">🚪 Cerrar sesión</a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
    <div class="topbar">
        <div>
            <div class="page-title">
                <?php 
                    if ($seccion === 'tickets') echo 'Gestión de Tickets de la App';
                    elseif ($seccion === 'interacciones') echo 'Historial de Interacciones CRM';
                    elseif ($seccion === 'evaluaciones') echo 'Evaluaciones y Calificaciones';
                    else echo 'Panel General de Soporte';
                ?>
            </div>
            <div class="subtitle">
                <?php 
                    if ($seccion === 'tickets') echo 'Buzón detallado de solicitudes enviadas por los clientes';
                    elseif ($seccion === 'interacciones') echo 'Registro de llamadas, correos y atención personalizada';
                    elseif ($seccion === 'evaluaciones') echo 'Calificaciones y opiniones dejadas por los usuarios';
                    else echo 'Resumen general de métricas y actividad de atención al cliente';
                ?>
            </div>
        </div>
        <div style="background: #fff; border: 1px solid var(--border); padding: 10px 16px; border-radius: 10px; font-size: 13px;">
            📅 <?= htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <?php if ($seccion === 'inicio'): ?>
        <!-- VISTA DE INICIO / RESUMEN GENERAL -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Tickets de la App</div>
                <div class="stat-value"><?= $totalTickets ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tickets Pendientes</div>
                <div class="stat-value" style="color: var(--warning);"><?= $ticketsPendientes ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Evaluaciones Recibidas</div>
                <div class="stat-value" style="color: var(--green);"><?= $totalEvaluaciones ?></div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <span>📋 Resumen Rápido de Solicitudes Recientes</span>
                <a href="dashboard.php?seccion=tickets" style="font-size: 12px; color: var(--primary); text-decoration: none;">Ver todos los tickets →</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Asunto</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--muted); padding: 30px;">No hay actividad reciente.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($tickets, 0, 5) as $t): ?>
                            <tr>
                                <td><span class="badge cliente-badge">👤 <?= htmlspecialchars($t['cliente_nombre'] ?? 'Cliente') ?></span></td>
                                <td><b><?= htmlspecialchars($t['asunto'] ?? 'Sin asunto') ?></b></td>
                                <td><span class="badge badge-<?= htmlspecialchars(strtolower($t['estado'] ?? 'pendiente')) ?>"><?= ucfirst(htmlspecialchars($t['estado'] ?? 'pendiente')) ?></span></td>
                                <td style="color: var(--muted);"><?= htmlspecialchars($t['creado_en'] ?? $t['fecha'] ?? 'N/D') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($seccion === 'tickets'): ?>
        <!-- SECCIÓN: TICKETS DE LA APP -->
        <div class="table-card">
            <div class="table-header">
                <span>📱 Tickets y Solicitudes Enviadas desde la App</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Asunto / Mensaje</th>
                        <th>Estado</th>
                        <th>Fecha de Envío</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 40px;">No hay tickets enviados desde la aplicación.</td></tr>
                    <?php else: foreach ($tickets as $t): ?>
                        <tr>
                            <td><span class="badge cliente-badge">👤 <?= htmlspecialchars($t['cliente_nombre'] ?? 'Cliente General') ?></span></td>
                            <td>
                                <div>✉️ <?= htmlspecialchars($t['cliente_correo'] ?? 'N/A') ?></div>
                                <div style="color: var(--muted); font-size: 12px;">📞 <?= htmlspecialchars($t['cliente_tel'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <b><?= htmlspecialchars($t['asunto'] ?? 'Sin asunto') ?></b>
                                <div style="font-size: 12px; color: var(--muted);"><?= htmlspecialchars($t['descripcion'] ?? $t['mensaje'] ?? '') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars(strtolower($t['estado'] ?? 'pendiente')) ?>">
                                    <?= ucfirst(htmlspecialchars($t['estado'] ?? 'pendiente')) ?>
                                </span>
                            </td>
                            <td style="color: var(--muted);"><?= htmlspecialchars($t['creado_en'] ?? $t['fecha'] ?? 'N/D') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($seccion === 'interacciones'): ?>
        <!-- SECCIÓN: INTERACCIONES CRM -->
        <div class="table-card">
            <div class="table-header">
                <span>💬 Historial de Interacciones CRM</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Tipo</th>
                        <th>Detalles / Comentario</th>
                        <th>Fecha y Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($interacciones)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 40px;">No hay interacciones registradas.</td></tr>
                    <?php else: foreach ($interacciones as $i): ?>
                        <tr>
                            <td><span class="badge cliente-badge">👤 <?= htmlspecialchars($i['cliente_nombre'] ?? 'Cliente General') ?></span></td>
                            <td>
                                <div>✉️ <?= htmlspecialchars($i['cliente_correo'] ?? 'N/A') ?></div>
                                <div style="color: var(--muted); font-size: 12px;">📞 <?= htmlspecialchars($i['cliente_tel'] ?? 'N/A') ?></div>
                            </td>
                            <td><span style="text-transform: capitalize; font-weight: 600;"><?= htmlspecialchars($i['tipo'] ?? 'General') ?></span></td>
                            <td>
                                <b><?= htmlspecialchars($i['asunto'] ?? 'Sin asunto') ?></b>
                                <div style="font-size: 12px; color: var(--muted);"><?= htmlspecialchars($i['descripcion'] ?? '') ?></div>
                            </td>
                            <td style="color: var(--muted);"><?= htmlspecialchars($i['fecha'] . ' ' . $i['hora']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($seccion === 'evaluaciones'): ?>
        <!-- SECCIÓN: EVALUACIONES -->
        <div class="table-card">
            <div class="table-header">
                <span>⭐ Evaluaciones y Calificaciones de Clientes</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Calificación</th>
                        <th>Comentarios / Opinión</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($evaluaciones)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--muted); padding: 40px;">No hay evaluaciones registradas.</td></tr>
                    <?php else: foreach ($evaluaciones as $e): ?>
                        <tr>
                            <td><span class="badge cliente-badge">👤 <?= htmlspecialchars($e['cliente_nombre'] ?? 'Anónimo') ?></span></td>
                            <td>
                                <span class="badge badge-estrellas">⭐ <?= htmlspecialchars($e['calificacion'] ?? '5') ?> / 5</span>
                            </td>
                            <td><?= htmlspecialchars($e['comentarios'] ?? 'Sin comentarios adicionales.') ?></td>
                            <td style="color: var(--muted);"><?= htmlspecialchars($e['fecha'] ?? 'N/D') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

</body>
</html>