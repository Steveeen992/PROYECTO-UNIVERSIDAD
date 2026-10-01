<?php
require_once 'config.php';
require_once 'google_config.php';

// Verificar configuración de Google OAuth
echo "<h1>Diagnóstico de Google OAuth</h1>";

echo "<h2>1. Verificación de credenciales</h2>";
if (checkGoogleConfig()) {
    echo "Las credenciales están configuradas";
} else {
    echo "Las credenciales NO están configuradas";
    echo "<br><strong>Necesitas actualizar el archivo google_config.php con tus credenciales reales</strong>";
}

echo "<h2>2. Valores actuales</h2>";
echo "CLIENT_ID: " . (GOOGLE_CLIENT_ID === 'TU_GOOGLE_CLIENT_ID_AQUI' ? 'No configurado' : 'Configurado') . "<br>";
echo "CLIENT_SECRET: " . (GOOGLE_CLIENT_SECRET === 'TU_GOOGLE_CLIENT_SECRET_AQUI' ? 'No configurado' : 'Configurado') . "<br>";
echo "REDIRECT_URI: " . GOOGLE_REDIRECT_URI . "<br>";

echo "<h2>3. Verificación de extensiones PHP</h2>";
if (function_exists('curl_init')) {
    echo "Extensión cURL está habilitada";
} else {
    echo "Extensión cURL NO está habilitada";
}

echo "<h2>4. URL de autorización generada</h2>";
if (checkGoogleConfig()) {
    $auth_url = getGoogleAuthUrl();
    echo "URL: <a href='$auth_url' target='_blank'>$auth_url</a>";
} else {
    echo "No se puede generar URL sin credenciales";
}

echo "<h2>5. Pasos para solucionar el problema</h2>";
echo "<ol>";
echo "<li>Ve a <a href='https://console.cloud.google.com/' target='_blank'>Google Cloud Console</a></li>";
echo "<li>Crea un nuevo proyecto o selecciona uno existente</li>";
echo "<li>Ve a 'APIs y servicios' > 'Credenciales'</li>";
echo "<li>Haz clic en 'Crear credenciales' > 'ID de cliente de OAuth 2.0'</li>";
echo "<li>Selecciona 'Aplicación web'</li>";
echo "<li>En 'URI de redirección autorizados' agrega: <code>" . GOOGLE_REDIRECT_URI . "</code></li>";
echo "<li>Copia el 'ID de cliente' y 'Secreto del cliente'</li>";
echo "<li>Actualiza el archivo <code>google_config.php</code> con estos valores</li>";
echo "</ol>";

echo "<h2>6. Ejemplo de configuración correcta</h2>";
echo "<pre>";
echo "define('GOOGLE_CLIENT_ID', '123456789-abcdefghijklmnop.apps.googleusercontent.com');\n";
echo "define('GOOGLE_CLIENT_SECRET', 'GOCSPX-abcdefghijklmnopqrstuvwxyz');\n";
echo "</pre>";

echo "<p><a href='login.php'>← Volver al login</a></p>";
?> 