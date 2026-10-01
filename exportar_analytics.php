<?php
require_once 'config.php';
requireLogin();

$tipo_reporte = $_GET['tipo'] ?? 'general';

// Función para generar CSV
function generateCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM para Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($data)) {
        // Headers
        fputcsv($output, array_keys($data[0]));
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

switch ($tipo_reporte) {
    case 'inventario':
        $data = $db->fetchAll(
            "SELECT i.id, i.codigo, i.nombre, i.descripcion, i.cantidad_disponible, i.valor_compra, 
                    (i.valor_compra * i.cantidad_disponible) as valor_total,
                    (SELECT COALESCE(SUM(cantidad_prestada), 0) FROM prestamos WHERE elemento_id = i.id AND estado = 'activo' AND usuario_registro = ?) as cantidad_prestada_activa,
                    c.nombre as categoria, u.nombre as ubicacion, p.nombre as proveedor,
                    i.fecha_registro
             FROM inventario i
             LEFT JOIN categorias c ON i.categoria_id = c.id AND c.usuario_registro = ?
             LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id AND u.usuario_registro = ?
             LEFT JOIN proveedores p ON i.proveedor_id = p.id AND p.usuario_registro = ?
             WHERE i.usuario_registro = ?
             ORDER BY i.nombre",
            [$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]
        );
        
        // Formatear datos (P. = préstamos activos junto a la cantidad)
        foreach ($data as &$row) {
            $p = (int)($row['cantidad_prestada_activa'] ?? 0);
            $row['cantidad_disponible'] = $row['cantidad_disponible'] . ($p > 0 ? ' (P. ' . $p . ')' : '');
            unset($row['id'], $row['cantidad_prestada_activa']);
            $row['valor_compra'] = '$' . number_format($row['valor_compra'], 2);
            $row['valor_total'] = '$' . number_format($row['valor_total'], 2);
            $row['fecha_registro'] = date('d/m/Y', strtotime($row['fecha_registro']));
        }
        
        generateCSV($data, 'reporte_inventario_' . date('Y-m-d_H-i-s'));
        break;
        
    case 'prestamos':
        $data = $db->fetchAll(
            "SELECT i.codigo, i.nombre as producto, p.prestatario, p.contacto_prestatario,
                    p.cantidad_prestada, p.fecha_prestamo, p.fecha_devolucion_esperada,
                    p.fecha_devolucion_real, p.proposito, p.estado, p.observaciones
             FROM prestamos p
             JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ?
             WHERE p.usuario_registro = ?
             ORDER BY p.fecha_prestamo DESC",
            [$_SESSION['user_id'], $_SESSION['user_id']]
        );
        
        // Formatear datos
        foreach ($data as &$row) {
            $row['fecha_prestamo'] = date('d/m/Y', strtotime($row['fecha_prestamo']));
            $row['fecha_devolucion_esperada'] = date('d/m/Y', strtotime($row['fecha_devolucion_esperada']));
            if ($row['fecha_devolucion_real']) {
                $row['fecha_devolucion_real'] = date('d/m/Y', strtotime($row['fecha_devolucion_real']));
            }
        }
        
        generateCSV($data, 'reporte_prestamos_' . date('Y-m-d_H-i-s'));
        break;
        
    case 'stock_bajo':
        $data = $db->fetchAll(
            "SELECT codigo, nombre, cantidad_disponible, valor_compra, 
                    (valor_compra * cantidad_disponible) as valor_total
             FROM inventario 
             WHERE cantidad_disponible <= 5 AND usuario_registro = ?
             ORDER BY cantidad_disponible ASC",
            [$_SESSION['user_id']]
        );
        
        // Formatear datos
        foreach ($data as &$row) {
            $row['valor_compra'] = '$' . number_format($row['valor_compra'], 2);
            $row['valor_total'] = '$' . number_format($row['valor_total'], 2);
        }
        
        generateCSV($data, 'reporte_stock_bajo_' . date('Y-m-d_H-i-s'));
        break;
        
    case 'estadisticas':
        // Estadísticas generales
        $stats = [
            'total_productos' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
            'valor_total' => $db->fetch("SELECT COALESCE(SUM(valor_compra * cantidad_disponible), 0) as total FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['total'],
            'productos_stock_bajo' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE cantidad_disponible <= 5 AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
            'prestamos_activos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
            'prestamos_vencidos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND fecha_devolucion_esperada < CURDATE() AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
            'total_categorias' => $db->fetch("SELECT COUNT(*) as count FROM categorias WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
            'total_ubicaciones' => $db->fetch("SELECT COUNT(*) as count FROM ubicaciones WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
            'total_proveedores' => $db->fetch("SELECT COUNT(*) as count FROM proveedores WHERE usuario_registro = ?", [$_SESSION['user_id']])['count']
        ];
        
        $data = [
            ['Metrica', 'Valor'],
            ['Total de Productos', $stats['total_productos']],
            ['Valor Total del Inventario', '$' . number_format($stats['valor_total'], 2)],
            ['Productos con Stock Bajo', $stats['productos_stock_bajo']],
            ['Préstamos Activos', $stats['prestamos_activos']],
            ['Préstamos Vencidos', $stats['prestamos_vencidos']],
            ['Total de Categorías', $stats['total_categorias']],
            ['Total de Ubicaciones', $stats['total_ubicaciones']],
            ['Total de Proveedores', $stats['total_proveedores']]
        ];
        
        generateCSV($data, 'estadisticas_generales_' . date('Y-m-d_H-i-s'));
        break;
        
    default:
        // Reporte general con múltiples hojas
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_completo_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Sección 1: Resumen
        fputcsv($output, ['REPORTE COMPLETO DE INVENTARIO']);
        fputcsv($output, ['Generado el: ' . date('d/m/Y H:i:s')]);
        fputcsv($output, ['Usuario: ' . $_SESSION['user_name']]);
        fputcsv($output, []);
        
        // Estadísticas
        $stats = [
            'total_productos' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
            'valor_total' => $db->fetch("SELECT COALESCE(SUM(valor_compra * cantidad_disponible), 0) as total FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['total'],
            'productos_stock_bajo' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE cantidad_disponible <= 5 AND usuario_registro = ?", [$_SESSION['user_id']])['count']
        ];
        
        fputcsv($output, ['ESTADÍSTICAS GENERALES']);
        fputcsv($output, ['Total de Productos', $stats['total_productos']]);
        fputcsv($output, ['Valor Total', '$' . number_format($stats['valor_total'], 2)]);
        fputcsv($output, ['Productos con Stock Bajo', $stats['productos_stock_bajo']]);
        fputcsv($output, []);
        
        // Sección 2: Productos con stock bajo
        fputcsv($output, ['PRODUCTOS CON STOCK BAJO']);
        fputcsv($output, ['Código', 'Nombre', 'Stock Actual', 'Precio', 'Valor Total']);
        
        $stock_bajo = $db->fetchAll(
            "SELECT codigo, nombre, cantidad_disponible, valor_compra, (valor_compra * cantidad_disponible) as valor_total
             FROM inventario 
             WHERE cantidad_disponible <= 5 AND usuario_registro = ?
             ORDER BY cantidad_disponible ASC",
            [$_SESSION['user_id']]
        );
        
        foreach ($stock_bajo as $producto) {
            fputcsv($output, [
                $producto['codigo'],
                $producto['nombre'],
                $producto['cantidad_disponible'],
                '$' . number_format($producto['valor_compra'], 2),
                '$' . number_format($producto['valor_total'], 2)
            ]);
        }
        
        fputcsv($output, []);
        
        // Sección 3: Préstamos recientes
        fputcsv($output, ['PRÉSTAMOS RECIENTES']);
        fputcsv($output, ['Producto', 'Prestatario', 'Fecha Préstamo', 'Fecha Devolución', 'Estado']);
        
        $prestamos = $db->fetchAll(
            "SELECT i.nombre as producto, p.prestatario, p.fecha_prestamo, p.fecha_devolucion_esperada, p.estado
             FROM prestamos p
             JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ?
             WHERE p.usuario_registro = ?
             ORDER BY p.fecha_prestamo DESC
             LIMIT 20",
            [$_SESSION['user_id'], $_SESSION['user_id']]
        );
        
        foreach ($prestamos as $prestamo) {
            fputcsv($output, [
                $prestamo['producto'],
                $prestamo['prestatario'],
                date('d/m/Y', strtotime($prestamo['fecha_prestamo'])),
                date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])),
                $prestamo['estado']
            ]);
        }
        
        fclose($output);
        exit;
        break;
}

// Si llegamos aquí, redirigir a análisis
redirect('analytics.php');
?> 