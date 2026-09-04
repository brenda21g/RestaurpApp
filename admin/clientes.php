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

// LÓGICA DE FILTRADO DE ESTADOS:
if (!empty($filtro_estado)) {
    if ($filtro_estado !== 'Todos') {
        // Si selecciona un estado específico (Activo, Inactivo, Baja, Prospecto), se filtra por ese
        $sql .= " AND estado = ?";
        $params[] = $filtro_estado;
    }
    // Si selecciona 'Todos', no agregamos ninguna condición de estado, por lo que trae absolutamente todos.
} else {
    // POR DEFECTO (si no se ha seleccionado nada en el filtro): 
    // Excluimos a los que tienen estado 'Baja' para que no aparezcan en la lista principal.
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
<title>Clientes – RestaurApp Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  :root {
    --bg: #0b0b0b;
    --card: #1c1c1c;
    --border: #2a2a2a;
    --accent: #e8b86d;
    --text: #f0ede8;
    --muted: #7a7060;
    --sidebar-w: 240px;
    --green: #6dbf8a;
    --red: #e07070;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    display: flex;
    min-height: 100vh;
    font-size: 14px;
  }

  /* SIDEBAR */
  .sidebar {
    width: var(--sidebar-w);
    background: #141414;
    border-right: 1px solid var(--border);
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
    border-bottom: 1px solid var(--border);
  }

  .sidebar-logo .name {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: var(--accent);
  }

  .sidebar-logo .role {
    font-size: 11px;
    color: var(--muted);
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
    border-radius: 8px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all .15s;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    margin-bottom: 2px;
  }

  .nav-item:hover, 
  .nav-item.active {
    background: rgba(232, 184, 109, 0.08);
    color: var(--accent);
  }

  .sidebar-bottom {
    padding: 16px 12px;
    border-top: 1px solid var(--border);
  }

  .logout-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #e07070;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background .15s;
  }

  .logout-btn:hover {
    background: rgba(224, 112, 112, 0.1);
  }

  /* CONTENIDO PRINCIPAL */
  .main {
    margin-left: var(--sidebar-w);
    flex: 1;
    padding: 28px 32px;
  }

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }

  .page-title {
    font-family: 'Playfair Display', serif;
    font-size: 26px;
  }

  .btn-primary {
    background: var(--accent);
    color: #0f0f0f;
    padding: 9px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
    transition: opacity 0.2s;
    display: inline-block;
  }

  .btn-primary:hover {
    opacity: 0.9;
  }

  .filters-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
  }

  .filter-input {
    background: #1a1a1a;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 14px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    outline: none;
  }

  .filter-input:focus {
    border-color: var(--accent);
  }

  .filter-input[name="q"] {
    flex: 1;
    max-width: 320px;
  }

  select.filter-input option {
    background: #1a1a1a;
  }

  .table-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  th {
    text-align: left;
    padding: 10px 16px;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    background: rgba(255, 255, 255, 0.02);
  }

  td {
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
  }

  .badge.Activo {
    background: rgba(109, 191, 138, 0.15);
    color: var(--green);
  }

  .badge.Inactivo {
    background: rgba(224, 112, 112, 0.15);
    color: var(--muted);
  }

  .badge.Baja {
    background: rgba(224, 112, 112, 0.15);
    color: var(--red);
  }

  .badge.Prospecto {
    background: rgba(232, 184, 109, 0.15);
    color: var(--accent);
  }

  .actions-icons a {
    color: var(--muted);
    text-decoration: none;
    margin-right: 12px;
    font-size: 15px;
    transition: color 0.2s;
  }

  .actions-icons a:hover {
    color: var(--accent);
  }

  .actions-icons a.delete:hover {
    color: var(--red);
  }
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
    <a class="nav-item" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a class="nav-item active" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="interacciones.php"><span class="icon">💬</span> Interacciones</a>
    <a class="nav-item" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span class="icon">🪑</span> Mesas & QR</a>
    <a class="nav-item" href="menu.php"><span class="icon">🍽️</span> Menú</a>
    <a class="nav-item" href="corte.php"><span>💵</span> Corte de Caja</a>

    <?php if (isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] === 'super_admin'): ?>
        <a class="nav-item" href="usuarios.php"><span class="icon">🛡️</span> Administradores</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
  <div class="page-header">
    <div class="page-title">👥 Gestión de Clientes</div>
    <a href="cliente_crear.php" class="btn-primary">+ Nuevo cliente</a>
  </div>

  <form method="GET" class="filters-bar">
    <input class="filter-input" type="text" name="q" placeholder="Buscar por nombre o correo..." value="<?= htmlspecialchars($busqueda) ?>">
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
                <tr><td colspan="7" style="text-align:center; color: var(--muted); padding: 30px;">No se encontraron clientes registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td style="font-weight: 500;"><?= htmlspecialchars($c['nombre']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= htmlspecialchars($c['telefono'] ?? 'N/D') ?></td>
                    <td><?= htmlspecialchars($c['etapa_crm'] ?? 'Prospecto') ?></td>
                    <td><span class="badge <?= htmlspecialchars($c['estado'] ?? 'Activo') ?>"><?= htmlspecialchars($c['estado'] ?? 'Activo') ?></span></td>
                    <td class="actions-icons">
                        <a href="cliente_ver.php?id=<?= $c['id'] ?>" title="Ver detalle">👁️</a>
                        <a href="cliente_editar.php?id=<?= $c['id'] ?>" title="Editar">✏️</a>
                        <a href="cliente_eliminar.php?id=<?= $c['id'] ?>" title="Eliminar" class="delete" onclick="return confirm('¿Seguro que deseas dar de baja este cliente?');">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
  </div>
</main>

</body>
</html>