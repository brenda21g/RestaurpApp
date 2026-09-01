<?php
// 1. Cargar la configuración global (inicia la sesión y funciones de base de datos)
require_once __DIR__ . '/../config/config.php';

// 2. Importar los espacios de nombres de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. Incluir los archivos de la librería PHPMailer
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

$mensaje = '';
$es_error = false;

// Preservar la mesa si viene por GET o sesión
if (!empty($_GET['mesa'])) {
    $_SESSION['mesa_token'] = sanitize($_GET['mesa']);
}

$mesa_param = '';
if (!empty($_GET['mesa'])) {
    $mesa_param = '?mesa=' . urlencode($_GET['mesa']);
} elseif (!empty($_SESSION['mesa_token'])) {
    $mesa_param = '?mesa=' . urlencode($_SESSION['mesa_token']);
}

// Variables para mantener los datos en el formulario si ocurre un error
$val_nombre = '';
$val_email = '';
$val_telefono = '';

// Procesar el formulario cuando se envía por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_nombre   = trim($_POST['nombre'] ?? '');
    $val_email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $val_telefono = trim($_POST['telefono'] ?? '');
    $raw_password = $_POST['password'] ?? '';

    if (empty($val_nombre) || empty($val_email) || empty($raw_password)) {
        $mensaje = "Por favor completa todos los campos obligatorios.";
        $es_error = true;
    } else {
        $password = md5($raw_password);
        $token = bin2hex(random_bytes(32));

        $db = getDB();

        // Verificar si el correo ya existe
        $stmt = $db->prepare("SELECT id FROM usuarios_cliente WHERE email = ?");
        $stmt->execute([$val_email]);
        
        if ($stmt->fetch()) {
            $mensaje = "El correo ya está registrado.";
            $es_error = true;
        } else {
            // Insertar el nuevo usuario en la base de datos
            $stmt = $db->prepare("INSERT INTO usuarios_cliente (nombre, email, password, telefono, token_verificacion) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$val_nombre, $val_email, $password, $val_telefono, $token])) {
                
                // Construcción de la URL dinámica para la confirmación de cuenta
                $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $enlace = "$protocolo://$host$dir/confirmar_cuenta.php?token=" . $token;

                // Instancia y configuración de PHPMailer
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'eloyguadalupesalasgonzalez@gmail.com';         // Tu correo Gmail
                    $mail->Password   = 'rele ihsw obmr lbdc';       // Tu contraseña de aplicación de Google
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom('no-reply@restaurapp.com', 'RestaurApp');
                    $mail->addAddress($val_email, $val_nombre);

                    $nombreHtml = htmlspecialchars($val_nombre);

                    $mail->isHTML(true);
                    $mail->Subject = 'Confirma tu cuenta en RestaurApp';
                    $mail->Body    = "
                        <h3>¡Hola, {$nombreHtml}!</h3>
                        <p>Gracias por registrarte. Haz clic en el siguiente enlace para activar tu cuenta y comenzar a realizar tus pedidos:</p>
                        <p><a href='{$enlace}' style='padding: 10px 15px; background: #e8b86d; color: #000; text-decoration: none; border-radius: 5px; font-weight: bold;'>Activar mi cuenta</a></p>
                        <br><small>O copia este enlace en tu navegador: {$enlace}</small>
                    ";

                    $mail->send();
                    $mensaje = "Registro exitoso. Revisa tu correo electrónico para activar tu cuenta.";
                    $es_error = false;
                    
                    // Limpiar formulario tras éxito
                    $val_nombre = $val_email = $val_telefono = '';
                } catch (Exception $e) {
                    $mensaje = "Error al enviar el correo de verificación: {$mail->ErrorInfo}";
                    $es_error = true;
                }
            }
        }
    }
}

// Ruta hacia la página de Login manteniendo la mesa
$loginUrl = "login.php" . $mesa_param;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta – RestaurApp</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: #0b0b0b; 
            color: #f0ede8; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px;
        }
        .card { 
            background: #1c1c1c; 
            border: 1px solid #2a2a2a; 
            padding: 30px; 
            border-radius: 12px; 
            width: 100%;
            max-width: 360px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        h2 { 
            color: #e8b86d; 
            margin-bottom: 20px; 
            font-size: 22px; 
            text-align: center; 
        }
        .msg { font-size: 13px; margin-bottom: 15px; text-align: center; line-height: 1.4; }
        .msg.success { color: #6dbf8a; }
        .msg.error { color: #e07070; }
        
        label {
            display: block;
            font-size: 13px;
            color: #a09a90;
            margin-bottom: 6px;
        }
        input { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 16px; 
            background: #141414; 
            border: 1px solid #2a2a2a; 
            color: #fff; 
            border-radius: 6px; 
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: #e8b86d;
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #e8b86d; 
            border: none; 
            color: #0b0b0b;
            font-weight: bold; 
            font-size: 15px;
            border-radius: 6px; 
            cursor: pointer; 
            transition: background 0.2s;
        }
        button:hover {
            background: #d4a359;
        }
        a { 
            color: #7a7060; 
            text-decoration: none; 
            font-size: 13px; 
            display: block; 
            text-align: center; 
            margin-top: 18px; 
            transition: color 0.2s;
        }
        a:hover {
            color: #e8b86d;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>Crear Cuenta</h2>
        
        <?php if (!empty($mensaje)): ?>
            <div class="msg <?= $es_error ? 'error' : 'success' ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($val_nombre) ?>">
            
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($val_email) ?>">
            
            <label for="telefono">Teléfono (opcional)</label>
            <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($val_telefono) ?>">
            
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
            
            <button type="submit">Registrarme</button>
        </form>

        <a href="<?= htmlspecialchars($loginUrl) ?>">¿Ya tienes cuenta? Inicia sesión</a>
    </div>
</body>
</html>