<?php
// ==========================================================================
// MÓDULO: Listado General de Evaluaciones (Restaurant_App)
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Obtener todas las evaluaciones con los datos del cliente correspondiente
$sql = "SELECT e.*, c.nombre as cliente_nombre, c.email as cliente_email 
        FROM evaluaciones e 
        INNER JOIN usuarios_cliente c ON e.cliente_id = c.id 
        ORDER BY e.fecha DESC";
$stmt = $db->query($sql);
$evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Evaluaciones – Restaurant_App</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #f8fafc;
        --bg-surface: #ffffff;
        --sidebar-bg: #0f172a;
        --sidebar-hover: #1e293b;
        --sidebar-text: #94a3b8;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-primary: #2563eb;
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

    /* SIDEBAR */
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
    }

    .page-title {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-main);
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

    .eval-score {
        font-weight: 700;
        color: var(--color-primary);
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
    <a class="nav-item" href="dashboard.php"><span>📊</span> Dashboard</a>
    <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="interacciones.php"><span>💬</span> Interacciones</a>
    <a class="nav-item active" href="evaluaciones.php"><span>📋</span> Evaluaciones</a>
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
  <div class="page-header">
    <div class="page-title">Evaluaciones Generales</div>
  </div>

  <div class="table-card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Puntuación</th>
                <th>Tipo</th>
                <th>Comentario</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($evaluaciones)): ?>
                <tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 30px;">No hay evaluaciones registradas en el sistema.</td></tr>
            <?php else: ?>
                <?php foreach ($evaluaciones as $ev): ?>
                <tr>
                    <td><?= $ev['id'] ?></td>
                    <td>
                        <a href="cliente_ver.php?id=<?= $ev['cliente_id'] ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 600;">
                            <?= htmlspecialchars($ev['cliente_nombre']) ?>
                        </a>
                    </td>
                    <td><span class="eval-score">⭐ <?= htmlspecialchars($ev['puntuacion']) ?> / 5</span></td>
                    <td><?= htmlspecialchars($ev['tipo'] ?? 'General') ?></td>
                    <td><?= htmlspecialchars($ev['comentario']) ?></td>
                    <td style="color: var(--text-muted); font-size: 13px;"><?= htmlspecialchars($ev['fecha']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
  </div>
</main>

</body>
</html>