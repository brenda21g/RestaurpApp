<?php
require_once __DIR__ . '/../config/config.php';

$mensaje = "Token no válido o expirado.";

if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
    $token = trim($_GET['token']);
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM usuarios_cliente WHERE token_verificacion = ?");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $update = $db->prepare("UPDATE usuarios_cliente SET email_confirmado = 1, token_verificacion = NULL WHERE id = ?");
        $update->execute([$usuario['id']]);
        header("Location: login_cliente.php?msg=cuenta_confirmada");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación – RestaurApp</title>
    <style>body { background: #0b0b0b; color: #f0ede8; font-family: sans-serif; text-align: center; padding-top: 50px; }</style>
</head>
<body>
    <h2><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></h2>
    <a href="login_cliente.php" style="color:#e8b86d;">Ir a inicio de sesión</a>
</body>
</html>