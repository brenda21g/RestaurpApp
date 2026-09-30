<?php
/**
 * Archivo: scm/logistica.php
 * Descripción: Configuración de la estrategia logística PUSH o PULL por insumo con control de roles.
 */
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

// Conteo global de stock crítico para la barra lateral
$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

$productos = $db->query("SELECT id, nombre, estrategia_logistica FROM scm_productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Logística SCM – Estrategia Push/Pull</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --secondary: #000049;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
    }

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

    .sidebar {
      width: 260px;
      background: var(--secondary);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      position: fixed;
      height: 100vh;
      z-index: 100;
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
    }

    .sidebar-item {
      padding: 12px 16px;
      border-radius: 8px;
      color: #94a3b8;
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.2s;
    }

    .sidebar-item:hover, .sidebar-item.active {
      background: rgba(2, 132, 199, 0.15);
      color: #ffffff;
    }

    .sidebar-item.active {
      color: var(--primary);
      font-weight: 600;
    }

    .main-content {
      margin-left: 260px;
      flex: 1;
      padding: 30px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
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

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      max-width: 650px;
    }

    h2 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 16px;
      color: var(--text);
    }

    .field {
      margin-bottom: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .field label {
      font-weight: 600;
      font-size: 12px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    select {
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      background: #fff;
      color: var(--text);
      font-family: inherit;
    }

    select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    }

    button {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 11px 16px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      font-size: 14px;
      font-family: inherit;
      transition: background 0.2s;
    }

    button:hover {
      background: var(--primary-hover);
    }

    .info-box {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 24px;
      color: #0369a1;
      line-height: 1.6;
      max-width: 650px;
    }

    .alert-success { 
      background: rgba(16, 185, 129, 0.1); 
      border: 1px solid rgba(16, 185, 129, 0.2); 
      color: var(--success); 
      padding: 12px 16px; 
      border-radius: 8px; 
      margin-bottom: 20px; 
      font-weight: 500; 
      max-width: 650px;
    }

    .alert-error { 
      background: rgba(239, 68, 68, 0.1); 
      border: 1px solid rgba(239, 68, 68, 0.2); 
      color: var(--danger); 
      padding: 12px 16px; 
      border-radius: 8px; 
      margin-bottom: 20px; 
      font-weight: 500; 
      max-width: 650px;
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item">📈 Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item">📦 Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        
        <a href="inventario.php" class="sidebar-item">
            📊 Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;">⚠️ <?= $num_alertas_global ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item active">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color: var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1>⚙️ Logística - Estrategia de Reposición</h1>
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual)) ?></div>
    </div>

    <div class="info-box">
        <b>Estrategia PUSH:</b> Se genera pedido de reposición de forma automática cuando el stock alcanza o desciende del nivel mínimo establecido.<br>
        <b>Estrategia PULL:</b> El inventario se repone exclusivamente bajo demanda real o mediante orden manual registrada en el sistema.
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert-success">✔ Estrategia logística actualizada correctamente en el sistema.</div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Configurar Estrategia por Insumo</h2>

        <?php if($es_gerente): ?>
            <form method="POST">
                <div class="field">
                    <label>Seleccionar Materia Prima / Insumo</label>
                    <select name="producto_id" required>
                        <option value="">-- Selecciona un insumo --</option>
                        <?php foreach($productos as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nombre']) ?> (Actual: <?= htmlspecialchars($p['estrategia_logistica']) ?>)
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
                <button type="submit">Guardar Configuración de Estrategia</button>
            </form>
        <?php else: ?>
            <p style="color:var(--muted); line-height: 1.5;">Estás accediendo en modo de consulta (Rol: <b><?= htmlspecialchars(ucfirst($rol_actual)) ?></b>). La modificación de estrategias logísticas Push/Pull está reservada exclusivamente para el perfil de <b>Gerente</b>.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>