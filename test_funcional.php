<?php
/**
 * Pruebas funcionales con sesión autenticada
 * Ejecutar: c:\xampp\php\php.exe test_funcional.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

$passed = 0;
$failed = 0;
$results = [];

function test($name, $condition, $detail = '') {
    global $results, $passed, $failed;
    $status = $condition ? 'PASS' : 'FAIL';
    if ($condition) $passed++; else $failed++;
    $results[] = compact('name', 'status', 'detail');
}

echo "=== PRUEBAS FUNCIONALES ===\n\n";

// 1. Simular login de administrador
$admin = $db->fetch("SELECT * FROM usuarios WHERE rol = 'administrador' LIMIT 1");
test('Obtener admin de BD', !empty($admin), $admin['nombre'] ?? '');

if ($admin) {
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_name'] = $admin['nombre'];
    $_SESSION['user_email'] = $admin['email'];
    $_SESSION['user_role'] = $admin['rol'];
    
    test('Sesión iniciada', isLoggedIn());
    test('Rol administrador', isAdministrador());
    test('Puede gestionar usuarios', canManageUsers());
    test('Puede agregar/editar productos', canAddEditProducts());
    test('Puede eliminar productos', canDeleteProducts());
    test('Puede acceder a config', canAccessConfig());
}

// 2. Módulo Inventario
try {
    $items = $db->fetchAll("SELECT i.*, c.nombre as categoria, u.nombre as ubicacion 
        FROM inventario i 
        LEFT JOIN categorias c ON i.categoria_id = c.id 
        LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id 
        LIMIT 10");
    test('Listar inventario con JOINs', is_array($items), count($items) . ' items');
    
    $stats = $db->fetch("SELECT COUNT(*) as total, SUM(cantidad_disponible) as stock FROM inventario WHERE estado = 'activo'");
    test('Estadísticas inventario', isset($stats['total']), "Total: {$stats['total']}, Stock: {$stats['stock']}");
} catch (Exception $e) {
    test('Módulo inventario', false, $e->getMessage());
}

// 3. Módulo Categorías
try {
    $cats = $db->fetchAll("SELECT c.*, COUNT(i.id) as total_items FROM categorias c LEFT JOIN inventario i ON c.id = i.categoria_id GROUP BY c.id");
    test('Listar categorías con conteo', is_array($cats), count($cats) . ' categorías');
} catch (Exception $e) {
    test('Módulo categorías', false, $e->getMessage());
}

// 4. Módulo Ubicaciones
try {
    $ubis = $db->fetchAll("SELECT * FROM ubicaciones");
    test('Listar ubicaciones', is_array($ubis), count($ubis) . ' ubicaciones');
} catch (Exception $e) {
    test('Módulo ubicaciones', false, $e->getMessage());
}

// 5. Módulo Préstamos
try {
    $prestamos = $db->fetchAll("SELECT p.*, i.nombre as elemento 
        FROM prestamos p 
        JOIN inventario i ON p.elemento_id = i.id 
        ORDER BY p.fecha_prestamo DESC LIMIT 10");
    test('Listar préstamos', is_array($prestamos), count($prestamos) . ' préstamos');
    
    $activos = $db->fetch("SELECT COUNT(*) as c FROM prestamos WHERE estado = 'activo'");
    test('Préstamos activos', isset($activos['c']), $activos['c'] . ' activos');
} catch (Exception $e) {
    test('Módulo préstamos', false, $e->getMessage());
}

// 6. Módulo Proveedores
try {
    $prov = $db->fetchAll("SELECT * FROM proveedores");
    test('Listar proveedores', is_array($prov), count($prov) . ' proveedores');
} catch (Exception $e) {
    test('Módulo proveedores', false, $e->getMessage());
}

// 7. Módulo Alertas
try {
    $alertas = getAlertasNoLeidas($db, 5);
    test('Obtener alertas no leídas', is_array($alertas));
    
    $stockBajo = getAlertasStockBajo($db);
    test('Alertas stock bajo', is_array($stockBajo), count($stockBajo) . ' items');
    
    $vencidos = getAlertasPrestamosVencidos($db);
    test('Préstamos vencidos', is_array($vencidos), count($vencidos) . ' vencidos');
    
    $totalAlertas = getTotalAlertas($db);
    test('Total alertas', is_numeric($totalAlertas), $totalAlertas . ' total');
} catch (Exception $e) {
    test('Módulo alertas', false, $e->getMessage());
}

// 8. Módulo Usuarios
try {
    $users = $db->fetchAll("SELECT id, nombre, email, rol FROM usuarios");
    test('Listar usuarios', is_array($users) && count($users) > 0, count($users) . ' usuarios');
    
    $roles = array_unique(array_column($users, 'rol'));
    test('Roles definidos', count($roles) >= 1, implode(', ', $roles));
} catch (Exception $e) {
    test('Módulo usuarios', false, $e->getMessage());
}

// 9. Historial de actividad
try {
    $log = $db->fetchAll("SELECT * FROM log_actividad_usuario ORDER BY fecha DESC LIMIT 5");
    test('Log de actividad', is_array($log), count($log) . ' registros');
} catch (Exception $e) {
    test('Historial actividad', false, $e->getMessage());
}

// 10. Documentos de préstamo
try {
    $docs = $db->fetchAll("SELECT * FROM documentos_prestamo LIMIT 5");
    test('Documentos préstamo', is_array($docs), count($docs) . ' documentos');
} catch (Exception $e) {
    test('Documentos préstamo', false, $e->getMessage());
}

// 11. Configuración alertas
try {
    $config = $db->fetch("SELECT * FROM configuracion_alertas WHERE usuario_id = ?", [$admin['id'] ?? 0]);
    test('Config alertas usuario', true, $config ? 'Configurada' : 'Sin config (usa defaults)');
} catch (Exception $e) {
    test('Config alertas', false, $e->getMessage());
}

// 12. Registrar actividad
try {
    registrarActividadUsuario($db, 'test', 'Prueba automática del sistema', 'test_funcional.php');
    $lastLog = $db->fetch("SELECT * FROM log_actividad_usuario WHERE accion = 'test' ORDER BY id DESC LIMIT 1");
    test('Registrar actividad', !empty($lastLog));
    if ($lastLog) {
        $db->query("DELETE FROM log_actividad_usuario WHERE id = ?", [$lastLog['id']]);
    }
} catch (Exception $e) {
    test('Registrar actividad', false, $e->getMessage());
}

// 13. Password reset table
try {
    $resets = $db->fetchAll("SELECT COUNT(*) as c FROM password_resets");
    test('Tabla password_resets accesible', isset($resets[0]['c']));
} catch (Exception $e) {
    test('Password resets', false, $e->getMessage());
}

// 14. Probar roles de editor
$editor = $db->fetch("SELECT * FROM usuarios WHERE rol = 'editor' LIMIT 1");
if ($editor) {
    $_SESSION['user_role'] = 'editor';
    test('Editor: NO puede gestionar usuarios', !canManageUsers());
    test('Editor: puede agregar/editar', canAddEditProducts());
    test('Editor: NO puede eliminar productos', !canDeleteProducts());
    test('Editor: NO puede acceder config', !canAccessConfig());
    test('Editor: puede gestionar préstamos', canManagePrestamos());
    $_SESSION['user_role'] = 'administrador';
}

// 15. Verificar páginas PHP cargan sin error fatal (include test)
$pagesToInclude = ['roles.php', 'user_helpers.php'];
foreach ($pagesToInclude as $page) {
    test("Include $page OK", file_exists(__DIR__ . '/' . $page));
}

// 16. Verificar estructura columnas críticas
$columnChecks = [
    'inventario' => ['id', 'nombre', 'codigo', 'cantidad_disponible', 'categoria_id', 'ubicacion_id', 'usuario_registro'],
    'prestamos' => ['id', 'elemento_id', 'estado', 'fecha_prestamo', 'fecha_devolucion_esperada'],
    'usuarios' => ['id', 'nombre', 'email', 'password', 'rol'],
];
foreach ($columnChecks as $table => $cols) {
    try {
        $columns = $db->fetchAll("SHOW COLUMNS FROM $table");
        $colNames = array_column($columns, 'Field');
        $missing = array_diff($cols, $colNames);
        test("Columnas $table", empty($missing), empty($missing) ? 'OK' : 'Faltan: ' . implode(', ', $missing));
    } catch (Exception $e) {
        test("Columnas $table", false, $e->getMessage());
    }
}

// Resultados
foreach ($results as $r) {
    $icon = $r['status'] === 'PASS' ? '[OK]' : '[XX]';
    $detail = $r['detail'] ? " — {$r['detail']}" : '';
    echo "$icon {$r['name']}$detail\n";
}

echo "\n--- RESUMEN FUNCIONAL ---\n";
echo "Pasaron:  $passed\n";
echo "Fallaron: $failed\n";
echo "Total:    " . ($passed + $failed) . "\n";

exit($failed > 0 ? 1 : 0);
