<?php
require_once 'config.php';
requireLogin();

// Solo administrador ve todos los documentos; resto ve solo los de sus préstamos
$where_extra = '';
$params_base = [];
if (!isAdministrador()) {
    $where_extra = ' AND p.usuario_registro = ?';
    $params_base[] = $_SESSION['user_id'];
}

// Asegurar que cada préstamo tenga un documento (crear si no existe)
$prestamos_sin_doc = $db->fetchAll(
    "SELECT p.id FROM prestamos p 
     LEFT JOIN documentos_prestamo d ON d.prestamo_id = p.id 
     WHERE d.id IS NULL" . ($where_extra ? $where_extra : ''),
    $params_base
);
foreach ($prestamos_sin_doc as $row) {
    try {
        $db->query("INSERT IGNORE INTO documentos_prestamo (prestamo_id) VALUES (?)", [$row['id']]);
    } catch (Exception $e) {}
}

// Listar documentos (préstamos con su documento y estado de firmas)
$documentos = $db->fetchAll(
    "SELECT d.id as doc_id, d.prestamo_id, d.fecha_creacion, d.firmado_receptor, d.firmado_autorizador,
            p.prestatario, p.contacto_prestatario, p.fecha_prestamo, p.fecha_devolucion_esperada, p.proposito, p.cantidad_prestada, p.estado,
            i.nombre as elemento_nombre, i.codigo as elemento_codigo,
            u.nombre as usuario_registro_nombre
     FROM documentos_prestamo d
     JOIN prestamos p ON p.id = d.prestamo_id
     JOIN inventario i ON i.id = p.elemento_id
     LEFT JOIN usuarios u ON u.id = p.usuario_registro
     " . ($where_extra !== '' ? 'WHERE 1=1' . $where_extra : '') . "
     ORDER BY d.fecha_creacion DESC",
    $params_base
);

$message = getMessage();
$nav_active = 'prestamos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos de entrega - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Documentos de entrega de préstamos</h1>
            <div class="header-actions">
                <a href="prestamos.php" class="btn btn-secondary">← Volver a Préstamos</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <p class="text-muted">Cada préstamo tiene un documento que acredita la entrega. Puede ver el documento y registrar la firma de quien recibe y de quien autoriza.</p>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha préstamo</th>
                        <th>Producto</th>
                        <th>Prestatario</th>
                        <th>Cantidad</th>
                        <th>Propósito</th>
                        <th>Firma receptor</th>
                        <th>Firma autorizador</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documentos)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No hay documentos de préstamos. Registre un préstamo desde Préstamos para generar el documento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($doc['fecha_prestamo'])); ?></td>
                                <td><?php echo htmlspecialchars($doc['elemento_codigo'] . ' - ' . $doc['elemento_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($doc['prestatario']); ?></td>
                                <td><?php echo (int)$doc['cantidad_prestada']; ?></td>
                                <td><?php echo htmlspecialchars(mb_substr($doc['proposito'] ?? '', 0, 40)); ?><?php echo mb_strlen($doc['proposito'] ?? '') > 40 ? '…' : ''; ?></td>
                                <td>
                                    <?php if (!empty($doc['firmado_receptor'])): ?>
                                        <span class="badge badge-activo">Firmado</span>
                                    <?php else: ?>
                                        <span class="badge badge-en_reparacion">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($doc['firmado_autorizador'])): ?>
                                        <span class="badge badge-activo">Firmado</span>
                                    <?php else: ?>
                                        <span class="badge badge-en_reparacion">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="documento_prestamo_ver.php?prestamo_id=<?php echo (int)$doc['prestamo_id']; ?>" class="btn btn-sm btn-primary">Ver / Firmar</a>
                                    <a href="documento_prestamo_ver.php?prestamo_id=<?php echo (int)$doc['prestamo_id']; ?>&formato=pdf" class="btn btn-sm btn-secondary" target="_blank">Imprimir PDF</a>
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
