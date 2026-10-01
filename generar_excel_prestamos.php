<?php
require_once 'config.php';
requireLogin();

// Obtener parámetros de filtro (los mismos que en el reporte)
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$estado = $_GET['estado'] ?? '';
$prestatario = $_GET['prestatario'] ?? '';
$elemento = $_GET['elemento'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

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

// Obtener datos
$prestamos = $db->fetchAll(
    "SELECT p.*, 
            i.nombre as elemento_nombre, 
            i.codigo as elemento_codigo,
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

// Calcular estadísticas
$total_prestamos = count($prestamos);
$prestamos_activos = count(array_filter($prestamos, fn($p) => $p['estado'] === 'activo'));
$prestamos_vencidos = count(array_filter($prestamos, fn($p) => $p['estado'] === 'activo' && $p['fecha_devolucion_esperada'] < date('Y-m-d')));
$prestamos_devueltos = count(array_filter($prestamos, fn($p) => in_array($p['estado'], ['devuelto_completo', 'devuelto_parcial'])));
$prestamos_perdidos = count(array_filter($prestamos, fn($p) => $p['estado'] === 'perdido'));

// Configurar headers para descarga de Excel
$filename = 'Reporte_Prestamos_' . date('Y-m-d_H-i-s') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Comenzar el contenido del archivo Excel
echo "\xEF\xBB\xBF"; // BOM para UTF-8
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        .header {
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            background-color: #4CAF50;
            color: white;
        }
        .subheader {
            font-weight: bold;
            font-size: 12px;
            background-color: #f2f2f2;
        }
        .stats {
            background-color: #e8f5e8;
            font-weight: bold;
        }
        .data {
            font-size: 10px;
        }
        .center {
            text-align: center;
        }
        .number {
            text-align: right;
        }
    </style>
</head>
<body>
    <table border="1">
        <!-- Encabezado del reporte -->
        <tr>
            <td colspan="15" class="header">REPORTE DE PRÉSTAMOS - <?php echo strtoupper(SITE_NAME); ?></td>
        </tr>
        <tr>
            <td colspan="15" class="center">Generado el: <?php echo date('d/m/Y H:i:s'); ?> por <?php echo $_SESSION['user_name']; ?></td>
        </tr>
        <tr>
            <td colspan="15"></td>
        </tr>
        
        <!-- Información de filtros -->
        <tr>
            <td colspan="15" class="subheader">FILTROS APLICADOS:</td>
        </tr>
        <tr>
            <td><strong>Fecha Inicio:</strong></td>
            <td><?php echo !empty($fecha_inicio) ? date('d/m/Y', strtotime($fecha_inicio)) : 'No especificada'; ?></td>
            <td><strong>Fecha Fin:</strong></td>
            <td><?php echo !empty($fecha_fin) ? date('d/m/Y', strtotime($fecha_fin)) : 'No especificada'; ?></td>
            <td><strong>Estado:</strong></td>
            <td><?php echo !empty($estado) ? ucfirst($estado) : 'Todos'; ?></td>
            <td><strong>Prestatario:</strong></td>
            <td><?php echo !empty($prestatario) ? $prestatario : 'Todos'; ?></td>
            <td><strong>Elemento:</strong></td>
            <td><?php echo !empty($elemento) ? $elemento : 'Todos'; ?></td>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td colspan="15"></td>
        </tr>
        
        <!-- Estadísticas resumen -->
        <tr>
            <td colspan="15" class="subheader">RESUMEN ESTADÍSTICO:</td>
        </tr>
        <tr class="stats">
            <td><strong>Total Préstamos:</strong></td>
            <td class="number"><?php echo $total_prestamos; ?></td>
            <td><strong>Activos:</strong></td>
            <td class="number"><?php echo $prestamos_activos; ?></td>
            <td><strong>Vencidos:</strong></td>
            <td class="number"><?php echo $prestamos_vencidos; ?></td>
            <td><strong>Devueltos:</strong></td>
            <td class="number"><?php echo $prestamos_devueltos; ?></td>
            <td><strong>Perdidos:</strong></td>
            <td class="number"><?php echo $prestamos_perdidos; ?></td>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td colspan="15"></td>
        </tr>
        
        <!-- Encabezados de la tabla de datos -->
        <tr class="subheader">
            <td><strong>ID</strong></td>
            <td><strong>Código Elemento</strong></td>
            <td><strong>Nombre Elemento</strong></td>
            <td><strong>Categoría</strong></td>
            <td><strong>Cantidad</strong></td>
            <td><strong>Prestatario</strong></td>
            <td><strong>Contacto</strong></td>
            <td><strong>Fecha Préstamo</strong></td>
            <td><strong>Fecha Esperada</strong></td>
            <td><strong>Fecha Real</strong></td>
            <td><strong>Días Préstamo</strong></td>
            <td><strong>Estado</strong></td>
            <td><strong>Propósito</strong></td>
            <td><strong>Observaciones</strong></td>
            <td><strong>Registrado por</strong></td>
        </tr>
        
        <!-- Datos de los préstamos -->
        <?php if (empty($prestamos)): ?>
            <tr>
                <td colspan="15" class="center">No se encontraron préstamos con los filtros aplicados</td>
            </tr>
        <?php else: ?>
            <?php foreach ($prestamos as $prestamo): ?>
                <tr class="data">
                    <td><?php echo $prestamo['id']; ?></td>
                    <td><?php echo htmlspecialchars($prestamo['elemento_codigo']); ?></td>
                    <td><?php echo htmlspecialchars($prestamo['elemento_nombre']); ?></td>
                    <td><?php echo htmlspecialchars($prestamo['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                    <td class="number"><?php echo $prestamo['cantidad_prestada']; ?></td>
                    <td><?php echo htmlspecialchars($prestamo['prestatario']); ?></td>
                    <td><?php echo htmlspecialchars($prestamo['contacto_prestatario']); ?></td>
                    <td class="center"><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                    <td class="center"><?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])); ?></td>
                    <td class="center"><?php echo $prestamo['fecha_devolucion_real'] ? date('d/m/Y', strtotime($prestamo['fecha_devolucion_real'])) : '-'; ?></td>
                    <td class="number"><?php echo $prestamo['dias_prestamo']; ?></td>
                    <td><?php echo $prestamo['estado_display']; ?></td>
                    <td><?php echo htmlspecialchars($prestamo['proposito']); ?></td>
                    <td><?php echo htmlspecialchars($prestamo['observaciones']); ?></td>
                    <td><?php echo htmlspecialchars($prestamo['usuario_registro_nombre']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Pie del reporte -->
        <tr>
            <td colspan="15"></td>
        </tr>
        <tr>
            <td colspan="15" class="center"><small>Reporte generado por el Sistema de Gestión de Inventario - <?php echo SITE_NAME; ?></small></td>
        </tr>
    </table>
</body>
</html>