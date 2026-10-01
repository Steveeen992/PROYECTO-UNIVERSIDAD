<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$flashMessage = getMessage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        // Inicio de sesión solo con usuario administradora: Litoral2026 / 2026Litoral
        $user = $db->fetch("SELECT * FROM usuarios WHERE email = ? OR nombre = ?", [$usuario, $usuario]);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['rol'];
            if (function_exists('registrarActividadUsuario')) {
                registrarActividadUsuario($db, 'login', 'Inició sesión', 'login.php');
            }
            redirect('dashboard.php');
        } else {
            $error = 'Credenciales incorrectas';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Estilos para el botón de Google */
        .login-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .login-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
        }
        
        .login-divider span {
            background: white;
            padding: 0 15px;
            color: #666;
            font-size: 14px;
        }
        
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .btn-google:hover {
            background: #f8f9fa;
            border-color: #ccc;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .google-icon {
            width: 18px;
            height: 18px;
        }

        .login-links {
            text-align: right;
            margin: -5px 0 15px;
        }

        .login-links a {
            color: #2563eb;
            font-size: 14px;
            text-decoration: none;
            font-weight: 500;
        }

        .login-links a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .btn-google {
                padding: 10px 15px;
                font-size: 14px;
            }
            
            .google-icon {
                width: 16px;
                height: 16px;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-form">
            <h1>Iniciar Sesión</h1>
            <p>Sistema de Inventario - Infraestructura Física y Tecnológica</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                
                <div class="login-links">
                    <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="btn btn-primary">Ingresar</button>
            </form>
        </div>
    </div>
</body>
</html>