<?php
require_once 'config.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendReportByEmail($email, $filepath, $filename) {
    try {
        $mail = new PHPMailer(true);
        
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Cambia según tu proveedor
        $mail->SMTPAuth = true;
        $mail->Username = 'tu-email@gmail.com'; // Configura tu email
        $mail->Password = 'tu-password-app'; // Configura tu contraseña de app
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Remitente y destinatario
        $mail->setFrom('noreply@inventario.com', SITE_NAME);
        $mail->addAddress($email);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = 'Reporte de Inventario - ' . SITE_NAME;
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px;'>
                    📊 Reporte de Inventario
                </h2>
                
                <p>Hola,</p>
                
                <p>Se ha generado un reporte completo de tu inventario según tu solicitud.</p>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #495057;'>Detalles del Reporte:</h3>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li><strong>Fecha de generación:</strong> " . date('d/m/Y H:i:s') . "</li>
                        <li><strong>Archivo:</strong> {$filename}</li>
                        <li><strong>Formato:</strong> Excel (CSV)</li>
                        <li><strong>Sistema:</strong> " . SITE_NAME . "</li>
                    </ul>
                </div>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h4 style='margin-top: 0; color: #1976d2;'>Contenido del Reporte:</h4>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>Lista completa de productos</li>
                        <li>Categorías y ubicaciones</li>
                        <li>Estados y valores</li>
                        <li>Fechas de registro</li>
                        <li>Información de proveedores</li>
                    </ul>
                </div>
                
                <p><strong>El archivo está adjunto a este email.</strong></p>
                
                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 0.9em;'>
                    Saludos,<br>
                    <strong>" . SITE_NAME . "</strong><br>
                    Sistema de Gestión de Inventario
                </p>
            </div>
        </body>
        </html>
        ";
        
        // Adjuntar archivo
        if (file_exists($filepath)) {
            $mail->addAttachment($filepath, $filename);
        }
        
        // Enviar email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Si falla PHPMailer, intentar con mail() básico
        return sendBasicEmail($email, $filepath, $filename);
    }
}

function sendBasicEmail($email, $filepath, $filename) {
    $subject = 'Reporte de Inventario - ' . SITE_NAME;
    $message = "
    Reporte de Inventario
    
    Se ha generado un reporte completo de tu inventario.
    
    Detalles:
    - Fecha: " . date('d/m/Y H:i:s') . "
    - Archivo: {$filename}
    - Formato: Excel (CSV)
    
    El archivo está adjunto.
    
    Saludos,
    " . SITE_NAME;
    
    $headers = [
        'From: ' . SITE_NAME . ' <noreply@inventario.com>',
        'Reply-To: noreply@inventario.com',
        'Content-Type: text/plain; charset=UTF-8'
    ];
    
    return mail($email, $subject, $message, implode("\r\n", $headers));
}

// Función para configurar email del usuario
function setUserEmail($email) {
    $_SESSION['user_email'] = $email;
}

// Función para obtener email del usuario
function getUserEmail() {
    return $_SESSION['user_email'] ?? null;
}
?> 