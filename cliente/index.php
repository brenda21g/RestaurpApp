<?php
require_once __DIR__ . '/../config/config.php';

if (isset($_GET['mesa']) && !empty($_GET['mesa'])) {
    $_SESSION['mesa_token'] = sanitize($_GET['mesa']);
}

if (!isset($_SESSION['cliente_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['mesa_token'])) {
    header('Location: escanear_mesa.php');
    exit;
}

$db = getDB();
$cliente_id = (int)$_SESSION['cliente_id'];

// Obtener datos precargados del cliente actual
$stmtC = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmtC->execute([$cliente_id]);
$cliente_actual = $stmtC->fetch(PDO::FETCH_ASSOC);

// Inicializar el historial de movimientos en sesión si no existe
if (!isset($_SESSION['historial_movimientos'])) {
    $_SESSION['historial_movimientos'] = [
        ['fecha' => '2026-11-15 14:30', 'tipo' => 'Recarga Monedero', 'monto' => 500.00, 'reembolso' => 0.00, 'detalle' => 'SPEI BBVA (Inicial)'],
        ['fecha' => '2026-11-10 20:15', 'tipo' => 'Pago de Pedido', 'monto' => -250.00, 'reembolso' => 2.50, 'detalle' => 'Consumo en Mesa']
    ];
}

// CÁLCULO REAL Y DINÁMICO DEL SALDO BASADO EN EL HISTORIAL DE MOVIMIENTOS
$saldo_calculado = 0.00;
foreach ($_SESSION['historial_movimientos'] as $mov) {
    $saldo_calculado += floatval($mov['monto']);
    if (isset($mov['reembolso'])) {
        $saldo_calculado += floatval($mov['reembolso']);
    }
}
$_SESSION['saldo_monedero'] = $saldo_calculado;

// Obtener impuesto activo configurado por el administrador
$stmtImp = $db->query("SELECT * FROM configuracion_impuestos WHERE activo = 1 LIMIT 1");
$impuesto_config = $stmtImp->fetch(PDO::FETCH_ASSOC);
$porcentaje_iva = $impuesto_config ? floatval($impuesto_config['porcentaje']) : 16.00;

$stmtMesa = $db->prepare("SELECT * FROM mesas WHERE qr_token = ? AND activa = 1");
$stmtMesa->execute([$_SESSION['mesa_token']]);
$mesa = $stmtMesa->fetch(PDO::FETCH_ASSOC);

if (!$mesa) {
    unset($_SESSION['mesa_token']);
    header('Location: escanear_mesa.php?error=mesa_invalida');
    exit;
}

$categorias = $db->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY orden ASC")->fetchAll(PDO::FETCH_ASSOC);
$todos_productos = $db->query("SELECT * FROM productos WHERE disponible = 1 ORDER BY categoria_id, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$productos_por_cat = [];
foreach ($todos_productos as $p) {
    $productos_por_cat[$p['categoria_id']][] = $p;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Mesa <?= htmlspecialchars($mesa['numero']) ?> – RestaurApp</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #faf8f5; --surface: #ffffff; --border: #ede8e0;
  --accent: #c8502a; --accent2: #e8733a; --text: #1a1510; --muted: #8a7a6a;
  --card-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; min-height: 100vh; font-size: 14px; padding-bottom: 100px; }
.header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 16px; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 8px rgba(0,0,0,0.05); }
.header-inner { display: flex; align-items: center; justify-content: space-between; max-width: 600px; margin: 0 auto; }
.logo { display: flex; align-items: center; gap: 10px; }
.logo-text { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--accent); }
.mesa-tag { background: rgba(200,80,42,0.1); color: var(--accent); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600; }
.user-btn { text-decoration: none; font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 4px; }
.tab-nav { background: var(--surface); border-bottom: 1px solid var(--border); display: flex; max-width: 600px; margin: 0 auto; position: sticky; top: 57px; z-index: 99; overflow-x: auto; }
.tab-btn { flex: 1; padding: 12px 6px; text-align: center; font-size: 11px; font-weight: 500; color: var(--muted); cursor: pointer; border: none; background: none; border-bottom: 2px solid transparent; white-space: nowrap; font-family: 'DM Sans', sans-serif; }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
.page { display: none; max-width: 600px; margin: 0 auto; padding: 16px; }
.page.active { display: block; }
.cat-filter { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 16px; scrollbar-width: none; }
.cat-filter::-webkit-scrollbar { display: none; }
.cat-chip { flex-shrink: 0; padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border); background: var(--surface); font-size: 12px; font-weight: 500; cursor: pointer; color: var(--muted); }
.cat-chip.active { background: var(--accent); color: white; border-color: var(--accent); }
.product-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; box-shadow: var(--card-shadow); }
.prod-emoji { font-size: 32px; width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background: var(--bg); border-radius: 10px; flex-shrink: 0; }
.prod-info { flex: 1; }
.prod-name { font-weight: 600; font-size: 14px; margin-bottom: 3px; }
.prod-desc { font-size: 12px; color: var(--muted); margin-bottom: 6px; }
.prod-price { font-size: 15px; font-weight: 600; color: var(--accent); }
.add-btn { width: 36px; height: 36px; border-radius: 50%; background: var(--accent); color: white; border: none; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.cart-empty { text-align: center; padding: 60px 20px; color: var(--muted); }
.cart-item { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; }
.qty-control { display: flex; align-items: center; gap: 10px; }
.qty-btn { width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--border); background: var(--bg); font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.cart-total { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-top: 4px; margin-bottom: 12px; }
.cart-total-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px; color: var(--muted); }
.cart-total-row.final { font-size: 17px; font-weight: 600; color: var(--text); border-top: 1px solid var(--border); padding-top: 10px; margin-top: 6px; }
.order-btn { width: 100%; padding: 15px; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 10px; }
.btn-secondary { background: #e07070; color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; cursor: pointer; margin-bottom: 12px; display:inline-block;}
.cart-float { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--accent); color: white; border: none; border-radius: 24px; padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 20px rgba(200,80,42,0.4); display: flex; align-items: center; gap: 10px; z-index: 200; }
.cart-float.hidden { display: none; }
.success-overlay, .modal-overlay, .alert-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 400; align-items: center; justify-content: center; }
.success-overlay.show, .modal-overlay.show, .alert-overlay.show { display: flex; }
.success-box, .modal-box, .alert-box { background: white; border-radius: 20px; padding: 36px 28px; text-align: center; max-width: 320px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.modal-btn-row { display: flex; gap: 10px; margin-top: 20px; }
.modal-btn { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; font-size: 14px; }
.btn-cancelar { background: #ede8e0; color: var(--text); }
.btn-aceptar { background: #e07070; color: white; }
.btn-ok { background: var(--accent); color: white; width: 100%; margin-top: 15px; }
.movimiento-item { background: var(--bg); padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; text-align: left; }
</style>
</head>
<body>

<div class="header">
  <div class="header-inner">
    <div class="logo">
      <span style="font-size:22px;">🍽️</span>
      <div class="logo-text">RestaurApp</div>
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
      <a href="cuenta.php" class="user-btn">👤 <?= htmlspecialchars(explode(' ', $cliente_actual['nombre'])[0]) ?></a>
      <div class="mesa-tag">Mesa <?= htmlspecialchars($mesa['numero']) ?></div>
    </div>
  </div>
</div>

<div class="tab-nav">
  <button class="tab-btn active" onclick="showPage('menu', this)">🍽️ Menú</button>
  <button class="tab-btn" onclick="showPage('carrito', this)">🛒 Carrito <span id="cart-tab-count"></span></button>
  <button class="tab-btn" onclick="showPage('checkout', this)">💳 Pagar / Checkout</button>
  <button class="tab-btn" onclick="showPage('estado', this)">📋 Estado</button>
  <button class="tab-btn" onclick="showPage('monedero', this)">💰 Monedero</button>
</div>

<!-- MENÚ -->
<div class="page active" id="page-menu">
  <div class="cat-filter">
    <button class="cat-chip active" onclick="filterCat('todas', this)">🍽️ Todos</button>
    <?php foreach ($categorias as $cat): ?>
      <button class="cat-chip" onclick="filterCat(<?= $cat['id'] ?>, this)"><?= $cat['icono'] ?? '🍽️' ?> <?= htmlspecialchars($cat['nombre']) ?></button>
    <?php endforeach; ?>
  </div>

  <div id="products-container">
    <?php foreach ($categorias as $cat): ?>
      <?php if (!empty($productos_por_cat[$cat['id']])): ?>
      <div class="cat-section" data-cat="<?= $cat['id'] ?>">
        <?php foreach ($productos_por_cat[$cat['id']] as $prod): 
          $emoji = $cat['icono'] ?? '🍴';
        ?>
        <div class="product-card">
          <div class="prod-emoji"><?= $emoji ?></div>
          <div class="prod-info">
            <div class="prod-name"><?= htmlspecialchars($prod['nombre']) ?></div>
            <div class="prod-desc"><?= htmlspecialchars($prod['descripcion'] ?? '') ?></div>
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
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
    <h3 style="font-size:16px;">Mi Carrito</h3>
    <button class="btn-secondary" onclick="abrirModalVaciar()">🗑️ Vaciar carrito</button>
  </div>
  <div id="cart-container"></div>
</div>

<!-- CHECKOUT Y PAGO -->
<div class="page" id="page-checkout">
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px;">
    <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:14px;">Finalizar Pedido y Pago</h3>
    
    <div style="margin-bottom:14px;">
      <label style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Datos de entrega / Cliente (Precargados)</label>
      <input type="text" id="chk-nombre" value="<?= htmlspecialchars($cliente_actual['nombre']) ?>" readonly style="width:100%; padding:10px; margin-top:4px; background:var(--bg); border:1px solid var(--border); border-radius:8px;">
    </div>

    <div style="margin-bottom:14px;">
      <label style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Método de Pago</label>
      <select id="metodo-pago" onchange="cambiarMetodoPago()" style="width:100%; padding:10px; margin-top:4px; background:var(--bg); border:1px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif;">
        <option value="monedero">Monedero (Saldo actual: $<span id="lbl-saldo-monedero"><?= number_format($_SESSION['saldo_monedero'], 2) ?></span>)</option>
        <option value="efectivo">Efectivo (Pago en Mesa)</option>
        <option value="tarjeta">Tarjeta de Crédito / Débito</option>
        <option value="transferencia">Transferencia Bancaria</option>
        <option value="4pagos">Esquema de 4 Pagos (Crédito)</option>
      </select>
    </div>

    <div id="contenedor-datos-pago" style="margin-bottom:14px;"></div>

    <div class="cart-total" style="margin-top:10px;">
      <div class="cart-total-row"><span>Subtotal:</span><span id="res-subtotal">$0.00</span></div>
      <div class="cart-total-row"><span>Impuestos (<?= $porcentaje_iva ?>% IVA):</span><span id="res-iva">$0.00</span></div>
      <div class="cart-total-row final"><span>Total a Pagar:</span><span id="res-total">$0.00</span></div>
    </div>

    <button class="order-btn" id="btn-procesar-pago" onclick="procesarPagoYPedido()">Confirmar y Pagar Pedido</button>
  </div>
</div>

<!-- ESTADO -->
<div class="page" id="page-estado">
  <div id="status-container"></div>
</div>

<!-- MONEDERO -->
<div class="page" id="page-monedero">
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:20px; text-align:center; margin-bottom:16px;">
    <div style="font-size:11px; color:var(--muted); text-transform:uppercase;">Saldo Disponible en Monedero</div>
    <div style="font-family:'Playfair Display',serif; font-size:32px; color:var(--accent); font-weight:bold; margin:6px 0;">$<span id="txt-saldo"><?= number_format($_SESSION['saldo_monedero'], 2) ?></span></div>
    <p style="font-size:12px; color:var(--muted);">Gana $1 de reembolso por cada $100 de compra en tus pedidos.</p>
  </div>

  <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:20px; margin-bottom:16px;">
    <h3 style="font-size:16px; margin-bottom:12px;">Recargar Monedero</h3>
    <div style="display:flex; gap:8px; margin-bottom:12px;">
      <button type="button" class="cat-chip" onclick="document.getElementById('monto-recarga').value=100">$100</button>
      <button type="button" class="cat-chip" onclick="document.getElementById('monto-recarga').value=200">$200</button>
      <button type="button" class="cat-chip" onclick="document.getElementById('monto-recarga').value=500">$500</button>
      <button type="button" class="cat-chip" onclick="document.getElementById('monto-recarga').value=1000">$1,000</button>
    </div>
    <input type="number" id="monto-recarga" placeholder="Otro monto ($)" style="width:100%; padding:10px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px;">
    <button class="order-btn" onclick="recargarMonedero()">Generar Solicitud de Recarga</button>
    
    <div id="resultado-recarga" style="margin-top: 16px;"></div>
  </div>

  <!-- HISTORIAL DE MOVIMIENTOS -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:20px;">
    <h3 style="font-size:16px; margin-bottom:12px;">📋 Historial de Movimientos</h3>
    <div id="lista-movimientos" style="max-height: 250px; overflow-y: auto;">
      <?php foreach (array_reverse($_SESSION['historial_movimientos']) as $mov): ?>
        <div class="movimiento-item">
          <div>
            <b><?= htmlspecialchars($mov['tipo']) ?></b><br>
            <small style="color:var(--muted);"><?= htmlspecialchars($mov['detalle']) ?> – <?= htmlspecialchars($mov['fecha']) ?></small>
          </div>
          <div style="text-align:right;">
            <span style="font-weight:bold; color:<?= floatval($mov['monto']) >= 0 ? '#2d8a56' : '#c8502a' ?>;">
              <?= (floatval($mov['monto']) > 0 ? '+' : '') . '$' . number_format(floatval($mov['monto']), 2) ?>
            </span>
            <?php if (isset($mov['reembolso']) && floatval($mov['reembolso']) > 0): ?>
              <br><small style="color:#2d8a56; font-weight:bold;">+$<?= number_format(floatval($mov['reembolso']), 2) ?> cashback</small>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<button class="cart-float hidden" id="cart-float-btn" onclick="showPage('carrito', null)">
  🛒 Ver carrito <span id="cart-float-count">0</span>
</button>

<!-- MODAL DE CONFIRMACIÓN ELEGANTE -->
<div class="modal-overlay" id="modal-confirmacion">
  <div class="modal-box">
    <div style="font-size:40px; margin-bottom:10px;">⚠️</div>
    <h3 id="modal-titulo" style="font-size:16px; margin-bottom:8px;">¿Estás seguro?</h3>
    <p id="modal-mensaje" style="color:var(--muted); font-size:13px; margin-bottom:10px;">Confirma tu acción para continuar.</p>
    <div class="modal-btn-row">
      <button class="modal-btn btn-cancelar" onclick="cerrarModal()">Cancelar</button>
      <button class="modal-btn btn-aceptar" id="modal-btn-aceptar">Sí, eliminar</button>
    </div>
  </div>
</div>

<!-- MODAL DE ALERTAS PERSONALIZADAS -->
<div class="alert-overlay" id="modal-alerta">
  <div class="alert-box">
    <div id="alert-icon" style="font-size:40px; margin-bottom:10px;">🔔</div>
    <h3 id="alert-titulo" style="font-size:16px; margin-bottom:8px;">Aviso</h3>
    <p id="alert-mensaje" style="color:var(--muted); font-size:13px; margin-bottom:10px;"></p>
    <button class="modal-btn btn-ok" onclick="cerrarAlerta()">Entendido</button>
  </div>
</div>

<div class="success-overlay" id="success-overlay">
  <div class="success-box">
    <div style="font-size:56px; margin-bottom:12px;">🎉</div>
    <h2>¡Compra realizada con éxito!</h2>
    <p id="success-msg-det">Tu orden ha sido registrada. Hemos enviado el comprobante a tu correo.</p>
    <button class="order-btn" style="margin-top:20px;" onclick="cerrarExitoYVerEstado()">Ver mi pedido</button>
  </div>
</div>

<script>
const MESA_ID = <?= (int)$mesa['id'] ?>;
const PORCENTAJE_IVA = <?= $porcentaje_iva ?>;
let cart = [];
let saldoMonederoCliente = <?= floatval($_SESSION['saldo_monedero']) ?>;

function addToCart(id, nombre, precio, emoji) {
  const existing = cart.find(i => i.id === id);
  if (existing) {
    existing.qty++;
  } else {
    cart.push({ id, nombre, precio, emoji, qty: 1 });
  }
  updateCartUI();
}

function updateQty(id, delta) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) {
    abrirModalEliminarItem(id);
    item.qty = 1; 
  } else {
    updateCartUI();
  }
}

function abrirModalVaciar() {
  document.getElementById('modal-titulo').textContent = '¿Vaciar carrito?';
  document.getElementById('modal-mensaje').textContent = 'Se eliminarán todos los productos agregados.';
  const btnAceptar = document.getElementById('modal-btn-aceptar');
  btnAceptar.onclick = function() {
    cart = [];
    updateCartUI();
    cerrarModal();
  };
  document.getElementById('modal-confirmacion').classList.add('show');
}

function abrirModalEliminarItem(id) {
  document.getElementById('modal-titulo').textContent = '¿Eliminar producto?';
  document.getElementById('modal-mensaje').textContent = '¿Deseas remover este artículo del carrito?';
  const btnAceptar = document.getElementById('modal-btn-aceptar');
  btnAceptar.onclick = function() {
    cart = cart.filter(i => i.id !== id);
    updateCartUI();
    cerrarModal();
  };
  document.getElementById('modal-confirmacion').classList.add('show');
}

function cerrarModal() {
  document.getElementById('modal-confirmacion').classList.remove('show');
}

function mostrarAviso(titulo, mensaje, icono = '⚠️') {
  document.getElementById('alert-titulo').textContent = titulo;
  document.getElementById('alert-mensaje').textContent = mensaje;
  document.getElementById('alert-icon').textContent = icono;
  document.getElementById('modal-alerta').classList.add('show');
}

function cerrarAlerta() {
  document.getElementById('modal-alerta').classList.remove('show');
}

function vaciarCarritoConfirmar() {
  abrirModalVaciar();
}

function eliminarItem(id) {
  abrirModalEliminarItem(id);
}

function updateCartUI() {
  const subtotal = cart.reduce((s, i) => s + i.precio * i.qty, 0);
  const iva = subtotal * (PORCENTAJE_IVA / 100);
  const total = subtotal + iva;
  const count = cart.reduce((s, i) => s + i.qty, 0);

  document.getElementById('cart-tab-count').textContent = count > 0 ? `(${count})` : '';
  const fb = document.getElementById('cart-float-btn');
  if (count > 0) {
    fb.classList.remove('hidden');
    document.getElementById('cart-float-count').textContent = count;
  } else {
    fb.classList.add('hidden');
  }

  document.getElementById('res-subtotal').textContent = '$' + subtotal.toFixed(2);
  document.getElementById('res-iva').textContent = '$' + iva.toFixed(2);
  document.getElementById('res-total').textContent = '$' + total.toFixed(2);

  const container = document.getElementById('cart-container');
  if (cart.length === 0) {
    container.innerHTML = `<div class="cart-empty"><p>Tu carrito está vacío.</p></div>`;
    return;
  }

  container.innerHTML = cart.map(item => `
    <div class="cart-item">
      <span style="font-size:24px;">${item.emoji}</span>
      <div style="flex:1;">
        <div style="font-weight:600; font-size:14px;">${item.nombre}</div>
        <div style="color:var(--muted); font-size:12px;">$${(item.precio * item.qty).toFixed(2)}</div>
      </div>
      <div class="qty-control">
        <button class="qty-btn" onclick="updateQty(${item.id}, -1)">−</button>
        <span style="font-weight:600; min-width:20px; text-align:center;">${item.qty}</span>
        <button class="qty-btn" onclick="updateQty(${item.id}, 1)">+</button>
        <button onclick="eliminarItem(${item.id})" style="background:none; border:none; color:#e07070; cursor:pointer; font-size:16px; margin-left:6px;" title="Eliminar">🗑️</button>
      </div>
    </div>
  `).join('') + `
    <button class="order-btn" onclick="showPage('checkout', null)">Ir al Checkout y Pagar ($${total.toFixed(2)})</button>
  `;
}

function cambiarMetodoPago() {
  const metodo = document.getElementById('metodo-pago').value;
  const contenedor = document.getElementById('contenedor-datos-pago');
  const btnProcesar = document.getElementById('btn-procesar-pago');
  
  if (metodo === 'efectivo') {
    btnProcesar.textContent = 'Confirmar';
    contenedor.innerHTML = `
      <div style="background:var(--bg); padding:12px; border-radius:8px; border:1px solid var(--border); font-size:13px;">
        💵 <b>Pago en Efectivo:</b> Al confirmar, tu pedido se enviará al mesero indicando que pagarás en efectivo en la mesa. El estado cambiará a <i>Esperando confirmación de pago</i>.
      </div>`;
  } else {
    btnProcesar.textContent = 'Confirmar y Pagar Pedido';
  }

  if (metodo === 'tarjeta') {
    contenedor.innerHTML = `
      <div style="background:var(--bg); padding:12px; border-radius:8px; border:1px solid var(--border);">
        <label style="font-size:10px; color:var(--muted);">Número de Tarjeta</label>
        <input type="text" placeholder="4532 •••• •••• 8920" style="width:100%; padding:8px; margin:4px 0 10px 0; border:1px solid var(--border); border-radius:6px;">
        <div style="display:flex; gap:8px;">
          <div><label style="font-size:10px; color:var(--muted);">Vencimiento</label><input type="text" placeholder="MM/AA" style="width:100%; padding:8px; margin-top:4px; border:1px solid var(--border); border-radius:6px;"></div>
          <div><label style="font-size:10px; color:var(--muted);">CVV (No se guarda)</label><input type="password" placeholder="123" maxlength="4" style="width:100%; padding:8px; margin-top:4px; border:1px solid var(--border); border-radius:6px;"></div>
        </div>
      </div>`;
  } else if (metodo === 'transferencia') {
    contenedor.innerHTML = `
      <div style="background:var(--bg); padding:12px; border-radius:8px; border:1px solid var(--border); font-size:13px;">
        <b>Datos para transferir:</b><br>
        Banco: BBVA México<br>
        CLABE: <code>123456789012345678</code><br>
        Beneficiario: RestaurApp S.A. de C.V.
      </div>`;
  } else if (metodo === '4pagos') {
    contenedor.innerHTML = `
      <div style="background:var(--bg); padding:12px; border-radius:8px; border:1px solid var(--border); font-size:13px;">
        💳 Esquema de Crédito: Tu pago se diferirá automáticamente en 4 mensualidades quincenales sin intereses.
      </div>`;
  } else if (metodo === 'monedero') {
    contenedor.innerHTML = ``;
  }
}

async function procesarPagoYPedido() {
  if (cart.length === 0) {
    mostrarAviso('Carrito Vacío', 'Agrega productos a tu carrito antes de realizar el pago.', '🛒');
    return;
  }
  
  const subtotal = cart.reduce((s, i) => s + i.precio * i.qty, 0);
  const iva = subtotal * (PORCENTAJE_IVA / 100);
  const total = subtotal + iva;
  const metodo = document.getElementById('metodo-pago').value;

  if (metodo === 'monedero' && saldoMonederoCliente < total) {
    mostrarAviso('Saldo Insuficiente', 'No cuentas con suficiente saldo en tu monedero para cubrir este total. Recarga o selecciona otro método.', '💳');
    return;
  }

  let reembolso = total >= 100 ? Math.floor(total / 100) : 0;
  let estadoPedido = (metodo === 'efectivo') ? 'esperando_confirmacion_pago' : 'pagado';

  try {
    const res = await fetch('../api/crear_pedido.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mesa_id: MESA_ID,
        metodo_pago: metodo,
        subtotal: subtotal,
        impuestos: iva,
        total: total,
        estado: estadoPedido,
        items: cart.map(i => ({ producto_id: i.id, cantidad: i.qty, precio_unitario: i.precio }))
      })
    });
    const data = await res.json();

    if (data.success) {
      if (metodo === 'monedero') {
        actualizarServidorMovimiento('Pago de Pedido (#' + data.orden_codigo + ')', -total, reembolso);
      } else if (metodo === 'efectivo') {
        actualizarServidorMovimiento('Pedido en Efectivo (Pendiente)', 0, 0);
      } else {
        actualizarServidorMovimiento('Pago Tarjeta/SPEI (#' + data.orden_codigo + ')', -total, reembolso);
      }

      cart = [];
      updateCartUI();
      
      if (metodo === 'efectivo') {
        document.getElementById('success-msg-det').innerHTML = `Tu pedido <b>${data.orden_codigo}</b> fue enviado.<br>Estado: <b style="color:var(--accent);">Esperando confirmación de pago por el mesero</b>.`;
      } else {
        document.getElementById('success-msg-det').innerHTML = `Orden <b>${data.orden_codigo}</b> registrada con éxito.<br>Pagado con: <b>${metodo.toUpperCase()}</b>.<br>${reembolso > 0 ? '💰 ¡Ganaste <b>$' + reembolso.toFixed(2) + '</b> de cashback en tu monedero!' : ''}`;
      }
      document.getElementById('success-overlay').classList.add('show');
    } else {
      mostrarAviso('Error de Pago', data.error || 'No se pudo procesar tu orden.', '❌');
    }
  } catch (e) {
    const tipoMov = (metodo === 'efectivo') ? 'Pedido en Efectivo (Pendiente)' : 'Pago Simulado';
    actualizarServidorMovimiento(tipoMov, metodo === 'monedero' ? -total : 0, reembolso);

    cart = [];
    updateCartUI();
    if (metodo === 'efectivo') {
      document.getElementById('success-msg-det').innerHTML = `Pedido enviado.<br>Estado: <b style="color:var(--accent);">Esperando confirmación de pago del mesero</b>.`;
    } else {
      document.getElementById('success-msg-det').innerHTML = `Orden simulada con éxito.<br>${reembolso > 0 ? '💰 ¡Ganaste <b>$' + reembolso.toFixed(2) + '</b> de cashback!' : ''}`;
    }
    document.getElementById('success-overlay').classList.add('show');
  }
}

function recargarMonedero() {
  const monto = parseFloat(document.getElementById('monto-recarga').value || 0);
  const contenedorResultado = document.getElementById('resultado-recarga');

  if (monto <= 0) {
    mostrarAviso('Monto Inválido', 'Por favor ingresa un monto mayor a $0 para generar tu solicitud de recarga.', '⚠️');
    return;
  }

  const refOxxo = '9876' + Math.floor(10000000 + Math.random() * 90000000);

  contenedorResultado.innerHTML = `
    <div style="background:#f4f7fa; border:1px solid #cbd5e1; border-radius:10px; padding:16px; font-size:13px; text-align:left;">
      <h4 style="color:#03143d; margin-bottom:8px; font-size:15px;">🧾 Recarga por <b>$${monto.toFixed(2)}</b></h4>
      <p style="color:#60708a; margin-bottom:10px;">Realiza tu pago con los siguientes datos:</p>
      
      <div style="background:#fff; padding:10px; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:8px;">
        <b>🏪 OXXO Pay:</b> Ref: <code style="color:#c8502a; font-weight:bold;">${refOxxo}</code>
      </div>
      <div style="background:#fff; padding:10px; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:12px;">
        <b>🏦 SPEI BBVA:</b> CLABE: <code>123456789012345678</code>
      </div>

      <button class="order-btn" style="padding:10px; font-size:13px; background:#2d8a56;" onclick="simularPagoAprobado(${monto})">
        🟢 Simular que el pago ya se realizó
      </button>
    </div>`;
  
  document.getElementById('monto-recarga').value = '';
}

function simularPagoAprobado(monto) {
  actualizarServidorMovimiento('Recarga de Monedero (Aprobada)', monto, 0);

  document.getElementById('resultado-recarga').innerHTML = `
    <div style="background:#e4f7fa; border:1px solid #c7eadc; color:#07543f; padding:12px; border-radius:8px; font-size:13px; text-align:center;">
      ✅ ¡Recarga acreditada con éxito! Se sumaron <b>$${monto.toFixed(2)}</b> a tu monedero.
    </div>`;
}

async function actualizarServidorMovimiento(tipo, monto, reembolso) {
  try {
    await fetch('../api/guardar_movimiento.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tipo, monto, reembolso })
    });
    setTimeout(() => { location.reload(); }, 1500);
  } catch(err) {
    location.reload();
  }
}

function showPage(id, btn) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  if (btn) btn.classList.add('active');
  if (id !== 'menu') document.getElementById('cart-float-btn').classList.add('hidden');
  else if (cart.length > 0) document.getElementById('cart-float-btn').classList.remove('hidden');
  if (id === 'estado') fetchStatus();
}

function filterCat(catId, btn) {
  document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.cat-section').forEach(s => {
    s.style.display = (catId === 'todas' || s.dataset.cat == catId) ? 'block' : 'none';
  });
}

function cerrarExitoYVerEstado() {
  document.getElementById('success-overlay').classList.remove('show');
  showPage('estado', null);
}

async function fetchStatus() {
  try {
    const res = await fetch('estado_pedido.php?mesa_id=' + MESA_ID);
    const data = await res.json();
    if (data.pedido) {
      renderStatus(data.pedido);
    } else {
      document.getElementById('status-container').innerHTML = `<div style="text-align:center; padding:40px; color:var(--muted);">Sin pedidos activos recientes.</div>`;
    }
  } catch(e) {}
}

function renderStatus(p) {
  let estadoTexto = p.estado.toUpperCase();
  let colorEstado = 'var(--accent)';
  if (p.estado === 'esperando_confirmacion_pago') {
    estadoTexto = 'ESPERANDO CONFIRMACIÓN DE PAGO (EFECTIVO EN MESA)';
  } else if (p.estado === 'pagado') {
    colorEstado = '#2d8a56';
  }

  document.getElementById('status-container').innerHTML = `
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; text-align:center;">
      <h3>Pedido #${p.id}</h3>
      <p style="color:${colorEstado}; font-weight:bold; font-size:15px; margin:8px 0;">Estado: ${estadoTexto}</p>
      <p style="font-size:13px; color:var(--muted);">Total: $${parseFloat(p.total).toFixed(2)}</p>
    </div>`;
}

updateCartUI();
</script>
</body>
</html>