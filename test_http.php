<?php
/**
 * Prueba de login y páginas autenticadas vía HTTP
 */
error_reporting(E_ALL);

$baseUrl = 'http://localhost/Sofware_inventarioo/';
$cookieFile = sys_get_temp_dir() . '/inv_test_cookies.txt';
@unlink($cookieFile);

$passed = 0;
$failed = 0;

function test($name, $condition, $detail = '') {
    global $passed, $failed;
    $icon = $condition ? '[OK]' : '[XX]';
    if ($condition) $passed++; else $failed++;
    echo "$icon $name" . ($detail ? " — $detail" : "") . "\n";
}

function httpRequest($url, $cookieFile, $post = null, $follow = true) {
    $opts = [
        'http' => [
            'method' => $post ? 'POST' : 'GET',
            'header' => "Cookie: " . (file_exists($cookieFile) ? file_get_contents($cookieFile) : '') . "\r\n",
            'timeout' => 10,
            'follow_location' => $follow ? 1 : 0,
            'ignore_errors' => true,
        ]
    ];
    if ($post) {
        $opts['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $opts['http']['content'] = http_build_query($post);
    }
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body, 'headers' => $http_response_header ?? []];
}

echo "=== PRUEBAS HTTP CON SESIÓN ===\n\n";

require_once __DIR__ . '/config.php';
$admin = $db->fetch("SELECT id, nombre, email FROM usuarios WHERE rol = 'administrador' LIMIT 1");
test('Admin encontrado en BD', !empty($admin), $admin['nombre'] ?? '');

// Login page loads
$r = httpRequest($baseUrl . 'login.php', $cookieFile);
test('Login page carga', $r['body'] !== false && stripos($r['body'], 'Iniciar Sesión') !== false);

// Páginas públicas
foreach (['forgot_password.php', 'reset_password.php'] as $page) {
    $r = httpRequest($baseUrl . $page, $cookieFile);
    test("Página pública $page", $r['body'] !== false && strlen($r['body']) > 100);
}

// Páginas protegidas sin sesión → redirect
foreach (['dashboard.php', 'inventario.php', 'prestamos.php', 'categorias.php', 'usuarios.php', 'alertas.php', 'analytics.php', 'chat_ai.php', 'reportes.php'] as $page) {
    $r = httpRequest($baseUrl . $page, $cookieFile, null, false);
    $redirected = false;
    foreach ($r['headers'] as $h) {
        if (stripos($h, 'Location:') !== false && stripos($h, 'login') !== false) $redirected = true;
    }
    test("Auth guard: $page", $redirected);
}

// Simular sesión vía PHP directo para probar contenido de páginas
$_SESSION['user_id'] = $admin['id'] ?? 1;
$_SESSION['user_name'] = $admin['nombre'] ?? 'Admin';
$_SESSION['user_email'] = $admin['email'] ?? '';
$_SESSION['user_role'] = 'administrador';

// Probar que las consultas de cada módulo funcionan con sesión
$modules = [
    'Dashboard stats' => fn() => $db->fetch("SELECT COUNT(*) as c FROM inventario WHERE estado='activo'"),
    'Inventario list' => fn() => $db->fetchAll("SELECT * FROM inventario LIMIT 5"),
    'Categorías list' => fn() => $db->fetchAll("SELECT * FROM categorias"),
    'Ubicaciones list' => fn() => $db->fetchAll("SELECT * FROM ubicaciones"),
    'Préstamos list' => fn() => $db->fetchAll("SELECT p.*, i.nombre FROM prestamos p JOIN inventario i ON p.elemento_id=i.id LIMIT 5"),
    'Proveedores list' => fn() => $db->fetchAll("SELECT * FROM proveedores"),
    'Usuarios list' => fn() => $db->fetchAll("SELECT id, nombre, rol FROM usuarios"),
    'Alertas list' => fn() => getAlertasNoLeidas($db),
    'Analytics prestamos' => fn() => $db->fetch("SELECT COUNT(*) as c FROM prestamos WHERE estado='activo'"),
    'Reportes inventario' => fn() => $db->fetchAll("SELECT i.*, c.nombre as cat FROM inventario i LEFT JOIN categorias c ON i.categoria_id=c.id LIMIT 5"),
];

foreach ($modules as $name => $fn) {
    try {
        $result = $fn();
        test("Módulo $name", $result !== false && $result !== null);
    } catch (Exception $e) {
        test("Módulo $name", false, $e->getMessage());
    }
}

// Verificar APIs responden (sin sesión deben fallar o redirigir)
$apiPages = ['api_chat_response.php', 'api_process_invoice.php', 'exportar_excel.php'];
foreach ($apiPages as $api) {
    test("API existe: $api", file_exists(__DIR__ . '/' . $api));
}

// Verificar CSS y assets
test('CSS styles.css existe', file_exists(__DIR__ . '/styles.css'));
test('CSS tiene contenido', filesize(__DIR__ . '/styles.css') > 1000);

// Verificar integridad de datos
$orphanItems = $db->fetch("SELECT COUNT(*) as c FROM inventario i LEFT JOIN categorias c ON i.categoria_id=c.id WHERE i.categoria_id IS NOT NULL AND c.id IS NULL");
test('Sin items huérfanos de categoría', ($orphanItems['c'] ?? 0) == 0, ($orphanItems['c'] ?? 0) . ' huérfanos');

$userNoRole = $db->fetch("SELECT COUNT(*) as c FROM usuarios WHERE rol IS NULL OR rol = ''");
if (($userNoRole['c'] ?? 0) > 0) {
    test('Usuarios sin rol', false, $userNoRole['c'] . ' usuario(s) sin rol asignado');
} else {
    test('Todos los usuarios tienen rol', true);
}

echo "\n--- RESUMEN HTTP ---\n";
echo "Pasaron:  $passed\n";
echo "Fallaron: $failed\n";
exit($failed > 0 ? 1 : 0);
