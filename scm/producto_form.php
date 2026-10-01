<?php
/**
 * Archivo: scm/producto_form.php
 * Descripción: Formulario para crear o editar materias primas e insumos con control de roles, banner global y Sidebar institucional.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'encargado']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_gerente = ($rol_actual === 'gerente');

// Solo el gerente puede modificar o crear productos
if (!$es_gerente && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Location: productos.php?error=sin_permisos");
    exit;
}

$id = $_GET['id'] ?? null;
$producto = ['nombre' => '', 'descripcion' => '', 'stock_actual' => 0, 'stock_minimo' => 5, 'estrategia_logistica' => 'PUSH', 'proveedor_id' => '', 'precio' => 0];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM scm_productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC) ?: $producto;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_gerente) {
    $nombre = trim($_POST['nombre'] ?? '');
    $desc = trim($_POST['descripcion'] ?? '');
    $stock_actual = intval($_POST['stock_actual'] ?? 0);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
    $estrategia = $_POST['estrategia_logistica'] ?? 'PUSH';
    $proveedor_id = !empty($_POST['proveedor_id']) ? $_POST['proveedor_id'] : null;
    $precio = floatval($_POST['precio'] ?? 0);

    if ($nombre !== '') {
        if ($id) {
            $stmt = $db->prepare("UPDATE scm_productos SET nombre=?, descripcion=?, stock_actual=?, stock_minimo=?, estrategia_logistica=?, proveedor_id=?, precio=? WHERE id=?");
            $stmt->execute([$nombre, $desc, $stock_actual, $stock_minimo, $estrategia, $proveedor_id, $precio, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO scm_productos (nombre, descripcion, stock_actual, stock_minimo, estrategia_logistica, proveedor_id, precio) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$nombre, $desc, $stock_actual, $stock_minimo, $estrategia, $proveedor_id, $precio]);
        }
        header("Location: productos.php");
        exit;
    }
}

// Conteo global de stock crítico para la barra lateral y banner global
$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();

$proveedores = $db->query("SELECT id, nombre FROM proveedores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

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
<title>Materia Prima Form – Restaurant App</title>
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

    .form-container-wrapper {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .card {
      background: var(--surface);
      width: 100%;
      max-width: 550px;
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

    input, select, textarea {
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

    input:focus, select:focus, textarea:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 80px;
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
        <a href="productos.php" class="sidebar-item active"><span>📦</span> Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item"><span>🤝</span> Proveedores</a>
        
        <a href="inventario.php" class="sidebar-item">
            <span>📊</span> Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;"><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item"><span>🔄</span> Movimientos</a>
        <a href="pedidos.php" class="sidebar-item"><span>🛒</span> Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item"><span>⚙️</span> Logística Push/Pull</a>
        
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

    <div class="form-container-wrapper">
        <div class="card">
            <h2><?= $id ? 'Editar' : 'Nueva' ?> Materia Prima</h2>
            
            <?php if(!$es_gerente): ?>
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: var(--warning); padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
                    ⚠️️ Estás visualizando en modo lectura. Solo el Gerente puede guardar cambios en los insumos.
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label>Nombre del Insumo *</label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>" required <?= !$es_gerente ? 'disabled' : '' ?> autocomplete="off">
                </div>
                <div class="field">
                    <label>Descripción</label>
                    <textarea name="descripcion" <?= !$es_gerente ? 'disabled' : '' ?>><?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="field">
                    <label>Stock Actual</label>
                    <input type="number" name="stock_actual" value="<?= htmlspecialchars($producto['stock_actual'], ENT_QUOTES, 'UTF-8') ?>" <?= !$es_gerente ? 'disabled' : '' ?>>
                </div>
                <div class="field">
                    <label>Stock Mínimo (Alerta)</label>
                    <input type="number" name="stock_minimo" value="<?= htmlspecialchars($producto['stock_minimo'], ENT_QUOTES, 'UTF-8') ?>" <?= !$es_gerente ? 'disabled' : '' ?>>
                </div>
                <div class="field">
                    <label>Estrategia Logística</label>
                    <select name="estrategia_logistica" <?= !$es_gerente ? 'disabled' : '' ?>>
                        <option value="PUSH" <?= ($producto['estrategia_logistica'] ?? 'PUSH') === 'PUSH' ? 'selected' : '' ?>>PUSH (Automática)</option>
                        <option value="PULL" <?= ($producto['estrategia_logistica'] ?? 'PUSH') === 'PULL' ? 'selected' : '' ?>>PULL (Bajo demanda)</option>
                    </select>
                </div>
                <div class="field">
                    <label>Proveedor Asignado</label>
                    <select name="proveedor_id" <?= !$es_gerente ? 'disabled' : '' ?>>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach($proveedores as $prov): ?>
                            <option value="<?= htmlspecialchars($prov['id'], ENT_QUOTES, 'UTF-8') ?>" <?= ($producto['proveedor_id'] ?? '') == $prov['id'] ? 'selected' : '' ?>><?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Costo / Precio Unitario</label>
                    <input type="number" step="0.01" name="precio" value="<?= htmlspecialchars($producto['precio'], ENT_QUOTES, 'UTF-8') ?>" <?= !$es_gerente ? 'disabled' : '' ?>>
                </div>
                
                <?php if($es_gerente): ?>
                    <button type="submit">Guardar Materia Prima</button>
                <?php else: ?>
                    <a href="productos.php" style="display: block; text-align: center; background: #e2e8f0; color: #334155; padding: 11px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; margin-top: 6px;">Regresar al Listado</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

</body>
</html>