<?php
// Incluimos la verificación de sesión de administrador
require_once _DIR_ . '/../config/auth_check.php';

// Conexión a la base de datos
$db = getDB();

// Lógica de búsqueda de clientes
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($busqueda !== '') {
    $stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE nombre LIKE ? OR email LIKE ? ORDER BY creado_en DESC");
    $stmt->execute(["%$busqueda%", "%$busqueda%"]);
    $clientes = $stmt->fetchAll();
} else {
    $clientes = $db->query("SELECT * FROM usuarios_cliente ORDER BY creado_en DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clientes – RestaurApp Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0b0b0b; --card: #1c1c1c; --border: #2a2a2a;
  --accent: #e8b86d; --text: #f0ede8; --muted: #7a7060;
  --sidebar-w: 240px;
}
body { background:var(--bg); color:var(--text); font-family:'DM Sans',sans-serif; display:flex; min-height:100vh; font-size:14px; }
.sidebar { width:var(--sidebar-w); background:#141414; border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0;left:0; height:100vh; z-index:100; }
.sidebar-logo { padding:24px 20px; border-bottom:1px solid var(--border); }
.sidebar-logo .name { font-family:'Playfair Display',serif; font-size:20px; color:var(--accent); }
.sidebar-logo .role { font-size:11px; color:var(--muted); letter-spacing:1px; text-transform:uppercase; margin-top:2px; }
.nav { padding:16px 12px; flex:1; }
.nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; color:var(--muted); text-decoration:none; font-size:13px; font-weight:500; transition:all .15s; margin-bottom:2px; }
.nav-item:hover,.nav-item.active { background:rgba(232,184,109,.08); color:var(--accent); }
.sidebar-bottom { padding:16px 12px; border-top:1px solid var(--border); }
.logout-btn { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; color:#e07070; text-decoration:none; font-size:13px; font-weight:500; transition:background .15s; }
.logout-btn:hover { background:rgba(224,112,112,.1); }

.main { margin-left:var(--sidebar-w); flex:1; padding:28px 32px; }
.page-title { font-family:'Playfair Display',serif; font-size:26px; margin-bottom:8px; }
.subtitle { color:var(--muted); font-size:13px; margin-bottom:24px; }

.controls-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:16px; }
.search-box { display:flex; gap:8px; background:var(--card); border:1px solid var(--border); padding:8px 14px; border-radius:8px; width:320px; }
.search-box input { background:transparent; border:none; outline:none; color:var(--text); font-family:inherit; font-size:13px; width:100%; }

.table-card { background:var(--card); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
table { width:100%; border-collapse:collapse; text-align:left; }
th { background:#141414; padding:14px 16px; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border); }
td { padding:14px 16px; border-bottom:1px solid var(--border); font-size:13px; color:var(--text); }
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.02); }

.badge-status { padding:4px 8px; border-radius:4px; font-size:11px; font-weight:500; display:inline-block; }
.badge-confirmed { background:rgba(110,224,140,.1); color:#6ee08c; }
.badge-pending { background:rgba(224,180,110,.1); color:#e0b46e; }

.points-pill { background:rgba(232,184,109,.1); color:var(--accent); font-weight:600; padding:2px 8px; border-radius:12px; font-size:12px; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo"><div class="name">🍽️ RestaurApp</div><div class="role">Administrador</div></div>
  <nav class="nav">
    <a class="nav-item" href="dashboard.php"><span>📊</span> Dashboard</a>
    <a class="nav-item" href="pedidos.php"><span>📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span>🪑</span> Mesas & QR</a>
    <a class="nav-item" href="menu.php"><span>🍽️</span> Menú</a>
    <a class="nav-item active" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="corte.php"><span>💵</span> Corte de Caja</a>
  </nav>
  <div class="sidebar-bottom"><a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a></div>
</aside>

<main class="main">
  <div class="page-title">👥 Control de Clientes</div>
  <div class="subtitle">Lista de usuarios registrados en el sistema de fidelidad.</div>

  <div class="controls-bar">
    <form class="search-box" method="GET" action="clientes.php">
      <span>🔍</span>
      <input type="text" name="q" placeholder="Buscar por nombre o correo..." value="<?= htmlspecialchars($busqueda) ?>">
    </form>
  </div>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Cliente</th>
          <th>Email</th>
          <th>Teléfono</th>
          <th>Puntos</th>
          <th>Estado Email</th>
          <th>Fecha Registro</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($clientes) > 0): ?>
          <?php foreach ($clientes as $c): ?>
            <tr>
              <td>#<?= $c['id'] ?></td>
              <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
              <td><?= htmlspecialchars($c['email']) ?></td>
              <td><?= htmlspecialchars($c['telefono'] ?? 'N/A') ?></td>
              <td><span class="points-pill"><?= $c['puntos'] ?> pts</span></td>
              <td>
                <?php if ($c['email_confirmado']): ?>
                  <span class="badge-status badge-confirmed">✓ Confirmado</span>
                <?php else: ?>
                  <span class="badge-status badge-pending">⏳ Pendiente</span>
                <?php endif; ?>
              </td>
              <td><?= date('d/m/Y', strtotime($c['creado_en'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--muted); padding: 30px;">
              No se encontraron clientes registrados.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

</body>
</html>