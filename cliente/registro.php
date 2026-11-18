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
                        <p><a href='{$enlace}' style='padding: 10px 15px; background: #c8502a; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold;'>Activar mi cuenta</a></p>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Crear Cuenta – RestaurApp</title>
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
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px;
            font-size: 14px;
        }
        .card { 
            background: var(--surface); 
            border: 1px solid var(--border); 
            padding: 32px 28px; 
            border-radius: 20px; 
            width: 100%;
            max-width: 380px; 
            box-shadow: var(--card-shadow);
        }
        h2 { 
            font-family: 'Playfair Display', serif;
            color: var(--text); 
            margin-bottom: 20px; 
            font-size: 22px; 
            text-align: center; 
        }
        .msg { font-size: 13px; margin-bottom: 16px; text-align: center; line-height: 1.4; padding: 10px; border-radius: 8px; }
        .msg.success { background: #e4f7fa; color: #07543f; border: 1px solid #c7eadc; }
        .msg.error { background: #fde9eb; color: #c8424c; border: 1px solid #f4c7cc; }
        
        label {
            display: block;
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        input { 
            width: 100%; 
            padding: 11px 14px; 
            margin-bottom: 16px; 
            background: var(--bg); 
            border: 1px solid var(--border); 
            color: var(--text); 
            border-radius: 10px; 
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: var(--accent);
        }
        button { 
            width: 100%; 
            padding: 13px; 
            background: linear-gradient(135deg, var(--accent), var(--accent2)); 
            border: none; 
            color: #ffffff;
            font-weight: 600; 
            font-size: 14px;
            border-radius: 10px; 
            cursor: pointer; 
            box-shadow: 0 4px 15px rgba(200,80,42,0.3);
            transition: opacity 0.2s;
        }
        button:hover {
            opacity: 0.9;
        }
        a { 
            color: var(--accent); 
            text-decoration: none; 
            font-size: 13px; 
            display: block; 
            text-align: center; 
            margin-top: 18px; 
            font-weight: 500;
            transition: color 0.2s;
        }
        a:hover {
            text-decoration: underline;
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