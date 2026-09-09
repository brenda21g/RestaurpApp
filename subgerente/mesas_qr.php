<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Carga explicita con FETCH_ASSOC
$mesas = $db->query("SELECT * FROM mesas ORDER BY numero ASC")->fetchAll(PDO::FETCH_ASSOC);

// Pre-generar la estructura para JavaScript en una sola pasada
$mesasData = array_map(function($m) {
    return [
        'id' => $m['id'],
        'numero' => $m['numero'],
        // Apuntamos directamente al login del cliente, pasando el token de la mesa
        'url' => SITE_URL . '/cliente/login.php?mesa=' . urlencode($m['qr_token'])
    ];
}, $mesas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mesas & QR – RestaurApp Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
.subtitle { color:var(--muted); font-size:13px; margin-bottom:28px; }
.mesas-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:16px; }
.mesa-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:20px; text-align:center; }
.mesa-num { font-family:'Playfair Display',serif; font-size:20px; color:var(--accent); margin-bottom:4px; }
.mesa-token { font-size:10px; color:var(--muted); word-break:break-all; margin-bottom:12px; }
.qr-box { background:white; border-radius:8px; padding:8px; display:inline-block; margin-bottom:12px; }
.qr-box img { display: block; margin: 0 auto; }
.url-link { font-size:11px; color:var(--muted); word-break:break-all; display:block; margin-bottom:12px; height: 30px; overflow: hidden; }
.print-mesa-btn { width: 100%; padding:10px; background:linear-gradient(135deg,#e8b86d,#c9956a); color:#0f0f0f; border:none; border-radius:6px; font-family:'DM Sans',sans-serif; font-size:12px; font-weight:600; cursor:pointer; }
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
    <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="interacciones.php"><span class="icon">💬</span> Interacciones</a>
    <a class="nav-item" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
    <a class="nav-item active" href="mesas_qr.php"><span class="icon">🪑</span> Mesas & QR</a>
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
<main class="main">
  <div class="page-title">🪑 Mesas & Códigos QR</div>
  <div class="subtitle">Cada mesa cuenta con su código de acceso directo para el cliente.</div>
  <div class="mesas-grid">
    <?php foreach ($mesasData as $m): ?>
    <div class="mesa-card">
      <div class="mesa-num">Mesa <?= htmlspecialchars($m['numero']) ?></div>
      <div class="qr-box" id="qr-<?= $m['id'] ?>"></div>
      <span class="url-link"><?= htmlspecialchars($m['url']) ?></span>
      <button class="print-mesa-btn" onclick="printQR(<?= $m['id'] ?>, <?= $m['numero'] ?>)">🖨️ Imprimir QR</button>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<script>
// Transmitir datos preparados a JavaScript
const mesasData = <?= json_encode($mesasData) ?>;

// Generar los códigos QR
mesasData.forEach(m => {
  new QRCode(document.getElementById('qr-' + m.id), {
    text: m.url, 
    width: 120, 
    height: 120,
    colorDark: "#000000", 
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
  });
});

// Función para impresión individual de QR
function printQR(id, numero) {
  const container = document.getElementById('qr-' + id);
  const canvas = container.querySelector('canvas');
  const img = container.querySelector('img');
  
  const qrSrc = canvas ? canvas.toDataURL("image/png") : img.src;
  
  const w = window.open('', '_blank');
  w.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>Imprimir QR Mesa ${numero}</title>
        <style>
          body { text-align: center; font-family: sans-serif; padding: 50px; }
          .card { border: 2px solid #333; padding: 40px; display: inline-block; border-radius: 15px; }
          h1 { font-size: 48px; margin-bottom: 10px; }
          img { width: 300px; margin: 20px 0; }
          p { font-size: 20px; color: #666; }
        </style>
      </head>
      <body>
        <div class="card">
            <h1>MESA ${numero}</h1>
            <img src="${qrSrc}">
            <p>Escanea para ver nuestro menú digital</p>
        </div>
        <script>
          window.onload = function() { 
            window.print(); 
            setTimeout(function() { window.close(); }, 500);
          }
        <\/script>
      </body>
    </html>
  `);
  w.document.close();
}
</script>
</body>
</html>