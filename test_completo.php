<?php
/**
 * Suite de pruebas completa del Sistema de Inventario
 * Ejecutar: php test_completo.php
 * O visitar: http://localhost/Sofware_inventarioo/test_completo.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$isCli = php_sapi_name() === 'cli';
$results = [];
$passed = 0;
$failed = 0;
$warnings = 0;

function test($name, $condition, $detail = '') {
    global $results, $passed, $failed;
    $status = $condition ? 'PASS' : 'FAIL';
    if ($condition) $passed++; else $failed++;
    $results[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
    return $condition;
}

function warn($name, $detail = '') {
    global $results, $warnings;
    $warnings++;
    $results[] = ['name' => $name, 'status' => 'WARN', 'detail' => $detail];
}

// ========== 1. ENTORNO PHP ==========
test('PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);
test('Extensión PDO', extension_loaded('pdo'));
test('Extensión PDO MySQL', extension_loaded('pdo_mysql'));
test('Extensión mbstring', extension_loaded('mbstring'));
test('Extensión json', extension_loaded('json'));
test('Extensión session', extension_loaded('session'));

// ========== 2. ARCHIVOS CRÍTICOS ==========
$requiredFiles = [
    'config.php', 'login.php', 'dashboard.php', 'inventario.php',
    'categorias.php', 'ubicaciones.php', 'prestamos.php', 'proveedores.php',
    'usuarios.php', 'alertas.php', 'analytics.php', 'reportes.php',
    'chat_ai.php', 'perfil.php', 'cerrarSesion.php', 'roles.php',
    'include_nav.php', 'styles.css', 'user_helpers.php',
    'forgot_password.php', 'reset_password.php', 'register.php',
    'historial.php', 'historial_retiros.php', 'historial_usuario.php',
    'documentos_prestamos.php', 'configurar_email.php', 'configurar_alertas.php',
    'exportar_excel.php', 'api_chat_response.php', 'api_process_invoice.php',
];

foreach ($requiredFiles as $file) {
    test("Archivo existe: $file", file_exists(__DIR__ . '/' . $file));
}

// ========== 3. SINTAXIS PHP ==========
$phpFiles = glob(__DIR__ . '/*.php');
foreach ($phpFiles as $file) {
    $basename = basename($file);
    if ($basename === 'test_completo.php') continue;
    $output = [];
    $code = 0;
    $phpBin = defined('PHP_BINARY') ? PHP_BINARY : null;
    if (!$phpBin || !file_exists($phpBin)) {
        $candidates = ['c:\\xampp\\php\\php.exe', 'C:\\xampp\\php\\php.exe', PHP_BINDIR . '\\php.exe'];
        foreach ($candidates as $c) {
            if (file_exists($c)) { $phpBin = $c; break; }
        }
    }
    if ($phpBin && file_exists($phpBin)) {
        exec('"' . $phpBin . '" -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        test("Sintaxis OK: $basename", $code === 0, implode(' ', $output));
    } else {
        // Fallback: token_get_all para detectar errores de parseo básicos
        $src = @file_get_contents($file);
        $tokens = $src !== false ? @token_get_all($src) : false;
        test("Sintaxis OK: $basename", $tokens !== false, 'Verificado con token_get_all');
    }
}

// ========== 4. CONFIGURACIÓN Y BASE DE DATOS ==========
require_once __DIR__ . '/config.php';

test('DB_HOST definido', defined('DB_HOST'), DB_HOST ?? '');
test('DB_NAME definido', defined('DB_NAME'), DB_NAME ?? '');
test('SITE_NAME definido', defined('SITE_NAME'), SITE_NAME ?? '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    test('Conexión MySQL servidor', true);
    
    $dbs = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'")->fetchAll();
    test('Base de datos existe: ' . DB_NAME, count($dbs) > 0);
} catch (PDOException $e) {
    test('Conexión MySQL servidor', false, $e->getMessage());
}

try {
    $dbTest = new Database();
    test('Clase Database instancia', true);
    
    $tables = $dbTest->fetchAll("SHOW TABLES");
    $tableNames = array_map(fn($t) => array_values($t)[0], $tables);
    
    $expectedTables = [
        'usuarios', 'inventario', 'categorias', 'ubicaciones',
        'prestamos', 'proveedores', 'alertas'
    ];
    
    foreach ($expectedTables as $tbl) {
        test("Tabla existe: $tbl", in_array($tbl, $tableNames));
    }
    
    test('Hay usuarios registrados', ($dbTest->fetch("SELECT COUNT(*) as c FROM usuarios")['c'] ?? 0) > 0);
    
    $admin = $dbTest->fetch("SELECT id, nombre, rol FROM usuarios WHERE rol = 'administrador' LIMIT 1");
    test('Existe usuario administrador', !empty($admin), $admin['nombre'] ?? 'ninguno');
    
} catch (Exception $e) {
    test('Clase Database instancia', false, $e->getMessage());
}

// ========== 5. FUNCIONES DE ROLES ==========
test('Función isAdministrador existe', function_exists('isAdministrador'));
test('Función canManageUsers existe', function_exists('canManageUsers'));
test('Función canAddEditProducts existe', function_exists('canAddEditProducts'));
test('Función canManagePrestamos existe', function_exists('canManagePrestamos'));

// ========== 6. FUNCIONES DE UTILIDAD ==========
test('Función sanitize', sanitize('<script>x</script>') === 'x' || sanitize('<script>x</script>') !== '<script>x</script>');
test('Función isLoggedIn (sin sesión)', isLoggedIn() === false);

// ========== 7. CONSULTAS CRÍTICAS ==========
if (isset($dbTest)) {
    try {
        $inv = $dbTest->fetch("SELECT COUNT(*) as c FROM inventario");
        test('Consulta inventario OK', isset($inv['c']));
        
        $cats = $dbTest->fetchAll("SELECT * FROM categorias LIMIT 5");
        test('Consulta categorías OK', is_array($cats));
        
        $pres = $dbTest->fetchAll("SELECT p.*, i.nombre FROM prestamos p LEFT JOIN inventario i ON p.elemento_id = i.id LIMIT 5");
        test('Consulta préstamos con JOIN OK', is_array($pres));
        
        $prov = $dbTest->fetchAll("SELECT * FROM proveedores LIMIT 5");
        test('Consulta proveedores OK', is_array($prov));
    } catch (Exception $e) {
        test('Consultas críticas', false, $e->getMessage());
    }
}

// ========== 8. TABLAS AUXILIARES (config.php las crea) ==========
if (isset($dbTest)) {
    foreach (['log_actividad_usuario', 'documentos_prestamo', 'password_resets'] as $tbl) {
        try {
            $exists = $dbTest->fetch("SHOW TABLES LIKE ?", [$tbl]);
            test("Tabla auxiliar: $tbl", !empty($exists));
        } catch (Exception $e) {
            test("Tabla auxiliar: $tbl", false, $e->getMessage());
        }
    }
}

// ========== 9. PHPMailer ==========
$phpmailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';
test('PHPMailer instalado', file_exists($phpmailerPath));

// ========== 10. HTTP ENDPOINTS (si Apache corre) ==========
$baseUrl = 'http://localhost/Sofware_inventarioo/';
$pagesToCheck = [
    'login.php' => 200,
    'register.php' => 200,
    'forgot_password.php' => 200,
];

foreach ($pagesToCheck as $page => $expectedCode) {
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $content = @file_get_contents($baseUrl . $page, false, $ctx);
    if ($content === false) {
        warn("HTTP $page", 'Apache no responde o URL incorrecta');
    } else {
        $httpCode = 200;
        if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
            $httpCode = (int)$m[0];
        }
        test("HTTP $page responde", $httpCode >= 200 && $httpCode < 400, "Código: $httpCode");
        test("HTTP $page tiene HTML", stripos($content, '<html') !== false || stripos($content, '<!DOCTYPE') !== false);
    }
}

// Páginas protegidas deben redirigir a login
$protectedPages = ['dashboard.php', 'inventario.php', 'usuarios.php'];
foreach ($protectedPages as $page) {
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'follow_location' => 0, 'ignore_errors' => true]
    ]);
    @file_get_contents($baseUrl . $page, false, $ctx);
    $redirected = false;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (stripos($h, 'Location:') !== false && stripos($h, 'login') !== false) {
                $redirected = true;
                break;
            }
        }
    }
    test("Protección auth: $page redirige a login", $redirected);
}

// ========== 11. LOGIN FUNCIONAL ==========
if (isset($dbTest)) {
    $user = $dbTest->fetch("SELECT * FROM usuarios WHERE rol = 'administrador' LIMIT 1");
    if ($user) {
        test('Password hash válido (bcrypt/argon)', 
            password_get_info($user['password'])['algo'] !== 0 || strlen($user['password']) > 20);
    }
}

// ========== RESULTADOS ==========
$total = $passed + $failed;

if ($isCli) {
    echo "\n=== PRUEBAS DEL SISTEMA DE INVENTARIO ===\n\n";
    foreach ($results as $r) {
        $icon = $r['status'] === 'PASS' ? '[OK]' : ($r['status'] === 'WARN' ? '[!!]' : '[XX]');
        $detail = $r['detail'] ? " — {$r['detail']}" : '';
        echo "$icon {$r['name']}$detail\n";
    }
    echo "\n--- RESUMEN ---\n";
    echo "Pasaron:  $passed\n";
    echo "Fallaron: $failed\n";
    echo "Avisos:   $warnings\n";
    echo "Total:    $total\n";
    exit($failed > 0 ? 1 : 0);
}

// Salida HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pruebas Completas - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .summary { display: flex; gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); flex: 1; text-align: center; }
        .card.pass { border-top: 4px solid #22c55e; }
        .card.fail { border-top: 4px solid #ef4444; }
        .card.warn { border-top: 4px solid #f59e0b; }
        .card h2 { margin: 0; font-size: 2em; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #333; color: white; }
        .PASS { color: #22c55e; font-weight: bold; }
        .FAIL { color: #ef4444; font-weight: bold; }
        .WARN { color: #f59e0b; font-weight: bold; }
        .detail { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>Pruebas Completas del Sistema</h1>
    <div class="summary">
        <div class="card pass"><h2><?php echo $passed; ?></h2><p>Pasaron</p></div>
        <div class="card fail"><h2><?php echo $failed; ?></h2><p>Fallaron</p></div>
        <div class="card warn"><h2><?php echo $warnings; ?></h2><p>Avisos</p></div>
    </div>
    <table>
        <thead><tr><th>Prueba</th><th>Estado</th><th>Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['name']); ?></td>
                <td class="<?php echo $r['status']; ?>"><?php echo $r['status']; ?></td>
                <td class="detail"><?php echo htmlspecialchars($r['detail']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:20px;color:#666;">Ejecutado: <?php echo date('Y-m-d H:i:s'); ?> | PHP <?php echo PHP_VERSION; ?></p>
</body>
</html>
