<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$message = getMessage();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        showMessage('Por favor ingresa un email válido', 'error');
    } else {
        // Guardar email en sesión
        $_SESSION['user_email'] = $email;
        
        // También actualizar en la base de datos si existe la columna
        try {
            $db->query("UPDATE usuarios SET email = ? WHERE id = ?", [$email, $_SESSION['user_id']]);
        } catch (Exception $e) {
            // Si no existe la columna email, no hacer nada
        }
        
        showMessage('Email configurado exitosamente. Ahora podrás recibir reportes por email.', 'success');
        redirect('configurar_email.php');
    }
}

// Obtener email actual
$currentEmail = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Email - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <h2><?php echo SITE_NAME; ?></h2>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php">Inicio</a>
            <a href="inventario.php">Inventario</a>
            <a href="categorias.php">Categorías</a>
            <a href="ubicaciones.php">Ubicaciones</a>
            <a href="prestamos.php">Prestamos</a>
            <a href="proveedores.php">Proveedores</a>
            <a href="chat_ai.php">Chat IA</a>
            <div class="dropdown">
                <a href="#" class="dropdown-toggle"><?php echo $_SESSION['user_name']; ?></a>
                <div class="dropdown-menu">
                    <a href="perfil.php">Perfil</a>
                    <a href="cerrarSesion.php">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Configurar Email</h1>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="email-config-card">
                <div class="email-config-header">
                    <h2>Configurar Email para Reportes</h2>
                    <p>Configura tu dirección de email para recibir reportes automáticamente desde el Chat IA.</p>
                </div>

                <form method="POST" class="form">
                    <div class="form-group">
                        <label for="email">Dirección de Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($currentEmail); ?>"
                               placeholder="tu-email@ejemplo.com">
                        <small class="form-text">
                            Este email se usará para enviarte reportes cuando los solicites desde el Chat IA.
                        </small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            💾 Guardar Configuración
                        </button>
                        <a href="chat_ai.php" class="btn btn-success">
                            🤖 Ir al Chat IA
                        </a>
                    </div>
                </form>

                <div class="email-info">
                    <h3>¿Cómo funciona?</h3>
                    <div class="info-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Configura tu email</h4>
                                <p>Agrega tu dirección de email arriba</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Ve al Chat IA</h4>
                                <p>Accede al Chat IA desde el menú</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Solicita un reporte</h4>
                                <p>Escribe: "Hazme un reporte del inventario"</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4>Recibe el email</h4>
                                <p>El reporte se enviará automáticamente a tu email</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="email-features">
                    <h3>✨ Características de los Reportes</h3>
                    <div class="features-grid">
                        <div class="feature">
                            <span class="feature-icon"></span>
                            <h4>Reporte Completo</h4>
                            <p>Lista completa de todos los productos en tu inventario</p>
                        </div>
                        <div class="feature">
                            <span class="feature-icon"></span>
                            <h4>Formato Excel</h4>
                            <p>Archivo CSV que puedes abrir en Excel o Google Sheets</p>
                        </div>
                        <div class="feature">
                            <span class="feature-icon"></span>
                            <h4>Enviado por Email</h4>
                            <p>Recibe el reporte directamente en tu bandeja de entrada</p>
                        </div>
                        <div class="feature">
                            <span class="feature-icon"></span>
                            <h4>Comando de Voz</h4>
                            <p>Solo di "Hazme un reporte" en el Chat IA</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funcionalidad del dropdown
        document.querySelector('.dropdown-toggle').addEventListener('click', function(e) {
            e.preventDefault();
            this.parentElement.querySelector('.dropdown-menu').classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    </script>

    <style>
        .email-config-card {
            max-width: 800px;
            margin: 0 auto;
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .email-config-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .email-config-header h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }

        .email-config-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        .form-container {
            padding: 30px;
        }

        .info-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: var(--bg-light);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .step-content h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }

        .step-content p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .email-features {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .feature {
            text-align: center;
            padding: 20px;
            background: var(--bg-light);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .feature-icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
        }

        .feature h4 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }

        .feature p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .email-config-header {
                padding: 20px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .info-steps {
                grid-template-columns: 1fr;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html> 