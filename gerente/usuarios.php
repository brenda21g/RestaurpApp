<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Gestión de Administradores / Usuarios (Restaurant_App)
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Restringir acceso exclusivo a gerentes
if (!isset($_SESSION['admin_rol']) || $_SESSION['admin_rol'] !== 'gerente') {
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';
$error = '';

// Procesar formulario de creación / edición de admins
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $username = trim($_POST['username'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'subgerente';
        $pin = trim($_POST['pin'] ?? '');

        if (!empty($username) && !empty($password) && !empty($email)) {
            $password_hash = md5($password); // Manteniendo el estándar MD5 del proyecto
            $pin_hash = !empty($pin) ? md5($pin) : null;

            try {
                $stmt = $db->prepare("INSERT INTO admins (username, password_hash, nombre, email, rol, pin, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$username, $password_hash, $nombre, $email, $rol, $pin_hash]);
                $mensaje = "Administrador creado exitosamente.";
            } catch (PDOException $e) {
                $error = "El nombre de usuario o correo ya existen en la base de datos.";
            }
        } else {
            $error = "Usuario, correo y contraseña son obligatorios.";
        }
    } elseif ($accion === 'toggle_activo') {
        $id_admin = intval($_POST['id'] ?? 0);
        $nuevo_estado = intval($_POST['estado'] ?? 1);
        $session_admin_id = intval($_SESSION['admin_id'] ?? 0);

        if ($id_admin !== $session_admin_id) { // Evitar desactivarse a sí mismo
            $stmt = $db->prepare("UPDATE admins SET activo = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id_admin]);
            $mensaje = "Estado del administrador actualizado.";
        } else {
            $error = "No puedes desactivar tu propia cuenta activa.";
        }
    }
}

// Obtener lista de administradores
$stmt_admins = $db->query("SELECT id, username, nombre, email, rol, activo, ultimo_login FROM admins ORDER BY id ASC");
$lista_admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
$session_admin_id = intval($_SESSION['admin_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Administradores – Restaurant_App</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-body: #f8fafc;
        --bg-surface: #ffffff;
        --sidebar-bg: #0f172a;
        --sidebar-hover: #1e293b;
        --sidebar-text: #94a3b8;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --color-primary: #0284c7;
        --color-primary-hover: #0369a1;
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

    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .page-title {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-main);
    }

    .card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
    }

    .card h3 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 20px;
        color: var(--text-main);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .field {
        margin-bottom: 16px;
    }

    label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    input, select {
        width: 100%;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 10px 14px;
        color: var(--text-main);
        font-family: inherit;
        font-size: 14px;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }

    input:focus, select:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    }

    .btn {
        background: var(--color-primary);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 11px 20px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        font-family: inherit;
        transition: background-color .2s;
    }

    .btn:hover { background: var(--color-primary-hover); }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        text-align: left;
        font-size: 13px;
    }

    th {
        background: #f1f5f9;
        color: var(--text-muted);
        font-weight: 600;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
    }

    td {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main);
    }

    tr:last-child td { border-bottom: none; }
    tr:hover td { background-color: #f8fafc; }

    .badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }

    .badge.gerente { background: rgba(2, 132, 199, 0.1); color: var(--color-primary); }
    .badge.subgerente { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }
    .badge.activo { background: rgba(16, 185, 129, 0.1); color: var(--color-success); }
    .badge.inactivo { background: rgba(239, 68, 68, 0.1); color: var(--color-danger); }

    .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--color-success); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--color-danger); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }

    @media (max-width: 768px) {
        .main { margin-left: 0; padding: 20px; }
        .sidebar { display: none; }
        .form-grid { grid-template-columns: 1fr; }
    }
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
        <a class="nav-item" href="evaluaciones.php"><span>📋</span> Evaluaciones</a>
        <a class="nav-item active" href="usuarios.php"><span>🛡️</span> Usuarios</a>
        <a class="nav-item" href="miactividad.php"><span>⏱️</span> Mi actividad</a>
        <a class="nav-item" href="configuracion.php"><span>⚙️</span> Configuración</a>
    </nav>
    <div class="sidebar-bottom">
        <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="main">
    <div class="topbar">
        <div class="page-title">Gestión de Administradores / Usuarios</div>
    </div>

    <?php if ($mensaje): ?><div class="alert-success">✅ <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>Registrar nuevo usuario administrativo</h3>
        <form method="POST">
            <input type="hidden" name="accion" value="crear">
            <div class="form-grid">
                <div class="field">
                    <label>Nombre de Usuario</label>
                    <input type="text" name="username" required placeholder="Ingresa el usuario">
                </div>
                <div class="field">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" placeholder="Ingresa el nombre completo">
                </div>
                <div class="field">
                    <label>Correo Electrónico</label>
                    <input type="email" name="email" required placeholder="correo@ejemplo.com">
                </div>
                <div class="field">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <div class="field">
                    <label>Rol del Sistema</label>
                    <select name="rol" id="rolSelect" onchange="togglePinField()">
                        <option value="subgerente">Subgerente</option>
                        <option value="gerente">Gerente</option>
                    </select>
                </div>
                <div class="field" id="pinField" style="display: none; grid-column: span 2;">
                    <label>PIN de Seguridad (2FA)</label>
                    <input type="password" name="pin" maxlength="6" placeholder="••••••">
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">Se solicitará como segundo paso de autenticación al iniciar sesión.</small>
                </div>
            </div>
            <button type="submit" class="btn" style="margin-top: 8px;">Crear Usuario →</button>
        </form>
    </div>

    <div class="card">
        <h3>Listado de cuentas con acceso al panel</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_admins as $adm): ?>
                    <tr>
                        <td><?= $adm['id'] ?></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars($adm['username']) ?></td>
                        <td><?= htmlspecialchars($adm['nombre'] ?? 'Sin nombre') ?></td>
                        <td><?= htmlspecialchars($adm['email'] ?? 'N/D') ?></td>
                        <td><span class="badge <?= htmlspecialchars($adm['rol']) ?>"><?= ucfirst($adm['rol']) ?></span></td>
                        <td>
                            <span class="badge <?= $adm['activo'] ? 'activo' : 'inactivo' ?>">
                                <?= $adm['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted);"><?= $adm['ultimo_login'] ?? 'Nunca' ?></td>
                        <td>
                            <?php if (intval($adm['id']) !== $session_admin_id): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="toggle_activo">
                                    <input type="hidden" name="id" value="<?= $adm['id'] ?>">
                                    <input type="hidden" name="estado" value="<?= $adm['activo'] ? 0 : 1 ?>">
                                    <button type="submit" style="background:none; border:none; color: <?= $adm['activo'] ? 'var(--color-danger)' : 'var(--color-success)' ?>; cursor:pointer; font-weight:600; font-size:13px;">
                                        <?= $adm['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:12px;">(Cuenta actual)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function togglePinField() {
    const rol = document.getElementById('rolSelect').value;
    const pinField = document.getElementById('pinField');
    if (rol === 'gerente') {
        pinField.style.display = 'block';
    } else {
        pinField.style.display = 'none';
    }
}
</script>
</body>
</html>