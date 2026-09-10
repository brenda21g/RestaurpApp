<?php
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

// Manejo de peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // 1. Cambiar disponibilidad (Switch ON/OFF)
    if ($action === 'toggle_producto') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE productos SET disponible = NOT disponible WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 2. Agregar nuevo producto
    if ($action === 'add_producto') {
        $nombre = sanitize($_POST['nombre'] ?? '');
        $desc = sanitize($_POST['descripcion'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        $cat_id = (int)($_POST['categoria_id'] ?? 0);

        if ($nombre && $precio > 0 && $cat_id) {
            $stmt = $db->prepare("INSERT INTO productos (categoria_id, nombre, descripcion, precio, disponible) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$cat_id, $nombre, $desc, $precio]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Por favor completa los campos requeridos correctamente.']);
        }
        exit;
    }

    // 3. Eliminar producto de forma segura
    if ($action === 'delete_producto') {
        $id = (int)($_POST['id'] ?? 0);

        try {
            // Intentamos eliminarlo de la tabla productos
            $stmt = $db->prepare("DELETE FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            // Si da error por Foreign Key, deshabilitamos el producto
            if ($e->getCode() == '23000') {
                $db->prepare("UPDATE productos SET disponible = 0 WHERE id = ?")->execute([$id]);
                echo json_encode([
                    'success' => false,
                    'error' => 'El producto no puede ser eliminado por completo porque ya se vendió previamente en órdenes anteriores. Se ha marcado como "No disponible".'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al intentar eliminar el producto.'
                ]);
            }
        }
        exit;
    }
}

// Carga de datos para la vista
$categorias = $db->query("SELECT * FROM categorias ORDER BY orden ASC")->fetchAll(PDO::FETCH_ASSOC);

$productos = $db->query("
    SELECT p.*, c.nombre as cat_nombre
    FROM productos p
    JOIN categorias c ON c.id = p.categoria_id
    ORDER BY c.orden ASC, p.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Menú – RestaurApp Admin</title>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --bg: #f7f9fc;
    --card: #ffffff;
    --border: #e2e8f0;

    --accent: #ffffff;

    --text: #071b3a;
    --muted: #60708a;

    --sidebar: #03143d;
    --sidebar-hover: #092e70;

    --green: #07543f;
    --green-light: #e4f7f0;

    --blue: #1684c4;

    --red: #ff5b5b;

    --sidebar-w: 240px;
}

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    display: flex;
    min-height: 100vh;
    font-size: 14px;
}

/* =========================
   SIDEBAR
========================= */

.sidebar {
    width: var(--sidebar-w);
    background: var(--sidebar);
    border-right: 1px solid #0b2455;
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
    border-bottom: 1px solid #0b2455;
}

.sidebar-logo .name {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: #ffffff;
}

.sidebar-logo .role {
    font-size: 11px;
    color: #91a6c7;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: 2px;
}

.nav {
    padding: 16px 12px;
    flex: 1;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    color: #9db0ce;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all .15s;
    margin-bottom: 4px;
}

.nav-item:hover {
    background: rgba(255, 255, 255, .06);
    color: #ffffff;
}

.nav-item.active {
    background: var(--sidebar-hover);
    color: #ffffff;
}

.nav-item .icon {
    font-size: 15px;
}

.sidebar-bottom {
    padding: 16px 12px;
    border-top: 1px solid #0b2455;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #ff6868;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background .15s;
}

.logout-btn:hover {
    background: rgba(255, 104, 104, .1);
}

/* =========================
   CONTENIDO PRINCIPAL
========================= */

.main {
    margin-left: var(--sidebar-w);
    flex: 1;
    padding: 28px 32px;
}

.page-title {
    font-family: 'Playfair Display', serif;
    font-size: 26px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 20px;
}

.top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

/* =========================
   BOTÓN AGREGAR
========================= */

.add-btn {
    padding: 10px 20px;
    background: var(--green);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, transform .15s;
}

.add-btn:hover {
    background: #063f30;
    transform: translateY(-1px);
}

/* =========================
   TABLA
========================= */

.table-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(7, 27, 58, .04);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 12px 16px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    background: #f8fafc;
}

td {
    padding: 13px 16px;
    border-top: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
    color: var(--text);
}

tbody tr {
    transition: background .15s;
}

tbody tr:hover {
    background: #f8fafc;
}

/* =========================
   ESTADO DISPONIBILIDAD
========================= */

.toggle-btn {
    padding: 5px 12px;
    border-radius: 7px;
    border: none;
    cursor: pointer;
    font-size: 12px;
    font-family: 'DM Sans', sans-serif;
    font-weight: 500;
    transition: all .15s;
}

.toggle-btn.on {
    background: var(--green-light);
    color: #0aa878;
}

.toggle-btn.on:hover {
    background: #d5f2e7;
}

.toggle-btn.off {
    background: #fff0f0;
    color: #e35c5c;
}

.toggle-btn.off:hover {
    background: #ffe3e3;
}

/* =========================
   BOTÓN ELIMINAR
========================= */

.del-btn {
    padding: 5px 11px;
    border-radius: 7px;
    border: 1px solid rgba(224, 112, 112, .3);
    background: transparent;
    color: #e07070;
    font-size: 12px;
    cursor: pointer;
    transition: all .15s;
}

.del-btn:hover {
    background: #fff0f0;
    border-color: #e07070;
}

/* =========================
   MODAL
========================= */

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(3, 20, 61, .55);
    backdrop-filter: blur(3px);
    z-index: 200;
    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    max-width: 440px;
    width: 90%;
    box-shadow: 0 20px 50px rgba(7, 27, 58, .18);
}

.modal h3 {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: var(--text);
    margin-bottom: 20px;
}

.field {
    margin-bottom: 16px;
}

label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
}

input[type=text],
input[type=number],
textarea,
select {
    width: 100%;
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 14px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}

input[type=text]:focus,
input[type=number]:focus,
textarea:focus,
select:focus {
    border-color: #1684c4;
    box-shadow: 0 0 0 3px rgba(22, 132, 196, .08);
}

textarea {
    resize: vertical;
}

select {
    cursor: pointer;
}

select option {
    background: #ffffff;
    color: var(--text);
}

/* =========================
   BOTONES DEL MODAL
========================= */

.modal-btns {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.modal-save {
    flex: 1;
    padding: 10px;
    background: var(--green);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}

.modal-save:hover {
    background: #063f30;
}

.modal-cancel {
    padding: 10px 16px;
    border: 1px solid var(--border);
    background: #ffffff;
    color: var(--muted);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    cursor: pointer;
    transition: all .15s;
}

.modal-cancel:hover {
    background: #f7f9fc;
    color: var(--text);
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 800px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
        padding: 24px;
    }

    .top-row {
        gap: 15px;
    }

    .table-card {
        overflow-x: auto;
    }

    table {
        min-width: 650px;
    }
}

@media (max-width: 600px) {

    .sidebar {
        width: 70px;
    }

    .sidebar-logo .name,
    .sidebar-logo .role,
    .logout-btn {
        font-size: 0;
    }

    .sidebar-logo {
        padding: 20px 10px;
        text-align: center;
    }

    .nav {
        padding: 16px 8px;
    }

    .nav-item {
        justify-content: center;
        padding: 12px 8px;
    }

    .main {
        margin-left: 70px;
        padding: 20px 16px;
    }

    .top-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .add-btn {
        width: 100%;
    }

    .table-card {
        overflow-x: auto;
    }

    table {
        min-width: 650px;
    }

    .modal {
        padding: 22px;
    }
}

</style>

</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="sidebar-logo">

        <div class="name">🍽️ RestaurApp</div>

        <div class="role">Subgerente</div>

    </div>

    <nav class="nav">

        <a class="nav-item" href="dashboard.php">
            <span class="icon">📊</span> Dashboard
        </a>

        <a class="nav-item" href="pedidos.php">
            <span class="icon">📋</span> Pedidos
        </a>

        <a class="nav-item" href="mesas_qr.php">
            <span class="icon">🪑</span> Mesas & QR
        </a>

        <a class="nav-item active" href="menu.php">
            <span class="icon">🍽️</span> Menú
        </a>

        <a class="nav-item" href="corte.php">
            <span>💵</span> Corte de Caja
        </a>

    </nav>

    <div class="sidebar-bottom">

        <a class="logout-btn" href="logout.php">
            🚪 Cerrar sesión
        </a>

    </div>

</aside>

<main class="main">

    <div class="top-row">

        <div class="page-title">
            🍽️ Gestión del Menú
        </div>

        <button class="add-btn" onclick="showAddModal()">
            + Agregar producto
        </button>

    </div>

    <div class="table-card">

        <table>

            <thead>

                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th></th>
                </tr>

            </thead>

            <tbody id="prod-table">

                <?php foreach ($productos as $p): ?>

                <tr id="row-<?= $p['id'] ?>">

                    <td>

                        <div style="font-weight:500;">
                            <?= htmlspecialchars($p['nombre']) ?>
                        </div>

                        <?php if ($p['descripcion']): ?>

                        <div style="color:var(--muted);font-size:12px;margin-top:2px;">
                            <?= htmlspecialchars($p['descripcion']) ?>
                        </div>

                        <?php endif; ?>

                    </td>

                    <td style="color:var(--muted);">
                        <?= htmlspecialchars($p['cat_nombre']) ?>
                    </td>

                    <td>
                        $<?= number_format($p['precio'], 2) ?>
                    </td>

                    <td>

                        <button
                            class="toggle-btn <?= $p['disponible'] ? 'on' : 'off' ?>"
                            onclick="toggleProducto(<?= $p['id'] ?>, this)"
                        >
                            <?= $p['disponible'] ? '✓ Disponible' : '✗ No disp.' ?>
                        </button>

                    </td>

                    <td>

                        <button
                            class="del-btn"
                            onclick="eliminarProducto(<?= $p['id'] ?>)"
                        >
                            Eliminar
                        </button>

                    </td>

                </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

<!-- MODAL AGREGAR PRODUCTO -->

<div class="modal-overlay" id="add-modal">

    <div class="modal">

        <h3>
            ➕ Agregar producto
        </h3>

        <div class="field">

            <label>Nombre</label>

            <input
                type="text"
                id="nuevo-nombre"
                placeholder="Ej: Tacos de suadero"
            >

        </div>

        <div class="field">

            <label>Descripción (opcional)</label>

            <textarea
                id="nuevo-desc"
                rows="2"
                placeholder="Breve descripción del platillo"
            ></textarea>

        </div>

        <div class="field">

            <label>Precio</label>

            <input
                type="number"
                id="nuevo-precio"
                placeholder="0.00"
                step="0.01"
                min="0"
            >

        </div>

        <div class="field">

            <label>Categoría</label>

            <select id="nuevo-cat">

                <?php foreach ($categorias as $c): ?>

                <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="modal-btns">

            <button
                class="modal-cancel"
                onclick="hideModal()"
            >
                Cancelar
            </button>

            <button
                class="modal-save"
                onclick="guardarProducto()"
            >
                Guardar producto
            </button>

        </div>

    </div>

</div>

<script>

function showAddModal() {
    document.getElementById('add-modal').classList.add('show');
}

function hideModal() {
    document.getElementById('add-modal').classList.remove('show');
}

async function toggleProducto(id, btn) {

    const fd = new FormData();

    fd.append('action', 'toggle_producto');
    fd.append('id', id);

    const res = await fetch('menu.php', {
        method: 'POST',
        body: fd
    });

    const data = await res.json();

    if (data.success) {

        btn.classList.toggle('on');
        btn.classList.toggle('off');

        btn.textContent = btn.classList.contains('on')
            ? '✓ Disponible'
            : '✗ No disp.';

    }

}

async function eliminarProducto(id) {

    if (!confirm('¿Seguro que deseas eliminar este producto?')) {
        return;
    }

    const fd = new FormData();

    fd.append('action', 'delete_producto');
    fd.append('id', id);

    const res = await fetch('menu.php', {
        method: 'POST',
        body: fd
    });

    const data = await res.json();

    if (data.success) {

        document.getElementById('row-' + id)?.remove();

    } else {

        alert(data.error);

        location.reload();

    }

}

async function guardarProducto() {

    const nombre = document.getElementById('nuevo-nombre').value.trim();
    const desc = document.getElementById('nuevo-desc').value.trim();
    const precio = document.getElementById('nuevo-precio').value;
    const cat_id = document.getElementById('nuevo-cat').value;

    if (!nombre || !precio) {

        alert('Completa nombre y precio');

        return;

    }

    const fd = new FormData();

    fd.append('action', 'add_producto');
    fd.append('nombre', nombre);
    fd.append('descripcion', desc);
    fd.append('precio', precio);
    fd.append('categoria_id', cat_id);

    const res = await fetch('menu.php', {
        method: 'POST',
        body: fd
    });

    const data = await res.json();

    if (data.success) {

        hideModal();

        location.reload();

    } else {

        alert(data.error);

    }

}

</script>

</body>

</html>