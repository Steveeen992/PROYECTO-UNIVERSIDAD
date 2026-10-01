<?php
/**
 * Barra de navegación según rol del usuario.
 * Definir $nav_active con la clave de la página actual para marcar el enlace activo (ej: 'inventario', 'dashboard').
 */
$nav_active = isset($nav_active) ? $nav_active : '';
// Registrar visita a la página en el historial del usuario (solo cuando cambia de página, no en cada recarga)
if (function_exists('registrarActividadUsuario') && isset($_SESSION['user_id']) && isset($db)) {
    $pagina_actual = basename($_SERVER['PHP_SELF'] ?? '');
    $ultima_log = $_SESSION['_ultima_pagina_log'] ?? '';
    if ($ultima_log !== $pagina_actual) {
        registrarActividadUsuario($db, 'vista', 'Visitó ' . $pagina_actual, $pagina_actual);
        $_SESSION['_ultima_pagina_log'] = $pagina_actual;
    }
}
?>
<nav class="navbar" role="navigation">
    <div class="nav-brand">
        <h2><?php echo SITE_NAME; ?></h2>
    </div>
    <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Abrir menú" aria-expanded="false">
        <span class="nav-toggle-bar"></span>
        <span class="nav-toggle-bar"></span>
        <span class="nav-toggle-bar"></span>
    </button>
    <div class="nav-menu" id="nav-menu">
        <a href="dashboard.php" class="<?php echo $nav_active === 'dashboard' ? 'active' : ''; ?>">Inicio</a>
        <a href="inventario.php" class="<?php echo $nav_active === 'inventario' ? 'active' : ''; ?>">Inventario</a>
        <a href="categorias.php" class="<?php echo $nav_active === 'categorias' ? 'active' : ''; ?>">Categorías</a>
        <a href="ubicaciones.php" class="<?php echo $nav_active === 'ubicaciones' ? 'active' : ''; ?>">Ubicaciones</a>
        <a href="prestamos.php" class="<?php echo $nav_active === 'prestamos' ? 'active' : ''; ?>">Prestamos</a>
        <a href="proveedores.php" class="<?php echo $nav_active === 'proveedores' ? 'active' : ''; ?>">Proveedores</a>
        <?php if (canViewAnalytics()): ?>
        <a href="analytics.php" class="<?php echo $nav_active === 'analytics' ? 'active' : ''; ?>">Análisis</a>
        <?php endif; ?>
        <a href="chat_ai.php" class="<?php echo $nav_active === 'chat_ai' ? 'active' : ''; ?>">Chat IA</a>
        <?php if (canViewAlertas()): ?>
        <a href="alertas.php" class="<?php echo $nav_active === 'alertas' ? 'active' : ''; ?>">Alertas</a>
        <?php endif; ?>
        <?php if (canManageUsers()): ?>
        <a href="usuarios.php" class="<?php echo $nav_active === 'usuarios' ? 'active' : ''; ?>">Usuarios</a>
        <?php endif; ?>
        <div class="dropdown">
            <a href="#" class="dropdown-toggle"><?php echo htmlspecialchars($_SESSION['user_name']); ?></a>
            <div class="dropdown-menu">
                <a href="perfil.php">Perfil</a>
                <?php if (canAccessConfig()): ?>
                <a href="configurar_email.php">Configurar Email</a>
                <a href="configurar_alertas.php">Configurar Alertas</a>
                <?php if (function_exists('isAdministrador') && isAdministrador()): ?>
                <a href="reset_inventario.php">Vaciar inventario (primer uso)</a>
                <?php endif; ?>
                <?php endif; ?>
                <a href="cerrarSesion.php">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</nav>
<script>
(function() {
    var toggle = document.getElementById('nav-toggle');
    var menu = document.getElementById('nav-menu');
    if (toggle && menu) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open);
            toggle.classList.toggle('open', open);
        });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.navbar')) {
                menu.classList.remove('open');
                toggle.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();
</script>
