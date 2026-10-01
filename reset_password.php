<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$message = '';
$messageType = '';
$isValidToken = false;
$user = null;
$resetRow = null;

if ($token !== '' && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $user = $db->fetch("SELECT id, email FROM usuarios WHERE email = ? LIMIT 1", [$email]);
    if ($user) {
        $candidates = $db->fetchAll(
            "SELECT id, token_hash, expires_at FROM password_resets WHERE usuario_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 10",
            [$user['id']]
        );

        foreach ($candidates as $candidate) {
            if (strtotime($candidate['expires_at']) < time()) {
                continue;
            }
            if (password_verify($token, $candidate['token_hash'])) {
                $isValidToken = true;
                $resetRow = $candidate;
                break;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$isValidToken || !$user || !$resetRow) {
        $message = 'El enlace de recuperación es inválido o ha expirado.';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'La contraseña debe tener al menos 6 caracteres.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Las contraseñas no coinciden.';
        $messageType = 'error';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $db->query("UPDATE usuarios SET password = ? WHERE id = ?", [$passwordHash, $user['id']]);
        $db->query("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [$resetRow['id']]);
        $db->query("UPDATE password_resets SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL", [$user['id']]);

        showMessage('Contraseña actualizada correctamente. Inicia sesión con tu nueva clave.', 'success');
        redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-form">
            <h1>Restablecer contraseña</h1>
            <p>Crea una nueva contraseña para tu cuenta.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!$isValidToken): ?>
                <div class="alert alert-error">El enlace de recuperación es inválido o ha expirado.</div>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="forgot_password.php">Solicitar nuevo enlace</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label for="password">Nueva contraseña</label>
                        <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
                </form>
            <?php endif; ?>

            <div style="margin-top: 15px; text-align: center;">
                <a href="login.php">Volver al login</a>
            </div>
        </div>
    </div>
</body>
</html>
