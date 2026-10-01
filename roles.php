<?php
/**
 * Sistema de roles y permisos
 * Administradora: acceso total
 * Editor: gestión operativa (agregar/editar), sin eliminar crítico ni config ni usuarios
 * Trabajador: solo visualización
 */

if (!defined('ROLES_LOADED')) {
    define('ROLES_LOADED', true);
}

function getRole() {
    if (!isset($_SESSION['user_role'])) return null;
    $r = $_SESSION['user_role'];
    return is_string($r) ? strtolower(trim($r)) : $r;
}

function isAdministrador() {
    return getRole() === 'administrador';
}

function isEditor() {
    return getRole() === 'editor';
}

function isTrabajador() {
    return getRole() === 'trabajador';
}

/** Solo administradora: gestionar usuarios y roles */
function canManageUsers() {
    return isAdministrador();
}

/** Administradora y Editor: agregar/editar productos. Solo administradora: eliminar productos */
function canAddEditProducts() {
    return isAdministrador() || isEditor();
}

function canDeleteProducts() {
    return isAdministrador();
}

/** Administradora y Editor: crear y administrar categorías (agregar/editar/eliminar si no crítico) */
function canManageCategories() {
    return isAdministrador() || isEditor();
}

/** Administradora y Editor: gestionar ubicaciones */
function canManageUbicaciones() {
    return isAdministrador() || isEditor();
}

/** Administradora y Editor: gestionar proveedores */
function canManageProveedores() {
    return isAdministrador() || isEditor();
}

/** Administradora y Editor: registrar y controlar préstamos */
function canManagePrestamos() {
    return isAdministrador() || isEditor();
}

/** Solo administradora: configuraciones generales (email, alertas) */
function canAccessConfig() {
    return isAdministrador();
}

/** Todos: ver reportes (trabajador solo consulta) */
function canViewReports() {
    return true;
}

/** Todos: ver análisis (trabajador solo consulta) */
function canViewAnalytics() {
    return true;
}

/** Todos: ver alertas */
function canViewAlertas() {
    return true;
}

/** Todos: ver inventario (lista y detalle) */
function canViewInventario() {
    return true;
}

/** Trabajador no puede retirar elementos (solo ver) */
function canRetirarElementos() {
    return isAdministrador() || isEditor();
}

/** Redirige si el usuario no tiene uno de los roles permitidos */
function requireRole($rolesAllowed) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    $rolesAllowed = (array) $rolesAllowed;
    if (!in_array(getRole(), $rolesAllowed, true)) {
        showMessage('No tienes permiso para acceder a esta sección.', 'error');
        header('Location: dashboard.php');
        exit;
    }
}

/** Solo administradora puede acceder */
function requireAdmin() {
    requireRole('administrador');
}
