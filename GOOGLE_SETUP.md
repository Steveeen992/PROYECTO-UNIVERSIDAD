# Configuración de Google OAuth para el Sistema de Inventario

## 📋 Pasos para configurar Google OAuth

### 1. Crear proyecto en Google Cloud Console

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la API de Google+ (Google+ API)

### 2. Configurar credenciales OAuth

1. En el menú lateral, ve a **"APIs y servicios"** > **"Credenciales"**
2. Haz clic en **"Crear credenciales"** > **"ID de cliente de OAuth 2.0"**
3. Selecciona **"Aplicación web"**
4. Configura los siguientes campos:
   - **Nombre**: Sistema de Inventario
   - **URI de redirección autorizados**: 
     - `http://localhost/Sofware_inventario/google_callback.php` (para desarrollo local)
     - `https://tudominio.com/Sofware_inventario/google_callback.php` (para producción)

### 3. Obtener las credenciales

1. Después de crear las credenciales, copia:
   - **ID de cliente**
   - **Secreto del cliente**

### 4. Configurar el archivo google_config.php

1. Abre el archivo `google_config.php`
2. Reemplaza las siguientes líneas con tus credenciales reales:

```php
define('GOOGLE_CLIENT_ID', 'TU_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'TU_GOOGLE_CLIENT_SECRET');
```

### 5. Verificar la configuración

1. Asegúrate de que tu servidor tenga habilitada la extensión `curl` de PHP
2. Verifica que las URLs de redirección coincidan exactamente
3. Prueba el inicio de sesión con Google

## 🔧 Solución de problemas comunes

### Error: "redirect_uri_mismatch"
- Verifica que la URI de redirección en Google Cloud Console coincida exactamente con `GOOGLE_REDIRECT_URI`

### Error: "invalid_client"
- Verifica que el CLIENT_ID y CLIENT_SECRET estén correctos
- Asegúrate de que las credenciales sean para una aplicación web

### Error de CURL
- Instala la extensión curl de PHP: `sudo apt-get install php-curl`
- Reinicia tu servidor web

## 📱 Características implementadas

### ✅ Funcionalidades disponibles:
- **Inicio de sesión con Google**: Botón integrado en la página de login
- **Registro automático**: Si el usuario no existe, se crea automáticamente
- **Sesión persistente**: Mantiene la sesión como el login tradicional
- **Interfaz moderna**: Botón con el logo oficial de Google
- **Responsive**: Funciona en móviles y tablets

### 🔒 Seguridad:
- **Verificación de email**: Solo usuarios con email válido pueden registrarse
- **Tokens seguros**: Manejo seguro de tokens de acceso
- **Sesiones PHP**: Uso de sesiones PHP estándar
- **Sanitización**: Todos los datos se sanitizan antes de procesar

## 🚀 Para producción

1. **Cambia las URLs**: Actualiza `GOOGLE_REDIRECT_URI` con tu dominio real
2. **HTTPS obligatorio**: Google requiere HTTPS en producción
3. **Configuración de dominio**: Agrega tu dominio en Google Cloud Console
4. **Monitoreo**: Revisa los logs de Google Cloud Console para errores

## 📞 Soporte

Si tienes problemas con la configuración:
1. Verifica que todas las credenciales estén correctas
2. Asegúrate de que las URLs de redirección coincidan
3. Revisa los logs de error de PHP
4. Verifica que la extensión curl esté habilitada 