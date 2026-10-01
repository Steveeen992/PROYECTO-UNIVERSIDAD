<?php
/**
 * Funciones helper para inventario.
 * Todos los roles ven el mismo inventario global; permisos de edición en roles.php.
 */
function _usuarioVeTodo() {
    return true;
}

function getUserInventario($db, $user_id) {
    if (_usuarioVeTodo()) {
        return $db->fetchAll(
            "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion, p.nombre as proveedor
             FROM inventario i
             LEFT JOIN categorias c ON i.categoria_id = c.id
             LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id
             LEFT JOIN proveedores p ON i.proveedor_id = p.id
             ORDER BY i.fecha_registro DESC"
        );
    }
    return $db->fetchAll(
        "SELECT i.*, c.nombre as categoria, u.nombre as ubicacion, p.nombre as proveedor
         FROM inventario i
         LEFT JOIN categorias c ON i.categoria_id = c.id AND c.usuario_registro = ?
         LEFT JOIN ubicaciones u ON i.ubicacion_id = u.id AND u.usuario_registro = ?
         LEFT JOIN proveedores p ON i.proveedor_id = p.id AND p.usuario_registro = ?
         WHERE i.usuario_registro = ?
         ORDER BY i.fecha_registro DESC",
        [$user_id, $user_id, $user_id, $user_id]
    );
}

function getUserCategorias($db, $user_id) {
    if (_usuarioVeTodo()) {
        return $db->fetchAll("SELECT * FROM categorias ORDER BY nombre");
    }
    return $db->fetchAll("SELECT * FROM categorias WHERE usuario_registro = ? ORDER BY nombre", [$user_id]);
}

function getUserUbicaciones($db, $user_id) {
    if (_usuarioVeTodo()) {
        return $db->fetchAll("SELECT * FROM ubicaciones ORDER BY nombre");
    }
    return $db->fetchAll("SELECT * FROM ubicaciones WHERE usuario_registro = ? ORDER BY nombre", [$user_id]);
}

function getUserProveedores($db, $user_id) {
    if (_usuarioVeTodo()) {
        return $db->fetchAll("SELECT * FROM proveedores ORDER BY nombre");
    }
    return $db->fetchAll("SELECT * FROM proveedores WHERE usuario_registro = ? ORDER BY nombre", [$user_id]);
}

function getUserPrestamos($db, $user_id) {
    if (_usuarioVeTodo()) {
        return $db->fetchAll(
            "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
             FROM prestamos p
             JOIN inventario i ON p.elemento_id = i.id
             ORDER BY p.fecha_prestamo DESC"
        );
    }
    return $db->fetchAll(
        "SELECT p.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
         FROM prestamos p
         JOIN inventario i ON p.elemento_id = i.id AND i.usuario_registro = ?
         WHERE p.usuario_registro = ?
         ORDER BY p.fecha_prestamo DESC",
        [$user_id, $user_id]
    );
}

function getUserHistorial($db, $user_id) {
    if (_usuarioVeTodo()) {
        return $db->fetchAll(
            "SELECT h.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
             FROM historial_elementos h
             JOIN inventario i ON h.elemento_id = i.id
             ORDER BY h.fecha_evento DESC"
        );
    }
    return $db->fetchAll(
        "SELECT h.*, i.nombre as elemento_nombre, i.codigo as elemento_codigo
         FROM historial_elementos h
         JOIN inventario i ON h.elemento_id = i.id AND i.usuario_registro = ?
         WHERE h.usuario_registro = ?
         ORDER BY h.fecha_evento DESC",
        [$user_id, $user_id]
    );
}

function getUserStats($db, $user_id) {
    if (_usuarioVeTodo()) {
        return [
            "total_elementos" => $db->fetch("SELECT COUNT(*) as total FROM inventario")["total"],
            "activos" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'activo'")["total"],
            "en_reparacion" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'en_reparacion'")["total"],
            "dados_baja" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE estado = 'dado_de_baja'")["total"]
        ];
    }
    return [
        "total_elementos" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ?", [$user_id])["total"],
        "activos" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = 'activo'", [$user_id])["total"],
        "en_reparacion" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = 'en_reparacion'", [$user_id])["total"],
        "dados_baja" => $db->fetch("SELECT COUNT(*) as total FROM inventario WHERE usuario_registro = ? AND estado = 'dado_de_baja'", [$user_id])["total"]
    ];
}

function canUserAccessElement($db, $user_id, $element_id) {
    if (_usuarioVeTodo()) {
        return $db->fetch("SELECT id FROM inventario WHERE id = ?", [$element_id]) ? true : false;
    }
    return $db->fetch("SELECT id FROM inventario WHERE id = ? AND usuario_registro = ?", [$element_id, $user_id]) ? true : false;
}

function canUserAccessCategory($db, $user_id, $category_id) {
    if (_usuarioVeTodo()) {
        return $db->fetch("SELECT id FROM categorias WHERE id = ?", [$category_id]) ? true : false;
    }
    return $db->fetch("SELECT id FROM categorias WHERE id = ? AND usuario_registro = ?", [$category_id, $user_id]) ? true : false;
}

function canUserAccessLocation($db, $user_id, $location_id) {
    if (_usuarioVeTodo()) {
        return $db->fetch("SELECT id FROM ubicaciones WHERE id = ?", [$location_id]) ? true : false;
    }
    return $db->fetch("SELECT id FROM ubicaciones WHERE id = ? AND usuario_registro = ?", [$location_id, $user_id]) ? true : false;
}

function canUserAccessProvider($db, $user_id, $provider_id) {
    if (_usuarioVeTodo()) {
        return $db->fetch("SELECT id FROM proveedores WHERE id = ?", [$provider_id]) ? true : false;
    }
    return $db->fetch("SELECT id FROM proveedores WHERE id = ? AND usuario_registro = ?", [$provider_id, $user_id]) ? true : false;
}

function canUserAccessPrestamo($db, $user_id, $prestamo_id) {
    if (_usuarioVeTodo()) {
        return $db->fetch("SELECT id FROM prestamos WHERE id = ?", [$prestamo_id]) ? true : false;
    }
    return $db->fetch("SELECT id FROM prestamos WHERE id = ? AND usuario_registro = ?", [$prestamo_id, $user_id]) ? true : false;
}
