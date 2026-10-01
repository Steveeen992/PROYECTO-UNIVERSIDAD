<?php
// Archivo de prueba para diagnosticar problemas de base de datos
require_once __DIR__ . '/config.php';
echo "<h1>Diagnóstico de Base de Datos</h1>";

// 1. Verificar configuración
echo "<h2>1. Configuración de Base de Datos</h2>";
echo "<p><strong>Host:</strong> " . (defined('DB_HOST') ? DB_HOST : 'No definido') . "</p>";
echo "<p><strong>Base de datos:</strong> " . (defined('DB_NAME') ? DB_NAME : 'No definido') . "</p>";
echo "<p><strong>Usuario:</strong> " . (defined('DB_USER') ? DB_USER : 'No definido') . "</p>";
echo "<p><strong>Contraseña:</strong> " . (defined('DB_PASS') ? (DB_PASS ? 'Configurada' : 'Vacía') : 'No definida') . "</p>";

// 2. Probar conexión sin base de datos específica
echo "<h2>2. Prueba de Conexión al Servidor MySQL</h2>";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "<p style='color: green;'>Conexión al servidor MySQL exitosa</p>";
    
    // 3. Verificar si la base de datos existe
    echo "<h2>3. Verificar Base de Datos</h2>";
    $databases = $pdo->query("SHOW DATABASES")->fetchAll();
    $db_exists = false;
    
    echo "<p><strong>Bases de datos disponibles:</strong></p>";
    echo "<ul>";
    foreach ($databases as $db) {
        echo "<li>" . $db['Database'] . "</li>";
        if ($db['Database'] === DB_NAME) {
            $db_exists = true;
        }
    }
    echo "</ul>";
    
    if ($db_exists) {
        echo "<p style='color: green;'>La base de datos '" . DB_NAME . "' existe</p>";
        
        // 4. Probar conexión a la base de datos específica
        echo "<h2>4. Prueba de Conexión a la Base de Datos</h2>";
        try {
            $pdo_db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            echo "<p style='color: green;'>Conexión a la base de datos '" . DB_NAME . "' exitosa</p>";
            
            // 5. Verificar tablas
            echo "<h2>5. Verificar Tablas</h2>";
            $tables = $pdo_db->query("SHOW TABLES")->fetchAll();
            
            if (empty($tables)) {
                echo "<p style='color: orange;'>La base de datos está vacía (no hay tablas)</p>";
            } else {
                echo "<p><strong>Tablas encontradas:</strong></p>";
                echo "<ul>";
                foreach ($tables as $table) {
                    $table_name = array_values($table)[0];
                    echo "<li>" . $table_name . "</li>";
                }
                echo "</ul>";
            }
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error conectando a la base de datos: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>La base de datos '" . DB_NAME . "' NO existe</p>";
        echo "<p>Necesitas crear la base de datos primero.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error de conexión al servidor MySQL: " . $e->getMessage() . "</p>";
    echo "<h3>Posibles soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verificar que MySQL esté ejecutándose</li>";
    echo "<li>Verificar el host, usuario y contraseña en config.php</li>";
    echo "<li>Verificar que el usuario tenga permisos de conexión</li>";
    echo "</ul>";
}

// 6. Información del sistema
echo "<h2>6. Información del Sistema</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Extensiones PHP:</strong></p>";
echo "<ul>";
echo "<li>PDO: " . (extension_loaded('pdo') ? 'Instalada' : 'No instalada') . "</li>";
echo "<li>PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'Instalada' : 'No instalada') . "</li>";
echo "<li>MySQL: " . (extension_loaded('mysqli') ? 'Instalada' : 'No instalada') . "</li>";
echo "</ul>";

echo "<hr>";
echo "<h2>Próximos Pasos</h2>";
echo "<p>Si la base de datos no existe, puedes:</p>";
echo "<ol>";
echo "<li><a href='crear_base_datos.php'>Crear la base de datos y tablas</a></li>";
echo "<li>Usar phpMyAdmin para crear la base de datos manualmente</li>";
echo "<li>Importar un archivo .sql si tienes uno</li>";
echo "</ol>";
?> 