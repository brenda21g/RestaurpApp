<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Restringir acceso exclusivo a Super Administradores
if (!isset($_SESSION['admin_rol']) || $_SESSION['admin_rol'] !== 'super_admin') {
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
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'admin_menor';
        $pin = trim($_POST['pin'] ?? '');

        if (!empty($username) && !empty($password)) {
            $password_hash = md5($password); // Manteniendo el estándar MD5 del proyecto
            // Si es super_admin y puso PIN, lo encriptamos en MD5, de lo contrario queda NULL
            $pin_hash = ($rol === 'super_admin' && !empty($pin)) ? md5($pin) : null;

            try {
                $stmt = $db->prepare("INSERT INTO admins (username, password_hash, nombre, rol, pin, activo) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$username, $password_hash, $nombre, $rol, $pin_hash]);
                $mensaje = "Administrador creado exitosamente.";
            } catch (PDOException $e) {
                $error = "El nombre de usuario ya existe o hubo un error en la base de datos.";
            }
        } else {
            $error = "Usuario y contraseña son obligatorios.";
        }
    } elseif ($accion === 'toggle_activo') {
        $id_admin = intval($_POST['id'] ?? 0);
        $nuevo_estado = intval($_POST['estado'] ?? 1);
        if ($id_admin !== $_SESSION['admin_id']) { // Evitar desactivarse a sí mismo
            $stmt = $db->prepare("UPDATE admins SET activo = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id_admin]);
            $mensaje = "Estado del administrador actualizado.";
        } else {
            $error = "No puedes desactivar tu propia cuenta activa.";
        }
    }
}

// Obtener lista de administradores
$stmt_admins = $db->query("SELECT id, username, nombre, rol, activo, ultimo_login FROM admins ORDER BY id ASC");
$lista_admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Administradores – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0b0b0b; --surface: #141414; --card: #1c1c1c; --border: #2a2a2a;
  --accent: #e8b86d; --accent2: #c9956a; --green: #6dbf8a; --red: #e07070;
  --text: #f0ede8; --muted: #7a7060; --sidebar-w: 240px;
}
body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; display: flex; min-height: 100vh; font-size: 14px; }
.sidebar { width: var(--sidebar-w); background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; }
.sidebar-logo { padding: 24px 20px; border-bottom: 1px solid var(--border); }
.sidebar-logo .name { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--accent); }
.sidebar-logo .role { font-size: 11px; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
.nav { padding: 16px 12px; flex: 1; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 500; transition: all .15s; margin-bottom: 2px; }
.nav-item:hover, .nav-item.active { background: rgba(232,184,109,0.08); color: var(--accent); }
.sidebar-bottom { padding: 16px 12px; border-top: 1px solid var(--border); }
.logout-btn { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: var(--red); text-decoration: none; font-size: 13px; font-weight: 500; }
.logout-btn:hover { background: rgba(224,112,112,0.1); }
.main { margin-left: var(--sidebar-w); flex: 1; padding: 28px 32px; max-width: calc(100% - var(--sidebar-w)); }
.topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.page-title { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--text); }
.card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
.card h3 { font-size: 16px; margin-bottom: 16px; color: var(--accent); font-family: 'Playfair Display', serif; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.field { margin-bottom: 16px; }
label { display: block; font-size: 12px; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
input, select { width: 100%; background: #111; border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
input:focus, select:focus { border-color: var(--accent); }
.btn { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #0f0f0f; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 500; cursor: pointer; font-size: 14px; }
.btn:hover { opacity: .9; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th { text-align: left; padding: 12px; font-size: 11px; text-transform: uppercase; color: var(--muted); background: rgba(255,255,255,0.02); }
td { padding: 12px; border-top: 1px solid var(--border); font-size: 13px; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
.badge.super_admin { background: rgba(232,184,109,0.15); color: var(--accent); }
.badge.admin_menor { background: rgba(109,176,232,0.15); color: #6db0e8; }
.badge.activo { background: rgba(109,191,138,0.15); color: var(--green); }
.badge.inactivo { background: rgba(224,112,112,0.15); color: var(--red); }
.alert-success { background: rgba(109,191,138,0.1); border: 1px solid rgba(109,191,138,0.3); color: var(--green); padding: 12px; border-radius: 8px; margin-bottom: 20px; }
.alert-error { background: rgba(224,112,112,0.1); border: 1px solid rgba(224,112,112,0.3); color: var(--red); padding: 12px; border-radius: 8px; margin-bottom: 20px; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="name">🍽️ RestaurApp</div>
    <div class="role">Super Administrador</div>
  </div>
  <nav class="nav">
    <a class="nav-item" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item" href="interacciones.php"><span class="icon">💬</span> Interacciones</a>
    <a class="nav-item" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span class="icon">🪑</span> Mesas & QR</a>
    <a class="nav-item" href="menu.php"><span class="icon">🍽️</span> Menú</a>
    <a class="nav-item" href="corte.php"><span>💵</span> Corte de Caja</a>
    <a class="nav-item active" href="usuarios.php"><span class="icon">🛡️</span> Administradores</a>
  </nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div class="page-title">Gestión de Administradores</div>
  </div>

  <?php if ($mensaje): ?><div class="alert-success">✅ <?= $mensaje ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error">⚠️ <?= $error ?></div><?php endif; ?>

  <div class="card">
    <h3>Registrar nuevo administrador</h3>
    <form method="POST">
      <input type="hidden" name="accion" value="crear">
      <div class="form-grid">
        <div class="field">
          <label>Nombre de Usuario</label>
          <input type="text" name="username" required placeholder="presione para escribir">
        </div>
        <div class="field">
          <label>Nombre Completo</label>
          <input type="text" name="nombre" placeholder="presione para escribir">
        </div>
        <div class="field">
          <label>Contraseña</label>
          <input type="password" name="password" required placeholder="••••••••">
        </div>
        <div class="field">
          <label>Rol del Sistema</label>
          <select name="rol" id="rolSelect" onchange="togglePinField()">
            <option value="admin_menor">Administrador Menor</option>
            <option value="super_admin">Super Administrador</option>
          </select>
        </div>
        <div class="field" id="pinField" style="display: none; grid-column: span 2;">
          <label>PIN de Seguridad (Solo Super Admin)</label>
          <input type="password" name="pin" maxlength="6" placeholder="••••••">
          <small style="color: var(--muted); font-size: 11px; margin-top: 4px; display: block;">Se solicitará como segundo paso de autenticación al iniciar sesión.</small>
        </div>
      </div>
      <button type="submit" class="btn" style="margin-top: 16px;">Crear Administrador →</button>
    </form>
  </div>

  <div class="card">
    <h3>Listado de cuentas con acceso al panel</h3>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuario</th>
          <th>Nombre</th>
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
          <td style="font-weight: 500;"><?= htmlspecialchars($adm['username']) ?></td>
          <td><?= htmlspecialchars($adm['nombre'] ?? 'Sin nombre') ?></td>
          <td><span class="badge <?= $adm['rol'] ?>"><?= ucfirst(str_replace('_', ' ', $adm['rol'])) ?></span></td>
          <td>
            <span class="badge <?= $adm['activo'] ? 'activo' : 'inactivo' ?>">
              <?= $adm['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td style="color:var(--muted);"><?= $adm['ultimo_login'] ?? 'Nunca' ?></td>
          <td>
            <?php if ($adm['id'] !== $_SESSION['admin_id']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="accion" value="toggle_activo">
                <input type="hidden" name="id" value="<?= $adm['id'] ?>">
                <input type="hidden" name="estado" value="<?= $adm['activo'] ? 0 : 1 ?>">
                <button type="submit" style="background:none; border:none; color: <?= $adm['activo'] ? 'var(--red)' : 'var(--green)' ?>; cursor:pointer; font-weight:500;">
                  <?= $adm['activo'] ? 'Desactivar' : 'Activar' ?>
                </button>
              </form>
            <?php else: ?>
              <span style="color:var(--muted); font-size:11px;">(Cuenta actual)</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<script>
function togglePinField() {
    const rol = document.getElementById('rolSelect').value;
    const pinField = document.getElementById('pinField');
    if (rol === 'super_admin') {
        pinField.style.display = 'block';
    } else {
        pinField.style.display = 'none';
    }
}
</script>
</body>
</html>