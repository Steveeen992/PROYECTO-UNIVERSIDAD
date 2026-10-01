<?php
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$formato = $_GET['formato'] ?? 'html';

// Obtener categorías y ubicaciones para filtros (solo del usuario actual)
$categorias = $db->fetchAll("SELECT * FROM categorias WHERE usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);
$ubicaciones = $db->fetchAll("SELECT * FROM ubicaciones WHERE usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);

// Construir consulta con filtros
$where_conditions = [];
$params = [];

// FILTRO OBLIGATORIO: Solo inventario del usuario actual
$where_conditions[] = "i.usuario_registro = ?";
$params[] = $_SESSION['user_id'];

// FILTRO OBLIGATORIO: Solo elementos con cantidad disponible > 0 (inventario real)
$where_conditions[] = "i.cantidad_disponible > 0";

if (!empty($filtro_categoria)) {
    $where_conditions[] = "i.categoria_id = ?";
    $params[] = $filtro_categoria;
}

if (!empty($filtro_ubicacion)) {
    $where_conditions[] = "i.ubicacion_id = ?";
    $params[] = $filtro_ubicacion;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "i.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "i.fecha_adquisicion >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "i.fecha_adquisicion <= ?";
    $params[] = $filtro_fecha_hasta;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Obtener datos del inventario (solo del usuario actual y con cantidad disponible > 0)
// cantidad_prestada_activa = total prestado en préstamos activos (para mostrar P + número en el reporte)
$inventario = $db->fetchAll(
    "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion, us.nombre as usuario_registro,
            (SELECT COALESCE(SUM(p.cantidad_prestada), 0) FROM prestamos p WHERE p.elemento_id = i.id AND p.estado = 'activo') as cantidad_prestada_activa
     FROM inventario i
     LEFT JOIN categorias c ON i.categoria_id = c.id AND c.usuario_registro = ?
     LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id AND u.usuario_registro = ?
     LEFT JOIN usuarios us ON i.usuario_registro = us.id
     $where_clause
     ORDER BY i.codigo",
    array_merge([$_SESSION['user_id'], $_SESSION['user_id']], $params)
);

// Calcular estadísticas (valor total = suma de valor unitario × cantidad por cada producto)
$total_valor_calculado = 0;
foreach ($inventario as $item) {
    $vu = isset($item['valor_compra']) && $item['valor_compra'] !== '' && $item['valor_compra'] !== null ? (float)$item['valor_compra'] : 0;
    $cant = (int)($item['cantidad_disponible'] ?? 0);
    $total_valor_calculado += $vu * $cant;
}
$stats = [
    'total_elementos' => count($inventario),
    'total_valor' => $total_valor_calculado,
    'por_estado' => [],
    'por_categoria' => [],
    'por_ubicacion' => []
];

foreach ($inventario as $item) {
    $estado = $item['estado'] ?? 'sin_estado';
    $categoria = $item['categoria'] ?? 'Sin categoría';
    $ubicacion = $item['ubicacion'] ?? 'Sin ubicación';
    
    $stats['por_estado'][$estado] = ($stats['por_estado'][$estado] ?? 0) + 1;
    $stats['por_categoria'][$categoria] = ($stats['por_categoria'][$categoria] ?? 0) + 1;
    $stats['por_ubicacion'][$ubicacion] = ($stats['por_ubicacion'][$ubicacion] ?? 0) + 1;
}

// Generar reporte en Excel
if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="inventario_inicial_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='11'>REPORTE DE INVENTARIO INICIAL</th></tr>";
    echo "<tr><th colspan='11'>Fecha de generación: " . date('d/m/Y H:i:s') . "</th></tr>";
    echo "<tr><th colspan='11'>Filtros aplicados: " . 
         ($filtro_categoria ? "Categoría: " . $filtro_categoria . " " : "") .
         ($filtro_ubicacion ? "Ubicación: " . $filtro_ubicacion . " " : "") .
         ($filtro_estado ? "Estado: " . $filtro_estado . " " : "") .
         ($filtro_fecha_desde ? "Desde: " . $filtro_fecha_desde . " " : "") .
         ($filtro_fecha_hasta ? "Hasta: " . $filtro_fecha_hasta . " " : "") .
         "</th></tr>";
    echo "<tr><th colspan='11'></th></tr>";
    
    // Encabezados
    echo "<tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Ubicación</th>
            <th>Estado</th>
            <th>Cantidad</th>
            <th>Valor Unitario</th>
            <th>Valor Total</th>
            <th>Fecha Adquisición</th>
          </tr>";
    
    // Datos (P. = préstamos activos)
    foreach ($inventario as $item) {
        $cantidad_prestada = (int)($item['cantidad_prestada_activa'] ?? 0);
        $cantidad_display = htmlspecialchars($item['cantidad_disponible']);
        if ($cantidad_prestada > 0) {
            $cantidad_display .= ' (P. ' . $cantidad_prestada . ')';
        }
        $valor_unit = isset($item['valor_compra']) && $item['valor_compra'] !== '' && $item['valor_compra'] !== null ? (float)$item['valor_compra'] : 0;
        $cant = (int)($item['cantidad_disponible'] ?? 0);
        $valor_total_fila = $valor_unit * $cant;
        
        echo "<tr>
                <td>" . htmlspecialchars($item['codigo']) . "</td>
                <td>" . htmlspecialchars($item['nombre']) . "</td>
                <td>" . htmlspecialchars($item['categoria'] ?? 'Sin categoría') . "</td>
                <td>" . htmlspecialchars($item['marca']) . "</td>
                <td>" . htmlspecialchars($item['modelo']) . "</td>
                <td>" . htmlspecialchars($item['ubicacion'] ?? 'Sin ubicación') . "</td>
                <td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $item['estado']))) . "</td>
                <td>" . $cantidad_display . "</td>
                <td>" . ($valor_unit > 0 ? '$' . number_format($valor_unit, 2) : '-') . "</td>
                <td>" . ($valor_total_fila > 0 ? '$' . number_format($valor_total_fila, 2) : '-') . "</td>
                <td>" . ($item['fecha_adquisicion'] ? date('d/m/Y', strtotime($item['fecha_adquisicion'])) : '-') . "</td>
              </tr>";
    }
    
    echo "<tr><th colspan='11'></th></tr>";
    echo "<tr><th colspan='6'>TOTAL ELEMENTOS: " . $stats['total_elementos'] . "</th>";
    echo "<th colspan='5'>VALOR TOTAL INVENTARIO: $" . number_format($stats['total_valor'], 2) . "</th></tr>";
    
    echo "</table>";
    exit;
}

// Generar reporte en PDF
if ($formato === 'pdf') {
    // Limpiar cualquier salida previa (si hay buffer activo)
    if (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Generar HTML optimizado para PDF
    $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario - ' . date('d/m/Y') . '</title>
    <style>
        @page { 
            margin: 1.5cm; 
            size: A4 landscape;
        }
        @media print {
            button, .print-hide { display: none !important; }
            body { margin: 0; padding: 0; }
        }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 9pt; 
            margin: 20px;
        }
        h1 { 
            text-align: center; 
            color: #2563eb; 
            margin-bottom: 5px; 
            font-size: 18pt;
        }
        .header-info { 
            text-align: center; 
            margin-bottom: 15px; 
            font-size: 8pt; 
            color: #666; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
            font-size: 8pt;
        }
        th { 
            background-color: #2563eb; 
            color: white; 
            padding: 6px; 
            text-align: left; 
            font-size: 8pt; 
            border: 1px solid #333; 
        }
        td { 
            padding: 5px; 
            border: 1px solid #ddd; 
            font-size: 8pt; 
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .stats { 
            margin-top: 20px; 
            padding: 10px; 
            background-color: #f0f0f0; 
            border: 1px solid #ddd; 
        }
        .stats-row { 
            display: flex; 
            justify-content: space-around; 
            margin-top: 10px; 
        }
        .stat-item { 
            text-align: center; 
        }
        .stat-label { 
            font-size: 7pt; 
            color: #666; 
        }
        .stat-value { 
            font-size: 11pt; 
            font-weight: bold; 
            color: #2563eb; 
        }
        .prestado { 
            color: #888; 
            font-size: 0.85em; 
        }
        button {
            background-color: #2563eb;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11pt;
            font-weight: bold;
            margin-top: 10px;
        }
        button:hover {
            background-color: #1e4fd8;
        }
    </style>
</head>
<body>
    ' . (function_exists('getLogoReportesHtml') ? getLogoReportesHtml() : '') . '
    <h1>REPORTE DE INVENTARIO</h1>
    <div class="header-info">
        <p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    
    // Agregar filtros aplicados
    $filtros_texto = [];
    if (!empty($filtro_categoria)) {
        $cat_nombre = $db->fetch("SELECT nombre FROM categorias WHERE id = ? AND usuario_registro = ?", [$filtro_categoria, $_SESSION['user_id']]);
        if ($cat_nombre) {
            $filtros_texto[] = "Categoría: " . $cat_nombre['nombre'];
        }
    }
    if (!empty($filtro_ubicacion)) {
        $ubic_nombre = $db->fetch("SELECT nombre FROM ubicaciones WHERE id = ? AND usuario_registro = ?", [$filtro_ubicacion, $_SESSION['user_id']]);
        if ($ubic_nombre) {
            $filtros_texto[] = "Ubicación: " . $ubic_nombre['nombre'];
        }
    }
    if (!empty($filtro_estado)) {
        $filtros_texto[] = "Estado: " . ucfirst(str_replace('_', ' ', $filtro_estado));
    }
    if (!empty($filtro_fecha_desde)) {
        $filtros_texto[] = "Desde: " . date('d/m/Y', strtotime($filtro_fecha_desde));
    }
    if (!empty($filtro_fecha_hasta)) {
        $filtros_texto[] = "Hasta: " . date('d/m/Y', strtotime($filtro_fecha_hasta));
    }
    
    if (!empty($filtros_texto)) {
        $html .= '<p><strong>Filtros aplicados:</strong> ' . implode(' | ', $filtros_texto) . '</p>';
    }
    
    $html .= '</div>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Ubicación</th>
                <th>Estado</th>
                <th>Cantidad</th>
                <th>Valor Unitario</th>
                <th>Valor Total</th>
                <th>Fecha Adquisición</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($inventario as $item) {
        $cantidad_prestada = (int)($item['cantidad_prestada_activa'] ?? 0);
        $valor_unit = isset($item['valor_compra']) && $item['valor_compra'] !== '' && $item['valor_compra'] !== null ? (float)$item['valor_compra'] : 0;
        $cant = (int)($item['cantidad_disponible'] ?? 0);
        $valor_total_fila = $valor_unit * $cant;
        
        $html .= '<tr>
            <td>' . htmlspecialchars($item['codigo']) . '</td>
            <td>' . htmlspecialchars($item['nombre']) . '</td>
            <td>' . htmlspecialchars($item['categoria'] ?? 'Sin categoría') . '</td>
            <td>' . htmlspecialchars($item['marca']) . '</td>
            <td>' . htmlspecialchars($item['modelo']) . '</td>
            <td>' . htmlspecialchars($item['ubicacion'] ?? 'Sin ubicación') . '</td>
            <td>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $item['estado']))) . '</td>
            <td>' . htmlspecialchars($item['cantidad_disponible']);
        
        if ($cantidad_prestada > 0) {
            $html .= ' <span class="prestado">(P. ' . $cantidad_prestada . ')</span>';
        }
        
        $html .= '</td>
            <td>' . ($valor_unit > 0 ? '$' . number_format($valor_unit, 2) : '-') . '</td>
            <td>' . ($valor_total_fila > 0 ? '$' . number_format($valor_total_fila, 2) : '-') . '</td>
            <td>' . ($item['fecha_adquisicion'] ? date('d/m/Y', strtotime($item['fecha_adquisicion'])) : '-') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="stats">
        <h2 style="text-align: center; margin-bottom: 15px; font-size: 11pt;">RESUMEN DEL REPORTE</h2>
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-value">' . $stats['total_elementos'] . '</div>
                <div class="stat-label">Total Elementos</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">$' . number_format($stats['total_valor'], 2) . '</div>
                <div class="stat-label">Valor Total</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">' . count($stats['por_categoria']) . '</div>
                <div class="stat-label">Categorías</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">' . count($stats['por_ubicacion']) . '</div>
                <div class="stat-label">Ubicaciones</div>
            </div>
        </div>
    </div>
    <div class="print-hide" style="text-align: center; margin-top: 30px; padding: 20px; border-top: 2px solid #2563eb;">
        <p style="color: #666; font-size: 9pt; margin-bottom: 15px;">Para guardar como PDF, presiona Ctrl+P (o Cmd+P en Mac) y selecciona "Guardar como PDF"</p>
        <button onclick="window.print();">
            📄 Imprimir / Guardar como PDF
        </button>
    </div>
    <script>
        // Auto-abrir diálogo de impresión al cargar
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>';
    
    // Limpiar buffer y enviar contenido
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
    <title>Reporte de Inventario - <?php echo SITE_NAME; ?></title>
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
            <h1>Reporte de Inventario Inicial</h1>
            <div class="header-actions">
                <a href="reporte_inventario.php?formato=excel<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                    Exportar Excel
                </a>
                <a href="reporte_inventario.php?formato=pdf<?php echo http_build_query($_GET); ?>" class="btn btn-danger">
                    Exportar PDF
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters-section">
            <h3>Filtros de Reporte</h3>
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="categoria">Categoría:</label>
                        <select name="categoria" id="categoria">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="ubicacion">Ubicación:</label>
                        <select name="ubicacion" id="ubicacion">
                            <option value="">Todas las ubicaciones</option>
                            <?php foreach ($ubicaciones as $ubi): ?>
                                <option value="<?php echo $ubi['id']; ?>" <?php echo $filtro_ubicacion == $ubi['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ubi['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="estado">Estado:</label>
                        <select name="estado" id="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo $filtro_estado == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="en_reparacion" <?php echo $filtro_estado == 'en_reparacion' ? 'selected' : ''; ?>>En Reparación</option>
                            <option value="dado_de_baja" <?php echo $filtro_estado == 'dado_de_baja' ? 'selected' : ''; ?>>Dado de Baja</option>
                            <option value="perdido" <?php echo $filtro_estado == 'perdido' ? 'selected' : ''; ?>>Perdido</option>
                        </select>
                    </div>
                </div>

                <div class="filter-row">
                    <div class="filter-group">
                        <label for="fecha_desde">Fecha desde:</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo $filtro_fecha_desde; ?>">
                    </div>

                    <div class="filter-group">
                        <label for="fecha_hasta">Fecha hasta:</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo $filtro_fecha_hasta; ?>">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                        <a href="reporte_inventario.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Estadísticas -->
        <div class="stats-section">
            <h3>Resumen del Reporte</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_elementos']; ?></div>
                    <div class="stat-label">Total Elementos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($stats['total_valor'], 2); ?></div>
                    <div class="stat-label">Valor Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($stats['por_categoria']); ?></div>
                    <div class="stat-label">Categorías</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($stats['por_ubicacion']); ?></div>
                    <div class="stat-label">Ubicaciones</div>
                </div>
            </div>
        </div>

        <!-- Tabla de datos -->
        <div class="report-section">
            <h3>Detalle del Inventario</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Ubicación</th>
                            <th>Estado</th>
                            <th>Cantidad</th>
                            <th>Valor Unitario</th>
                            <th>Valor Total</th>
                            <th>Fecha Adquisición</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventario)): ?>
                            <tr>
                                <td colspan="11" class="text-center">No se encontraron elementos con los filtros aplicados</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventario as $item): ?>
                                <?php
                                $cantidad_prestada = (int)($item['cantidad_prestada_activa'] ?? 0);
                                $valor_unit = isset($item['valor_compra']) && $item['valor_compra'] !== '' && $item['valor_compra'] !== null ? (float)$item['valor_compra'] : 0;
                                $cant = (int)($item['cantidad_disponible'] ?? 0);
                                $valor_total_fila = $valor_unit * $cant;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($item['categoria'] ?? 'Sin categoría'); ?></td>
                                    <td><?php echo htmlspecialchars($item['marca'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($item['modelo'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($item['ubicacion'] ?? 'Sin ubicación'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $item['estado']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $item['estado'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['cantidad_disponible']); ?>
                                        <?php if ($cantidad_prestada > 0): ?>
                                            <span style="color:#888;font-size:0.95em;">(P. <?php echo $cantidad_prestada; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $valor_unit > 0 ? '$' . number_format($valor_unit, 2) : '-'; ?></td>
                                    <td><?php echo $valor_total_fila > 0 ? '$' . number_format($valor_total_fila, 2) : '-'; ?></td>
                                    <td><?php echo $item['fecha_adquisicion'] ? date('d/m/Y', strtotime($item['fecha_adquisicion'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gráficos de estadísticas -->
        <div class="charts-section">
            <div class="chart-grid">
                <div class="chart-card">
                    <h4>Distribución por Estado</h4>
                    <div class="chart-container">
                        <?php foreach ($stats['por_estado'] as $estado => $cantidad): ?>
                            <div class="chart-item">
                                <span class="chart-label"><?php echo ucfirst(str_replace('_', ' ', $estado)); ?></span>
                                <span class="chart-value"><?php echo $cantidad; ?></span>
                                <div class="chart-bar" style="width: <?php echo ($cantidad / $stats['total_elementos']) * 100; ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="chart-card">
                    <h4>Distribución por Categoría</h4>
                    <div class="chart-container">
                        <?php foreach ($stats['por_categoria'] as $categoria => $cantidad): ?>
                            <div class="chart-item">
                                <span class="chart-label"><?php echo htmlspecialchars($categoria); ?></span>
                                <span class="chart-value"><?php echo $cantidad; ?></span>
                                <div class="chart-bar" style="width: <?php echo ($cantidad / $stats['total_elementos']) * 100; ?>%"></div>
                            </div>
                        <?php endforeach; ?>
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

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    </script>

    <style>
        .header-actions {
            display: flex;
            gap: 10px;
        }

        /* Estilos mejorados para la sección de filtros */
        .filters-section {
            background: #2563eb;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            color: white;
            position: relative;
        }

        .filters-section h3 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .filter-form {
            position: relative;
            z-index: 1;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 12px 15px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            background: white;
            color: #333;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
            transform: translateY(-2px);
        }

        .filter-group select:hover,
        .filter-group input:hover {
            border-color: rgba(255,255,255,0.6);
            transform: translateY(-1px);
        }

        .filter-actions {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-top: 10px;
        }

        .filter-actions .btn {
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .filter-actions .btn-primary {
            background: white;
            color: #2563eb;
            border-color: rgba(255,255,255,0.3);
        }

        .filter-actions .btn-primary:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .filter-actions .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border-color: rgba(255,255,255,0.3);
        }

        .filter-actions .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        /* Estilos para las estadísticas */
        .stats-section {
            margin-bottom: 30px;
        }

        .stats-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.4rem;
            font-weight: 600;
            position: relative;
        }

        .stats-section h3::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background: #2563eb;
            border-radius: 2px;
        }

        /* Estilos para la sección de reporte */
        .report-section {
            margin-bottom: 30px;
        }

        .report-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.4rem;
            font-weight: 600;
            position: relative;
        }

        .report-section h3::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background: #2563eb;
            border-radius: 2px;
        }

        /* Estilos para los gráficos */
        .charts-section {
            margin-top: 30px;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .chart-card h4 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: 600;
            position: relative;
        }

        .chart-card h4::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 30px;
            height: 2px;
            background: #2563eb;
            border-radius: 1px;
        }

        .chart-container {
            margin-top: 20px;
        }

        .chart-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            position: relative;
            padding: 10px 0;
        }

        .chart-label {
            flex: 1;
            font-size: 0.9rem;
            color: #555;
            font-weight: 500;
        }

        .chart-value {
            margin: 0 15px;
            font-weight: bold;
            min-width: 40px;
            text-align: right;
            color: #2563eb;
            font-size: 1.1rem;
        }

        .chart-bar {
            height: 25px;
            background: #2563eb;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        /* Responsive design mejorado */
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-actions .btn {
                width: 100%;
                text-align: center;
            }
            
            .chart-grid {
                grid-template-columns: 1fr;
            }

            .filters-section {
                padding: 20px;
            }

            .chart-card {
                padding: 20px;
            }
        }

        /* Animaciones adicionales */
        .filter-group select option {
            background: white;
            color: #333;
        }

        .filter-group select option:hover {
            background: #f8f9fa;
        }

        /* Efectos de hover para los gráficos */
        .chart-item:hover .chart-bar {
            transform: scaleX(1.05);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .chart-item:hover .chart-label {
            color: #2563eb;
            font-weight: 600;
        }
    </style>
</body>
</html> 