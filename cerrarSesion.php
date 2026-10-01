<?php
/**
 * cerrarSesion.php
 * Script para cerrar la sesión del usuario de forma segura
 */

// Iniciar la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si hay una sesión activa antes de cerrarla
$user_name = $_SESSION['user_name'] ?? null;
$was_logged = isset($_SESSION['user_id']);

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Regenerar ID de sesión por seguridad
session_start();
session_regenerate_id(true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión cerrada - Sistema de Inventario</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --text-dark: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --bg-page: #f8fafc;
            --white: #ffffff;
            --success: #059669;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-page);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-dark);
        }

        .logout-container {
            background: var(--white);
            padding: 48px 40px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 420px;
            width: 100%;
            border: 1px solid var(--border);
        }

        .logout-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 24px;
            background: #ecfdf5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-icon svg {
            width: 28px;
            height: 28px;
            stroke: var(--success);
        }

        .logout-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .logout-message {
            color: var(--text-muted);
            font-size: 0.9375rem;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .user-goodbye {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 28px;
        }
        .user-goodbye strong { color: var(--text-dark); font-weight: 600; }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9375rem;
            transition: background 0.2s, color 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .redirect-info {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 0.8125rem;
        }
        .redirect-info .countdown { font-weight: 600; color: var(--text-dark); }

        .loading-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #e5e7eb;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-left: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            .logout-container { padding: 32px 24px; }
            .logout-title { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="logout-title">Sesión cerrada correctamente</h1>

        <p class="logout-message">
            Has cerrado sesión de forma segura. Para volver a acceder al sistema, inicia sesión de nuevo.
        </p>

        <?php if ($was_logged && $user_name): ?>
        <div class="user-goodbye">
            <p>Usuario: <strong><?php echo htmlspecialchars($user_name); ?></strong></p>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="login.php" class="btn btn-primary">Iniciar sesión</a>
            <a href="login.php" class="btn btn-secondary">Ir a la página de acceso</a>
        </div>

        <div class="redirect-info">
            Redirección automática en <span class="countdown" id="countdown">10</span> s
            <span class="loading-spinner"></span>
        </div>
    </div>

    <script>
        // Redirección automática con contador
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'login.php';
            }
        }, 1000);

        // Limpiar datos de formularios autocompletos (seguridad adicional)
        if (window.history && window.history.pushState) {
            window.history.pushState('', '', window.location.pathname);
            window.onpopstate = function() {
                window.location.href = 'login.php';
            };
        }

        // Prevenir navegación hacia atrás
        window.addEventListener('beforeunload', function() {
            // Limpiar cualquier dato sensible del localStorage si existe
            if (typeof(Storage) !== "undefined") {
                localStorage.clear();
                sessionStorage.clear();
            }
        });

        // Mensaje de confirmación si intentan navegar hacia atrás
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>