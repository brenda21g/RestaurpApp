<?php
// ==========================================================================
// VISTA Y CONTROLADOR: Verificación de PIN de Super Admin – Restaurant_app
// ==========================================================================
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

            // Comparar el PIN usando MD5 (consistente con el alta de administradores)
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
<title>Verificación PIN – Restaurant_app</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ==========================================================================
        VARIABLES Y RESET GENERAL (TEMA AZUL Y BLANCO - RESTAURANT_APP)
        ========================================================================== */
    :root {
        --bg-body: #002e5c;
        --bg-surface: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-primary: #0284c7;
        --color-primary-hover: #0369a1;
        --color-danger: #ef4444;
        --radius: 12px;
        --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
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

    .wrap {
        width: 100%;
        max-width: 400px;
        padding: 20px;
    }

    .card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 36px 30px;
        box-shadow: var(--shadow-sm);
        text-align: center;
    }

    .app-icon {
        font-size: 32px;
        margin-bottom: 12px;
        display: inline-block;
    }

    h2 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 8px;
    }

    p {
        color: var(--text-muted);
        font-size: 13px;
        margin-bottom: 24px;
        line-height: 1.4;
    }

    .field {
        margin-bottom: 20px;
        text-align: left;
    }

    label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    input {
        width: 100%;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 12px 16px;
        color: var(--text-main);
        font-family: inherit;
        font-size: 20px;
        text-align: center;
        letter-spacing: 6px;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }

    input:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    }

    .btn {
        width: 100%;
        background: var(--color-primary);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 12px;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: background-color .2s;
    }

    .btn:hover {
        background: var(--color-primary-hover);
    }

    .error-msg {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 8px;
        padding: 12px;
        color: var(--color-danger);
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 20px;
        text-align: left;
    }

    .back {
        display: inline-block;
        margin-top: 20px;
        color: var(--text-muted);
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: color 0.2s;
    }

    .back:hover {
        color: var(--color-primary);
    }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="app-icon">🛡️</div>
    <h2>Seguridad de Super Admin</h2>
    <p>Ingresa tu PIN numérico de seguridad para completar el acceso</p>

    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>PIN de Acceso</label>
        <input type="password" name="pin" maxlength="6" placeholder="••••••" required autofocus autocomplete="one-time-code">
      </div>
      <button type="submit" class="btn">Verificar PIN →</button>
    </form>
    
    <a href="login.php" class="back">← Cancelar y volver al login</a>
  </div>
</div>
</body>
</html>