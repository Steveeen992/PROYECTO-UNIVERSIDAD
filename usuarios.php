<?php
require_once 'config.php';
requireLogin();
requireAdmin();

// Comprobar si la tabla usuarios tiene la columna activo (no existe en todas las instalaciones)
$tiene_columna_activo = false;
try {
    $columnas = $db->fetchAll("SHOW COLUMNS FROM usuarios LIKE 'activo'");
    $tiene_columna_activo = !empty($columnas);
} catch (Exception $e) {}

$message = getMessage();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Usuario administradora principal (no se puede eliminar)
$admin_principal_email = 'Litoral2026';

// Crear nuevo usuario (solo Editor o Trabajador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear') {
    $nombre = trim(sanitize($_POST['nombre'] ?? ''));
    $email = trim(sanitize($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar_password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    
    $error_crear = '';
    if (empty($nombre) || empty($email) || empty($password) || empty($confirmar)) {
        $error_crear = 'Todos los campos son obligatorios.';
    } elseif (!in_array($rol, ['editor', 'trabajador'], true)) {
        $error_crear = 'El rol debe ser Editor o Trabajador.';
    } elseif (strlen($password) < 6) {
        $error_crear = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirmar) {
        $error_crear = 'Las contraseñas no coinciden.';
    } else {
        $existe = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
        if ($existe) {
            $error_crear = 'Ya existe un usuario con ese email.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)",
                    [$nombre, $email, $hash, $rol]
                );
                showMessage('Usuario creado correctamente. Ya puede iniciar sesión con su email y contraseña.', 'success');
                redirect('usuarios.php');
            } catch (Exception $e) {
                $error_crear = 'Error al guardar. Comprueba que el rol esté permitido en la base de datos.';
            }
        }
    }
    if ($error_crear) {
        showMessage($error_crear, 'error');
        redirect('usuarios.php?action=crear');
    }
}

// Eliminar usuario (no la administradora principal ni uno mismo)
if ($action === 'eliminar' && $id && $id != $_SESSION['user_id']) {
    $u = $db->fetch("SELECT id, email FROM usuarios WHERE id = ?", [$id]);
    if ($u && $u['email'] !== $admin_principal_email) {
        try {
            $db->query("DELETE FROM usuarios WHERE id = ?", [$id]);
            showMessage('Usuario eliminado.', 'success');
        } catch (Exception $e) {
            showMessage('No se pudo eliminar el usuario.', 'error');
        }
    }
    redirect('usuarios.php');
}

// Actualizar rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'editar_rol' && $id) {
    $rol = $_POST['rol'] ?? '';
    if (in_array($rol, ['administrador', 'editor', 'trabajador'], true)) {
        $db->query("UPDATE usuarios SET rol = ? WHERE id = ?", [$rol, $id]);
        showMessage('Rol actualizado correctamente', 'success');
    }
    redirect('usuarios.php');
}

// Activar/desactivar usuario (solo si la tabla tiene columna activo)
if ($tiene_columna_activo && $action === 'toggle_activo' && $id && $id != $_SESSION['user_id']) {
    $u = $db->fetch("SELECT activo FROM usuarios WHERE id = ?", [$id]);
    if ($u) {
        $nuevo = $u['activo'] ? 0 : 1;
        $db->query("UPDATE usuarios SET activo = ? WHERE id = ?", [$nuevo, $id]);
        showMessage($nuevo ? 'Usuario activado' : 'Usuario desactivado', 'success');
    }
    redirect('usuarios.php');
}

$campos = $tiene_columna_activo ? "id, nombre, email, rol, activo, fecha_registro" : "id, nombre, email, rol, fecha_registro";
$usuarios = $db->fetchAll("SELECT {$campos} FROM usuarios ORDER BY nombre");
$nav_active = 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Gestión de usuarios y roles</h1>
            <div class="header-actions">
                <a href="usuarios.php?action=crear" class="btn btn-primary">Crear usuario</a>
                <a href="eliminar_otros_usuarios.php" class="btn btn-danger" onclick="return confirm('¿Eliminar TODOS los usuarios excepto la administradora (Litoral2026)? Esta acción no se puede deshacer.');">Dejar solo administradora</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <?php if ($tiene_columna_activo): ?><th>Estado</th><?php endif; ?>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <?php
                            $rolLabel = [
                                'administrador' => '👩‍💼 Administradora',
                                'editor' => '✏️ Editor',
                                'trabajador' => '👷 Trabajador'
                            ];
                            echo $rolLabel[$u['rol']] ?? $u['rol'];
                            ?>
                        </td>
                        <?php if ($tiene_columna_activo): ?>
                        <td>
                            <?php if (!empty($u['activo'])): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?php echo date('d/m/Y', strtotime($u['fecha_registro'])); ?></td>
                        <td class="actions">
                            <a href="historial_usuario.php?usuario_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info" title="Ver historial de navegación y actividad">Historial</a>
                            <a href="usuarios.php?action=editar_rol&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary">Cambiar rol</a>
                            <?php if ($u['email'] !== $admin_principal_email && $u['id'] != $_SESSION['user_id']): ?>
                                <a href="usuarios.php?action=eliminar&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Eliminar a este usuario? No podrá iniciar sesión.');">Eliminar</a>
                            <?php endif; ?>
                            <?php if ($tiene_columna_activo && $u['id'] != $_SESSION['user_id']): ?>
                                <a href="usuarios.php?action=toggle_activo&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-secondary"
                                   onclick="return confirm('¿<?php echo !empty($u['activo']) ? 'Desactivar' : 'Activar'; ?> este usuario?');">
                                    <?php echo !empty($u['activo']) ? 'Desactivar' : 'Activar'; ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($action === 'crear'): ?>
            <div class="card" style="max-width:500px;margin:20px 0;">
                <h3>Crear nuevo usuario</h3>
                <p class="text-muted">Solo se pueden crear usuarios con rol Editor o Trabajador. Podrán iniciar sesión con su email y contraseña.</p>
                <form method="POST" action="usuarios.php?action=crear">
                    <div class="form-group">
                        <label for="nombre">Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email (usuario para iniciar sesión) *</label>
                        <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirmar_password">Confirmar contraseña *</label>
                        <input type="password" id="confirmar_password" name="confirmar_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="rol">Rol *</label>
                        <select id="rol" name="rol" class="form-control" required>
                            <option value="editor" <?php echo ($_POST['rol'] ?? '') === 'editor' ? 'selected' : ''; ?>>✏️ Editor</option>
                            <option value="trabajador" <?php echo ($_POST['rol'] ?? '') === 'trabajador' ? 'selected' : ''; ?>>👷 Trabajador</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Crear usuario</button>
                        <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($action === 'editar_rol' && $id): ?>
            <?php
            $usuario_editar = $db->fetch("SELECT id, nombre, email, rol FROM usuarios WHERE id = ?", [$id]);
            if ($usuario_editar):
            ?>
            <div class="modal-overlay" style="display:block;">
                <div class="card" style="max-width:400px;margin:20px auto;">
                    <h3>Cambiar rol: <?php echo htmlspecialchars($usuario_editar['nombre']); ?></h3>
                    <form method="POST" action="usuarios.php?action=editar_rol&id=<?php echo $id; ?>">
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="rol" class="form-control" required>
                                <option value="administrador" <?php echo $usuario_editar['rol'] === 'administrador' ? 'selected' : ''; ?>>👩‍💼 Administradora</option>
                                <option value="editor" <?php echo $usuario_editar['rol'] === 'editor' ? 'selected' : ''; ?>>✏️ Editor</option>
                                <option value="trabajador" <?php echo $usuario_editar['rol'] === 'trabajador' ? 'selected' : ''; ?>>👷 Trabajador</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const menu = this.parentElement.querySelector('.dropdown-menu');
                    if (menu) {
                        menu.classList.toggle('show');
                        menu.style.display = menu.classList.contains('show') ? 'block' : 'none';
                    }
                });
            }
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(function(m) {
                        m.classList.remove('show');
                        m.style.display = 'none';
                    });
                }
            });
        });
    </script>
</body>
</html>
