<?php
// config/mail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar PHPMailer desde tu estructura manual en libs/
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

function enviarCorreoRecuperacion($destinatario, $nombre, $pin) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP (Ejemplo con Gmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'eloyguadalupesalasgonzalez@gmail.com';         // <-- CAMBIA ESTO por tu correo real
        $mail->Password   = 'rele ihsw obmr lbdc'; // <-- CAMBIA ESTO por tu Contraseña de Aplicación de Google
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Remitente y destinatario
        $mail->setFrom('eloyguadalupesalasgonzalez@gmail.com', 'Restaurant App Soporte');
        $mail->addAddress($destinatario, $nombre);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Código de recuperación de contraseña - Restaurant App';
        
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; padding: 20px; color: #0f172a; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 10px;">
            <h2 style="color: #0284c7; text-align: center;">Recuperación de Contraseña</h2>
            <p>Hola <b>' . htmlspecialchars($nombre) . '</b>,</p>
            <p>Has solicitado restablecer tu contraseña en el panel de Artesanía MX. Tu PIN temporal de seguridad es:</p>
            <div style="background: #f1f5f9; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #0284c7; border-radius: 8px; margin: 20px 0;">
                ' . $pin . '
            </div>
            <p style="font-size: 12px; color: #64748b;">Este código expira pronto. Si tú no solicitaste este cambio, puedes ignorar este mensaje.</p>
        </div>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}