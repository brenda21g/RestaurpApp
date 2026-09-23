<?php
/**
 * Archivo: scm/producto_form.php
 * Descripción: Formulario para crear o editar materias primas e insumos.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente']);
$db = getDB();

$id = $_GET['id'] ?? null;
$producto = ['nombre' => '', 'descripcion' => '', 'stock_actual' => 0, 'stock_minimo' => 5, 'estrategia_logistica' => 'PUSH', 'proveedor_id' => '', 'precio' => 0];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM scm_productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC) ?: $producto;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $desc = trim($_POST['descripcion']);
    $stock_actual = intval($_POST['stock_actual']);
    $stock_minimo = intval($_POST['stock_minimo']);
    $estrategia = $_POST['estrategia_logistica'];
    $proveedor_id = !empty($_POST['proveedor_id']) ? $_POST['proveedor_id'] : null;
    $precio = floatval($_POST['precio']);

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
$proveedores = $db->query("SELECT id, nombre FROM proveedores")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Materia Prima Form</title>
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
    }

    .sidebar-brand {
      padding: 24px;
      font-size: 18px;
      font-weight: 700;
      color: var(--primary);
      border-bottom: 1px solid var(--border);
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
      color: var(--muted);
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.2s;
    }

    .sidebar-item:hover, .sidebar-item.active {
      background: #e0f2fe;
      color: var(--primary);
    }

    .main-content {
      margin-left: 260px;
      flex: 1;
      padding: 30px;
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
      font-size: 20px;
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
      font-size: 12px;
      color: var(--muted);
      text-transform: uppercase;
    }

    input, select, textarea {
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      background: #fff;
      color: var(--text);
    }

    input:focus, select:focus, textarea:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
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
      transition: background 0.2s;
    }

    button:hover {
      background: var(--primary-hover);
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant App SCM</div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item">📈 Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item active">📦 Materias Primas</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        <a href="inventario.php" class="sidebar-item">📊 Inventario / Alertas</a>
        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color:var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="card">
        <h2><?= $id ? 'Editar' : 'Nueva' ?> Materia Prima</h2>
        <form method="POST">
            <div class="field">
                <label>Nombre del Insumo</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
            </div>
            <div class="field">
                <label>Descripción</label>
                <textarea name="descripcion"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
            </div>
            <div class="field">
                <label>Stock Actual</label>
                <input type="number" name="stock_actual" value="<?= $producto['stock_actual'] ?>">
            </div>
            <div class="field">
                <label>Stock Mínimo (Alerta)</label>
                <input type="number" name="stock_minimo" value="<?= $producto['stock_minimo'] ?>">
            </div>
            <div class="field">
                <label>Estrategia Logística</label>
                <select name="estrategia_logistica">
                    <option value="PUSH" <?= $producto['estrategia_logistica']=='PUSH'?'selected':'' ?>>PUSH (Automática)</option>
                    <option value="PULL" <?= $producto['estrategia_logistica']=='PULL'?'selected':'' ?>>PULL (Bajo demanda)</option>
                </select>
            </div>
            <div class="field">
                <label>Proveedor Asignado</label>
                <select name="proveedor_id">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach($proveedores as $prov): ?>
                        <option value="<?= $prov['id'] ?>" <?= $producto['proveedor_id']==$prov['id']?'selected':'' ?>><?= htmlspecialchars($prov['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Costo / Precio Unitario</label>
                <input type="number" step="0.01" name="precio" value="<?= $producto['precio'] ?>">
            </div>
            <button type="submit">Guardar Materia Prima</button>
        </form>
    </div>
</div>

</body>
</html>