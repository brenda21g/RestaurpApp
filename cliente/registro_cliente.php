<?php
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

$mensaje = '';
$es_error = false;

// Preservar la mesa si viene por GET o sesión
$mesa_param = '';
if (!empty($_GET['mesa'])) {
    $mesa_param = '?mesa=' . urlencode($_GET['mesa']);
} elseif (!empty($_SESSION['mesa_token'])) {
    $mesa_param = '?mesa=' . urlencode($_SESSION['mesa_token']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $telefono = trim($_POST['telefono']);
    $token = bin2hex(random_bytes(32));

    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM usuarios_cliente WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $mensaje = "El correo ya está registrado.";
        $es_error = true;
    } else {
        $stmt = $db->prepare("INSERT INTO usuarios_cliente (nombre, email, password, telefono, token_verificacion) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$nombre, $email, $password, $telefono, $token])) {
            
            // Construcción dinámica de la URL de confirmación
            $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $enlace = "$protocolo://$host$dir/confirmar_cuenta.php?token=" . $token;

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'TUCORREO@gmail.com';         // Tu correo Gmail
                $mail->Password   = 'TU_CLAVE_DE_16_LETRAS';       // Tu contraseña de aplicación
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('no-reply@restaurapp.com', 'RestaurApp');
                $mail->addAddress($email, $nombre);

                $mail->isHTML(true);
                $mail->Subject = 'Confirma tu cuenta en RestaurApp';
                $mail->Body    = "
                    <h3>¡Hola, $nombre!</h3>
                    <p>Gracias por registrarte. Haz clic en el siguiente enlace para activar tu cuenta y comenzar a acumular puntos:</p>
                    <p><a href='$enlace' style='padding: 10px 15px; background: #e8b86d; color: #000; text-decoration: none; border-radius: 5px; font-weight: bold;'>Activar mi cuenta</a></p>
                    <br><small>O usa este enlace: $enlace</small>
                ";

                $mail->send();
                $mensaje = "Registro exitoso. Revisa tu correo electrónico para activar tu cuenta.";
                $es_error = false;
            } catch (Exception $e) {
                $mensaje = "Error al enviar el correo de verificación: {$mail->ErrorInfo}";
                $es_error = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro – RestaurApp</title>
    <style>
        body { background: #0b0b0b; color: #f0ede8; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #1c1c1c; border: 1px solid #2a2a2a; padding: 30px; border-radius: 12px; width: 320px; }
        h2 { color: #e8b86d; margin-top: 0; }
        input { width: 100%; padding: 10px; margin: 8px 0 16px; background: #141414; border: 1px solid #2a2a2a; color: #fff; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #e8b86d; border: none; font-weight: bold; border-radius: 6px; cursor: pointer; }
        a { color: #7a7060; text-decoration: none; font-size: 13px; display: block; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Crear Cuenta</h2>
        <?php if ($mensaje): ?>
            <p style="color:<?= $es_error ? '#e07070' : '#6dbf8a' ?>; font-size:13px;"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>
        <form method="POST">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required>
            <label>Correo Electrónico</label>
            <input type="email" name="email" required>
            <label>Teléfono</label>
            <input type="text" name="telefono">
            <label>Contraseña</label>
            <input type="password" name="password" required>
            <button type="submit">Registrarme</button>
        </form>
        <a href="login_cliente.php<?= $mesa_param ?>">¿Ya tienes cuenta? Inicia sesión</a>
    </div>
</body>
</html>