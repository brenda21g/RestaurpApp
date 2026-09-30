<?php
/**
 * Archivo: scm/movimiento_form.php
 * Descripción: Formulario para registrar entradas o salidas manuales en el inventario con control de roles y Sidebar institucional.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';

$db_sidebar = getDB();
$num_alertas_global = $db_sidebar->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = intval($_POST['producto_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'Entrada'; // 'Entrada' o 'Salida'
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $usuario_id = $_SESSION['admin_id'] ?? null;

    if ($producto_id > 0 && $cantidad > 0 && $motivo !== '') {
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
}

$productos = $db->query("SELECT id, nombre FROM scm_productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registrar Movimiento SCM – Restaurant App</title>
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
      border-bottom: 1px solid rgba(255,255,255,0.1);
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
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
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
      background: #e2e8f0;
      color: #334155;
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
      background: #cbd5e1;
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

        <a href="movimientos.php" class="sidebar-item active">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color: var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1>🔄 Registrar Movimiento de Inventario</h1>
        <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual)) ?></div>
    </div>

    <div class="card">
        <form method="POST">
            <div class="field">
                <label>Materia Prima / Insumo *</label>
                <select name="producto_id" required>
                    <option value="">Seleccione un insumo...</option>
                    <?php foreach($productos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
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
    </div>
</div>

</body>
</html>