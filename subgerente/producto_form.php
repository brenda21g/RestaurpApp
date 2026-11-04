<?php
/**
 * Archivo: scm/producto_form.php
 * Descripción: Formulario para crear o editar materias primas e insumos con control de roles, banner global y Sidebar institucional actualizado.
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
<title>Materia Prima Form – RestaurApp</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f7f9fc;
      --surface: #ffffff;
      --secondary: #03143d;
      --sidebar-hover: #092e70;
      --text: #071b3a;
      --muted: #60708a;
      --border: #e2e8f0;
      --primary: #0788c9;
      --primary-hover: #0674ad;
      --danger: #ef5b63;
      --success: #13b77a;
      --warning: #f5a000;
      --sidebar-w: 260px;
      --radius: 10px;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'DM Sans', sans-serif;
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
      border-right: 1px solid #0b2455;
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
      padding: 24px 20px;
      border-bottom: 1px solid #0b2455;
    }

    .sidebar-brand .name {
      font-family: 'Playfair Display', serif;
      font-size: 20px;
      color: #ffffff;
    }

    .sidebar-brand .role {
      font-size: 11px;
      color: #91a6c7;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-top: 2px;
    }

    .sidebar-menu {
      padding: 16px 12px;
      display: flex;
      flex-direction: column;
      gap: 4px;
      flex: 1;
    }

    .sidebar-item {
      padding: 10px 12px;
      border-radius: 12px;
      color: #9db0ce;
      text-decoration: none;
      font-weight: 500;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.15s;
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
      background: #e0f2fe;
      color: var(--primary);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .alert-banner {
      background: #fdebed;
      border: 1px solid #f4c7cc;
      border-left: 4px solid var(--danger);
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alert-banner span.icon {
      font-size: 20px;
    }

    .alert-banner .content {
      color: #c8424c;
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
      box-shadow: 0 2px 5px rgba(15,23,42,.025);
    }

    h2 {
      font-family: 'Playfair Display', serif;
      font-size: 22px;
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
      padding: 10px 14px;
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
      box-shadow: 0 0 0 3px rgba(7, 136, 201, 0.08);
    }

    textarea {
      resize: vertical;
      min-height: 80px;
    }

    button {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 12px;
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
    <div class="sidebar-brand">
        <div class="name">🍽️ RestaurApp SCM</div>
        <div class="role">Logística & Insumos</div>
    </div>
    <div class="sidebar-menu">
    <a class="sidebar-item" href="dashboard.php?seccion=inicio"><span>📊</span> Dashboard</a>
    <a class="sidebar-item" href="dashboard.php?seccion=ordenes"><span>🛒</span> Órdenes ERP</a>
    <a class="sidebar-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="sidebar-item active" href="productos.php"><span>📦</span> Productos</a>
    <a class="sidebar-item" href="pedidos.php"><span>📋</span> Pedidos</a>
    <a class="sidebar-item" href="mesas_qr.php"><span>🪑</span> Mesas QR</a>
    <a class="sidebar-item" href="menu.php"><span>🍲</span> Menú</a>
    <a class="sidebar-item" href="corte.php"><span>💰</span> Corte</a>
    <a class="sidebar-item" href="miactividad.php"><span>📈</span> Mi Actividad</a>
    <a class="nav-item" href="configuracion.php"><span>⚙️</span>Configuracion</a>
  </nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
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
                <div style="background: #fffbeb; border: 1px solid #fef3c7; color: var(--warning); padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; font-weight: 500;">
                    ⚠ Estás visualizando en modo lectura. Solo el Gerente puede guardar cambios en los insumos.
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
                    <a href="productos.php" style="display: block; text-align: center; background: #e2e8f0; color: #334155; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; margin-top: 6px;">Regresar al Listado</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

</body>
</html>