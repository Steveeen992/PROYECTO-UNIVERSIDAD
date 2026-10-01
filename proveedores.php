<?php
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if (isTrabajador() && in_array($action, ['add', 'edit', 'eliminar', 'delete'], true)) {
    showMessage('No tienes permiso para realizar esta acción.', 'error');
    redirect('proveedores.php');
}

$effective_user_id = null;

if ($action === 'eliminar' && is_numeric($id)) {
    try {
        $relacionados = ($effective_user_id === null)
            ? $db->fetch("SELECT COUNT(*) AS total FROM inventario WHERE proveedor_id = ?", [$id])
            : $db->fetch("SELECT COUNT(*) AS total FROM inventario WHERE proveedor_id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
        if ($relacionados && $relacionados['total'] > 0) {
            showMessage('No se puede eliminar: hay productos de este proveedor', 'error');
        } else {
            if ($effective_user_id === null) {
                $db->query("DELETE FROM proveedores WHERE id = ?", [$id]);
            } else {
                $db->query("DELETE FROM proveedores WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
            }
            showMessage('Proveedor eliminado exitosamente', 'success');
        }
        redirect('proveedores.php');
    } catch (Exception $e) {
        showMessage('Error al eliminar: ' . $e->getMessage(), 'error');
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $nombre = sanitize($_POST['nombre']);
        $contacto = sanitize($_POST['contacto']);
        $telefono = sanitize($_POST['telefono']);
        $email = sanitize($_POST['email']);
        $direccion = sanitize($_POST['direccion']);
        $descripcion = sanitize($_POST['descripcion']);
        
        if (empty($nombre)) {
            showMessage('El nombre es obligatorio', 'error');
        } else {
            try {
                if ($action === 'add') {
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM proveedores WHERE nombre = ?", [$nombre])
                        : $db->fetch("SELECT id FROM proveedores WHERE nombre = ? AND usuario_registro = ?", [$nombre, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('Ya existe un proveedor con ese nombre', 'error');
                    } else {
                        $db->query(
                            "INSERT INTO proveedores (nombre, contacto, telefono, email, direccion, descripcion, usuario_registro) VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [$nombre, $contacto, $telefono, $email, $direccion, $descripcion, $_SESSION['user_id']]
                        );
                        showMessage('Proveedor agregado exitosamente', 'success');
                        redirect('proveedores.php');
                    }
                } else {
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM proveedores WHERE nombre = ? AND id != ?", [$nombre, $id])
                        : $db->fetch("SELECT id FROM proveedores WHERE nombre = ? AND id != ? AND usuario_registro = ?", [$nombre, $id, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('Ya existe un proveedor con ese nombre', 'error');
                    } else {
                        if ($effective_user_id === null) {
                            $db->query("UPDATE proveedores SET nombre = ?, contacto = ?, telefono = ?, email = ?, direccion = ?, descripcion = ? WHERE id = ?", [$nombre, $contacto, $telefono, $email, $direccion, $descripcion, $id]);
                        } else {
                            $db->query("UPDATE proveedores SET nombre = ?, contacto = ?, telefono = ?, email = ?, direccion = ?, descripcion = ? WHERE id = ? AND usuario_registro = ?", [$nombre, $contacto, $telefono, $email, $direccion, $descripcion, $id, $_SESSION['user_id']]);
                        }
                        showMessage('Proveedor actualizado exitosamente', 'success');
                        redirect('proveedores.php');
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
                ? $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE proveedor_id = ?", [$id])
                : $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE proveedor_id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
            if ($elementos['total'] > 0) {
                showMessage('No se puede eliminar el proveedor porque tiene elementos asociados', 'error');
            } else {
                if ($effective_user_id === null) {
                    $db->query("DELETE FROM proveedores WHERE id = ?", [$id]);
                } else {
                    $db->query("DELETE FROM proveedores WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
                }
                showMessage('Proveedor eliminado exitosamente', 'success');
            }
            redirect('proveedores.php');
        } catch (Exception $e) {
            showMessage('Error al eliminar el proveedor', 'error');
        }
    }
}

// Obtener datos para editar
$proveedor = null;
if ($action === 'edit' && $id) {
    $proveedor = ($effective_user_id === null)
        ? $db->fetch("SELECT * FROM proveedores WHERE id = ?", [$id])
        : $db->fetch("SELECT * FROM proveedores WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
    if (!$proveedor) {
        showMessage('Proveedor no encontrado', 'error');
        redirect('proveedores.php');
    }
}

// Lista: todas para admin/editor, solo propias para trabajador
if ($effective_user_id === null) {
    $proveedores = $db->fetchAll(
        "SELECT p.*, u.nombre as usuario_registro,
                (SELECT COUNT(*) FROM inventario i WHERE i.proveedor_id = p.id) as total_elementos
         FROM proveedores p
         LEFT JOIN usuarios u ON p.usuario_registro = u.id
         ORDER BY p.nombre"
    );
} else {
    $proveedores = $db->fetchAll(
        "SELECT p.*, u.nombre as usuario_registro,
                (SELECT COUNT(*) FROM inventario i WHERE i.proveedor_id = p.id AND i.usuario_registro = ?) as total_elementos
         FROM proveedores p
         LEFT JOIN usuarios u ON p.usuario_registro = u.id
         WHERE p.usuario_registro = ?
         ORDER BY p.nombre",
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
    <title>Proveedores - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php $nav_active = 'proveedores'; include 'include_nav.php'; ?>

    <div class="container">
        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <h1>Gestión de Proveedores</h1>
                <?php if (canManageProveedores()): ?>
                <a href="proveedores.php?action=add" class="btn btn-primary">Agregar Proveedor</a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Tarjetas de proveedores -->
            <div class="categories-grid">
                <?php if (empty($proveedores)): ?>
                    <div class="empty-state">
                        <h3>No hay proveedores registrados</h3>
                        <p>Comienza agregando tu primer proveedor para gestionar tus compras y suministros.</p>
                        <?php if (canManageProveedores()): ?>
                        <a href="proveedores.php?action=add" class="btn btn-primary">Agregar Primer Proveedor</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($proveedores as $prov): ?>
                        <div class="category-card">
                            <div class="category-header">
                                <h3><?php echo htmlspecialchars($prov['nombre']); ?></h3>
                                <?php if (canManageProveedores()): ?>
                                <div class="category-actions">
                                    <a href="proveedores.php?action=edit&id=<?php echo $prov['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <?php if ($prov['total_elementos'] == 0): ?>
                                        <a href="proveedores.php?action=eliminar&id=<?php echo $prov['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Está seguro de eliminar este proveedor?')">Eliminar</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="category-body">
                                <?php if ($prov['descripcion']): ?>
                                    <p class="category-description">
                                        <?php echo htmlspecialchars($prov['descripcion']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="provider-info">
                                    <?php if ($prov['contacto']): ?>
                                        <div class="info-item">
                                            <strong>Contacto:</strong> <?php echo htmlspecialchars($prov['contacto']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($prov['telefono']): ?>
                                        <div class="info-item">
                                            <strong>Teléfono:</strong> 
                                            <a href="tel:<?php echo htmlspecialchars($prov['telefono']); ?>">
                                                <?php echo htmlspecialchars($prov['telefono']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($prov['email']): ?>
                                        <div class="info-item">
                                            <strong>Email:</strong> 
                                            <a href="mailto:<?php echo htmlspecialchars($prov['email']); ?>">
                                                <?php echo htmlspecialchars($prov['email']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($prov['direccion']): ?>
                                        <div class="info-item">
                                            <strong>Dirección:</strong> <?php echo htmlspecialchars($prov['direccion']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="category-stats">
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $prov['total_elementos']; ?></span>
                                        <span class="stat-label">Productos</span>
                                    </div>
                                </div>
                                
                                <div class="category-meta">
                                    <small>Creado por: <?php echo htmlspecialchars($prov['usuario_registro'] ?? 'Sistema'); ?></small>
                                    <small>Fecha: <?php echo date('d/m/Y', strtotime($prov['fecha_registro'])); ?></small>
                                </div>
                            </div>
                            
                            <?php if ($prov['total_elementos'] > 0): ?>
                                <div class="category-footer">
                                    <a href="inventario.php?proveedor=<?php echo $prov['id']; ?>" class="btn btn-light">
                                        Ver Productos
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <div class="page-header">
                <h1><?php echo $action === 'add' ? 'Agregar' : 'Editar'; ?> Proveedor</h1>
                <a href="proveedores.php" class="btn btn-secondary">Volver al listado</a>
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
                            <label for="nombre">Nombre del Proveedor *</label>
                            <input type="text" id="nombre" name="nombre" required 
                                   value="<?php echo htmlspecialchars($proveedor['nombre'] ?? ''); ?>"
                                   placeholder="Ej: Distribuidora ABC, Empresa XYZ">
                        </div>

                        <div class="form-group">
                            <label for="contacto">Persona de Contacto</label>
                            <input type="text" id="contacto" name="contacto" 
                                   value="<?php echo htmlspecialchars($proveedor['contacto'] ?? ''); ?>"
                                   placeholder="Nombre del contacto principal">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" 
                                   value="<?php echo htmlspecialchars($proveedor['telefono'] ?? ''); ?>"
                                   placeholder="Ej: +57 300 123 4567">
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($proveedor['email'] ?? ''); ?>"
                                   placeholder="contacto@proveedor.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <input type="text" id="direccion" name="direccion" 
                               value="<?php echo htmlspecialchars($proveedor['direccion'] ?? ''); ?>"
                               placeholder="Dirección completa del proveedor">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="4" 
                                  placeholder="Describe los productos o servicios que ofrece este proveedor..."><?php echo htmlspecialchars($proveedor['descripcion'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? 'Crear' : 'Actualizar'; ?> Proveedor
                        </button>
                        <a href="proveedores.php" class="btn btn-secondary">Cancelar</a>
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
        /* Estilos adicionales para las tarjetas de proveedores */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .category-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .category-header {
            padding: 20px 20px 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--border-color);
        }

        .category-header h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin: 0;
            flex-grow: 1;
        }

        .category-actions {
            display: flex;
            gap: 8px;
            margin-left: 15px;
        }

        .category-body {
            padding: 20px;
        }

        .category-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .provider-info {
            margin-bottom: 15px;
        }

        .info-item {
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .info-item strong {
            color: var(--text-primary);
            margin-right: 5px;
        }

        .info-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .info-item a:hover {
            text-decoration: underline;
        }

        .category-stats {
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

        .category-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .category-meta small {
            color: var(--text-light);
            font-size: 0.8rem;
        }

        .category-footer {
            padding: 15px 20px;
            background-color: var(--bg-light);
            border-top: 1px solid var(--border-color);
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .category-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .category-actions {
                margin-left: 0;
                justify-content: flex-end;
            }
        }
    </style>
</body>
</html>