<?php
require_once 'config.php';
requireLogin();

// Función para extraer información del retiro de la descripción
function extraerInfoRetiro($descripcion) {
    $info = [
        'cantidad' => '',
        'receptor' => '',
        'finalidad' => '',
        'cantidad_restante' => ''
    ];
    
    if (preg_match('/Retiro de (\d+) unidades/', $descripcion, $matches)) {
        $info['cantidad'] = $matches[1];
    }
    
    if (preg_match('/\) a ([^:]+) con finalidad:/', $descripcion, $matches)) {
        $info['receptor'] = trim($matches[1]);
    }
    
    if (preg_match('/con finalidad: ([^.]+)\./', $descripcion, $matches)) {
        $info['finalidad'] = trim($matches[1]);
    }
    
    if (preg_match('/Cantidad restante: (\d+)\./', $descripcion, $matches)) {
        $info['cantidad_restante'] = $matches[1];
    }
    
    return $info;
}

// Construir filtros para la consulta (misma lógica que historial_retiros.php)
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

// Consulta para obtener el historial de retiros
// Usamos LEFT JOIN para mostrar retiros incluso si el elemento fue eliminado del inventario
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

// Verificar si se solicita descarga de Excel
if (isset($_GET['formato']) && $_GET['formato'] === 'excel') {
    // Configurar headers para descarga de Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Reporte_Retiros_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Generar contenido Excel
    echo '<html xmlns="http://www.w3.org/1999/xhtml">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '.center { text-align: center; }';
    echo '.number { text-align: right; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    if (function_exists('getLogoReportesHtml')) echo getLogoReportesHtml();
    echo '<h2>Reporte de Historial de Retiros</h2>';
    echo '<p>Generado el: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p>Total de registros: ' . count($retiros) . '</p>';
    echo '<br>';
    
    if (!empty($retiros)) {
        echo '<table>';
        echo '<tr>';
        echo '<th>Fecha Retiro</th>';
        echo '<th>Código</th>';
        echo '<th>Elemento</th>';
        echo '<th>Marca</th>';
        echo '<th>Modelo</th>';
        echo '<th>Categoría</th>';
        echo '<th>Ubicación</th>';
        echo '<th>Cantidad Retirada</th>';
        echo '<th>Entregado a</th>';
        echo '<th>Finalidad</th>';
        echo '<th>Cantidad Restante</th>';
        echo '<th>Usuario Registro</th>';
        echo '<th>Fecha Registro</th>';
        echo '</tr>';
        
        foreach ($retiros as $retiro) {
            $info_retiro = extraerInfoRetiro($retiro['descripcion']);
            echo '<tr>';
            echo '<td class="center">' . date('d/m/Y', strtotime($retiro['fecha_evento'])) . '</td>';
            echo '<td>' . htmlspecialchars($retiro['codigo']) . '</td>';
            echo '<td>' . htmlspecialchars($retiro['elemento_nombre']) . '</td>';
            echo '<td>' . htmlspecialchars($retiro['marca']) . '</td>';
            echo '<td>' . htmlspecialchars($retiro['modelo']) . '</td>';
            echo '<td>' . htmlspecialchars($retiro['categoria'] ?? 'Sin categoría') . '</td>';
            echo '<td>' . htmlspecialchars($retiro['ubicacion'] ?? 'Sin ubicación') . '</td>';
            echo '<td class="number">' . htmlspecialchars($info_retiro['cantidad']) . '</td>';
            echo '<td>' . htmlspecialchars($info_retiro['receptor']) . '</td>';
            echo '<td>' . htmlspecialchars($info_retiro['finalidad']) . '</td>';
            echo '<td class="number">' . htmlspecialchars($info_retiro['cantidad_restante']) . '</td>';
            echo '<td>' . htmlspecialchars($retiro['usuario_registro']) . '</td>';
            echo '<td class="center">' . date('d/m/Y H:i', strtotime($retiro['fecha_registro'])) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No se encontraron registros para el reporte.</p>';
    }
    
    echo '</body>';
    echo '</html>';
    exit;
}

// Si no es descarga de Excel, mostrar página normal
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Retiros - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .report-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .report-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .report-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #666;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 0.85em;
        }
        
        .report-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .btn-print {
            background-color:rgb(8, 19, 180);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-excel {
            background-color:rgb(184, 15, 15);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back {
            background-color:rgb(34, 24, 172);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 20px;
            }
            
            .report-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            
            .report-table {
                font-size: 0.75em;
            }
            
            .report-table th,
            .report-table td {
                padding: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <nav class="navbar">
            <div class="nav-brand">
                <h2><?php echo SITE_NAME; ?></h2>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php">Inicio</a>
                <a href="inventario.php">Inventario</a>
                <a href="historial_retiros.php">Historial de Retiros</a>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle"><?php echo $_SESSION['user_name']; ?></a>
                    <div class="dropdown-menu">
                        <a href="perfil.php">Perfil</a>
                        <a href="cerrarSesion.php">Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn-print">Imprimir Reporte</button>
            <a href="generar_reporte_retiros.php?<?php echo http_build_query($_GET); ?>&formato=excel'; ?>" class="btn-excel">Descargar Excel</a>
            <a href="historial_retiros.php" class="btn-back">← Volver</a>
        </div>

        <div class="report-container">
            <div class="report-header">
                <h1>REPORTE DE HISTORIAL DE RETIROS</h1>
                <h3><?php echo SITE_NAME; ?></h3>
            </div>
            
            <div class="report-info">
                <div>
                    <strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?>
                </div>
                <div>
                    <strong>Total de registros:</strong> <?php echo count($retiros); ?>
                </div>
                <div>
                    <strong>Generado por:</strong> <?php echo $_SESSION['user_name']; ?>
                </div>
            </div>

            <?php if (!empty($retiros)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Fecha<br>Retiro</th>
                            <th>Código</th>
                            <th>Elemento</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Categoría</th>
                            <th>Ubicación</th>
                            <th>Cant.<br>Retirada</th>
                            <th>Entregado a</th>
                            <th>Finalidad</th>
                            <th>Cant.<br>Restante</th>
                            <th>Usuario<br>Registro</th>
                            <th>Fecha<br>Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($retiros as $retiro): 
                            $info_retiro = extraerInfoRetiro($retiro['descripcion']);
                        ?>
                            <tr>
                                <td class="text-center"><?php echo date('d/m/Y', strtotime($retiro['fecha_evento'])); ?></td>
                                <td><?php echo htmlspecialchars($retiro['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($retiro['elemento_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($retiro['marca']); ?></td>
                                <td><?php echo htmlspecialchars($retiro['modelo']); ?></td>
                                <td><?php echo htmlspecialchars($retiro['categoria'] ?? 'Sin categoría'); ?></td>
                                <td><?php echo htmlspecialchars($retiro['ubicacion'] ?? 'Sin ubicación'); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($info_retiro['cantidad']); ?></td>
                                <td><?php echo htmlspecialchars($info_retiro['receptor']); ?></td>
                                <td><?php echo htmlspecialchars($info_retiro['finalidad']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($info_retiro['cantidad_restante']); ?></td>
                                <td><?php echo htmlspecialchars($retiro['usuario_registro']); ?></td>
                                <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($retiro['fecha_registro'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <h3>No se encontraron registros</h3>
                    <p>No hay retiros que coincidan con los filtros aplicados.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Funcionalidad del dropdown
        document.querySelector('.dropdown-toggle')?.addEventListener('click', function(e) {
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