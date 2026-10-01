<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$usuario_id = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
if ($usuario_id <= 0) {
    showMessage('Usuario no especificado.', 'error');
    redirect('usuarios.php');
}

$usuario = $db->fetch("SELECT id, nombre, email FROM usuarios WHERE id = ?", [$usuario_id]);
if (!$usuario) {
    showMessage('Usuario no encontrado.', 'error');
    redirect('usuarios.php');
}

// Actividad de navegación (log_actividad_usuario)
$log_actividad = [];
try {
    $log_actividad = $db->fetchAll(
        "SELECT id, accion, descripcion, pagina, fecha FROM log_actividad_usuario WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 500",
        [$usuario_id]
    );
} catch (Exception $e) {}

// Acciones en el sistema (historial_elementos: préstamos, devoluciones, retiros, etc.)
$historial_elementos = [];
try {
    $historial_elementos = $db->fetchAll(
        "SELECT h.tipo_evento, h.descripcion, h.fecha_evento as fecha, i.codigo, i.nombre as elemento_nombre
         FROM historial_elementos h
         LEFT JOIN inventario i ON h.elemento_id = i.id
         WHERE h.usuario_registro = ?
         ORDER BY h.fecha_evento DESC LIMIT 500",
        [$usuario_id]
    );
} catch (Exception $e) {}

// Unificar en una sola lista con fecha para ordenar
$eventos = [];
foreach ($log_actividad as $r) {
    $eventos[] = [
        'fecha' => $r['fecha'],
        'tipo'  => 'navegacion',
        'accion' => $r['accion'],
        'descripcion' => $r['descripcion'],
        'pagina' => $r['pagina'] ?? '',
        'detalle' => ''
    ];
}
foreach ($historial_elementos as $r) {
    $tipo_etiqueta = ucfirst(str_replace('_', ' ', $r['tipo_evento'] ?? ''));
    $elem = trim(($r['codigo'] ?? '') . ' ' . ($r['elemento_nombre'] ?? ''));
    $eventos[] = [
        'fecha' => $r['fecha'],
        'tipo'  => 'sistema',
        'accion' => $tipo_etiqueta,
        'descripcion' => $r['descripcion'] ?? '',
        'pagina' => '',
        'detalle' => $elem ? "Elemento: {$elem}" : ''
    ];
}

usort($eventos, function ($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});
$eventos = array_slice($eventos, 0, 300);

$message = getMessage();
$nav_active = 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de <?php echo htmlspecialchars($usuario['nombre']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Historial de actividad: <?php echo htmlspecialchars($usuario['nombre']); ?></h1>
            <div class="header-actions">
                <a href="usuarios.php" class="btn btn-secondary">← Volver a Usuarios</a>
            </div>
        </div>
        <p class="text-muted"><?php echo htmlspecialchars($usuario['email']); ?> — Últimas acciones y navegación.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Tipo</th>
                        <th>Acción / Descripción</th>
                        <th>Página / Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eventos)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay registros de actividad para este usuario.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($eventos as $e): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($e['fecha'])); ?></td>
                                <td>
                                    <?php if ($e['tipo'] === 'navegacion'): ?>
                                        <span class="badge badge-activo">Navegación</span>
                                    <?php else: ?>
                                        <span class="badge badge-en_reparacion">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($e['accion']); ?>
                                    <?php if (!empty($e['descripcion'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($e['descripcion']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($e['pagina'])): ?>
                                        <code><?php echo htmlspecialchars($e['pagina']); ?></code>
                                    <?php endif; ?>
                                    <?php if (!empty($e['detalle'])): ?>
                                        <br><small><?php echo htmlspecialchars($e['detalle']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
