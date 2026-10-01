<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'index';

// Obtener categorías y ubicaciones para los filtros
$categorias = $db->fetchAll("SELECT * FROM categorias ORDER BY nombre");
$ubicaciones = $db->fetchAll("SELECT * FROM ubicaciones ORDER BY nombre");

// Variables para los filtros
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir la consulta base
$where_conditions = [];
$params = [];

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

if (!empty($fecha_desde)) {
    $where_conditions[] = "i.fecha_adquisicion >= ?";
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where_conditions[] = "i.fecha_adquisicion <= ?";
    $params[] = $fecha_hasta;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Consulta principal para obtener los datos del reporte
$elementos = $db->fetchAll(
    "SELECT i.*, 
            c.nombre as categoria, 
            u.nombre as ubicacion, 
            us.nombre as usuario_registro
     FROM inventario i
     LEFT JOIN categorias c ON i.categoria_id = c.id
     LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id
     LEFT JOIN usuarios us ON i.usuario_registro = us.id
     $where_clause
     ORDER BY i.codigo ASC",
    $params
);

// Calcular estadísticas del reporte
$total_elementos = count($elementos);
$total_unidades = array_sum(array_column($elementos, 'cantidad_disponible'));
$valor_total = array_sum(array_filter(array_column($elementos, 'valor_compra')));

// Estadísticas por estado
$estadisticas_estado = [];
foreach ($elementos as $elemento) {
    $estado = $elemento['estado'];
    if (!isset($estadisticas_estado[$estado])) {
        $estadisticas_estado[$estado] = ['cantidad' => 0, 'unidades' => 0, 'valor' => 0];
    }
    $estadisticas_estado[$estado]['cantidad']++;
    $estadisticas_estado[$estado]['unidades'] += $elemento['cantidad_disponible'];
    $estadisticas_estado[$estado]['valor'] += $elemento['valor_compra'] ?? 0;
}

// Estadísticas por categoría
$estadisticas_categoria = [];
foreach ($elementos as $elemento) {
    $categoria = $elemento['categoria'] ?? 'Sin categoría';
    if (!isset($estadisticas_categoria[$categoria])) {
        $estadisticas_categoria[$categoria] = ['cantidad' => 0, 'unidades' => 0, 'valor' => 0];
    }
    $estadisticas_categoria[$categoria]['cantidad']++;
    $estadisticas_categoria[$categoria]['unidades'] += $elemento['cantidad_disponible'];
    $estadisticas_categoria[$categoria]['valor'] += $elemento['valor_compra'] ?? 0;
}

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Inventario - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .report-header {
            background: linear-gradient(135deg,rgb(18, 46, 173) 0%,rgb(18, 34, 179) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solidrgb(12, 37, 146);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 2rem;
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-weight: 500;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 20px 0;
        }
        
        .btn-excel {
            background:rgb(145, 14, 14);
            color: white;
        }
        
        .btn-excel:hover {
            background: #1e6339;
        }
        
        .btn-print {
            background:rgb(20, 52, 155);
        
        }
        
        .btn-print:hover {
            background:rgb(20, 52, 155);
        }
        
        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .container {
                max-width: none;
                margin: 0;
                padding: 0;
            }
            
            .report-header {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
            }
        }
        
        .category-stats, .status-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .mini-stat {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
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
            <a href="prestamos.php">Prestamos</a>
            <a href="proveedores.php">Proveedores</a>
            <a href="reportes.php" class="active">Reportes</a>
            
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
            <h1>Reporte de Inventario</h1>
            <p>Generado el <?php echo date('d/m/Y H:i:s'); ?> por <?php echo $_SESSION['user_name']; ?></p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?> no-print">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <!-- Sección de Filtros -->
        <div class="filters-section no-print">
            <h2>Filtros de Reporte</h2>
            <form method="GET" class="form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="categoria">Categoría</label>
                        <select id="categoria" name="categoria">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ubicacion">Ubicación</label>
                        <select id="ubicacion" name="ubicacion">
                            <option value="">Todas las ubicaciones</option>
                            <?php foreach ($ubicaciones as $ubi): ?>
                                <option value="<?php echo $ubi['id']; ?>" <?php echo $filtro_ubicacion == $ubi['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ubi['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo $filtro_estado == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="en_reparacion" <?php echo $filtro_estado == 'en_reparacion' ? 'selected' : ''; ?>>En Reparación</option>
                            <option value="dado_de_baja" <?php echo $filtro_estado == 'dado_de_baja' ? 'selected' : ''; ?>>Dado de Baja</option>
                            <option value="perdido" <?php echo $filtro_estado == 'perdido' ? 'selected' : ''; ?>>Perdido</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_desde">Fecha Desde</label>
                        <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_hasta">Fecha Hasta</label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    <a href="reportes.php" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
            </form>
        </div>

        <!-- Estadísticas Generales -->
        <div class="report-stats">
            <div class="stat-card">
                <h3><?php echo number_format($total_elementos); ?></h3>
                <p>Total de Elementos</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_unidades); ?></h3>
                <p>Total de Unidades</p>
            </div>
            <div class="stat-card">
                <h3>$<?php echo number_format($valor_total, 2); ?></h3>
                <p>Valor Total</p>
            </div>
        </div>

        <!-- Estadísticas por Estado -->
        <?php if (!empty($estadisticas_estado)): ?>
            <div class="filters-section">
                <h3>📈 Estadísticas por Estado</h3>
                <div class="status-stats">
                    <?php foreach ($estadisticas_estado as $estado => $stats): ?>
                        <div class="mini-stat">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $estado)); ?></strong><br>
                            Elementos: <?php echo $stats['cantidad']; ?><br>
                            Unidades: <?php echo $stats['unidades']; ?><br>
                            Valor: $<?php echo number_format($stats['valor'], 2); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Estadísticas por Categoría -->
        <?php if (!empty($estadisticas_categoria)): ?>
            <div class="filters-section">
                <h3>📊 Estadísticas por Categoría</h3>
                <div class="category-stats">
                    <?php foreach ($estadisticas_categoria as $categoria => $stats): ?>
                        <div class="mini-stat">
                            <strong><?php echo htmlspecialchars($categoria); ?></strong><br>
                            Elementos: <?php echo $stats['cantidad']; ?><br>
                            Unidades: <?php echo $stats['unidades']; ?><br>
                            Valor: $<?php echo number_format($stats['valor'], 2); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Botones de Acción -->
        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn btn-print">Imprimir Reporte</button>
            <a href="exportar_excel.php?<?php echo http_build_query($_GET); ?>" class="btn btn-excel">Descargar Excel</a>
                            <button onclick="exportarPDF()" class="btn btn-secondary">Exportar PDF</button>
        </div>

        <!-- Tabla de Datos -->
        <div class="table-responsive">
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
                        <th>Unidades</th>
                        <th>Valor Unitario</th>
                        <th>Valor Total</th>
                        <th>Fecha Adquisición</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($elementos)): ?>
                        <tr>
                            <td colspan="11" class="text-center">No se encontraron elementos con los filtros aplicados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($elementos as $elemento): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($elemento['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($elemento['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($elemento['categoria'] ?? 'Sin categoría'); ?></td>
                                <td><?php echo htmlspecialchars($elemento['marca']); ?></td>
                                <td><?php echo htmlspecialchars($elemento['modelo']); ?></td>
                                <td><?php echo htmlspecialchars($elemento['ubicacion'] ?? 'Sin ubicación'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $elemento['estado']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $elemento['estado'])); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($elemento['cantidad_disponible']); ?></td>
                                <td>
                                    <?php echo $elemento['valor_compra'] ? '$' . number_format($elemento['valor_compra'], 2) : '-'; ?>
                                </td>
                                <td>
                                    <?php 
                                    $valor_total_item = ($elemento['valor_compra'] ?? 0) * $elemento['cantidad_disponible'];
                                    echo $valor_total_item > 0 ? '$' . number_format($valor_total_item, 2) : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php echo $elemento['fecha_adquisicion'] ? date('d/m/Y', strtotime($elemento['fecha_adquisicion'])) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

        // Función para exportar PDF (requiere jsPDF)
        function exportarPDF() {
            alert('Funcionalidad de PDF en desarrollo. Por ahora puedes usar la opción de imprimir y seleccionar "Guardar como PDF".');
        }

        // Auto-submit del formulario al cambiar los filtros
        document.querySelectorAll('select, input[type="date"]').forEach(element => {
            element.addEventListener('change', function() {
                // Opcional: auto-submit del formulario
                // this.form.submit();
            });
        });
    </script>
</body>
</html>