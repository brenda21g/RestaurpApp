<?php
/**
 * Archivo: subgerente/login.php (o el nombre de tu archivo de subgerencia)
 * Descripción: Login exclusivo del módulo de subgerencia con diseño en tonos azul cobalto y azul acentuado.
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

                // Validar que el usuario tenga permitido entrar a subgerencia (subgerente, gerente o super_admin)
                $rol = $admin['rol'];
                if ($rol !== 'subgerente' && $rol !== 'gerente' && $rol !== 'super_admin') {
                    $error = "Esta cuenta no tiene permisos para acceder al panel de subgerencia.";
                } else {
                    // Prevenir Session Fixation
                    session_regenerate_id(true);

                    // Si es Super Administrador y tiene un PIN configurado, pedir PIN en segundo paso
                    if ($admin['rol'] === 'super_admin' && !empty($admin['pin'])) {
                        $_SESSION['temp_admin_id'] = $admin['id'];
                        $_SESSION['requires_pin'] = true;

                        header('Location: verificar_pin.php');
                        exit;
                    }

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

                    // REDIRECCIÓN EXCLUSIVA AL DASHBOARD DE SUBGERENCIA
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
<title>Subgerencia – RestaurApp</title>

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
    --border: #bae6fd; /* Borde azul cielo suave */
    --text: #071b3a;
    --muted: #60708a;
    --sidebar: #0284c7;        /* Azul cobalto / brillante principal para subgerencia */
    --sidebar-hover: #0369a1;  /* Azul cobalto oscuro para hover */
    --accent-blue: #0284c7;
    --red: #ef4444;
}

/* =========================
   CUERPO
========================= */

body {
    /* Fondo con un degradado elegante combinando azul muy suave y matices celestes */
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
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

/* Decoración de fondo difuminada */
body::before {
    content: "";
    position: fixed;
    width: 480px;
    height: 480px;
    background: rgba(2, 132, 199, 0.10);
    border-radius: 50%;
    top: -180px;
    left: -150px;
    pointer-events: none;
}

body::after {
    content: "";
    position: fixed;
    width: 520px;
    height: 520px;
    background: rgba(14, 116, 144, 0.08);
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
    filter: drop-shadow(0 4px 8px rgba(2, 132, 199, 0.2));
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
    background: var(--sidebar);
    padding: 5px 14px;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
}

/* =========================
   TARJETA
========================= */

.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 36px;
    box-shadow: 0 12px 30px rgba(2, 132, 199, 0.08);
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
    border-color: var(--sidebar);
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

input:hover {
    border-color: #94a3b8;
}

/* =========================
   BOTÓN
========================= */

.btn {
    width: 100%;
    background: linear-gradient(135deg, #0f172a 0%, var(--sidebar) 100%);
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
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
}

.btn:hover {
    background: linear-gradient(135deg, #0f172a 0%, var(--sidebar-hover) 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 15px rgba(2, 132, 199, 0.35);
}

.btn:active {
    transform: translateY(0);
}

/* =========================
   MENSAJE DE ERROR
========================= */

.error-msg {
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 11px 14px;
    color: #b91c1c;
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
</style>
</head>

<body>

<div class="login-wrap">

    <div class="logo-area">
        <span class="logo-icon">📊</span>

        <div class="logo-title">
            RestaurApp
        </div>

        <div class="logo-sub">
            Módulo de Subgerencia
        </div>
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
                Entrar a Subgerencia →
            </button>

        </form>

        <p class="hint">
            Derechos reservados BrendaEloy S.A. de C.V
        </p>

    </div>

</div>

</body>
</html>