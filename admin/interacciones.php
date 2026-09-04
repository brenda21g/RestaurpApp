<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'crear') {
        $cliente_id = !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : null;
        $tipo = $_POST['tipo'] ?? 'correo';
        $asunto = trim($_POST['asunto'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';

        if ($asunto === '' || $fecha === '' || $hora === '') {
            $error = "Por favor completa los campos obligatorios.";
        } else {
            $stmt = $db->prepare("
                INSERT INTO interacciones
                (cliente_id, tipo, asunto, descripcion, fecha, hora, estado, creado_en)
                VALUES (?, ?, ?, ?, ?, ?, 'pendiente', NOW())
            ");
            $stmt->execute([$cliente_id, $tipo, $asunto, $descripcion, $fecha, $hora]);
            header("Location: interacciones.php?mensaje=creada");
            exit;
        }
    }

    if ($accion === 'estado') {
        $id = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? 'pendiente';
        $estadosPermitidos = ['pendiente', 'completada', 'cancelada'];

        if ($id > 0 && in_array($estado, $estadosPermitidos, true)) {
            $stmt = $db->prepare("UPDATE interacciones SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
        }

        header("Location: interacciones.php");
        exit;
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM interacciones WHERE id = ?");
            $stmt->execute([$id]);
        }

        header("Location: interacciones.php");
        exit;
    }
}

$mensaje = '';

if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'creada') {
    $mensaje = 'Interacción programada correctamente.';
}

$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$sql = "
    SELECT i.*, c.nombre AS cliente_nombre
    FROM interacciones i
    LEFT JOIN usuarios_cliente c ON c.id = i.cliente_id
    WHERE 1=1
";
$params = [];

if ($filtro_tipo !== '') {
    $sql .= " AND i.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_estado !== '') {
    $sql .= " AND i.estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY i.fecha ASC, i.hora ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$interacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtClientes = $db->query("
    SELECT id, nombre
    FROM usuarios_cliente
    ORDER BY nombre ASC
");
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

$totalInteracciones = $db->query("SELECT COUNT(*) FROM interacciones")->fetchColumn();
$pendientes = $db->query("SELECT COUNT(*) FROM interacciones WHERE estado = 'pendiente'")->fetchColumn();
$completadas = $db->query("SELECT COUNT(*) FROM interacciones WHERE estado = 'completada'")->fetchColumn();
$reuniones = $db->query("SELECT COUNT(*) FROM interacciones WHERE tipo = 'reunion' AND estado = 'pendiente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Interacciones – RestaurApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0b0b0b;--surface:#141414;--card:#1c1c1c;--border:#2a2a2a;--accent:#e8b86d;--accent2:#c9956a;--green:#6dbf8a;--red:#e07070;--blue:#6db0e8;--text:#f0ede8;--muted:#7a7060;--sidebar-w:240px}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;font-size:14px}
.sidebar{width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100}
.sidebar-logo{padding:24px 20px;border-bottom:1px solid var(--border)}
.sidebar-logo .name{font-family:'Playfair Display',serif;font-size:20px;color:var(--accent)}
.sidebar-logo .role{font-size:11px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-top:2px}
.nav{padding:16px 12px;flex:1}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;transition:all .15s;margin-bottom:2px}
.nav-item:hover,.nav-item.active{background:rgba(232,184,109,.08);color:var(--accent)}
.nav-item span{font-size:16px}
.sidebar-bottom{padding:16px 12px;border-top:1px solid var(--border)}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:var(--red);text-decoration:none;font-size:13px;font-weight:500}
.logout-btn:hover{background:rgba(224,112,112,.1)}
.main{margin-left:var(--sidebar-w);padding:28px 32px;min-height:100vh}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
.page-title{font-family:'Playfair Display',serif;font-size:28px}
.subtitle{color:var(--muted);font-size:13px;margin-top:4px}
.date-badge{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 16px;color:var(--muted)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px 20px}
.stat-label{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.stat-value{font-size:27px;font-weight:600}
.toolbar{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:20px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.filters{display:flex;gap:10px;flex-wrap:wrap}
select{background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-family:inherit}
.btn{border:none;border-radius:8px;padding:10px 16px;font-family:inherit;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#0f0f0f;font-weight:600}
.btn-secondary{background:var(--surface);color:var(--text);border:1px solid var(--border)}
.btn-danger{background:rgba(224,112,112,.1);color:var(--red);border:1px solid rgba(224,112,112,.2)}
.alert{background:rgba(109,191,138,.1);border:1px solid rgba(109,191,138,.2);color:var(--green);border-radius:8px;padding:12px 16px;margin-bottom:20px}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal.show{display:flex}
.modal-content{background:var(--card);border:1px solid var(--border);border-radius:14px;width:100%;max-width:600px;padding:25px}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}
.modal-header h2{font-family:'Playfair Display',serif;font-size:22px}
.close{background:none;border:none;color:var(--muted);font-size:24px;cursor:pointer}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.form-group{display:flex;flex-direction:column;gap:7px}
.form-group.full{grid-column:1/-1}
.form-group label{font-size:12px;color:var(--muted)}
input,textarea{background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:8px;padding:11px 12px;font-family:inherit;outline:none}
input:focus,textarea:focus,select:focus{border-color:var(--accent)}
textarea{min-height:100px;resize:vertical}
.modal-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}
.interactions{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
.interaction{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;transition:.2s}
.interaction:hover{border-color:rgba(232,184,109,.4)}
.interaction-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px}
.type{display:flex;align-items:center;gap:10px;font-weight:600}
.type-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px;background:rgba(232,184,109,.1)}
.type-correo .type-icon{background:rgba(109,176,232,.1)}
.type-llamada .type-icon{background:rgba(109,191,138,.1)}
.type-reunion .type-icon{background:rgba(232,184,109,.1)}
.status{padding:4px 10px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase}
.status-pendiente{color:var(--accent);background:rgba(232,184,109,.12)}
.status-completada{color:var(--green);background:rgba(109,191,138,.12)}
.status-cancelada{color:var(--red);background:rgba(224,112,112,.12)}
.interaction h3{font-size:16px;margin-bottom:7px}
.client{color:var(--accent);font-size:13px;margin-bottom:12px}
.description{color:var(--muted);font-size:12px;line-height:1.5;margin-bottom:15px}
.info{display:flex;gap:18px;color:var(--muted);font-size:12px;margin-bottom:15px}
.actions{display:flex;gap:8px;padding-top:14px;border-top:1px solid var(--border)}
.actions form{display:inline}
.empty{grid-column:1/-1;text-align:center;padding:50px;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--muted)}
@media(max-width:1000px){.stats{grid-template-columns:repeat(2,1fr)}.interactions{grid-template-columns:1fr}}
@media(max-width:700px){.sidebar{width:200px}.main{margin-left:200px;padding:20px}.stats{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.form-group.full{grid-column:auto}}
</style>
</head>
<body>
<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="name">🍽️ RestaurApp</div>
    <div class="role">Administrador</div>
  </div>
<nav class="nav">
    <a class="nav-item" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a class="nav-item" href="clientes.php"><span>👥</span> Clientes</a>
    <a class="nav-item active" href="interacciones.php"><span class="icon">💬</span> Interacciones</a>
    <a class="nav-item" href="pedidos.php"><span class="icon">📋</span> Pedidos</a>
    <a class="nav-item" href="mesas_qr.php"><span class="icon">🪑</span> Mesas & QR</a>
    <a class="nav-item" href="menu.php"><span class="icon">🍽️</span> Menú</a>
    <a class="nav-item" href="corte.php"><span>💵</span> Corte de Caja</a>

    <?php if (isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] === 'super_admin'): ?>
        <a class="nav-item" href="usuarios.php"><span class="icon">🛡️</span> Administradores</a>
    <?php endif; ?>
</nav>
  <div class="sidebar-bottom">
    <a class="logout-btn" href="logout.php">🚪 Cerrar sesión</a>
  </div>
</aside>

<main class="main">
<div class="topbar">
<div>
<div class="page-title">Interacciones</div>
<div class="subtitle">Programa y administra las interacciones con tus clientes</div>
</div>
<div class="date-badge">📅 <?= date('d/m/Y') ?></div>
</div>

<?php if ($mensaje): ?>
<div class="alert">✅ <?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert" style="color:var(--red);">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="stats">
<div class="stat">
<div class="stat-label">Total</div>
<div class="stat-value"><?= $totalInteracciones ?></div>
</div>
<div class="stat">
<div class="stat-label">Pendientes</div>
<div class="stat-value" style="color:var(--accent);"><?= $pendientes ?></div>
</div>
<div class="stat">
<div class="stat-label">Completadas</div>
<div class="stat-value" style="color:var(--green);"><?= $completadas ?></div>
</div>
<div class="stat">
<div class="stat-label">Reuniones pendientes</div>
<div class="stat-value" style="color:var(--blue);"><?= $reuniones ?></div>
</div>
</div>

<div class="toolbar">
<form method="GET" class="filters">
<select name="tipo">
<option value="">Todos los tipos</option>
<option value="correo" <?= $filtro_tipo === 'correo' ? 'selected' : '' ?>>📧 Correo</option>
<option value="llamada" <?= $filtro_tipo === 'llamada' ? 'selected' : '' ?>>📞 Llamada</option>
<option value="reunion" <?= $filtro_tipo === 'reunion' ? 'selected' : '' ?>>🤝 Reunión</option>
</select>
<select name="estado">
<option value="">Todos los estados</option>
<option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
<option value="completada" <?= $filtro_estado === 'completada' ? 'selected' : '' ?>>Completadas</option>
<option value="cancelada" <?= $filtro_estado === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
</select>
<button class="btn btn-secondary" type="submit">🔎 Filtrar</button>
</form>
<button class="btn btn-primary" onclick="abrirModal()">＋ Nueva interacción</button>
</div>

<div class="interactions">
<?php if (empty($interacciones)): ?>
<div class="empty">
<div style="font-size:40px;margin-bottom:12px;">💬</div>
<div style="font-size:16px;margin-bottom:5px;">No hay interacciones</div>
<div>Programa una nueva interacción con un cliente.</div>
</div>
<?php else: ?>

<?php foreach ($interacciones as $i): ?>
<?php
$icono = match($i['tipo']) {
    'correo' => '📧',
    'llamada' => '📞',
    'reunion' => '🤝',
    default => '💬'
};
$tipoTexto = match($i['tipo']) {
    'correo' => 'Correo',
    'llamada' => 'Llamada',
    'reunion' => 'Reunión',
    default => 'Interacción'
};
$estadoTexto = match($i['estado']) {
    'pendiente' => 'Pendiente',
    'completada' => 'Completada',
    'cancelada' => 'Cancelada',
    default => ucfirst($i['estado'])
};
?>

<div class="interaction">
<div class="interaction-top">
<div class="type type-<?= htmlspecialchars($i['tipo']) ?>">
<div class="type-icon"><?= $icono ?></div>
<div><?= $tipoTexto ?></div>
</div>
<span class="status status-<?= htmlspecialchars($i['estado']) ?>"><?= $estadoTexto ?></span>
</div>

<h3><?= htmlspecialchars($i['asunto']) ?></h3>

<div class="client">
👤 <?= htmlspecialchars($i['cliente_nombre'] ?? 'Sin cliente') ?>
</div>

<?php if (!empty($i['descripcion'])): ?>
<div class="description">
<?= nl2br(htmlspecialchars($i['descripcion'])) ?>
</div>
<?php endif; ?>

<div class="info">
<span>📅 <?= date('d/m/Y', strtotime($i['fecha'])) ?></span>
<span>🕐 <?= date('H:i', strtotime($i['hora'])) ?></span>
</div>

<div class="actions">
<?php if ($i['estado'] === 'pendiente'): ?>
<form method="POST">
<input type="hidden" name="accion" value="estado">
<input type="hidden" name="id" value="<?= $i['id'] ?>">
<input type="hidden" name="estado" value="completada">
<button class="btn btn-secondary" type="submit">✅ Completar</button>
</form>

<form method="POST">
<input type="hidden" name="accion" value="estado">
<input type="hidden" name="id" value="<?= $i['id'] ?>">
<input type="hidden" name="estado" value="cancelada">
<button class="btn btn-danger" type="submit">✕ Cancelar</button>
</form>
<?php endif; ?>

<form method="POST" onsubmit="return confirm('¿Eliminar esta interacción?');">
<input type="hidden" name="accion" value="eliminar">
<input type="hidden" name="id" value="<?= $i['id'] ?>">
<button class="btn btn-danger" type="submit">🗑️</button>
</form>
</div>
</div>
<?php endforeach; ?>

<?php endif; ?>
</div>
</main>

<div class="modal" id="modal">
<div class="modal-content">
<div class="modal-header">
<h2>Nueva interacción</h2>
<button class="close" onclick="cerrarModal()">×</button>
</div>

<form method="POST">
<input type="hidden" name="accion" value="crear">

<div class="form-grid">
<div class="form-group full">
<label>Cliente</label>
<select name="cliente_id" style="width:100%;">
<option value="">Sin cliente específico</option>
<?php foreach ($clientes as $cliente): ?>
<option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nombre']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label>Tipo de interacción *</label>
<select name="tipo" required style="width:100%;">
<option value="correo">📧 Correo</option>
<option value="llamada">📞 Llamada</option>
<option value="reunion">🤝 Reunión</option>
</select>
</div>

<div class="form-group">
<label>Asunto *</label>
<input type="text" name="asunto" placeholder="Ej. Confirmación de reservación" required>
</div>

<div class="form-group">
<label>Fecha *</label>
<input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required>
</div>

<div class="form-group">
<label>Hora *</label>
<input type="time" name="hora" required>
</div>

<div class="form-group full">
<label>Descripción / Notas</label>
<textarea name="descripcion" placeholder="Escribe los detalles de la interacción..."></textarea>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
<button type="submit" class="btn btn-primary">💾 Programar interacción</button>
</div>
</form>
</div>
</div>

<script>
function abrirModal(){
document.getElementById('modal').classList.add('show');
}
function cerrarModal(){
document.getElementById('modal').classList.remove('show');
}
document.getElementById('modal').addEventListener('click',function(e){
if(e.target===this) cerrarModal();
});
</script>
</body>
</html>
