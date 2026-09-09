<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Módulo de Interacciones CRM 
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'crear') {
        $cliente_id = !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : null;
        $tipo = $_POST['tipo'] ?? 'correo';
        $asunto = trim($_POST['asunto'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';

        if ($asunto === '' || $fecha === '' || $hora === '') {
            $error = "Por favor completa los campos obligatorios.";
        } else {
            $stmt = $db->prepare("
                INSERT INTO interacciones
                (cliente_id, tipo, asunto, descripcion, fecha, hora, estado, creado_en)
                VALUES (?, ?, ?, ?, ?, ?, 'pendiente', NOW())
            ");
            $stmt->execute([$cliente_id, $tipo, $asunto, $descripcion, $fecha, $hora]);
            header("Location: interacciones.php?mensaje=creada");
            exit;
        }
    }

    if ($accion === 'estado') {
        $id = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? 'pendiente';
        $estadosPermitidos = ['pendiente', 'completada', 'cancelada'];

        if ($id > 0 && in_array($estado, $estadosPermitidos, true)) {
            $stmt = $db->prepare("UPDATE interacciones SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
        }

        header("Location: interacciones.php");
        exit;
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM interacciones WHERE id = ?");
            $stmt->execute([$id]);
        }

        header("Location: interacciones.php");
        exit;
    }
}

if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'creada') {
    $mensaje = 'Interacción programada correctamente.';
}

$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$sql = "
    SELECT i.*, c.nombre AS cliente_nombre
    FROM interacciones i
    LEFT JOIN usuarios_cliente c ON c.id = i.cliente_id
    WHERE 1=1
";
$params = [];

if ($filtro_tipo !== '') {
    $sql .= " AND i.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_estado !== '') {
    $sql .= " AND i.estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY i.fecha ASC, i.hora ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$interacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtClientes = $db->query("
    SELECT id, nombre
    FROM usuarios_cliente
    ORDER BY nombre ASC
");
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

$totalInteracciones = $db->query("SELECT COUNT(*) FROM interacciones")->fetchColumn();
$pendientes = $db->query("SELECT COUNT(*) FROM interacciones WHERE estado = 'pendiente'")->fetchColumn();
$completadas = $db->query("SELECT COUNT(*) FROM interacciones WHERE estado = 'completada'")->fetchColumn();
$reuniones = $db->query("SELECT COUNT(*) FROM interacciones WHERE tipo = 'reunion' AND estado = 'pendiente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Interacciones – Restaurant App</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ==========================================================================
        VARIABLES Y RESET GENERAL (TEMA VERDE Y BLANCO - CONSISTENTE CON DASHBOARD)
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
        --color-info: #0284c7;
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

    .subtitle {
        color: var(--text-muted);
        font-size: 13px;
        margin-top: 2px;
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
        ESTADÍSTICAS (STATS GRID)
        ========================================================================== */
    .stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    .stat {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 24px;
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

    /* ==========================================================================
        TOOLBAR Y FILTROS
        ========================================================================== */
    .toolbar {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 16px 20px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        box-shadow: var(--shadow-sm);
    }

    .filters {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }

    select, input, textarea {
        background: var(--bg-surface);
        color: var(--text-main);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 9px 12px;
        font-family: inherit;
        font-size: 13px;
        outline: none;
        transition: border-color 0.2s;
    }

    select:focus, input:focus, textarea:focus {
        border-color: var(--color-primary);
    }

    textarea {
        min-height: 100px;
        resize: vertical;
    }

    .btn {
        border: none;
        border-radius: 8px;
        padding: 9px 16px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: opacity 0.2s;
    }

    .btn:hover { opacity: 0.9; }

    .btn-primary {
        background: var(--color-primary);
        color: #ffffff;
        font-weight: 600;
    }

    .btn-secondary {
        background: var(--bg-surface);
        color: var(--text-main);
        border: 1px solid var(--border-color);
    }

    .btn-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--color-danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .alert {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.2);
        color: var(--color-success);
        border-radius: var(--radius);
        padding: 12px 16px;
        margin-bottom: 24px;
        font-weight: 500;
    }

    /* ==========================================================================
        LISTADO DE INTERACCIONES (GRID)
        ========================================================================== */
    .interactions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .interaction {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 24px;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: border-color 0.2s;
    }

    .interaction:hover { border-color: #cbd5e1; }

    .interaction-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 14px;
    }

    .type {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        font-size: 14px;
    }

    .type-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        background: #f1f5f9;
    }

    .status {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pendiente { color: var(--color-warning); background: rgba(245, 158, 11, 0.1); }
    .status-completada { color: var(--color-success); background: rgba(16, 185, 129, 0.1); }
    .status-cancelada { color: var(--color-danger); background: rgba(239, 68, 68, 0.1); }

    .interaction h3 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 6px;
    }

    .client {
        color: var(--color-primary);
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 10px;
    }

    .description {
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 16px;
    }

    .info {
        display: flex;
        gap: 16px;
        color: var(--text-muted);
        font-size: 12px;
        margin-bottom: 16px;
        padding-top: 10px;
        border-top: 1px solid var(--border-color);
    }

    .actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .actions form { display: inline; }

    .empty {
        grid-column: 1/-1;
        text-align: center;
        padding: 60px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        color: var(--text-muted);
    }

    /* ==========================================================================
        MODAL
        ========================================================================== */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        z-index: 500;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal.show { display: flex; }

    .modal-content {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        width: 100%;
        max-width: 550px;
        padding: 28px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .modal-header h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-main);
    }

    .close {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 20px;
        cursor: pointer;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group.full { grid-column: 1/-1; }

    .form-group label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    @media (max-width: 1000px) {
        .stats { grid-template-columns: repeat(2, 1fr); }
        .interactions { grid-template-columns: 1fr; }
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
        <a class="nav-item active" href="interacciones.php"><span>💬</span> Interacciones</a>
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
            <div class="page-title">Interacciones</div>
            <div class="subtitle">Programa y administra las interacciones y seguimientos con tus clientes</div>
        </div>
        <div class="date-badge">📅 <?= date('d \d\e F \d\e Y') ?></div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert">✅ <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert" style="background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.2);color:var(--color-danger);">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- TARJETAS DE MÉTRICAS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?= $totalInteracciones ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value" style="color:var(--color-warning);"><?= $pendientes ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Completadas</div>
            <div class="stat-value" style="color:var(--color-success);"><?= $completadas ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Reuniones pendientes</div>
            <div class="stat-value" style="color:var(--color-info);"><?= $reuniones ?></div>
        </div>
    </div>

    <!-- TOOLBAR DE FILTROS -->
    <div class="toolbar">
        <form method="GET" class="filters">
            <select name="tipo">
                <option value="">Todos los tipos</option>
                <option value="correo" <?= $filtro_tipo === 'correo' ? 'selected' : '' ?>>📧 Correo</option>
                <option value="llamada" <?= $filtro_tipo === 'llamada' ? 'selected' : '' ?>>📞 Llamada</option>
                <option value="reunion" <?= $filtro_tipo === 'reunion' ? 'selected' : '' ?>>🤝 Reunión</option>
            </select>
            <select name="estado">
                <option value="">Todos los estados</option>
                <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                <option value="completada" <?= $filtro_estado === 'completada' ? 'selected' : '' ?>>Completadas</option>
                <option value="cancelada" <?= $filtro_estado === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
            </select>
            <button class="btn btn-secondary" type="submit">🔎 Filtrar</button>
        </form>
        <button class="btn btn-primary" onclick="abrirModal()">＋ Nueva interacción</button>
    </div>

    <!-- GRID DE INTERACCIONES -->
    <div class="interactions">
        <?php if (empty($interacciones)): ?>
            <div class="empty">
                <div style="font-size:36px;margin-bottom:10px;">💬</div>
                <div style="font-size:15px;font-weight:600;margin-bottom:4px;">No hay interacciones registradas</div>
                <div style="font-size:13px;">Programa una nueva interacción para mantener el seguimiento con un cliente.</div>
            </div>
        <?php else: ?>
            <?php foreach ($interacciones as $i): ?>
                <?php
                $icono = match($i['tipo']) {
                    'correo' => '📧',
                    'llamada' => '📞',
                    'reunion' => '🤝',
                    default => '💬'
                };
                $tipoTexto = match($i['tipo']) {
                    'correo' => 'Correo',
                    'llamada' => 'Llamada',
                    'reunion' => 'Reunión',
                    default => 'Interacción'
                };
                $estadoTexto = match($i['estado']) {
                    'pendiente' => 'Pendiente',
                    'completada' => 'Completada',
                    'cancelada' => 'Cancelada',
                    default => ucfirst($i['estado'])
                };
                ?>
                <div class="interaction">
                    <div>
                        <div class="interaction-top">
                            <div class="type">
                                <div class="type-icon"><?= $icono ?></div>
                                <div><?= $tipoTexto ?></div>
                            </div>
                            <span class="status status-<?= htmlspecialchars($i['estado']) ?>"><?= $estadoTexto ?></span>
                        </div>

                        <h3><?= htmlspecialchars($i['asunto']) ?></h3>
                        <div class="client">👤 <?= htmlspecialchars($i['cliente_nombre'] ?? 'Sin cliente específico') ?></div>

                        <?php if (!empty($i['descripcion'])): ?>
                            <div class="description"><?= nl2br(htmlspecialchars($i['descripcion'])) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="info">
                            <span>📅 <?= date('d/m/Y', strtotime($i['fecha'])) ?></span>
                            <span>🕐 <?= date('H:i', strtotime($i['hora'])) ?></span>
                        </div>

                        <div class="actions">
                            <?php if ($i['estado'] === 'pendiente'): ?>
                                <form method="POST">
                                    <input type="hidden" name="accion" value="estado">
                                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                    <input type="hidden" name="estado" value="completada">
                                    <button class="btn btn-secondary" type="submit" style="padding:6px 12px;font-size:12px;">✅ Completar</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="accion" value="estado">
                                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                    <input type="hidden" name="estado" value="cancelada">
                                    <button class="btn btn-danger" type="submit" style="padding:6px 12px;font-size:12px;">✕ Cancelar</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" onsubmit="return confirm('¿Estás seguro de eliminar esta interacción?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                <button class="btn btn-danger" type="submit" style="padding:6px 10px;font-size:12px;" title="Eliminar">🗑️</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL PARA NUEVA INTERACCIÓN -->
<div class="modal" id="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nueva interacción</h2>
            <button class="close" onclick="cerrarModal()">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="accion" value="crear">

            <div class="form-grid">
                <div class="form-group full">
                    <label>Cliente</label>
                    <select name="cliente_id" style="width:100%;">
                        <option value="">Sin cliente específico</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de interacción *</label>
                    <select name="tipo" required style="width:100%;">
                        <option value="correo">📧 Correo</option>
                        <option value="llamada">📞 Llamada</option>
                        <option value="reunion">🤝 Reunión</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Asunto *</label>
                    <input type="text" name="asunto" placeholder="Ej. Seguimiento de catálogo" required>
                </div>

                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>Hora *</label>
                    <input type="time" name="hora" required>
                </div>

                <div class="form-group full">
                    <label>Descripción / Notas</label>
                    <textarea name="descripcion" placeholder="Escribe los detalles de la interacción..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Programar interacción</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(){
    document.getElementById('modal').classList.add('show');
}
function cerrarModal(){
    document.getElementById('modal').classList.remove('show');
}
document.getElementById('modal').addEventListener('click', function(e){
    if(e.target === this) cerrarModal();
});
</script>
</body>
</html>