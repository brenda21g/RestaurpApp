<?php
/**
 * Archivo: scm/pedido_form.php
 * Descripción: Formulario para generar órdenes manuales de reposición (Pull) con su proveedor específico.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = intval($_POST['cantidad']);
    $fecha = $_POST['fecha'];
    $num_orden = 'ORD-SCM-' . strtoupper(substr(uniqid(), -6));

    $stmt_prod = $db->prepare("SELECT proveedor_id FROM scm_productos WHERE id = ?");
    $stmt_prod->execute([$producto_id]);
    $prod = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    $proveedor_id = $prod['proveedor_id'] ?? null;

    if ($proveedor_id) {
        $stmt = $db->prepare("INSERT INTO scm_pedidos (numero_orden_scm, producto_id, proveedor_id, cantidad, tipo, estado, fecha) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$num_orden, $producto_id, $proveedor_id, $cantidad, 'Reposición Manual (Pull)', 'pendiente', $fecha]);
    }
    header("Location: pedidos.php");
    exit;
}
$productos = $db->query("SELECT id, nombre FROM scm_productos WHERE proveedor_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Generar Pedido SCM</title>
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
      background: var(--surface);
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

    input, select {
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      background: #fff;
      color: var(--text);
    }

    input:focus, select:focus {
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
        <a href="productos.php" class="sidebar-item">📦 Materias Primas</a>
        <a href="proveedores.php" class="sidebar-item">🤝 Proveedores</a>
        <a href="inventario.php" class="sidebar-item">📊 Inventario / Alertas</a>
        <a href="movimientos.php" class="sidebar-item">🔄 Movimientos</a>
        <a href="pedidos.php" class="sidebar-item active">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color:var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="card">
        <h2>Generar Pedido Interno (Pull Manual)</h2>
        <form method="POST">
            <div class="field">
                <label>Insumo (Con proveedor asignado)</label>
                <select name="producto_id" required>
                    <?php foreach($productos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Cantidad a Solicitar</label>
                <input type="number" name="cantidad" required min="1">
            </div>
            <div class="field">
                <label>Fecha Esperada</label>
                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
            </div>
            <button type="submit">Crear Orden de Reposición</button>
        </form>
    </div>
</div>

</body>
</html>