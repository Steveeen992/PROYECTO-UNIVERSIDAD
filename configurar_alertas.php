<?php
require_once 'config.php';
requireLogin();
requireAdmin();

// Obtener configuración actual del usuario
$configuracion = $db->fetch(
    "SELECT * FROM configuracion_alertas WHERE usuario_id = ?", 
    [$_SESSION['user_id']]
);

// Si no existe configuración, crear una por defecto
if (!$configuracion) {
    try {
        $db->query(
            "INSERT INTO configuracion_alertas (usuario_id) VALUES (?)",
            [$_SESSION['user_id']]
        );
        $configuracion = [
            'alerta_stock_bajo' => true,
            'alerta_prestamos_vencidos' => true,
            'alerta_prestamos_por_vencer' => true,
            'alerta_mantenimiento' => true,
            'email_notificaciones' => true,
            'dashboard_notificaciones' => true,
            'stock_minimo' => 5,
            'dias_vencimiento' => 3
        ];
    } catch (Exception $e) {
        showMessage('Error al crear configuración: ' . $e->getMessage(), 'error');
        redirect('alertas.php');
    }
}

// Procesar formulario de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alerta_stock_bajo = isset($_POST['alerta_stock_bajo']) ? 1 : 0;
    $alerta_prestamos_vencidos = isset($_POST['alerta_prestamos_vencidos']) ? 1 : 0;
    $alerta_prestamos_por_vencer = isset($_POST['alerta_prestamos_por_vencer']) ? 1 : 0;
    $alerta_mantenimiento = isset($_POST['alerta_mantenimiento']) ? 1 : 0;
    $email_notificaciones = isset($_POST['email_notificaciones']) ? 1 : 0;
    $dashboard_notificaciones = isset($_POST['dashboard_notificaciones']) ? 1 : 0;
    $stock_minimo = (int)$_POST['stock_minimo'];
    $dias_vencimiento = (int)$_POST['dias_vencimiento'];
    
    // Validaciones
    if ($stock_minimo < 1) {
        showMessage('El stock mínimo debe ser al menos 1', 'error');
    } elseif ($dias_vencimiento < 1) {
        showMessage('Los días de vencimiento deben ser al menos 1', 'error');
    } else {
        try {
            $db->query(
                "UPDATE configuracion_alertas SET 
                 alerta_stock_bajo = ?, alerta_prestamos_vencidos = ?, alerta_prestamos_por_vencer = ?,
                 alerta_mantenimiento = ?, email_notificaciones = ?, dashboard_notificaciones = ?,
                 stock_minimo = ?, dias_vencimiento = ?
                 WHERE usuario_id = ?",
                [
                    $alerta_stock_bajo, $alerta_prestamos_vencidos, $alerta_prestamos_por_vencer,
                    $alerta_mantenimiento, $email_notificaciones, $dashboard_notificaciones,
                    $stock_minimo, $dias_vencimiento, $_SESSION['user_id']
                ]
            );
            
            showMessage('Configuración de alertas actualizada exitosamente', 'success');
            redirect('configurar_alertas.php');
        } catch (Exception $e) {
            showMessage('Error al actualizar configuración: ' . $e->getMessage(), 'error');
        }
    }
}

// Obtener configuración actualizada
$configuracion = $db->fetch(
    "SELECT * FROM configuracion_alertas WHERE usuario_id = ?", 
    [$_SESSION['user_id']]
);

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Alertas - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .config-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .config-section h3 {
            margin-top: 0;
            color: var(--text-primary);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .config-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: var(--bg-light);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .config-item label {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .config-item .description {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 5px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .number-input {
            width: 80px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            text-align: center;
        }
        
        .preview-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-top: 15px;
        }
        
        .preview-alert h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        
        .preview-alert p {
            margin: 5px 0;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .config-grid {
                grid-template-columns: 1fr;
            }
            
            .config-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
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
            <a href="alertas.php">Alertas</a>
            <div class="dropdown">
                <a href="#" class="dropdown-toggle"><?php echo $_SESSION['user_name']; ?></a>
                <div class="dropdown-menu">
                    <a href="perfil.php">Perfil</a>
                    <a href="configurar_email.php">Configurar Email</a>
                    <a href="cerrarSesion.php">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Configurar Alertas</h1>
            <div class="header-actions">
                <a href="alertas.php" class="btn btn-secondary">Ver Alertas</a>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Configuración de tipos de alertas -->
            <div class="config-section">
                <h3>Tipos de Alertas</h3>
                <p>Selecciona qué tipos de alertas quieres recibir:</p>
                
                <div class="config-grid">
                    <div class="config-item">
                        <div>
                            <label for="alerta_stock_bajo">Stock Bajo</label>
                            <div class="description">Alertas cuando el stock esté por debajo del mínimo</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="alerta_stock_bajo" id="alerta_stock_bajo" 
                                   <?php echo $configuracion['alerta_stock_bajo'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="config-item">
                        <div>
                            <label for="alerta_prestamos_vencidos">Préstamos Vencidos</label>
                            <div class="description">Alertas de préstamos que ya vencieron</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="alerta_prestamos_vencidos" id="alerta_prestamos_vencidos" 
                                   <?php echo $configuracion['alerta_prestamos_vencidos'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="config-item">
                        <div>
                            <label for="alerta_prestamos_por_vencer">Préstamos por Vencer</label>
                            <div class="description">Alertas de préstamos que vencen pronto</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="alerta_prestamos_por_vencer" id="alerta_prestamos_por_vencer" 
                                   <?php echo $configuracion['alerta_prestamos_por_vencer'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="config-item">
                        <div>
                            <label for="alerta_mantenimiento">Mantenimiento</label>
                            <div class="description">Alertas de mantenimientos pendientes</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="alerta_mantenimiento" id="alerta_mantenimiento" 
                                   <?php echo $configuracion['alerta_mantenimiento'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configuración de canales de notificación -->
            <div class="config-section">
                <h3>Canales de Notificación</h3>
                <p>Elige cómo quieres recibir las notificaciones:</p>
                
                <div class="config-grid">
                    <div class="config-item">
                        <div>
                            <label for="dashboard_notificaciones">Dashboard</label>
                            <div class="description">Mostrar alertas en el panel principal</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="dashboard_notificaciones" id="dashboard_notificaciones" 
                                   <?php echo $configuracion['dashboard_notificaciones'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="config-item">
                        <div>
                            <label for="email_notificaciones">Email</label>
                            <div class="description">Enviar notificaciones por correo electrónico</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="email_notificaciones" id="email_notificaciones" 
                                   <?php echo $configuracion['email_notificaciones'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configuración de umbrales -->
            <div class="config-section">
                <h3>Umbrales de Alerta</h3>
                <p>Configura los valores que activarán las alertas:</p>
                
                <div class="config-grid">
                    <div class="config-item">
                        <div>
                            <label for="stock_minimo">Stock Mínimo</label>
                            <div class="description">Cantidad mínima antes de alertar stock bajo</div>
                        </div>
                        <input type="number" name="stock_minimo" id="stock_minimo" 
                               class="number-input" min="1" max="100" 
                               value="<?php echo $configuracion['stock_minimo']; ?>">
                    </div>
                    
                    <div class="config-item">
                        <div>
                            <label for="dias_vencimiento">Días antes de Vencimiento</label>
                            <div class="description">Días antes de vencer para alertar préstamos</div>
                        </div>
                        <input type="number" name="dias_vencimiento" id="dias_vencimiento" 
                               class="number-input" min="1" max="30" 
                               value="<?php echo $configuracion['dias_vencimiento']; ?>">
                    </div>
                </div>
            </div>

            <!-- Vista previa de alertas -->
            <div class="config-section">
                <h3>Vista Previa</h3>
                <p>Así se verán las alertas con tu configuración actual:</p>
                
                <div class="preview-alert">
                    <h4>Alerta de Stock Bajo</h4>
                    <p><strong>Producto:</strong> Laptop Dell XPS 13</p>
                    <p><strong>Cantidad actual:</strong> <?php echo $configuracion['stock_minimo']; ?> unidades</p>
                    <p><strong>Ubicación:</strong> Almacén Principal</p>
                    <p><strong>Acción recomendada:</strong> Reabastecer inventario</p>
                </div>
                
                <div class="preview-alert">
                    <h4>Alerta de Préstamo por Vencer</h4>
                    <p><strong>Elemento:</strong> Proyector Epson</p>
                    <p><strong>Prestatario:</strong> Juan Pérez</p>
                    <p><strong>Vence en:</strong> <?php echo $configuracion['dias_vencimiento']; ?> días</p>
                    <p><strong>Acción recomendada:</strong> Contactar al prestatario</p>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="actions-bar">
                <button type="submit" class="btn btn-primary">💾 Guardar Configuración</button>
                <a href="alertas.php" class="btn btn-secondary">Ver Alertas</a>
                <a href="dashboard.php" class="btn btn-info">Ir al Dashboard</a>
            </div>
        </form>
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

        // Actualizar vista previa en tiempo real
        document.getElementById('stock_minimo').addEventListener('input', function() {
            const previews = document.querySelectorAll('.preview-alert p');
            if (previews.length > 0) {
                previews[1].innerHTML = '<strong>Cantidad actual:</strong> ' + this.value + ' unidades';
            }
        });

        document.getElementById('dias_vencimiento').addEventListener('input', function() {
            const previews = document.querySelectorAll('.preview-alert p');
            if (previews.length > 3) {
                previews[7].innerHTML = '<strong>Vence en:</strong> ' + this.value + ' días';
            }
        });
    </script>
</body>
</html> 