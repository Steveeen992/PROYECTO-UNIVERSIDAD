<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$message = '';
$messageType = '';
$debugResetLink = '';

function getAppBaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($path ? $path : '');
}

function sendResetPasswordEmail($toEmail, $toName, $resetLink) {
    $subject = 'Recuperación de contraseña - ' . SITE_NAME;
    $html = "
        <p>Hola " . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ",</p>
        <p>Recibimos una solicitud para restablecer tu contraseña.</p>
        <p>Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
        <p><a href='" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "'>Restablecer contraseña</a></p>
        <p>Este enlace expira en 60 minutos y solo se puede usar una vez.</p>
        <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";

    return @mail($toEmail, $subject, $html, $headers);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Ingresa un correo electrónico válido.';
        $messageType = 'error';
    } else {
        $user = $db->fetch("SELECT id, nombre, email FROM usuarios WHERE email = ? LIMIT 1", [$email]);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = password_hash($token, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

            // Invalidar solicitudes anteriores no usadas para este usuario
            $db->query("UPDATE password_resets SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL", [$user['id']]);
            $db->query(
                "INSERT INTO password_resets (usuario_id, token_hash, expires_at) VALUES (?, ?, ?)",
                [$user['id'], $tokenHash, $expiresAt]
            );

            $resetLink = getAppBaseUrl() . '/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($user['email']);
            $sent = sendResetPasswordEmail($user['email'], $user['nombre'], $resetLink);

            if (!$sent && (($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:'))) {
                // En desarrollo local, mostrar el enlace cuando no hay servidor SMTP configurado.
                $debugResetLink = $resetLink;
            }
        }

        // Mensaje genérico para no revelar si el correo existe.
        $message = 'Si el correo existe en el sistema, te enviamos un enlace para restablecer tu contraseña.';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-form">
            <h1>¿Olvidaste tu contraseña?</h1>
            <p>Te enviaremos un enlace de recuperación a tu correo.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($debugResetLink): ?>
                <div class="alert alert-warning">
                    No se pudo enviar correo (entorno local). Usa este enlace para continuar:<br>
                    <a href="<?php echo htmlspecialchars($debugResetLink, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($debugResetLink, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" required autocomplete="email">
                </div>

                <button type="submit" class="btn btn-primary">Enviar enlace</button>
            </form>

            <div style="margin-top: 15px; text-align: center;">
                <a href="login.php">Volver al login</a>
            </div>
        </div>
    </div>
</body>
</html>
