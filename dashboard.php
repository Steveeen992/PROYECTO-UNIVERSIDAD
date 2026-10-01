<?php
require_once 'config.php';
requireLogin();

// Obtener estadísticas del usuario actual
$stats = function_exists('getUserStats') ? getUserStats($db, $_SESSION['user_id']) : [
    'total_elementos' => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['total'],
    'activos' => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = 'activo'", [$_SESSION['user_id']])['total'],
    'en_reparacion' => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = 'en_reparacion'", [$_SESSION['user_id']])['total'],
    'dados_baja' => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = 'dado_de_baja'", [$_SESSION['user_id']])['total']
];

// Obtener alertas para el dashboard
$alertas_dashboard = getAlertasNoLeidas($db, 5);
$total_alertas = getTotalAlertas($db);
$alertas_stock_bajo = count(getAlertasStockBajo($db));
$prestamos_vencidos = count(getAlertasPrestamosVencidos($db));
$prestamos_por_vencer = count(getAlertasPrestamosPorVencer($db));

// Obtener elementos recientes del usuario actual
$elementos_recientes = function_exists('getUserInventario') ? 
    array_slice(getUserInventario($db, $_SESSION['user_id']), 0, 5) :
    $db->fetchAll(
        "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion 
         FROM inventario i 
         LEFT JOIN categorias c ON i.categoria_id = c.id 
         LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id 
         WHERE i.usuario_registro = ?
         ORDER BY i.fecha_registro DESC LIMIT 5",
        [$_SESSION['user_id']]
    );

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .login-method {
            color: #666;
            font-size: 0.9em;
            font-style: italic;
        }

        /* Estilos para la tarjeta de IA */
        .ai-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 30px;
            margin: 20px 0;
            box-shadow: var(--shadow-md);
        }

        .ai-card-header h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }

        .ai-card-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        .ai-card-content {
            margin-top: 20px;
        }

        .ai-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .ai-feature {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
        }

        .ai-icon {
            font-size: 1.2rem;
        }

        .btn-large {
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* Estilos para alertas en dashboard */
        .alerts-section {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 25px;
            margin: 20px 0;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--danger-color);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h3 {
            margin: 0;
            color: var(--danger-color);
        }
        
        .alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .alert-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .alert-card.warning {
            background: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .alert-card.danger {
            background: #fee2e2;
            border-color: #fecaca;
        }
        
        .alert-icon {
            font-size: 2rem;
        }
        
        .alert-content h4 {
            margin: 0 0 5px 0;
            font-size: 1rem;
        }
        
        .alert-content p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .recent-alerts {
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }
        
        .recent-alerts h4 {
            margin: 0 0 15px 0;
            font-size: 1rem;
        }
        
        .alert-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .alert-item:last-child {
            border-bottom: none;
        }
        
        .alert-type {
            font-size: 1.2rem;
        }
        
        .alert-text {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .alert-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .ai-features {
                grid-template-columns: 1fr;
            }
            
            .ai-card {
                padding: 20px;
            }
            
            .alerts-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php $nav_active = 'dashboard'; include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Panel de Control</h1>
            <p>Bienvenido, <?php echo $_SESSION['user_name']; ?>
                <?php if (isset($_SESSION['login_method']) && $_SESSION['login_method'] === 'google'): ?>
                    <span class="login-method">(Inicio de sesión con Google)</span>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_elementos']; ?></div>
                <div class="stat-label">Total Elementos</div>
            </div>
            <div class="stat-card active">
                <div class="stat-number"><?php echo $stats['activos']; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $stats['en_reparacion']; ?></div>
                <div class="stat-label">En Reparación</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number"><?php echo $stats['dados_baja']; ?></div>
                <div class="stat-label">Dados de Baja</div>
            </div>
        </div>

        <!-- Sección de Alertas -->
        <?php if ($total_alertas > 0): ?>
            <div class="alerts-section">
                <div class="section-header">
                    <h3>Alertas Activas (<?php echo $total_alertas; ?>)</h3>
                    <a href="alertas.php" class="btn btn-danger btn-sm">Ver Todas</a>
                </div>
                
                <div class="alerts-grid">
                    <?php if ($alertas_stock_bajo > 0): ?>
                        <div class="alert-card warning">
                            <div class="alert-icon"></div>
                            <div class="alert-content">
                                <h4>Stock Bajo</h4>
                                <p><?php echo $alertas_stock_bajo; ?> productos con stock bajo</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($prestamos_vencidos > 0): ?>
                        <div class="alert-card danger">
                            <div class="alert-icon"></div>
                            <div class="alert-content">
                                <h4>Préstamos Vencidos</h4>
                                <p><?php echo $prestamos_vencidos; ?> préstamos vencidos</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($prestamos_por_vencer > 0): ?>
                        <div class="alert-card warning">
                            <div class="alert-icon"></div>
                            <div class="alert-content">
                                <h4>Préstamos por vencer</h4>
                                <p><?php echo $prestamos_por_vencer; ?> préstamos por vencer</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($alertas_dashboard)): ?>
                    <div class="recent-alerts">
                        <h4>Alertas Recientes:</h4>
                        <?php foreach ($alertas_dashboard as $alerta): ?>
                            <div class="alert-item">
                                <span class="alert-type"><?php 
                                    switch($alerta['tipo']) {
                                        case 'stock_bajo': echo 'Stock Bajo'; break;
                                        case 'prestamo_vencido': echo 'Préstamo Vencido'; break;
                                        case 'prestamo_por_vencer': echo 'Por Vencer'; break;
                                        case 'mantenimiento': echo 'Mantenimiento'; break;
                                        default: echo 'General'; break;
                                    }
                                ?></span>
                                <span class="alert-text"><?php echo htmlspecialchars($alerta['descripcion']); ?></span>
                                <span class="alert-time"><?php echo date('H:i', strtotime($alerta['fecha_creacion'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <!-- Aquí estaba el apartado del Asistente IA, eliminado según solicitud -->
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-section">
                <h3>Acciones Rápidas</h3>
                <div class="quick-actions">
                    <?php if (canAddEditProducts()): ?>
                    <a href="inventario.php?action=add" class="btn btn-primary">
                        Agregar Elemento
                    </a>
                    <?php endif; ?>
                    <a href="inventario.php" class="btn btn-secondary">
                        Ver Inventario
                    </a>
                    <?php if (canViewReports()): ?>
                    <a href="reporte_inventario.php" class="btn btn-info">
                        📊 Reporte de Inventario
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>Elementos Recientes</h3>
                <?php if (empty($elementos_recientes)): ?>
                    <p>No hay elementos registrados</p>
                <?php else: ?>
                    <div class="recent-items">
                        <?php foreach ($elementos_recientes as $elemento): ?>
                            <div class="recent-item">
                                <div class="item-info">
                                    <strong><?php echo htmlspecialchars($elemento['nombre']); ?></strong>
                                    <span class="item-code"><?php echo htmlspecialchars($elemento['codigo']); ?></span>
                                    <span class="item-category"><?php echo htmlspecialchars($elemento['categoria'] ?? 'Sin categoría'); ?></span>
                                </div>
                                <div class="item-status">
                                    <span class="status <?php echo $elemento['estado']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $elemento['estado'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Funcionalidad del dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdownMenu = this.parentElement.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        dropdownMenu.classList.toggle('show');
                        if (dropdownMenu.classList.contains('show')) {
                            dropdownMenu.style.display = 'block';
                        } else {
                            dropdownMenu.style.display = 'none';
                        }
                    }
                });
            }

            // Cerrar dropdown al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.remove('show');
                        menu.style.display = 'none';
                    });
                }
            });
        });
    </script>
</body>
</html>