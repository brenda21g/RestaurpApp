<?php
/**
 * Archivo: login.php
 * Descripción: Controlador y vista de inicio de sesión para administradores con rol de gerente (Diseño Azul y Rojo Elegante).
 */
require_once __DIR__ . '/../config/config.php';

// Redirigir si ya existe una sesión activa
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$exito_msg = '';

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
<title>Iniciar sesión – Panel Gerencial</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #011139;
        --bg-surface: #ffffff;
        --color-primary: #dc2626; /* Rojo elegante y distintivo para gerencia */
        --color-primary-hover: #b91c1c;
        --text-main: #071b3a;
        --text-muted: #64748b;
        --border-color: #fecaca;
        --color-danger: #ef4444;
        --color-success: #10b981;
        --radius: 10px;
        --shadow-lg: 0 12px 35px rgba(220, 38, 38, 0.12);
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        /* Fondo con degradado sofisticado entre el azul marino institucional y un toque rojo gerencial */
        background: linear-gradient(135deg, #011139 0%, #1e1b4b 50%, #290f1a 100%);
        color: var(--text-main);
        font-family: 'Inter', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow-x: hidden;
    }

    /* Elementos decorativos de fondo en las esquinas */
    body::before {
        content: "";
        position: fixed;
        width: 480px;
        height: 480px;
        background: rgba(2, 132, 199, 0.12);
        border-radius: 50%;
        top: -180px;
        left: -150px;
        pointer-events: none;
    }

    body::after {
        content: "";
        position: fixed;
        width: 500px;
        height: 500px;
        background: rgba(220, 38, 38, 0.12);
        border-radius: 50%;
        bottom: -250px;
        right: -180px;
        pointer-events: none;
    }

    .login-wrap {
        width: 100%;
        max-width: 400px;
        padding: 20px;
        position: relative;
        z-index: 1;
    }

    .logo-area {
        text-align: center;
        margin-bottom: 28px;
    }

    .logo-icon {
        font-size: 42px;
        display: block;
        margin-bottom: 10px;
        filter: drop-shadow(0 4px 8px rgba(220, 38, 38, 0.3));
    }

    .logo-title {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        font-weight: 700;
        color: #ffffff;
    }

    .logo-sub {
        color: #ffffff;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-top: 6px;
        display: inline-block;
        background: var(--color-primary);
        padding: 5px 12px;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
    }

    .card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 32px;
        box-shadow: var(--shadow-lg);
    }

    .card h2 {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 20px;
        color: var(--text-main);
    }

    .field {
        margin-bottom: 16px;
    }

    label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 6px;
    }

    input {
        width: 100%;
        background: var(--bg-surface);
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 11px 14px;
        color: var(--text-main);
        font-family: inherit;
        font-size: 14px;
        transition: border-color .2s, box-shadow .2s;
        outline: none;
    }

    input:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
    }

    .btn {
        width: 100%;
        background: linear-gradient(135deg, #011139 0%, var(--color-primary) 100%);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 12px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 8px;
        transition: all .2s;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }

    .btn:hover {
        opacity: 0.95;
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(220, 38, 38, 0.35);
    }

    .forgot-link {
        display: block;
        text-align: right;
        font-size: 12px;
        color: var(--color-primary);
        text-decoration: none;
        margin-top: -8px;
        margin-bottom: 16px;
        font-weight: 500;
    }

    .forgot-link:hover {
        text-decoration: underline;
    }

    .error-msg {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 8px;
        padding: 10px 14px;
        color: var(--color-danger);
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 16px;
    }

    .success-msg {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 8px;
        padding: 10px 14px;
        color: var(--color-success);
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 16px;
    }

    .hint {
        text-align: center;
        color: #94a3b8;
        font-size: 11px;
        margin-top: 24px;
        line-height: 1.4;
    }
</style>
</head>
<body>
<div class="login-wrap">
    <div class="logo-area">
        <span class="logo-icon">👑</span>
        <div class="logo-title">RestaurApp</div>
        <div class="logo-sub">Panel Gerencial ERP</div>
    </div>

    <div class="card">
        <h2>Iniciar sesión</h2>

        <?php if (!empty($exito_msg)): ?>
            <div class="success-msg">✅ <?= htmlspecialchars($exito_msg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-msg">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
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