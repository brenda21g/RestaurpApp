<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Mi Actividad (Restaurant_app)
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$admin_id = $_SESSION['admin_id'] ?? 0;

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

// Nota: Si la tabla interacciones registra al usuario responsable, se añade el filtro. 
// Asumimos que existe un campo `admin_id` o similar, o listamos las interacciones generales del sistema/usuario.
// Ajustaremos filtrando por el usuario en sesión si la tabla lo contempla, o mostrando las interacciones del CRM en general gestionadas.
// Vamos a filtrar por un campo opcional o mostrar las interacciones recientes del sistema asociadas.
// Consultemos las interacciones ordenadas por fecha descendente.

$sql .= " ORDER BY i.fecha DESC, i.hora DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales para métricas de actividad
$totalActividades = count($actividades);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi actividad – Restaurant_app CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ==========================================================================
        VARIABLES Y RESET GENERAL (TEMA AZUL Y BLANCO)
        ========================================================================== */
    :root {
        --bg-body: #f8fafc;
        --bg-surface: #ffffff;
        --sidebar-bg: #0f172a;
        --sidebar-hover: #1e293b;
        --sidebar-text: #94a3b8;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-primary: #0284c7;
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
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
        background-color: rgba(239, 68, 68, 0.15);
        color: #fff;
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

    .subtitle {
        color: var(--text-muted);
        font-size: 13px;
        margin-top: 2px;
    }

    /* ==========================================================================
        TARJETA CONTENEDORA DE ACTIVIDAD (ESTILO BLOQUE RECOPILATORIO)
        ========================================================================== */
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

    /* ==========================================================================
        TABLA DE ACTIVIDADES
        ========================================================================== */
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
        <div class="name">Restaurant App</div>
        <div class="role">Administrador</div>
    </div>
    <nav class="nav">
        <a class="nav-item" href="dashboard.php"><span>📊</span> Dashboard</a>
        <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
        <a class="nav-item" href="interacciones.php"><span>💬</span> Interacciones</a>
        <a class="nav-item" href="evaluaciones.php"><span>📋</span> Evaluaciones</a>
        <a class="nav-item" href="usuarios.php"><span>🛡️</span> Usuarios</a>
        <a class="nav-item active" href="miactividad.php"><span>⏱️</span> Mi actividad</a>
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
            <div class="page-title">Mi actividad</div>
            <div class="subtitle">Historial de gestiones y acciones realizadas en el sistema</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Registro de operaciones</div>
            <div class="date-filter-badge">
                <span>📅</span> <?= date('01/m/Y') ?> – <?= date('d/m/Y') ?>
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
                                <td><?= date('d/m/Y', strtotime($act['fecha'])) ?></td>
                                <td style="font-weight:500;"><?= htmlspecialchars($act['cliente_nombre'] ?? 'Sin cliente específico') ?></td>
                                <td>
                                    <span class="badge-tipo">
                                        <span><?= $iconoTipo ?></span>
                                        <?= htmlspecialchars(ucfirst($act['tipo'] ?? 'Acción')) ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-muted);"><?= htmlspecialchars($act['descripcion'] ?? $act['asunto'] ?? 'Sin detalles') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div>Mostrando <?= $totalActividades ?> de <?= $totalActividades ?> actividades</div>
        </div>
    </div>
</main>

</body>
</html>