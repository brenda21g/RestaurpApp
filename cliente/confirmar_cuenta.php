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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Cuenta – RestaurApp</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: #0b0b0b; 
            color: #f0ede8; 
            font-family: 'DM Sans', sans-serif; 
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            background: #1c1c1c;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 32px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 25px rgba(0,0,0,0.6);
        }
        h2 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: <?= $estado === 'exito' ? '#e8b86d' : '#e07070' ?>;
            margin-bottom: 12px;
        }
        p {
            font-size: 14px;
            color: #a09a90;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #e8b86d, #c9956a);
            color: #0f0f0f;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
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