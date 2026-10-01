<?php
/**
 * Crea o actualiza el usuario administradora: Litoral2026 / 2026Litoral
 * Ejecutar UNA VEZ para poder iniciar sesión.
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

$usuario = 'Litoral2026';
$password = '2026Litoral';
$email = 'Litoral2026';   // Se ingresa como usuario en el login
$nombre = 'Administradora';
$rol = 'administrador';

try {
    $existe = $db->fetch("SELECT id, password FROM usuarios WHERE email = ? OR nombre = ?", [$email, $usuario]);
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    if ($existe) {
        $db->query(
            "UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ? WHERE id = ?",
            [$nombre, $email, $hash, $rol, $existe['id']]
        );
        echo "<p style='color: green;'>✅ Usuario actualizado. Puedes iniciar sesión con:</p>";
    } else {
        $db->query(
            "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)",
            [$nombre, $email, $hash, $rol]
        );
        echo "<p style='color: green;'>✅ Usuario administradora creado. Puedes iniciar sesión con:</p>";
    }
    
    echo "<p><strong>Usuario:</strong> {$usuario}<br><strong>Contraseña:</strong> {$password}</p>";
    echo "<p><a href='login.php'>Ir al inicio de sesión</a></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
