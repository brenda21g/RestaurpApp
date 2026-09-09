<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Validación de Código y Establecer Nueva Contraseña
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$admin_id = $_SESSION['admin_id'] ?? 0;
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin_ingresado = trim($_POST['pin'] ?? '');
    $pass_nueva    = $_POST['password_nueva'] ?? '';
    $pass_confirm  = $_POST['password_confirm'] ?? '';

    if (!empty($pin_ingresado) && !empty($pass_nueva)) {
        if ($pass_nueva === $pass_confirm) {
            // Verificar PIN y vigencia (15 minutos)
            $stmt = $db->prepare("SELECT pin_recuperacion, pin_expira FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data && $data['pin_recuperacion'] === $pin_ingresado && strtotime($data['pin_expira']) >= time()) {
                // Actualizar contraseña cifrada en MD5 (o password_hash según prefieras, usamos tu estándar MD5)
                $nuevo_hash = md5($pass_nueva);

                $stmt_upd = $db->prepare("UPDATE admins SET password_hash = ?, pin_recuperacion = NULL, pin_expira = NULL WHERE id = ?");
                $stmt_upd->execute([$nuevo_hash, $admin_id]);

                // Redirigir al dashboard con éxito o a configuración
                header("Location: configuracion.php?exito_pass=1");
                exit;
            } else {
                $mensaje_error = "El código ingresado es incorrecto o ha expirado.";
            }
        } else {
            $mensaje_error = "Las nuevas contraseñas no coinciden.";
        }
    } else {
        $mensaje_error = "Por favor completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña – Restaurant App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --color-primary: #2563eb;
            --radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; font-size: 14px; }
        .card { background: var(--bg-surface); border: 1px solid var(--border-color); padding: 32px; border-radius: var(--radius); width: 100%; max-width: 400px; box-shadow: var(--shadow-sm); }
        h2 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
        p { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
        .field { margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        input { background: #fff; border: 1px solid var(--border-color); color: var(--text-main); padding: 10px 12px; border-radius: var(--radius); font-size: 14px; outline: none; transition: border-color 0.2s; }
        input:focus { border-color: var(--color-primary); }
        .btn-submit { width: 100%; background: var(--color-primary); color: #fff; padding: 12px; border: none; border-radius: var(--radius); font-weight: 600; cursor: pointer; margin-top: 10px; transition: background 0.2s; }
        .btn-submit:hover { background: #1d4ed8; }
        .back { display: inline-block; margin-bottom: 16px; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 500; }
        .back:hover { color: var(--color-primary); }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px; border-radius: var(--radius); font-size: 13px; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="card">
    <a href="configuracion.php" class="back">← Cancelar y volver</a>
    <h2>Verificación de Código</h2>
    <p>Ingresa el código de 6 dígitos enviado a tu correo y define tu nueva contraseña.</p>

    <?php if ($mensaje_error): ?><div class="alert-error"><?= $mensaje_error ?></div><?php endif; ?>

    <form method="POST">
        <div class="field">
            <label>Código de Verificación</label>
            <input type="text" name="pin" maxlength="6" placeholder="Ej. 482910" style="text-align: center; font-size: 18px; letter-spacing: 4px;" required autofocus>
        </div>
        <div class="field">
            <label>Nueva Contraseña</label>
            <input type="password" name="password_nueva" placeholder="••••••••" required>
        </div>
        <div class="field">
            <label>Confirmar Nueva Contraseña</label>
            <input type="password" name="password_confirm" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-submit">Actualizar Contraseña</button>
    </form>
</div>
</body>
</html>