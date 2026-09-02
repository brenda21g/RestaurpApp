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

if (!empty($filtro_estado) && $filtro_estado !== 'Todos') {
    $sql .= " AND estado = ?";
    $params[] = $filtro_estado;
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
<title>Clientes – RestaurApp Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root { --bg: #0b0b0b; --surface: #141414; --card: #1c1c1c; --border: #2a2a2a; --accent: #e8b86d; --text: #f0ede8; --muted: #7a7060; --green: #6dbf8a; --red: #e07070; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; padding: 30px; }
    .top-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    h2 { font-size: 24px; }
    .btn-primary { background: var(--accent); color: #000; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; transition: opacity 0.2s; }
    .btn-primary:hover { opacity: 0.9; }
    .filters-bar { display: flex; gap: 12px; margin-bottom: 24px; }
    input, select { background: var(--card); border: 1px solid var(--border); color: var(--text); padding: 10px 14px; border-radius: 8px; font-size: 13px; outline: none; }
    input { flex: 1; max-width: 350px; }
    table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
    th, td { padding: 14px 20px; text-align: left; font-size: 13px; border-bottom: 1px solid var(--border); }
    th { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; background: rgba(255,255,255,0.02); }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
    .badge.Activo { background: rgba(109,191,138,0.15); color: var(--green); }
    .badge.Inactivo { background: rgba(224,112,112,0.15); color: var(--muted); }
    .badge.Baja { background: rgba(224,112,112,0.15); color: var(--red); }
    .badge.Prospecto { background: rgba(232,184,109,0.15); color: var(--accent); }
    .actions-icons a { color: var(--muted); text-decoration: none; margin-right: 12px; font-size: 16px; transition: color 0.2s; }
    .actions-icons a:hover { color: var(--accent); }
    .actions-icons a.delete:hover { color: var(--red); }
    .back-dash { display: inline-block; margin-bottom: 15px; color: var(--muted); text-decoration: none; font-size: 13px; }
    .back-dash:hover { color: var(--accent); }
</style>
</head>
<body>
<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="name">🍽️ RestaurApp</div>
    <div class="role">Administrador</div>
  </div>
<nav class="nav">
    <a class="nav-item active" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="interacciones.php"><span class="icon">💬</span> Interacciones</a>
    <a class="nav-item" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span class="icon">🪑</span> Mesas & QR</a>
    <a class="nav-item" href="menu.php"><span class="icon">🍽️</span> Menú</a>
    <a class="nav-item" href="corte.php"><span>💵</span> Corte de Caja</a>

</nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
</aside>

<div class="top-actions">
    <h2>Gestión de Clientes y Prospectos</h2>
    <a href="cliente_crear.php" class="btn-primary">+ Nuevo cliente</a>
</div>

<form method="GET" class="filters-bar">
    <input type="text" name="q" placeholder="Buscar por nombre o correo..." value="<?= htmlspecialchars($busqueda) ?>">
    <select name="estado" onchange="this.form.submit()">
        <option value="Todos">Todos los estados</option>
        <option value="Activo" <?= $filtro_estado === 'Activo' ? 'selected' : '' ?>>Activo</option>
        <option value="Inactivo" <?= $filtro_estado === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
        <option value="Baja" <?= $filtro_estado === 'Baja' ? 'selected' : '' ?>>Baja</option>
        <option value="Prospecto" <?= $filtro_estado === 'Prospecto' ? 'selected' : '' ?>>Prospecto</option>
    </select>
</form>

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
            <tr><td colspan="7" style="text-align:center; color: var(--muted); padding: 30px;">No se encontraron clientes registrados.</td></tr>
        <?php else: ?>
            <?php foreach ($clientes as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td style="font-weight: 500;"><?= htmlspecialchars($c['nombre']) ?></td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= htmlspecialchars($c['telefono'] ?? 'N/D') ?></td>
                <td><?= htmlspecialchars($c['etapa_crm'] ?? 'Prospecto') ?></td>
                <td><span class="badge <?= $c['estado'] ?? 'Activo' ?>"><?= htmlspecialchars($c['estado'] ?? 'Activo') ?></span></td>
                <td class="actions-icons">
                    <a href="cliente_ver.php?id=<?= $c['id'] ?>" title="Ver detalle">👁️</a>
                    <a href="cliente_editar.php?id=<?= $c['id'] ?>" title="Editar">✏️</a>
                    <a href="cliente_eliminar.php?id=<?= $c['id'] ?>" title="Eliminar" class="delete" onclick="return confirm('¿Seguro que deseas eliminar este cliente?');">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>