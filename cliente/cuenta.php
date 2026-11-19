<?php
require_once __DIR__ . '/../config/config.php';

// Importar PHPMailer para el cambio de contraseña
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

if (!isset($_SESSION['cliente_id'])) {
    header('Location: login_cliente.php');
    exit;
}

$db = getDB();
$cliente_id = (int)$_SESSION['cliente_id'];

$mensaje_perfil = ''; $tipo_perfil = 'success';
$mensaje_ticket = ''; $tipo_ticket = 'success';
$mensaje_pass   = ''; $tipo_pass   = 'success';
$mensaje_recarga = ''; $tipo_recarga = 'success';

// Procesamiento de formularios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Actualizar Datos Personales
    if (isset($_POST['actualizar_perfil'])) {
        $nombre   = sanitize($_POST['nombre'] ?? '');
        $telefono = sanitize($_POST['telefono'] ?? '');

        if (!empty($nombre)) {
            $update = $db->prepare("UPDATE usuarios_cliente SET nombre = ?, telefono = ? WHERE id = ?");
            $update->execute([$nombre, $telefono, $cliente_id]);
            
            $_SESSION['cliente_nombre'] = $nombre;
            $mensaje_perfil = "Datos actualizados correctamente.";
        } else {
            $tipo_perfil = 'error';
            $mensaje_perfil = "El nombre no puede estar vacío.";
        }
    } 
    // 2. Enviar Ticket de Soporte
    elseif (isset($_POST['crear_ticket'])) {
        $asunto_sel = sanitize($_POST['asunto_select'] ?? '');
        $asunto_otro = sanitize($_POST['asunto_otro'] ?? '');
        $asunto = ($asunto_sel === 'Otra' && !empty($asunto_otro)) ? $asunto_otro : $asunto_sel;
        $mensaje_t = sanitize($_POST['mensaje_ticket'] ?? '');
        
        if (!empty($asunto) && !empty($mensaje_t)) {
            $stmt_t_ins = $db->prepare("INSERT INTO tickets_soporte (cliente_id, asunto, mensaje, estado, creado_en) VALUES (?, ?, ?, 'En proceso', NOW())");
            $stmt_t_ins->execute([$cliente_id, $asunto, $mensaje_t]);
            $mensaje_ticket = "¡Ticket enviado con éxito! Soporte le dará seguimiento pronto.";
        } else {
            $tipo_ticket = 'error';
            $mensaje_ticket = "El asunto y el mensaje del ticket son obligatorios.";
        }
    } 
    // 3. Enviar Evaluación
    elseif (isset($_POST['enviar_evaluacion'])) {
        $puntuacion = intval($_POST['puntuacion'] ?? 5);
        $tipo       = sanitize($_POST['tipo'] ?? 'Servicio');
        $comentario = sanitize($_POST['comentario'] ?? '');

        if ($puntuacion >= 1 && $puntuacion <= 5 && !empty($comentario)) {
            $stmt_ev = $db->prepare("INSERT INTO evaluaciones (cliente_id, puntuacion, tipo, comentario, fecha) VALUES (?, ?, ?, ?, NOW())");
            $stmt_ev->execute([$cliente_id, $puntuacion, $tipo, $comentario]);
            $mensaje_perfil = "¡Gracias por tu opinión! Evaluación enviada con éxito.";
        } else {
            $tipo_perfil = 'error';
            $mensaje_perfil = "La puntuación debe ser de 1 a 5 y el comentario es obligatorio.";
        }
    }
    // 4. Solicitar Cambio de Contraseña por Correo
    elseif (isset($_POST['solicitar_cambio_pass'])) {
        $stmtC = $db->prepare("SELECT email, nombre FROM usuarios_cliente WHERE id = ?");
        $stmtC->execute([$cliente_id]);
        $cliInfo = $stmtC->fetch(PDO::FETCH_ASSOC);

        if ($cliInfo) {
            $token_pass = bin2hex(random_bytes(32));
            $updToken = $db->prepare("UPDATE usuarios_cliente SET token_verificacion = ? WHERE id = ?");
            $updToken->execute([$token_pass, $cliente_id]);

            $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $enlace = "$protocolo://$host/restaurant_app/cliente/reset_password.php?token=" . $token_pass;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'eloyguadalupesalasgonzalez@gmail.com';
                $mail->Password   = 'rele ihsw obmr lbdc';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('no-reply@restaurapp.com', 'RestaurApp');
                $mail->addAddress($cliInfo['email'], $cliInfo['nombre']);

                $mail->isHTML(true);
                $mail->Subject = 'Solicitud de Cambio de Contraseña - RestaurApp';
                $mail->Body    = "
                    <h3>¡Hola, {$cliInfo['nombre']}!</h3>
                    <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para continuar:</p>
                    <p><a href='{$enlace}' style='padding: 10px 15px; background: #c8502a; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold;'>Cambiar mi Contraseña</a></p>
                ";
                $mail->send();
                $mensaje_pass = "Se ha enviado un enlace de verificación a tu correo electrónico.";
            } catch (Exception $e) {
                $tipo_pass = 'error';
                $mensaje_pass = "Error al enviar el correo: {$mail->ErrorInfo}";
            }
        }
    }
    // 5. Procesar Recarga de Monedero con Formas de Pago
    elseif (isset($_POST['procesar_recarga'])) {
        $monto = floatval($_POST['monto_recarga'] ?? 0);
        $forma_pago = sanitize($_POST['forma_pago_recarga'] ?? 'tarjeta');
        $recordar_tarjeta = isset($_POST['recordar_tarjeta']) ? 1 : 0;

        if ($monto <= 0) {
            $tipo_recarga = 'error';
            $mensaje_recarga = "Ingresa un monto válido mayor a $0.";
        } else {
            // Si marca recordar tarjeta, guardamos simulación en sesión para este usuario
            if ($forma_pago === 'tarjeta' && $recordar_tarjeta) {
                $_SESSION['tarjeta_guardada_' . $cliente_id] = '•••• •••• •••• 4242';
            }

            if ($forma_pago === 'efectivo') {
                // Estado pendiente esperando confirmación de caja
                $mensaje_recarga = "Solicitud de recarga por $" . number_format($monto, 2) . " generada en Efectivo. Esperando confirmación de caja.";
                // Aquí podrías insertarlo en una tabla de solicitudes pendientes si lo deseas
            } else {
                // Acreditación inmediata para Tarjeta o Transferencia simulada
                $stmtReg = $db->prepare("INSERT INTO monedero_movimientos (cliente_id, tipo, monto, detalle, fecha) VALUES (?, 'Recarga', ?, ?, NOW())");
                // Si no tienes tabla monedero_movimientos en BD, usamos sesión específica por usuario:
                if (!isset($_SESSION['movimientos_' . $cliente_id])) {
                    $_SESSION['movimientos_' . $cliente_id] = [];
                }
                $_SESSION['movimientos_' . $cliente_id][] = [
                    'fecha' => date('Y-m-d H:i:s'),
                    'tipo' => 'Recarga Monedero (' . ucfirst($forma_pago) . ')',
                    'monto' => $monto,
                    'detalle' => 'Acreditado exitosamente'
                ];
                $mensaje_recarga = "¡Recarga de $" . number_format($monto, 2) . " acreditada con éxito en tu monedero!";
            }
        }
    }
}

// Obtener datos del cliente actual de la BD
$stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Inicializar movimientos del usuario si no existen
if (!isset($_SESSION['movimientos_' . $cliente_id])) {
    $_SESSION['movimientos_' . $cliente_id] = [
        ['fecha' => '2026-11-15 14:30', 'tipo' => 'Recarga Inicial (SPEI)', 'monto' => 500.00, 'detalle' => 'Depósito bancario'],
        ['fecha' => '2026-11-10 20:15', 'tipo' => 'Pago de Pedido', 'monto' => -250.00, 'detalle' => 'Consumo en Mesa']
    ];
}

// Calcular saldo exclusivamente para este usuario
$saldo_usuario = 0.00;
foreach ($_SESSION['movimientos_' . $cliente_id] as $m) {
    $saldo_usuario += floatval($m['monto']);
}

// Obtener evaluaciones y tickets específicos de este usuario
$stmt_evs = $db->prepare("SELECT * FROM evaluaciones WHERE cliente_id = ? ORDER BY fecha DESC");
$stmt_evs->execute([$cliente_id]);
$mis_evaluaciones = $stmt_evs->fetchAll(PDO::FETCH_ASSOC);

$stmt_t = $db->prepare("SELECT * FROM tickets_soporte WHERE cliente_id = ? ORDER BY creado_en DESC");
$stmt_t->execute([$cliente_id]);
$mis_tickets = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

$mesa_param = '';
if (!empty($_GET['mesa'])) {
    $mesa_param = '?mesa=' . urlencode($_GET['mesa']);
} elseif (!empty($_SESSION['mesa_token'])) {
    $mesa_param = '?mesa=' . urlencode($_SESSION['mesa_token']);
}

$tarjeta_guardada = $_SESSION['tarjeta_guardada_' . $cliente_id] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Mi Cuenta, Monedero y Soporte – RestaurApp</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
          --bg: #faf8f5; --surface: #ffffff; --border: #ede8e0;
          --accent: #c8502a; --accent2: #e8733a; --text: #1a1510; --muted: #8a7a6a;
          --card-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        body { 
            background: var(--bg); 
            color: var(--text); 
            font-family: 'DM Sans', sans-serif; 
            padding: 24px 16px; 
            max-width: 480px; 
            margin: 0 auto; 
            font-size: 14px;
            padding-bottom: 60px;
        }
        .btn-back { display: inline-block; color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .card { background: var(--surface); border: 1px solid var(--border); padding: 20px; border-radius: 16px; margin-bottom: 16px; box-shadow: var(--card-shadow); }
        .monedero-box { background: rgba(200,80,42,0.05); border: 1px solid var(--accent); border-radius: 14px; padding: 20px; text-align: center; }
        .monedero-val { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: bold; color: var(--accent); margin: 6px 0; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 11px; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        input, select, textarea { width: 100%; padding: 10px 14px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); }
        input[disabled] { opacity: 0.6; cursor: not-allowed; background: #f0ece6; }
        textarea { resize: vertical; min-height: 80px; }
        button { width: 100%; padding: 13px; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #ffffff; border: none; font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 14px; border-radius: 10px; cursor: pointer; margin-top: 6px; box-shadow: 0 4px 15px rgba(200,80,42,0.3); }
        .btn-logout { display: block; text-align: center; color: #e07070; text-decoration: none; font-size: 13px; font-weight: 600; margin-top: 16px; }
        .btn-logout:hover { text-decoration: underline; }
        .alert { padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; text-align: center; }
        .alert-success { background: #e4f7fa; color: #07543f; border: 1px solid #c7eadc; }
        .alert-error { background: #fde9eb; color: #c8424c; border: 1px solid #f4c7cc; }
        .eval-item, .ticket-item, .movimiento-item { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 12px; margin-bottom: 10px; font-size: 13px; }
        .ticket-item { border-left: 4px solid var(--accent); }
        .stars { color: #f59e0b; font-weight: bold; }
        .monto-chip { flex: 1; padding: 8px; text-align: center; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); font-size: 13px; font-weight: 600; cursor: pointer; color: var(--muted); }
        .monto-chip:hover { border-color: var(--accent); color: var(--accent); }
    </style>
</head>
<body>

    <a href="index.php<?= $mesa_param ?>" class="btn-back">← Volver al Menú</a>
    
    <!-- Bloque de Saldo en Monedero -->
    <div class="card monedero-box">
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:1px; font-weight:600;">Mi Monedero (<?= htmlspecialchars($cliente['nombre']) ?>)</div>
        <div class="monedero-val">$<?= number_format($saldo_usuario, 2) ?></div>
        <small style="color:var(--muted); font-size:12px;">Ganas $1 de reembolso por cada $100 de compra</small>
    </div>

    <!-- Recargar Monedero con Formas de Pago -->
    <div class="card">
        <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:14px;">💰 Recargar Saldo</h3>
        
        <?php if ($mensaje_recarga): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_recarga) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="procesar_recarga" value="1">
            
            <label>Selecciona o ingresa monto ($10 a $500+)</label>
            <div style="display:flex; gap:6px; margin-bottom:10px;">
                <button type="button" class="monto-chip" onclick="document.getElementById('monto_recarga').value=10">$10</button>
                <button type="button" class="monto-chip" onclick="document.getElementById('monto_recarga').value=50">$50</button>
                <button type="button" class="monto-chip" onclick="document.getElementById('monto_recarga').value=100">$100</button>
                <button type="button" class="monto-chip" onclick="document.getElementById('monto_recarga').value=200">$200</button>
                <button type="button" class="monto-chip" onclick="document.getElementById('monto_recarga').value=500">$500</button>
            </div>
            
            <div class="form-group">
                <input type="number" id="monto_recarga" name="monto_recarga" placeholder="Monto personalizado ($)" min="1" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Forma de Pago</label>
                <select name="forma_pago_recarga" id="forma_pago_recarga" onchange="cambiarFormaPagoRecarga()" required>
                    <option value="tarjeta">Tarjeta de Crédito / Débito</option>
                    <option value="transferencia">Transferencia Bancaria (SPEI)</option>
                    <option value="efectivo">Efectivo (Pago en Caja / Sucursal)</option>
                </select>
            </div>

            <!-- Contenedor dinámico según forma de pago -->
            <div id="info_pago_tarjeta">
                <?php if ($tarjeta_guardada): ?>
                    <div style="background:var(--bg); padding:10px; border-radius:8px; border:1px solid var(--border); margin-bottom:10px; font-size:13px;">
                        💳 Tarjeta Guardada: <b><?= $tarjeta_guardada ?></b>
                    </div>
                    <div class="form-group">
                        <label>CVV (Código de Seguridad)</label>
                        <input type="password" name="cvv" placeholder="123" maxlength="4">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Número de Tarjeta</label>
                        <input type="text" placeholder="4532 •••• •••• 8920">
                    </div>
                    <div style="display:flex; gap:8px;">
                        <div style="flex:1;"><label>Vencimiento</label><input type="text" placeholder="MM/AA"></div>
                        <div style="flex:1;"><label>CVV</label><input type="password" placeholder="123" maxlength="4"></div>
                    </div>
                    <div style="margin: 10px 0; font-size:13px;">
                        <label style="display:inline; text-transform:none;"><input type="checkbox" name="recordar_tarjeta" value="1"> Recordar tarjeta para futuras recargas</label>
                    </div>
                <?php endif; ?>
            </div>

            <div id="info_pago_transferencia" style="display:none; background:var(--bg); padding:12px; border-radius:8px; border:1px solid var(--border); margin-bottom:14px; font-size:13px;">
                <b>🏦 Datos para Transferencia SPEI:</b><br>
                Banco: BBVA México<br>
                CLABE: <code>123456789012345678</code><br>
                Beneficiario: RestaurApp S.A. de C.V.<br>
                <i>El saldo se acreditará al confirmar la recepción.</i>
            </div>

            <div id="info_pago_efectivo" style="display:none; background:var(--bg); padding:12px; border-radius:8px; border:1px solid var(--border); margin-bottom:14px; font-size:13px;">
                💵 <b>Pago en Efectivo / Caja:</b> Al generar la solicitud, acude a caja para realizar tu pago. El estado quedará <i style="color:var(--accent);">Esperando confirmación de caja</i> para verse reflejado en tu monedero.
            </div>

            <button type="submit">Procesar Recarga</button>
        </form>

        <div style="margin-top: 18px;">
            <label style="margin-bottom: 8px;">Mis Movimientos de Monedero</label>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach (array_reverse($_SESSION['movimientos_' . $cliente_id]) as $mov): ?>
                    <div class="movimiento-item" style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <b><?= htmlspecialchars($mov['tipo']) ?></b><br>
                            <small style="color:var(--muted);"><?= htmlspecialchars($mov['detalle']) ?> – <?= htmlspecialchars($mov['fecha']) ?></small>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-weight:bold; color:<?= floatval($mov['monto']) >= 0 ? '#2d8a56' : '#c8502a' ?>;">
                                <?= (floatval($mov['monto']) > 0 ? '+' : '') . '$' . number_format(floatval($mov['monto']), 2) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Datos Personales y Contraseña -->
    <div class="card">
        <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:14px;">Mis Datos Personales</h3>
        
        <?php if ($mensaje_perfil): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_perfil) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="actualizar_perfil" value="1">
            <div class="form-group">
                <label>Nombre Completo</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Correo Electrónico (No editable)</label>
                <input type="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" disabled>
            </div>
            
            <div class="form-group">
                <label>Teléfono</label>
                <input type="tel" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>" placeholder="Ej. 4491234567">
            </div>
            
            <button type="submit">Guardar Cambios</button>
        </form>

        <hr style="border:0; border-top:1px solid var(--border); margin:20px 0;">

        <h4 style="font-size:15px; margin-bottom:10px;">🔐 Cambiar Contraseña</h4>
        <p style="font-size:12px; color:var(--muted); margin-bottom:12px;">Te enviaremos un enlace de verificación a tu correo para restablecer tu contraseña.</p>
        
        <?php if ($mensaje_pass): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_pass) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="solicitar_cambio_pass" value="1">
            <button type="submit" style="background:#4a5568;">Solicitar Enlace de Cambio</button>
        </form>
    </div>

    <!-- Soporte y Tickets -->
    <div class="card">
        <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:14px;">💬 Soporte y Tickets</h3>
        
        <?php if ($mensaje_ticket): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_ticket) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="crear_ticket" value="1">
            <div class="form-group">
                <label>Asunto / Duda</label>
                <select name="asunto_select" id="asunto_select" onchange="toggleAsuntoOtro()" required>
                    <option value="Duda sobre mi pedido">Duda sobre mi pedido</option>
                    <option value="Cambio de producto">Cambio de producto</option>
                    <option value="Facturación">Facturación</option>
                    <option value="Problema con monedero">Problema con monedero</option>
                    <option value="Otra">Otra (Escribir asunto...)</option>
                </select>
            </div>

            <div class="form-group" id="grupo_asunto_otro" style="display:none;">
                <label>Escribe tu Asunto Personalizado</label>
                <input type="text" name="asunto_otro" id="asunto_otro" placeholder="Ej. Sugerencia de menú...">
            </div>

            <div class="form-group">
                <label>Mensaje / Consulta</label>
                <textarea name="mensaje_ticket" placeholder="Escribe tu consulta detallada..." required rows="3"></textarea>
            </div>

            <button type="submit">Enviar Ticket</button>
        </form>

        <div style="margin-top: 18px;">
            <label style="margin-bottom: 8px;">Mis Tickets Registrados</label>
            <?php if (empty($mis_tickets)): ?>
                <p style="color: var(--muted); font-size: 12px;">No tienes tickets abiertos.</p>
            <?php else: ?>
                <?php foreach ($mis_tickets as $t): ?>
                    <div class="ticket-item">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <b>#<?= $t['id'] ?> - <?= htmlspecialchars($t['asunto']) ?></b>
                            <small style="color: var(--accent); font-weight:bold;"><?= htmlspecialchars($t['estado']) ?></small>
                        </div>
                        <p style="color:var(--text); margin-bottom:4px;"><?= htmlspecialchars($t['mensaje']) ?></p>
                        <small style="color:var(--muted);"><?= $t['creado_en'] ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Califícanos -->
    <div class="card">
        <h3 style="font-family:'Playfair Display',serif; font-size:18px; margin-bottom:14px;">⭐ Califícanos</h3>
        <form method="POST">
            <input type="hidden" name="enviar_evaluacion" value="1">
            <div class="form-group">
                <label>Puntuación</label>
                <select name="puntuacion" required>
                    <option value="5">⭐⭐⭐⭐⭐ (5 - Excelente)</option>
                    <option value="4">⭐⭐⭐⭐ (4 - Muy bueno)</option>
                    <option value="3">⭐⭐⭐ (3 - Bueno)</option>
                    <option value="2">⭐⭐ (2 - Regular)</option>
                    <option value="1">⭐ (1 - Malo)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Categoría</label>
                <select name="tipo" required>
                    <option value="Servicio">Servicio</option>
                    <option value="Comida">Comida</option>
                    <option value="Ambiente">Ambiente</option>
                    <option value="General">General</option>
                </select>
            </div>
            <div class="form-group">
                <label>Comentario</label>
                <textarea name="comentario" placeholder="Cuéntanos tu experiencia..." required></textarea>
            </div>
            <button type="submit">Enviar Evaluación</button>
        </form>
        <a href="logout_cliente.php<?= $mesa_param ?>" class="btn-logout">🚪 Cerrar Sesión</a>
    </div>

<script>
function cambiarFormaPagoRecarga() {
    const forma = document.getElementById('forma_pago_recarga').value;
    document.getElementById('info_pago_tarjeta').style.display = (forma === 'tarjeta') ? 'block' : 'none';
    document.getElementById('info_pago_transferencia').style.display = (forma === 'transferencia') ? 'block' : 'none';
    document.getElementById('info_pago_efectivo').style.display = (forma === 'efectivo') ? 'block' : 'none';
}

function toggleAsuntoOtro() {
    const select = document.getElementById('asunto_select');
    const grupoOtro = document.getElementById('grupo_asunto_otro');
    const inputOtro = document.getElementById('asunto_otro');
    if (select.value === 'Otra') {
        grupoOtro.style.display = 'block';
        inputOtro.required = true;
    } else {
        grupoOtro.style.display = 'none';
        inputOtro.required = false;
        inputOtro.value = '';
    }
}
</script>
</body>
</html>