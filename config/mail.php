<?php
/**
 * Archivo: config/mail.php
 * Descripción: Servicio de envío de correos electrónicos mediante PHPMailer optimizado.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar PHPMailer desde la estructura manual en libs/
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

/**
 * Envía un correo electrónico con el PIN de recuperación de contraseña.
 * 
 * @param string $destinatario Correo electrónico del destinatario.
 * @param string $nombre Nombre del usuario.
 * @param string $pin Código PIN de seguridad temporal.
 * @return bool True si se envió correctamente, false en caso de error.
 */
function enviarCorreoRecuperacion($destinatario, $nombre, $pin) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP 
        // (Se recomienda definir MAIL_USER y MAIL_PASS en config.php por seguridad)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('MAIL_USER') ? MAIL_USER : 'eloyguadalupesalasgonzalez@gmail.com'; // O tu correo configurado
        $mail->Password   = defined('MAIL_PASS') ? MAIL_PASS : 'rele ihsw obmr lbdc'; // Contraseña de aplicación
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Obtener nombre del sistema de forma dinámica desde config.php o usar valor por defecto
        $nombreSitio = defined('SITE_NAME') ? SITE_NAME : 'RestaurApp';

        // Remitente y destinatario
        $mail->setFrom($mail->Username, $nombreSitio . ' Soporte');
        $mail->addAddress($destinatario, $nombre);

        // Contenido del correo en formato HTML profesional
        $mail->isHTML(true);
        $mail->Subject = 'Código de recuperación de contraseña - ' . $nombreSitio;
        
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; padding: 25px; color: #0f172a; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
            <h2 style="color: #0284c7; text-align: center; margin-top: 0;">Recuperación de Contraseña</h2>
            <p>Hola <b>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</b>,</p>
            <p>Has solicitado restablecer tu contraseña en el panel de <b>' . htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') . '</b>. Tu PIN temporal de seguridad es:</p>
            <div style="background: #f1f5f9; padding: 15px; text-align: center; font-size: 26px; font-weight: bold; letter-spacing: 6px; color: #0284c7; border-radius: 8px; margin: 25px 0; border: 1px dashed #cbd5e1;">
                ' . htmlspecialchars($pin, ENT_QUOTES, 'UTF-8') . '
            </div>
            <p style="font-size: 13px; color: #64748b; line-height: 1.4;">Este código expira pronto. Si tú no solicitaste este cambio, puedes ignorar este mensaje de forma segura.</p>
            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
            <p style="font-size: 11px; color: #94a3b8; text-align: center;">Este es un mensaje automático, por favor no respondas a este correo.</p>
        </div>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Registrar error en el log del servidor para facilitar la depuración
        error_log("Error al enviar correo con PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}
?>