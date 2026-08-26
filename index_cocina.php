<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cocina – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #0a0a0f;
  --surface: #131318;
  --card: #1a1a22;
  --border: #25252f;
  --accent: #ff6b35;
  --accent2: #ff9a3c;
  --green: #3dd68c;
  --blue: #4db6ff;
  --text: #f0f0f5;
  --muted: #6a6a7a;
}

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  font-size: 14px;
}

.header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 16px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-title {
  font-family: 'Playfair Display', serif;
  font-size: 22px;
  color: var(--accent);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.count-badge {
  background: var(--accent);
  color: white;
  border-radius: 20px;
  padding: 4px 14px;
  font-size: 13px;
  font-weight: 600;
}

.live-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--green);
  animation: blink 1.5s infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: .3; }
}

.main {
  padding: 20px 24px;
}

.section-title {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 14px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

/* Kanban columns */
.kanban {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.kanban-col {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  min-height: 400px;
}

.col-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.col-title {
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.col-count {
  background: var(--border);
  color: var(--muted);
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
}

.col-count.active { background: rgba(255,107,53,0.2); color: var(--accent); }
.col-count.cooking { background: rgba(77,182,255,0.2); color: var(--blue); }
.col-count.done { background: rgba(61,214,140,0.2); color: var(--green); }

/* Order cards */
.order-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px;
  margin-bottom: 10px;
  position: relative;
  animation: slideIn .3s ease;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateY(-10px); }
  to   { opacity: 1; transform: translateY(0); }
}

.order-card.new-pulse {
  border-color: var(--accent);
  box-shadow: 0 0 0 2px rgba(255,107,53,0.2);
}

.order-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.order-num {
  font-weight: 600;
  font-size: 14px;
}

.mesa-tag {
  background: rgba(255,107,53,0.15);
  color: var(--accent);
  border-radius: 6px;
  padding: 2px 8px;
  font-size: 12px;
  font-weight: 500;
}

.order-time {
  font-size: 11px;
  color: var(--muted);
  margin-bottom: 10px;
}

.items-list {
  margin-bottom: 12px;
}

.item-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 4px 0;
  font-size: 13px;
}

.item-qty {
  background: var(--border);
  border-radius: 4px;
  padding: 1px 6px;
  font-size: 11px;
  font-weight: 600;
  color: var(--accent2);
  flex-shrink: 0;
}

.item-note {
  font-size: 11px;
  color: var(--muted);
  font-style: italic;
}

/* Timer input */
.timer-section {
  border-top: 1px solid var(--border);
  padding-top: 10px;
  display: flex;
  gap: 8px;
  align-items: center;
}

.timer-input {
  background: #111;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 6px 10px;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  width: 80px;
  outline: none;
}

.timer-input:focus { border-color: var(--accent); }

.timer-unit {
  color: var(--muted);
  font-size: 12px;
}

/* Action buttons */
.action-btn {
  width: 100%;
  padding: 8px;
  border-radius: 8px;
  border: none;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  margin-top: 8px;
  transition: all .15s;
}

.btn-start {
  background: linear-gradient(135deg, #e8b86d, #c9956a);
  color: #0f0f0f;
}

.btn-start:hover { opacity: .9; }

.btn-done {
  background: linear-gradient(135deg, #3dd68c, #2ab870);
  color: #0f0f0f;
}

.btn-done:hover { opacity: .9; }

.btn-delivering {
  background: linear-gradient(135deg, #4db6ff, #2a9fe0);
  color: #0f0f0f;
}

.estimated-time {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--blue);
  margin-top: 4px;
}

/* Sound notification */
.notif-toast {
  position: fixed;
  top: 80px;
  right: 20px;
  background: var(--accent);
  color: white;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  z-index: 300;
  display: none;
  animation: slideFromRight .3s ease;
}

@keyframes slideFromRight {
  from { transform: translateX(100px); opacity: 0; }
  to   { transform: translateX(0); opacity: 1; }
}

.empty-col {
  text-align: center;
  color: var(--muted);
  font-size: 13px;
  padding: 40px 0;
}

@media (max-width: 900px) {
  .kanban { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="header">
  <div style="display:flex;align-items:center;gap:12px;">
    <span style="font-size:24px;">👨‍🍳</span>
    <div class="header-title">Cocina</div>
  </div>
  <div class="header-right">
    <div class="live-indicator">
      <div class="live-dot"></div>
      Actualizando automáticamente
    </div>
    <div class="count-badge" id="pending-count">0 pendientes</div>
  </div>
</div>

<div class="main">
  <div class="kanban">
    <!-- Columna: Nuevos pedidos -->
    <div class="kanban-col">
      <div class="col-header">
        <div class="col-title">🔔 Nuevos pedidos</div>
        <span class="col-count active" id="count-pendiente">0</span>
      </div>
      <div id="col-pendiente"></div>
    </div>

    <!-- Columna: En preparación -->
    <div class="kanban-col">
      <div class="col-header">
        <div class="col-title">🔥 En preparación</div>
        <span class="col-count cooking" id="count-preparando">0</span>
      </div>
      <div id="col-preparando"></div>
    </div>

    <!-- Columna: Listos -->
    <div class="kanban-col">
      <div class="col-header">
        <div class="col-title">✅ Listos para entregar</div>
        <span class="col-count listo" id="count-listo">0</span>
      </div>
      <div id="col-listo"></div>
    </div>
  </div>
</div>

<div class="notif-toast" id="notif-toast">🔔 ¡Nuevo pedido recibido!</div>

<script>
let knownOrders = new Set();
let timerValues = {};

// Función para obtener pedidos corregida
async function fetchPedidos() {
  try {
    const res = await fetch('cocina_pedidos.php'); 
    const data = await res.json();
    
    // Se acepta 'data.pedidos' sin depender obligatoriamente de 'data.success'
    if (data.pedidos && Array.isArray(data.pedidos)) {
      renderPedidos(data.pedidos);
    } else if (data.success && data.pedidos) {
      renderPedidos(data.pedidos);
    }
  } catch (e) { 
    console.error('Error al cargar pedidos:', e); 
  }
}

// Procesa y distribuye los pedidos en las tres columnas
function renderPedidos(pedidos) {
  const pendientes = pedidos.filter(p => p.estado === 'pendiente');
  const preparando = pedidos.filter(p => p.estado === 'preparando');
  const listos = pedidos.filter(p => p.estado === 'listo');

  // Sonido y notificación de nuevo pedido
  let hasNew = false;
  pendientes.forEach(p => {
    if (!knownOrders.has(p.id)) {
      knownOrders.add(p.id);
      hasNew = true;
    }
  });

  if (hasNew && knownOrders.size > pendientes.length) {
    showToast('¡Nuevo pedido recibido!');
    playNotificationSound();
  } else {
    pendientes.forEach(p => knownOrders.add(p.id));
  }

  // Actualizar contadores
  document.getElementById('pending-count').textContent = `${pendientes.length} pendientes`;
  document.getElementById('count-pendiente').textContent = pendientes.length;
  document.getElementById('count-preparando').textContent = preparando.length;
  document.getElementById('count-listo').textContent = listos.length;

  // Renderizar columnas
  renderColumn('pendiente', pendientes);
  renderColumn('preparando', preparando);
  renderColumn('listo', listos);
}

function renderColumn(estado, pedidos) {
  const col = document.getElementById('col-' + estado);
  if (pedidos.length === 0) {
    col.innerHTML = '<div class="empty-col">Sin pedidos</div>';
    return;
  }

  col.innerHTML = pedidos.map(p => {
    const timerVal = timerValues[p.id] || (p.tiempo_estimado || '');
    const items = p.items.map(it => `
      <div class="item-row">
        <span class="item-qty">${it.cantidad}x</span>
        <div>
          <div>${it.nombre}</div>
          ${it.notas ? `<div class="item-note">${it.notas}</div>` : ''}
        </div>
      </div>
    `).join('');

    // Formatear hora de manera compatible con fechas SQL (reemplazando espacio por T)
    const fechaFormateable = p.creado_en ? p.creado_en.replace(' ', 'T') : new Date();
    const hora = new Date(fechaFormateable).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

    let actions = '';
    if (estado === 'pendiente') {
      actions = `
        <div class="timer-section">
          <input class="timer-input" type="number" id="timer-${p.id}" value="${timerVal}"
                 min="1" max="120" placeholder="min"
                 onchange="timerValues[${p.id}] = this.value">
          <span class="timer-unit">minutos</span>
        </div>
        <button class="action-btn btn-start" onclick="cambiarEstado(${p.id}, 'preparando')">
          🔥 Comenzar preparación
        </button>
      `;
    } else if (estado === 'preparando') {
      const eta = p.tiempo_estimado ? `<div class="estimated-time">⏱️ ~${p.tiempo_estimado} min estimados</div>` : '';
      actions = `
        ${eta}
        <button class="action-btn btn-done" onclick="cambiarEstado(${p.id}, 'listo')">
          ✅ Pedido listo
        </button>
      `;
    } else if (estado === 'listo') {
      actions = `
        <button class="action-btn btn-delivering" onclick="cambiarEstado(${p.id}, 'entregado')">
          🛵 Marcar como entregado
        </button>
      `;
    }

    return `
      <div class="order-card ${estado === 'pendiente' ? 'new-pulse' : ''}" id="card-${p.id}">
        <div class="order-top">
          <span class="order-num">${p.numero_orden}</span>
          <span class="mesa-tag">Mesa ${p.mesa_num}</span>
        </div>
        <div class="order-time">Recibido a las ${hora}</div>
        <div class="items-list">${items}</div>
        ${p.notas ? `<div style="font-size:12px;color:var(--muted);margin-bottom:8px;">📝 ${p.notas}</div>` : ''}
        ${actions}
      </div>
    `;
  }).join('');
}

async function cambiarEstado(id, nuevoEstado) {
  const timerInput = document.getElementById('timer-' + id);
  const tiempoEstimado = timerInput ? parseInt(timerInput.value) || null : null;

  const btn = document.querySelector(`#card-${id} .action-btn`);
  if (btn) { btn.disabled = true; btn.textContent = 'Actualizando...'; }

  try {
    const res = await fetch('cocina_actualizar.php', { 
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, estado: nuevoEstado, tiempo_estimado: tiempoEstimado })
    });
    const data = await res.json();
    
    if (data.success) {
      await fetchPedidos();
    } else {
      alert('Error: ' + data.error);
      if (btn) btn.disabled = false;
    }
  } catch (e) {
    console.error(e);
    if (btn) { btn.disabled = false; btn.textContent = 'Reintentar'; }
  }
}

function showToast(msg) {
  const t = document.getElementById('notif-toast');
  t.textContent = '🔔 ' + msg;
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 4000);
}

function playNotificationSound() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    osc.frequency.setValueAtTime(660, ctx.currentTime + 0.1);
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.5);
  } catch (e) {}
}

// Fetch inicial y actualización automática cada 5 segundos
fetchPedidos();
setInterval(fetchPedidos, 5000);
</script>
</body>
</html>