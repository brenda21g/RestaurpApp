<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Recuperación de Contraseña por Correo (Restaurant_App)
// ==========================================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mail.php';

$error = '';
$mensaje = '';
$paso = $_SESSION['recuperar_paso'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $db = getDB();

    if ($accion === 'enviar_pin') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!empty($username) && !empty($email)) {
            $stmt = $db->prepare("SELECT id, nombre FROM admins WHERE username = ? AND email = ? AND activo = 1");
            $stmt->execute([$username, $email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                $pin_recuperacion = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $pin_expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $update = $db->prepare("UPDATE admins SET pin_recuperacion = ?, pin_expira = ? WHERE id = ?");
                $update->execute([$pin_recuperacion, $pin_expira, $admin['id']]);

                $enviado = enviarCorreoRecuperacion($email, $admin['nombre'], $pin_recuperacion);

                if ($enviado) {
                    $_SESSION['recuperar_admin_id'] = $admin['id'];
                    $_SESSION['recuperar_paso'] = 2;
                    $paso = 2;
                    $mensaje = "Hemos enviado un PIN de verificación a tu correo electrónico.";
                } else {
                    $error = "Error al enviar el correo. Configura correctamente los datos SMTP en mail.php.";
                }
            } else {
                $error = "No se encontró ninguna cuenta activa con ese usuario y correo.";
            }
        } else {
            $error = "Por favor completa todos los campos.";
        }
    } elseif ($accion === 'verificar_pin') {
        $pin_ingresado = trim($_POST['pin'] ?? '');
        $admin_id = $_SESSION['recuperar_admin_id'] ?? 0;

        $stmt = $db->prepare("SELECT pin_recuperacion, pin_expira FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        $ahora = date('Y-m-d H:i:s');

        if ($admin && $admin['pin_recuperacion'] === $pin_ingresado) {
            if ($admin['pin_expira'] && $admin['pin_expira'] < $ahora) {
                $error = "El PIN ha expirado. Solicita uno nuevo.";
                $paso = 1;
                unset($_SESSION['recuperar_paso']);
            } else {
                $_SESSION['recuperar_paso'] = 3;
                $paso = 3;
            }
        } else {
            $error = "PIN incorrecto.";
            $paso = 2;
        }
    } elseif ($accion === 'cambiar_password') {
        $nueva_pass = $_POST['nueva_password'] ?? '';
        $admin_id = $_SESSION['recuperar_admin_id'] ?? 0;

        if (!empty($nueva_pass)) {
            $nuevo_hash = md5($nueva_pass);

            $update = $db->prepare("UPDATE admins SET password_hash = ?, pin_recuperacion = NULL, pin_expira = NULL WHERE id = ?");
            $update->execute([$nuevo_hash, $admin_id]);

            unset($_SESSION['recuperar_paso']);
            unset($_SESSION['recuperar_admin_id']);

            header('Location: login.php?exito=password_actualizado');
            exit;
        } else {
            $error = "Ingresa una nueva contraseña válida.";
            $paso = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar Contraseña – Restaurant_App</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #002e5c;
        --bg-surface: #ffffff;
        --color-primary: #0284c7;
        --color-primary-hover: #0369a1;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-danger: #ef4444;
        --color-success: #10b981;
        --radius: 10px;
        --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        background-color: var(--bg-body);
        color: var(--text-main);
        font-family: 'Inter', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .wrap { width: 100%; max-width: 400px; padding: 20px; }
    .card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 32px; box-shadow: var(--shadow-lg); }
    h2 { font-size: 18px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
    p { color: var(--text-muted); font-size: 13px; margin-bottom: 20px; line-height: 1.4; }
    .field { margin-bottom: 16px; }
    label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    input { width: 100%; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 11px 14px; color: var(--text-main); font-family: inherit; font-size: 14px; outline: none; }
    input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1); }
    .btn { width: 100%; background: var(--color-primary); color: #ffffff; border: none; border-radius: 8px; padding: 12px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 8px; transition: background-color .2s; }
    .btn:hover { background: var(--color-primary-hover); }
    .error-msg { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 10px 14px; color: var(--color-danger); font-size: 13px; font-weight: 500; margin-bottom: 16px; }
    .success-msg { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 10px 14px; color: var(--color-success); font-size: 13px; font-weight: 500; margin-bottom: 16px; }
    .back { display: block; text-align: center; margin-top: 16px; color: var(--text-muted); font-size: 13px; text-decoration: none; }
    .back:hover { color: var(--color-primary); }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>Recuperar Contraseña</h2>
    
    <?php if ($error): ?><div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($mensaje): ?><div class="success-msg">✅ <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <?php if ($paso == 1): ?>
        <p>Ingresa tu nombre de usuario y correo electrónico registrado para recibir un PIN.</p>
        <form method="POST">
            <input type="hidden" name="accion" value="enviar_pin">
            <div class="field">
                <label>Usuario</label>
                <input type="text" name="username" placeholder="Tu usuario" required>
            </div>
            <div class="field">
                <label>Correo Electrónico</label>
                <input type="email" name="email" placeholder="correo@ejemplo.com" required>
            </div>
            <button type="submit" class="btn">Enviar PIN de recuperación →</button>
        </form>

    <?php elseif ($paso == 2): ?>
        <p>Introduce el PIN de 6 dígitos que enviamos a tu correo electrónico.</p>
        <form method="POST">
            <input type="hidden" name="accion" value="verificar_pin">
            <div class="field">
                <label>PIN Numérico</label>
                <input type="text" name="pin" maxlength="6" placeholder="123456" style="text-align: center; font-size: 20px; letter-spacing: 4px;" required autofocus>
            </div>
            <button type="submit" class="btn">Verificar PIN →</button>
        </form>

    <?php elseif ($paso == 3): ?>
        <p>¡PIN verificado con éxito! Ahora ingresa tu nueva contraseña.</p>
        <form method="POST">
            <input type="hidden" name="accion" value="cambiar_password">
            <div class="field">
                <label>Nueva Contraseña</label>
                <input type="password" name="nueva_password" placeholder="••••••••" required autofocus>
            </div>
            <button type="submit" class="btn">Actualizar Contraseña →</button>
        </form>
    <?php endif; ?>

    <a href="login.php" class="back">← Volver al inicio de sesión</a>
  </div>
</div>
</body>
</html>