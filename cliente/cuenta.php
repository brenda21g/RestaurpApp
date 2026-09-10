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
$tipo_alerta = 'success'; // success o error

// Procesamiento de formulario (Actualizar datos o Enviar Evaluación)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_perfil'])) {
        $nombre   = sanitize($_POST['nombre'] ?? '');
        $telefono = sanitize($_POST['telefono'] ?? '');

        if (!empty($nombre)) {
            $update = $db->prepare("UPDATE usuarios_cliente SET nombre = ?, telefono = ? WHERE id = ?");
            $update->execute([$nombre, $telefono, $cliente_id]);
            
            $_SESSION['cliente_nombre'] = $nombre;
            $mensaje = "Datos actualizados correctamente.";
        } else {
            $tipo_alerta = 'error';
            $mensaje = "El nombre no puede estar vacío.";
        }
    } elseif (isset($_POST['enviar_evaluacion'])) {
        $puntuacion = intval($_POST['puntuacion'] ?? 5);
        $tipo       = sanitize($_POST['tipo'] ?? 'Servicio');
        $comentario = sanitize($_POST['comentario'] ?? '');

        if ($puntuacion >= 1 && $puntuacion <= 5 && !empty($comentario)) {
            $stmt_ev = $db->prepare("INSERT INTO evaluaciones (cliente_id, puntuacion, tipo, comentario, fecha) VALUES (?, ?, ?, ?, NOW())");
            $stmt_ev->execute([$cliente_id, $puntuacion, $tipo, $comentario]);
            $mensaje = "¡Gracias por tu opinión! Evaluación enviada con éxito.";
        } else {
            $tipo_alerta = 'error';
            $mensaje = "La puntuación debe ser de 1 a 5 y el comentario es obligatorio.";
        }
    }
}

// Obtener datos del cliente
$stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener las evaluaciones de este cliente
$stmt_evs = $db->prepare("SELECT * FROM evaluaciones WHERE cliente_id = ? ORDER BY fecha DESC");
$stmt_evs->execute([$cliente_id]);
$mis_evaluaciones = $stmt_evs->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Mi Cuenta y Evaluaciones – RestaurApp</title>
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
        input, select, textarea { 
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
        input:focus, select:focus, textarea:focus { border-color: var(--accent); }
        input[disabled] { opacity: 0.6; cursor: not-allowed; }
        textarea { resize: vertical; min-height: 80px; }
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
        .alert { padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; text-align: center; }
        .alert-success { background: rgba(109,191,138,.15); color: #6dbf8a; border: 1px solid rgba(109,191,138,.3); }
        .alert-error { background: rgba(224,112,112,.15); color: #e07070; border: 1px solid rgba(224,112,112,.3); }
        
        .eval-item {
            background: #121212;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .stars { color: #e8b86d; font-weight: bold; }
    </style>
</head>
<body>

    <a href="index.php<?= $mesa_param ?>" class="btn-back">← Volver al Menú</a>
    
    <!-- Bloque de Puntos -->
    <div class="card puntos-box">
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:1px;">Tus Puntos Acumulados</div>
        <div class="puntos-val"><?= (int)($cliente['puntos'] ?? 0) ?> pts</div>
        <small style="color:var(--muted); font-size:12px;">Acumulas 1 punto por cada $10 de compra</small>
    </div>

    <!-- Formulario Datos Personales -->
    <div class="card">
        <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:16px;">Mis Datos Personales</h3>
        
        <?php if ($mensaje && isset($_POST['actualizar_perfil'])): ?>
            <div class="alert alert-<?= $tipo_alerta ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="actualizar_perfil" value="1">
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
    </div>

    <!-- Formulario de Evaluaciones / Reseñas -->
    <div class="card">
        <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:16px;">⭐ Califícanos</h3>
        
        <?php if ($mensaje && isset($_POST['enviar_evaluacion'])): ?>
            <div class="alert alert-<?= $tipo_alerta ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="enviar_evaluacion" value="1">
            <div class="form-group">
                <label>Puntuación</label>
                <select name="puntuacion" required>
                    <option value="5">⭐⭐⭐⭐⭐ (5 - Excelente)</option>
                    <option value="4">⭐⭐⭐⭐ (4 - Muy bueno)</option>
                    <option value="3">⭐⭐⭐ (3 - Bueno)</option>
                    <option value="2">⭐⭐ (2 - Regular)</option>
                    <option value="1">⭐ (1 - Malo)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Categoría</label>
                <select name="tipo" required>
                    <option value="Servicio">Servicio</option>
                    <option value="Comida">Comida</option>
                    <option value="Ambiente">Ambiente</option>
                    <option value="General">General</option>
                </select>
            </div>

            <div class="form-group">
                <label>Comentario</label>
                <textarea name="comentario" placeholder="Cuéntanos tu experiencia..." required></textarea>
            </div>

            <button type="submit">Enviar Evaluación</button>
        </form>

        <div style="margin-top: 20px;">
            <label style="margin-bottom: 10px;">Mis Evaluaciones Anteriores</label>
            <?php if (empty($mis_evaluaciones)): ?>
                <p style="color: var(--muted); font-size: 12px;">Aún no has registrado opiniones.</p>
            <?php else: ?>
                <?php foreach ($mis_evaluaciones as $ev): ?>
                    <div class="eval-item">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span><b><?= htmlspecialchars($ev['tipo']) ?></b></span>
                            <span class="stars"><?= str_repeat('★', $ev['puntuacion']) ?></span>
                        </div>
                        <p style="color:var(--text); margin-bottom:4px;"><?= htmlspecialchars($ev['comentario']) ?></p>
                        <small style="color:var(--muted);"><?= $ev['fecha'] ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <a href="logout_cliente.php<?= $mesa_param ?>" class="btn-logout">🚪 Cerrar Sesión</a>
    </div>

</body>
</html>