<?php
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Marcar alerta como leída
if ($action === 'marcar_leida' && $id) {
    if (marcarAlertaComoLeida($db, $id)) {
        showMessage('Alerta marcada como leída', 'success');
    } else {
        showMessage('Error al marcar la alerta', 'error');
    }
    redirect('alertas.php');
}

// Marcar todas como leídas
if ($action === 'marcar_todas_leidas') {
    try {
        $db->query("UPDATE alertas SET leida = 1, fecha_leida = NOW() WHERE leida = 0");
        showMessage('Todas las alertas marcadas como leídas', 'success');
    } catch (Exception $e) {
        showMessage('Error al marcar las alertas', 'error');
    }
    redirect('alertas.php');
}

// Eliminar alerta
if ($action === 'eliminar' && $id) {
    try {
        $db->query("DELETE FROM alertas WHERE id = ?", [$id]);
        showMessage('Alerta eliminada exitosamente', 'success');
    } catch (Exception $e) {
        showMessage('Error al eliminar la alerta', 'error');
    }
    redirect('alertas.php');
}

// Obtener filtros
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_leida = $_GET['leida'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_tipo)) {
    $where_conditions[] = "a.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_leida !== '') {
    $where_conditions[] = "a.leida = ?";
    $params[] = $filtro_leida;
}

if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "a.fecha_creacion >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "a.fecha_creacion <= ?";
    $params[] = $filtro_fecha_hasta;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Obtener alertas
$alertas = $db->fetchAll(
    "SELECT a.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo, u.nombre as usuario_nombre
     FROM alertas a
     LEFT JOIN inventario i ON a.elemento_id = i.id
     LEFT JOIN usuarios u ON a.usuario_registro = u.id
     $where_clause
     ORDER BY a.fecha_creacion DESC",
    $params
);

// Obtener estadísticas
$total_alertas = count($alertas);
$alertas_no_leidas = count(array_filter($alertas, fn($a) => !$a['leida']));
$alertas_stock_bajo = count(array_filter($alertas, fn($a) => $a['tipo'] === 'stock_bajo'));
$alertas_prestamos_vencidos = count(array_filter($alertas, fn($a) => $a['tipo'] === 'prestamo_vencido'));

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .alertas-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .alerta-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-color);
            margin-bottom: 15px;
            transition: var(--transition);
        }
        
        .alerta-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .alerta-card.no-leida {
            border-left-color: var(--danger-color);
            background: #fff5f5;
        }
        
        .alerta-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .alerta-tipo {
            display: inline-block;
            padding: 4px 8px;
            border-radius: var(--border-radius);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .alerta-tipo.stock_bajo { background: #fef3c7; color: #92400e; }
        .alerta-tipo.prestamo_vencido { background: #fee2e2; color: #991b1b; }
        .alerta-tipo.prestamo_por_vencer { background: #fef3c7; color: #92400e; }
        .alerta-tipo.mantenimiento { background: #dbeafe; color: #1e40af; }
        .alerta-tipo.general { background: #e0e7ff; color: #3730a3; }
        
        .alerta-fecha {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .alerta-descripcion {
            margin: 10px 0;
            line-height: 1.5;
        }
        
        .alerta-acciones {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .filtros-alertas {
            background: var(--bg-light);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .estado-alerta {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .estado-alerta.leida { background: var(--success-color); }
        .estado-alerta.no-leida { background: var(--danger-color); }
        
        @media (max-width: 768px) {
            .alertas-stats {
                grid-template-columns: 1fr;
            }
            
            .filtros-grid {
                grid-template-columns: 1fr;
            }
            
            .alerta-acciones {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php $nav_active = 'alertas'; include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Sistema de Alertas</h1>
            <div class="header-actions">
                <a href="configurar_alertas.php" class="btn btn-secondary">Configurar</a>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas de alertas -->
        <div class="alertas-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_alertas; ?></div>
                <div class="stat-label">Total Alertas</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number"><?php echo $alertas_no_leidas; ?></div>
                <div class="stat-label">No Leídas</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $alertas_stock_bajo; ?></div>
                <div class="stat-label">Stock Bajo</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number"><?php echo $alertas_prestamos_vencidos; ?></div>
                <div class="stat-label">Préstamos Vencidos</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-alertas">
            <h3>Filtros</h3>
            <form method="GET" class="filtros-grid">
                <div>
                    <label for="tipo">Tipo de Alerta:</label>
                    <select name="tipo" id="tipo">
                        <option value="">Todos los tipos</option>
                        <option value="stock_bajo" <?php echo $filtro_tipo === 'stock_bajo' ? 'selected' : ''; ?>>Stock Bajo</option>
                        <option value="prestamo_vencido" <?php echo $filtro_tipo === 'prestamo_vencido' ? 'selected' : ''; ?>>Préstamo Vencido</option>
                        <option value="prestamo_por_vencer" <?php echo $filtro_tipo === 'prestamo_por_vencer' ? 'selected' : ''; ?>>Préstamo por Vencer</option>
                        <option value="mantenimiento" <?php echo $filtro_tipo === 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                        <option value="general" <?php echo $filtro_tipo === 'general' ? 'selected' : ''; ?>>General</option>
                    </select>
                </div>
                <div>
                    <label for="leida">Estado:</label>
                    <select name="leida" id="leida">
                        <option value="">Todos</option>
                        <option value="0" <?php echo $filtro_leida === '0' ? 'selected' : ''; ?>>No Leídas</option>
                        <option value="1" <?php echo $filtro_leida === '1' ? 'selected' : ''; ?>>Leídas</option>
                    </select>
                </div>
                <div>
                    <label for="fecha_desde">Desde:</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo $filtro_fecha_desde; ?>">
                </div>
                <div>
                    <label for="fecha_hasta">Hasta:</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo $filtro_fecha_hasta; ?>">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="alertas.php" class="btn btn-secondary">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Acciones masivas -->
        <div class="actions-bar">
            <a href="alertas.php?action=marcar_todas_leidas" class="btn btn-success" onclick="return confirm('¿Marcar todas las alertas como leídas?')">
                Marcar Todas como Leídas
            </a>
            <a href="generar_reporte_alertas.php" class="btn btn-info">
                Generar Reporte
            </a>
        </div>

        <!-- Lista de alertas -->
        <div class="content-section">
            <h3>Lista de Alertas</h3>
            
            <?php if (empty($alertas)): ?>
                <div class="empty-state">
                    <p>¡No hay alertas que mostrar!</p>
                    <p>Todas las alertas están bajo control.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alertas as $alerta): ?>
                    <div class="alerta-card <?php echo !$alerta['leida'] ? 'no-leida' : ''; ?>">
                        <div class="alerta-header">
                            <div>
                                <span class="estado-alerta <?php echo $alerta['leida'] ? 'leida' : 'no-leida'; ?>"></span>
                                <span class="alerta-tipo <?php echo $alerta['tipo']; ?>">
                                    <?php 
                                    switch($alerta['tipo']) {
                                        case 'stock_bajo': echo 'Stock Bajo'; break;
                                        case 'prestamo_vencido': echo 'Préstamo Vencido'; break;
                                        case 'prestamo_por_vencer': echo 'Por Vencer'; break;
                                        case 'mantenimiento': echo 'Mantenimiento'; break;
                                        default: echo 'General'; break;
                                    }
                                    ?>
                                </span>
                            </div>
                            <span class="alerta-fecha">
                                <?php echo date('d/m/Y H:i', strtotime($alerta['fecha_creacion'])); ?>
                            </span>
                        </div>
                        
                        <div class="alerta-descripcion">
                            <?php echo htmlspecialchars($alerta['descripcion']); ?>
                        </div>
                        
                        <?php if ($alerta['elemento_nombre']): ?>
                            <div class="alerta-elemento">
                                <strong>Elemento:</strong> 
                                <a href="inventario.php?action=edit&id=<?php echo $alerta['elemento_id']; ?>">
                                    <?php echo htmlspecialchars($alerta['elemento_nombre']); ?> 
                                    (<?php echo htmlspecialchars($alerta['elemento_codigo']); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alerta-acciones">
                            <?php if (!$alerta['leida']): ?>
                                <a href="alertas.php?action=marcar_leida&id=<?php echo $alerta['id']; ?>" 
                                   class="btn btn-success btn-sm">Marcar como Leída</a>
                            <?php endif; ?>
                            
                            <a href="alertas.php?action=eliminar&id=<?php echo $alerta['id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('¿Eliminar esta alerta?')">Eliminar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

        // Auto-refresh cada 30 segundos para alertas nuevas
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html> 