<?php
/**
 * Migración: actualiza la columna rol de usuarios a los nuevos valores
 * (administrador, editor, trabajador). Ejecutar UNA VEZ tras desplegar el sistema de roles.
 */
require_once 'config.php';
requireLogin();
requireAdmin();

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Actualización de roles</h1>";

try {
    $pdo = $db->getConnection();
    // Modificar ENUM para incluir editor y trabajador; convertir 'usuario' a 'editor'
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('administrador', 'editor', 'trabajador') DEFAULT 'trabajador'");
    echo "<p style='color: green;'>✅ Columna rol actualizada.</p>";
    $pdo->exec("UPDATE usuarios SET rol = 'editor' WHERE rol NOT IN ('administrador', 'editor', 'trabajador') OR rol IS NULL");
    echo "<p style='color: green;'>✅ Usuarios con rol antiguo actualizados a 'editor'.</p>";
    echo "<p><a href='dashboard.php'>Ir al Dashboard</a></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
