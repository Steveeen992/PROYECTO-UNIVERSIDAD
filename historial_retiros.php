<?php
require_once 'config.php';
requireLogin();

// Obtener categorías y ubicaciones para los filtros (solo del usuario actual)
$categorias = $db->fetchAll("SELECT * FROM categorias WHERE usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);
$ubicaciones = $db->fetchAll("SELECT * FROM ubicaciones WHERE usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);

// Construir filtros para la consulta
// Filtrar solo retiros del usuario actual
$where_conditions = ["he.tipo_evento = 'retiro'", "he.usuario_registro = ?"];
$params = [$_SESSION['user_id']];

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    // Búsqueda mejorada que funciona incluso con elementos eliminados
    $where_conditions[] = "(COALESCE(i.codigo, '') LIKE ? OR COALESCE(i.nombre, '') LIKE ? OR COALESCE(i.marca, '') LIKE ? OR COALESCE(i.modelo, '') LIKE ? OR he.descripcion LIKE ?)";
    $params = array_merge($params, [$search, $search, $search, $search, $search]);
}

if (!empty($_GET['categoria'])) {
    $where_conditions[] = "i.categoria_id = ?";
    $params[] = $_GET['categoria'];
}

if (!empty($_GET['ubicacion'])) {
    $where_conditions[] = "i.ubicacion_id = ?";
    $params[] = $_GET['ubicacion'];
}

if (!empty($_GET['fecha_desde'])) {
    $where_conditions[] = "he.fecha_evento >= ?";
    $params[] = $_GET['fecha_desde'];
}

if (!empty($_GET['fecha_hasta'])) {
    $where_conditions[] = "he.fecha_evento <= ?";
    $params[] = $_GET['fecha_hasta'];
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Función para extraer información del retiro de la descripción (usada en PDF, Excel y listado)
function extraerInfoRetiro($descripcion) {
    $info = ['cantidad' => '', 'receptor' => '', 'finalidad' => '', 'cantidad_restante' => ''];
    if (preg_match('/Retiro de (\d+) unidades/', $descripcion, $m)) $info['cantidad'] = $m[1];
    if (preg_match('/\) a ([^:]+) con finalidad:/', $descripcion, $m)) $info['receptor'] = trim($m[1]);
    if (preg_match('/con finalidad: ([^.]+)\./', $descripcion, $m)) $info['finalidad'] = trim($m[1]);
    if (preg_match('/Cantidad restante: (\d+)\./', $descripcion, $m)) $info['cantidad_restante'] = $m[1];
    return $info;
}

// Consulta única de retiros (para listado, PDF y Excel)
$retiros = $db->fetchAll(
    "SELECT 
        he.id,
        he.fecha_evento,
        he.descripcion,
        he.fecha_registro,
        COALESCE(i.codigo, 'ELEMENTO ELIMINADO') as codigo,
        COALESCE(i.nombre, 'Elemento eliminado del inventario') as elemento_nombre,
        COALESCE(i.marca, '') as marca,
        COALESCE(i.modelo, '') as modelo,
        c.nombre as categoria,
        u.nombre as ubicacion,
        us.nombre as usuario_registro
     FROM historial_elementos he
     LEFT JOIN inventario i ON he.elemento_id = i.id
     LEFT JOIN categorias c ON i.categoria_id = c.id
     LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id
     LEFT JOIN usuarios us ON he.usuario_registro = us.id
     $where_clause
     ORDER BY he.fecha_evento DESC, he.fecha_registro DESC",
    $params
);

// Exportación a Excel
if (isset($_GET['formato']) && $_GET['formato'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Reporte_Retiros_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo '<table border="1"><tr><th colspan="13">REPORTE DE HISTORIAL DE RETIROS</th></tr>';
    echo '<tr><th colspan="13">Fecha de generación: ' . date('d/m/Y H:i:s') . ' | Generado por: ' . htmlspecialchars($_SESSION['user_name']) . '</th></tr>';
    echo '<tr><th colspan="13"></th></tr>';
    echo '<tr><th>Fecha Retiro</th><th>Código</th><th>Elemento</th><th>Marca/Modelo</th><th>Categoría</th><th>Ubicación</th><th>Cantidad</th><th>Receptor</th><th>Finalidad</th><th>Cant. Restante</th><th>Usuario</th><th>Fecha Registro</th></tr>';
    foreach ($retiros as $r) {
        $info = extraerInfoRetiro($r['descripcion']);
        echo '<tr>';
        echo '<td>' . date('d/m/Y', strtotime($r['fecha_evento'])) . '</td>';
        echo '<td>' . htmlspecialchars($r['codigo']) . '</td>';
        echo '<td>' . htmlspecialchars($r['elemento_nombre']) . '</td>';
        echo '<td>' . htmlspecialchars(trim($r['marca'] . ' ' . $r['modelo'])) . '</td>';
        echo '<td>' . htmlspecialchars($r['categoria'] ?? 'Sin categoría') . '</td>';
        echo '<td>' . htmlspecialchars($r['ubicacion'] ?? 'Sin ubicación') . '</td>';
        echo '<td>' . htmlspecialchars($info['cantidad'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($info['receptor'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($info['finalidad'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($info['cantidad_restante'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($r['usuario_registro']) . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($r['fecha_registro'])) . '</td>';
        echo '</tr>';
    }
    echo '<tr><th colspan="13"></th></tr>';
    echo '<tr><th colspan="8">TOTAL REGISTROS: ' . count($retiros) . '</th><th colspan="5"></th></tr>';
    echo '</table>';
    exit;
}

// Exportación a PDF (vista para imprimir / Guardar como PDF)
if (isset($_GET['formato']) && $_GET['formato'] === 'pdf') {
    if (ob_get_level()) ob_end_clean();
    ob_start();
    
    // Generar HTML optimizado para PDF
    $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Retiros - ' . date('d/m/Y') . '</title>
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
            background-color: #dc3545; 
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
            color: #dc3545; 
        }
        button {
            background-color: #dc3545;
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
            background-color: #c82333;
        }
    </style>
</head>
<body>
    ' . (function_exists('getLogoReportesHtml') ? getLogoReportesHtml() : '') . '
    <h1>HISTORIAL DE RETIROS</h1>
    <div class="header-info">
        <p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>
        <p><strong>Generado por:</strong> ' . htmlspecialchars($_SESSION['user_name']) . '</p>';
    
    // Agregar filtros aplicados
    $filtros_texto = [];
    if (!empty($_GET['search'])) {
        $filtros_texto[] = "Búsqueda: " . htmlspecialchars($_GET['search']);
    }
    if (!empty($_GET['categoria'])) {
        $cat_nombre = $db->fetch("SELECT nombre FROM categorias WHERE id = ? AND usuario_registro = ?", [$_GET['categoria'], $_SESSION['user_id']]);
        if ($cat_nombre) {
            $filtros_texto[] = "Categoría: " . $cat_nombre['nombre'];
        }
    }
    if (!empty($_GET['ubicacion'])) {
        $ubic_nombre = $db->fetch("SELECT nombre FROM ubicaciones WHERE id = ? AND usuario_registro = ?", [$_GET['ubicacion'], $_SESSION['user_id']]);
        if ($ubic_nombre) {
            $filtros_texto[] = "Ubicación: " . $ubic_nombre['nombre'];
        }
    }
    if (!empty($_GET['fecha_desde'])) {
        $filtros_texto[] = "Desde: " . date('d/m/Y', strtotime($_GET['fecha_desde']));
    }
    if (!empty($_GET['fecha_hasta'])) {
        $filtros_texto[] = "Hasta: " . date('d/m/Y', strtotime($_GET['fecha_hasta']));
    }
    
    if (!empty($filtros_texto)) {
        $html .= '<p><strong>Filtros aplicados:</strong> ' . implode(' | ', $filtros_texto) . '</p>';
    }
    
    $html .= '</div>
    <table>
        <thead>
            <tr>
                <th>Fecha Retiro</th>
                <th>Código</th>
                <th>Elemento</th>
                <th>Marca/Modelo</th>
                <th>Categoría</th>
                <th>Ubicación</th>
                <th>Cantidad</th>
                <th>Receptor</th>
                <th>Finalidad</th>
            </tr>
        </thead>
        <tbody>';
    
    // Procesar retiros y agregar datos a la tabla
    foreach ($retiros as $retiro_pdf) {
        $info_retiro = extraerInfoRetiro($retiro_pdf['descripcion']);
        
        $html .= '<tr>
            <td>' . date('d/m/Y', strtotime($retiro_pdf['fecha_evento'])) . '</td>
            <td>' . htmlspecialchars($retiro_pdf['codigo']) . '</td>
            <td>' . htmlspecialchars($retiro_pdf['elemento_nombre']) . '</td>
            <td>' . htmlspecialchars(trim($retiro_pdf['marca'] . ' ' . $retiro_pdf['modelo'])) . '</td>
            <td>' . htmlspecialchars($retiro_pdf['categoria'] ?? 'Sin categoría') . '</td>
            <td>' . htmlspecialchars($retiro_pdf['ubicacion'] ?? 'Sin ubicación') . '</td>
            <td>' . htmlspecialchars($info_retiro['cantidad'] ?: '-') . '</td>
            <td>' . htmlspecialchars($info_retiro['receptor'] ?: '-') . '</td>
            <td>' . htmlspecialchars($info_retiro['finalidad'] ?: '-') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="stats">
        <h2 style="text-align: center; margin-bottom: 15px; font-size: 11pt;">RESUMEN DEL REPORTE</h2>
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-value">' . count($retiros) . '</div>
                <div class="stat-label">Total Retiros</div>
            </div>
        </div>
    </div>
    <div class="print-hide" style="text-align: center; margin-top: 30px; padding: 20px; border-top: 2px solid #dc3545;">
        <p style="color: #666; font-size: 9pt; margin-bottom: 15px;">Para guardar como PDF, presiona Ctrl+P (o Cmd+P en Mac) y selecciona "Guardar como PDF"</p>
        <button onclick="window.print();">
            📄 Imprimir / Guardar como PDF
        </button>
    </div>
    <script>
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
    <title>Historial de Retiros - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .info-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .info-item {
            flex: 1;
            min-width: 200px;
        }
        
        .info-item strong {
            color:rgb(87, 73, 73);
        }
        
        .retiro-details {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .retiro-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .retiro-details .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .badge-retiro {
            background-color:rgb(231, 37, 37);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .finalidad-text {
            font-style: italic;
            color: #666;
            background: #fff;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg,rgb(40, 25, 173) 0%,rgb(42, 22, 155) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-actions .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php $nav_active = ''; include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Historial de Retiros</h1>
            <div class="header-actions">
                <a href="inventario.php" class="btn btn-primary">Volver al Inventario</a>
                <?php $query_export = http_build_query(array_merge($_GET, ['formato' => 'excel'])); ?>
                <a href="historial_retiros.php?<?php echo $query_export; ?>" class="btn btn-success">Exportar Excel</a>
                <a href="historial_retiros.php?<?php echo http_build_query(array_merge($_GET, ['formato' => 'pdf'])); ?>" class="btn btn-danger">Exportar PDF</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($retiros); ?></div>
                <div class="stat-label">Total de Retiros</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $elementos_unicos = array_unique(array_column($retiros, 'codigo'));
                    echo count($elementos_unicos);
                    ?>
                </div>
                <div class="stat-label">Elementos Diferentes</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Buscar por código, nombre, receptor..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    
                    <select name="categoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($_GET['categoria'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="ubicacion">
                        <option value="">Todas las ubicaciones</option>
                        <?php foreach ($ubicaciones as $ubi): ?>
                            <option value="<?php echo $ubi['id']; ?>" 
                                    <?php echo ($_GET['ubicacion'] ?? '') == $ubi['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ubi['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="fecha_desde" placeholder="Fecha desde" 
                           value="<?php echo htmlspecialchars($_GET['fecha_desde'] ?? ''); ?>">
                    
                    <input type="date" name="fecha_hasta" placeholder="Fecha hasta" 
                           value="<?php echo htmlspecialchars($_GET['fecha_hasta'] ?? ''); ?>">
                    
                    <button type="submit" class="btn btn-secondary">Filtrar</button>
                    <a href="historial_retiros.php" class="btn btn-light">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Lista de retiros -->
        <?php if (empty($retiros)): ?>
            <div class="info-card">
                <h3>No se encontraron retiros</h3>
                <p>No hay registros de retiros que coincidan con los filtros aplicados.</p>
            </div>
        <?php else: ?>
            <?php foreach ($retiros as $retiro): 
                $info_retiro = extraerInfoRetiro($retiro['descripcion']);
            ?>
                <div class="info-card">
                    <div class="info-row">
                        <div class="info-item">
                            <strong>Elemento:</strong><br>
                            <?php echo htmlspecialchars($retiro['elemento_nombre']); ?>
                            <span class="badge badge-secondary"><?php echo htmlspecialchars($retiro['codigo']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Fecha de Retiro:</strong><br>
                            <?php echo date('d/m/Y', strtotime($retiro['fecha_evento'])); ?>
                        </div>
                        <div class="info-item">
                            <strong>Registrado por:</strong><br>
                            <?php echo htmlspecialchars($retiro['usuario_registro']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Cantidad Retirada:</strong><br>
                            <span class="badge-retiro"><?php echo htmlspecialchars($info_retiro['cantidad']); ?> unidades</span>
                        </div>
                    </div>
                    
                    <div class="retiro-details">
                        <div class="detail-row">
                            <strong>Marca/Modelo:</strong>
                            <span><?php echo htmlspecialchars(trim($retiro['marca'] . ' ' . $retiro['modelo'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Categoría:</strong>
                            <span><?php echo htmlspecialchars($retiro['categoria'] ?? 'Sin categoría'); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Ubicación:</strong>
                            <span><?php echo htmlspecialchars($retiro['ubicacion'] ?? 'Sin ubicación'); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Entregado a:</strong>
                            <span><?php echo htmlspecialchars($info_retiro['receptor']); ?></span>
                        </div>
                        <?php if (!empty($info_retiro['cantidad_restante'])): ?>
                        <div class="detail-row">
                            <strong>Cantidad que quedó:</strong>
                            <span><?php echo htmlspecialchars($info_retiro['cantidad_restante']); ?> unidades</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($info_retiro['finalidad'])): ?>
                        <div style="margin-top: 10px;">
                            <strong>Finalidad:</strong>
                            <div class="finalidad-text">
                                <?php echo htmlspecialchars($info_retiro['finalidad']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 10px; font-size: 0.85em; color: #666;">
                        <strong>Registrado el:</strong> <?php echo date('d/m/Y H:i', strtotime($retiro['fecha_registro'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Mejorar la experiencia del dropdown
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

        // Auto-submit del formulario de filtros al cambiar los selects
        document.querySelectorAll('.filter-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>