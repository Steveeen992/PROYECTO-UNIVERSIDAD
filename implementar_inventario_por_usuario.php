<?php
require_once 'config.php';

echo "<h1>🔐 Implementando Inventario Único por Usuario</h1>";

try {
    // 1. Verificar que las tablas principales tengan la columna usuario_registro
    $tables_to_check = ['inventario', 'categorias', 'ubicaciones', 'proveedores', 'prestamos', 'historial_elementos'];
    
    foreach ($tables_to_check as $table) {
        $columns = $db->fetchAll("SHOW COLUMNS FROM $table");
        $has_usuario_registro = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'usuario_registro') {
                $has_usuario_registro = true;
                break;
            }
        }
        
        if (!$has_usuario_registro) {
            echo "<p style='color: orange;'>Agregando columna usuario_registro a tabla $table...</p>";
            $db->query("ALTER TABLE $table ADD COLUMN usuario_registro INT NULL");
            $db->query("ALTER TABLE $table ADD INDEX idx_usuario_registro (usuario_registro)");
            
            // Agregar foreign key si es posible
            try {
                $db->query("ALTER TABLE $table ADD CONSTRAINT fk_{$table}_usuario 
                           FOREIGN KEY (usuario_registro) REFERENCES usuarios(id) 
                           ON DELETE SET NULL ON UPDATE CASCADE");
                echo "<p style='color: green;'>Foreign key agregado a $table</p>";
            } catch (Exception $e) {
                echo "<p style='color: orange;'>No se pudo agregar foreign key a $table: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>Tabla $table ya tiene columna usuario_registro</p>";
        }
    }
    
    // 2. Crear función para obtener inventario del usuario actual
    echo "<h2>Configurando funciones de filtrado por usuario</h2>";
    
    // 3. Actualizar registros existentes (asignar al primer usuario si no tienen usuario_registro)
    $users = $db->fetchAll("SELECT id FROM usuarios ORDER BY id LIMIT 1");
    if (!empty($users)) {
        $default_user_id = $users[0]['id'];
        
        // Actualizar inventario sin usuario_registro
        $db->query("UPDATE inventario SET usuario_registro = ? WHERE usuario_registro IS NULL", [$default_user_id]);
        echo "<p style='color: green;'>Inventario existente asignado al usuario por defecto</p>";
        
        // Actualizar categorías sin usuario_registro
        $db->query("UPDATE categorias SET usuario_registro = ? WHERE usuario_registro IS NULL", [$default_user_id]);
        echo "<p style='color: green;'>Categorías existentes asignadas al usuario por defecto</p>";
        
        // Actualizar ubicaciones sin usuario_registro
        $db->query("UPDATE ubicaciones SET usuario_registro = ? WHERE usuario_registro IS NULL", [$default_user_id]);
        echo "<p style='color: green;'>Ubicaciones existentes asignadas al usuario por defecto</p>";
        
        // Actualizar proveedores sin usuario_registro
        $db->query("UPDATE proveedores SET usuario_registro = ? WHERE usuario_registro IS NULL", [$default_user_id]);
        echo "<p style='color: green;'>Proveedores existentes asignados al usuario por defecto</p>";
    }
    
    // 4. Crear índices para mejor rendimiento
    echo "<h2>⚡ Optimizando rendimiento</h2>";
    
    $db->query("CREATE INDEX IF NOT EXISTS idx_inventario_usuario ON inventario(usuario_registro)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_categorias_usuario ON categorias(usuario_registro)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_ubicaciones_usuario ON ubicaciones(usuario_registro)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_proveedores_usuario ON proveedores(usuario_registro)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_prestamos_usuario ON prestamos(usuario_registro)");
    $db->query("CREATE INDEX IF NOT EXISTS idx_historial_usuario ON historial_elementos(usuario_registro)");
    
    echo "<p style='color: green;'>Índices creados para optimizar consultas por usuario</p>";
    
    // 5. Crear función helper para obtener datos del usuario actual
    echo "<h2>📝 Creando funciones helper</h2>";
    
    // Crear archivo de funciones helper
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
        "SELECT p.id FROM prestamos p 
         JOIN inventario i ON p.elemento_id = i.id 
         WHERE p.id = ? AND p.usuario_registro = ? AND i.usuario_registro = ?",
        [$prestamo_id, $user_id, $user_id]
    );
    return $prestamo !== false;
}
?>';
    
    file_put_contents('user_helpers.php', $helper_content);
    echo "<p style='color: green;'>Archivo user_helpers.php creado con funciones helper</p>";
    
    echo "<h2>Sistema de Inventario por Usuario Implementado</h2>";
    echo "<p>El sistema ahora incluye:</p>";
    echo "<ul>";
    echo "<li>Inventario único por usuario</li>";
    echo "<li>Categorías privadas por usuario</li>";
    echo "<li>Ubicaciones privadas por usuario</li>";
    echo "<li>Proveedores privados por usuario</li>";
    echo "<li>Préstamos privados por usuario</li>";
    echo "<li>Historial privado por usuario</li>";
    echo "<li>Estadísticas privadas por usuario</li>";
    echo "<li>Validaciones de acceso</li>";
    echo "<li>Optimización de rendimiento</li>";
    echo "</ul>";
    
    echo "<h3>Próximos pasos:</h3>";
    echo "<ol>";
    echo "<li>Incluir 'user_helpers.php' en los archivos principales</li>";
    echo "<li>Modificar las consultas para usar las funciones helper</li>";
    echo "<li>Agregar validaciones de acceso en todas las páginas</li>";
    echo "<li>Probar el sistema con múltiples usuarios</li>";
    echo "</ol>";
    
    echo "<br><a href='dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a>";
    echo " <a href='inventario.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ver Inventario</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 