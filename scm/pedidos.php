<?php
/**
 * Archivo: scm/pedidos.php
 * Descripción: Gestión de pedidos con simulación automática por tiempos para PUSH (15s iniciales y 10s por estado) y control manual para PULL.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
verificarAcceso(['gerente', 'subgerente', 'logistica']);
$db = getDB();

$rol_actual = $_SESSION['admin_rol'] ?? '';
$es_editable = puedeEditar('scm'); // TRUE para Gerente y Logística; FALSE para Subgerente

// 1. Detección automática de insumos con stock crítico para generar órdenes PUSH si no existen
$stmt_criticos = $db->query("SELECT * FROM scm_productos WHERE stock_actual <= stock_minimo AND estrategia_logistica = 'PUSH'");
$insumos_criticos = $stmt_criticos->fetchAll(PDO::FETCH_ASSOC);

foreach ($insumos_criticos as $ic) {
    // Verificar si ya existe un pedido activo (pendiente o en_proceso) para este producto
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM scm_pedidos WHERE producto_id = ? AND estado != 'recibido'");
    $stmt_check->execute([$ic['id']]);
    if ($stmt_check->fetchColumn() == 0 && $ic['proveedor_id']) {
        // Crear orden PUSH automática en estado 'pendiente'
        $orden_num = 'ORD-PUSH-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $cant_sugerida = ($ic['stock_minimo'] * 2) - $ic['stock_actual'];
        if ($cant_sugerida < 1) $cant_sugerida = 5;

        $stmt_ins = $db->prepare("INSERT INTO scm_pedidos (numero_orden_scm, producto_id, proveedor_id, cantidad, tipo, estado, fecha) VALUES (?, ?, ?, ?, 'Reposición Automática (Push)', 'pendiente', CURDATE())");
        $stmt_ins->execute([$orden_num, $ic['id'], $ic['proveedor_id'], $cant_sugerida]);
    }
}

// 2. Control AJAX / Petición interna para avanzar estados automáticamente según la simulación temporal
if (isset($_POST['ajax_accion']) && isset($_POST['pedido_id'])) {
    $id_p = intval($_POST['pedido_id']);
    $accion = $_POST['ajax_accion'];

    $stmt_p = $db->prepare("SELECT p.*, sp.estrategia_logistica FROM scm_pedidos p JOIN scm_productos sp ON p.producto_id = sp.id WHERE p.id = ?");
    $stmt_p->execute([$id_p]);
    $ped = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if ($ped) {
        if ($accion === 'a_proceso' && $ped['estado'] === 'pendiente') {
            $db->prepare("UPDATE scm_pedidos SET estado = 'en_proceso' WHERE id = ?")->execute([$id_p]);
            echo json_encode(['status' => 'success', 'nuevo_estado' => 'en_proceso']);
            exit;
        } elseif ($accion === 'a_recibido' && $ped['estado'] === 'en_proceso') {
            // Completar pedido PUSH o PULL: Actualizar estado, sumar stock y registrar movimiento de entrada
            $db->prepare("UPDATE scm_pedidos SET estado = 'recibido' WHERE id = ?")->execute([$id_p]);
            $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual + ? WHERE id = ?")->execute([$ped['cantidad'], $ped['producto_id']]);
            
            $db->prepare("INSERT INTO scm_movimientos (producto_id, tipo, cantidad, motivo, fecha, usuario_id) VALUES (?, 'Entrada', ?, ?, CURDATE(), ?)")
               ->execute([$ped['producto_id'], $ped['cantidad'], 'Recepción Automática ' . $ped['numero_orden_scm'], $_SESSION['admin_id'] ?? null]);

            echo json_encode(['status' => 'success', 'nuevo_estado' => 'recibido']);
            exit;
        }
    }
    echo json_encode(['status' => 'error']);
    exit;
}

// Control manual para PULL (por si se usa el botón de avance manual)
if (isset($_GET['accion']) && isset($_GET['id'])) {
    if (!$es_editable) {
        header("Location: pedidos.php?error=no_permisos");
        exit;
    }

    $id_pedido = intval($_GET['id']);
    $accion = $_GET['accion'];
    $stmt_check = $db->prepare("SELECT p.*, sp.estrategia_logistica FROM scm_pedidos p JOIN scm_productos sp ON p.producto_id = sp.id WHERE p.id = ?");
    $stmt_check->execute([$id_pedido]);
    $ped_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($ped_check && strtoupper($ped_check['estrategia_logistica']) === 'PULL') {
        if ($accion === 'avanzar' && $ped_check['estado'] === 'pendiente') {
            $db->prepare("UPDATE scm_pedidos SET estado = 'en_proceso' WHERE id = ?")->execute([$id_pedido]);
        } elseif ($accion === 'entregar' && $ped_check['estado'] === 'en_proceso') {
            $db->prepare("UPDATE scm_pedidos SET estado = 'recibido' WHERE id = ?")->execute([$id_pedido]);
            $db->prepare("UPDATE scm_productos SET stock_actual = stock_actual + ? WHERE id = ?")->execute([$ped_check['cantidad'], $ped_check['producto_id']]);
            $db->prepare("INSERT INTO scm_movimientos (producto_id, tipo, cantidad, motivo, fecha, usuario_id) VALUES (?, 'Entrada', ?, ?, CURDATE(), ?)")
               ->execute([$ped_check['producto_id'], $ped_check['cantidad'], 'Recepción Manual Pull ' . $ped_check['numero_orden_scm'], $_SESSION['admin_id'] ?? null]);
        }
    }
    header("Location: pedidos.php");
    exit;
}

$num_alertas_global = $db->query("SELECT COUNT(*) FROM scm_productos WHERE stock_actual <= stock_minimo")->fetchColumn();
$pedidos = $db->query("SELECT p.*, pr.nombre as proveedor_nombre, sp.nombre as producto_nombre, sp.estrategia_logistica FROM scm_pedidos p JOIN proveedores pr ON p.proveedor_id = pr.id JOIN scm_productos sp ON p.producto_id = sp.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Definir enlace de salida según el rol actual
$url_salida = '../index.php';
if ($rol_actual === 'gerente') {
    $url_salida = '../gerente/dashboard.php';
} elseif ($rol_actual === 'subgerente') {
    $url_salida = '../subgerente/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos SCM – Simulación Push/Pull</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --secondary: #000049;
      --sidebar-hover: #0369a1;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
      --sidebar-w: 260px;
      --radius: 10px;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; font-size: 14px; }

    .sidebar { width: var(--sidebar-w); background: var(--secondary); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; overflow-y: auto; }
    .sidebar-brand { padding: 24px; font-size: 18px; font-weight: 700; color: #ffffff; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .sidebar-brand span { color: var(--primary); }
    .sidebar-menu { padding: 20px 10px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .sidebar-item { padding: 12px 16px; border-radius: var(--radius); color: #94a3b8; text-decoration: none; font-weight: 500; font-size: 13px; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
    .sidebar-item:hover, .sidebar-item.active { background: var(--sidebar-hover); color: #ffffff; }
    .sidebar-item.active { font-weight: 600; }

    .main-content { margin-left: var(--sidebar-w); flex: 1; padding: 32px 40px; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
    .header h1 { font-size: 22px; font-weight: 700; color: var(--text); }
    .role-badge { background: rgba(2, 132, 199, 0.1); color: var(--primary); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }

    .alert-banner { background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid var(--danger); padding: 16px; border-radius: var(--radius); margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
    .alert-banner span.icon { font-size: 20px; }
    .alert-banner .content { color: #991b1b; font-weight: 500; }

    .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }

    .btn { background: var(--primary); color: #fff; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; transition: background 0.2s; }
    .btn:hover { background: var(--primary-hover); }

    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-shadow: var(--shadow-sm); overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: #f1f5f9; padding: 12px 16px; font-size: 11px; text-transform: uppercase; color: var(--muted); letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
    td { padding: 14px 16px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #f8fafc; }

    .badge-push { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #e0f2fe; color: #0369a1; display: inline-block; }
    .badge-pull { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #fef3c7; color: #d97706; display: inline-block; }

    .status-pendiente { color: #d97706; font-weight: 600; }
    .status-proceso { color: var(--primary); font-weight: 600; }
    .status-recibido { color: var(--success); font-weight: 600; }

    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 20px; }
      .sidebar { display: none; }
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Restaurant <span>App SCM</span></div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item"><span>📈</span> Dashboard SCM</a>
        <a href="productos.php" class="sidebar-item"><span>📦</span> Productos SCM</a>
        <a href="proveedores.php" class="sidebar-item"><span>🤝</span> Proveedores</a>
        
        <a href="inventario.php" class="sidebar-item">
            <span>📊</span> Inventario / Alertas 
            <?php if($num_alertas_global > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: auto; font-weight: 700;"><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>

        <a href="movimientos.php" class="sidebar-item"><span>🔄</span> Movimientos</a>
        <a href="pedidos.php" class="sidebar-item active"><span>🛒</span> Pedidos Internos</a>
        <a href="logistica.php" class="sidebar-item"><span>⚙️</span> Logística Push/Pull</a>
        
        <!-- Salida condicional: Cerrar sesión para Logística, Salir al Panel para Gerente/Subgerente -->
        <?php if ($rol_actual === 'logistica'): ?>
            <a href="logout.php" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>🚪</span> Cerrar sesión</a>
        <?php else: ?>
            <a href="<?= htmlspecialchars($url_salida, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-item" style="margin-top: auto; color: #fca5a5;"><span>←</span> Salir al Panel</a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <?php if ($num_alertas_global > 0): ?>
        <div class="alert-banner">
            <span class="icon">⚠️</span>
            <div class="content">
                <b>¡Atención SCM!</b> Hay <b><?= htmlspecialchars($num_alertas_global, ENT_QUOTES, 'UTF-8') ?></b> insumo(s) con stock crítico por debajo del mínimo permitido. 
                <a href="inventario.php?estado=critico" style="color: #b91c1c; font-weight: 700; text-decoration: underline; margin-left: 5px;">Ver inventario crítico</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'no_permisos'): ?>
        <div class="alert-error">⚠️ Acceso denegado: No tienes permisos de modificación en este módulo.</div>
    <?php endif; ?>

    <div class="header">
        <div>
            <h1>🛒 Pedidos Internos (Simulación Push Automática vs Pull)</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="role-badge">Rol: <?= htmlspecialchars(ucfirst($rol_actual), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($es_editable): ?>
                <a href="pedido_form.php" class="btn">+ Generar Pedido Pull Manual</a>
            <?php else: ?>
                <span style="color: var(--muted); font-size: 13px; font-style: italic;">Modo consulta (Lectura)</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Orden #</th>
                    <th>Insumo</th>
                    <th>Proveedor</th>
                    <th>Cantidad</th>
                    <th>Estrategia Logística</th>
                    <th>Estado Actual</th>
                    <th style="text-align: right;">Acciones / Simulación temporal</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pedidos)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:30px;">No hay pedidos internos registrados en el sistema.</td></tr>
                <?php else: foreach($pedidos as $pe): 
                    $es_push = (strtoupper($pe['estrategia_logistica'] ?? 'PUSH') === 'PUSH');
                    $estado = $pe['estado'] ?? 'pendiente';
                ?>
                    <tr id="row-<?= $pe['id'] ?>" data-id="<?= $pe['id'] ?>" data-estrategia="<?= $es_push ? 'PUSH' : 'PULL' ?>" data-estado="<?= $estado ?>">
                        <td><b><?= htmlspecialchars($pe['numero_orden_scm'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td><?= htmlspecialchars($pe['producto_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($pe['proveedor_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><b><?= htmlspecialchars($pe['cantidad'], ENT_QUOTES, 'UTF-8') ?></b></td>
                        <td>
                            <?php if($es_push): ?>
                                <span class="badge-push">PUSH (Automático)</span>
                            <?php else: ?>
                                <span class="badge-pull">PULL (Bajo Demanda)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge-text">
                                <?php if($estado === 'pendiente' || $estado === ''): ?>
                                    <span class="status-pendiente">⏳ Pendiente (15s inicio)</span>
                                <?php elseif($estado === 'en_proceso'): ?>
                                    <span class="status-proceso">🔄 En Proceso (10s entrega)</span>
                                <?php else: ?>
                                    <span class="status-recibido">✔ Recibido (+ Stock registrado)</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td style="text-align: right;" class="action-cell">
                            <?php if($es_push): ?>
                                <?php if($estado !== 'recibido'): ?>
                                    <span style="color: var(--primary); font-size: 12px; font-weight: 600;" class="sim-timer-text">Simulando cadena automática...</span>
                                <?php else: ?>
                                    <span style="color:var(--success); font-size:12px; font-weight:600;">Completado en Movimientos</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if($es_editable): ?>
                                    <?php if($estado === 'pendiente' || $estado === ''): ?>
                                        <a href="pedidos.php?accion=avanzar&id=<?= $pe['id'] ?>" class="btn" style="padding: 6px 12px; font-size:11px; background:var(--warning); color:#fff;">Pasar a En Proceso</a>
                                    <?php elseif($estado === 'en_proceso'): ?>
                                        <a href="pedidos.php?accion=entregar&id=<?= $pe['id'] ?>" class="btn" style="padding: 6px 12px; font-size:11px; background:var(--success);">Recibir / Sumar Stock</a>
                                    <?php else: ?>
                                        <span style="color:var(--muted); font-size:12px; font-weight:600;">Completado</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--muted); font-size:12px; font-style:italic;">Solo lectura</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Script de simulación automática en tiempo real para las órdenes PUSH
document.addEventListener("DOMContentLoaded", function() {
    const rows = document.querySelectorAll("tr[data-estrategia='PUSH']");

    rows.forEach(row => {
        const id = row.getAttribute("data-id");
        let estado = row.getAttribute("data-estado");

        if (estado === 'pendiente' || estado === '') {
            // Esperar 15 segundos para pasar de Pendiente a En Proceso automáticamente
            setTimeout(() => {
                actualizarEstadoPedido(id, 'a_proceso', row);
            }, 15000);
        } else if (estado === 'en_proceso') {
            // Si ya está en proceso, esperar 10 segundos adicionales para recibir e impactar movimientos/stock
            setTimeout(() => {
                actualizarEstadoPedido(id, 'a_recibido', row);
            }, 10000);
        }
    });

    function actualizarEstadoPedido(id, accion, row) {
        const formData = new FormData();
        formData.append('ajax_accion', accion);
        formData.append('pedido_id', id);

        fetch('pedidos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const statusText = row.querySelector(".status-badge-text");
                const actionCell = row.querySelector(".action-cell");

                if (data.nuevo_estado === 'en_proceso') {
                    statusText.innerHTML = '<span class="status-proceso">🔄 En Proceso (10s entrega)</span>';
                    row.setAttribute("data-estado", 'en_proceso');
                    
                    // Programar el siguiente paso (10 segundos más para entregado)
                    setTimeout(() => {
                        actualizarEstadoPedido(id, 'a_recibido', row);
                    }, 10000);

                } else if (data.nuevo_estado === 'recibido') {
                    statusText.innerHTML = '<span class="status-recibido">✔ Recibido (+ Stock registrado)</span>';
                    actionCell.innerHTML = '<span style="color:var(--success); font-size:12px; font-weight:600;">Registrado en Movimientos</span>';
                    row.setAttribute("data-estado", 'recibido');
                }
            }
        })
        .catch(error => console.error('Error en simulación automática:', error));
    }
});
</script>

</body>
</html>