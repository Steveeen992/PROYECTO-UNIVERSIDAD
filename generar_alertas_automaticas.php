<?php
require_once 'config.php';

// Este script puede ejecutarse manualmente o mediante cron job
// Para ejecutar automáticamente, agregar al crontab:
// */15 * * * * php /ruta/a/tu/proyecto/generar_alertas_automaticas.php

echo "Generando alertas automáticas...\n";

try {
    // 1. Generar alertas de stock bajo
    $elementos_stock_bajo = $db->fetchAll(
        "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion 
         FROM inventario i 
         LEFT JOIN categorias c ON i.categoria_id = c.id 
         LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id 
         WHERE i.cantidad_disponible <= ? AND i.estado = 'activo'
         AND NOT EXISTS (
             SELECT 1 FROM alertas a 
             WHERE a.elemento_id = i.id 
             AND a.tipo = 'stock_bajo' 
             AND a.fecha_creacion > DATE_SUB(NOW(), INTERVAL 1 DAY)
         )",
        [ALERT_STOCK_MINIMO]
    );
    
    foreach ($elementos_stock_bajo as $elemento) {
        $descripcion = "Stock bajo en {$elemento['nombre']} ({$elemento['codigo']}). Cantidad actual: {$elemento['cantidad_disponible']} unidades. Ubicación: {$elemento['ubicacion']}";
        
        $db->query(
            "INSERT INTO alertas (tipo, descripcion, elemento_id, usuario_registro, fecha_creacion, leida) 
             VALUES ('stock_bajo', ?, ?, ?, NOW(), 0)",
            [$descripcion, $elemento['id'], $elemento['usuario_registro']]
        );
        
        echo "Alerta de stock bajo generada para: {$elemento['nombre']}\n";
    }
    
    // 2. Generar alertas de préstamos vencidos
    $prestamos_vencidos = $db->fetchAll(
        "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
         FROM prestamos p 
         JOIN inventario i ON p.elemento_id = i.id 
         WHERE p.estado = 'activo' AND p.fecha_devolucion_esperada < CURDATE()
         AND NOT EXISTS (
             SELECT 1 FROM alertas a 
             WHERE a.elemento_id = p.elemento_id 
             AND a.tipo = 'prestamo_vencido' 
             AND a.fecha_creacion > DATE_SUB(NOW(), INTERVAL 1 DAY)
         )"
    );
    
    foreach ($prestamos_vencidos as $prestamo) {
        $dias_vencido = date_diff(date_create($prestamo['fecha_devolucion_esperada']), date_create())->days;
        $descripcion = "Préstamo vencido: {$prestamo['prestatario']} debe devolver {$prestamo['elemento_nombre']} ({$prestamo['elemento_codigo']}). Vencido hace {$dias_vencido} días.";
        
        $db->query(
            "INSERT INTO alertas (tipo, descripcion, elemento_id, usuario_registro, fecha_creacion, leida) 
             VALUES ('prestamo_vencido', ?, ?, ?, NOW(), 0)",
            [$descripcion, $prestamo['elemento_id'], $prestamo['usuario_registro']]
        );
        
        echo "Alerta de préstamo vencido generada para: {$prestamo['elemento_nombre']}\n";
    }
    
    // 3. Generar alertas de préstamos por vencer
    $prestamos_por_vencer = $db->fetchAll(
        "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
         FROM prestamos p 
         JOIN inventario i ON p.elemento_id = i.id 
         WHERE p.estado = 'activo' 
         AND p.fecha_devolucion_esperada >= CURDATE() 
         AND p.fecha_devolucion_esperada <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         AND NOT EXISTS (
             SELECT 1 FROM alertas a 
             WHERE a.elemento_id = p.elemento_id 
             AND a.tipo = 'prestamo_por_vencer' 
             AND a.fecha_creacion > DATE_SUB(NOW(), INTERVAL 1 DAY)
         )",
        [ALERT_DIAS_VENCIMIENTO]
    );
    
    foreach ($prestamos_por_vencer as $prestamo) {
        $dias_restantes = date_diff(date_create(), date_create($prestamo['fecha_devolucion_esperada']))->days;
        $descripcion = "Préstamo por vencer: {$prestamo['prestatario']} debe devolver {$prestamo['elemento_nombre']} ({$prestamo['elemento_codigo']}) en {$dias_restantes} días.";
        
        $db->query(
            "INSERT INTO alertas (tipo, descripcion, elemento_id, usuario_registro, fecha_creacion, leida) 
             VALUES ('prestamo_por_vencer', ?, ?, ?, NOW(), 0)",
            [$descripcion, $prestamo['elemento_id'], $prestamo['usuario_registro']]
        );
        
        echo "Alerta de préstamo por vencer generada para: {$prestamo['elemento_nombre']}\n";
    }
    
    // 4. Enviar notificaciones por email si está habilitado
    if (ALERT_EMAIL_ENABLED) {
        $usuarios_con_email = $db->fetchAll(
            "SELECT u.*, ca.email_notificaciones 
             FROM usuarios u 
             LEFT JOIN configuracion_alertas ca ON u.id = ca.usuario_id 
             WHERE ca.email_notificaciones = 1 AND u.email IS NOT NULL"
        );
        
        foreach ($usuarios_con_email as $usuario) {
            $alertas_usuario = getAlertasNoLeidas($db, 10);
            
            if (!empty($alertas_usuario)) {
                $asunto = "Alertas del Sistema de Inventario - " . date('d/m/Y H:i');
                $mensaje = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .alerta { background: #f8f9fa; padding: 10px; margin: 10px 0; border-left: 4px solid #dc3545; }
                        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class='header'>
                        <h2>Sistema de Alertas</h2>
                        <p>Hola {$usuario['nombre']}, tienes nuevas alertas</p>
                    </div>
                    <div style='padding: 20px;'>
                        <h3>Alertas recientes:</h3>";
                
                foreach ($alertas_usuario as $alerta) {
                    $mensaje .= "
                        <div class='alerta'>
                            <strong>" . ucfirst(str_replace('_', ' ', $alerta['tipo'])) . "</strong><br>
                            " . htmlspecialchars($alerta['descripcion']) . "<br>
                            <small>Fecha: " . date('d/m/Y H:i', strtotime($alerta['fecha_creacion'])) . "</small>
                        </div>";
                }
                
                $mensaje .= "
                        <p><a href='" . SITE_URL . "alertas.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ver Todas las Alertas</a></p>
                    </div>
                </body>
                </html>";
                
                if (enviarEmailAlerta($usuario['email'], $asunto, $mensaje)) {
                    echo "Email enviado a: {$usuario['email']}\n";
                } else {
                    echo "Error enviando email a: {$usuario['email']}\n";
                }
            }
        }
    }
    
    // 5. Limpiar alertas antiguas (más de 30 días)
    $db->query("DELETE FROM alertas WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    echo "Generación de alertas completada.\n";
    echo "Resumen:\n";
    echo "   - Stock bajo: " . count($elementos_stock_bajo) . " alertas\n";
    echo "   - Préstamos vencidos: " . count($prestamos_vencidos) . " alertas\n";
    echo "   - Préstamos por vencer: " . count($prestamos_por_vencer) . " alertas\n";
    
} catch (Exception $e) {
    echo "Error generando alertas: " . $e->getMessage() . "\n";
    error_log("Error en generar_alertas_automaticas.php: " . $e->getMessage());
}
?> 