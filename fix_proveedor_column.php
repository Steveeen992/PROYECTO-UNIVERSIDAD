<?php
require_once 'config.php';

echo "<h1>Verificando estructura de la base de datos</h1>";

try {
    // Verificar si la columna proveedor_id existe en la tabla inventario
    $columns = $db->fetchAll("SHOW COLUMNS FROM inventario");
    $has_proveedor_id = false;
    
    echo "<h2>Columnas actuales en la tabla inventario:</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column['Field']} - {$column['Type']}</li>";
        if ($column['Field'] === 'proveedor_id') {
            $has_proveedor_id = true;
        }
    }
    echo "</ul>";
    
    if (!$has_proveedor_id) {
        echo "<h2>Agregando columna proveedor_id...</h2>";
        
        // Agregar la columna proveedor_id
        $db->query("ALTER TABLE inventario ADD COLUMN proveedor_id INT NULL");
        
        // Agregar índice para mejor rendimiento
        $db->query("ALTER TABLE inventario ADD INDEX idx_proveedor_id (proveedor_id)");
        
        // Agregar foreign key si es posible
        try {
            $db->query("ALTER TABLE inventario ADD CONSTRAINT fk_inventario_proveedor 
                       FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) 
                       ON DELETE SET NULL ON UPDATE CASCADE");
            echo "<p style='color: green;'>Foreign key agregado correctamente</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>No se pudo agregar foreign key: " . $e->getMessage() . "</p>";
        }
        
        echo "<p style='color: green;'>Columna proveedor_id agregada correctamente</p>";
    } else {
        echo "<p style='color: green;'>La columna proveedor_id ya existe</p>";
    }
    
    // Verificar si la tabla proveedores existe
    $tables = $db->fetchAll("SHOW TABLES LIKE 'proveedores'");
    if (empty($tables)) {
        echo "<h2>Creando tabla proveedores...</h2>";
        
        $db->query("CREATE TABLE proveedores (
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
        )");
        
        echo "<p style='color: green;'>Tabla proveedores creada correctamente</p>";
    } else {
        echo "<p style='color: green;'>La tabla proveedores ya existe</p>";
    }
    
    echo "<h2>Estructura final:</h2>";
    echo "<p>Tabla inventario con columna proveedor_id</p>";
    echo "<p>Tabla proveedores disponible</p>";
    echo "<p>Relación entre inventario y proveedores establecida</p>";
    
    echo "<br><a href='proveedores.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir a Proveedores</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 