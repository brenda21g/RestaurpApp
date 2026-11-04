<?php
/**
 * Archivo: logistica/movimiento_form.php
 * Descripción: Formulario para registrar entradas o salidas manuales en el inventario con el Sidebar de logística.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? 'logistica';
$es_editable = puedeEditar('scm'); // TRUE para Gerente y Logística; FALSE para Subgerente

// Obtener datos del usuario actual para el sidebar
$admin_id = $_SESSION['admin_id'] ?? 0;
$stmt_admin = $db->prepare("SELECT nombre FROM admins WHERE id = ?");
$stmt_admin->execute([$admin_id]);
$admin_actual = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$nombre_usuario = $admin_actual['nombre'] ?? 'Logística';

$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$es_editable) {
        $error = "Acceso denegado: No tienes permisos de modificación en este módulo.";
    } else {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'Entrada'; // 'Entrada' o 'Salida'
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $usuario_id = $_SESSION['admin_id'] ?? null;

        if ($producto_id > 0 && $cantidad > 0 && $motivo !== '') {
            // Validar si hay suficiente stock en caso de una salida
            if ($tipo === 'Salida') {
                $stmt_stock = $db->prepare("SELECT stock_actual, nombre FROM scm_productos WHERE id = ?");
                $stmt_stock->execute([$producto_id]);
                $prod_info = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                
                if ($prod_info && $cantidad > intval($prod_info['stock_actual'])) {
                    $error = "Stock insuficiente para el insumo <b>" . htmlspecialchars($prod_info['nombre'], ENT_QUOTES, 'UTF-8') . "</b>. Stock actual disponible: <b>" . $prod_info['stock_actual'] . "</b>.";
                }
            }

            if ($error === '') {
                // Registrar el movimiento
                $stmt = $db->prepare("INSERT INTO scm_movimientos (producto_id, tipo, cantidad, motivo, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$producto_id, $tipo, $cantidad, $motivo, $fecha, $usuario_id]);

                // Actualizar stock actual en scm_productos automáticamente
                if ($tipo === 'Entrada') {
                    $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual + ? WHERE id = ?")->execute([$cantidad, $producto_id]);
                } else {
                    $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual - ? WHERE id = ?")->execute([$cantidad, $producto_id]);
                }

                header("Location: movimientos.php");
                exit;
            }
        } else {
            $error = "Por favor completa todos los campos requeridos correctamente.";
        }
    }
}

$productos = $db->query("SELECT id, nombre, stock_actual FROM scm_productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$seccion = 'movimientos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrar Movimiento – Panel de Logística</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --secondary: #03143d;
      --sidebar-hover: #092e70;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0788c9;
      --primary-hover: #0674ad;
      --danger: #ef4444;
      --success: #13b77a;
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
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-brand .role-sub {
      font-size: 11px;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 4px;
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
      color: #cbd5e1;
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
      background: rgba(7, 136, 201, 0.1);
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

    .btn {
      background: var(--primary);
      color: #fff;
      padding: 10px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: none;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn:hover {
      background: var(--primary-hover);
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: var(--shadow-sm);
      max-width: 600px;
    }

    .field {
      margin-bottom: 18px;
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

    .field input, .field select, .field textarea {
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      color: var(--text);
      background: #fff;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .field input:focus, .field select:focus, .field textarea:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(7, 136, 201, 0.1);
    }

    .field textarea {
      resize: vertical;
      min-height: 90px;
    }

    .form-actions {
      display: flex;
      gap: 10px;
      margin-top: 24px;
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
    <div class="sidebar-brand">
        🍽️ RestaurApp
        <div class="role-sub">Logística (<?= htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8') ?>)</div>
    </div>
    <div class="sidebar-menu">
        <a class="sidebar-item <?= ($seccion ?? '') === 'inicio' ? 'active' : '' ?>" href="dashboard.php?seccion=inicio"><span>📊</span> Inicio</a>
        <a class="sidebar-item <?= ($seccion ?? '') === 'ordenes' ? 'active' : '' ?>" href="dashboard.php?seccion=ordenes"><span>🛒</span> Órdenes ERP</a>
        <a class="sidebar-item <?= ($seccion ?? '') === 'reportes' ? 'active' : '' ?>" href="dashboard.php?seccion=reportes"><span>📈</span> Reportes y Métricas</a>
        <a class="sidebar-item" href="productos.php"><span>📦</span> Productos</a>
        <a class="sidebar-item" href="inventario.php"><span>📊</span> Inventario</a>
        <a class="sidebar-item active" href="movimientos.php"><span>🔄</span> Movimientos</a>
        <a class="sidebar-item" href="configuracion.php"><span>⚙️</span> Configuración</a>
    </div>
    <div style="padding: 16px; border-top: 1px solid #0b2455;">
        <a href="logout.php" style="color: #ff6868; text-decoration: none; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px;">🚪 Cerrar sesión</a>
    </div>
</div>

<div class="main-content">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención Logística!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Ver inventario crítico</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="header">
        <h1>🔄 Registrar Movimiento de Inventario</h1>
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <?php if($error): ?>
        <div class="alert-error">⚠ <?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if ($es_editable): ?>
            <form method="POST">
                <div class="field">
                    <label>Materia Prima / Insumo *</label>
                    <select name="producto_id" required>
                        <option value="">Seleccione un insumo...</option>
                        <?php foreach($productos as $p): ?>
                            <option value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?> (Stock actual: <?= htmlspecialchars($p['stock_actual'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Tipo de Movimiento *</label>
                    <select name="tipo" required>
                        <option value="Entrada">Entrada (Suma Stock)</option>
                        <option value="Salida">Salida (Resta Stock)</option>
                    </select>
                </div>

                <div class="field">
                    <label>Cantidad *</label>
                    <input type="number" name="cantidad" min="1" placeholder="Ej. 10" required>
                </div>

                <div class="field">
                    <label>Motivo / Razón *</label>
                    <textarea name="motivo" placeholder="Ej. Merma por caducidad, Ajuste de inventario, Recepción de lote, etc." required></textarea>
                </div>

                <div class="field">
                    <label>Fecha del Movimiento *</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Guardar Movimiento</button>
                    <a href="movimientos.php" class="btn-secondary">Cancelar</a>
                </div>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 20px; color: var(--muted);">
                <p style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">🔒 Modo Consulta</p>
                <p style="font-size: 13px; margin-bottom: 20px;">Tu rol actual (Subgerente) tiene acceso de solo lectura en este módulo. No puedes registrar entradas o salidas de inventario.</p>
                <a href="movimientos.php" class="btn-secondary">← Volver a Movimientos</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>