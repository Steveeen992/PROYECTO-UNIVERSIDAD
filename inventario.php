
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Trabajador: solo ver inventario (no agregar, editar, eliminar ni retirar)
if (isTrabajador() && in_array($action, ['add', 'edit', 'eliminar', 'withdraw'], true)) {
    showMessage('No tienes permiso para realizar esta acción.', 'error');
    redirect('inventario.php');
}
// Solo administradora puede eliminar productos
if ($action === 'eliminar' && !canDeleteProducts()) {
    showMessage('No tienes permiso para eliminar elementos.', 'error');
    redirect('inventario.php');
}
// Editor y administradora pueden retirar; trabajador no
if ($action === 'withdraw' && !canRetirarElementos()) {
    showMessage('No tienes permiso para retirar elementos.', 'error');
    redirect('inventario.php');
}

// Todos los roles ven el mismo inventario global; permisos de edición en roles.php
$effective_user_id = null;

if ($action === 'eliminar' && is_numeric($id)) {
    $id = (int)$id;

    try {
        // Eliminar registros relacionados (préstamos activos)
        // IMPORTANTE: NO eliminamos el historial de elementos para preservar los registros históricos de retiros
        $db->query("DELETE FROM prestamos WHERE elemento_id = ?", [$id]);
        
        // Solo eliminar registros de historial que NO sean retiros (preservar historial de retiros)
        $db->query("DELETE FROM historial_elementos WHERE elemento_id = ? AND tipo_evento != 'retiro'", [$id]);

        // Luego eliminar el producto
        $db->query("DELETE FROM inventario WHERE id = ?", [$id]);

        showMessage('Elemento eliminado exitosamente. Los registros históricos de retiros se han preservado.', 'success');
        redirect('inventario.php');
    } catch (Exception $e) {
        showMessage('Error al eliminar: ' . $e->getMessage(), 'error');
    }
}

// Obtener categorías, ubicaciones y proveedores del usuario actual
$categorias = function_exists('getUserCategorias') ? getUserCategorias($db, $_SESSION['user_id']) : 
    $db->fetchAll("SELECT * FROM categorias WHERE usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);
$ubicaciones = function_exists('getUserUbicaciones') ? getUserUbicaciones($db, $_SESSION['user_id']) : 
    $db->fetchAll("SELECT * FROM ubicaciones WHERE usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);
$proveedores = function_exists('getUserProveedores') ? getUserProveedores($db, $_SESSION['user_id']) : 
    $db->fetchAll("SELECT * FROM proveedores WHERE usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);

// Función para generar el siguiente código automático del usuario actual
function generarSiguienteCodigo($db, $user_id) {
    // Obtener el último código usado del usuario actual
    $ultimoCodigo = $db->fetch("SELECT codigo FROM inventario WHERE usuario_registro = ? ORDER BY CAST(codigo AS UNSIGNED) DESC LIMIT 1", [$user_id]);
    
    if (!$ultimoCodigo) {
        // Si no hay códigos, empezar con 0001
        return '0001';
    }
    
    // Convertir el código a número y sumar 1
    $numero = intval($ultimoCodigo['codigo']);
    $siguiente = $numero + 1;
    
    // Formatear con ceros a la izquierda (4 dígitos)
    return str_pad($siguiente, 4, '0', STR_PAD_LEFT);
}

// Obtener el siguiente código que no exista en toda la tabla (evita duplicados cuando codigo es UNIQUE global)
function obtenerSiguienteCodigoDisponibleGlobal($db) {
    $r = $db->fetch("SELECT COALESCE(MAX(CAST(codigo AS UNSIGNED)), 0) + 1 AS next_code FROM inventario");
    $next = $r ? (int)$r['next_code'] : 1;
    return str_pad($next, 4, '0', STR_PAD_LEFT);
}

// Generar código automático solo para nuevos elementos (global para admin/editor)
$codigoAutomatico = null;
if ($action === 'add') {
    $codigoAutomatico = ($effective_user_id === null) ? obtenerSiguienteCodigoDisponibleGlobal($db) : generarSiguienteCodigo($db, $_SESSION['user_id']);
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        // Para nuevos elementos, usar código automático; para edición, usar el código del formulario
        if ($action === 'add') {
            $codigo = $codigoAutomatico;
        } else {
        $codigo = sanitize($_POST['codigo']);
        }
        
        $nombre = sanitize($_POST['nombre']);
        $categoria_id = $_POST['categoria_id'] ?: null;
        $marca = sanitize($_POST['marca']);
        $modelo = sanitize($_POST['modelo']);
        $ubicacion_id = $_POST['ubicacion_id'] ?: null;
        $estado = $_POST['estado'];
        $fecha_adquisicion = $_POST['fecha_adquisicion'] ?: null;
        $valor_compra = $_POST['valor_compra'] ?: null;
        $observaciones = sanitize($_POST['observaciones']);
        // Nueva variable para la cantidad disponible
        $cantidad_disponible = $_POST['cantidad_disponible'] ?? 1; // Valor predeterminado de 1

        if (empty($nombre)) {
            showMessage('El nombre es obligatorio', 'error');
        } else {
            try {
                if ($action === 'add') {
                    // Verificar que el código no exista (global para admin/editor)
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM inventario WHERE codigo = ?", [$codigo])
                        : $db->fetch("SELECT id FROM inventario WHERE codigo = ? AND usuario_registro = ?", [$codigo, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('El código ya existe en el inventario', 'error');
                    } else {
                        $proveedor_id = $_POST['proveedor_id'] ?: null;
                        $maxReintentos = 5;
                        $insertado = false;
                        for ($reintento = 0; $reintento < $maxReintentos && !$insertado; $reintento++) {
                            try {
                                $db->query(
                                    "INSERT INTO inventario (codigo, nombre, categoria_id, marca, modelo, ubicacion_id, proveedor_id, estado, fecha_adquisicion, valor_compra, observaciones, cantidad_disponible, usuario_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                    [$codigo, $nombre, $categoria_id, $marca, $modelo, $ubicacion_id, $proveedor_id, $estado, $fecha_adquisicion, $valor_compra, $observaciones, $cantidad_disponible, $_SESSION['user_id']]
                                );
                                $insertado = true;
                            } catch (PDOException $e) {
                                // Duplicate entry para key 'codigo' (1062) o Integrity constraint (23000)
                                if ($e->getCode() == '23000' || strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                                    // Usar el siguiente código disponible en toda la tabla (evita repetir 0001 si ya existe)
                                    $codigo = obtenerSiguienteCodigoDisponibleGlobal($db);
                                    continue;
                                }
                                throw $e;
                            }
                        }
                        if (!$insertado) {
                            showMessage('No se pudo asignar un código único. Intenta de nuevo.', 'error');
                        } else {
                            // Crear registro en historial
                            $elemento_id = $db->lastInsertId();
                            $db->query(
                                "INSERT INTO historial_elementos (elemento_id, tipo_evento, fecha_evento, descripcion, usuario_registro) VALUES (?, 'adquisicion', ?, ?, ?)",
                                [$elemento_id, $fecha_adquisicion ?: date('Y-m-d'), "Elemento agregado al inventario con {$cantidad_disponible} unidades", $_SESSION['user_id']]
                            );
                            showMessage('Elemento agregado exitosamente', 'success');
                            redirect('inventario.php');
                        }
                    }
                } else {
                    // Verificar que el código no exista en otro elemento del usuario actual
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM inventario WHERE codigo = ? AND id != ?", [$codigo, $id])
                        : $db->fetch("SELECT id FROM inventario WHERE codigo = ? AND id != ? AND usuario_registro = ?", [$codigo, $id, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('El código ya existe en otro elemento de tu inventario', 'error');
                    } else {
                        $proveedor_id = $_POST['proveedor_id'] ?: null;
                        $db->query(
                            "UPDATE inventario SET codigo = ?, nombre = ?, categoria_id = ?, marca = ?, modelo = ?, ubicacion_id = ?, proveedor_id = ?, estado = ?, fecha_adquisicion = ?, valor_compra = ?, observaciones = ?, cantidad_disponible = ? WHERE id = ?",
                            [$codigo, $nombre, $categoria_id, $marca, $modelo, $ubicacion_id, $proveedor_id, $estado, $fecha_adquisicion, $valor_compra, $observaciones, $cantidad_disponible, $id]
                        );
                        
                        showMessage('Elemento actualizado exitosamente', 'success');
                        redirect('inventario.php');
                    }
                }
            } catch (Exception $e) {
                showMessage('Error al procesar la solicitud: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    // Nueva lógica para procesar el formulario de retiro
    if ($action === 'withdraw' && $id) {
        $cantidad_a_retirar = $_POST['cantidad'] ?? null;
        $receptor = sanitize($_POST['receptor'] ?? '');
        $fecha_entrega = $_POST['fecha_entrega'] ?? null;
        $finalidad = sanitize($_POST['finalidad'] ?? '');

        if (empty($cantidad_a_retirar) || empty($receptor) || empty($fecha_entrega) || empty($finalidad) || !is_numeric($cantidad_a_retirar) || $cantidad_a_retirar <= 0) {
            showMessage('Todos los campos de retiro son obligatorios y la cantidad debe ser un número positivo.', 'error');
        } else {
            try {
                // Obtener información del elemento y su cantidad actual
                $elemento_a_retirar = $db->fetch("SELECT nombre, codigo, cantidad_disponible FROM inventario WHERE id = ?", [$id]);
                if (!$elemento_a_retirar) {
                    showMessage('El elemento a retirar no fue encontrado.', 'error');
                    redirect('inventario.php');
                }

                // Validar que haya suficientes unidades para retirar
                if ($cantidad_a_retirar > $elemento_a_retirar['cantidad_disponible']) {
                    showMessage('No hay suficientes unidades disponibles para retirar. Cantidad actual: ' . $elemento_a_retirar['cantidad_disponible'], 'error');
                } else {
                    // Actualizar la cantidad disponible en la tabla inventario
                    $nueva_cantidad = $elemento_a_retirar['cantidad_disponible'] - $cantidad_a_retirar;
                    $db->query("UPDATE inventario SET cantidad_disponible = ? WHERE id = ?", [$nueva_cantidad, $id]);

                    // Insertar registro en historial de elementos
                    $descripcion_historial = "Retiro de {$cantidad_a_retirar} unidades de '{$elemento_a_retirar['nombre']}' ({$elemento_a_retirar['codigo']}) a {$receptor} con finalidad: {$finalidad}. Cantidad restante: {$nueva_cantidad}.";
                    
                    $db->query(
                        "INSERT INTO historial_elementos (elemento_id, tipo_evento, fecha_evento, descripcion, usuario_registro) VALUES (?, 'retiro', ?, ?, ?)",
                        [$id, $fecha_entrega, $descripcion_historial, $_SESSION['user_id']]
                    );

                    showMessage('Retiro registrado exitosamente y cantidad actualizada', 'success');
                    redirect('inventario.php');
                }

            } catch (Exception $e) {
                showMessage('Error al registrar el retiro: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    if ($action === 'eliminar' && is_numeric($id)) {
        
      
    $id = (int)$id;

    try {
        // Eliminar primero registros dependientes (préstamos activos)
        // IMPORTANTE: NO eliminamos el historial de retiros para preservar los registros históricos
        $db->query("DELETE FROM prestamos WHERE elemento_id = ?", [$id]);
        
        // Solo eliminar registros de historial que NO sean retiros (preservar historial de retiros)
        $db->query("DELETE FROM historial_elementos WHERE elemento_id = ? AND tipo_evento != 'retiro'", [$id]);

        // Luego eliminar el elemento del inventario
        $db->query("DELETE FROM inventario WHERE id = ?", [$id]);

        showMessage('Elemento eliminado exitosamente. Los registros históricos de retiros se han preservado.', 'success');
        
        exit;
    } catch (PDOException $e) {
        showMessage('Error al eliminar: ' . $e->getMessage(), 'error');
    }
}

}

// Obtener datos para editar o retirar (admin/editor: cualquier elemento; trabajador: solo los suyos)
$elemento = null;
if (($action === 'edit' || $action === 'withdraw') && $id) {
    if ($effective_user_id === null) {
        $elemento = $db->fetch("SELECT * FROM inventario WHERE id = ?", [$id]);
    } else {
        $elemento = $db->fetch("SELECT * FROM inventario WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
    }
    if (!$elemento) {
        showMessage('Elemento no encontrado o no tienes permisos para editarlo', 'error');
        redirect('inventario.php');
    }
}

// Obtener lista de elementos con filtros
$where_conditions = [];
$params = [];

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(i.codigo LIKE ? OR i.nombre LIKE ? OR i.marca LIKE ? OR i.modelo LIKE ?)";
    $params = array_merge($params, [$search, $search, $search, $search]);
}

if (!empty($_GET['categoria'])) {
    $where_conditions[] = "i.categoria_id = ?";
    $params[] = $_GET['categoria'];
}

if (!empty($_GET['estado'])) {
    $where_conditions[] = "i.estado = ?";
    $params[] = $_GET['estado'];
}

if (!empty($_GET['ubicacion'])) {
    $where_conditions[] = "i.ubicacion_id = ?";
    $params[] = $_GET['ubicacion'];
}

if (!empty($_GET['proveedor'])) {
    $where_conditions[] = "i.proveedor_id = ?";
    $params[] = $_GET['proveedor'];
}

// Admin/editor: ver todo el inventario; trabajador: solo el suyo
if ($effective_user_id !== null) {
    $where_conditions[] = "i.usuario_registro = ?";
    $params[] = $_SESSION['user_id'];
}
$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

if ($effective_user_id === null) {
    $elementos = $db->fetchAll(
        "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion, p.nombre as proveedor, us.nombre as usuario_registro
         FROM inventario i
         LEFT JOIN categorias c ON i.categoria_id = c.id
         LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id
         LEFT JOIN proveedores p ON i.proveedor_id = p.id
         LEFT JOIN usuarios us ON i.usuario_registro = us.id
         $where_clause
         ORDER BY i.fecha_registro DESC",
        $params
    );
} else {
    $elementos = $db->fetchAll(
        "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion, p.nombre as proveedor, us.nombre as usuario_registro
         FROM inventario i
         LEFT JOIN categorias c ON i.categoria_id = c.id AND c.usuario_registro = ?
         LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id AND u.usuario_registro = ?
         LEFT JOIN proveedores p ON i.proveedor_id = p.id AND p.usuario_registro = ?
         LEFT JOIN usuarios us ON i.usuario_registro = us.id
         $where_clause
         ORDER BY i.fecha_registro DESC",
        array_merge([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']], $params)
    );
}

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php $nav_active = 'inventario'; include 'include_nav.php'; ?>

    <div class="container">
        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <h1>Gestión de Inventario</h1>
                <div class="header-actions">
                    <a href="chat_ai.php" class="btn btn-success">🤖 Procesar Factura con IA</a>
                    <?php if (canAddEditProducts()): ?>
                    <a href="inventario.php?action=add" class="btn btn-primary">Agregar Elemento</a>
                    <?php endif; ?>
                    <a href="reporte_inventario.php" class="btn btn-success">Reporte</a>
                    <?php if (canRetirarElementos()): ?>
                    <a href="historial_retiros.php?action=add" class="btn btn-info">Historial de Retiros</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
            <?php endif; ?>

            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Buscar..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        
                        <select name="categoria">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($_GET['categoria'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo ($_GET['estado'] ?? '') == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="en_reparacion" <?php echo ($_GET['estado'] ?? '') == 'en_reparacion' ? 'selected' : ''; ?>>En Reparación</option>
                            <option value="dado_de_baja" <?php echo ($_GET['estado'] ?? '') == 'dado_de_baja' ? 'selected' : ''; ?>>Dado de Baja</option>
                            <option value="perdido" <?php echo ($_GET['estado'] ?? '') == 'perdido' ? 'selected' : ''; ?>>Perdido</option>
                        </select>
                        
                        <select name="ubicacion">
                            <option value="">Todas las ubicaciones</option>
                            <?php foreach ($ubicaciones as $ubi): ?>
                                <option value="<?php echo $ubi['id']; ?>" <?php echo ($_GET['ubicacion'] ?? '') == $ubi['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ubi['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="proveedor">
                            <option value="">Todos los proveedores</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id']; ?>" <?php echo ($_GET['proveedor'] ?? '') == $prov['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-secondary">Filtrar</button>
                        <a href="inventario.php" class="btn btn-light">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Ubicación</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Unidades</th>
                            <th>Valor Unitario</th>
                            <th>Valor Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($elementos)): ?>
                            <tr>
                                <td colspan="12" class="text-center">No se encontraron elementos</td> </tr>
                        <?php else: ?>
                            <?php foreach ($elementos as $elemento): ?>
                                <?php
                                // Obtener la cantidad prestada de este elemento
                                $prestados = ($effective_user_id === null)
                                ? $db->fetch("SELECT COALESCE(SUM(cantidad_prestada),0) as total FROM prestamos WHERE elemento_id = ? AND estado = 'activo'", [$elemento['id']])
                                : $db->fetch("SELECT COALESCE(SUM(cantidad_prestada),0) as total FROM prestamos WHERE elemento_id = ? AND estado = 'activo' AND usuario_registro = ?", [$elemento['id'], $_SESSION['user_id']]);
                                $cantidad_prestada = $prestados ? $prestados['total'] : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($elemento['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($elemento['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($elemento['categoria'] ?? 'Sin categoría'); ?></td>
                                    <td><?php echo htmlspecialchars($elemento['marca']); ?></td>
                                    <td><?php echo htmlspecialchars($elemento['modelo']); ?></td>
                                    <td><?php echo htmlspecialchars($elemento['ubicacion'] ?? 'Sin ubicación'); ?></td>
                                    <td><?php echo htmlspecialchars($elemento['proveedor'] ?? 'Sin proveedor'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $elemento['estado']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $elemento['estado'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($elemento['cantidad_disponible']); ?>
                                        <?php if ($cantidad_prestada > 0): ?>
                                            <span style="color:#888;font-size:0.95em;">(P. <?php echo $cantidad_prestada; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo isset($elemento['valor_compra']) && $elemento['valor_compra'] !== '' && $elemento['valor_compra'] !== null ? '$' . number_format((float)$elemento['valor_compra'], 2) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $valor_unit = isset($elemento['valor_compra']) && $elemento['valor_compra'] !== '' && $elemento['valor_compra'] !== null ? (float)$elemento['valor_compra'] : 0;
                                        $cant = (int)($elemento['cantidad_disponible'] ?? 0);
                                        $valor_total_fila = $valor_unit * $cant;
                                        echo $valor_total_fila > 0 ? '$' . number_format($valor_total_fila, 2) : '-';
                                        ?>
                                    </td>
                                    <td class="actions">
                                        <?php if (canAddEditProducts()): ?>
                                        <a href="inventario.php?action=edit&id=<?php echo $elemento['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <?php endif; ?>
                                        <?php if (canRetirarElementos() && $elemento['cantidad_disponible'] > 0): ?>
                                            <a href="inventario.php?action=withdraw&id=<?php echo $elemento['id']; ?>" class="btn btn-sm btn-info">Retirar</a>
                                        <?php endif; ?>
                                        <?php if (canDeleteProducts()): ?>
                                        <a href="inventario.php?action=eliminar&id=<?php echo $elemento['id']; ?>" 
                                        class="btn btn-sm btn-danger" 
                                        onclick="return confirm('¿Estás seguro?')">Eliminar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <div class="page-header">
                <h1><?php echo $action === 'add' ? 'Agregar' : 'Editar'; ?> Elemento</h1>
                <a href="inventario.php" class="btn btn-secondary">Volver al listado</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo">Código *</label>
                            <?php if ($action === 'add'): ?>
                                <div class="barcode-input-container">
                                    <input type="text" id="codigo" name="codigo" required 
                                           value="<?php echo htmlspecialchars($codigoAutomatico); ?>" 
                                           placeholder="Escanear código de barras o ingresar manualmente"
                                           class="barcode-input">
                                    <button type="button" id="toggleBarcodeMode" class="btn btn-sm btn-secondary">
                                        <i class="barcode-icon"></i> Modo Escáner
                                    </button>
                                </div>
                                <small class="form-text">Código generado automáticamente. Puedes escanear un código de barras o ingresar manualmente.</small>
                            <?php else: ?>
                            <input type="text" id="codigo" name="codigo" required 
                                       value="<?php echo htmlspecialchars($elemento['codigo'] ?? ''); ?>"
                                       placeholder="Escanear código de barras o ingresar manualmente">
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" required 
                                   value="<?php echo htmlspecialchars($elemento['nombre'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoria_id">Categoría</label>
                            <select id="categoria_id" name="categoria_id">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo ($elemento['categoria_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ubicacion_id">Ubicación</label>
                            <select id="ubicacion_id" name="ubicacion_id">
                                <option value="">Seleccionar ubicación</option>
                                <?php foreach ($ubicaciones as $ubi): ?>
                                    <option value="<?php echo $ubi['id']; ?>" 
                                            <?php echo ($elemento['ubicacion_id'] ?? '') == $ubi['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ubi['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="proveedor_id">Proveedor</label>
                            <select id="proveedor_id" name="proveedor_id">
                                <option value="">Seleccionar proveedor</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id']; ?>" 
                                            <?php echo ($elemento['proveedor_id'] ?? '') == $prov['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prov['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="marca">Marca</label>
                            <input type="text" id="marca" name="marca" 
                                   value="<?php echo htmlspecialchars($elemento['marca'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="modelo">Modelo</label>
                            <input type="text" id="modelo" name="modelo" 
                                   value="<?php echo htmlspecialchars($elemento['modelo'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado" required>
                                <option value="activo" <?php echo ($elemento['estado'] ?? 'activo') == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="en_reparacion" <?php echo ($elemento['estado'] ?? '') == 'en_reparacion' ? 'selected' : ''; ?>>En Reparación</option>
                                <option value="dado_de_baja" <?php echo ($elemento['estado'] ?? '') == 'dado_de_baja' ? 'selected' : ''; ?>>Dado de Baja</option>
                                <option value="perdido" <?php echo ($elemento['estado'] ?? '') == 'perdido' ? 'selected' : ''; ?>>Perdido</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fecha_adquisicion">Fecha de Adquisición</label>
                            <input type="date" id="fecha_adquisicion" name="fecha_adquisicion" 
                                   value="<?php echo $elemento['fecha_adquisicion'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="valor_compra">Valor de Compra</label>
                            <input type="number" id="valor_compra" name="valor_compra" step="0.01" 
                                   value="<?php echo $elemento['valor_compra'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="cantidad_disponible">Unidades Disponibles *</label> <input type="number" id="cantidad_disponible" name="cantidad_disponible" min="1" required 
                                   value="<?php echo htmlspecialchars($elemento['cantidad_disponible'] ?? 1); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="4"><?php echo htmlspecialchars($elemento['observaciones'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? 'Agregar' : 'Actualizar'; ?> Elemento
                        </button>
                        <a href="inventario.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php elseif ($action === 'withdraw' && $elemento): // Nueva sección para el formulario de retiro ?>
            <div class="page-header">
                <h1>Retirar Elemento: <?php echo htmlspecialchars($elemento['nombre']); ?> (<?php echo htmlspecialchars($elemento['codigo']); ?>)</h1>
                <p>Unidades disponibles: **<?php echo htmlspecialchars($elemento['cantidad_disponible']); ?>**</p>
                <a href="inventario.php" class="btn btn-secondary">Volver al listado</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
                
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" class="form">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($elemento['id']); ?>">
                    <div class="form-group">
                        <label for="cantidad">Cantidad a Retirar *</label>
                        <input type="number" id="cantidad" name="cantidad" min="1" max="<?php echo htmlspecialchars($elemento['cantidad_disponible']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="receptor">A Quién se Entrega *</label>
                        <input type="text" id="receptor" name="receptor" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_entrega">Fecha de Entrega *</label>
                        <input type="date" id="fecha_entrega" name="fecha_entrega" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="finalidad">Con Qué Fin *</label>
                        <textarea id="finalidad" name="finalidad" rows="3" required></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Confirmar Retiro</button>
                        <a href="inventario.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Funcionalidad del dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdownMenu = this.parentElement.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        dropdownMenu.classList.toggle('show');
                        if (dropdownMenu.classList.contains('show')) {
                            dropdownMenu.style.display = 'block';
                        } else {
                            dropdownMenu.style.display = 'none';
                        }
                    }
                });
            }

            // Cerrar dropdown al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.remove('show');
                        menu.style.display = 'none';
                    });
                }
            });
        });

        // Auto-submit del formulario de filtros al cambiar los selects
        document.querySelectorAll('.filter-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Funcionalidad para escáner de códigos de barras
        const codigoInput = document.getElementById('codigo');
        const toggleBarcodeMode = document.getElementById('toggleBarcodeMode');
        let barcodeMode = false;
        let barcodeBuffer = '';
        let barcodeTimeout;

        if (codigoInput) {
            // Si no hay botón de toggle (modo edición), crear funcionalidad básica
            if (!toggleBarcodeMode) {
                // Para modo edición, detectar automáticamente entrada de escáner
                codigoInput.addEventListener('keydown', function(e) {
                    if (e.key.length === 1) {
                        barcodeBuffer += e.key;
                        clearTimeout(barcodeTimeout);
                        
                        barcodeTimeout = setTimeout(() => {
                            if (barcodeBuffer.length > 0) {
                                codigoInput.value = barcodeBuffer;
                                barcodeBuffer = '';
                                showNotification('Código de barras escaneado: ' + codigoInput.value, 'success');
                                
                                // Mover al siguiente campo
                                const nextField = codigoInput.parentElement.nextElementSibling.querySelector('input');
                                if (nextField) {
                                    nextField.focus();
                                }
                            }
                        }, 100);
                    }
                });
            } else {
                // Modo con toggle (nuevos elementos)
            // Toggle modo escáner
            toggleBarcodeMode.addEventListener('click', function() {
                barcodeMode = !barcodeMode;
                if (barcodeMode) {
                    this.innerHTML = '<i class="barcode-icon"></i> Modo Manual';
                    this.classList.add('btn-primary');
                    this.classList.remove('btn-secondary');
                    codigoInput.placeholder = 'Escanear código de barras...';
                    codigoInput.focus();
                    showNotification('Modo escáner activado. Escanea un código de barras.', 'info');
                } else {
                    this.innerHTML = '<i class="barcode-icon"></i> Modo Escáner';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-secondary');
                    codigoInput.placeholder = 'Escanear código de barras o ingresar manualmente';
                    showNotification('Modo manual activado.', 'info');
                }
            });

            // Detectar entrada de escáner (códigos de barras se envían rápidamente)
            codigoInput.addEventListener('keydown', function(e) {
                if (barcodeMode) {
                    // En modo escáner, capturar cada carácter
                    if (e.key.length === 1) {
                        barcodeBuffer += e.key;
                        clearTimeout(barcodeTimeout);
                        
                        barcodeTimeout = setTimeout(() => {
                            if (barcodeBuffer.length > 0) {
                                // Procesar el código de barras completo
                                codigoInput.value = barcodeBuffer;
                                barcodeBuffer = '';
                                showNotification('Código de barras escaneado: ' + codigoInput.value, 'success');
                                
                                // Mover al siguiente campo
                                const nextField = codigoInput.parentElement.nextElementSibling.querySelector('input');
                                if (nextField) {
                                    nextField.focus();
                                }
                            }
                        }, 100); // Esperar 100ms para confirmar que es un escáner
                    }
                }
            });

            // Para entrada manual normal
            codigoInput.addEventListener('input', function() {
                if (!barcodeMode) {
                    // En modo manual, permitir edición normal
                    barcodeBuffer = '';
                }
            });

            // Cambiar estilo visual cuando está en modo escáner
            toggleBarcodeMode.addEventListener('click', function() {
                if (barcodeMode) {
                    codigoInput.classList.add('scanner-mode');
                } else {
                    codigoInput.classList.remove('scanner-mode');
                }
            });
        }

        // Función para mostrar notificaciones
        function showNotification(message, type = 'info') {
            // Crear notificación temporal
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} notification`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                max-width: 300px;
                animation: slideIn 0.3s ease-out;
            `;
            
            document.body.appendChild(notification);
            
            // Remover después de 3 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
    </script>

    <style>
        /* Estilos para el campo de código automático */
        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        input[readonly] {
            background-color: #f8f9fa !important;
            color: #6c757d !important;
            cursor: not-allowed;
        }
        
        input[readonly]:focus {
            box-shadow: none;
            border-color: #ced4da;
        }

        /* Estilos para el contenedor de códigos de barras */
        .barcode-input-container {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .barcode-input {
            flex: 1;
        }

        .barcode-icon {
            margin-right: 5px;
        }

        /* Animaciones para notificaciones */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .notification {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
        }

        /* Estilos para el modo escáner activo */
        .barcode-input.scanner-mode {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }

        /* Responsive para móviles */
        @media (max-width: 768px) {
            .barcode-input-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .barcode-input-container button {
                margin-top: 5px;
            }
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</body>
</html>