<?php
/**
 * Archivo: gerente/verificar_pin.php
 * Descripción: Vista y controlador para la verificación del PIN de 2FA de Super Admin (Diseño Azul y Rojo Elegante).
 */
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
<title>Verificación PIN – Panel Gerencial</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #011139;
        --bg-surface: #ffffff;
        --text-main: #071b3a;
        --text-muted: #64748b;
        --border-color: #fecaca;
        --color-primary: #dc2626; /* Rojo elegante institucional */
        --color-primary-hover: #b91c1c;
        --color-danger: #ef4444;
        --radius: 12px;
        --shadow-lg: 0 12px 35px rgba(220, 38, 38, 0.12);
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        /* Fondo con el mismo degradado sofisticado azul y rojo elegante */
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

    .wrap {
        width: 100%;
        max-width: 400px;
        padding: 20px;
        position: relative;
        z-index: 1;
    }

    .card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 36px 30px;
        box-shadow: var(--shadow-lg);
        text-align: center;
    }

    .app-icon {
        font-size: 36px;
        margin-bottom: 12px;
        display: inline-block;
        filter: drop-shadow(0 4px 8px rgba(220, 38, 38, 0.3));
    }

    h2 {
        font-family: 'Playfair Display', serif;
        font-size: 22px;
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
        border: 1px solid #cbd5e1;
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
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
    }

    .btn {
        width: 100%;
        background: linear-gradient(135deg, #011139 0%, var(--color-primary) 100%);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 12px;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: all .2s;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }

    .btn:hover {
        opacity: 0.95;
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(220, 38, 38, 0.35);
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
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="app-icon">🛡️</div>
    <h2>Seguridad de Super Admin</h2>
    <p>Ingresa tu PIN numérico de seguridad para completar el acceso</p>

    <?php if (!empty($error)): ?>
      <div class="error-msg">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
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