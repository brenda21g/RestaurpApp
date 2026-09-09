<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$busqueda = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$sql = "SELECT * FROM usuarios_cliente WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR email LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Lógica de filtrado de estados según requerimiento
if (!empty($filtro_estado)) {
    if ($filtro_estado !== 'Todos') {
        $sql .= " AND estado = ?";
        $params[] = $filtro_estado;
    }
} else {
    // Por defecto excluimos a los que tienen estado 'Baja'
    $sql .= " AND estado != 'Baja'";
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clientes – Restaurant_App</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #f8fafc;
        --bg-surface: #ffffff;
        --sidebar-bg: #011139;
        --sidebar-hover: #002056;
        --sidebar-text: #94a3b8;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-primary: #2563eb;
        --color-success: #10b981;
        --color-danger: #ef4444;
        --color-warning: #f59e0b;
        --sidebar-w: 250px;
        --radius: 10px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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

    /* SIDEBAR BLANCO CON AZUL */
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

    .btn-primary {
        background: var(--color-primary);
        color: #fff;
        padding: 10px 18px;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .filters-bar {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 24px;
        align-items: center;
    }

    .filter-input {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 10px 14px;
        color: var(--text-main);
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        outline: none;
        box-shadow: var(--shadow-sm);
        transition: border-color 0.2s;
    }

    .filter-input:focus {
        border-color: var(--color-primary);
    }

    .filter-input[name="q"] {
        flex: 1;
        max-width: 350px;
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

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge.Activo {
        background: #dcfce7;
        color: #15803d;
    }

    .badge.Inactivo {
        background: #f1f5f9;
        color: #475569;
    }

    .badge.Baja {
        background: #fee2e2;
        color: #b91c1c;
    }

    .badge.Prospecto {
        background: #e0f2fe;
        color: #0369a1;
    }

    .actions-icons a {
        color: var(--text-muted);
        text-decoration: none;
        margin-right: 12px;
        font-size: 15px;
        transition: color 0.2s;
        cursor: pointer;
    }

    .actions-icons a:hover {
        color: var(--color-primary);
    }

    .actions-icons a.delete:hover {
        color: var(--color-danger);
    }

    /* MODAL DE CONFIRMACIÓN CON FONDO BORROSO */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-box {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        padding: 28px;
        border-radius: 12px;
        width: 100%;
        max-width: 380px;
        box-shadow: var(--shadow-lg);
        text-align: center;
        animation: modalScale 0.2s ease-in-out;
    }

    @keyframes modalScale {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .modal-box h3 {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 10px;
    }

    .modal-box p {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 24px;
        line-height: 1.5;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
    }

    .modal-btn {
        flex: 1;
        padding: 10px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: background 0.2s;
        text-decoration: none;
        text-align: center;
    }

    .modal-btn.btn-cancel {
        background: #f1f5f9;
        color: var(--text-main);
        border: 1px solid var(--border-color);
    }

    .modal-btn.btn-cancel:hover {
        background: #e2e8f0;
    }

    .modal-btn.btn-danger {
        background: var(--color-danger);
        color: #ffffff;
    }

    .modal-btn.btn-danger:hover {
        background: #dc2626;
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
    <a class="nav-item active" href="clientes.php"><span>👥</span> Clientes</a>
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
  <div class="page-header">
    <div class="page-title">Clientes</div>
    <a href="cliente_crear.php" class="btn-primary">+ Nuevo cliente</a>
  </div>

  <form method="GET" class="filters-bar">
    <input class="filter-input" type="text" name="q" placeholder="Buscar por nombre, correo..." value="<?= htmlspecialchars($busqueda) ?>">
    <select class="filter-input" name="estado" onchange="this.form.submit()">
        <option value="">Todos (Excluyendo Bajas)</option>
        <option value="Todos" <?= $filtro_estado === 'Todos' ? 'selected' : '' ?>>Todos (Incluyendo Bajas)</option>
        <option value="Activo" <?= $filtro_estado === 'Activo' ? 'selected' : '' ?>>Activo</option>
        <option value="Inactivo" <?= $filtro_estado === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
        <option value="Baja" <?= $filtro_estado === 'Baja' ? 'selected' : '' ?>>Baja</option>
        <option value="Prospecto" <?= $filtro_estado === 'Prospecto' ? 'selected' : '' ?>>Prospecto</option>
    </select>
  </form>

  <div class="table-card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Teléfono</th>
                <th>Etapa CRM</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clientes)): ?>
                <tr><td colspan="7" style="text-align:center; color: var(--text-muted); padding: 30px;">No se encontraron clientes registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td style="font-weight: 600;"><?= htmlspecialchars($c['nombre']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= htmlspecialchars($c['telefono'] ?? 'N/D') ?></td>
                    <td><?= htmlspecialchars($c['etapa_crm'] ?? 'Prospecto') ?></td>
                    <td><span class="badge <?= htmlspecialchars($c['estado'] ?? 'Activo') ?>"><?= htmlspecialchars($c['estado'] ?? 'Activo') ?></span></td>
                    <td class="actions-icons">
                        <a href="cliente_ver.php?id=<?= $c['id'] ?>" title="Ver detalle">👁️</a>
                        <a href="cliente_editar.php?id=<?= $c['id'] ?>" title="Editar">✏️</a>
                        <a onclick="abrirModalBaja(<?= $c['id'] ?>)" title="Dar de baja" class="delete">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
  </div>
</main>

<!-- MODAL DE BAJA -->
<div class="modal-overlay" id="modalBaja">
    <div class="modal-box">
        <h3>Confirmar acción</h3>
        <p>¿Desea dar de baja este cliente?</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn btn-cancel" onclick="cerrarModalBaja()">Cancelar</button>
            <a id="btnConfirmarBaja" href="#" class="modal-btn btn-danger">Sí, dar de baja</a>
        </div>
    </div>
</div>

<script>
function abrirModalBaja(id) {
    const modal = document.getElementById('modalBaja');
    const btnConfirmar = document.getElementById('btnConfirmarBaja');
    btnConfirmar.href = 'cliente_eliminar.php?id=' + id;
    modal.classList.add('active');
}

function cerrarModalBaja() {
    const modal = document.getElementById('modalBaja');
    modal.classList.remove('active');
}

// Cerrar al hacer clic fuera del modal
window.addEventListener('click', function(e) {
    const modal = document.getElementById('modalBaja');
    if (e.target === modal) {
        cerrarModalBaja();
    }
});
</script>

</body>
</html>