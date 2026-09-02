<?php
require_once __DIR__ . '/../config/config.php';

// Verificación única de sesión cliente
if (!isset($_SESSION['cliente_id'])) {
    header('Location: login_cliente.php');
    exit;
}

$db = getDB();
$cliente_id = (int)$_SESSION['cliente_id'];
$mensaje = '';

// Procesamiento de formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = sanitize($_POST['nombre'] ?? '');
    $telefono = sanitize($_POST['telefono'] ?? '');

    if (!empty($nombre)) {
        $update = $db->prepare("UPDATE usuarios_cliente SET nombre = ?, telefono = ? WHERE id = ?");
        $update->execute([$nombre, $telefono, $cliente_id]);
        
        $_SESSION['cliente_nombre'] = $nombre;
        $mensaje = "Datos actualizados correctamente.";
    } else {
        $mensaje = "El nombre no puede estar vacío.";
    }
}

// Obtener datos del cliente
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta – RestaurApp</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
          --bg: #0b0b0b; --card: #1c1c1c; --border: #2a2a2a;
          --accent: #e8b86d; --text: #f0ede8; --muted: #7a7060;
        }
        body { 
            background: var(--bg); 
            color: var(--text); 
            font-family: 'DM Sans', sans-serif; 
            padding: 24px 16px; 
            max-width: 480px; 
            margin: 0 auto; 
        }
        .btn-back { 
            display: inline-block; 
            color: var(--accent); 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 500; 
            margin-bottom: 20px; 
        }
        .card { 
            background: var(--card); 
            border: 1px solid var(--border); 
            padding: 24px; 
            border-radius: 16px; 
            margin-bottom: 20px; 
        }
        .puntos-box { 
            background: rgba(232, 184, 109, 0.08); 
            border: 1px solid var(--accent); 
            border-radius: 12px; 
            padding: 20px; 
            text-align: center; 
        }
        .puntos-val { 
            font-family: 'Playfair Display', serif;
            font-size: 36px; 
            font-weight: bold; 
            color: var(--accent); 
            margin: 6px 0; 
        }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        input { 
            width: 100%; 
            padding: 12px 14px; 
            background: #121212; 
            border: 1px solid var(--border); 
            color: var(--text); 
            border-radius: 8px; 
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            outline: none; 
        }
        input:focus { border-color: var(--accent); }
        input[disabled] { opacity: 0.6; cursor: not-allowed; }
        button { 
            width: 100%; 
            padding: 12px; 
            background: linear-gradient(135deg, #e8b86d, #c9956a); 
            color: #0f0f0f;
            border: none; 
            font-family: 'DM Sans', sans-serif;
            font-weight: 600; 
            font-size: 14px;
            border-radius: 8px; 
            cursor: pointer; 
            margin-top: 8px;
        }
        .btn-logout { 
            display: block; 
            text-align: center; 
            color: #e07070; 
            text-decoration: none; 
            font-size: 13px; 
            margin-top: 18px; 
        }
        .btn-logout:hover { text-decoration: underline; }
        .alert { padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; text-align: center; background: rgba(109,191,138,.15); color: #6dbf8a; border: 1px solid rgba(109,191,138,.3); }
    </style>
</head>
<body>

    <a href="index_cliente.php<?= $mesa_param ?>" class="btn-back">← Volver al Menú</a>
    
    <!-- Bloque de Puntos -->
    <div class="card puntos-box">
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:1px;">Tus Puntos Acumulados</div>
        <div class="puntos-val"><?= (int)($cliente['puntos'] ?? 0) ?> pts</div>
        <small style="color:var(--muted); font-size:12px;">Acumulas 1 punto por cada $10 de compra</small>
    </div>

    <!-- Formulario Datos Personales -->
    <div class="card">
        <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:16px;">Mis Datos Personales</h3>
        
        <?php if ($mensaje): ?>
            <div class="alert"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nombre Completo</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Correo Electrónico (No editable)</label>
                <input type="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" disabled>
            </div>
            
            <div class="form-group">
                <label>Teléfono</label>
                <input type="tel" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>" placeholder="Ej. 5512345678">
            </div>
            
            <button type="submit">Guardar Cambios</button>
        </form>
        
        <a href="logout_cliente.php<?= $mesa_param ?>" class="btn-logout">🚪 Cerrar Sesión</a>
    </div>

</body>
</html>