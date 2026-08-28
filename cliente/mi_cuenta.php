<?php

require_once __DIR__ . '/../config/config.php';

session_start();
if (!isset($_SESSION['cliente_id'])) {
    header('Location: login_cliente.php');
    exit;
}
if (!isset($_SESSION['cliente_id'])) {
    header("Location: login_cliente.php");
    exit;
}

$db = getDB();
$cliente_id = $_SESSION['cliente_id'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);

    $update = $db->prepare("UPDATE usuarios_cliente SET nombre = ?, telefono = ? WHERE id = ?");
    $update->execute([$nombre, $telefono, $cliente_id]);
    $_SESSION['cliente_nombre'] = $nombre;
    $mensaje = "Datos actualizados correctamente.";
}

$stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Determinar el enlace de regreso al menú conservando la mesa si existe
$mesa_param = '';
if (!empty($_GET['mesa'])) {
    $mesa_param = '?mesa=' . urlencode($_GET['mesa']);
} elseif (!empty($_SESSION['mesa_token'])) {
    $mesa_param = '?mesa=' . urlencode($_SESSION['mesa_token']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Cuenta – RestaurApp</title>
    <style>
        body { background: #0b0b0b; color: #f0ede8; font-family: sans-serif; padding: 20px; max-width: 500px; margin: 0 auto; }
        .card { background: #1c1c1c; border: 1px solid #2a2a2a; padding: 24px; border-radius: 12px; margin-bottom: 20px; }
        .puntos-box { background: rgba(232,184,109,0.1); border: 1px solid #e8b86d; border-radius: 8px; padding: 15px; text-align: center; }
        .puntos-val { font-size: 32px; font-weight: bold; color: #e8b86d; margin-top: 5px; }
        input { width: 100%; padding: 10px; margin: 8px 0 16px; background: #141414; border: 1px solid #2a2a2a; color: #fff; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #e8b86d; border: none; font-weight: bold; border-radius: 6px; cursor: pointer; }
        .btn-back { display: inline-block; color: #7a7060; text-decoration: none; margin-bottom: 20px; }
        .btn-logout { display: block; text-align: center; color: #e07070; text-decoration: none; font-size: 13px; margin-top: 15px; }
    </style>
</head>
<body>
    <a href="index_cliente.php<?= $mesa_param ?>" class="btn-back">← Volver al Menú</a>
    
    <div class="card puntos-box">
        <div style="font-size:12px; color:#7a7060; text-transform:uppercase;">Tus Puntos Acumulados</div>
        <div class="puntos-val"><?= (int)($cliente['puntos'] ?? 0) ?> pts</div>
        <small style="color:#7a7060;">Acumulas 1 punto por cada $10 de compra</small>
    </div>

    <div class="card">
        <h3>Mis Datos Personales</h3>
        <?php if ($mensaje): ?><p style="color:#6dbf8a; font-size:13px;"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
        <form method="POST">
            <label>Nombre</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>" required>
            
            <label>Correo Electrónico (No editable)</label>
            <input type="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" disabled style="opacity:0.6;">
            
            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
            
            <button type="submit">Guardar Cambios</button>
        </form>
        <a href="logout_cliente.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</body>
</html>