<?php
/**
 * Archivo: scm/pedido_form.php
 * Descripción: Formulario para generar órdenes manuales de reposición (Pull) con su proveedor específico, control de roles, permisos y banner global.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_editable = puedeEditar('scm'); // TRUE para Gerente y Logística; FALSE para Subgerente

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$es_editable) {
        $error = "Acceso denegado: No tienes permisos de modificación en este módulo.";
    } else {
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
                // Se registra como Pull manual en estado pendiente para seguir el flujo de la cadena de suministro
                $stmt = $db->prepare("INSERT INTO scm_pedidos (numero_orden_scm, producto_id, proveedor_id, cantidad, tipo, estado, fecha) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$num_orden, $producto_id, $proveedor_id, $cantidad, 'Reposición Manual (Pull)', 'pendiente', $fecha]);
                
                header("Location: pedidos.php");
                exit;
            } else {
                $error = "El insumo seleccionado no tiene un proveedor asignado.";
            }
        } else {
            $error = "Por favor completa todos los campos requeridos correctamente.";
        }
    }
}

// Conteo global de stock crítico para la barra lateral y banner global
$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

$productos = $db->query("SELECT id, nombre FROM scm_productos WHERE proveedor_id IS NOT NULL ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Definir enlace de salida según el rol actual
$url_salida = '../index.php';
if ($rol_actual === 'gerente') {
    $url_salida = '../gerente/dashboard.php';
} elseif ($rol_actual === 'subgerente') {
    $url_salida = '../subgerente/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generar Pedido Pull SCM – Restaurant App</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
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

    .main-content {
      margin-left: var(--sidebar-w);
      flex: 1;
      padding: 32px 40px;
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

    .alert-error { 
      background: rgba(239, 68, 68, 0.1); 
      border: 1px solid rgba(239, 68, 68, 0.2); 
      color: var(--danger); 
      padding: 12px 16px; 
      border-radius: 8px; 
      margin-bottom: 20px; 
      font-weight: 500; 
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
      box-shadow: var(--shadow-sm);
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
      transition: border-color 0.2s;
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

    .btn-secondary {
      background: var(--surface);
      color: var(--text);
      border: 1px solid var(--border);
      padding: 10px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s;
      margin-top: 10px;
      width: 100%;
    }

    .btn-secondary:hover {
      background: #f1f5f9;
    }

    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 20px; }
      .sidebar { display: none; }
    }
</style>
</head>
<body>

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
        <a href="pedidos.php" class="sidebar-item active"><span>🛒</span> Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item"><span>⚙</span> Logística Push/Pull</a>
        
        <!-- Salida condicional: Cerrar sesión para Logística, Salir al Panel para Gerente/Subgerente -->
        <?php if ($rol_actual === 'logistica'): ?>
            <a href="logout.php" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>🚪</span> Cerrar sesión</a>
        <?php else: ?>
            <a href="<?= htmlspecialchars($url_salida, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>←</span> Salir al Panel</a>
        <?php endif; ?>
    </div>
</div>

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

    <div class="header-top">
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <?php if($error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="form-container-wrapper">
        <div class="card">
            <?php if ($es_editable): ?>
                <h2>Generar Pedido Manual (Estrategia PULL)</h2>
                <form method="POST">
                    <div class="field">
                        <label>Insumo (Con su proveedor asignado)</label>
                        <select name="producto_id" required>
                            <option value="">-- Selecciona un insumo --</option>
                            <?php foreach($productos as $p): ?>
                                <option value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
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
                    <a href="pedidos.php" class="btn-secondary">Cancelar</a>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 10px; color: var(--muted);">
                    <p style="font-size: 16px; font-weight: 600; margin-bottom: 8px; color: var(--text);">🔒 Modo Consulta</p>
                    <p style="font-size: 13px; margin-bottom: 20px;">Tu rol actual (Subgerente) tiene acceso de solo lectura en el módulo SCM. No puedes generar órdenes manuales de reposición.</p>
                    <a href="pedidos.php" class="btn-secondary">← Volver a Pedidos</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>