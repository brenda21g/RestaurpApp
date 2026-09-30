<?php
/**
 * Archivo: scm/pedido_form.php
 * Descripción: Formulario para generar órdenes manuales de reposición (Pull) con su proveedor específico y control de roles.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = intval($_POST['producto_id'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $num_orden = 'ORD-PULL-' . strtoupper(substr(uniqid(), -6));

    if ($producto_id > 0 && $cantidad > 0) {
        $stmt_prod = $db->prepare("SELECT proveedor_id FROM scm_productos WHERE id = ?");
        $stmt_prod->execute([$producto_id]);
        $prod = $stmt_prod->fetch(PDO::FETCH_ASSOC);
        $proveedor_id = $prod['proveedor_id'] ?? null;

        if ($proveedor_id) {
            // Se registra como Pull manual en estado procesando para que luego sume stock
            $stmt = $db->prepare("INSERT INTO scm_pedidos (numero_orden_scm, producto_id, proveedor_id, cantidad, tipo, estado, fecha) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$num_orden, $producto_id, $proveedor_id, $cantidad, 'Reposición Manual (Pull)', 'procesando', $fecha]);
        }
    }
    header("Location: pedidos.php");
    exit;
}

// Conteo global de stock crítico para la barra lateral
$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

$productos = $db->query("SELECT id, nombre FROM scm_productos WHERE proveedor_id IS NOT NULL ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Generar Pedido Pull SCM – Restaurant App</title>
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
      display: flex;
      flex-direction: column;
    }

    .header-top {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 20px;
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

    .form-container-wrapper {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .card {
      background: var(--surface);
      width: 100%;
      max-width: 500px;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    h2 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 20px;
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
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    input, select {
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      background: #fff;
      color: var(--text);
      font-family: inherit;
    }

    input:focus, select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    }

    button {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 11px 12px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      font-size: 14px;
      font-family: inherit;
      transition: background 0.2s;
      margin-top: 6px;
    }

    button:hover {
      background: var(--primary-hover);
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
        <a href="pedidos.php" class="sidebar-item active">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color: var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header-top">
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual)) ?></div>
    </div>

    <div class="form-container-wrapper">
        <div class="card">
            <h2>Generar Pedido Manual (Estrategia PULL)</h2>
            <form method="POST">
                <div class="field">
                    <label>Insumo (Con su proveedor asignado)</label>
                    <select name="producto_id" required>
                        <option value="">-- Selecciona un insumo --</option>
                        <?php foreach($productos as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Cantidad a solicitar</label>
                    <input type="number" name="cantidad" required min="1" placeholder="Ej. 25">
                </div>
                <div class="field">
                    <label>Fecha de Solicitud</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>
                <button type="submit">Crear Orden de Reposición Pull</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>