<?php
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'view';
$message = getMessage();

// Obtener datos del usuario actual
$usuario = $db->fetch("SELECT * FROM usuarios WHERE id = ?", [$_SESSION['user_id']]);

if (!$usuario) {
    showMessage('Usuario no encontrado', 'error');
    redirect('dashboard.php');
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $nombre = sanitize($_POST['nombre']);
    $email = sanitize($_POST['email']);
    $telefono = sanitize($_POST['telefono']);
    $departamento = sanitize($_POST['departamento']);
    $cargo = sanitize($_POST['cargo']);
    
    // Validar campos obligatorios
    if (empty($nombre) || empty($email)) {
        showMessage('El nombre y email son obligatorios', 'error');
    } else {
        try {
            // Verificar si el email ya existe en otro usuario
            $existing = $db->fetch("SELECT id FROM usuarios WHERE email = ? AND id != ?", [$email, $_SESSION['user_id']]);
            if ($existing) {
                showMessage('El email ya está registrado por otro usuario', 'error');
            } else {
                // Actualizar datos del usuario
                $db->query(
                    "UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, departamento = ?, cargo = ? WHERE id = ?",
                    [$nombre, $email, $telefono, $departamento, $cargo, $_SESSION['user_id']]
                );
                
                // Actualizar datos de sesión
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_email'] = $email;
                
                showMessage('Perfil actualizado exitosamente', 'success');
                redirect('perfil.php');
            }
        } catch (Exception $e) {
            showMessage('Error al actualizar el perfil: ' . $e->getMessage(), 'error');
        }
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'password') {
    $password_actual = $_POST['password_actual'];
    $password_nuevo = $_POST['password_nuevo'];
    $password_confirmar = $_POST['password_confirmar'];
    
    if (empty($password_actual) || empty($password_nuevo) || empty($password_confirmar)) {
        showMessage('Todos los campos de contraseña son obligatorios', 'error');
    } elseif ($password_nuevo !== $password_confirmar) {
        showMessage('Las contraseñas nuevas no coinciden', 'error');
    } elseif (strlen($password_nuevo) < 6) {
        showMessage('La contraseña debe tener al menos 6 caracteres', 'error');
    } else {
        try {
            // Verificar contraseña actual
            $usuario_actual = $db->fetch("SELECT password FROM usuarios WHERE id = ?", [$_SESSION['user_id']]);
            
            if (!password_verify($password_actual, $usuario_actual['password'])) {
                showMessage('La contraseña actual es incorrecta', 'error');
            } else {
                // Actualizar contraseña
                $password_hash = password_hash($password_nuevo, PASSWORD_DEFAULT);
                $db->query("UPDATE usuarios SET password = ? WHERE id = ?", [$password_hash, $_SESSION['user_id']]);
                
                showMessage('Contraseña actualizada exitosamente', 'success');
                redirect('perfil.php');
            }
        } catch (Exception $e) {
            showMessage('Error al actualizar la contraseña: ' . $e->getMessage(), 'error');
        }
    }
}

// Obtener estadísticas del usuario
$stats = [
    'total_productos' => $db->fetch("SELECT COUNT(*) as count FROM inventario WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
    'total_categorias' => $db->fetch("SELECT COUNT(*) as count FROM categorias WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
    'total_ubicaciones' => $db->fetch("SELECT COUNT(*) as count FROM ubicaciones WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
    'total_proveedores' => $db->fetch("SELECT COUNT(*) as count FROM proveedores WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
    'total_prestamos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE usuario_registro = ?", [$_SESSION['user_id']])['count'],
    'prestamos_activos' => $db->fetch("SELECT COUNT(*) as count FROM prestamos WHERE estado = 'activo' AND usuario_registro = ?", [$_SESSION['user_id']])['count']
];

// Obtener actividad reciente
$actividad_reciente = $db->fetchAll(
    "SELECT 'inventario' as tipo, nombre, fecha_registro as fecha, 'Producto agregado' as accion
     FROM inventario 
     WHERE usuario_registro = ?
     UNION ALL
     SELECT 'prestamo' as tipo, CONCAT('Préstamo a ', prestatario) as nombre, fecha_prestamo as fecha, 'Préstamo registrado' as accion
     FROM prestamos 
     WHERE usuario_registro = ?
     ORDER BY fecha DESC
     LIMIT 10",
    [$_SESSION['user_id'], $_SESSION['user_id']]
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .profile-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .profile-info h2 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
        }
        
        .profile-info p {
            margin: 0;
            color: var(--text-secondary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .activity-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .activity-type {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .activity-type.inventario {
            background: #d4edda;
            color: #155724;
        }
        
        .activity-type.prestamo {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .form-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-section h3 {
            margin: 0 0 1rem 0;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        
        .password-form {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .password-form h4 {
            margin: 0 0 1rem 0;
            color: #856404;
        }
        
        .info-grid {
            display: grid;
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--bg-light);
            border-radius: 6px;
        }
        
        .info-item strong {
            color: var(--text-primary);
        }
        
        .info-item span {
            color: var(--text-secondary);
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php $nav_active = 'perfil'; include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Mi Perfil</h1>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Información del Perfil -->
            <div class="profile-section">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($usuario['nombre']); ?></h2>
                        <p><?php echo htmlspecialchars($usuario['email']); ?></p>
                        <p>Miembro desde: <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></p>
                    </div>
                </div>

                <!-- Estadísticas del Usuario -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_productos']; ?></span>
                        <span class="stat-label">Productos</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_categorias']; ?></span>
                        <span class="stat-label">Categorías</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_ubicaciones']; ?></span>
                        <span class="stat-label">Ubicaciones</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_proveedores']; ?></span>
                        <span class="stat-label">Proveedores</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_prestamos']; ?></span>
                        <span class="stat-label">Préstamos</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['prestamos_activos']; ?></span>
                        <span class="stat-label">Activos</span>
                    </div>
                </div>

                <!-- Actividad Reciente -->
                <h3>Actividad Reciente</h3>
                <ul class="activity-list">
                    <?php if (empty($actividad_reciente)): ?>
                        <li class="activity-item">
                            <div class="activity-info">
                                <div class="activity-title">No hay actividad reciente</div>
                                <div class="activity-date">Comienza agregando productos a tu inventario</div>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($actividad_reciente as $actividad): ?>
                            <li class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-title"><?php echo htmlspecialchars($actividad['nombre']); ?></div>
                                    <div class="activity-date"><?php echo date('d/m/Y H:i', strtotime($actividad['fecha'])); ?></div>
                                </div>
                                <span class="activity-type <?php echo $actividad['tipo']; ?>">
                                    <?php echo ucfirst($actividad['tipo']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Formularios de Edición -->
            <div>
                <!-- Editar Información Personal -->
                <div class="form-section">
                    <h3>Información Personal</h3>
                    <form method="POST" action="perfil.php?action=edit">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nombre">Nombre Completo *</label>
                                <input type="text" id="nombre" name="nombre" required 
                                       value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($usuario['email']); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" 
                                       value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="departamento">Departamento</label>
                                <input type="text" id="departamento" name="departamento" 
                                       value="<?php echo htmlspecialchars($usuario['departamento'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="cargo">Cargo</label>
                            <input type="text" id="cargo" name="cargo" 
                                   value="<?php echo htmlspecialchars($usuario['cargo'] ?? ''); ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                        </div>
                    </form>
                </div>

                <!-- Cambiar Contraseña -->
                <div class="form-section">
                    <h3>Seguridad</h3>
                    <div class="password-form">
                        <h4>Cambiar Contraseña</h4>
                        <form method="POST" action="perfil.php?action=password">
                            <div class="form-group">
                                <label for="password_actual">Contraseña Actual *</label>
                                <input type="password" id="password_actual" name="password_actual" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password_nuevo">Nueva Contraseña *</label>
                                    <input type="password" id="password_nuevo" name="password_nuevo" required 
                                           minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="password_confirmar">Confirmar Contraseña *</label>
                                    <input type="password" id="password_confirmar" name="password_confirmar" required 
                                           minlength="6">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información de la Cuenta -->
                <div class="form-section">
                    <h3>Información de la Cuenta</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>ID de Usuario:</strong>
                            <span><?php echo $usuario['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Rol:</strong>
                            <span><?php echo ucfirst($usuario['rol'] ?? 'usuario'); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Estado:</strong>
                            <span class="badge badge-success">Activo</span>
                        </div>
                        <div class="info-item">
                            <strong>Último Acceso:</strong>
                            <span><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funcionalidad del dropdown
        document.querySelector('.dropdown-toggle').addEventListener('click', function(e) {
            e.preventDefault();
            this.parentElement.querySelector('.dropdown-menu').classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Validación de contraseñas
        document.querySelector('form[action*="password"]').addEventListener('submit', function(e) {
            const passwordNuevo = document.getElementById('password_nuevo').value;
            const passwordConfirmar = document.getElementById('password_confirmar').value;
            
            if (passwordNuevo !== passwordConfirmar) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }
            
            if (passwordNuevo.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }
        });
    </script>
</body>
</html> 