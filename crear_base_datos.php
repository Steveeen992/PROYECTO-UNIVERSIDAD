<?php
// Script para crear la base de datos completa desde cero
require_once 'config.php';

echo "<h1>🗄️ Creando Base de Datos Completa</h1>";

try {
    // 1. Conectar al servidor MySQL sin especificar base de datos
    echo "<h2>1. Conectando al servidor MySQL...</h2>";
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "<p style='color: green;'>✅ Conexión al servidor MySQL exitosa</p>";
    
    // 2. Crear la base de datos si no existe
    echo "<h2>2. Creando base de datos...</h2>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ Base de datos '" . DB_NAME . "' creada/verificada</p>";
    
    // 3. Conectar a la base de datos específica
    $pdo_db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // 4. Crear tabla usuarios
    echo "<h2>3. Creando tabla usuarios...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            rol ENUM('administrador', 'editor', 'trabajador') DEFAULT 'trabajador',
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultimo_acceso TIMESTAMP NULL,
            activo BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla usuarios creada</p>";
    
    // 5. Crear tabla categorias
    echo "<h2>4. Creando tabla categorias...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            usuario_registro INT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla categorias creada</p>";
    
    // 6. Crear tabla ubicaciones
    echo "<h2>5. Creando tabla ubicaciones...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS ubicaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            usuario_registro INT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla ubicaciones creada</p>";
    
    // 7. Crear tabla proveedores
    echo "<h2>6. Creando tabla proveedores...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS proveedores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL UNIQUE,
            contacto VARCHAR(255),
            telefono VARCHAR(50),
            email VARCHAR(255),
            direccion TEXT,
            descripcion TEXT,
            usuario_registro INT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla proveedores creada</p>";
    
    // 8. Crear tabla inventario
    echo "<h2>7. Creando tabla inventario...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS inventario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(100) NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            categoria_id INT,
            ubicacion_id INT,
            proveedor_id INT,
            cantidad_disponible INT DEFAULT 0,
            cantidad_total INT DEFAULT 0,
            valor_compra DECIMAL(10,2) DEFAULT 0.00,
            fecha_adquisicion DATE,
            estado ENUM('activo', 'en_reparacion', 'dado_de_baja') DEFAULT 'activo',
            observaciones TEXT,
            usuario_registro INT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
            FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id) ON DELETE SET NULL,
            FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL,
            FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_codigo (codigo),
            INDEX idx_categoria (categoria_id),
            INDEX idx_ubicacion (ubicacion_id),
            INDEX idx_estado (estado),
            INDEX idx_usuario (usuario_registro)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla inventario creada</p>";
    
    // 9. Crear tabla prestamos
    echo "<h2>8. Creando tabla prestamos...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS prestamos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            elemento_id INT NOT NULL,
            cantidad_prestada INT DEFAULT 1,
            prestatario VARCHAR(255) NOT NULL,
            contacto_prestatario VARCHAR(255),
            fecha_prestamo DATE NOT NULL,
            fecha_devolucion_esperada DATE NOT NULL,
            fecha_devolucion_real DATE NULL,
            proposito TEXT,
            observaciones TEXT,
            estado ENUM('activo', 'devuelto_completo', 'devuelto_parcial', 'perdido') DEFAULT 'activo',
            usuario_registro INT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (elemento_id) REFERENCES inventario(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_elemento (elemento_id),
            INDEX idx_prestatario (prestatario),
            INDEX idx_estado (estado),
            INDEX idx_fecha_prestamo (fecha_prestamo),
            INDEX idx_usuario (usuario_registro)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla prestamos creada</p>";
    
    // 9b. Crear tabla documentos_prestamo (documento de entrega con firmas digitales)
    echo "<h2>8b. Creando tabla documentos_prestamo...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS documentos_prestamo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prestamo_id INT NOT NULL UNIQUE,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            firmado_receptor TINYINT(1) DEFAULT 0,
            fecha_firma_receptor DATETIME NULL,
            nombre_receptor VARCHAR(255) NULL,
            firma_receptor_imagen LONGTEXT NULL,
            firmado_autorizador TINYINT(1) DEFAULT 0,
            fecha_firma_autorizador DATETIME NULL,
            usuario_autorizador_id INT NULL,
            firma_autorizador_imagen LONGTEXT NULL,
            INDEX idx_prestamo (prestamo_id),
            INDEX idx_fecha_creacion (fecha_creacion),
            FOREIGN KEY (prestamo_id) REFERENCES prestamos(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_autorizador_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla documentos_prestamo creada</p>";
    
    // 10. Crear tabla historial_elementos
    echo "<h2>9. Creando tabla historial_elementos...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS historial_elementos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            elemento_id INT NOT NULL,
            tipo_evento ENUM('creacion', 'actualizacion', 'eliminacion', 'prestamo', 'devolucion', 'reparacion', 'baja') NOT NULL,
            descripcion TEXT NOT NULL,
            datos_anteriores JSON NULL,
            datos_nuevos JSON NULL,
            usuario_registro INT,
            fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (elemento_id) REFERENCES inventario(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_elemento (elemento_id),
            INDEX idx_tipo_evento (tipo_evento),
            INDEX idx_fecha_evento (fecha_evento),
            INDEX idx_usuario (usuario_registro)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla historial_elementos creada</p>";
    
    // 11. Crear tabla alertas
    echo "<h2>10. Creando tabla alertas...</h2>";
    $pdo_db->exec("
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
    echo "<p style='color: green;'>✅ Tabla alertas creada</p>";
    
    // 11b. Crear tabla log_actividad_usuario (historial de navegación por usuario)
    echo "<h2>10b. Creando tabla log_actividad_usuario...</h2>";
    $pdo_db->exec("
        CREATE TABLE IF NOT EXISTS log_actividad_usuario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            accion VARCHAR(50) NOT NULL,
            descripcion TEXT,
            pagina VARCHAR(255) DEFAULT NULL,
            fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id),
            INDEX idx_fecha (fecha),
            INDEX idx_accion (accion),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ Tabla log_actividad_usuario creada</p>";
    
    // 12. Crear tabla configuracion_alertas
    echo "<h2>11. Creando tabla configuracion_alertas...</h2>";
    $pdo_db->exec("
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
    echo "<p style='color: green;'>✅ Tabla configuracion_alertas creada</p>";
    
    // 13. Crear usuario administrador por defecto
    echo "<h2>12. Creando usuario administrador...</h2>";
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo_db->exec("
        INSERT IGNORE INTO usuarios (nombre, email, password, rol) 
        VALUES ('Administrador', 'admin@inventario.com', '$admin_password', 'administrador')
    ");
    echo "<p style='color: green;'>✅ Usuario administrador creado</p>";
    echo "<p><strong>Email:</strong> admin@inventario.com</p>";
    echo "<p><strong>Contraseña:</strong> admin123</p>";
    
    // 14. Crear archivo de funciones helper
    echo "<h2>13. Creando funciones helper...</h2>";
    $helper_content = '<?php
// Funciones helper para inventario por usuario
function getUserInventario($db, $user_id) {
    return $db->fetchAll(
        "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion, p.nombre as proveedor
         FROM inventario i
         LEFT JOIN categorias c ON i.categoria_id = c.id AND c.usuario_registro = ?
         LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id AND u.usuario_registro = ?
         LEFT JOIN proveedores p ON i.proveedor_id = p.id AND p.usuario_registro = ?
         WHERE i.usuario_registro = ?
         ORDER BY i.fecha_registro DESC",
        [$user_id, $user_id, $user_id, $user_id]
    );
}

function getUserCategorias($db, $user_id) {
    return $db->fetchAll(
        "SELECT * FROM categorias WHERE usuario_registro = ? ORDER BY nombre",
        [$user_id]
    );
}

function getUserUbicaciones($db, $user_id) {
    return $db->fetchAll(
        "SELECT * FROM ubicaciones WHERE usuario_registro = ? ORDER BY nombre",
        [$user_id]
    );
}

function getUserProveedores($db, $user_id) {
    return $db->fetchAll(
        "SELECT * FROM proveedores WHERE usuario_registro = ? ORDER BY nombre",
        [$user_id]
    );
}

function getUserPrestamos($db, $user_id) {
    return $db->fetchAll(
        "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
         FROM prestamos p
         JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ?
         WHERE p.usuario_registro = ?
         ORDER BY p.fecha_prestamo DESC",
        [$user_id, $user_id]
    );
}

function getUserHistorial($db, $user_id) {
    return $db->fetchAll(
        "SELECT h.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
         FROM historial_elementos h
         JOIN inventario i ON h.elemento_id = i.id AND i.usuario_registro = ?
         WHERE h.usuario_registro = ?
         ORDER BY h.fecha_evento DESC",
        [$user_id, $user_id]
    );
}

function getUserStats($db, $user_id) {
    return [
        "total_elementos" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ?", [$user_id])["total"],
        "activos" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = \"activo\"", [$user_id])["total"],
        "en_reparacion" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = \"en_reparacion\"", [$user_id])["total"],
        "dados_baja" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = \"dado_de_baja\"", [$user_id])["total"]
    ];
}

function canUserAccessElement($db, $user_id, $element_id) {
    $element = $db->fetch(
        "SELECT id FROM inventario WHERE id = ? AND usuario_registro = ?",
        [$element_id, $user_id]
    );
    return $element !== false;
}

function canUserAccessCategory($db, $user_id, $category_id) {
    $category = $db->fetch(
        "SELECT id FROM categorias WHERE id = ? AND usuario_registro = ?",
        [$category_id, $user_id]
    );
    return $category !== false;
}

function canUserAccessLocation($db, $user_id, $location_id) {
    $location = $db->fetch(
        "SELECT id FROM ubicaciones WHERE id = ? AND usuario_registro = ?",
        [$location_id, $user_id]
    );
    return $location !== false;
}

function canUserAccessProvider($db, $user_id, $provider_id) {
    $provider = $db->fetch(
        "SELECT id FROM proveedores WHERE id = ? AND usuario_registro = ?",
        [$provider_id, $user_id]
    );
    return $provider !== false;
}

function canUserAccessPrestamo($db, $user_id, $prestamo_id) {
    $prestamo = $db->fetch(
        "SELECT id FROM prestamos WHERE id = ? AND usuario_registro = ?",
        [$prestamo_id, $user_id]
    );
    return $prestamo !== false;
}
';
    
    file_put_contents('user_helpers.php', $helper_content);
    echo "<p style='color: green;'>✅ Archivo user_helpers.php creado</p>";
    
    // 15. Verificar todas las tablas
    echo "<h2>14. Verificando estructura final...</h2>";
    $tables = $pdo_db->query("SHOW TABLES")->fetchAll();
    echo "<p><strong>Tablas creadas:</strong></p>";
    echo "<ul>";
    foreach ($tables as $table) {
        $table_name = array_values($table)[0];
        echo "<li>✅ " . $table_name . "</li>";
    }
    echo "</ul>";
    
    echo "<h2>🎉 ¡Base de datos creada exitosamente!</h2>";
    echo "<p>La base de datos está lista para usar. Puedes:</p>";
    echo "<ul>";
    echo "<li><a href='login.php'>Ir al login</a></li>";
    echo "<li><a href='register.php'>Registrar un nuevo usuario</a></li>";
    echo "<li><a href='dashboard.php'>Ir al dashboard</a></li>";
    echo "</ul>";
    
    echo "<h3>📋 Información de acceso:</h3>";
    echo "<p><strong>Usuario administrador:</strong></p>";
    echo "<ul>";
    echo "<li>Email: admin@inventario.com</li>";
    echo "<li>Contraseña: admin123</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<h3>Posibles soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verificar que MySQL esté ejecutándose</li>";
    echo "<li>Verificar las credenciales en config.php</li>";
    echo "<li>Verificar que el usuario tenga permisos de CREATE DATABASE</li>";
    echo "</ul>";
}
?> 