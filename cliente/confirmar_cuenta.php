<?php
require_once __DIR__ . '/../config/config.php';

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_SPECIAL_CHARS);
$estado = 'error';
$mensaje = 'No se ha proporcionado un token válido.';

if ($token) {
    try {
        $db = getDB();
        
        // Verificar token en la tabla usuarios_cliente
        $stmt = $db->prepare("SELECT id FROM usuarios_cliente WHERE token_verificacion = ? AND email_confirmado = 0");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            // Confirmar el correo y limpiar el token
            $update = $db->prepare("UPDATE usuarios_cliente SET email_confirmado = 1, token_verificacion = NULL WHERE id = ?");
            $update->execute([$usuario['id']]);

            $estado = 'exito';
            $mensaje = '¡Tu correo ha sido confirmado exitosamente! Ya puedes iniciar sesión para continuar.';
        } else {
            $mensaje = 'El enlace de confirmación ya expiró, es inválido o la cuenta ya fue activada anteriormente.';
        }

    } catch (Exception $e) {
        $mensaje = 'Ocurrió un error en el sistema al intentar verificar tu cuenta. Inténtalo más tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Confirmación de Cuenta – RestaurApp</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
          --bg: #faf8f5; --surface: #ffffff; --border: #ede8e0;
          --accent: #c8502a; --accent2: #e8733a; --text: #1a1510; --muted: #8a7a6a;
          --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        body { 
            background: var(--bg); 
            color: var(--text); 
            font-family: 'DM Sans', sans-serif; 
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            font-size: 14px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 28px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: var(--card-shadow);
        }
        h2 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: <?= $estado === 'exito' ? 'var(--accent)' : '#c8424c' ?>;
            margin-bottom: 12px;
        }
        p {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #ffffff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(200,80,42,0.3);
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="card">
        <h2><?= $estado === 'exito' ? '🎉 ¡Cuenta Confirmada!' : '⚠️ Error de Verificación' ?></h2>
        <p><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="login.php" class="btn">Ir a inicio de sesión</a>
    </div>
</body>
</html>