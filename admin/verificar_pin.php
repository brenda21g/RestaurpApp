<?php
require_once __DIR__ . '/../config/config.php';

// Si no hay un proceso de PIN pendiente, regresar al login
if (!isset($_SESSION['requires_pin']) || !isset($_SESSION['temp_admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin_ingresado = trim($_POST['pin'] ?? '');

    if (!empty($pin_ingresado)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, nombre, rol, pin FROM admins WHERE id = ?");
            $stmt->execute([$_SESSION['temp_admin_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Comparar el PIN usando MD5 (o password_verify si usas hashes modernos)
            if ($admin && md5($pin_ingresado) === $admin['pin']) {
                // Limpiar variables temporales y otorgar acceso total
                unset($_SESSION['requires_pin']);
                unset($_SESSION['temp_admin_id']);

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
                $error = "PIN de seguridad incorrecto.";
            }
        } catch (PDOException $e) {
            $error = "Error en el sistema.";
        }
    } else {
        $error = "Por favor ingresa el PIN.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificación PIN – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0f0f0f; --card: #1a1a1a; --border: #2a2a2a;
    --accent: #e8b86d; --accent2: #c9956a; --text: #f0ede8; --muted: #8a8070; --error: #e07070;
  }
  body {
    background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
  }
  .wrap { width: 100%; max-width: 380px; padding: 20px; }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 30px; text-align: center; }
  h2 { font-family: 'Playfair Display', serif; font-size: 20px; margin-bottom: 8px; color: var(--text); }
  p { color: var(--muted); font-size: 13px; margin-bottom: 24px; }
  .field { margin-bottom: 20px; text-align: left; }
  label { display: block; font-size: 12px; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
  input { width: 100%; background: #111; border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; color: var(--text); font-size: 18px; text-align: center; letter-spacing: 4px; outline: none; }
  input:focus { border-color: var(--accent); }
  .btn { width: 100%; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #0f0f0f; border: none; border-radius: 8px; padding: 14px; font-size: 15px; font-weight: 500; cursor: pointer; }
  .error-msg { background: rgba(224,112,112,0.1); border: 1px solid rgba(224,112,112,0.3); border-radius: 8px; padding: 10px; color: var(--error); font-size: 13px; margin-bottom: 16px; }
  .back { display: block; margin-top: 16px; color: var(--muted); font-size: 12px; text-decoration: none; }
  .back:hover { color: var(--text); }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>Seguridad de Super Admin</h2>
    <p>Ingresa tu PIN de seguridad para continuar</p>

    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>PIN Numérico</label>
        <input type="password" name="pin" maxlength="6" placeholder="••••" required autofocus autocomplete="one-time-code">
      </div>
      <button type="submit" class="btn">Verificar PIN →</button>
    </form>
    <a href="login.php" class="back">← Cancelar y volver al login</a>
  </div>
</div>
</body>
</html>