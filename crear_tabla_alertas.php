<?php
require_once 'config.php';

echo "<h1>Creando tabla de alertas</h1>";

try {
    // Crear tabla de alertas
    $db->query("
        CREATE TABLE IF NOT EXISTS alertas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('stock_bajo', 'prestamo_vencido', 'prestamo_por_vencer', 'mantenimiento', 'general') NOT NULL,
            descripcion TEXT NOT NULL,
            elemento_id INT NULL,
            usuario_registro INT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            leida BOOLEAN DEFAULT FALSE,
            fecha_leida TIMESTAMP NULL,
            INDEX idx_tipo (tipo),
            INDEX idx_leida (leida),
            INDEX idx_fecha_creacion (fecha_creacion),
            FOREIGN KEY (elemento_id) REFERENCES inventario(id) ON DELETE SET NULL,
            FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p style='color: green;'>✅ Tabla de alertas creada correctamente</p>";
    
    // Crear tabla de configuración de alertas por usuario
    $db->query("
        CREATE TABLE IF NOT EXISTS configuracion_alertas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            alerta_stock_bajo BOOLEAN DEFAULT TRUE,
            alerta_prestamos_vencidos BOOLEAN DEFAULT TRUE,
            alerta_prestamos_por_vencer BOOLEAN DEFAULT TRUE,
            alerta_mantenimiento BOOLEAN DEFAULT TRUE,
            email_notificaciones BOOLEAN DEFAULT TRUE,
            dashboard_notificaciones BOOLEAN DEFAULT TRUE,
            stock_minimo INT DEFAULT 5,
            dias_vencimiento INT DEFAULT 3,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_usuario (usuario_id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p style='color: green;'>✅ Tabla de configuración de alertas creada correctamente</p>";
    
    // Insertar configuraciones por defecto para usuarios existentes
    $usuarios = $db->fetchAll("SELECT id FROM usuarios");
    foreach ($usuarios as $usuario) {
        try {
            $db->query(
                "INSERT INTO configuracion_alertas (usuario_id) VALUES (?)",
                [$usuario['id']]
            );
        } catch (Exception $e) {
            // Si ya existe, no hacer nada
        }
    }
    
    echo "<p style='color: green;'>✅ Configuraciones de alertas inicializadas</p>";
    
    // Crear algunos triggers para alertas automáticas
    $db->query("
        CREATE TRIGGER IF NOT EXISTS trigger_stock_bajo_after_update
        AFTER UPDATE ON inventario
        FOR EACH ROW
        BEGIN
            IF NEW.cantidad_disponible <= OLD.cantidad_disponible AND NEW.cantidad_disponible <= 5 THEN
                INSERT INTO alertas (tipo, descripcion, elemento_id, usuario_registro)
                VALUES ('stock_bajo', CONCAT('Stock bajo en ', NEW.nombre, ' (', NEW.codigo, '). Cantidad actual: ', NEW.cantidad_disponible), NEW.id, NEW.usuario_registro);
            END IF;
        END
    ");
    
    echo "<p style='color: green;'>✅ Trigger de stock bajo creado</p>";
    
    // Crear trigger para préstamos vencidos
    $db->query("
        CREATE TRIGGER IF NOT EXISTS trigger_prestamo_vencido_after_insert
        AFTER INSERT ON prestamos
        FOR EACH ROW
        BEGIN
            IF NEW.fecha_devolucion_esperada < CURDATE() THEN
                INSERT INTO alertas (tipo, descripcion, elemento_id, usuario_registro)
                VALUES ('prestamo_vencido', CONCAT('Préstamo vencido: ', NEW.prestatario, ' debe devolver ', NEW.elemento_id), NEW.elemento_id, NEW.usuario_registro);
            END IF;
        END
    ");
    
    echo "<p style='color: green;'>✅ Trigger de préstamos vencidos creado</p>";
    
    echo "<h2>🎉 Sistema de alertas configurado correctamente</h2>";
    echo "<p>El sistema ahora incluye:</p>";
    echo "<ul>";
    echo "<li>✅ Alertas de stock bajo</li>";
    echo "<li>✅ Alertas de préstamos vencidos</li>";
    echo "<li>✅ Alertas de préstamos por vencer</li>";
    echo "<li>✅ Configuración personalizada por usuario</li>";
    echo "<li>✅ Notificaciones por email</li>";
    echo "<li>✅ Dashboard de alertas</li>";
    echo "</ul>";
    
    echo "<br><a href='alertas.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ver Alertas</a>";
    echo " <a href='dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 