<?php
/**
 * Elimina todos los usuarios excepto la administradora (Litoral2026).
 * Antes reasigna inventario, préstamos, etc. de los otros usuarios a la administradora.
 */
require_once 'config.php';
requireLogin();
requireAdmin();

header('Content-Type: text/html; charset=utf-8');

$admin_email = 'Litoral2026';

try {
    $admin = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$admin_email]);
    if (!$admin) {
        echo "<p style='color: red;'>No se encontró el usuario administradora (Litoral2026). No se eliminó nadie.</p>";
        echo "<p><a href='usuarios.php'>Volver a Usuarios</a></p>";
        exit;
    }
    $admin_id = (int) $admin['id'];

    $otros = $db->fetchAll("SELECT id FROM usuarios WHERE id != ?", [$admin_id]);
    $otros_ids = array_column($otros, 'id');
    if (empty($otros_ids)) {
        showMessage('Ya solo existe la administradora.', 'info');
        redirect('usuarios.php');
        exit;
    }

    $pdo = $db->getConnection();
    $placeholders = implode(',', array_map('intval', $otros_ids));

    $pdo->beginTransaction();
    try {
        $tablas_con_nombre_unico = ['categorias', 'ubicaciones', 'proveedores'];
        foreach ($tablas_con_nombre_unico as $tabla) {
            $existentes_admin = $db->fetchAll("SELECT nombre FROM {$tabla} WHERE usuario_registro = ?", [$admin_id]);
            $nombres_admin = array_column($existentes_admin, 'nombre');
            $filas_reasignar = $db->fetchAll("SELECT id, nombre FROM {$tabla} WHERE usuario_registro IN ({$placeholders})");
            foreach ($filas_reasignar as $fila) {
                $nombre = $fila['nombre'];
                $nombre_final = $nombre;
                $sufijo = 1;
                while (in_array($nombre_final, $nombres_admin, true)) {
                    $nombre_final = $nombre . ' (' . ($sufijo++) . ')';
                }
                $nombres_admin[] = $nombre_final;
                if ($nombre_final !== $nombre) {
                    $pdo->prepare("UPDATE {$tabla} SET nombre = ? WHERE id = ?")->execute([$nombre_final, $fila['id']]);
                }
            }
            $pdo->exec("UPDATE {$tabla} SET usuario_registro = {$admin_id} WHERE usuario_registro IN ({$placeholders})");
        }
        $tablas_sin_nombre_unico = ['inventario', 'prestamos', 'historial_elementos', 'alertas'];
        foreach ($tablas_sin_nombre_unico as $tabla) {
            $pdo->exec("UPDATE {$tabla} SET usuario_registro = {$admin_id} WHERE usuario_registro IN ({$placeholders})");
        }
        $pdo->exec("DELETE FROM configuracion_alertas WHERE usuario_id IN ({$placeholders})");
        $pdo->exec("DELETE FROM usuarios WHERE id IN ({$placeholders})");
        $pdo->commit();
        showMessage('Se eliminaron todos los usuarios excepto la administradora (Litoral2026). Sus datos se reasignaron a la administradora.', 'success');
        redirect('usuarios.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='usuarios.php'>Volver a Usuarios</a></p>";
}
