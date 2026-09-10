<?php
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

                // Prevenir Session Fixation
                session_regenerate_id(true);

                // Si es Super Administrador y tiene un PIN configurado,
                // pedir PIN en segundo paso
                if ($admin['rol'] === 'super_admin' && !empty($admin['pin'])) {
                    $_SESSION['temp_admin_id'] = $admin['id'];
                    $_SESSION['requires_pin'] = true;

                    header('Location: verificar_pin.php');
                    exit;
                }

                // Para administradores menores o superadmins sin PIN configurado
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

                header('Location: dashboard.php');
                exit;

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

<title>Admin – RestaurApp</title>

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
    --bg: #f7f9fc;
    --card: #ffffff;
    --border: #e2e8f0;
    --accent: #ffffff;
    --text: #071b3a;
    --muted: #60708a;
    --sidebar: #03143d;
    --sidebar-hover: #092e70;
    --green: #07543f;
    --green-light: #e4f7f0;
    --blue: #1684c4;
    --red: #ff5b5b;
}

/* =========================
   CUERPO
========================= */

body {
    background: var(--bg);
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

/* Decoración de fondo */

body::before {
    content: "";
    position: fixed;
    width: 450px;
    height: 450px;
    background: rgba(22, 132, 196, 0.06);
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
    background: rgba(3, 20, 61, 0.04);
    border-radius: 50%;
    bottom: -250px;
    right: -180px;
    pointer-events: none;
}

/* =========================
   CONTENEDOR
========================= */

.login-wrap {
    width: 100%;
    max-width: 420px;
    padding: 20px;
    position: relative;
    z-index: 1;
}

/* =========================
   LOGO
========================= */

.logo-area {
    text-align: center;
    margin-bottom: 28px;
}

.logo-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 10px;
    filter: drop-shadow(0 4px 8px rgba(3, 20, 61, 0.15));
}

.logo-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.5px;
}

.logo-sub {
    color: var(--muted);
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-top: 5px;
}

/* =========================
   TARJETA
========================= */

.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 36px;
    box-shadow: 0 12px 30px rgba(7, 27, 58, 0.08);
}

.card h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 26px;
    color: var(--text);
}

/* =========================
   CAMPOS
========================= */

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
    border: 1px solid var(--border);
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
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(22, 132, 196, 0.10);
}

input:hover {
    border-color: #cbd5e1;
}

/* =========================
   BOTÓN
========================= */

.btn {
    width: 100%;
    background: var(--sidebar);
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
}

.btn:hover {
    background: var(--sidebar-hover);
    transform: translateY(-1px);
    box-shadow: 0 5px 12px rgba(3, 20, 61, 0.15);
}

.btn:active {
    transform: translateY(0);
}

/* =========================
   MENSAJE DE ERROR
========================= */

.error-msg {
    background: #fde9eb;
    border: 1px solid #f4c7cc;
    border-radius: 8px;
    padding: 11px 14px;
    color: #c8424c;
    font-size: 13px;
    margin-bottom: 20px;
}

/* =========================
   TEXTO INFERIOR
========================= */

.hint {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    margin-top: 20px;
    line-height: 1.5;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 600px) {
    .login-wrap {
        max-width: 390px;
        padding: 16px;
    }

    .logo-area {
        margin-bottom: 22px;
    }

    .logo-icon {
        font-size: 42px;
    }

    .logo-title {
        font-size: 28px;
    }

    .card {
        padding: 28px 24px;
        border-radius: 14px;
    }

    .card h2 {
        font-size: 20px;
    }
}

@media (max-width: 380px) {
    .login-wrap {
        padding: 12px;
    }

    .card {
        padding: 24px 20px;
    }

    .logo-icon {
        font-size: 38px;
    }

    .logo-title {
        font-size: 26px;
    }
}
</style>
</head>

<body>

<div class="login-wrap">

    <div class="logo-area">
        <span class="logo-icon">🍽️</span>

        <div class="logo-title">
            RestaurApp
        </div>

        <div class="logo-sub">
            Panel Administrativo
        </div>
    </div>

    <div class="card">

        <h2>Iniciar sesión</h2>

        <?php if ($error): ?>
            <div class="error-msg">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="field">
                <label>Usuario</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Presione para escribir"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="field">
                <label>Contraseña</label>

                <input
                    type="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn">
                Entrar al panel →
            </button>

        </form>

        <p class="hint">
            Derechos reservados BrendaEloy S.A. de C.V
        </p>

    </div>

</div>

</body>
</html>