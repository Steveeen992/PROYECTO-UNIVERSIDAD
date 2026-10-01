<?php
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Trabajador: solo ver categorías (no agregar, editar ni eliminar)
if (isTrabajador() && in_array($action, ['add', 'edit', 'eliminar', 'delete'], true)) {
    showMessage('No tienes permiso para realizar esta acción.', 'error');
    redirect('categorias.php');
}

$effective_user_id = null;

if ($action === 'eliminar' && is_numeric($id)) {
    try {
        $relacionados = ($effective_user_id === null)
            ? $db->fetch("SELECT COUNT(*) AS total FROM inventario WHERE categoria_id = ?", [$id])
            : $db->fetch("SELECT COUNT(*) AS total FROM inventario WHERE categoria_id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
        if ($relacionados && $relacionados['total'] > 0) {
            showMessage('No se puede eliminar: hay productos en esta categoría', 'error');
        } else {
            if ($effective_user_id === null) {
                $db->query("DELETE FROM categorias WHERE id = ?", [$id]);
            } else {
                $db->query("DELETE FROM categorias WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
            }
            showMessage('Categoría eliminada exitosamente', 'success');
        }
        redirect('categorias.php');
    } catch (Exception $e) {
        showMessage('Error al eliminar: ' . $e->getMessage(), 'error');
    }
}


// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $nombre = sanitize($_POST['nombre']);
        $descripcion = sanitize($_POST['descripcion']);
        
        if (empty($nombre)) {
            showMessage('El nombre es obligatorio', 'error');
        } else {
            try {
                if ($action === 'add') {
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM categorias WHERE nombre = ?", [$nombre])
                        : $db->fetch("SELECT id FROM categorias WHERE nombre = ? AND usuario_registro = ?", [$nombre, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('Ya existe una categoría con ese nombre', 'error');
                    } else {
                        $db->query(
                            "INSERT INTO categorias (nombre, descripcion, usuario_registro) VALUES (?, ?, ?)",
                            [$nombre, $descripcion, $_SESSION['user_id']]
                        );
                        showMessage('Categoría agregada exitosamente', 'success');
                        redirect('categorias.php');
                    }
                } else {
                    $existing = ($effective_user_id === null)
                        ? $db->fetch("SELECT id FROM categorias WHERE nombre = ? AND id != ?", [$nombre, $id])
                        : $db->fetch("SELECT id FROM categorias WHERE nombre = ? AND id != ? AND usuario_registro = ?", [$nombre, $id, $_SESSION['user_id']]);
                    if ($existing) {
                        showMessage('Ya existe una categoría con ese nombre', 'error');
                    } else {
                        if ($effective_user_id === null) {
                            $db->query("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?", [$nombre, $descripcion, $id]);
                        } else {
                            $db->query("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ? AND usuario_registro = ?", [$nombre, $descripcion, $id, $_SESSION['user_id']]);
                        }
                        showMessage('Categoría actualizada exitosamente', 'success');
                        redirect('categorias.php');
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
                ? $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE categoria_id = ?", [$id])
                : $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE categoria_id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
            if ($elementos['total'] > 0) {
                showMessage('No se puede eliminar la categoría porque tiene elementos asociados', 'error');
            } else {
                if ($effective_user_id === null) {
                    $db->query("DELETE FROM categorias WHERE id = ?", [$id]);
                } else {
                    $db->query("DELETE FROM categorias WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
                }
                showMessage('Categoría eliminada exitosamente', 'success');
            }
            redirect('categorias.php');
        } catch (Exception $e) {
            showMessage('Error al eliminar la categoría', 'error');
        }
    }
}

// Obtener datos para editar
$categoria = null;
if ($action === 'edit' && $id) {
    $categoria = ($effective_user_id === null)
        ? $db->fetch("SELECT * FROM categorias WHERE id = ?", [$id])
        : $db->fetch("SELECT * FROM categorias WHERE id = ? AND usuario_registro = ?", [$id, $_SESSION['user_id']]);
    if (!$categoria) {
        showMessage('Categoría no encontrada', 'error');
        redirect('categorias.php');
    }
}

// Lista: todas para admin/editor, solo propias para trabajador
if ($effective_user_id === null) {
    $categorias = $db->fetchAll(
        "SELECT c.*, u.nombre as usuario_registro, COUNT(i.id) as total_elementos
         FROM categorias c
         LEFT JOIN usuarios u ON c.usuario_registro = u.id
         LEFT JOIN inventario i ON c.id = i.categoria_id
         GROUP BY c.id
         ORDER BY c.nombre"
    );
} else {
    $categorias = $db->fetchAll(
        "SELECT c.*, u.nombre as usuario_registro, COUNT(i.id) as total_elementos
         FROM categorias c
         LEFT JOIN usuarios u ON c.usuario_registro = u.id
         LEFT JOIN inventario i ON c.id = i.categoria_id AND i.usuario_registro = ?
         WHERE c.usuario_registro = ?
         GROUP BY c.id
         ORDER BY c.nombre",
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
    <title>Categorías - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php $nav_active = 'categorias'; include 'include_nav.php'; ?>

    <div class="container">
        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <h1>Gestión de Categorías</h1>
                <?php if (canManageCategories()): ?>
                <a href="categorias.php?action=add" class="btn btn-primary">Agregar Categoría</a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Tarjetas de categorías -->
            <div class="categories-grid">
                <?php if (empty($categorias)): ?>
                    <div class="empty-state">
                        <h3>No hay categorías registradas</h3>
                        <p>Comienza agregando tu primera categoría para organizar tu inventario.</p>
                        <?php if (canManageCategories()): ?>
                        <a href="categorias.php?action=add" class="btn btn-primary">Agregar Primera Categoría</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($categorias as $cat): ?>
                        <div class="category-card">
                            <div class="category-header">
                                <h3><?php echo htmlspecialchars($cat['nombre']); ?></h3>
                                <?php if (canManageCategories()): ?>
                                <div class="category-actions">
                                    <a href="categorias.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <?php if ($cat['total_elementos'] == 0): ?>
                                        <a href="categorias.php?action=eliminar&id=<?php echo $cat['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Está seguro de eliminar esta categoría?')">Eliminar</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="category-body">
                                <p class="category-description">
                                    <?php echo $cat['descripcion'] ? htmlspecialchars($cat['descripcion']) : 'Sin descripción'; ?>
                                </p>
                                
                                <div class="category-stats">
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $cat['total_elementos']; ?></span>
                                        <span class="stat-label">Elementos</span>
                                    </div>
                                </div>
                                
                                <div class="category-meta">
                                    <small>Creado por: <?php echo htmlspecialchars($cat['usuario_registro'] ?? 'Sistema'); ?></small>
                                    <small>Fecha: <?php echo date('d/m/Y', strtotime($cat['fecha_registro'])); ?></small>
                                </div>
                            </div>
                            
                            <?php if ($cat['total_elementos'] > 0): ?>
                                <div class="category-footer">
                                    <a href="inventario.php?categoria=<?php echo $cat['id']; ?>" class="btn btn-light">
                                        Ver Elementos
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <div class="page-header">
                <h1><?php echo $action === 'add' ? 'Agregar' : 'Editar'; ?> Categoría</h1>
                <a href="categorias.php" class="btn btn-secondary">Volver al listado</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo $message['message']; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" class="form">
                    <div class="form-group">
                        <label for="nombre">Nombre de la Categoría *</label>
                        <input type="text" id="nombre" name="nombre" required 
                               value="<?php echo htmlspecialchars($categoria['nombre'] ?? ''); ?>"
                               placeholder="Ej: Equipos de Cómputo, Mobiliario, Herramientas">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="4" 
                                  placeholder="Describe qué tipo de elementos incluye esta categoría..."><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? 'Crear' : 'Actualizar'; ?> Categoría
                        </button>
                        <a href="categorias.php" class="btn btn-secondary">Cancelar</a>
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
        /* Estilos adicionales para las tarjetas de categorías */
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