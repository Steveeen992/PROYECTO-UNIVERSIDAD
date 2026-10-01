<?php
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if (isTrabajador() && in_array($action, ['add', 'edit', 'eliminar', 'delete'], true)) {
    showMessage('No tienes permiso para realizar esta acción.', 'error');
    redirect('ubicaciones.php');
}

$effective_user_id = null;

if ($action === 'eliminar' && is_numeric($id)) {
    try {
        $relacionados = ($effective_user_id === null)
            ? $db->fetch("SELECT COUNT(*) AS total FROM inventario WHERE ubicacion_id = ?", [$id])
            : $db->fetch("SELECT COUNT(*) AS total FROM inventario WHERE ubicacion_id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
        if ($relacionados && $relacionados['total'] > 0) {
            showMessage('No se puede eliminar: hay productos en esta ubicación', 'error');
        } else {
            if ($effective_user_id === null) {
                $db->query("DELETE FROM ubicaciones WHERE id = ?", [$id]);
            } else {
                $db->query("DELETE FROM ubicaciones WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
            }
            showMessage('Ubicación eliminada exitosamente', 'success');
        }
        redirect('ubicaciones.php');
    } catch (Exception $e) {
        showMessage('Error al eliminar: ' . $e->getMessage(), 'error');
    }
}


// Funciones auxiliares
function getLocationIcon($tipo) {
    $icons = [
        'oficina' => '',
        'almacen' => '',
        'aula' => '',
        'laboratorio' => '',
        'otro' => ''
    ];
    return $icons[$tipo] ?? '';
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $nombre = sanitize($_POST['nombre']);
        $descripcion = sanitize($_POST['descripcion']);
        $tipo = $_POST['tipo'];
        
        if (empty($nombre)) {
            showMessage('El nombre es obligatorio', 'error');
        } else {
            try {
                if ($action === 'add') {
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM ubicaciones WHERE nombre = ?", [$nombre])
                        : $db->fetch("SELECT id FROM ubicaciones WHERE nombre = ? AND usuario_registro = ?", [$nombre, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('Ya existe una ubicación con ese nombre', 'error');
                    } else {
                        $db->query(
                            "INSERT INTO ubicaciones (nombre, descripcion, tipo, usuario_registro) VALUES (?, ?, ?, ?)",
                            [$nombre, $descripcion, $tipo, $_SESSION['user_id']]
                        );
                        showMessage('Ubicación agregada exitosamente', 'success');
                        redirect('ubicaciones.php');
                    }
                } else {
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM ubicaciones WHERE nombre = ? AND id != ?", [$nombre, $id])
                        : $db->fetch("SELECT id FROM ubicaciones WHERE nombre = ? AND id != ? AND usuario_registro = ?", [$nombre, $id, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('Ya existe una ubicación con ese nombre', 'error');
                    } else {
                        if ($effective_user_id === null) {
                            $db->query("UPDATE ubicaciones SET nombre = ?, descripcion = ?, tipo = ? WHERE id = ?", [$nombre, $descripcion, $tipo, $id]);
                        } else {
                            $db->query("UPDATE ubicaciones SET nombre = ?, descripcion = ?, tipo = ? WHERE id = ? AND usuario_registro = ?", [$nombre, $descripcion, $tipo, $id, $_SESSION['user_id']]);
                        }
                        showMessage('Ubicación actualizada exitosamente', 'success');
                        redirect('ubicaciones.php');
                    }
                }
            } catch (Exception $e) {
                showMessage('Error al procesar la solicitud', 'error');
            }
        }
    }
    
    if ($action === 'delete' && $id) {
        try {
            $elementos = ($effective_user_id === null)
                ? $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE ubicacion_id = ?", [$id])
                : $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE ubicacion_id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
            if ($elementos['total'] > 0) {
                showMessage('No se puede eliminar la ubicación porque tiene elementos asociados', 'error');
            } else {
                if ($effective_user_id === null) {
                    $db->query("DELETE FROM ubicaciones WHERE id = ?", [$id]);
                } else {
                    $db->query("DELETE FROM ubicaciones WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
                }
                showMessage('Ubicación eliminada exitosamente', 'success');
            }
            redirect('ubicaciones.php');
        } catch (Exception $e) {
            showMessage('Error al eliminar la ubicación', 'error');
        }
    }
}

// Obtener datos para editar
$ubicacion = null;
if ($action === 'edit' && $id) {
    $ubicacion = ($effective_user_id === null)
        ? $db->fetch("SELECT * FROM ubicaciones WHERE id = ?", [$id])
        : $db->fetch("SELECT * FROM ubicaciones WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
    if (!$ubicacion) {
        showMessage('Ubicación no encontrada', 'error');
        redirect('ubicaciones.php');
    }
}

// Lista: todas para admin/editor, solo propias para trabajador
if ($effective_user_id === null) {
    $ubicaciones = $db->fetchAll(
        "SELECT u.*, us.nombre as usuario_registro, COUNT(i.id) as total_elementos
         FROM ubicaciones u
         LEFT JOIN usuarios us ON u.usuario_registro = us.id
         LEFT JOIN inventario i ON u.id = i.ubicacion_id
         GROUP BY u.id
         ORDER BY u.tipo, u.nombre"
    );
} else {
    $ubicaciones = $db->fetchAll(
        "SELECT u.*, us.nombre as usuario_registro, COUNT(i.id) as total_elementos
         FROM ubicaciones u
         LEFT JOIN usuarios us ON u.usuario_registro = us.id
         LEFT JOIN inventario i ON u.id = i.ubicacion_id AND i.usuario_registro = ?
         WHERE u.usuario_registro = ?
         GROUP BY u.id
         ORDER BY u.tipo, u.nombre",
        [$_SESSION['user_id'], $_SESSION['user_id']]
    );
}

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubicaciones - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php $nav_active = 'ubicaciones'; include 'include_nav.php'; ?>

    <div class="container">
        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <h1>Gestión de Ubicaciones</h1>
                <?php if (canManageUbicaciones()): ?>
                <a href="ubicaciones.php?action=add" class="btn btn-primary">Agregar Ubicación</a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Tarjetas de ubicaciones agrupadas por tipo -->
            <?php if (empty($ubicaciones)): ?>
                <div class="empty-state">
                    <h3>No hay ubicaciones registradas</h3>
                    <p>Comienza agregando ubicaciones para organizar dónde se encuentran tus elementos.</p>
                    <?php if (canManageUbicaciones()): ?>
                    <a href="ubicaciones.php?action=add" class="btn btn-primary">Agregar Primera Ubicación</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php
                $tipos = ['oficina' => 'Oficinas', 'almacen' => 'Almacenes', 'aula' => 'Aulas', 'laboratorio' => 'Laboratorios', 'otro' => 'Otros'];
                $ubicaciones_por_tipo = [];
                
                foreach ($ubicaciones as $ubi) {
                    $tipo = $ubi['tipo'] ?: 'otro';
                    $ubicaciones_por_tipo[$tipo][] = $ubi;
                }
                ?>
                
                <?php foreach ($tipos as $tipo_key => $tipo_nombre): ?>
                    <?php if (isset($ubicaciones_por_tipo[$tipo_key])): ?>
                        <div class="location-section">
                            <h2 class="section-title">
                                <span class="section-icon"><?php echo getLocationIcon($tipo_key); ?></span>
                                <?php echo $tipo_nombre; ?>
                                <span class="section-count">(<?php echo count($ubicaciones_por_tipo[$tipo_key]); ?>)</span>
                            </h2>
                            
                            <div class="locations-grid">
                                <?php foreach ($ubicaciones_por_tipo[$tipo_key] as $ubi): ?>
                                    <div class="location-card">
                                        <div class="location-header">
                                            <h3><?php echo htmlspecialchars($ubi['nombre']); ?></h3>
                                            <?php if (canManageUbicaciones()): ?>
                                            <div class="location-actions">
                                                <a href="ubicaciones.php?action=edit&id=<?php echo $ubi['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                                <?php if ($ubi['total_elementos'] == 0): ?>
                                                    <a href="ubicaciones.php?action=eliminar&id=<?php echo $ubi['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('¿Está seguro de eliminar esta ubicación?')">Eliminar</a>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="location-body">
                                            <p class="location-description">
                                                <?php echo $ubi['descripcion'] ? htmlspecialchars($ubi['descripcion']) : 'Sin descripción'; ?>
                                            </p>
                                            
                                            <div class="location-stats">
                                                <div class="stat">
                                                    <span class="stat-number"><?php echo $ubi['total_elementos']; ?></span>
                                                    <span class="stat-label">Elementos</span>
                                                </div>
                                            </div>
                                            
                                            <div class="location-meta">
                                                <small>Creado por: <?php echo htmlspecialchars($ubi['usuario_registro'] ?? 'Sistema'); ?></small>
                                                <small>Fecha: <?php echo date('d/m/Y', strtotime($ubi['fecha_registro'])); ?></small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($ubi['total_elementos'] > 0): ?>
                                            <div class="location-footer">
                                                <a href="inventario.php?ubicacion=<?php echo $ubi['id']; ?>" class="btn btn-light">
                                                    Ver Elementos
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <div class="page-header">
                <h1><?php echo $action === 'add' ? 'Agregar' : 'Editar'; ?> Ubicación</h1>
                <a href="ubicaciones.php" class="btn btn-secondary">Volver al listado</a>
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
                            <label for="nombre">Nombre de la Ubicación *</label>
                            <input type="text" id="nombre" name="nombre" required 
                                   value="<?php echo htmlspecialchars($ubicacion['nombre'] ?? ''); ?>"
                                   placeholder="Ej: Oficina Principal, Almacén A, Laboratorio 1">
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo">Tipo de Ubicación</label>
                            <select id="tipo" name="tipo">
                                <option value="oficina" <?php echo ($ubicacion['tipo'] ?? '') == 'oficina' ? 'selected' : ''; ?>>Oficina</option>
                                <option value="almacen" <?php echo ($ubicacion['tipo'] ?? '') == 'almacen' ? 'selected' : ''; ?>>Almacén</option>
                                <option value="aula" <?php echo ($ubicacion['tipo'] ?? '') == 'aula' ? 'selected' : ''; ?>>Aula</option>
                                <option value="laboratorio" <?php echo ($ubicacion['tipo'] ?? '') == 'laboratorio' ? 'selected' : ''; ?>>Laboratorio</option>
                                <option value="otro" <?php echo ($ubicacion['tipo'] ?? '') == 'otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="4" 
                                  placeholder="Describe esta ubicación, incluyendo detalles como piso, edificio, características especiales..."><?php echo htmlspecialchars($ubicacion['descripcion'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? 'Crear' : 'Actualizar'; ?> Ubicación
                        </button>
                        <a href="ubicaciones.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Funcionalidad del dropdown
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
    </script>

    <style>
        /* Estilos adicionales para ubicaciones */
        .location-section {
            margin-bottom: 40px;
        }

        .section-title {
            color: var(--text-primary);
            font-size: 1.4rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .section-icon {
            font-size: 1.2rem;
        }

        .section-count {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: normal;
        }

        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .location-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .location-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .location-header {
            padding: 20px 20px 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--border-color);
        }

        .location-header h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin: 0;
            flex-grow: 1;
        }

        .location-actions {
            display: flex;
            gap: 8px;
            margin-left: 15px;
        }

        .location-body {
            padding: 20px;
        }

        .location-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .location-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .location-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .location-meta small {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .location-footer {
            padding: 15px 20px;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            margin-top: 40px;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .locations-grid {
                grid-template-columns: 1fr;
            }
            
            .location-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .location-actions {
                margin-left: 0;
                justify-content: flex-end;
            }
            
            .section-title {
                font-size: 1.2rem;
                flex-wrap: wrap;
            }
        }

        /* Form styles específicos */
        .form-container {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            padding: 30px;
            margin-top: 20px;
            box-shadow: var(--shadow);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Animaciones suaves */
        .location-card,
        .location-actions .btn,
        .form-container {
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        /* Estados de botones mejorados */
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-light {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-light:hover {
            background: var(--bg-primary);
            border-color: var(--primary-color);
        }
    </style>
</body>
</html>