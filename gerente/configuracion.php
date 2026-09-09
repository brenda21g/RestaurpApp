<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Configuración del Sistema y Perfil
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/mail.php';
$db = getDB();

$admin_id = $_SESSION['admin_id'] ?? 0;
$mensaje_exito = '';
$mensaje_error = '';

// Obtener datos actuales del administrador logueado
$stmt = $db->prepare("SELECT id, username, nombre, email, rol FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header("Location: login.php");
    exit;
}

// 1. Actualizar perfil general (Usuario, Nombre y Correo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $nuevo_username = trim($_POST['username'] ?? '');
    $nuevo_nombre   = trim($_POST['nombre'] ?? '');
    $nuevo_email    = trim($_POST['email'] ?? '');

    if (!empty($nuevo_username) && !empty($nuevo_nombre) && !empty($nuevo_email)) {
        try {
            $stmt_upd = $db->prepare("UPDATE admins SET username = ?, nombre = ?, email = ? WHERE id = ?");
            $stmt_upd->execute([$nuevo_username, $nuevo_nombre, $nuevo_email, $admin_id]);
            
            // Actualizar variables de sesión si es necesario
            $_SESSION['admin_nombre'] = $nuevo_nombre;
            $_SESSION['admin_username'] = $nuevo_username;
            
            $mensaje_exito = "Perfil actualizado correctamente.";
            
            // Recargar datos actualizados
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $mensaje_error = "El nombre de usuario o el correo electrónico ya están registrados por otro usuario.";
        }
    } else {
        $mensaje_error = "Todos los campos de perfil son obligatorios.";
    }
}

// 2. Solicitar cambio de contraseña (Envío de PIN por correo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_cambio_pass'])) {
    $pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Guardar PIN temporal y expiración de 15 minutos en la BD
    $stmt_pin = $db->prepare("UPDATE admins SET pin_recuperacion = ?, pin_expira = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
    $stmt_pin->execute([$pin, $admin_id]);

    try {
        enviarCorreoRecuperacion($admin['email'], $admin['nombre'], $pin);
        
        // Redirigir a la vista de validación de PIN para cambio de contraseña
        header("Location: cambiar_password.php");
        exit;
    } catch (Exception $e) {
        $mensaje_error = "Error al enviar el correo con PHPMailer.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración – Restaurant App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --sidebar-bg: #011139;
            --sidebar-hover: #002056;
            --sidebar-text: #94a3b8;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --color-primary: #2563eb;
            --color-success: #10b981;
            --color-danger: #ef4444;
            --sidebar-w: 250px;
            --radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            display: flex;
            min-height: 100vh;
            font-size: 14px;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-w);
            background-color: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
        }

        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-logo .name {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }

        .sidebar-logo .role {
            font-size: 11px;
            color: var(--sidebar-text);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .nav {
            padding: 20px 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background-color: var(--sidebar-hover);
            color: #fff;
        }

        .sidebar-bottom {
            padding: 16px 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            color: #f87171;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .logout-btn:hover {
            background-color: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
        }

        /* CONTENIDO PRINCIPAL */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            padding: 32px 40px;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
        }

        .page-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 24px;
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }

        .card h2 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .field {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        input, select {
            background: #fff;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 10px 12px;
            border-radius: var(--radius);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus, select:focus {
            border-color: var(--color-primary);
        }

        .btn-primary {
            background: var(--color-primary);
            color: #fff;
            padding: 10px 18px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="name">Restaurant App</div>
        <div class="role">Gerente</div>
    </div>
    <nav class="nav">
        <a class="nav-item" href="dashboard.php"><span>📊</span> Dashboard</a>
        <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
        <a class="nav-item" href="interacciones.php"><span>💬</span> Interacciones</a>
        <a class="nav-item" href="evaluaciones.php"><span>⭐</span> Evaluaciones</a>
        <a class="nav-item" href="usuarios.php"><span>🛡️</span> Usuarios</a>
        <a class="nav-item" href="miactividad.php"><span>⏱️</span> Mi actividad</a>
        <a class="nav-item active" href="configuracion.php"><span>⚙️</span> Configuración</a>
    </nav>
    <div class="sidebar-bottom">
        <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
    <div class="page-header">
        <div class="page-title">Configuración</div>
        <div class="page-subtitle">Administra tu cuenta, preferencias y opciones de seguridad del CRM.</div>
    </div>

    <?php if ($mensaje_exito): ?><div class="alert alert-success"><?= $mensaje_exito ?></div><?php endif; ?>
    <?php if ($mensaje_error): ?><div class="alert alert-error"><?= $mensaje_error ?></div><?php endif; ?>

    <div class="config-grid">
        <!-- TARJETA 1: PERFIL DE USUARIO -->
        <div class="card">
            <h2>👤 Perfil de usuario</h2>
            <div class="card-desc">Actualiza tus datos de acceso y personales en el sistema.</div>
            
            <form method="POST">
                <input type="hidden" name="actualizar_perfil" value="1">
                <div class="field">
                    <label>Nombre de usuario (Login)</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required>
                </div>
                <div class="field">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($admin['nombre']) ?>" required>
                </div>
                <div class="field">
                    <label>Correo electrónico</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                </div>
                <div class="field">
                    <label>Rol de acceso</label>
                    <input type="text" value="<?= ucfirst($admin['rol']) ?>" disabled style="background:#f1f5f9; color:#64748b; cursor:not-allowed;">
                </div>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </form>
        </div>

        <!-- TARJETA 2: SEGURIDAD Y CONTRASEÑA -->
        <div class="card">
            <h2>🛡️ Seguridad</h2>
            <div class="card-desc">Modifica tu contraseña mediante doble factor de autenticación por correo.</div>
            
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="solicitar_cambio_pass" value="1">
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
                    Al hacer clic en el botón inferior, enviaremos un código de verificación de 6 dígitos a tu correo electrónico registrado (<b><?= htmlspecialchars($admin['email']) ?></b>) para autorizar el cambio de contraseña.
                </p>
                <button type="submit" class="btn-primary" style="background: var(--sidebar-bg);">Solicitar código de cambio</button>
            </form>
        </div>
    </div>
</main>

</body>
</html>