<?php
/**
 * Archivo: scm/logistica.php
 * Descripción: Configuración de la estrategia logística PUSH o PULL por insumo.
 */
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['admin_rol'] === 'gerente') {
    $producto_id = intval($_POST['producto_id']);
    $estrategia = $_POST['estrategia'];
    $db->prepare("UPDATE scm_productos SET estrategia_logistica = ? WHERE id = ?")->execute([$estrategia, $producto_id]);
    header("Location: logistica.php?success=1");
    exit;
}

$productos = $db->query("SELECT id, nombre, estrategia_logistica FROM scm_productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$es_gerente = ($_SESSION['admin_rol'] === 'gerente');
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

    .card {
      background: var(--surface);
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

    select {
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      background: #fff;
      color: var(--text);
    }

    select:focus {
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

    .info-box {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      color: #0369a1;
      line-height: 1.5;
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
        <a href="pedidos.php" class="sidebar-item">🛒 Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item active">⚙️ Logística Push/Pull</a>
        <a href="../gerente/dashboard.php" class="sidebar-item" style="margin-top: 20px; color:var(--danger);">← Salir al Panel</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1>⚙️ Logística - Estrategia de Reposición</h1>
    </div>

    <div class="info-box">
        <b>PUSH:</b> Se genera pedido de forma automática cuando el stock alcanza el nivel mínimo establecido.<br>
        <b>PULL:</b> Se repone exclusivamente bajo demanda o mediante orden manual por parte del administrador.
    </div>

    <div class="card" style="max-width: 600px;">
        <h2>Configurar Estrategia por Insumo</h2>
        <?php if(isset($_GET['success'])): ?>
            <p style="color:var(--success); margin-bottom:15px; font-weight:600;">✔ Estrategia actualizada correctamente.</p>
        <?php endif; ?>

        <?php if($es_gerente): ?>
        <form method="POST">
            <div class="field">
                <label>Seleccionar Materia Prima</label>
                <select name="producto_id" required>
                    <?php foreach($productos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Actual: <?= $p['estrategia_logistica'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Estrategia Logística</label>
                <select name="estrategia">
                    <option value="PUSH">PUSH (Automática por stock mínimo)</option>
                    <option value="PULL">PULL (Bajo demanda / Manual)</option>
                </select>
            </div>
            <button type="submit">Guardar Configuración</button>
        </form>
        <?php else: ?>
            <p style="color:var(--muted);">Modo consulta: Solo el Gerente puede modificar las estrategias logísticas.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>