<?php
/**
 * Archivo: gerente/cliente_evaluaciones.php
 * Descripción: Evaluaciones específicas de un cliente con Sidebar institucional y control de acceso.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente']);
$db = getDB();

$cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$cliente_id) {
    header('Location: clientes.php');
    exit;
}

// Obtener datos del cliente
$stmt_cliente = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmt_cliente->execute([$cliente_id]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: clientes.php');
    exit;
}

// Obtener evaluaciones del cliente
$stmt_eval = $db->prepare("SELECT * FROM evaluaciones WHERE cliente_id = ? ORDER BY fecha DESC");
$stmt_eval->execute([$cliente_id]);
$evaluaciones = $stmt_eval->fetchAll(PDO::FETCH_ASSOC);

// Control de alertas de stock crítico para la barra lateral
$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();
$rol_actual = $_SESSION['admin_rol'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Evaluaciones del Cliente – Panel Gerencial</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #f8fafc;
        --bg-surface: #ffffff;
        --sidebar-bg: #000049;
        --sidebar-hover: #0369a1;
        --sidebar-text: #cbd5e1;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-primary: #0284c7;
        --color-primary-hover: #0369a1;
        --sidebar-w: 260px;
        --radius: 10px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --danger: #ef4444;
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

    /* CONTENIDO PRINCIPAL */
    .main {
        margin-left: var(--sidebar-w);
        flex: 1;
        padding: 32px 40px;
    }

    .back-link {
        display: inline-block;
        color: var(--text-muted);
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 16px;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: var(--color-primary);
    }

    .client-header-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
    }

    .client-info h1 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .client-info p {
        color: var(--text-muted);
        font-size: 13px;
        margin-top: 4px;
    }

    .badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge.Activo { background: #dcfce7; color: #15803d; }

    /* PESTAÑAS DE DETALLE */
    .detail-tabs {
        display: flex;
        gap: 8px;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 24px;
    }

    .tab-item {
        padding: 10px 16px;
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 500;
        font-size: 13px;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
    }

    .tab-item:hover {
        color: var(--text-main);
    }

    .tab-item.active {
        color: var(--color-primary);
        border-bottom-color: var(--color-primary);
        font-weight: 600;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-main);
    }

    .btn-primary {
        background: var(--color-primary);
        color: #fff;
        padding: 8px 16px;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        background: var(--color-primary-hover);
    }

    /* TARJETAS DE EVALUACIÓN */
    .eval-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 16px;
        box-shadow: var(--shadow-sm);
    }

    .eval-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .eval-score {
        font-weight: 700;
        font-size: 15px;
        color: var(--color-primary);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .eval-date {
        font-size: 12px;
        color: var(--text-muted);
    }

    .eval-comment {
        font-size: 14px;
        color: var(--text-main);
        line-height: 1.5;
    }

    .empty-state {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        color: var(--text-muted);
    }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="name">Panel Gerencial</div>
    <div class="role"><?= ucfirst(htmlspecialchars($rol_actual, ENT_QUOTES, 'UTF-8')) ?></div>
  </div>
  <nav class="nav">
    <a class="nav-item" href="dashboard.php"><span>📊</span> Dashboard</a>
    <a class="nav-item active" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="interacciones.php"><span>💬</span> Interacciones</a>
    <a class="nav-item" href="evaluaciones.php"><span>⭐</span> Evaluaciones</a>
    <a class="nav-item" href="usuarios.php"><span>🛡️</span> Usuarios</a>
    <a class="nav-item" href="miactividad.php"><span>⏱️</span> Mi actividad</a>
    <a class="nav-item" href="configuracion.php"><span>⚙️</span> Configuración</a>
    
    <?php if ($rol_actual === 'gerente'): ?>
        <div style="padding: 10px 16px; font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-top: 10px;">SCM & Logística</div>
        <a class="nav-item" href="../scm/dashboard.php">
            <span>📈</span> Dashboard SCM
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;">⚠️ <?= $num_alertas_global ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
  <a href="clientes.php" class="back-link">&larr; Volver a clientes</a>

  <div class="client-header-card">
    <div class="client-info">
        <h1><?= htmlspecialchars($cliente['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?> <span class="badge <?= htmlspecialchars($cliente['estado'] ?? 'Activo', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cliente['estado'] ?? 'Activo', ENT_QUOTES, 'UTF-8') ?></span></h1>
        <p><?= htmlspecialchars($cliente['email'] ?? '', ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($cliente['telefono'] ?? 'Sin teléfono', ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>

  <!-- PESTAÑAS DE DETALLE -->
  <div class="detail-tabs">
    <a href="cliente_ver.php?id=<?= $cliente['id'] ?>" class="tab-item">Información</a>
    <a href="cliente_interacciones.php?id=<?= $cliente['id'] ?>" class="tab-item">Interacciones</a>
    <a href="cliente_evaluaciones.php?id=<?= $cliente['id'] ?>" class="tab-item active">Evaluaciones</a>
  </div>

  <div class="section-header">
    <div class="section-title">Evaluaciones del cliente</div>
    <a href="evaluacion_crear.php?cliente_id=<?= $cliente['id'] ?>" class="btn-primary">+ Nueva evaluación</a>
  </div>

  <?php if (empty($evaluaciones)): ?>
      <div class="empty-state">
          No hay evaluaciones registradas para este cliente.
      </div>
  <?php else: ?>
      <?php foreach ($evaluaciones as $ev): ?>
          <div class="eval-card">
              <div class="eval-header">
                  <div class="eval-score">
                      ⭐ <?= htmlspecialchars($ev['puntuacion'] ?? '5', ENT_QUOTES, 'UTF-8') ?> / 5 
                      <span style="font-size: 12px; color: var(--text-muted); font-weight: normal; margin-left: 8px;">(<?= htmlspecialchars($ev['tipo'] ?? 'General', ENT_QUOTES, 'UTF-8') ?>)</span>
                  </div>
                  <div class="eval-date"><?= htmlspecialchars($ev['fecha'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="eval-comment">
                  <?= nl2br(htmlspecialchars($ev['comentario'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
              </div>
          </div>
      <?php endforeach; ?>
  <?php endif; ?>
</main>

</body>
</html>