<?php
// Corregimos la ruta al archivo de configuración
require_once 'config.php';
$db = getDB();

// Validar token de mesa
$mesa_token = $_GET['mesa'] ?? '';
if (!$mesa_token) {
    die('<h1 style="font-family:sans-serif;text-align:center;margin-top:40px;color:#666">⚠️ Acceso inválido. Escanea el código QR de tu mesa.</h1>');
}

$mesa_stmt = $db->prepare("SELECT * FROM mesas WHERE qr_token = ? AND activa = 1");
$mesa_stmt->execute([$mesa_token]);
$mesa = $mesa_stmt->fetch(PDO::FETCH_ASSOC);

if (!$mesa) {
    die('<h1 style="font-family:sans-serif;text-align:center;margin-top:40px;color:#666">⚠️ Mesa no encontrada o inactiva.</h1>');
}

// Cargar menú: Categorías y Productos
$categorias = $db->query("SELECT * FROM categorias WHERE activa=1 ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);
$productos_q = $db->query("SELECT p.*, c.nombre as cat_nombre FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.disponible=1 ORDER BY p.categoria_id, p.nombre")->fetchAll(PDO::FETCH_ASSOC);

$productos_por_cat = [];
foreach ($productos_q as $prod) {
    $productos_por_cat[$prod['categoria_id']][] = $prod;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Mesa <?= $mesa['numero'] ?> – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #faf8f5;
  --surface: #ffffff;
  --border: #ede8e0;
  --accent: #c8502a;
  --accent2: #e8733a;
  --text: #1a1510;
  --muted: #8a7a6a;
  --card-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  font-size: 14px;
  padding-bottom: 100px;
}

/* Header */
.header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 14px 16px;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 1px 8px rgba(0,0,0,0.05);
}

.header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 600px;
  margin: 0 auto;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
}

.logo-text {
  font-family: 'Playfair Display', serif;
  font-size: 18px;
  color: var(--accent);
}

.mesa-tag {
  background: rgba(200,80,42,0.1);
  color: var(--accent);
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
}

/* Tab nav */
.tab-nav {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  max-width: 600px;
  margin: 0 auto;
  position: sticky;
  top: 57px;
  z-index: 99;
}

.tab-btn {
  flex: 1;
  padding: 12px;
  text-align: center;
  font-size: 13px;
  font-weight: 500;
  color: var(--muted);
  cursor: pointer;
  border: none;
  background: none;
  border-bottom: 2px solid transparent;
  transition: all .2s;
  font-family: 'DM Sans', sans-serif;
}

.tab-btn.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}

/* Pages */
.page {
  display: none;
  max-width: 600px;
  margin: 0 auto;
  padding: 16px;
}

.page.active { display: block; }

/* Category filter */
.cat-filter {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
  margin-bottom: 16px;
  scrollbar-width: none;
}

.cat-filter::-webkit-scrollbar { display: none; }

.cat-chip {
  flex-shrink: 0;
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--surface);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all .15s;
  font-family: 'DM Sans', sans-serif;
  color: var(--muted);
}

.cat-chip.active {
  background: var(--accent);
  color: white;
  border-color: var(--accent);
}

/* Product cards */
.product-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: var(--card-shadow);
}

.prod-emoji {
  font-size: 32px;
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg);
  border-radius: 10px;
  flex-shrink: 0;
}

.prod-info { flex: 1; }

.prod-name {
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 3px;
}

.prod-desc {
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 6px;
  line-height: 1.4;
}

.prod-price {
  font-size: 15px;
  font-weight: 600;
  color: var(--accent);
}

.add-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--accent);
  color: white;
  border: none;
  font-size: 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: transform .15s;
}

.add-btn:active { transform: scale(.9); }

/* Cart */
.cart-empty {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}

.cart-empty .icon { font-size: 48px; margin-bottom: 12px; }

.cart-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.cart-item-name { flex: 1; font-weight: 500; font-size: 14px; }
.cart-item-price { color: var(--muted); font-size: 13px; margin-top: 2px; }

.qty-control {
  display: flex;
  align-items: center;
  gap: 10px;
}

.qty-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid var(--border);
  background: var(--bg);
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .15s;
}

.qty-btn:hover { border-color: var(--accent); color: var(--accent); }

.qty-num {
  font-weight: 600;
  font-size: 15px;
  min-width: 20px;
  text-align: center;
}

.cart-total {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  margin-top: 4px;
  margin-bottom: 12px;
}

.cart-total-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-bottom: 6px;
  color: var(--muted);
}

.cart-total-row.final {
  font-size: 17px;
  font-weight: 600;
  color: var(--text);
  border-top: 1px solid var(--border);
  padding-top: 10px;
  margin-top: 6px;
  margin-bottom: 0;
}

.notas-input {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  color: var(--text);
  resize: none;
  margin-bottom: 12px;
  outline: none;
}

.notas-input:focus { border-color: var(--accent); }

.order-btn {
  width: 100%;
  padding: 15px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: white;
  border: none;
  border-radius: 12px;
  font-family: 'DM Sans', sans-serif;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity .15s;
}

.order-btn:hover { opacity: .9; }
.order-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Status page */
.status-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 12px;
  text-align: center;
}

.status-icon { font-size: 48px; margin-bottom: 12px; }
.status-title { font-family: 'Playfair Display', serif; font-size: 20px; margin-bottom: 6px; }
.status-sub { color: var(--muted); font-size: 13px; }

.timer-display {
  background: rgba(200,80,42,0.08);
  border: 1px solid rgba(200,80,42,0.2);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  margin-bottom: 12px;
}

.timer-num {
  font-family: 'Playfair Display', serif;
  font-size: 36px;
  color: var(--accent);
}

.timer-label { font-size: 12px; color: var(--muted); margin-top: 4px; }

.total-display {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.total-display .label { color: var(--muted); font-size: 13px; }
.total-display .amount { font-size: 20px; font-weight: 600; color: var(--accent); }

/* Floating cart btn */
.cart-float {
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--accent);
  color: white;
  border: none;
  border-radius: 24px;
  padding: 12px 24px;
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(200,80,42,0.4);
  display: flex;
  align-items: center;
  gap: 10px;
  z-index: 200;
  transition: transform .15s;
}

.cart-float:active { transform: translateX(-50%) scale(.97); }
.cart-float.hidden { display: none; }

.cart-badge {
  background: white;
  color: var(--accent);
  border-radius: 50%;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
}

/* Success overlay */
.success-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.7);
  z-index: 400;
  align-items: center;
  justify-content: center;
}

.success-overlay.show { display: flex; }

.success-box {
  background: white;
  border-radius: 20px;
  padding: 36px 28px;
  text-align: center;
  max-width: 320px;
  width: 90%;
  animation: popIn .3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes popIn {
  from { transform: scale(.8); opacity: 0; }
  to   { transform: scale(1); opacity: 1; }
}

.success-box .big-icon { font-size: 56px; margin-bottom: 12px; }
.success-box h2 { font-family: 'Playfair Display', serif; font-size: 22px; color: #1a1510; margin-bottom: 8px; }
.success-box p { color: #8a7a6a; font-size: 13px; line-height: 1.5; }
.success-close-btn {
  margin-top: 20px;
  padding: 12px 28px;
  background: linear-gradient(135deg, #c8502a, #e8733a);
  color: white;
  border: none;
  border-radius: 10px;
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
</style>
</head>
<body>

<div class="header">
  <div class="header-inner">
    <div class="logo">
      <span style="font-size:22px;">🍽️</span>
      <div class="logo-text">RestaurApp</div>
    </div>
    <div class="mesa-tag">Mesa <?= $mesa['numero'] ?></div>
  </div>
</div>

<div class="tab-nav">
  <button class="tab-btn active" onclick="showPage('menu', this)">🍽️ Menú</button>
  <button class="tab-btn" onclick="showPage('carrito', this)">🛒 Carrito <span id="cart-tab-count"></span></button>
  <button class="tab-btn" onclick="showPage('estado', this)">📋 Mi pedido</button>
</div>

<!-- MENÚ -->
<div class="page active" id="page-menu">
  <div class="cat-filter">
    <button class="cat-chip active" onclick="filterCat('todas', this)">🍽️ Todos</button>
    <?php foreach ($categorias as $cat): ?>
      <button class="cat-chip" onclick="filterCat(<?= $cat['id'] ?>, this)"><?= $cat['icono'] ?> <?= htmlspecialchars($cat['nombre']) ?></button>
    <?php endforeach; ?>
  </div>

  <div id="products-container">
    <?php foreach ($categorias as $cat): ?>
      <?php if (!empty($productos_por_cat[$cat['id']])): ?>
      <div class="cat-section" data-cat="<?= $cat['id'] ?>">
        <?php foreach ($productos_por_cat[$cat['id']] as $prod):
          // Emoji por categoría
          $emojis = [1=>'🥗', 2=>'🍽️', 3=>'🥤', 4=>'🍰'];
          $emoji = $emojis[$prod['categoria_id']] ?? '🍴';
        ?>
        <div class="product-card">
          <div class="prod-emoji"><?= $emoji ?></div>
          <div class="prod-info">
            <div class="prod-name"><?= htmlspecialchars($prod['nombre']) ?></div>
            <?php if ($prod['descripcion']): ?>
              <div class="prod-desc"><?= htmlspecialchars($prod['descripcion']) ?></div>
            <?php endif; ?>
            <div class="prod-price">$<?= number_format($prod['precio'], 2) ?></div>
          </div>
          <button class="add-btn" onclick="addToCart(<?= $prod['id'] ?>, '<?= addslashes($prod['nombre']) ?>', <?= $prod['precio'] ?>, '<?= $emoji ?>')">+</button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<!-- CARRITO -->
<div class="page" id="page-carrito">
  <div id="cart-container"></div>
</div>

<!-- ESTADO PEDIDO -->
<div class="page" id="page-estado">
  <div id="status-container"></div>
</div>

<!-- Floating cart button -->
<button class="cart-float hidden" id="cart-float-btn" onclick="showPage('carrito', null)">
  🛒 Ver carrito
  <span class="cart-badge" id="cart-float-count">0</span>
</button>

<!-- Success overlay -->
<div class="success-overlay" id="success-overlay">
  <div class="success-box">
    <div class="big-icon">🎉</div>
    <h2>¡Pedido enviado!</h2>
    <p>Tu pedido fue recibido por cocina. Te avisaremos cuando esté listo.</p>
    <button class="success-close-btn" onclick="closeSuccess()">Ver estado del pedido</button>
  </div>
</div>

<script>
const MESA_ID = <?= $mesa['id'] ?>;
const MESA_TOKEN = '<?= addslashes($mesa['qr_token']) ?>';
let cart = [];

// Recuperar el ID guardado al cargar la página


// =====================
// CARRITO
// =====================
function addToCart(id, nombre, precio, emoji) {
  const existing = cart.find(i => i.id === id);
  if (existing) {
    existing.qty++;
  } else {
    cart.push({ id, nombre, precio, emoji, qty: 1 });
  }
  updateCartUI();
  
  const btn = event.target;
  btn.style.transform = 'scale(1.3)';
  setTimeout(() => btn.style.transform = '', 200);
}

function updateQty(id, delta) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) cart = cart.filter(i => i.id !== id);
  updateCartUI();
}

function updateCartUI() {
  const total = cart.reduce((s, i) => s + i.precio * i.qty, 0);
  const count = cart.reduce((s, i) => s + i.qty, 0);

  const tabCount = document.getElementById('cart-tab-count');
  tabCount.textContent = count > 0 ? `(${count})` : '';

  const fb = document.getElementById('cart-float-btn');
  const fbc = document.getElementById('cart-float-count');
  if (count > 0 && document.getElementById('page-menu').classList.contains('active')) {
    fb.classList.remove('hidden');
    fbc.textContent = count;
  } else {
    fb.classList.add('hidden');
  }

  const container = document.getElementById('cart-container');
  if (cart.length === 0) {
    container.innerHTML = `
      <div class="cart-empty">
        <div class="icon">🛒</div>
        <p>Tu carrito está vacío.<br>Agrega platillos desde el menú.</p>
      </div>`;
    return;
  }

  let html = cart.map(item => `
    <div class="cart-item">
      <span style="font-size:28px;">${item.emoji}</span>
      <div style="flex:1;">
        <div class="cart-item-name">${item.nombre}</div>
        <div class="cart-item-price">$${(item.precio * item.qty).toFixed(2)}</div>
      </div>
      <div class="qty-control">
        <button class="qty-btn" onclick="updateQty(${item.id}, -1)">−</button>
        <span class="qty-num">${item.qty}</span>
        <button class="qty-btn" onclick="updateQty(${item.id}, 1)">+</button>
      </div>
    </div>
  `).join('');

  html += `
    <div class="cart-total">
      <div class="cart-total-row"><span>Subtotal (${count} items)</span><span>$${total.toFixed(2)}</span></div>
      <div class="cart-total-row final"><span>Total</span><span>$${total.toFixed(2)}</span></div>
    </div>
    <textarea class="notas-input" id="notas-input" placeholder="Notas especiales (alergias, sin picante...)" rows="2"></textarea>
    <button class="order-btn" onclick="enviarPedido()" id="order-btn">
      🛵 Enviar pedido a cocina – $${total.toFixed(2)}
    </button>
  `;

  container.innerHTML = html;
}

async function enviarPedido() {
  if (cart.length === 0) { alert('Tu carrito está vacío'); return; }

  const btn = document.getElementById('order-btn');
  btn.disabled = true;
  btn.textContent = 'Enviando...';

  const notas = document.getElementById('notas-input')?.value || '';

  try {
    const res = await fetch('crear_pedido.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mesa_id: MESA_ID,
        items: cart.map(i => ({ producto_id: i.id, cantidad: i.qty, precio_unitario: i.precio })),
        notas
      })
    });
    const data = await res.json();

    if (data.success) {
      currentPedidoId = data.pedido_id;
      // Guardar localmente en el navegador para que no se pierda al recargar
      localStorage.setItem('restaurapp_pedido_' + MESA_ID, currentPedidoId);

      cart = [];
      updateCartUI();
      document.getElementById('success-overlay').classList.add('show');
      vibrateDevice();
    } else {
      alert('Error al enviar: ' + (data.error || 'Intenta de nuevo'));
      btn.disabled = false;
      btn.textContent = '🛵 Enviar pedido a cocina';
    }
  } catch (e) {
    alert('Error de conexión con el servidor');
    btn.disabled = false;
    btn.textContent = '🛵 Enviar pedido a cocina';
  }
}

function vibrateDevice() {
  if ('vibrate' in navigator) navigator.vibrate([200, 100, 200]);
}

function closeSuccess() {
  document.getElementById('success-overlay').classList.remove('show');
  showPage('estado', null);
  fetchStatus();
}

// =====================
// ESTADO DEL PEDIDO
// =====================
async function fetchStatus() {
  // Ruta corregida: 'estado_pedido.php' en lugar de '../api/estado_pedido.php'
  let url = 'estado_pedido.php?';
  if (currentPedidoId) {
    url += 'pedido_id=' + currentPedidoId;
  } else {
    url += 'mesa_id=' + MESA_ID;
  }

  try {
    const res = await fetch(url);
    const data = await res.json();
    
    if (data.pedido) {
      currentPedidoId = data.pedido.id;
      localStorage.setItem('restaurapp_pedido_' + MESA_ID, currentPedidoId);
      renderStatus(data.pedido);

      if (data.pedido.estado === 'listo' || data.pedido.estado === 'entregado') {
        if (!window._notifiedReady) {
          window._notifiedReady = true;
          vibrateDevice();
          if (Notification.permission === 'granted') {
            new Notification('🍽️ ¡Tu pedido está listo!', { body: 'El mesero llevará tu pedido pronto.' });
          }
        }
      }
    } else {
      renderNoOrder();
    }
  } catch (e) {
    console.error(e);
  }
}

function renderNoOrder() {
  document.getElementById('status-container').innerHTML = `
    <div class="status-card">
      <div class="status-icon">🍽️</div>
      <div class="status-title">Sin pedido activo</div>
      <div class="status-sub">Cuando hagas un pedido desde el carrito, podrás ver su estado aquí.</div>
    </div>
  `;
}

function renderStatus(p) {
  const estados = {
    pendiente:  { icon: '⏳', title: 'Pedido recibido', sub: 'Tu pedido está en la fila de cocina.' },
    preparando: { icon: '🔥', title: '¡En preparación!', sub: 'Los cocineros están preparando tu pedido.' },
    listo:      { icon: '✅', title: '¡Tu pedido está listo!', sub: 'El mesero llevará tu pedido en un momento.' },
    entregado:  { icon: '🎉', title: '¡Buen provecho!', sub: 'Pedido entregado. ¡Disfruta tu comida!' }
  };

  const est = estados[p.estado] || estados['pendiente'];

  let timerHtml = '';
  if (p.tiempo_estimado && p.estado === 'preparando') {
    timerHtml = `
      <div class="timer-display">
        <div class="timer-num">~${p.tiempo_estimado} min</div>
        <div class="timer-label">Tiempo estimado de espera</div>
      </div>
    `;
  }

  const items = (p.items || []).map(it => `
    <div class="cart-item" style="padding:10px 14px;">
      <div style="flex:1;"><span class="cart-item-name">${it.cantidad}x ${it.nombre}</span></div>
      <span style="color:var(--muted);font-size:13px;">$${parseFloat(it.subtotal || (it.precio_unitario * it.cantidad)).toFixed(2)}</span>
    </div>
  `).join('');

  document.getElementById('status-container').innerHTML = `
    <div class="status-card">
      <div class="status-icon">${est.icon}</div>
      <div class="status-title">${est.title}</div>
      <div class="status-sub">${est.sub}</div>
    </div>
    ${timerHtml}
    <div class="total-display">
      <div class="label">Total de tu pedido</div>
      <div class="amount">$${parseFloat(p.total).toFixed(2)}</div>
    </div>
    ${items ? `<div style="margin-top:12px;">${items}</div>` : ''}
  `;
}

// =====================
// NAVEGACIÓN
// =====================
function showPage(id, btn) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  
  if (btn) {
    btn.classList.add('active');
  } else {
    const btns = document.querySelectorAll('.tab-btn');
    if (id === 'menu')    btns[0].classList.add('active');
    if (id === 'carrito') btns[1].classList.add('active');
    if (id === 'estado')  btns[2].classList.add('active');
  }

  if (id !== 'menu') document.getElementById('cart-float-btn').classList.add('hidden');
  else if (cart.length > 0) document.getElementById('cart-float-btn').classList.remove('hidden');

  if (id === 'estado') fetchStatus();
}

function filterCat(catId, btn) {
  document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');

  document.querySelectorAll('.cat-section').forEach(s => {
    if (catId === 'todas' || s.dataset.cat == catId) {
      s.style.display = 'block';
    } else {
      s.style.display = 'none';
    }
  });
}

if ('Notification' in window && Notification.permission === 'default') {
  Notification.requestPermission();
}

// Consultar actualización de estado cada 5 segundos automáticamente
setInterval(() => {
  if (document.getElementById('page-estado').classList.contains('active') || currentPedidoId) {
    fetchStatus();
  }
}, 5000);

// Cargar carrito e iniciar verificación de pedido existente al entrar
updateCartUI();
fetchStatus();
</script>
</body>
</html>