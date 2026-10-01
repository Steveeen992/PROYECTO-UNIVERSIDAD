<?php
require_once 'config.php';
require_once 'google_config.php';

// Verificar si hay un código de autorización
if (!isset($_GET['code'])) {
    showMessage('Error: No se recibió el código de autorización de Google', 'error');
    redirect('login.php');
}

$code = $_GET['code'];

try {
    // Obtener token de acceso
    $token_data = getGoogleAccessToken($code);
    
    if (!isset($token_data['access_token'])) {
        showMessage('Error al obtener token de acceso: ' . json_encode($token_data), 'error');
        redirect('login.php');
    }
    
    // Obtener información del usuario
    $user_info = getGoogleUserInfo($token_data['access_token']);
    
    if (!isset($user_info['email'])) {
        showMessage('Error al obtener información del usuario', 'error');
        redirect('login.php');
    }
    
    // Verificar si el usuario existe en la base de datos
    $existing_user = $db->fetch("SELECT * FROM usuarios WHERE email = ?", [$user_info['email']]);
    
    if ($existing_user) {
        // Usuario existe, iniciar sesión
        $_SESSION['user_id'] = $existing_user['id'];
        $_SESSION['user_name'] = $existing_user['nombre'];
        $_SESSION['user_email'] = $existing_user['email'];
        $_SESSION['user_role'] = $existing_user['rol'];
        $_SESSION['login_method'] = 'google';
        
        showMessage('Inicio de sesión exitoso con Google', 'success');
        redirect('dashboard.php');
    } else {
        // Usuario no existe, crear cuenta automáticamente
        $nombre = $user_info['given_name'] . ' ' . $user_info['family_name'];
        $email = $user_info['email'];
        
        // Generar contraseña aleatoria (no se usará pero es requerida por la estructura)
        $password_hash = password_hash(uniqid(), PASSWORD_DEFAULT);
        
        try {
            $db->query(
                "INSERT INTO usuarios (nombre, email, password, rol, fecha_registro) VALUES (?, ?, ?, 'editor', NOW())",
                [$nombre, $email, $password_hash]
            );
            
            $user_id = $db->lastInsertId();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $nombre;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'editor';
            $_SESSION['login_method'] = 'google';
            
            showMessage('Cuenta creada exitosamente con Google', 'success');
            redirect('dashboard.php');
            
        } catch (Exception $e) {
            showMessage('Error al crear cuenta: ' . $e->getMessage(), 'error');
            redirect('login.php');
        }
    }
    
} catch (Exception $e) {
    showMessage('Error en la autenticación con Google: ' . $e->getMessage(), 'error');
    redirect('login.php');
}
?> 