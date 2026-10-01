<?php
require_once 'config.php';
requireLogin();

// Mismo criterio que en prestamos.php: todos los roles ven todos los préstamos
$effective_user_id = null;

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$estado = $_GET['estado'] ?? '';
$prestatario = $_GET['prestatario'] ?? '';
$elemento = $_GET['elemento'] ?? '';

// Construir consulta con filtros (sin filtrar por usuario: mismo inventario global)
$where_conditions = [];
$params = [];
if ($effective_user_id !== null) {
    $where_conditions[] = "p.usuario_registro = ?";
    $params[] = $_SESSION['user_id'];
}

if (!empty($fecha_inicio)) {
    $where_conditions[] = "p.fecha_prestamo >= ?";
    $params[] = $fecha_inicio;
}

if (!empty($fecha_fin)) {
    $where_conditions[] = "p.fecha_prestamo <= ?";
    $params[] = $fecha_fin;
}

if (!empty($estado)) {
    $where_conditions[] = "p.estado = ?";
    $params[] = $estado;
}

if (!empty($prestatario)) {
    $where_conditions[] = "p.prestatario LIKE ?";
    $params[] = '%' . $prestatario . '%';
}

if (!empty($elemento)) {
    $where_conditions[] = "(i.codigo LIKE ? OR i.nombre LIKE ?)";
    $params[] = '%' . $elemento . '%';
    $params[] = '%' . $elemento . '%';
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Obtener datos del reporte
$prestamos = $db->fetchAll(
    "SELECT p.*, 
            i.nombre as elemento_nombre, 
            i.codigo as elemento_codigo,
            i.categoria_id,
            c.nombre as categoria_nombre,
            u.nombre as ubicacion_nombre,
            us.nombre as usuario_registro_nombre,
            CASE 
                WHEN p.estado = 'activo' AND p.fecha_devolucion_esperada < CURDATE() THEN 'VENCIDO'
                ELSE UPPER(REPLACE(p.estado, '_', ' '))
            END as estado_display,
            DATEDIFF(
                COALESCE(p.fecha_devolucion_real, CURDATE()), 
                p.fecha_prestamo
            ) as dias_prestamo
     FROM prestamos p
     JOIN inventario i ON p.elemento_id = i.id
     LEFT JOIN categorias c ON i.categoria_id = c.id
     LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id
     LEFT JOIN usuarios us ON p.usuario_registro = us.id
     $where_clause
     ORDER BY p.fecha_prestamo DESC",
    $params
);

// Calcular estadísticas del reporte
$total_prestamos = count($prestamos);
$prestamos_activos = count(array_filter($prestamos, fn($p) => $p['estado'] === 'activo'));
$prestamos_vencidos = count(array_filter($prestamos, fn($p) => $p['estado'] === 'activo' && $p['fecha_devolucion_esperada'] < date('Y-m-d')));
$prestamos_devueltos = count(array_filter($prestamos, fn($p) => in_array($p['estado'], ['devuelto_completo', 'devuelto_parcial'])));
$prestamos_perdidos = count(array_filter($prestamos, fn($p) => $p['estado'] === 'perdido'));

// Procesar acción de limpiar todos los préstamos
if (isset($_GET['action']) && $_GET['action'] === 'limpiar_todos') {
    try {
        // Eliminar todos los préstamos del usuario actual
        $eliminados = $db->query("DELETE FROM prestamos WHERE usuario_registro = ?", [$_SESSION['user_id']]);
        showMessage('Todos los préstamos han sido eliminados exitosamente.', 'success');
        redirect('reporte_prestamos.php');
    } catch (Exception $e) {
        showMessage('Error al eliminar los préstamos: ' . $e->getMessage(), 'error');
        redirect('reporte_prestamos.php');
    }
}

// Obtener listas para filtros (todos los préstamos e inventario global)
$prestatarios = $effective_user_id === null
    ? $db->fetchAll("SELECT DISTINCT prestatario FROM prestamos ORDER BY prestatario", [])
    : $db->fetchAll("SELECT DISTINCT prestatario FROM prestamos WHERE usuario_registro = ? ORDER BY prestatario", [$_SESSION['user_id']]);
$elementos_inventario = $effective_user_id === null
    ? $db->fetchAll("SELECT id, codigo, nombre FROM inventario WHERE estado = 'activo' ORDER BY nombre", [])
    : $db->fetchAll("SELECT id, codigo, nombre FROM inventario WHERE estado = 'activo' AND usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);

// Exportación Excel
if (isset($_GET['formato']) && $_GET['formato'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Reporte_Prestamos_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    echo '<table border="1"><tr><th colspan="13">REPORTE DE PRÉSTAMOS</th></tr>';
    echo '<tr><th colspan="13">Fecha: ' . date('d/m/Y H:i:s') . ' | Generado por: ' . htmlspecialchars($_SESSION['user_name']) . '</th></tr><tr><th colspan="13"></th></tr>';
    echo '<tr><th>ID</th><th>Elemento</th><th>Categoría</th><th>Cantidad</th><th>Prestatario</th><th>Contacto</th><th>F. Préstamo</th><th>F. Esperada</th><th>F. Real</th><th>Días</th><th>Estado</th><th>Propósito</th><th>Registrado por</th></tr>';
    foreach ($prestamos as $p) {
        $estado_display = ($p['estado'] === 'activo' && $p['fecha_devolucion_esperada'] < date('Y-m-d')) ? 'VENCIDO' : strtoupper(str_replace('_', ' ', $p['estado']));
        echo '<tr>';
        echo '<td>' . (int)$p['id'] . '</td>';
        echo '<td>' . htmlspecialchars($p['elemento_codigo'] . ' - ' . $p['elemento_nombre']) . '</td>';
        echo '<td>' . htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría') . '</td>';
        echo '<td>' . (int)$p['cantidad_prestada'] . '</td>';
        echo '<td>' . htmlspecialchars($p['prestatario']) . '</td>';
        echo '<td>' . htmlspecialchars($p['contacto_prestatario'] ?? '') . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($p['fecha_prestamo'])) . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])) . '</td>';
        echo '<td>' . ($p['fecha_devolucion_real'] ? date('d/m/Y', strtotime($p['fecha_devolucion_real'])) : '-') . '</td>';
        echo '<td>' . (int)$p['dias_prestamo'] . '</td>';
        echo '<td>' . htmlspecialchars($estado_display) . '</td>';
        echo '<td>' . htmlspecialchars(substr($p['proposito'] ?? '', 0, 80)) . '</td>';
        echo '<td>' . htmlspecialchars($p['usuario_registro_nombre'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '<tr><th colspan="13"></th></tr><tr><th colspan="6">TOTAL REGISTROS: ' . count($prestamos) . '</th><th colspan="7"></th></tr></table>';
    exit;
}

// Exportación PDF (vista para imprimir / Guardar como PDF)
if (isset($_GET['formato']) && $_GET['formato'] === 'pdf') {
    if (ob_get_level()) ob_end_clean();
    ob_start();
    $filtros_texto = [];
    if (!empty($fecha_inicio)) $filtros_texto[] = 'Desde: ' . date('d/m/Y', strtotime($fecha_inicio));
    if (!empty($fecha_fin)) $filtros_texto[] = 'Hasta: ' . date('d/m/Y', strtotime($fecha_fin));
    if (!empty($estado)) $filtros_texto[] = 'Estado: ' . ucfirst(str_replace('_', ' ', $estado));
    if (!empty($prestatario)) $filtros_texto[] = 'Prestatario: ' . htmlspecialchars($prestatario);
    if (!empty($elemento)) $filtros_texto[] = 'Elemento: ' . htmlspecialchars($elemento);
    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de Préstamos - ' . date('d/m/Y') . '</title>';
    $html .= '<style>@page{ margin:1.5cm; size:A4 landscape; } body{ font-family:Arial,sans-serif; font-size:9pt; margin:20px; }';
    $html .= 'h1{ text-align:center; color:#2563eb; font-size:18pt; } .header-info{ text-align:center; margin-bottom:15px; font-size:8pt; color:#666; }';
    $html .= 'table{ width:100%; border-collapse:collapse; font-size:8pt; } th{ background:#2563eb; color:white; padding:6px; border:1px solid #333; }';
    $html .= 'td{ padding:5px; border:1px solid #ddd; } tr:nth-child(even){ background:#f9f9f9; }';
    $html .= '.print-hide{ display:none; } @media print{ .print-hide{ display:none !important; } }</style></head><body>';
    $html .= (function_exists('getLogoReportesHtml') ? getLogoReportesHtml() : '');
    $html .= '<h1>REPORTE DE PRÉSTAMOS</h1><div class="header-info"><p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    $html .= '<p><strong>Generado por:</strong> ' . htmlspecialchars($_SESSION['user_name']) . '</p>';
    if (!empty($filtros_texto)) $html .= '<p><strong>Filtros:</strong> ' . implode(' | ', $filtros_texto) . '</p>';
    $html .= '</div><table><thead><tr><th>ID</th><th>Elemento</th><th>Categoría</th><th>Cant.</th><th>Prestatario</th><th>Contacto</th><th>F. Préstamo</th><th>F. Esperada</th><th>F. Real</th><th>Días</th><th>Estado</th><th>Propósito</th><th>Registrado por</th></tr></thead><tbody>';
    foreach ($prestamos as $p) {
        $estado_display = ($p['estado'] === 'activo' && $p['fecha_devolucion_esperada'] < date('Y-m-d')) ? 'VENCIDO' : strtoupper(str_replace('_', ' ', $p['estado']));
        $html .= '<tr><td>' . (int)$p['id'] . '</td><td>' . htmlspecialchars($p['elemento_codigo'] . ' - ' . $p['elemento_nombre']) . '</td><td>' . htmlspecialchars($p['categoria_nombre'] ?? '-') . '</td><td>' . (int)$p['cantidad_prestada'] . '</td>';
        $html .= '<td>' . htmlspecialchars($p['prestatario']) . '</td><td>' . htmlspecialchars($p['contacto_prestatario'] ?? '') . '</td><td>' . date('d/m/Y', strtotime($p['fecha_prestamo'])) . '</td><td>' . date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])) . '</td>';
        $html .= '<td>' . ($p['fecha_devolucion_real'] ? date('d/m/Y', strtotime($p['fecha_devolucion_real'])) : '-') . '</td><td>' . (int)$p['dias_prestamo'] . '</td><td>' . htmlspecialchars($estado_display) . '</td>';
        $html .= '<td>' . htmlspecialchars(substr($p['proposito'] ?? '', 0, 50)) . '</td><td>' . htmlspecialchars($p['usuario_registro_nombre'] ?? '') . '</td></tr>';
    }
    $html .= '</tbody></table><div style="margin-top:20px;text-align:center;"><strong>Total registros: ' . count($prestamos) . '</strong></div>';
    $html .= '<div class="print-hide" style="text-align:center;margin-top:30px;"><p>Para guardar como PDF: Ctrl+P → Guardar como PDF</p><button onclick="window.print()">Imprimir / Guardar PDF</button></div>';
    $html .= '<script>setTimeout(function(){ window.print(); }, 500);</script></body></html>';
    ob_end_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .report-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            border-bottom: 2px solid #eee;
        }
        
        .report-filters {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }
        
        .action-buttons {
            margin-bottom: 1.5rem;
            text-align: right;
        }
        
        .action-buttons .btn {
            margin-left: 0.5rem;
        }
        
        .report-table {
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-activo { background: #d4edda; color: #155724; }
        .status-vencido { background: #f8d7da; color: #721c24; }
        .status-devuelto-completo { background: #d1ecf1; color: #0c5460; }
        .status-devuelto-parcial { background: #fff3cd; color: #856404; }
        .status-perdido { background: #f5c6cb; color: #721c24; }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .container {
                max-width: none;
                margin: 0;
                padding: 0;
            }
            
            .report-table {
                font-size: 0.8rem;
            }
            
            .table th,
            .table td {
                padding: 0.3rem;
                border: 1px solid #000;
            }
            
            .table {
                border-collapse: collapse;
            }
        }
        
        .print-info {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <nav class="navbar no-print">
        <div class="nav-brand">
            <h2><?php echo SITE_NAME; ?></h2>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php">Inicio</a>
            <a href="inventario.php">Inventario</a>
            <a href="categorias.php">Categorías</a>
            <a href="ubicaciones.php">Ubicaciones</a>
            <a href="prestamos.php">Préstamos</a>
            <a href="proveedores.php">Proveedores</a>
            <a href="reporte_prestamos.php" class="active">Reportes</a>
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
        <div class="report-header">
            <h1>Reporte de Préstamos</h1>
            <p><strong><?php echo SITE_NAME; ?></strong></p>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?> por <?php echo $_SESSION['user_name']; ?></p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?> no-print">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="report-filters no-print">
            <h3>Filtros del Reporte</h3>
            <form method="GET" class="form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo $estado == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="devuelto_completo" <?php echo $estado == 'devuelto_completo' ? 'selected' : ''; ?>>Devuelto Completo</option>
                            <option value="devuelto_parcial" <?php echo $estado == 'devuelto_parcial' ? 'selected' : ''; ?>>Devuelto Parcial</option>
                            <option value="perdido" <?php echo $estado == 'perdido' ? 'selected' : ''; ?>>Perdido</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="prestatario">Prestatario</label>
                        <input type="text" id="prestatario" name="prestatario" value="<?php echo htmlspecialchars($prestatario); ?>" placeholder="Nombre del prestatario">
                    </div>
                    
                    <div class="form-group">
                        <label for="elemento">Elemento</label>
                        <input type="text" id="elemento" name="elemento" value="<?php echo htmlspecialchars($elemento); ?>" placeholder="Código o nombre del elemento">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    <a href="reporte_prestamos.php" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
            </form>
        </div>

        <!-- Estadísticas -->
        <div class="stats-summary">
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_prestamos; ?></div>
                <div class="stat-label">Total Préstamos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #28a745;"><?php echo $prestamos_activos; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #dc3545;"><?php echo $prestamos_vencidos; ?></div>
                <div class="stat-label">Vencidos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #17a2b8;"><?php echo $prestamos_devueltos; ?></div>
                <div class="stat-label">Devueltos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #6c757d;"><?php echo $prestamos_perdidos; ?></div>
                <div class="stat-label">Perdidos</div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="action-buttons no-print">
            <?php 
            $get_sin_action = array_diff_key($_GET, ['action' => 1]); 
            $get_export = array_merge($get_sin_action, ['formato' => 'excel']); 
            $get_pdf = array_merge($get_sin_action, ['formato' => 'pdf']); 
            ?>
            <a href="reporte_prestamos.php?<?php echo http_build_query($get_export); ?>" class="btn btn-success">
                Exportar Excel
            </a>
            <a href="reporte_prestamos.php?<?php echo http_build_query($get_pdf); ?>" class="btn btn-danger">
                Exportar PDF
            </a>
            <button onclick="window.print()" class="btn btn-info">
                Imprimir Reporte
            </button>
            <a href="reporte_prestamos.php?action=limpiar_todos" 
               class="btn btn-danger" 
               onclick="return confirm('¿Estás seguro de que deseas eliminar TODOS los préstamos? Esta acción no se puede deshacer.')">
                🗑️ Limpiar Todos los Préstamos
            </a>
            <a href="prestamos.php" class="btn btn-secondary">
                ← Volver a Préstamos
            </a>
        </div>

        <!-- Tabla de datos -->
        <div class="table-container">
            <table class="table report-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Elemento</th>
                        <th>Categoría</th>
                        <th>Cantidad</th>
                        <th>Prestatario</th>
                        <th>Contacto</th>
                        <th>F. Préstamo</th>
                        <th>F. Esperada</th>
                        <th>F. Real</th>
                        <th>Días</th>
                        <th>Estado</th>
                        <th>Propósito</th>
                        <th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestamos)): ?>
                        <tr>
                            <td colspan="13" class="text-center">No se encontraron préstamos con los filtros aplicados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prestamos as $prestamo): ?>
                            <tr>
                                <td><?php echo $prestamo['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prestamo['elemento_codigo']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($prestamo['elemento_nombre']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($prestamo['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                <td><?php echo $prestamo['cantidad_prestada']; ?></td>
                                <td><?php echo htmlspecialchars($prestamo['prestatario']); ?></td>
                                <td><?php echo htmlspecialchars($prestamo['contacto_prestatario']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])); ?></td>
                                <td><?php echo $prestamo['fecha_devolucion_real'] ? date('d/m/Y', strtotime($prestamo['fecha_devolucion_real'])) : '-'; ?></td>
                                <td><?php echo $prestamo['dias_prestamo']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $prestamo['estado_display'])); ?>">
                                        <?php echo $prestamo['estado_display']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars(substr($prestamo['proposito'], 0, 50)); ?><?php echo strlen($prestamo['proposito']) > 50 ? '...' : ''; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($prestamo['usuario_registro_nombre']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="print-info">
            <strong>Información del Reporte:</strong><br>
            • Total de registros: <?php echo $total_prestamos; ?><br>
            • Filtros aplicados: 
            <?php if (empty($_GET) || (empty($fecha_inicio) && empty($fecha_fin) && empty($estado) && empty($prestatario) && empty($elemento))): ?>
                Ninguno (Todos los préstamos)
            <?php else: ?>
                <?php
                $filtros_activos = [];
                if (!empty($fecha_inicio)) $filtros_activos[] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio));
                if (!empty($fecha_fin)) $filtros_activos[] = "Hasta: " . date('d/m/Y', strtotime($fecha_fin));
                if (!empty($estado)) $filtros_activos[] = "Estado: " . ucfirst($estado);
                if (!empty($prestatario)) $filtros_activos[] = "Prestatario: " . $prestatario;
                if (!empty($elemento)) $filtros_activos[] = "Elemento: " . $elemento;
                echo implode(', ', $filtros_activos);
                ?>
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

        // Mejorar la experiencia de impresión
        window.addEventListener('beforeprint', function() {
            document.title = 'Reporte_Prestamos_' + new Date().toISOString().split('T')[0];
        });
    </script>
</body>
</html>