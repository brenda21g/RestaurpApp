<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Login de Administradores (Restaurant_App)
// ==========================================================================
require_once __DIR__ . '/../config/config.php';

// Redirigir si ya existe una sesión activa
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Mensaje opcional si viene de restablecer contraseña exitosamente
if (isset($_GET['exito']) && $_GET['exito'] === 'password_actualizado') {
    $exito_msg = "Contraseña actualizada con éxito. Ya puedes iniciar sesión.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id, username, password_hash, nombre, rol, pin FROM admins WHERE username = ? AND activo = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificación mediante MD5 y existencia del usuario
            if ($admin && md5($password) === $admin['password_hash']) {
                
                // RESTRICCIÓN: Solo permitir acceso a usuarios con rol 'gerente'
                if ($admin['rol'] !== 'gerente') {
                    $error = "Acceso denegado. Este panel es exclusivo para gerentes.";
                } else {
                    session_regenerate_id(true);

                    // Si el gerente tiene un PIN configurado, solicitar el segundo factor
                    if (!empty($admin['pin'])) {
                        $_SESSION['temp_admin_id'] = $admin['id'];
                        $_SESSION['requires_pin'] = true;
                        header('Location: verificar_pin.php');
                        exit;
                    }

                    // Si es gerente sin PIN de segundo paso configurado, acceso directo
                    $_SESSION['admin_id']     = $admin['id'];
                    $_SESSION['admin_nombre'] = $admin['nombre'];
                    $_SESSION['admin_user']   = $admin['username'];
                    $_SESSION['admin_rol']    = $admin['rol'];
                    $_SESSION['loggedin']     = true;

                    $update = $db->prepare("UPDATE admins SET ultimo_login = NOW() WHERE id = ?");
                    $update->execute([$admin['id']]);

                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "Error en el sistema. Intenta de nuevo más tarde.";
        }
    } else {
        $error = 'Por favor completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión – Restaurant_App</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #011139;
        --bg-surface: #ffffff;
        --color-primary: #0284c7;
        --color-primary-hover: #0369a1;
        --text-main: #000000;
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
    .login-wrap { width: 100%; max-width: 400px; padding: 20px; }
    .logo-area { text-align: center; margin-bottom: 28px; }
    .logo-icon { font-size: 40px; display: block; margin-bottom: 10px; }
    .logo-title { font-size: 22px; font-weight: 700; color: #ffffff; }
    .logo-sub { color: var(--text-muted); font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; margin-top: 4px; }
    .card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 32px; box-shadow: var(--shadow-lg); }
    .card h2 { font-size: 16px; font-weight: 600; margin-bottom: 20px; color: var(--text-main); }
    .field { margin-bottom: 16px; }
    label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    input { width: 100%; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 11px 14px; color: var(--text-main); font-family: inherit; font-size: 14px; transition: border-color .2s, box-shadow .2s; outline: none; }
    input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1); }
    .btn { width: 100%; background: var(--color-primary); color: #ffffff; border: none; border-radius: 8px; padding: 12px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 8px; transition: background-color .2s; }
    .btn:hover { background: var(--color-primary-hover); }
    .error-msg { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 10px 14px; color: var(--color-danger); font-size: 13px; font-weight: 500; margin-bottom: 16px; }
    .success-msg { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 10px 14px; color: var(--color-success); font-size: 13px; font-weight: 500; margin-bottom: 16px; }
    .forgot-link { display: block; text-align: right; font-size: 12px; color: var(--color-primary); text-decoration: none; margin-top: -8px; margin-bottom: 16px; font-weight: 500; }
    .forgot-link:hover { text-decoration: underline; }
    .hint { text-align: center; color: var(--text-muted); font-size: 11px; margin-top: 24px; line-height: 1.4; }
</style>
</head>
<body>
<div class="login-wrap">
    <div class="logo-area">
        <span class="logo-icon">🍽️</span>
        <div class="logo-title">Restaurant_App</div>
        <div class="logo-sub">Panel Gerencial Administrativo</div>
    </div>

    <div class="card">
        <h2>Iniciar sesión</h2>

        <?php if (!empty($exito_msg)): ?>
            <div class="success-msg">✅ <?= htmlspecialchars($exito_msg) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Usuario</label>
                <input type="text" name="username" placeholder="Ingresa tu usuario" required autocomplete="username">
            </div>
            <div class="field">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <a href="recuperar.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
            <button type="submit" class="btn">Entrar al panel →</button>
        </form>

        <p class="hint">Derechos reservados Eloy y Brenda S.A. de S.V.</p>
    </div>
</div>
</body>
</html>