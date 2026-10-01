<?php
// IMPORTANTE: No incluir espacios o saltos de línea antes de esta línea
ini_set('display_errors', 0); // Desactivar errores para evitar interferencia
ini_set('display_startup_errors', 0);
error_reporting(0);

// Iniciar buffer de salida para capturar cualquier salida no deseada
ob_start();

require_once 'config.php';
requireLogin();

// Variables para los filtros (recibidas por GET)
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir la consulta con filtros
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

// Obtener datos
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

// Calcular estadísticas
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

// Estadísticas por ubicación
$estadisticas_ubicacion = [];
foreach ($elementos as $elemento) {
    $ubicacion = $elemento['ubicacion'] ?? 'Sin ubicación';
    if (!isset($estadisticas_ubicacion[$ubicacion])) {
        $estadisticas_ubicacion[$ubicacion] = ['cantidad' => 0, 'unidades' => 0, 'valor' => 0];
    }
    $estadisticas_ubicacion[$ubicacion]['cantidad']++;
    $estadisticas_ubicacion[$ubicacion]['unidades'] += $elemento['cantidad_disponible'];
    $estadisticas_ubicacion[$ubicacion]['valor'] += $elemento['valor_compra'] ?? 0;
}

// Limpiar el buffer antes de enviar headers
ob_clean();

// Nombre del archivo
$fecha_actual = date('Y-m-d_H-i-s');
$nombre_archivo = "Inventario_" . $fecha_actual . ".xls";

// Headers para descarga - IMPORTANTES: deben enviarse antes de cualquier salida
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
header("Pragma: public");

// Generar el contenido HTML del Excel
echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        
        .header {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            font-size: 16px;
        }
        
        .info-section {
            background-color: #D9E2F3;
            padding: 8px;
            margin: 5px 0;
        }
        
        .stats-table {
            border-collapse: collapse;
            width: 100%;
            margin: 10px 0;
        }
        
        .stats-table th {
            background-color: #70AD47;
            color: white;
            font-weight: bold;
            padding: 8px;
            border: 1px solid #000;
            text-align: center;
        }
        
        .stats-table td {
            padding: 6px;
            border: 1px solid #000;
            text-align: center;
            background-color: #E2EFDA;
        }
        
        .main-table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        
        .main-table th {
            background-color: #2F5597;
            color: white;
            font-weight: bold;
            padding: 10px;
            border: 1px solid #000;
            text-align: center;
        }
        
        .main-table td {
            padding: 8px;
            border: 1px solid #666;
            text-align: left;
        }
        
        .main-table tr:nth-child(even) {
            background-color: #F2F2F2;
        }
        
        .main-table tr:nth-child(odd) {
            background-color: #FFFFFF;
        }
        
        .estado-activo {
            background-color: #C6EFCE;
            color: #006100;
            font-weight: bold;
            text-align: center;
        }
        
        .estado-en_reparacion {
            background-color: #FFEB9C;
            color: #9C5700;
            font-weight: bold;
            text-align: center;
        }
        
        .estado-dado_de_baja {
            background-color: #FFC7CE;
            color: #9C0006;
            font-weight: bold;
            text-align: center;
        }
        
        .estado-perdido {
            background-color: #FFCCCC;
            color: #CC0000;
            font-weight: bold;
            text-align: center;
        }
        
        .number {
            text-align: right;
        }
        
        .currency {
            text-align: right;
            color: #0F7B0F;
            font-weight: bold;
        }
        
        .section-title {
            background-color: #44546A;
            color: white;
            font-weight: bold;
            padding: 8px;
            margin: 15px 0 5px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Encabezado principal -->
    <div class="header">
        REPORTE DE INVENTARIO - <?php echo defined('SITE_NAME') ? strtoupper(SITE_NAME) : 'SISTEMA DE INVENTARIO'; ?>
    </div>
    
    <!-- Información del reporte -->
    <div class="info-section">
        <strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
        <strong>Generado por:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?><br>
        <strong>Filtros aplicados:</strong> 
        <?php
        $filtros_aplicados = [];
        if (!empty($filtro_categoria)) {
            $cat_nombre = $db->fetch("SELECT nombre FROM categorias WHERE id = ?", [$filtro_categoria]);
            if ($cat_nombre) {
                $filtros_aplicados[] = "Categoría: " . $cat_nombre['nombre'];
            }
        }
        if (!empty($filtro_ubicacion)) {
            $ubi_nombre = $db->fetch("SELECT nombre FROM ubicaciones WHERE id = ?", [$filtro_ubicacion]);
            if ($ubi_nombre) {
                $filtros_aplicados[] = "Ubicación: " . $ubi_nombre['nombre'];
            }
        }
        if (!empty($filtro_estado)) {
            $filtros_aplicados[] = "Estado: " . ucfirst(str_replace('_', ' ', $filtro_estado));
        }
        if (!empty($fecha_desde)) {
            $filtros_aplicados[] = "Desde: " . date('d/m/Y', strtotime($fecha_desde));
        }
        if (!empty($fecha_hasta)) {
            $filtros_aplicados[] = "Hasta: " . date('d/m/Y', strtotime($fecha_hasta));
        }
        
        echo empty($filtros_aplicados) ? "Ninguno (Reporte completo)" : implode(", ", $filtros_aplicados);
        ?>
    </div>
    
    <!-- Estadísticas generales -->
    <div class="section-title">ESTADÍSTICAS POR ESTADO</div>
    <table class="stats-table">
        <tr>
            <th>Estado</th>
            <th>Cantidad de Elementos</th>
            <th>Total de Unidades</th>
            <th>Valor Total</th>
        </tr>
        <?php foreach ($estadisticas_estado as $estado => $stats): ?>
        <tr>
            <td><?php echo ucfirst(str_replace('_', ' ', $estado)); ?></td>
            <td class="number"><?php echo number_format($stats['cantidad']); ?></td>
            <td class="number"><?php echo number_format($stats['unidades']); ?></td>
            <td class="currency">$<?php echo number_format($stats['valor'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <!-- Estadísticas por categoría -->
    <?php if (!empty($estadisticas_categoria)): ?>
    <div class="section-title">ESTADÍSTICAS POR CATEGORÍA</div>
    <table class="stats-table">
        <tr>
            <th>Categoría</th>
            <th>Cantidad de Elementos</th>
            <th>Total de Unidades</th>
            <th>Valor Total</th>
        </tr>
        <?php foreach ($estadisticas_categoria as $categoria => $stats): ?>
        <tr>
            <td><?php echo htmlspecialchars($categoria); ?></td>
            <td class="number"><?php echo number_format($stats['cantidad']); ?></td>
            <td class="number"><?php echo number_format($stats['unidades']); ?></td>
            <td class="currency">$<?php echo number_format($stats['valor'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    
    <!-- Estadísticas por ubicación -->
    <?php if (!empty($estadisticas_ubicacion)): ?>
    <div class="section-title">ESTADÍSTICAS POR UBICACIÓN</div>
    <table class="stats-table">
        <tr>
            <th>Ubicación</th>
            <th>Cantidad de Elementos</th>
            <th>Total de Unidades</th>
            <th>Valor Total</th>
        </tr>
        <?php foreach ($estadisticas_ubicacion as $ubicacion => $stats): ?>
        <tr>
            <td><?php echo htmlspecialchars($ubicacion); ?></td>
            <td class="number"><?php echo number_format($stats['cantidad']); ?></td>
            <td class="number"><?php echo number_format($stats['unidades']); ?></td>
            <td class="currency">$<?php echo number_format($stats['valor'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    
    <!-- Detalle completo del inventario -->
    <div class="section-title">DETALLE COMPLETO DEL INVENTARIO</div>
    <?php if (!empty($elementos)): ?>
    <table class="main-table">
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Ubicación</th>
            <th>Estado</th>
            <th>Cantidad</th>
            <th>Valor Unitario</th>
            <th>Valor Total</th>
            <th>Fecha Adquisición</th>
            <th>Usuario Registro</th>
        </tr>
        <?php foreach ($elementos as $elemento): ?>
        <tr>
            <td><?php echo htmlspecialchars($elemento['codigo']); ?></td>
            <td><?php echo htmlspecialchars($elemento['nombre']); ?></td>
            <td><?php echo htmlspecialchars($elemento['descripcion'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($elemento['categoria'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($elemento['marca'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($elemento['modelo'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($elemento['ubicacion'] ?? '-'); ?></td>
            <td class="estado-<?php echo $elemento['estado']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $elemento['estado'])); ?>
            </td>
            <td class="number">
                <?php 
                $prestados = $db->fetch("SELECT COALESCE(SUM(cantidad_prestada),0) as total FROM prestamos WHERE elemento_id = ? AND estado = 'activo'", [$elemento['id']]);
                $cant_prestada = $prestados ? (int)$prestados['total'] : 0;
                echo number_format($elemento['cantidad_disponible']);
                if ($cant_prestada > 0) echo ' <span style="color:#888;font-size:0.9em;">(P. ' . $cant_prestada . ')</span>';
                ?>
            </td>
            <td class="currency">
                <?php echo $elemento['valor_compra'] ? '$' . number_format($elemento['valor_compra'], 2) : '-'; ?>
            </td>
            <td class="currency">
                <?php 
                if ($elemento['valor_compra']) {
                    $valor_total_item = $elemento['valor_compra'] * $elemento['cantidad_disponible'];
                    echo '$' . number_format($valor_total_item, 2);
                } else {
                    echo '-';
                }
                ?>
            </td>
            <td><?php echo $elemento['fecha_adquisicion'] ? date('d/m/Y', strtotime($elemento['fecha_adquisicion'])) : '-'; ?></td>
            <td><?php echo htmlspecialchars($elemento['usuario_registro'] ?? '-'); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="info-section">
        <strong>No se encontraron elementos que coincidan con los filtros aplicados.</strong>
    </div>
    <?php endif; ?>
    
    <!-- Pie del reporte -->
    <div class="info-section" style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
        <strong>Reporte generado automáticamente por <?php echo defined('SITE_NAME') ? SITE_NAME : 'Sistema de Inventario'; ?></strong><br>
        Este documento contiene información confidencial del inventario de la organización.
    </div>
</body>
</html>
<?php
// Finalizar y limpiar el buffer
ob_end_flush();
exit();
?>