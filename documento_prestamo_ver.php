<?php
require_once 'config.php';
requireLogin();

$prestamo_id = isset($_GET['prestamo_id']) ? (int)$_GET['prestamo_id'] : 0;
$formato = $_GET['formato'] ?? '';

if ($prestamo_id <= 0) {
    showMessage('Préstamo no especificado.', 'error');
    redirect('documentos_prestamos.php');
}

$effective_user_id = null;
$prestamo = $db->fetch(
    "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo,
            u.nombre as usuario_registro_nombre
     FROM prestamos p
     JOIN inventario i ON i.id = p.elemento_id
     LEFT JOIN usuarios u ON u.id = p.usuario_registro
     WHERE p.id = ?" . ($effective_user_id !== null ? ' AND p.usuario_registro = ?' : ''),
    $effective_user_id !== null ? [$prestamo_id, $_SESSION['user_id']] : [$prestamo_id]
);

if (!$prestamo) {
    showMessage('Préstamo no encontrado.', 'error');
    redirect('documentos_prestamos.php');
}

$doc = $db->fetch("SELECT * FROM documentos_prestamo WHERE prestamo_id = ?", [$prestamo_id]);
if (!$doc) {
    try {
        $db->query("INSERT INTO documentos_prestamo (prestamo_id) VALUES (?)", [$prestamo_id]);
        $doc = $db->fetch("SELECT * FROM documentos_prestamo WHERE prestamo_id = ?", [$prestamo_id]);
    } catch (Exception $e) {
        showMessage('Error al crear el documento.', 'error');
        redirect('documentos_prestamos.php');
    }
}

// Guardar firma vía POST (AJAX o form)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo_firma'] ?? ''; // 'receptor' o 'autorizador'
    $imagen_base64 = $_POST['firma_imagen'] ?? '';
    
    if ($tipo === 'receptor' && $imagen_base64 !== '') {
        $nombre = trim(sanitize($_POST['nombre_receptor'] ?? ''));
        $db->query(
            "UPDATE documentos_prestamo SET firmado_receptor = 1, fecha_firma_receptor = NOW(), nombre_receptor = ?, firma_receptor_imagen = ? WHERE prestamo_id = ?",
            [$nombre, $imagen_base64, $prestamo_id]
        );
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        showMessage('Firma del receptor guardada.', 'success');
        redirect('documento_prestamo_ver.php?prestamo_id=' . $prestamo_id);
    }
    
    if ($tipo === 'autorizador' && $imagen_base64 !== '') {
        $db->query(
            "UPDATE documentos_prestamo SET firmado_autorizador = 1, fecha_firma_autorizador = NOW(), usuario_autorizador_id = ?, firma_autorizador_imagen = ? WHERE prestamo_id = ?",
            [$_SESSION['user_id'], $imagen_base64, $prestamo_id]
        );
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        showMessage('Firma del autorizador guardada.', 'success');
        redirect('documento_prestamo_ver.php?prestamo_id=' . $prestamo_id);
    }
}

$nav_active = 'prestamos';
$message = getMessage();

// Vista solo para imprimir/PDF
if ($formato === 'pdf') {
    header('Content-Type: text/html; charset=UTF-8');
    $fecha_entrega = date('d/m/Y', strtotime($prestamo['fecha_prestamo']));
    $titulo = 'DOCUMENTO DE ENTREGA DE PRÉSTAMO';
    $texto = "Se hace constar que el día {$fecha_entrega} se hizo entrega del siguiente bien en préstamo:\n\n";
    $texto .= "• Producto: " . htmlspecialchars($prestamo['elemento_codigo'] . ' - ' . $prestamo['elemento_nombre']) . "\n";
    $texto .= "• Cantidad: {$prestamo['cantidad_prestada']} unidad(es)\n";
    $texto .= "• Entregado a: " . htmlspecialchars($prestamo['prestatario']) . "\n";
    $texto .= "• Contacto: " . htmlspecialchars($prestamo['contacto_prestatario'] ?? '—') . "\n";
    $texto .= "• Propósito / Uso: " . htmlspecialchars($prestamo['proposito'] ?? '—') . "\n";
    $texto .= "• Fecha prevista de devolución: " . date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])) . "\n\n";
    $texto .= "El presente documento queda como constancia de la entrega. Firma de quien recibe y de quien autoriza el préstamo.";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Documento de entrega - Préstamo #<?php echo $prestamo_id; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2cm; font-size: 11pt; }
        h1 { text-align: center; font-size: 14pt; margin-bottom: 1.5em; }
        .contenido { white-space: pre-wrap; margin: 1.5em 0; line-height: 1.5; }
        .firmas { margin-top: 2.5em; display: table; width: 100%; border-collapse: collapse; }
        .firma-box { display: table-cell; width: 50%; padding: 15px; vertical-align: top; border: 1px solid #ccc; }
        .firma-box h3 { margin: 0 0 8px 0; font-size: 10pt; }
        .firma-img { max-width: 100%; max-height: 80px; display: block; margin: 8px 0; }
        .fecha-firma { font-size: 9pt; color: #666; }
        .logo-pdf { text-align: center; margin-bottom: 1em; }
        .logo-pdf img { max-height: 56px; max-width: 180px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <?php if (function_exists('getLogoReportesHtml')): ?>
    <div class="logo-pdf"><?php echo getLogoReportesHtml(); ?></div>
    <?php else: ?>
    <div class="logo-pdf"><img src="assets/logo.png" alt="Logo" style="max-height:56px;max-width:180px;" /></div>
    <?php endif; ?>
    <h1><?php echo $titulo; ?></h1>
    <div class="contenido"><?php echo nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')); ?></div>

    <div class="firmas">
        <div class="firma-box">
            <h3>Firma de quien recibe el producto</h3>
            <?php if (!empty($doc['firmado_receptor'])): ?>
                <?php if (!empty($doc['firma_receptor_imagen'])): ?>
                    <img src="<?php echo $doc['firma_receptor_imagen']; ?>" alt="Firma receptor" class="firma-img" />
                <?php endif; ?>
                <div><?php echo htmlspecialchars($doc['nombre_receptor'] ?? $prestamo['prestatario']); ?></div>
                <div class="fecha-firma"><?php echo $doc['fecha_firma_receptor'] ? date('d/m/Y H:i', strtotime($doc['fecha_firma_receptor'])) : ''; ?></div>
            <?php else: ?>
                <p style="color:#999;">Pendiente de firma</p>
            <?php endif; ?>
        </div>
        <div class="firma-box">
            <h3>Firma de quien autoriza el préstamo</h3>
            <?php if (!empty($doc['firmado_autorizador'])): ?>
                <?php if (!empty($doc['firma_autorizador_imagen'])): ?>
                    <img src="<?php echo $doc['firma_autorizador_imagen']; ?>" alt="Firma autorizador" class="firma-img" />
                <?php endif; ?>
                <div><?php echo htmlspecialchars($prestamo['usuario_registro_nombre'] ?? '—'); ?></div>
                <div class="fecha-firma"><?php echo $doc['fecha_firma_autorizador'] ? date('d/m/Y H:i', strtotime($doc['fecha_firma_autorizador'])) : ''; ?></div>
            <?php else: ?>
                <p style="color:#999;">Pendiente de firma</p>
            <?php endif; ?>
        </div>
    </div>

    <p class="no-print" style="margin-top:2em;text-align:center;">
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
    </p>
</body>
</html>
<?php
    exit;
}

// Recargar doc por si se actualizó
$doc = $db->fetch("SELECT * FROM documentos_prestamo WHERE prestamo_id = ?", [$prestamo_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento de entrega - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .doc-paper { max-width: 700px; margin: 0 auto 2rem; background: #fff; padding: 2rem; border: 1px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .doc-title { text-align: center; font-size: 1.25rem; margin-bottom: 1.5rem; }
        .doc-body { white-space: pre-wrap; line-height: 1.6; margin-bottom: 2rem; }
        .firmas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem; }
        .firma-block { border: 1px solid #e0e0e0; padding: 1rem; border-radius: 8px; background: #fafafa; }
        .firma-block h4 { margin: 0 0 0.5rem 0; font-size: 0.95rem; }
        .signature-canvas { border: 1px solid #ccc; background: #fff; cursor: crosshair; display: block; width: 100%; max-width: 280px; height: 100px; border-radius: 4px; touch-action: none; }
        .firma-preview { max-width: 280px; max-height: 80px; margin: 8px 0; }
        .btn-firmar { margin-top: 8px; }
    </style>
</head>
<body>
    <?php include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Documento de entrega</h1>
            <div class="header-actions">
                <a href="documento_prestamo_ver.php?prestamo_id=<?php echo $prestamo_id; ?>&formato=pdf" class="btn btn-secondary" target="_blank">Imprimir / PDF</a>
                <a href="documentos_prestamos.php" class="btn btn-light">← Volver a documentos</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <div class="doc-paper">
            <h2 class="doc-title">DOCUMENTO DE ENTREGA DE PRÉSTAMO</h2>
            <?php
            $fecha_entrega = date('d/m/Y', strtotime($prestamo['fecha_prestamo']));
            $texto = "Se hace constar que el día {$fecha_entrega} se hizo entrega del siguiente bien en préstamo:\n\n";
            $texto .= "• Producto: {$prestamo['elemento_codigo']} - {$prestamo['elemento_nombre']}\n";
            $texto .= "• Cantidad: {$prestamo['cantidad_prestada']} unidad(es)\n";
            $texto .= "• Entregado a: {$prestamo['prestatario']}\n";
            $texto .= "• Contacto: " . ($prestamo['contacto_prestatario'] ?? '—') . "\n";
            $texto .= "• Propósito / Uso: " . ($prestamo['proposito'] ?? '—') . "\n";
            $texto .= "• Fecha prevista de devolución: " . date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])) . "\n\n";
            $texto .= "El presente documento queda como constancia de la entrega.";
            ?>
            <div class="doc-body"><?php echo nl2br(htmlspecialchars($texto)); ?></div>

            <div class="firmas-grid">
                <div class="firma-block">
                    <h4>Firma de quien recibe el producto</h4>
                    <?php if (!empty($doc['firmado_receptor'])): ?>
                        <?php if (!empty($doc['firma_receptor_imagen'])): ?>
                            <img src="<?php echo htmlspecialchars($doc['firma_receptor_imagen']); ?>" alt="Firma" class="firma-preview" />
                        <?php endif; ?>
                        <p><strong><?php echo htmlspecialchars($doc['nombre_receptor'] ?? $prestamo['prestatario']); ?></strong></p>
                        <p class="text-muted" style="font-size:0.85rem;"><?php echo $doc['fecha_firma_receptor'] ? date('d/m/Y H:i', strtotime($doc['fecha_firma_receptor'])) : ''; ?></p>
                    <?php else: ?>
                        <form method="POST" class="form-firma-receptor">
                            <input type="hidden" name="tipo_firma" value="receptor" />
                            <input type="hidden" name="firma_imagen" id="firma_imagen_receptor" value="" />
                            <label>Nombre de quien recibe:</label>
                            <input type="text" name="nombre_receptor" class="form-control" value="<?php echo htmlspecialchars($prestamo['prestatario']); ?>" required style="margin-bottom:8px;max-width:280px;" />
                            <canvas id="canvas_receptor" class="signature-canvas" width="280" height="100"></canvas>
                            <button type="button" class="btn btn-sm btn-light btn-firmar" onclick="limpiarCanvas('receptor')">Limpiar</button>
                            <button type="submit" class="btn btn-primary btn-firmar">Guardar firma receptor</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="firma-block">
                    <h4>Firma de quien autoriza el préstamo</h4>
                    <?php if (!empty($doc['firmado_autorizador'])): ?>
                        <?php if (!empty($doc['firma_autorizador_imagen'])): ?>
                            <img src="<?php echo htmlspecialchars($doc['firma_autorizador_imagen']); ?>" alt="Firma" class="firma-preview" />
                        <?php endif; ?>
                        <p><strong><?php echo htmlspecialchars($prestamo['usuario_registro_nombre'] ?? '—'); ?></strong></p>
                        <p class="text-muted" style="font-size:0.85rem;"><?php echo $doc['fecha_firma_autorizador'] ? date('d/m/Y H:i', strtotime($doc['fecha_firma_autorizador'])) : ''; ?></p>
                    <?php else: ?>
                        <?php if ($prestamo['usuario_registro'] == $_SESSION['user_id']): ?>
                            <form method="POST" class="form-firma-autorizador">
                                <input type="hidden" name="tipo_firma" value="autorizador" />
                                <input type="hidden" name="firma_imagen" id="firma_imagen_autorizador" value="" />
                                <p class="text-muted" style="font-size:0.9rem;">Usted registró este préstamo. Firme a continuación para autorizar.</p>
                                <canvas id="canvas_autorizador" class="signature-canvas" width="280" height="100"></canvas>
                                <button type="button" class="btn btn-sm btn-light btn-firmar" onclick="limpiarCanvas('autorizador')">Limpiar</button>
                                <button type="submit" class="btn btn-primary btn-firmar">Guardar firma autorizador</button>
                            </form>
                        <?php else: ?>
                            <p class="text-muted">Solo el usuario que autorizó el préstamo puede firmar aquí.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var canvases = { receptor: null, autorizador: null };
        var ctx = {};
        var drawing = false;

        function initCanvas(name) {
            var el = document.getElementById('canvas_' + name);
            if (!el) return;
            canvases[name] = el;
            ctx[name] = el.getContext('2d');
            ctx[name].strokeStyle = '#000';
            ctx[name].lineWidth = 2;
            ctx[name].lineCap = 'round';

            el.addEventListener('mousedown', function(e) { start(e, name); });
            el.addEventListener('mousemove', function(e) { move(e, name); });
            el.addEventListener('mouseup', stop);
            el.addEventListener('mouseout', stop);
            el.addEventListener('touchstart', function(e) { e.preventDefault(); start(e, name); }, { passive: false });
            el.addEventListener('touchmove', function(e) { e.preventDefault(); move(e, name); }, { passive: false });
            el.addEventListener('touchend', stop);
        }

        function getPos(e, el) {
            if (e.touches && e.touches.length) {
                var rect = el.getBoundingClientRect();
                return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
            }
            return { x: e.offsetX, y: e.offsetY };
        }

        function start(e, name) {
            drawing = true;
            var pos = getPos(e, canvases[name]);
            ctx[name].beginPath();
            ctx[name].moveTo(pos.x, pos.y);
        }

        function move(e, name) {
            if (!drawing) return;
            var pos = getPos(e, canvases[name]);
            ctx[name].lineTo(pos.x, pos.y);
            ctx[name].stroke();
        }

        function stop() { drawing = false; }

        window.limpiarCanvas = function(name) {
            if (canvases[name]) {
                ctx[name].clearRect(0, 0, canvases[name].width, canvases[name].height);
            }
        };

        initCanvas('receptor');
        initCanvas('autorizador');

        document.querySelectorAll('.form-firma-receptor, .form-firma-autorizador').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var tipo = form.querySelector('input[name="tipo_firma"]').value;
                var canvas = document.getElementById('canvas_' + tipo);
                var input = document.getElementById('firma_imagen_' + tipo);
                if (!canvas || !input) { form.submit(); return; }
                input.value = canvas.toDataURL('image/png');
                form.submit();
            });
        });
    })();
    </script>
</body>
</html>
