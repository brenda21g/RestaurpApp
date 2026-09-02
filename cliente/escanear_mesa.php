<?php
require_once __DIR__ . '/../config/config.php';

// Si no está logueado, redirige al login de clientes
if (!isset($_SESSION['cliente_id'])) {
    header('Location: login.php?msg=escanea_despues_de_login');
    exit;
}

// Si la cámara envía la mesa como parámetro GET
if (isset($_GET['mesa']) && !empty($_GET['mesa'])) {
    $_SESSION['mesa_token'] = sanitize($_GET['mesa']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear Mesa - <?= SITE_NAME ?></title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { font-family: Arial, sans-serif; background-color: #faf8f5; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .container { max-width: 450px; width: 100%; background: #fff; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        #reader { width: 100%; margin-top: 15px; border-radius: 8px; overflow: hidden; }
    </style>
</head>
<body>

<div class="container">
    <h2>📷 Escanear Código QR de tu Mesa</h2>
    <p style="font-size:13px; color:#666;">Permite el acceso a la cámara para escanear la mesa.</p>
    <div id="reader"></div>
    <div id="resultado" style="margin-top: 10px; font-weight: bold; color: green;"></div>
</div>

<script>
    function onScanSuccess(decodedText) {
        html5QrcodeScanner.clear();
        document.getElementById('resultado').innerText = "¡Mesa detectada! Cargando menú...";

        let token = decodedText;
        if (decodedText.includes('mesa=')) {
            const urlParams = new URLSearchParams(decodedText.split('?')[1]);
            token = urlParams.get('mesa');
        }

        window.location.href = "index.php?mesa=" + encodeURIComponent(token);
    }

    let html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: { width: 250, height: 250 } }, false);
    html5QrcodeScanner.render(onScanSuccess);
</script>

</body>
</html>