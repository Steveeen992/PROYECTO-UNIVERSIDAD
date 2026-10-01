<?php
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Trabajador: solo ver préstamos (no registrar, editar, eliminar ni devolver)
if (isTrabajador() && in_array($action, ['add', 'edit', 'eliminar', 'return'], true)) {
    showMessage('No tienes permiso para realizar esta acción.', 'error');
    redirect('prestamos.php');
}

$effective_user_id = null;

if ($action === 'eliminar' && is_numeric($id)) {
    try {
        if ($effective_user_id === null) {
            $db->query("DELETE FROM prestamos WHERE id = ?", [$id]);
        } else {
            $db->query("DELETE FROM prestamos WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
        }
        showMessage('Préstamo eliminado exitosamente', 'success');
        redirect('prestamos.php');
    } catch (Exception $e) {
        showMessage('Error al eliminar el préstamo: ' . $e->getMessage(), 'error');
    }
}

// Elementos del inventario para formularios (todos para admin/editor)
$elementos_inventario = ($effective_user_id === null)
    ? $db->fetchAll("SELECT id, codigo, nombre, cantidad_disponible FROM inventario WHERE estado = 'activo' ORDER BY nombre", [])
    : $db->fetchAll("SELECT id, codigo, nombre, cantidad_disponible FROM inventario WHERE estado = 'activo' AND usuario_registro = ? ORDER BY nombre", [$_SESSION['user_id']]);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $elemento_id = $_POST['elemento_id'];
        $cantidad_prestada = $_POST['cantidad_prestada'] ?? 1;
        $prestatario = sanitize($_POST['prestatario']);
        $contacto_prestatario = sanitize($_POST['contacto_prestatario']);
        $fecha_prestamo = $_POST['fecha_prestamo'] ?: date('Y-m-d');
        $fecha_devolucion_esperada = $_POST['fecha_devolucion_esperada'];
        $proposito = sanitize($_POST['proposito']);
        $observaciones = sanitize($_POST['observaciones']);

        $firma_receptor_imagen = trim($_POST['firma_receptor_imagen'] ?? '');
                $nombre_receptor = trim(sanitize($_POST['nombre_receptor'] ?? ''));
                if (empty($nombre_receptor)) {
                    $nombre_receptor = $prestatario;
                }
                $firma_autorizador_imagen = trim($_POST['firma_autorizador_imagen'] ?? '');
                $requiere_firma = ($action === 'add');
                if ($requiere_firma && (empty($firma_receptor_imagen) || !preg_match('/^data:image\//', $firma_receptor_imagen))) {
                    showMessage('Debe capturar la firma de quien recibe el producto antes de crear el préstamo.', 'error');
                } elseif ($requiere_firma && (empty($firma_autorizador_imagen) || !preg_match('/^data:image\//', $firma_autorizador_imagen))) {
                    showMessage('Debe capturar su firma como autorizador del préstamo antes de crear el préstamo.', 'error');
                } elseif (empty($elemento_id) || empty($prestatario) || empty($fecha_devolucion_esperada) || empty($proposito) || $cantidad_prestada <= 0) {
            showMessage('Todos los campos obligatorios deben ser completados y la cantidad debe ser mayor a 0', 'error');
        } else {
            try {
                // Verificar que el elemento existe y está activo
                $elemento = ($effective_user_id === null)
                    ? $db->fetch("SELECT nombre, codigo, cantidad_disponible FROM inventario WHERE id = ? AND estado = 'activo'", [$elemento_id])
                    : $db->fetch("SELECT nombre, codigo, cantidad_disponible FROM inventario WHERE id = ? AND estado = 'activo' AND usuario_registro = ?", [$elemento_id, $_SESSION['user_id']]);
                if (!$elemento) {
                    showMessage('El elemento seleccionado no existe o no está activo', 'error');
                } else {
                    $prestados_actuales = ($effective_user_id === null)
                        ? $db->fetch("SELECT COALESCE(SUM(cantidad_prestada), 0) as total_prestado FROM prestamos WHERE elemento_id = ? AND estado = 'activo'", [$elemento_id])
                        : $db->fetch("SELECT COALESCE(SUM(cantidad_prestada), 0) as total_prestado FROM prestamos WHERE elemento_id = ? AND estado = 'activo' AND usuario_registro = ?", [$elemento_id, $_SESSION['user_id']]);
                    $cantidad_disponible_prestamo = $elemento['cantidad_disponible'] - $prestados_actuales['total_prestado'];
                    
                    if ($action === 'add') {
                        if ($cantidad_prestada > $cantidad_disponible_prestamo) {
                            showMessage("No hay suficientes unidades disponibles para préstamo. Disponibles: {$cantidad_disponible_prestamo}, En inventario: {$elemento['cantidad_disponible']}, Ya prestadas: {$prestados_actuales['total_prestado']}", 'error');
                        } else {
                            $db->query(
                                "INSERT INTO prestamos (elemento_id, cantidad_prestada, prestatario, contacto_prestatario, fecha_prestamo, fecha_devolucion_esperada, proposito, observaciones, estado, usuario_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo', ?)",
                                [$elemento_id, $cantidad_prestada, $prestatario, $contacto_prestatario, $fecha_prestamo, $fecha_devolucion_esperada, $proposito, $observaciones, $_SESSION['user_id']]
                            );
                            
                            // Crear registro en historial
                            $prestamo_id = $db->lastInsertId();
                            $db->query(
                                "INSERT INTO historial_elementos (elemento_id, tipo_evento, fecha_evento, descripcion, usuario_registro) VALUES (?, 'prestamo', ?, ?, ?)",
                                [$elemento_id, $fecha_prestamo, "Préstamo de {$cantidad_prestada} unidades a {$prestatario} con propósito: {$proposito}", $_SESSION['user_id']]
                            );
                            // Crear documento de entrega y guardar ambas firmas (receptor y autorizador)
                            try {
                                $db->query("INSERT INTO documentos_prestamo (prestamo_id) VALUES (?)", [$prestamo_id]);
                                $db->query(
                                    "UPDATE documentos_prestamo SET firmado_receptor = 1, fecha_firma_receptor = NOW(), nombre_receptor = ?, firma_receptor_imagen = ?, firmado_autorizador = 1, fecha_firma_autorizador = NOW(), usuario_autorizador_id = ?, firma_autorizador_imagen = ? WHERE prestamo_id = ?",
                                    [$nombre_receptor, $firma_receptor_imagen, $_SESSION['user_id'], $firma_autorizador_imagen, $prestamo_id]
                                );
                            } catch (Exception $e) {}
                            
                            showMessage('Préstamo registrado exitosamente. El documento de entrega incluye las firmas del receptor y del autorizador. Puede verlo en Documentos de préstamos.', 'success');
                            redirect('prestamos.php');
                        }
                    } else {
                        // En edición, verificar disponibilidad excluyendo el préstamo actual
                        $prestamo_actual = ($effective_user_id === null)
                            ? $db->fetch("SELECT cantidad_prestada FROM prestamos WHERE id = ?", [$id])
                            : $db->fetch("SELECT cantidad_prestada FROM prestamos WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
                        $cantidad_disponible_edicion = $cantidad_disponible_prestamo + $prestamo_actual['cantidad_prestada'];
                        
                        if ($cantidad_prestada > $cantidad_disponible_edicion) {
                            showMessage("No hay suficientes unidades disponibles para este cambio. Disponibles: {$cantidad_disponible_edicion}", 'error');
                        } else {
                            if ($effective_user_id === null) {
                                $db->query("UPDATE prestamos SET elemento_id = ?, cantidad_prestada = ?, prestatario = ?, contacto_prestatario = ?, fecha_prestamo = ?, fecha_devolucion_esperada = ?, proposito = ?, observaciones = ? WHERE id = ?", [$elemento_id, $cantidad_prestada, $prestatario, $contacto_prestatario, $fecha_prestamo, $fecha_devolucion_esperada, $proposito, $observaciones, $id]);
                            } else {
                                $db->query("UPDATE prestamos SET elemento_id = ?, cantidad_prestada = ?, prestatario = ?, contacto_prestatario = ?, fecha_prestamo = ?, fecha_devolucion_esperada = ?, proposito = ?, observaciones = ? WHERE id = ? AND usuario_registro = ?", [$elemento_id, $cantidad_prestada, $prestatario, $contacto_prestatario, $fecha_prestamo, $fecha_devolucion_esperada, $proposito, $observaciones, $id, $_SESSION['user_id']]);
                            }
                            showMessage('Préstamo actualizado exitosamente', 'success');
                            redirect('prestamos.php');
                        }
                    }
                }
            } catch (Exception $e) {
                showMessage('Error al procesar la solicitud: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    if ($action === 'return' && $id) {
        $fecha_devolucion_real = $_POST['fecha_devolucion_real'] ?? date('Y-m-d');
        $observaciones_devolucion = sanitize($_POST['observaciones_devolucion']);
        $estado_devolucion = $_POST['estado_devolucion'] ?? 'devuelto_completo';

        try {
            $prestamo = ($effective_user_id === null)
                ? $db->fetch("SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo FROM prestamos p JOIN inventario i ON p.elemento_id = i.id WHERE p.id = ?", [$id])
                : $db->fetch("SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo FROM prestamos p JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ? WHERE p.id = ? AND p.usuario_registro = ?", [$_SESSION['user_id'], $id, $_SESSION['user_id']]);
            if (!$prestamo) {
                showMessage('Préstamo no encontrado', 'error');
                redirect('prestamos.php');
            }
            if ($effective_user_id === null) {
                $db->query("UPDATE prestamos SET estado = ?, fecha_devolucion_real = ?, observaciones_devolucion = ? WHERE id = ?", [$estado_devolucion, $fecha_devolucion_real, $observaciones_devolucion, $id]);
            } else {
                $db->query("UPDATE prestamos SET estado = ?, fecha_devolucion_real = ?, observaciones_devolucion = ? WHERE id = ? AND usuario_registro = ?", [$estado_devolucion, $fecha_devolucion_real, $observaciones_devolucion, $id, $_SESSION['user_id']]);
            }
            
            // Crear registro en historial
            $descripcion_devolucion = "Devolución de préstamo: {$prestamo['cantidad_prestada']} unidades de '{$prestamo['elemento_nombre']}' ({$prestamo['elemento_codigo']}) por {$prestamo['prestatario']}. Estado: " . str_replace('_', ' ', $estado_devolucion);
            
            $db->query(
                "INSERT INTO historial_elementos (elemento_id, tipo_evento, fecha_evento, descripcion, usuario_registro) VALUES (?, 'devolucion', ?, ?, ?)",
                [$prestamo['elemento_id'], $fecha_devolucion_real, $descripcion_devolucion, $_SESSION['user_id']]
            );
            
            showMessage('Devolución registrada exitosamente', 'success');
            redirect('prestamos.php');
        } catch (Exception $e) {
            showMessage('Error al registrar la devolución: ' . $e->getMessage(), 'error');
        }
    }
    
    if ($action === 'delete' && $id) {
        try {
            if ($effective_user_id === null) {
                $db->query("DELETE FROM prestamos WHERE id = ?", [$id]);
            } else {
                $db->query("DELETE FROM prestamos WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
            }
            showMessage('Préstamo eliminado exitosamente', 'success');
            redirect('prestamos.php');
        } catch (Exception $e) {
            showMessage('Error al eliminar el préstamo: ' . $e->getMessage(), 'error');
        }
    }
}

// Obtener datos para editar o devolver
$prestamo = null;
if (($action === 'edit' || $action === 'return') && $id) {
    $prestamo = ($effective_user_id === null)
        ? $db->fetch("SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo FROM prestamos p JOIN inventario i ON p.elemento_id = i.id WHERE p.id = ?", [$id])
        : $db->fetch("SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo FROM prestamos p JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ? WHERE p.id = ? AND p.usuario_registro = ?", [$_SESSION['user_id'], $id, $_SESSION['user_id']]);
    if (!$prestamo) {
        showMessage('Préstamo no encontrado', 'error');
        redirect('prestamos.php');
    }
}

// Obtener lista de préstamos con filtros
$where_conditions = [];
$params = [];
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(i.codigo LIKE ? OR i.nombre LIKE ? OR p.prestatario LIKE ?)";
    $params = array_merge($params, [$search, $search, $search]);
}
if (!empty($_GET['estado'])) {
    $where_conditions[] = "p.estado = ?";
    $params[] = $_GET['estado'];
}
$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

if ($effective_user_id === null) {
    $prestamos = $db->fetchAll(
        "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo, i.cantidad_disponible, us.nombre as usuario_registro
         FROM prestamos p
         JOIN inventario i ON p.elemento_id = i.id
         LEFT JOIN usuarios us ON p.usuario_registro = us.id
         $where_clause
         ORDER BY p.fecha_prestamo DESC",
        $params
    );
    $stats = [
        'total_activos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo'")['count'],
        'total_vencidos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND fecha_devolucion_esperada < CURDATE()")['count'],
        'total_devueltos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado IN ('devuelto_completo', 'devuelto_parcial')")['count'],
        'total_perdidos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'perdido'")['count']
    ];
} else {
    $prestamos = $db->fetchAll(
        "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo, i.cantidad_disponible, us.nombre as usuario_registro
         FROM prestamos p
         JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ?
         LEFT JOIN usuarios us ON p.usuario_registro = us.id
         WHERE p.usuario_registro = ? $where_clause
         ORDER BY p.fecha_prestamo DESC",
        array_merge([$_SESSION['user_id'], $_SESSION['user_id']], $params)
    );
    $stats = [
        'total_activos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'total_vencidos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND fecha_devolucion_esperada < CURDATE() AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'total_devueltos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado IN ('devuelto_completo', 'devuelto_parcial') AND usuario_registro = ?", [$_SESSION['user_id']])['count'],
        'total_perdidos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'perdido' AND usuario_registro = ?", [$_SESSION['user_id']])['count']
    ];
}

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-activo { background: #d4edda; color: #155724; }
        .status-vencido { background: #f8d7da; color: #721c24; }
        .status-devuelto_completo { background: #d1ecf1; color: #0c5460; }
        .status-devuelto_parcial { background: #fff3cd; color: #856404; }
        .status-perdido { background: #f5c6cb; color: #721c24; }
        .overdue { background-color: #fff5f5; }
    </style>
</head>
<body>
    <?php $nav_active = 'prestamos'; include 'include_nav.php'; ?>

    <div class="container">
        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <h1>Gestión de Préstamos</h1>
                <?php if (canManagePrestamos()): ?>
                <a href="prestamos.php?action=add" class="btn btn-primary">Nuevo Préstamo</a>
                <?php endif; ?>
                <a href="documentos_prestamos.php" class="btn btn-primary">Documentos de entrega</a>
                <a href="reporte_prestamos.php?action=add" class="btn btn-primary">Reporte</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_activos']; ?></div>
                    <div class="stat-label">Préstamos Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #dc3545;"><?php echo $stats['total_vencidos']; ?></div>
                    <div class="stat-label">Préstamos Vencidos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #28a745;"><?php echo $stats['total_devueltos']; ?></div>
                    <div class="stat-label">Préstamos Devueltos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #6c757d;"><?php echo $stats['total_perdidos']; ?></div>
                    <div class="stat-label">Préstamos Perdidos</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Buscar por código, elemento o prestatario..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        
                        <select name="estado">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo ($_GET['estado'] ?? '') == 'activo' ? 'selected' : ''; ?>>Activos</option>
                            <option value="devuelto_completo" <?php echo ($_GET['estado'] ?? '') == 'devuelto_completo' ? 'selected' : ''; ?>>Devuelto Completo</option>
                            <option value="devuelto_parcial" <?php echo ($_GET['estado'] ?? '') == 'devuelto_parcial' ? 'selected' : ''; ?>>Devuelto Parcial</option>
                            <option value="perdido" <?php echo ($_GET['estado'] ?? '') == 'perdido' ? 'selected' : ''; ?>>Perdido</option>
                        </select>
                        
                        <button type="submit" class="btn btn-secondary">Filtrar</button>
                        <a href="prestamos.php" class="btn btn-light">Limpiar</a>
                    </div>
                </form>
            </div>

            <!-- Tabla de préstamos -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Elemento</th>
                            <th>Cantidad</th>
                            <th>Prestatario</th>
                            <th>Contacto</th>
                            <th>F. Préstamo</th>
                            <th>F. Esperada</th>
                            <th>F. Real</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prestamos)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No se encontraron préstamos</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prestamos as $prestamo): 
                                $is_overdue = ($prestamo['estado'] === 'activo' && $prestamo['fecha_devolucion_esperada'] < date('Y-m-d'));
                                $row_class = $is_overdue ? 'overdue' : '';
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($prestamo['elemento_codigo']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($prestamo['elemento_nombre']); ?></small>
                                    </td>
                                    <td><?php echo $prestamo['cantidad_prestada']; ?></td>
                                    <td><?php echo htmlspecialchars($prestamo['prestatario']); ?></td>
                                    <td><?php echo htmlspecialchars($prestamo['contacto_prestatario']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])); ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="status-badge status-vencido">VENCIDO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $prestamo['fecha_devolucion_real'] ? date('d/m/Y', strtotime($prestamo['fecha_devolucion_real'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $prestamo['estado']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $prestamo['estado'])); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <?php if (canManagePrestamos()): ?>
                                        <?php if ($prestamo['estado'] === 'activo'): ?>
                                            <a href="prestamos.php?action=return&id=<?php echo $prestamo['id']; ?>" class="btn btn-sm btn-success">Devolver</a>
                                            <a href="prestamos.php?action=edit&id=<?php echo $prestamo['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <?php endif; ?>
                                        <a href="prestamos.php?action=eliminar&id=<?php echo $prestamo['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Está seguro de eliminar este préstamo?')">Eliminar</a>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <div class="page-header">
                <h1><?php echo $action === 'add' ? 'Nuevo' : 'Editar'; ?> Préstamo</h1>
                <a href="prestamos.php" class="btn btn-secondary">Volver al listado</a>
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
                            <label for="elemento_id">Elemento *</label>
                            <select id="elemento_id" name="elemento_id" required onchange="updateAvailableQuantity()">
                                <option value="">Seleccionar elemento</option>
                                <?php foreach ($elementos_inventario as $elemento): ?>
                                    <option value="<?php echo $elemento['id']; ?>" 
                                            data-disponible="<?php echo $elemento['cantidad_disponible']; ?>"
                                            <?php echo ($prestamo['elemento_id'] ?? '') == $elemento['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($elemento['codigo'] . ' - ' . $elemento['nombre'] . ' (Disponible: ' . $elemento['cantidad_disponible'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cantidad_prestada">Cantidad a Prestar *</label>
                            <input type="number" id="cantidad_prestada" name="cantidad_prestada" min="1" required 
                                   value="<?php echo $prestamo['cantidad_prestada'] ?? 1; ?>">
                            <small id="disponible-info"></small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prestatario">Prestatario *</label>
                            <input type="text" id="prestatario" name="prestatario" required 
                                   value="<?php echo htmlspecialchars($prestamo['prestatario'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="contacto_prestatario">Contacto del Prestatario</label>
                            <input type="text" id="contacto_prestatario" name="contacto_prestatario" 
                                   value="<?php echo htmlspecialchars($prestamo['contacto_prestatario'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_prestamo">Fecha de Préstamo</label>
                            <input type="date" id="fecha_prestamo" name="fecha_prestamo" 
                                   value="<?php echo $prestamo['fecha_prestamo'] ?? date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="fecha_devolucion_esperada">Fecha de Devolución Esperada *</label>
                            <input type="date" id="fecha_devolucion_esperada" name="fecha_devolucion_esperada" required 
                                   value="<?php echo $prestamo['fecha_devolucion_esperada'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="proposito">Propósito del Préstamo *</label>
                        <textarea id="proposito" name="proposito" rows="3" required><?php echo htmlspecialchars($prestamo['proposito'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="3"><?php echo htmlspecialchars($prestamo['observaciones'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($action === 'add'): ?>
                    <div class="firmas-prestamo-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-top:1.5rem;">
                        <div class="form-group" style="padding:1rem; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">
                            <h4 style="margin:0 0 0.75rem 0; font-size:1rem;">Firma de quien recibe el producto *</h4>
                            <p class="text-muted" style="font-size:0.9rem; margin-bottom:0.75rem;">La persona que recibe el préstamo debe firmar aquí.</p>
                            <label for="nombre_receptor">Nombre de quien recibe *</label>
                            <input type="text" id="nombre_receptor" name="nombre_receptor" required style="margin-bottom:10px; max-width:100%; display:block;" placeholder="Nombre completo" value="">
                            <label style="display:block; margin-top:8px;">Firma *</label>
                            <canvas id="canvas_firma_receptor" width="280" height="100" style="border:1px solid #ccc; background:#fff; cursor:crosshair; display:block; border-radius:4px; touch-action:none; max-width:100%;"></canvas>
                            <input type="hidden" name="firma_receptor_imagen" id="firma_receptor_imagen" value="">
                            <button type="button" id="btn_limpiar_firma" class="btn btn-light" style="margin-top:8px;">Limpiar</button>
                        </div>
                        <div class="form-group" style="padding:1rem; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">
                            <h4 style="margin:0 0 0.75rem 0; font-size:1rem;">Firma de quien autoriza el préstamo *</h4>
                            <p class="text-muted" style="font-size:0.9rem; margin-bottom:0.75rem;">Usted debe firmar aquí como autorizador. Esta firma quedará en el documento de entrega.</p>
                            <label style="display:block; margin-top:8px;">Su firma *</label>
                            <canvas id="canvas_firma_autorizador" width="280" height="100" style="border:1px solid #ccc; background:#fff; cursor:crosshair; display:block; border-radius:4px; touch-action:none; max-width:100%;"></canvas>
                            <input type="hidden" name="firma_autorizador_imagen" id="firma_autorizador_imagen" value="">
                            <button type="button" id="btn_limpiar_firma_autorizador" class="btn btn-light" style="margin-top:8px;">Limpiar</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="btn_submit_prestamo">
                            <?php echo $action === 'add' ? 'Crear' : 'Actualizar'; ?> Préstamo
                        </button>
                        <a href="prestamos.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <?php if ($action === 'add'): ?>
            <script>
            (function() {
                var form = document.querySelector('.form-container form');
                var nombreReceptor = document.getElementById('nombre_receptor');
                var prestatario = document.getElementById('prestatario');
                if (!form) return;

                function initCanvas(canvasId, inputId) {
                    var canvas = document.getElementById(canvasId);
                    var inputFirma = document.getElementById(inputId);
                    if (!canvas || !inputFirma) return null;
                    var ctx = canvas.getContext('2d');
                    ctx.strokeStyle = '#000';
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    var drawing = false;
                    function getPos(e) {
                        var rect = canvas.getBoundingClientRect();
                        var scaleX = canvas.width / rect.width, scaleY = canvas.height / rect.height;
                        if (e.touches && e.touches.length) {
                            return { x: (e.touches[0].clientX - rect.left) * scaleX, y: (e.touches[0].clientY - rect.top) * scaleY };
                        }
                        return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
                    }
                    canvas.addEventListener('mousedown', function(e) { drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
                    canvas.addEventListener('mousemove', function(e) { if (!drawing) return; var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
                    canvas.addEventListener('mouseup', function() { drawing = false; });
                    canvas.addEventListener('mouseout', function() { drawing = false; });
                    canvas.addEventListener('touchstart', function(e) { e.preventDefault(); drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }, { passive: false });
                    canvas.addEventListener('touchmove', function(e) { e.preventDefault(); var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }, { passive: false });
                    canvas.addEventListener('touchend', function(e) { e.preventDefault(); drawing = false; }, { passive: false });
                    return { canvas: canvas, ctx: ctx, input: inputFirma };
                }

                var receptor = initCanvas('canvas_firma_receptor', 'firma_receptor_imagen');
                var autorizador = initCanvas('canvas_firma_autorizador', 'firma_autorizador_imagen');

                document.getElementById('btn_limpiar_firma').addEventListener('click', function() {
                    if (receptor) { receptor.ctx.clearRect(0, 0, receptor.canvas.width, receptor.canvas.height); receptor.input.value = ''; }
                });
                document.getElementById('btn_limpiar_firma_autorizador').addEventListener('click', function() {
                    if (autorizador) { autorizador.ctx.clearRect(0, 0, autorizador.canvas.width, autorizador.canvas.height); autorizador.input.value = ''; }
                });

                if (prestatario && nombreReceptor) {
                    prestatario.addEventListener('blur', function() {
                        if (!nombreReceptor.value.trim()) nombreReceptor.value = prestatario.value.trim();
                    });
                }

                function hasStroke(ctx, canvas) {
                    var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    for (var i = 0; i < imageData.data.length; i += 4) {
                        if (imageData.data[i + 3] > 0) return true;
                    }
                    return false;
                }

                form.addEventListener('submit', function(e) {
                    if (!receptor || !autorizador) return;
                    if (!hasStroke(receptor.ctx, receptor.canvas)) {
                        e.preventDefault();
                        alert('Debe capturar la firma de quien recibe el producto.');
                        return;
                    }
                    if (!hasStroke(autorizador.ctx, autorizador.canvas)) {
                        e.preventDefault();
                        alert('Debe capturar su firma como autorizador del préstamo.');
                        return;
                    }
                    receptor.input.value = receptor.canvas.toDataURL('image/png');
                    autorizador.input.value = autorizador.canvas.toDataURL('image/png');
                });
            })();
            </script>
            <?php endif; ?>

        <?php elseif ($action === 'return' && $prestamo): ?>
            <div class="page-header">
                <h1>Devolver Préstamo</h1>
                <p><strong>Elemento:</strong> <?php echo htmlspecialchars($prestamo['elemento_codigo'] . ' - ' . $prestamo['elemento_nombre']); ?></p>
                <p><strong>Prestatario:</strong> <?php echo htmlspecialchars($prestamo['prestatario']); ?></p>
                <p><strong>Cantidad prestada:</strong> <?php echo $prestamo['cantidad_prestada']; ?></p>
                <a href="prestamos.php" class="btn btn-secondary">Volver al listado</a>
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
                            <label for="fecha_devolucion_real">Fecha de Devolución Real *</label>
                            <input type="date" id="fecha_devolucion_real" name="fecha_devolucion_real" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="estado_devolucion">Estado de la Devolución *</label>
                            <select id="estado_devolucion" name="estado_devolucion" required>
                                <option value="devuelto_completo">Devuelto Completo</option>
                                <option value="devuelto_parcial">Devuelto Parcial</option>
                                <option value="perdido">Perdido</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observaciones_devolucion">Observaciones de la Devolución</label>
                        <textarea id="observaciones_devolucion" name="observaciones_devolucion" rows="4" 
                                  placeholder="Describa el estado en que se devuelve el elemento, daños, etc."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Confirmar Devolución</button>
                        <a href="prestamos.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Mejorar la experiencia del dropdown
        document.querySelector('.dropdown-toggle').addEventListener('click', function(e) {
            e.preventDefault();
            this.parentElement.querySelector('.dropdown-menu').classList.toggle('show');
        });

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Auto-submit del formulario de filtros al cambiar los selects
        document.querySelectorAll('.filter-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Función para actualizar la cantidad disponible
        function updateAvailableQuantity() {
            const select = document.getElementById('elemento_id');
            const cantidadInput = document.getElementById('cantidad_prestada');
            const infoDiv = document.getElementById('disponible-info');
            
            if (select.selectedIndex > 0) {
                const option = select.options[select.selectedIndex];
                const disponible = option.getAttribute('data-disponible');
                cantidadInput.max = disponible;
                infoDiv.textContent = `Máximo disponible para préstamo: ${disponible}`;
                infoDiv.style.color = '#666';
            } else {
                cantidadInput.max = '';
                infoDiv.textContent = '';
            }
        }

        // Inicializar la información disponible si hay un elemento seleccionado
        document.addEventListener('DOMContentLoaded', function() {
            updateAvailableQuantity();
        });
    </script>
</body>
</html>