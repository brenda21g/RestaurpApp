<?php
/**
 * Archivo: scm/logistica.php
 * Descripción: Configuración y visualización (Tabla o Gráfica interactiva) de la estrategia logística PUSH o PULL por insumo.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_gerente = ($rol_actual === 'gerente');

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$es_gerente) {
        $error = "Acceso denegado: Solo el Gerente puede modificar las estrategias logísticas.";
    } else {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        $estrategia = $_POST['estrategia'] ?? 'PUSH';
        
        if ($producto_id > 0 && in_array($estrategia, ['PUSH', 'PULL'], true)) {
            $stmt = $db->prepare("UPDATE scm_productos SET estrategia_logistica = ? WHERE id = ?");
            $stmt->execute([$estrategia, $producto_id]);
            header("Location: logistica.php?success=1");
            exit;
        } else {
            $error = "Por favor selecciona un producto y una estrategia válida.";
        }
    }
}

// Conteo global de stock crítico para la barra lateral y alerta de pantalla
$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

// Obtener productos (sin la columna categoria que causaba el error)
$productos = $db->query("SELECT id, nombre, estrategia_logistica, stock_actual, stock_minimo FROM scm_productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$total_push = 0;
$total_pull = 0;
foreach ($productos as $p) {
    if (strtoupper($p['estrategia_logistica'] ?? 'PUSH') === 'PULL') {
        $total_pull++;
    } else {
        $total_push++;
    }
}
$total_prod_count = count($productos);
$porcentaje_push = $total_prod_count > 0 ? round(($total_push / $total_prod_count) * 100) : 0;
$porcentaje_pull = $total_prod_count > 0 ? round(($total_pull / $total_prod_count) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logística SCM – Estrategia Push/Pull</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ==========================================================================
       1. VARIABLES Y CONFIGURACIÓN GLOBAL
       ========================================================================== */
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --secondary: #000049;
      --sidebar-hover: #0369a1;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
      --sidebar-w: 260px;
      --radius: 10px;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
    }

    /* ==========================================================================
       2. RESET Y ESTILOS BASE
       ========================================================================== */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', sans-serif;
    }

    body {
      background: var(--bg);
      color: var(--text);
      display: flex;
      min-height: 100vh;
      font-size: 14px;
    }

    /* ==========================================================================
       3. SIDEBAR INSTITUCIONAL AZUL
       ========================================================================== */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--secondary);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      z-index: 100;
      overflow-y: auto;
    }

    .sidebar-brand {
      padding: 24px;
      font-size: 18px;
      font-weight: 700;
      color: #ffffff;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-brand span {
      color: var(--primary);
    }

    .sidebar-menu {
      padding: 20px 10px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }

    .sidebar-item {
      padding: 12px 16px;
      border-radius: var(--radius);
      color: #94a3b8;
      text-decoration: none;
      font-weight: 500;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: 0.2s;
    }

    .sidebar-item:hover, .sidebar-item.active {
      background: var(--sidebar-hover);
      color: #ffffff;
    }

    .sidebar-item.active {
      font-weight: 600;
    }

    /* ==========================================================================
       4. CONTENIDO PRINCIPAL Y BANNER DE ALERTA
       ========================================================================== */
    .main-content {
      margin-left: var(--sidebar-w);
      flex: 1;
      padding: 32px 40px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .header h1 {
      font-size: 22px;
      font-weight: 700;
      color: var(--text);
    }

    .role-badge {
      background: rgba(2, 132, 199, 0.1);
      color: var(--primary);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .alert-banner {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-left: 4px solid var(--danger);
      padding: 16px;
      border-radius: var(--radius);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alert-banner span.icon {
      font-size: 20px;
    }

    .alert-banner .content {
      color: #991b1b;
      font-weight: 500;
    }

    .info-box {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      padding: 16px;
      border-radius: var(--radius);
      margin-bottom: 24px;
      color: #0369a1;
      line-height: 1.6;
    }

    /* ==========================================================================
       5. COMPONENTES Y SWITCH DE VISTA (TABLA / GRÁFICA)
       ========================================================================== */
    .grid-container {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      align-items: start;
    }

    @media (max-width: 1024px) {
      .grid-container { grid-template-columns: 1fr; }
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: var(--shadow-sm);
      margin-bottom: 20px;
    }

    .card h2 {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 16px;
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .view-switcher {
      display: flex;
      background: #f1f5f9;
      padding: 4px;
      border-radius: 8px;
      gap: 4px;
    }

    .switch-btn {
      background: transparent;
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
      width: auto;
    }

    .switch-btn.active {
      background: var(--surface);
      color: var(--primary);
      box-shadow: var(--shadow-sm);
    }

    .view-section {
      display: none;
    }

    .view-section.active {
      display: block;
    }

    /* Estilos Formulario */
    .field {
      margin-bottom: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .field label {
      font-weight: 600;
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    select, input {
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 13px;
      outline: none;
      background: #fff;
      color: var(--text);
      font-family: inherit;
    }

    select:focus, input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    }

    button.btn-submit {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 11px 16px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      font-size: 13px;
      font-family: inherit;
      transition: background 0.2s;
    }

    button.btn-submit:hover {
      background: var(--primary-hover);
    }

    /* Tabla y Gráfica visual */
    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 13px;
    }

    th {
      background: #f1f5f9;
      padding: 10px 14px;
      font-size: 11px;
      text-transform: uppercase;
      color: var(--muted);
      letter-spacing: 0.5px;
      border-bottom: 1px solid var(--border);
    }

    td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--border);
      color: var(--text);
      vertical-align: middle;
    }

    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #f8fafc; }

    .badge-push {
      background: rgba(2, 132, 199, 0.1);
      color: var(--primary);
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
    }

    .badge-pull {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
    }

    /* Gráfica de barras CSS Proporcional */
    .chart-container {
      display: flex;
      flex-direction: column;
      gap: 16px;
      padding: 10px 0;
    }

    .chart-bar-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .chart-label-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      font-weight: 600;
    }

    .chart-track {
      background: #f1f5f9;
      height: 14px;
      border-radius: 10px;
      overflow: hidden;
      border: 1px solid var(--border);
    }

    .chart-fill {
      height: 100%;
      border-radius: 10px;
      transition: width 0.5s ease;
    }

    .chart-fill.push { background: var(--primary); }
    .chart-fill.pull { background: var(--success); }

    .alert-success { 
      background: rgba(16, 185, 129, 0.1); 
      border: 1px solid rgba(16, 185, 129, 0.2); 
      color: var(--success); 
      padding: 12px 16px; 
      border-radius: 8px; 
      margin-bottom: 20px; 
      font-weight: 500; 
    }

    .alert-error { 
      background: rgba(239, 68, 68, 0.1); 
      border: 1px solid rgba(239, 68, 68, 0.2); 
      color: var(--danger); 
      padding: 12px 16px; 
      border-radius: 8px; 
      margin-bottom: 20px; 
      font-weight: 500; 
    }

    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 20px; }
      .sidebar { display: none; }
    }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item"><span>📈</span> Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item"><span>📦</span> Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item"><span>🤝</span> Proveedores</a>
        <a href="inventario.php" class="sidebar-item">
            <span>📊</span> Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;"><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>
        <a href="movimientos.php" class="sidebar-item"><span>🔄</span> Movimientos</a>
        <a href="pedidos.php" class="sidebar-item"><span>🛒</span> Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item active"><span>⚙️</span> Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>←</span> Salir al Panel</a>
    </div>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Ver inventario crítico</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="header">
        <h1>⚙️️ Logística - Estrategia de Reposición Push / Pull</h1>
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="info-box">
        <b>Estrategia PUSH:</b> Se genera pedido de reposición de forma automática cuando el stock alcanza o desciende del nivel mínimo establecido.<br>
        <b>Estrategia PULL:</b> El inventario se repone exclusivamente bajo demanda real o mediante orden manual registrada en el sistema.
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert-success">✔ Estrategia logística actualizada correctamente en el sistema.</div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="grid-container">
        <!-- COLUMNA IZQUIERDA: CONFIGURACIÓN DE ESTRATEGIA -->
        <div class="card">
            <h2>Configurar Estrategia por Insumo</h2>

            <?php if($es_gerente): ?>
                <form method="POST">
                    <div class="field">
                        <label>Seleccionar Materia Prima / Insumo</label>
                        <select name="producto_id" required>
                            <option value="">-- Selecciona un insumo --</option>
                            <?php foreach($productos as $p): ?>
                                <option value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?> (Actual: <?= htmlspecialchars($p['estrategia_logistica'] ?? 'PUSH', ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Estrategia Logística</label>
                        <select name="estrategia" required>
                            <option value="PUSH">PUSH (Automática por stock mínimo)</option>
                            <option value="PULL">PULL (Bajo demanda / Manual)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Guardar Configuración de Estrategia</button>
                </form>
            <?php else: ?>
                <p style="color:var(--muted); line-height: 1.5;">Estás accediendo en modo de consulta (Rol: <b><?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></b>). La modificación de estrategias logísticas Push/Pull está reservada exclusivamente para el perfil de <b>Gerente</b>.</p>
            <?php endif; ?>
        </div>

        <!-- COLUMNA DERECHA: VISOR INTERACTIVO (TABLA VS GRÁFICA) -->
        <div class="card">
            <h2>
                <span>Distribución de Estrategias</span>
                <div class="view-switcher">
                    <button type="button" class="switch-btn active" onclick="switchView('tabla', event)">📋 Tabla</button>
                    <button type="button" class="switch-btn" onclick="switchView('grafica', event)">📊 Gráfica</button>
                </div>
            </h2>

            <!-- VISTA DE TABLA -->
            <div id="view-tabla" class="view-section active">
                <div style="max-height: 320px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th>Stock Actual</th>
                                <th style="text-align: center;">Estrategia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($productos)): ?>
                                <tr><td colspan="3" style="text-align:center; color:var(--muted); padding:20px;">No hay productos registrados.</td></tr>
                            <?php else: foreach($productos as $p): $est = strtoupper($p['estrategia_logistica'] ?? 'PUSH'); ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                    <td style="color:var(--muted);"><?= htmlspecialchars($p['stock_actual'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="text-align: center;">
                                        <span class="<?= $est === 'PULL' ? 'badge-pull' : 'badge-push' ?>">
                                            <?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VISTA DE GRÁFICA INTERACTIVA -->
            <div id="view-grafica" class="view-section">
                <div class="chart-container">
                    <p style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">Comparativa del total de insumos bajo modelo Push vs Pull (Total: <b><?= $total_prod_count ?></b>)</p>
                    
                    <div class="chart-bar-group">
                        <div class="chart-label-row">
                            <span style="color: var(--primary);">📦 Estrategia PUSH (Automática)</span>
                            <span><b><?= $total_push ?></b> insumos (<?= $porcentaje_push ?>%)</span>
                        </div>
                        <div class="chart-track">
                            <div class="chart-fill push" style="width: <?= $porcentaje_push ?>%;"></div>
                        </div>
                    </div>

                    <div class="chart-bar-group" style="margin-top: 10px;">
                        <div class="chart-label-row">
                            <span style="color: var(--success);">🛒 Estrategia PULL (Bajo demanda)</span>
                            <span><b><?= $total_pull ?></b> insumos (<?= $porcentaje_pull ?>%)</span>
                        </div>
                        <div class="chart-track">
                            <div class="chart-fill pull" style="width: <?= $porcentaje_pull ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchView(tipo, evt) {
    // Cambiar clases de las secciones
    document.getElementById('view-tabla').classList.remove('active');
    document.getElementById('view-grafica').classList.remove('active');
    document.getElementById('view-' + tipo).classList.add('active');

    // Cambiar clases de los botones del switch
    const buttons = document.querySelectorAll('.switch-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    evt.currentTarget.classList.add('active');
}
</script>

</body>
</html>