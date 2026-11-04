<?php
/**
 * Archivo: logistica/login.php
 * Descripción: Login exclusivo del módulo de logística con fondo combinado en tonos azul y verde.
 */
require_once __DIR__ . '/../config/config.php';

// Redirigir si ya existe una sesión activa
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $db = getDB();

            $stmt = $db->prepare("
                SELECT id, username, password_hash, nombre, rol, pin
                FROM admins
                WHERE username = ? AND activo = 1
            ");

            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificación mediante MD5 y existencia del usuario
            if ($admin && md5($password) === $admin['password_hash']) {

                // Validar que el usuario tenga permitido entrar a logística (logistica o gerente)
                $rol = $admin['rol'];
                if ($rol !== 'logistica' && $rol !== 'gerente' && $rol !== 'super_admin') {
                    $error = "Esta cuenta no tiene permisos para acceder al panel de logística.";
                } else {
                    // Prevenir Session Fixation
                    session_regenerate_id(true);

                    // Guardar variables de sesión
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_nombre'] = $admin['nombre'];
                    $_SESSION['admin_user'] = $admin['username'];
                    $_SESSION['admin_rol'] = $admin['rol'];
                    $_SESSION['loggedin'] = true;

                    // Actualizar último login
                    $update = $db->prepare("
                        UPDATE admins
                        SET ultimo_login = NOW()
                        WHERE id = ?
                    ");
                    $update->execute([$admin['id']]);

                    // REDIRECCIÓN EXCLUSIVA AL DASHBOARD DE LOGÍSTICA
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
<title>Logística – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --card: #ffffff;
    --border: #a7f3d0; /* Borde verde pastel vivo */
    --text: #071b3a;
    --muted: #60708a;
    --sidebar: #000049; /* Azul marino institucional */
    --green: #059669;
    --red: #ef4444;
}

body {
    /* Fondo con un degradado dinámico combinando verde agua y azul suave */
    background: linear-gradient(135deg, #e6f4ea 0%, #e0f2fe 100%);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    position: relative;
    overflow-x: hidden;
}

/* Círculo decorativo superior izquierdo (Azul institucional) */
body::before {
    content: "";
    position: fixed;
    width: 480px;
    height: 480px;
    background: rgba(2, 132, 196, 0.12);
    border-radius: 50%;
    top: -180px;
    left: -150px;
    pointer-events: none;
}

/* Círculo decorativo inferior derecho (Verde esmeralda fuerte para el fondo) */
body::after {
    content: "";
    position: fixed;
    width: 520px;
    height: 520px;
    background: rgba(16, 185, 129, 0.12);
    border-radius: 50%;
    bottom: -250px;
    right: -180px;
    pointer-events: none;
}

.login-wrap {
    width: 100%;
    max-width: 420px;
    padding: 20px;
    position: relative;
    z-index: 1;
}

.logo-area {
    text-align: center;
    margin-bottom: 28px;
}

.logo-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 10px;
    filter: drop-shadow(0 4px 8px rgba(5, 150, 105, 0.25));
}

.logo-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.5px;
}

.logo-sub {
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-top: 6px;
    display: inline-block;
    background: #059669;
    padding: 5px 14px;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.2);
}

.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 36px;
    box-shadow: 0 12px 35px rgba(5, 150, 105, 0.1);
}

.card h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 26px;
    color: var(--text);
}

.field {
    margin-bottom: 20px;
}

label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
}

input {
    width: 100%;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 13px 14px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    transition: all .2s;
    outline: none;
}

input::placeholder {
    color: #9aa8bb;
}

input:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

input:hover {
    border-color: #94a3b8;
}

.btn {
    width: 100%;
    background: linear-gradient(135deg, var(--sidebar) 0%, var(--green) 100%);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 13px 16px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    margin-top: 6px;
    transition: all .2s;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
}

.btn:hover {
    opacity: 0.95;
    transform: translateY(-1px);
    box-shadow: 0 6px 15px rgba(5, 150, 105, 0.35);
}

.error-msg {
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 11px 14px;
    color: #b91c1c;
    font-size: 13px;
    margin-bottom: 20px;
}

.hint {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    margin-top: 20px;
    line-height: 1.5;
}
</style>
</head>

<body>

<div class="login-wrap">
    <div class="logo-area">
        <span class="logo-icon">📦</span>
        <div class="logo-title">RestaurApp</div>
        <div class="logo-sub">Módulo de Logística</div>
    </div>

    <div class="card">
        <h2>Iniciar sesión</h2>

        <?php if ($error): ?>
            <div class="error-msg">
                ⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Usuario</label>
                <input type="text" name="username" placeholder="Presione para escribir" required autocomplete="username">
            </div>

            <div class="field">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn">
                Entrar a Logística →
            </button>
        </form>

        <p class="hint">
            Derechos reservados BrendaEloy S.A. de C.V
        </p>
    </div>
</div>

</body>
</html>