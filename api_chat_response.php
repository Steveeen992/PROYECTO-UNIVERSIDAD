<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener el mensaje del usuario
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

try {
    $response = processUserQuestion($message);
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function processUserQuestion($message) {
    global $db;
    
    $message = strtolower($message);
    
    // 1. Generar y descargar reporte si se pide un archivo
    if (preg_match('/(hazme|genera|crea|envía|envia|descarga|mándame|mandame|quiero|necesito)?\s*(un\s+)?(reporte|report)(\s+del)?(\s+inventario)?(\s+en\s+(excel|csv|archivo|pdf))?/i', $message)) {
        return generateReportForChat();
    }

    // 2. Usuarios y roles
    if (preg_match('/(cuántos|cuantos|quiénes|quienes|lista|listar|usuarios|users|admins|administradores|roles|permisos)/i', $message)) {
        return getUsersAndRolesInfo();
    }
    if (preg_match('/(quién|quien)\s+(es|son)\s+(el\s+)?admin(istrador)?/i', $message)) {
        return getAdminInfo();
    }
    if (preg_match('/(cómo|como)\s+(cambiar|modificar)\s+(mi\s+)?contraseña|password/i', $message)) {
        return "Para cambiar tu contraseña, ve a tu perfil y haz clic en 'Cambiar contraseña'. Ingresa tu contraseña actual y la nueva contraseña.";
    }
    if (preg_match('/(cómo|como)\s+(configurar|cambiar|modificar)\s+(el\s+)?nombre\s+del\s+sistema/i', $message)) {
        return "Para cambiar el nombre del sistema, edita la constante SITE_NAME en el archivo config.php.";
    }
    if (preg_match('/(cómo|como)\s+(configurar|agregar|cambiar)\s+mi\s+email/i', $message)) {
        return "Para configurar tu email, ve a 'Configurar Email' en el menú de usuario y guarda tu dirección de correo.";
    }
    if (preg_match('/(quién|quien)\s+(está|estan|hay)\s+(conectado|conectados|en\s+línea|online)/i', $message)) {
        return getActiveSessionsInfo();
    }
    if (preg_match('/(historial|auditoría|cambios|logs)/i', $message)) {
        return getHistoryInfo();
    }
    if (preg_match('/(backup|copia\s+de\s+seguridad|restaurar|restore)/i', $message)) {
        return getBackupInfo();
    }
    if (preg_match('/(soporte|ayuda|contacto|support)/i', $message)) {
        return getSupportInfo();
    }

    // 3. Consultas sobre inventario (estadísticas)
    if (preg_match('/(estadísticas?|statistics?)/i', $message)) {
        return getGeneralStats();
    }
    if (preg_match('/(cuántos|cuantos|cuántas|cuantas)\s+(monitores?|pantallas?|screens?)/i', $message)) {
        return getMonitorCount();
    }
    if (preg_match('/(cuántos|cuantos|cuántas|cuantas)\s+(productos?|elementos?|items?)/i', $message)) {
        return getTotalProducts();
    }
    if (preg_match('/(cuántos|cuantos|cuántas|cuantas)\s+(activos?|funcionando)/i', $message)) {
        return getActiveProducts();
    }
    if (preg_match('/(cuántos|cuantos|cuántas|cuantas)\s+(en\s+reparación|reparación)/i', $message)) {
        return getRepairProducts();
    }
    if (preg_match('/(valor|precio|costo)\s+(total|del\s+inventario)/i', $message)) {
        return getTotalValue();
    }
    if (preg_match('/(proveedores?|suppliers?)/i', $message)) {
        return getSuppliersInfo();
    }
    if (preg_match('/(categorías?|categories?)/i', $message)) {
        return getCategoriesInfo();
    }
    if (preg_match('/(ubicaciones?|locations?)/i', $message)) {
        return getLocationsInfo();
    }
    if (preg_match('/(préstamos?|loans?|prestamos?)/i', $message)) {
        return getLoansInfo();
    }

    // 4. Preguntas sobre funcionalidades
    if (preg_match('/(cómo|como)\s+(agregar|añadir|crear)/i', $message)) {
        return getAddInstructions();
    }
    if (preg_match('/(cómo|como)\s+(procesar|factura|ia)/i', $message)) {
        return getInvoiceProcessingInfo();
    }
    if (preg_match('/(ayuda|help|soporte)/i', $message)) {
        return getHelpInfo();
    }

    // 5. Respuesta por defecto
    return getDefaultResponse($message);
}

function getMonitorCount() {
    global $db;
    
    $monitores = $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE LOWER(nombre) LIKE '%monitor%' OR LOWER(nombre) LIKE '%pantalla%' OR LOWER(nombre) LIKE '%screen%'");
    $total = $monitores['total'];
    
    if ($total == 0) {
        return "No tienes monitores registrados en tu inventario actualmente.";
    } elseif ($total == 1) {
        return "Tienes 1 monitor registrado en tu inventario.";
    } else {
        return "Tienes {$total} monitores registrados en tu inventario.";
    }
}

function getTotalProducts() {
    global $db;
    
    $total = $db->fetch("SELECT COUNT(*) as total FROM inventario")['total'];
    
    if ($total == 0) {
        return "No tienes productos registrados en tu inventario.";
    } elseif ($total == 1) {
        return "Tienes 1 producto registrado en tu inventario.";
    } else {
        return "Tienes {$total} productos registrados en tu inventario.";
    }
}

function getActiveProducts() {
    global $db;
    
    $activos = $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'activo'")['total'];
    $total = $db->fetch("SELECT COUNT(*) as total FROM inventario")['total'];
    
    return "Tienes {$activos} productos activos de un total de {$total} productos en tu inventario.";
}

function getRepairProducts() {
    global $db;
    
    $reparacion = $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'en_reparacion'")['total'];
    
    if ($reparacion == 0) {
        return "No tienes productos en reparación actualmente.";
    } elseif ($reparacion == 1) {
        return "Tienes 1 producto en reparación.";
    } else {
        return "Tienes {$reparacion} productos en reparación.";
    }
}

function getTotalValue() {
    global $db;
    
    $result = $db->fetch("SELECT SUM(valor_compra) as total FROM inventario WHERE valor_compra IS NOT NULL");
    $total = $result['total'] ?? 0;
    
    if ($total == 0) {
        return "No hay información de valor total en tu inventario.";
    } else {
        return "El valor total de tu inventario es: $" . number_format($total, 0, ',', '.');
    }
}

function getSuppliersInfo() {
    global $db;
    
    $proveedores = $db->fetch("SELECT COUNT(*) as total FROM proveedores")['total'];
    
    if ($proveedores == 0) {
        return "No tienes proveedores registrados. Puedes agregar proveedores desde la sección 'Proveedores'.";
    } elseif ($proveedores == 1) {
        return "Tienes 1 proveedor registrado. Puedes gestionar proveedores desde la sección 'Proveedores'.";
    } else {
        return "Tienes {$proveedores} proveedores registrados. Puedes gestionar proveedores desde la sección 'Proveedores'.";
    }
}

function getCategoriesInfo() {
    global $db;
    
    $categorias = $db->fetch("SELECT COUNT(*) as total FROM categorias")['total'];
    
    if ($categorias == 0) {
        return "No tienes categorías registradas. Puedes agregar categorías desde la sección 'Categorías'.";
    } elseif ($categorias == 1) {
        return "Tienes 1 categoría registrada. Puedes gestionar categorías desde la sección 'Categorías'.";
    } else {
        return "Tienes {$categorias} categorías registradas. Puedes gestionar categorías desde la sección 'Categorías'.";
    }
}

function getLocationsInfo() {
    global $db;
    
    $ubicaciones = $db->fetch("SELECT COUNT(*) as total FROM ubicaciones")['total'];
    
    if ($ubicaciones == 0) {
        return "No tienes ubicaciones registradas. Puedes agregar ubicaciones desde la sección 'Ubicaciones'.";
    } elseif ($ubicaciones == 1) {
        return "Tienes 1 ubicación registrada. Puedes gestionar ubicaciones desde la sección 'Ubicaciones'.";
    } else {
        return "Tienes {$ubicaciones} ubicaciones registradas. Puedes gestionar ubicaciones desde la sección 'Ubicaciones'.";
    }
}

function getLoansInfo() {
    global $db;
    
    $prestamos = $db->fetch("SELECT COUNT(*) as total FROM prestamos")['total'];
    
    if ($prestamos == 0) {
        return "No tienes préstamos registrados. Puedes gestionar préstamos desde la sección 'Prestamos'.";
    } elseif ($prestamos == 1) {
        return "Tienes 1 préstamo registrado. Puedes gestionar préstamos desde la sección 'Prestamos'.";
    } else {
        return "Tienes {$prestamos} préstamos registrados. Puedes gestionar préstamos desde la sección 'Prestamos'.";
    }
}

function getGeneralStats() {
    global $db;
    
    $stats = [
        'total' => $db->fetch("SELECT COUNT(*) as total FROM inventario")['total'],
        'activos' => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'activo'")['total'],
        'reparacion' => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'en_reparacion'")['total'],
        'baja' => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'dado_de_baja'")['total'],
        'categorias' => $db->fetch("SELECT COUNT(*) as total FROM categorias")['total'],
        'ubicaciones' => $db->fetch("SELECT COUNT(*) as total FROM ubicaciones")['total'],
        'proveedores' => $db->fetch("SELECT COUNT(*) as total FROM proveedores")['total']
    ];
    
    return "📊 **Estadísticas de tu inventario:**
• Total de productos: {$stats['total']}
• Productos activos: {$stats['activos']}
• En reparación: {$stats['reparacion']}
• Dados de baja: {$stats['baja']}
• Categorías: {$stats['categorias']}
• Ubicaciones: {$stats['ubicaciones']}
• Proveedores: {$stats['proveedores']}

Para ver reportes detallados, ve a la sección 'Reportes'.";
}

function getAddInstructions() {
    return "📝 **Para agregar productos:**
1. Ve a 'Inventario' en el menú
2. Haz clic en 'Agregar Elemento'
3. Completa el formulario con los datos del producto
4. Haz clic en 'Guardar'

**Consejo:** También puedes usar el botón '🤖 Procesar Factura con IA' para agregar productos automáticamente desde una foto de factura.";
}

function getInvoiceProcessingInfo() {
    return "🤖 **Para procesar facturas con IA:**
1. Haz clic en '🤖 Procesar Factura con IA'
2. Sube una foto de tu factura
3. La IA extraerá automáticamente:
   • Nombre del producto
   • Precio
   • Proveedor
   • Fecha
   • Código
4. Revisa y confirma los datos
5. ¡Listo! El producto se agrega al inventario

**Tip:** Usa buena iluminación y asegúrate que el texto sea legible para mejores resultados.";
}

function getHelpInfo() {
    return "🆘 **¿En qué puedo ayudarte?**

**📦 Inventario:**
• Consultar productos
• Agregar elementos
• Ver estadísticas

**🤖 IA:**
• Procesar facturas
• Extraer datos automáticamente

**📊 Reportes:**
• Generar reportes
• Ver estadísticas

**⚙️ Configuración:**
• Gestionar categorías
• Gestionar ubicaciones
• Gestionar proveedores

**💡 Ejemplos de preguntas:**
• '¿Cuántos monitores tengo?'
• '¿Cuál es el valor total?'
• '¿Cómo agregar un producto?'
• '¿Cómo procesar una factura?'

¡Pregúntame lo que necesites! 😊";
}

function generateReportForChat() {
    global $db;
    
    try {
        // Obtener datos del inventario (P. = préstamos activos por elemento)
        $inventario = $db->fetchAll("
            SELECT i.*, c.nombre as categoria, u.nombre as ubicacion, p.nombre as proveedor,
                   (SELECT COALESCE(SUM(cantidad_prestada), 0) FROM prestamos WHERE elemento_id = i.id AND estado = 'activo') as cantidad_prestada_activa
            FROM inventario i
            LEFT JOIN categorias c ON i.categoria_id = c.id
            LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id
            LEFT JOIN proveedores p ON i.proveedor_id = p.id
            ORDER BY i.fecha_registro DESC
        ");
        
        // Generar archivo CSV
        $filename = 'reporte_inventario_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = generateExcelReport($inventario, $filename);
        
        // Crear URL de descarga
        $downloadUrl = 'download_report.php?file=' . urlencode($filename);
        
        // Generar estadísticas para mostrar en el chat
        $stats = [
            'total' => count($inventario),
            'activos' => count(array_filter($inventario, fn($item) => $item['estado'] === 'activo')),
            'reparacion' => count(array_filter($inventario, fn($item) => $item['estado'] === 'en_reparacion')),
            'baja' => count(array_filter($inventario, fn($item) => $item['estado'] === 'dado_de_baja')),
            'valor_total' => array_sum(array_column($inventario, 'valor_compra'))
        ];
        
        return "📊 **¡Reporte generado exitosamente!**
        
✅ Se ha creado un reporte completo de tu inventario
📁 Archivo: {$filename}
📅 Generado: " . date('d/m/Y H:i:s') . "

**📈 Estadísticas del reporte:**
• Total de productos: {$stats['total']}
• Productos activos: {$stats['activos']}
• En reparación: {$stats['reparacion']}
• Dados de baja: {$stats['baja']}
• Valor total: $" . number_format($stats['valor_total'], 0, ',', '.') . "

**📋 Contenido del reporte:**
• Lista completa de productos
• Categorías y ubicaciones
• Estados y valores
• Fechas de registro
• Información de proveedores

**⬇️ Descargar reporte:**
<a href='{$downloadUrl}' class='btn btn-primary' download>
📥 Descargar Reporte Excel
</a>

El archivo está listo para descargar. Haz clic en el botón de arriba para descargarlo.";
        
    } catch (Exception $e) {
        return "❌ **Error al generar el reporte:**
        
" . $e->getMessage() . "

Puedes generar reportes manualmente desde la sección 'Reportes'.";
    }
}

function generateExcelReport($data, $filename) {
    // require_once 'vendor/autoload.php'; // Si usas PhpSpreadsheet
    
    // Crear directorio temporal si no existe
    $tempDir = 'temp_reports/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $filepath = $tempDir . $filename;
    
    // Generar CSV como alternativa (más simple)
    $fp = fopen($filepath, 'w');
    
    // Headers
    fputcsv($fp, [
        'Código', 'Nombre', 'Categoría', 'Marca', 'Modelo', 
        'Ubicación', 'Proveedor', 'Estado', 'Cantidad', 
        'Valor Compra', 'Fecha Adquisición', 'Observaciones'
    ]);
    
    // Datos (P. = préstamos activos junto a la cantidad)
    foreach ($data as $row) {
        $cant = $row['cantidad_disponible'];
        if (!empty($row['cantidad_prestada_activa']) && (int)$row['cantidad_prestada_activa'] > 0) {
            $cant .= ' (P. ' . (int)$row['cantidad_prestada_activa'] . ')';
        }
        fputcsv($fp, [
            $row['codigo'],
            $row['nombre'],
            $row['categoria'] ?? 'Sin categoría',
            $row['marca'] ?? '',
            $row['modelo'] ?? '',
            $row['ubicacion'] ?? 'Sin ubicación',
            $row['proveedor'] ?? 'Sin proveedor',
            ucfirst(str_replace('_', ' ', $row['estado'])),
            $cant,
            '$' . number_format($row['valor_compra'] ?? 0, 0, ',', '.'),
            $row['fecha_adquisicion'] ?? 'N/A',
            $row['observaciones'] ?? ''
        ]);
    }
    
    fclose($fp);
    return $filepath;
}

function sendReportByEmail($email, $filepath, $filename) {
    // Configuración básica de email
    $to = $email;
    $subject = 'Reporte de Inventario - ' . SITE_NAME;
    $message = "
    <html>
    <body>
        <h2>📊 Reporte de Inventario</h2>
        <p>Hola,</p>
        <p>Se ha generado un reporte completo de tu inventario.</p>
        <p><strong>Detalles del reporte:</strong></p>
        <ul>
            <li>Fecha de generación: " . date('d/m/Y H:i:s') . "</li>
            <li>Archivo: {$filename}</li>
            <li>Formato: Excel (CSV)</li>
        </ul>
        <p>El archivo está adjunto a este email.</p>
        <p>Saludos,<br>Sistema de Inventario</p>
    </body>
    </html>
    ";
    
    // Headers para email HTML
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . SITE_NAME . ' <noreply@inventario.com>',
        'Reply-To: noreply@inventario.com'
    ];
    
    // Enviar email con archivo adjunto
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function getDefaultResponse($message) {
    return "Entiendo tu pregunta. Te ayudo con información sobre tu inventario:

**📊 Puedo consultar:**
• Cantidad de productos
• Estado del inventario
• Valor total
• Estadísticas generales

**🤖 También puedo:**
• Procesar facturas con IA
• Ayudarte con el uso del sistema
• Explicar funcionalidades

**📥 Generar reportes:**
• 'Hazme un reporte del inventario'
• 'Genera un reporte y envíamelo'
• 'Crea un reporte del inventario'

**💡 Ejemplos:**
• '¿Cuántos monitores tengo?'
• '¿Cuál es el valor total?'
• '¿Cómo procesar una factura?'
• 'Hazme un reporte del inventario'

¿Qué te gustaría saber específicamente? 😊";
}

function getUsersAndRolesInfo() {
    global $db;
    $usuarios = $db->fetchAll("SELECT nombre, rol FROM usuarios");
    if (!$usuarios) return "No hay usuarios registrados.";
    $res = "👥 <b>Usuarios y roles:</b><ul>";
    foreach ($usuarios as $u) {
        $res .= "<li>" . htmlspecialchars($u['nombre']) . " - <b>" . htmlspecialchars($u['rol']) . "</b></li>";
    }
    $res .= "</ul>";
    return $res;
}
function getAdminInfo() {
    global $db;
    $admin = $db->fetch("SELECT nombre FROM usuarios WHERE rol = 'administrador' LIMIT 1");
    if ($admin) return "👑 El administrador es: <b>" . htmlspecialchars($admin['nombre']) . "</b>";
    return "No hay administrador registrado.";
}
function getActiveSessionsInfo() {
    // Simulación básica
    return "Actualmente solo puedes ver tu propia sesión activa. En futuras versiones podrás ver usuarios conectados en tiempo real.";
}
function getHistoryInfo() {
    return "Puedes ver el historial de cambios y auditoría en la sección 'Historial' o 'Reportes'.";
}
function getBackupInfo() {
    return "Para hacer una copia de seguridad, respalda la base de datos MySQL y la carpeta del sistema. Para restaurar, importa el backup en tu gestor de base de datos.";
}
function getSupportInfo() {
    return "Para soporte técnico, contacta al administrador del sistema o escribe a soporte@tusistema.com. También puedes consultar la documentación o la sección de ayuda.";
}
?> 