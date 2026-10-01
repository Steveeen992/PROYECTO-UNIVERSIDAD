<?php
require_once 'config.php';
requireLogin();

$effective_user_id = null;

// Estadísticas: global para admin/editor, por usuario para trabajador
if ($effective_user_id === null) {
    $stats = [
        'total_productos' => $db->fetch("SELECT COUNT(*) as count FROM inventario")['count'],
        'total_categorias' => $db->fetch("SELECT COUNT(*) as count FROM categorias")['count'],
        'total_ubicaciones' => $db->fetch("SELECT COUNT(*) as count FROM ubicaciones")['count'],
        'total_proveedores' => $db->fetch("SELECT COUNT(*) as count FROM proveedores")['count'],
        'valor_total' => $db->fetch("SELECT COALESCE(SUM(valor_compra * cantidad_disponible), 0) as total FROM inventario")['total'],
        'productos_stock_bajo' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE cantidad_disponible <= 5")['count'],
        'prestamos_activos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo'")['count'],
        'prestamos_vencidos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND fecha_devolucion_esperada < CURDATE()")['count']
    ];
    $productos_por_categoria = $db->fetchAll(
        "SELECT c.nombre as categoria, COUNT(i.id) as cantidad FROM categorias c LEFT JOIN inventario i ON c.id = i.categoria_id GROUP BY c.id ORDER BY cantidad DESC"
    );
    $productos_por_ubicacion = $db->fetchAll(
        "SELECT u.nombre as ubicacion, COUNT(i.id) as cantidad FROM ubicaciones u LEFT JOIN inventario i ON u.id = i.ubicacion_id GROUP BY u.id ORDER BY cantidad DESC"
    );
    $prestamos_por_mes = $db->fetchAll(
        "SELECT DATE_FORMAT(fecha_prestamo, '%Y-%m') as mes, COUNT(*) as cantidad FROM prestamos GROUP BY mes ORDER BY mes DESC LIMIT 12"
    );
    $productos_mas_prestados = $db->fetchAll(
        "SELECT i.nombre as producto, COUNT(p.id) as prestamos FROM inventario i LEFT JOIN prestamos p ON i.id = p.elemento_id GROUP BY i.id HAVING prestamos > 0 ORDER BY prestamos DESC LIMIT 10"
    );
    $productos_stock_critico = $db->fetchAll(
        "SELECT nombre, codigo, cantidad_disponible, valor_compra FROM inventario WHERE cantidad_disponible <= 5 ORDER BY cantidad_disponible ASC"
    );
    $prestamos_recientes = $db->fetchAll(
        "SELECT p.*, i.nombre as producto_nombre, i.codigo as producto_codigo FROM prestamos p JOIN inventario i ON p.elemento_id = i.id ORDER BY p.fecha_prestamo DESC LIMIT 10"
    );
} else {
    $stats = [
        'total_productos' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'total_categorias' => $db->fetch("SELECT COUNT(*) as count FROM categorias WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'total_ubicaciones' => $db->fetch("SELECT COUNT(*) as count FROM ubicaciones WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'total_proveedores' => $db->fetch("SELECT COUNT(*) as count FROM proveedores WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'valor_total' => $db->fetch("SELECT COALESCE(SUM(valor_compra * cantidad_disponible), 0) as total FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['total'],
        'productos_stock_bajo' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE cantidad_disponible <= 5 AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'prestamos_activos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'prestamos_vencidos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND fecha_devolucion_esperada < CURDATE() AND usuario_registro = ?", [$_SESSION['user_id']])['count']
    ];
    $productos_por_categoria = $db->fetchAll(
        "SELECT c.nombre as categoria, COUNT(i.id) as cantidad FROM categorias c LEFT JOIN inventario i ON c.id = i.categoria_id AND i.usuario_registro = ? WHERE c.usuario_registro = ? GROUP BY c.id ORDER BY cantidad DESC",
        [$_SESSION['user_id'], $_SESSION['user_id']]
    );
    $productos_por_ubicacion = $db->fetchAll(
        "SELECT u.nombre as ubicacion, COUNT(i.id) as cantidad FROM ubicaciones u LEFT JOIN inventario i ON u.id = i.ubicacion_id AND i.usuario_registro = ? WHERE u.usuario_registro = ? GROUP BY u.id ORDER BY cantidad DESC",
        [$_SESSION['user_id'], $_SESSION['user_id']]
    );
    $prestamos_por_mes = $db->fetchAll(
        "SELECT DATE_FORMAT(fecha_prestamo, '%Y-%m') as mes, COUNT(*) as cantidad FROM prestamos WHERE usuario_registro = ? GROUP BY mes ORDER BY mes DESC LIMIT 12",
        [$_SESSION['user_id']]
    );
    $productos_mas_prestados = $db->fetchAll(
        "SELECT i.nombre as producto, COUNT(p.id) as prestamos FROM inventario i LEFT JOIN prestamos p ON i.id = p.elemento_id AND p.usuario_registro = ? WHERE i.usuario_registro = ? GROUP BY i.id HAVING prestamos > 0 ORDER BY prestamos DESC LIMIT 10",
        [$_SESSION['user_id'], $_SESSION['user_id']]
    );
    $productos_stock_critico = $db->fetchAll(
        "SELECT nombre, codigo, cantidad_disponible, valor_compra FROM inventario WHERE cantidad_disponible <= 5 AND usuario_registro = ? ORDER BY cantidad_disponible ASC",
        [$_SESSION['user_id']]
    );
    $prestamos_recientes = $db->fetchAll(
        "SELECT p.*, i.nombre as producto_nombre, i.codigo as producto_codigo FROM prestamos p JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ? WHERE p.usuario_registro = ? ORDER BY p.fecha_prestamo DESC LIMIT 10",
        [$_SESSION['user_id'], $_SESSION['user_id']]
    );
}

$message = getMessage();

// Exportación Excel
if (isset($_GET['formato']) && $_GET['formato'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Analisis_Inventario_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    echo '<table border="1"><tr><th colspan="2">REPORTE ANÁLISIS - ' . date('d/m/Y H:i:s') . '</th></tr><tr><th colspan="2"></th></tr>';
    echo '<tr><th>Indicador</th><th>Valor</th></tr>';
    echo '<tr><td>Total Productos</td><td>' . $stats['total_productos'] . '</td></tr>';
    echo '<tr><td>Valor Total Inventario</td><td>$' . number_format($stats['valor_total'], 2) . '</td></tr>';
    echo '<tr><td>Productos Stock Bajo</td><td>' . $stats['productos_stock_bajo'] . '</td></tr>';
    echo '<tr><td>Préstamos Activos</td><td>' . $stats['prestamos_activos'] . '</td></tr>';
    echo '<tr><td>Préstamos Vencidos</td><td>' . $stats['prestamos_vencidos'] . '</td></tr>';
    echo '<tr><td>Categorías</td><td>' . $stats['total_categorias'] . '</td></tr>';
    echo '<tr><td>Ubicaciones</td><td>' . $stats['total_ubicaciones'] . '</td></tr>';
    echo '<tr><td>Proveedores</td><td>' . $stats['total_proveedores'] . '</td></tr>';
    echo '<tr><th colspan="2"></th></tr>';
    echo '<tr><th colspan="2">Productos por Categoría</th></tr><tr><th>Categoría</th><th>Cantidad</th></tr>';
    foreach ($productos_por_categoria as $r) { echo '<tr><td>' . htmlspecialchars($r['categoria'] ?? '-') . '</td><td>' . $r['cantidad'] . '</td></tr>'; }
    echo '<tr><th colspan="2"></th></tr>';
    echo '<tr><th colspan="2">Productos por Ubicación</th></tr><tr><th>Ubicación</th><th>Cantidad</th></tr>';
    foreach ($productos_por_ubicacion as $r) { echo '<tr><td>' . htmlspecialchars($r['ubicacion'] ?? '-') . '</td><td>' . $r['cantidad'] . '</td></tr>'; }
    echo '<tr><th colspan="2"></th></tr>';
    echo '<tr><th colspan="2">Préstamos por Mes</th></tr><tr><th>Mes</th><th>Cantidad</th></tr>';
    foreach ($prestamos_por_mes as $r) { echo '<tr><td>' . htmlspecialchars($r['mes']) . '</td><td>' . $r['cantidad'] . '</td></tr>'; }
    echo '<tr><th colspan="2"></th></tr>';
    echo '<tr><th colspan="2">Productos Stock Crítico</th></tr><tr><th>Producto</th><th>Código</th><th>Stock</th><th>Precio</th><th>Valor</th></tr>';
    foreach ($productos_stock_critico as $p) {
        $valor = ($p['valor_compra'] ?? 0) * ($p['cantidad_disponible'] ?? 0);
        echo '<tr><td>' . htmlspecialchars($p['nombre']) . '</td><td>' . htmlspecialchars($p['codigo']) . '</td><td>' . ($p['cantidad_disponible'] ?? 0) . '</td><td>$' . number_format($p['valor_compra'] ?? 0, 2) . '</td><td>$' . number_format($valor, 2) . '</td></tr>';
    }
    echo '<tr><th colspan="2"></th></tr>';
    echo '<tr><th colspan="2">Productos Más Prestados</th></tr><tr><th>Producto</th><th>Préstamos</th></tr>';
    foreach ($productos_mas_prestados as $r) { echo '<tr><td>' . htmlspecialchars($r['producto']) . '</td><td>' . $r['prestamos'] . '</td></tr>'; }
    echo '<tr><th colspan="2"></th></tr>';
    echo '<tr><th colspan="2">Préstamos Recientes</th></tr><tr><th>Producto</th><th>Prestatario</th><th>F. Préstamo</th><th>F. Devolución</th><th>Estado</th></tr>';
    foreach ($prestamos_recientes as $p) {
        $est = $p['estado'] === 'activo' && strtotime($p['fecha_devolucion_esperada']) < time() ? 'Vencido' : ($p['estado'] === 'activo' ? 'Activo' : 'Devuelto');
        echo '<tr><td>' . htmlspecialchars($p['producto_nombre']) . '</td><td>' . htmlspecialchars($p['prestatario']) . '</td><td>' . date('d/m/Y', strtotime($p['fecha_prestamo'])) . '</td><td>' . date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])) . '</td><td>' . $est . '</td></tr>';
    }
    echo '</table>';
    exit;
}

// Exportación PDF (vista para imprimir / Guardar como PDF)
if (isset($_GET['formato']) && $_GET['formato'] === 'pdf') {
    if (ob_get_level()) ob_end_clean();
    ob_start();
    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Análisis Inventario - ' . date('d/m/Y') . '</title>';
    $html .= '<style>@page{margin:1.5cm;size:A4 landscape;} body{font-family:Arial,sans-serif;font-size:9pt;margin:20px;}';
    $html .= 'h1{text-align:center;color:#2563eb;font-size:18pt;} .section{margin-bottom:25px;} table{width:100%;border-collapse:collapse;font-size:8pt;margin-top:8px;}';
    $html .= 'th{background:#2563eb;color:white;padding:6px;border:1px solid #333;} td{padding:5px;border:1px solid #ddd;} .print-hide{display:none;} @media print{.print-hide{display:none!important;}}</style></head><body>';
    $html .= (function_exists('getLogoReportesHtml') ? getLogoReportesHtml() : '');
    $html .= '<h1>ANÁLISIS DEL INVENTARIO</h1><p style="text-align:center;color:#666;">Generado: ' . date('d/m/Y H:i:s') . ' | ' . htmlspecialchars($_SESSION['user_name']) . '</p>';
    $html .= '<div class="section"><h2>Resumen</h2><table><tr><th>Indicador</th><th>Valor</th></tr>';
    $html .= '<tr><td>Total Productos</td><td>' . $stats['total_productos'] . '</td></tr><tr><td>Valor Total</td><td>$' . number_format($stats['valor_total'], 2) . '</td></tr>';
    $html .= '<tr><td>Stock Bajo</td><td>' . $stats['productos_stock_bajo'] . '</td></tr><tr><td>Préstamos Activos</td><td>' . $stats['prestamos_activos'] . '</td></tr><tr><td>Préstamos Vencidos</td><td>' . $stats['prestamos_vencidos'] . '</td></tr></table></div>';
    $html .= '<div class="section"><h2>Productos por Categoría</h2><table><tr><th>Categoría</th><th>Cantidad</th></tr>';
    foreach ($productos_por_categoria as $r) { $html .= '<tr><td>' . htmlspecialchars($r['categoria'] ?? '-') . '</td><td>' . $r['cantidad'] . '</td></tr>'; }
    $html .= '</table></div><div class="section"><h2>Productos por Ubicación</h2><table><tr><th>Ubicación</th><th>Cantidad</th></tr>';
    foreach ($productos_por_ubicacion as $r) { $html .= '<tr><td>' . htmlspecialchars($r['ubicacion'] ?? '-') . '</td><td>' . $r['cantidad'] . '</td></tr>'; }
    $html .= '</table></div><div class="section"><h2>Stock Crítico</h2><table><tr><th>Producto</th><th>Código</th><th>Stock</th><th>Valor</th></tr>';
    foreach ($productos_stock_critico as $p) { $html .= '<tr><td>' . htmlspecialchars($p['nombre']) . '</td><td>' . htmlspecialchars($p['codigo']) . '</td><td>' . ($p['cantidad_disponible'] ?? 0) . '</td><td>$' . number_format(($p['valor_compra'] ?? 0) * ($p['cantidad_disponible'] ?? 0), 2) . '</td></tr>'; }
    $html .= '</table></div><div class="section"><h2>Préstamos Recientes</h2><table><tr><th>Producto</th><th>Prestatario</th><th>F. Préstamo</th><th>Estado</th></tr>';
    foreach ($prestamos_recientes as $p) {
        $est = $p['estado'] === 'activo' && strtotime($p['fecha_devolucion_esperada']) < time() ? 'Vencido' : ($p['estado'] === 'activo' ? 'Activo' : 'Devuelto');
        $html .= '<tr><td>' . htmlspecialchars($p['producto_nombre']) . '</td><td>' . htmlspecialchars($p['prestatario']) . '</td><td>' . date('d/m/Y', strtotime($p['fecha_prestamo'])) . '</td><td>' . $est . '</td></tr>';
    }
    $html .= '</table></div>';
    $html .= '<div class="print-hide" style="text-align:center;margin-top:30px;"><p>Ctrl+P → Guardar como PDF</p><button onclick="window.print()">Imprimir / Guardar PDF</button></div>';
    $html .= '<script>setTimeout(function(){window.print();},500);</script></body></html>';
    ob_end_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analisis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .data-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .data-table h3 {
            padding: 1rem 1.5rem;
            margin: 0;
            background: var(--bg-light);
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            background: var(--bg-light);
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: var(--bg-light);
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-activo { background: #d4edda; color: #155724; }
        .status-vencido { background: #f8d7da; color: #721c24; }
        .status-devuelto { background: #d1ecf1; color: #0c5460; }
        
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .export-btn {
            padding: 0.5rem 1rem;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .export-btn:hover {
            background: var(--success-dark);
        }
    </style>
</head>
<body>
    <?php $nav_active = 'analytics'; include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Análisis del Inventario</h1>
            <div class="export-buttons">
                <a href="analytics.php?formato=excel" class="export-btn">Exportar Excel</a>
                <a href="analytics.php?formato=pdf" class="btn btn-danger">Exportar PDF</a>
                <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <!-- Métricas Principales -->
        <div class="analisis-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_productos']); ?></div>
                <div class="stat-label">Total de Productos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['valor_total'], 2); ?></div>
                <div class="stat-label">Valor Total del Inventario</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['productos_stock_bajo']); ?></div>
                <div class="stat-label">Productos con Stock Bajo</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['prestamos_activos']); ?></div>
                <div class="stat-label">Préstamos Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['prestamos_vencidos']); ?></div>
                <div class="stat-label">Préstamos Vencidos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_categorias']); ?></div>
                <div class="stat-label">Categorías</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_ubicaciones']); ?></div>
                <div class="stat-label">Ubicaciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_proveedores']); ?></div>
                <div class="stat-label">Proveedores</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="chart-container">
            <div class="chart-title">Distribución por Categorías</div>
            <canvas id="categoriasChart" width="400" height="200"></canvas>
        </div>

        <div class="chart-container">
            <div class="chart-title">Distribución por Ubicaciones</div>
            <canvas id="ubicacionesChart" width="400" height="200"></canvas>
        </div>

        <div class="chart-container">
            <div class="chart-title">Préstamos por Mes</div>
            <canvas id="prestamosChart" width="400" height="200"></canvas>
        </div>

        <!-- Tablas de Datos -->
        <div class="data-table">
            <h3>Productos con Stock Crítico</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Código</th>
                        <th>Stock Actual</th>
                        <th>Precio</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos_stock_critico)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">No hay productos con stock crítico</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos_stock_critico as $producto): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                <td>
                                    <span style="color: <?php echo $producto['cantidad_disponible'] <= 2 ? '#dc3545' : '#ffc107'; ?>">
                                        <?php echo $producto['cantidad_disponible']; ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($producto['valor_compra'], 2); ?></td>
                                <td>$<?php echo number_format($producto['valor_compra'] * $producto['cantidad_disponible'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="data-table">
            <h3>Productos Más Prestados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Total de Préstamos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos_mas_prestados)): ?>
                        <tr>
                            <td colspan="2" style="text-align: center; color: #666;">No hay datos de préstamos</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos_mas_prestados as $producto): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($producto['producto']); ?></td>
                                <td><?php echo $producto['prestamos']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="data-table">
            <h3>Préstamos Recientes</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Prestatario</th>
                        <th>Fecha Préstamo</th>
                        <th>Fecha Devolución</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestamos_recientes)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">No hay préstamos recientes</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prestamos_recientes as $prestamo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prestamo['producto_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prestamo['prestatario']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])); ?></td>
                                <td>
                                    <?php
                                    $estado_class = 'status-activo';
                                    $estado_text = 'Activo';
                                    
                                    if ($prestamo['estado'] !== 'activo') {
                                        $estado_class = 'status-devuelto';
                                        $estado_text = 'Devuelto';
                                    } elseif (strtotime($prestamo['fecha_devolucion_esperada']) < time()) {
                                        $estado_class = 'status-vencido';
                                        $estado_text = 'Vencido';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $estado_class; ?>">
                                        <?php echo $estado_text; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Configuración de colores para los gráficos
        const colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
        ];

        // Gráfico de Categorías
        const categoriasCtx = document.getElementById('categoriasChart').getContext('2d');
        new Chart(categoriasCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($productos_por_categoria, 'categoria')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($productos_por_categoria, 'cantidad')); ?>,
                    backgroundColor: colors.slice(0, <?php echo count($productos_por_categoria); ?>),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Ubicaciones
        const ubicacionesCtx = document.getElementById('ubicacionesChart').getContext('2d');
        new Chart(ubicacionesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($productos_por_ubicacion, 'ubicacion')); ?>,
                datasets: [{
                    label: 'Productos',
                    data: <?php echo json_encode(array_column($productos_por_ubicacion, 'cantidad')); ?>,
                    backgroundColor: '#36A2EB',
                    borderColor: '#2693e6',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfico de Préstamos por Mes
        const prestamosCtx = document.getElementById('prestamosChart').getContext('2d');
        new Chart(prestamosCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($prestamos_por_mes, 'mes')); ?>,
                datasets: [{
                    label: 'Préstamos',
                    data: <?php echo json_encode(array_column($prestamos_por_mes, 'cantidad')); ?>,
                    borderColor: '#FF6384',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

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
</body>
</html> 