<?php
require_once 'config.php';

// Registro deshabilitado: solo se accede con el usuario administradora (Litoral2026)
if (isLoggedIn()) {
    redirect('dashboard.php');
}
showMessage('El registro de nuevos usuarios está deshabilitado.', 'info');
redirect('login.php');
exit;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize($_POST['nombre']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor complete todos los campos';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        // Verificar si el email ya existe
        $existing_user = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
        
        if ($existing_user) {
            $error = 'El email ya está registrado';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $db->query(
                    "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'editor')",
                    [$nombre, $email, $hashed_password]
                );
                
                $success = 'Usuario registrado exitosamente. Ahora puede iniciar sesión.';
            } catch (Exception $e) {
                $error = 'Error al registrar el usuario';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-form">
            <h1>Registro de Usuario</h1>
            <p>Sistema de Inventario - Infraestructura Física y Tecnológica</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre Completo:</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo $_POST['nombre'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Registrarse</button>
            </form>
            
            <div class="login-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Iniciar Sesión</a></p>
            </div>
        </div>
    </div>
</body>
</html>