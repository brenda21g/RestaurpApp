<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$estado_filtro = $_GET['estado'] ?? '';
$fecha_filtro  = $_GET['fecha'] ?? date('Y-m-d');

$where = "WHERE DATE(pe.creado_en) = ?";
$params = [$fecha_filtro];

if ($estado_filtro && in_array($estado_filtro, ['pendiente','preparando','listo','entregado','cancelado'])) {
    $where .= " AND pe.estado = ?";
    $params[] = $estado_filtro;
}

// Se formatea explícitamente el identificador de orden como ID o columna dedicada
$pedidos = $db->prepare("
    SELECT pe.*, m.numero as mesa_num
    FROM pedidos pe 
    JOIN mesas m ON m.id = pe.mesa_id
    {$where} 
    ORDER BY pe.creado_en DESC
");
$pedidos->execute($params);
$lista = $pedidos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos – RestaurApp Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--bg:#0b0b0b;--card:#1c1c1c;--border:#2a2a2a;--accent:#e8b86d;--text:#f0ede8;--muted:#7a7060;--sidebar-w:240px;}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;font-size:14px;}
.sidebar{width:var(--sidebar-w);background:#141414;border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;}
.sidebar-logo{padding:24px 20px;border-bottom:1px solid var(--border);}
.sidebar-logo .name{font-family:'Playfair Display',serif;font-size:20px;color:var(--accent);}
.sidebar-logo .role{font-size:11px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-top:2px;}
.nav{padding:16px 12px;flex:1;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;transition:all .15s;margin-bottom:2px;}
.nav-item:hover,.nav-item.active{background:rgba(232,184,109,.08);color:var(--accent);}
.sidebar-bottom{padding:16px 12px;border-top:1px solid var(--border);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:#e07070;text-decoration:none;font-size:13px;font-weight:500;transition:background .15s;}
.logout-btn:hover{background:rgba(224,112,112,.1);}
.main{margin-left:var(--sidebar-w);flex:1;padding:28px 32px;}
.page-title{font-family:'Playfair Display',serif;font-size:26px;margin-bottom:20px;}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
.filter-input{background:#1a1a1a;border:1px solid var(--border);border-radius:8px;padding:8px 14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;}
.filter-input:focus{border-color:var(--accent);}
select.filter-input option{background:#1a1a1a;}
.filter-btn{padding:8px 16px;background:var(--accent);color:#0f0f0f;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;cursor:pointer;}
.table-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:10px 16px;font-size:11px;font-weight:500;letter-spacing:1px;text-transform:uppercase;color:var(--muted);background:rgba(255,255,255,.02);}
td{padding:12px 16px;border-top:1px solid var(--border);font-size:13px;vertical-align:middle;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;}
.badge.pendiente{background:rgba(232,184,109,.15);color:#e8b86d;}
.badge.preparando{background:rgba(109,176,232,.15);color:#6db0e8;}
.badge.listo{background:rgba(109,191,138,.2);color:#6dbf8a;}
.badge.entregado{background:rgba(109,191,138,.1);color:#4a9a67;}
.badge.cancelado{background:rgba(224,112,112,.1);color:#e07070;}
.detail-btn{padding:5px 12px;border:1px solid var(--border);border-radius:6px;background:transparent;color:var(--muted);font-size:12px;cursor:pointer;transition:all .15s;}
.detail-btn:hover{border-color:var(--accent);color:var(--accent);}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:200;align-items:center;justify-content:center;}
.modal-overlay.show{display:flex;}
.modal{background:#1c1c1c;border:1px solid #2a2a2a;border-radius:16px;padding:28px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto;}
.modal h3{font-family:'Playfair Display',serif;font-size:18px;margin-bottom:16px;}
.modal-close{float:right;background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;}
.item-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;}
.item-row:last-child{border-bottom:none;}
.total-line{display:flex;justify-content:space-between;padding-top:12px;font-weight:600;color:var(--accent);font-size:15px;}
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
    <a class="nav-item active" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
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
<main class="main">
  <div class="page-title">📋 Registro de pedidos</div>
  <form class="filters" method="GET">
    <input class="filter-input" type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>">
    <select class="filter-input" name="estado">
      <option value="">Todos los estados</option>
      <?php foreach (['pendiente','preparando','listo','entregado','cancelado'] as $e): ?>
        <option value="<?= $e ?>" <?= $estado_filtro === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="filter-btn" type="submit">Filtrar</button>
  </form>
  <div class="table-card">
    <table>
      <thead><tr><th>#Orden</th><th>Mesa</th><th>Total</th><th>Estado</th><th>Hora</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($lista)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px;">Sin pedidos registrados para el filtro seleccionado</td></tr>
        <?php else: ?>
          <?php foreach ($lista as $p): 
            $numOrden = $p['numero_orden'] ?? ('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT));
          ?>
          <tr>
            <td style="font-weight:500;"><?= htmlspecialchars($numOrden) ?></td>
            <td>Mesa <?= htmlspecialchars($p['mesa_num']) ?></td>
            <td>$<?= number_format($p['total'], 2) ?></td>
            <td><span class="badge <?= htmlspecialchars($p['estado']) ?>"><?= ucfirst(htmlspecialchars($p['estado'])) ?></span></td>
            <td style="color:var(--muted);"><?= date('H:i', strtotime($p['creado_en'])) ?></td>
            <td><button class="detail-btn" onclick="verDetalle(<?= (int)$p['id'] ?>)">Ver detalle</button></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<div class="modal-overlay" id="modal">
  <div class="modal">
    <button class="modal-close" onclick="cerrarModal()">✕</button>
    <h3 id="modal-title">Detalle del pedido</h3>
    <div id="modal-body"></div>
  </div>
</div>

<script>
async function verDetalle(id) {
  try {
    const res = await fetch('pedido_detalle.php?id=' + id);
    const data = await res.json();
    
    if (!data.success && data.error) { 
      alert(data.error); 
      return; 
    }
    
    const p = data.pedido;
    const items = data.items;
    const ordenCodigo = p.numero_orden || ('#' + String(p.id).padStart(4, '0'));
    
    let html = `<div style="color:var(--muted);font-size:12px;margin-bottom:16px;">Mesa ${p.mesa_num} · ${new Date(p.creado_en).toLocaleString('es-MX')}</div>`;
    
    if (p.notas) {
      html += `<div style="background:rgba(232,184,109,.1);border-left:3px solid var(--accent);padding:8px 12px;margin-bottom:16px;border-radius:4px;font-size:12px;"><strong>Notas:</strong> ${p.notas}</div>`;
    }
    
    items.forEach(it => {
      html += `<div class="item-row"><span>${it.cantidad}x ${it.nombre}</span><span>$${parseFloat(it.subtotal).toFixed(2)}</span></div>`;
    });
    
    html += `<div class="total-line"><span>Total</span><span>$${parseFloat(p.total).toFixed(2)}</span></div>`;
    
    document.getElementById('modal-title').textContent = 'Orden ' + ordenCodigo;
    document.getElementById('modal-body').innerHTML = html;
    document.getElementById('modal').classList.add('show');
  } catch (err) {
    alert('Ocurrió un error al cargar el detalle del pedido.');
  }
}

function cerrarModal() {
  document.getElementById('modal').classList.remove('show');
}

document.getElementById('modal').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});
</script>
</body>
</html>