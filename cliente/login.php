<?php
// Cargar configuración global (inicia la sesión y carga las funciones necesarias)
require_once __DIR__ . '/../config/config.php';

$error = '';
$mensaje_exito = '';

// Si viene un token de mesa por GET, lo respaldamos en la sesión
if (isset($_GET['mesa']) && !empty($_GET['mesa'])) {
    $_SESSION['mesa_token'] = sanitize($_GET['mesa']);
}

// Mensaje tras confirmar la cuenta por correo
if (isset($_GET['msg']) && $_GET['msg'] === 'cuenta_confirmada') {
    $mensaje_exito = "¡Cuenta confirmada con éxito! Ya puedes iniciar sesión.";
}

// Procesar el formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Por favor ingresa tu correo y contraseña.";
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE email = ?");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente && md5($password) === $cliente['password']) {
            if (isset($cliente['email_confirmado']) && $cliente['email_confirmado'] == 0) {
                $error = "Por favor confirma tu correo electrónico antes de iniciar sesión.";
            } else {
                // Guardar variables de sesión del cliente
                $_SESSION['cliente_id'] = $cliente['id'];
                $_SESSION['cliente_nombre'] = $cliente['nombre'];

                // Determinar a qué página redirigir manteniendo la mesa
                $mesaParam = '';
                if (!empty($_GET['mesa'])) {
                    $mesaParam = '?mesa=' . urlencode($_GET['mesa']);
                } elseif (!empty($_SESSION['mesa_token'])) {
                    $mesaParam = '?mesa=' . urlencode($_SESSION['mesa_token']);
                }

               
                // Redirigir al index del cliente manteniendo la mesa
                header("Location: index.php" . $mesaParam);
                exit;
            }
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    }
}

// Construir la URL del enlace a registro preservando el parámetro mesa
$registroUrl = "registro_cliente.php";
if (!empty($_GET['mesa'])) {
    $registroUrl .= "?mesa=" . urlencode($_GET['mesa']);
} elseif (!empty($_SESSION['mesa_token'])) {
    $registroUrl .= "?mesa=" . urlencode($_SESSION['mesa_token']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión – RestaurApp</title>
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
        .msg { font-size: 13px; margin-bottom: 15px; text-align: center; }
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
        <h2>Iniciar Sesión</h2>
        
        <?php if (!empty($mensaje_exito)): ?>
            <div class="msg success"><?= htmlspecialchars($mensaje_exito) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
            
            <button type="submit">Entrar</button>
        </form>

       <a href="registro.php">¿No tienes cuenta? Regístrate</a>
    </div>
</body>
</html>