<?php
require_once 'config.php';
requireLogin();

// Verificar que se proporcione un archivo
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die('Archivo no especificado');
}

$filename = $_GET['file'];
$filepath = 'temp_reports/' . $filename;

// Validar que el archivo existe y está en el directorio correcto
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('Archivo no encontrado');
}

// Verificar que el archivo es un reporte válido
if (!preg_match('/^reporte_inventario_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/', $filename)) {
    http_response_code(403);
    die('Archivo no válido');
}

// Configurar headers para descarga
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Enviar el archivo
readfile($filepath);

// Opcional: eliminar el archivo después de la descarga
// unlink($filepath);
?> 