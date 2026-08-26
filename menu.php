<?php
require_once 'auth_check.php';
$db = getDB();

// Manejo de peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // Cambiar disponibilidad (Switch ON/OFF)
    if ($action === 'toggle_producto') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE productos SET disponible = NOT disponible WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Agregar nuevo producto
    if ($action === 'add_producto') {
        $nombre = sanitize($_POST['nombre'] ?? '');
        $desc   = sanitize($_POST['descripcion'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        $cat_id = (int)($_POST['categoria_id'] ?? 0);

        if ($nombre && $precio > 0 && $cat_id) {
            $db->prepare("INSERT INTO productos (categoria_id, nombre, descripcion, precio, disponible) VALUES (?,?,?,?,1)")
               ->execute([$cat_id, $nombre, $desc, $precio]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Datos incompletos o inválidos']);
        }
        exit;
    }
    // ... resto de acciones
}

// Carga de datos para la vista
$categorias = $db->query("SELECT * FROM categorias ORDER BY orden")->fetchAll();
$productos = $db->query("SELECT p.*, c.nombre as cat_nombre FROM productos p JOIN categorias c ON c.id = p.categoria_id ORDER BY c.orden, p.nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menú – RestaurApp Admin</title>
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
.top-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.add-btn{padding:10px 20px;background:linear-gradient(135deg,#e8b86d,#c9956a);color:#0f0f0f;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;}
.table-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:10px 16px;font-size:11px;font-weight:500;letter-spacing:1px;text-transform:uppercase;color:var(--muted);background:rgba(255,255,255,.02);}
td{padding:11px 16px;border-top:1px solid var(--border);font-size:13px;vertical-align:middle;}
.toggle-btn{padding:4px 12px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-family:'DM Sans',sans-serif;font-weight:500;}
.toggle-btn.on{background:rgba(61,214,140,.15);color:#3dd68c;}
.toggle-btn.off{background:rgba(224,112,112,.1);color:#e07070;}
.del-btn{padding:4px 10px;border-radius:6px;border:1px solid rgba(224,112,112,.3);background:transparent;color:#e07070;font-size:12px;cursor:pointer;}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:200;align-items:center;justify-content:center;}
.modal-overlay.show{display:flex;}
.modal{background:#1c1c1c;border:1px solid #2a2a2a;border-radius:16px;padding:28px;max-width:440px;width:90%;}
.modal h3{font-family:'Playfair Display',serif;font-size:18px;margin-bottom:20px;}
.field{margin-bottom:16px;}
label{display:block;font-size:11px;font-weight:500;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
input[type=text],input[type=number],textarea,select{width:100%;background:#111;border:1px solid var(--border);border-radius:8px;padding:10px 14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;}
input:focus,select:focus,textarea:focus{border-color:var(--accent);}
select option{background:#1c1c1c;}
.modal-btns{display:flex;gap:10px;margin-top:8px;}
.modal-save{flex:1;padding:10px;background:linear-gradient(135deg,#e8b86d,#c9956a);color:#0f0f0f;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;}
.modal-cancel{padding:10px 16px;border:1px solid var(--border);background:transparent;color:var(--muted);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;cursor:pointer;}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo"><div class="name">🍽️ RestaurApp</div><div class="role">Administrador</div></div>
  <nav class="nav">
    <a class="nav-item" href="dashboard.php"><span>📊</span> Dashboard</a>
    <a class="nav-item" href="pedidos.php"><span>📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span>🪑</span> Mesas & QR</a>
    <a class="nav-item active" href="menu.php"><span>🍽️</span> Menú</a>
  </nav>
  <div class="sidebar-bottom"><a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a></div>
</aside>
<main class="main">
  <div class="top-row">
    <div class="page-title">🍽️ Gestión del Menú</div>
    <button class="add-btn" onclick="showAddModal()">+ Agregar producto</button>
  </div>
  <div class="table-card">
    <table>
      <thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Estado</th><th></th></tr></thead>
      <tbody id="prod-table">
        <?php foreach ($productos as $p): ?>
        <tr id="row-<?= $p['id'] ?>">
          <td>
            <div style="font-weight:500;"><?= htmlspecialchars($p['nombre']) ?></div>
            <?php if ($p['descripcion']): ?>
              <div style="color:var(--muted);font-size:12px;margin-top:2px;"><?= htmlspecialchars($p['descripcion']) ?></div>
            <?php endif; ?>
          </td>
          <td style="color:var(--muted);"><?= htmlspecialchars($p['cat_nombre']) ?></td>
          <td>$<?= number_format($p['precio'], 2) ?></td>
          <td>
            <button class="toggle-btn <?= $p['disponible'] ? 'on' : 'off' ?>" 
                    onclick="toggleProducto(<?= $p['id'] ?>, this)">
              <?= $p['disponible'] ? '✓ Disponible' : '✗ No disp.' ?>
            </button>
          </td>
          <td>
            <button class="del-btn" onclick="eliminarProducto(<?= $p['id'] ?>)">Eliminar</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <h3>➕ Agregar producto</h3>
    <div class="field">
      <label>Nombre</label>
      <input type="text" id="nuevo-nombre" placeholder="Ej: Tacos de suadero">
    </div>
    <div class="field">
      <label>Descripción (opcional)</label>
      <textarea id="nuevo-desc" rows="2" placeholder="Breve descripción del platillo"></textarea>
    </div>
    <div class="field">
      <label>Precio</label>
      <input type="number" id="nuevo-precio" placeholder="0.00" step="0.01" min="0">
    </div>
    <div class="field">
      <label>Categoría</label>
      <select id="nuevo-cat">
        <?php foreach ($categorias as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="modal-btns">
      <button class="modal-cancel" onclick="hideModal()">Cancelar</button>
      <button class="modal-save" onclick="guardarProducto()">Guardar producto</button>
    </div>
  </div>
</div>

<script>
function showAddModal() { document.getElementById('add-modal').classList.add('show'); }
function hideModal()    { document.getElementById('add-modal').classList.remove('show'); }

async function toggleProducto(id, btn) {
  const fd = new FormData();
  fd.append('action', 'toggle_producto');
  fd.append('id', id);
  const res = await fetch('menu.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    btn.classList.toggle('on');
    btn.classList.toggle('off');
    btn.textContent = btn.classList.contains('on') ? '✓ Disponible' : '✗ No disp.';
  }
}

async function eliminarProducto(id) {
  if (!confirm('¿Eliminar este producto?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_producto');
  fd.append('id', id);
  const res = await fetch('menu.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    document.getElementById('row-' + id)?.remove();
  }
}

async function guardarProducto() {
  const nombre = document.getElementById('nuevo-nombre').value.trim();
  const desc   = document.getElementById('nuevo-desc').value.trim();
  const precio = document.getElementById('nuevo-precio').value;
  const cat_id = document.getElementById('nuevo-cat').value;

  if (!nombre || !precio) { alert('Completa nombre y precio'); return; }

  const fd = new FormData();
  fd.append('action', 'add_producto');
  fd.append('nombre', nombre);
  fd.append('descripcion', desc);
  fd.append('precio', precio);
  fd.append('categoria_id', cat_id);

  const res = await fetch('menu.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    hideModal();
    location.reload();
  } else {
    alert(data.error);
  }
}
</script>
</body>
</html>