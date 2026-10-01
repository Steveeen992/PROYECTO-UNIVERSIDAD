<?php
/**
 * Script para vaciar inventario, categorías, ubicaciones, proveedores, préstamos y alertas.
 * Deja la base de datos como en primer uso. Los usuarios (administrador, editor, etc.) se mantienen.
 */
require_once 'config.php';

// Solo administrador puede ejecutar el reseteo
if (!isAdministrador()) {
    die('Solo el administrador puede ejecutar esta acción.');
}

$confirmar = $_GET['confirmar'] ?? '';
$mensaje = '';

if ($confirmar === 'si') {
    try {
        $pdo = $db->getConnection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $pdo->exec('TRUNCATE TABLE historial_elementos');
        $pdo->exec('TRUNCATE TABLE prestamos');
        $pdo->exec('TRUNCATE TABLE alertas');
        $pdo->exec('TRUNCATE TABLE inventario');
        $pdo->exec('TRUNCATE TABLE configuracion_alertas');
        $pdo->exec('TRUNCATE TABLE categorias');
        $pdo->exec('TRUNCATE TABLE ubicaciones');
        $pdo->exec('TRUNCATE TABLE proveedores');

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $mensaje = 'Reseteo completado. Inventario, categorías, ubicaciones, proveedores, préstamos y alertas han sido vaciados. Los usuarios se mantienen.';
        showMessage($mensaje, 'success');
        redirect('dashboard.php');
        exit;
    } catch (Exception $e) {
        $mensaje = 'Error al resetear: ' . $e->getMessage();
        showMessage($mensaje, 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resetear inventario - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php $nav_active = 'dashboard'; include 'include_nav.php'; ?>
    <div class="container">
        <h1>Vaciar todo el inventario (primer uso)</h1>
        <p>Esta acción eliminará <strong>todos</strong> los datos de:</p>
        <ul>
            <li>Inventario</li>
            <li>Categorías</li>
            <li>Ubicaciones</li>
            <li>Proveedores</li>
            <li>Préstamos</li>
            <li>Alertas</li>
            <li>Historial de elementos</li>
            <li>Configuración de alertas por usuario</li>
        </ul>
        <p>Se mantendrán los <strong>usuarios</strong> (administrador, editor, trabajador).</p>
        <?php if ($mensaje): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        <p>
            <a href="reset_inventario.php?confirmar=si" class="btn btn-danger" onclick="return confirm('¿Estás seguro? No se puede deshacer.');">Sí, vaciar todo</a>
            <a href="dashboard.php" class="btn">Cancelar</a>
        </p>
    </div>
</body>
</html>
