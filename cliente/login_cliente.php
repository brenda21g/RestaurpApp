<?php
require_once __DIR__ . '/../config/config.php';

$error = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'cuenta_confirmada') {
    $mensaje_exito = "¡Cuenta confirmada con éxito! Ya puedes iniciar sesión.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE email = ?");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente && password_verify($password, $cliente['password'])) {
        if ($cliente['email_confirmado'] == 0) {
            $error = "Por favor confirma tu correo antes de iniciar sesión.";
        } else {
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nombre'] = $cliente['nombre'];

            // Si hay un token de mesa guardado en la URL o sesión previa, redirige con él
            if (!empty($_GET['mesa'])) {
                header("Location: index_cliente.php?mesa=" . urlencode($_GET['mesa']));
            } elseif (!empty($_SESSION['mesa_token'])) {
                header("Location: index_cliente.php?mesa=" . urlencode($_SESSION['mesa_token']));
            } else {
                header("Location: index_cliente.php");
            }
            exit;
        }
    } else {
        $error = "Correo o contraseña incorrectos.";
    }
}
// Ejemplo dentro del procesamiento del formulario de login en login_cliente.php:
if ($password_valida) {
    session_start();
    $_SESSION['cliente_id'] = $usuario['id'];
    $_SESSION['cliente_nombre'] = $usuario['nombre'];

    // Redirigir al menú principal
    header('Location: index_cliente.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Cliente – RestaurApp</title>
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
        <h2>Iniciar Sesión</h2>
        <?php if (isset($mensaje_exito)): ?><p style="color:#6dbf8a; font-size:13px;"><?= $mensaje_exito ?></p><?php endif; ?>
        <?php if ($error): ?><p style="color:#e07070; font-size:13px;"><?= $error ?></p><?php endif; ?>
        <form method="POST">
            <label>Correo Electrónico</label>
            <input type="email" name="email" required>
            <label>Contraseña</label>
            <input type="password" name="password" required>
            <button type="submit">Entrar</button>
        </form>
        <a href="registro_cliente.php<?= !empty($_GET['mesa']) ? '?mesa=' . urlencode($_GET['mesa']) : '' ?>">¿No tienes cuenta? Regístrate</a>
    </div>
</body>
</html>