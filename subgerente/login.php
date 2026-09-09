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
            
           $stmt = $db->prepare("SELECT id, username, password_hash, nombre, rol, pin FROM admins WHERE username = ? AND activo = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificación mediante MD5 y existencia del usuario
            if ($admin && md5($password) === $admin['password_hash']) {
                // Prevenir Session Fixation
                session_regenerate_id(true);

                // Si es Super Administrador y tiene un PIN configurado, pedir PIN en segundo paso
                if ($admin['rol'] === 'super_admin' && !empty($admin['pin'])) {
                    $_SESSION['temp_admin_id'] = $admin['id'];
                    $_SESSION['requires_pin'] = true;
                    header('Location: verificar_pin.php');
                    exit;
                }

                // Para administradores menores o superadmins sin PIN configurado
                $_SESSION['admin_id']     = $admin['id'];
                $_SESSION['admin_nombre'] = $admin['nombre'];
                $_SESSION['admin_user']   = $admin['username'];
                $_SESSION['admin_rol']    = $admin['rol'];
                $_SESSION['loggedin']     = true;

                // Actualizar último login
                $update = $db->prepare("UPDATE admins SET ultimo_login = NOW() WHERE id = ?");
                $update->execute([$admin['id']]);

                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "Error en el sistema. Intenta de nuevo más tarde.";
            // Registrar $e->getMessage() en el log del servidor si es necesario
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
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0f0f0f;
    --card: #1a1a1a;
    --border: #2a2a2a;
    --accent: #e8b86d;
    --accent2: #c9956a;
    --text: #f0ede8;
    --muted: #8a8070;
    --error: #e07070;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-image: radial-gradient(ellipse at 20% 50%, #1a1208 0%, transparent 60%),
                      radial-gradient(ellipse at 80% 20%, #12100a 0%, transparent 50%);
  }

  .login-wrap {
    width: 100%;
    max-width: 420px;
    padding: 20px;
  }

  .logo-area {
    text-align: center;
    margin-bottom: 40px;
  }

  .logo-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 12px;
    filter: drop-shadow(0 0 20px rgba(232,184,109,0.4));
  }

  .logo-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    color: var(--accent);
    letter-spacing: -0.5px;
  }

  .logo-sub {
    color: var(--muted);
    font-size: 13px;
    font-weight: 300;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 4px;
  }

  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 40px 80px rgba(0,0,0,0.5);
  }

  .card h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    margin-bottom: 28px;
    color: var(--text);
  }

  .field {
    margin-bottom: 20px;
  }

  label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
  }

  input {
    width: 100%;
    background: #111;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    transition: border-color .2s;
    outline: none;
  }

  input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(232,184,109,0.1);
  }

  .btn {
    width: 100%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #0f0f0f;
    border: none;
    border-radius: 8px;
    padding: 15px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    margin-top: 8px;
    transition: opacity .2s, transform .1s;
  }

  .btn:hover { opacity: .9; }
  .btn:active { transform: scale(.98); }

  .error-msg {
    background: rgba(224,112,112,0.1);
    border: 1px solid rgba(224,112,112,0.3);
    border-radius: 8px;
    padding: 12px 16px;
    color: var(--error);
    font-size: 14px;
    margin-bottom: 20px;
  }

  .hint {
    text-align: center;
    color: var(--muted);
    font-size: 12px;
    margin-top: 20px;
  }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="logo-area">
    <span class="logo-icon">🍽️</span>
    <div class="logo-title">RestaurApp</div>
    <div class="logo-sub">Panel Administrativo</div>
  </div>

  <div class="card">
    <h2>Iniciar sesión</h2>

    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= $error ?></div>
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
      <button type="submit" class="btn">Entrar al panel →</button>
    </form>

    <p class="hint">Derechos reservados BrendaEloy S.A. de C.V</p>
  </div>
</div>
</body>
</html>