# 🚨 Sistema de Alertas - Guía Completa

## 📋 Descripción

El sistema de alertas te permite recibir notificaciones automáticas sobre eventos importantes en tu inventario:

- **📦 Stock Bajo**: Cuando los productos tienen poca cantidad disponible
- **⏰ Préstamos Vencidos**: Cuando los préstamos han superado su fecha de devolución
- **⚠️ Préstamos por Vencer**: Cuando los préstamos están próximos a vencer
- **🔧 Mantenimiento**: Para recordatorios de mantenimiento (futuro)

## 🚀 Instalación

### Paso 1: Crear las tablas de la base de datos

Ejecuta el script de configuración:

```bash
# Accede a tu navegador y ve a:
http://localhost/Sofware_inventario/crear_tabla_alertas.php
```

Este script creará:
- Tabla `alertas` para almacenar las notificaciones
- Tabla `configuracion_alertas` para configuraciones por usuario
- Triggers automáticos para generar alertas

### Paso 2: Configurar alertas automáticas (Opcional)

Para que las alertas se generen automáticamente, configura un cron job:

```bash
# Edita el crontab
crontab -e

# Agrega esta línea para ejecutar cada 15 minutos:
*/15 * * * * php /ruta/a/tu/proyecto/generar_alertas_automaticas.php
```

## ⚙️ Configuración

### Configuración Global

Edita `config.php` para ajustar los valores por defecto:

```php
// Configuración del sistema de alertas
define('ALERT_STOCK_MINIMO', 5); // Stock mínimo para alertas
define('ALERT_DIAS_VENCIMIENTO', 3); // Días antes de vencimiento
define('ALERT_EMAIL_ENABLED', true); // Habilitar emails
define('ALERT_DASHBOARD_ENABLED', true); // Mostrar en dashboard
```

### Configuración por Usuario

Cada usuario puede personalizar sus alertas:

1. Ve a **🚨 Alertas** en el menú principal
2. Haz clic en **⚙️ Configurar**
3. Ajusta los tipos de alertas que quieres recibir
4. Configura los umbrales (stock mínimo, días de vencimiento)
5. Elige los canales de notificación (dashboard, email)

## 📧 Configuración de Email

### Opción 1: Usar PHPMailer (Recomendado)

1. Configura tu servidor SMTP en `config.php`:

```php
// En la función enviarEmailAlerta()
$mail->Host = 'smtp.gmail.com'; // Tu servidor SMTP
$mail->Username = 'tu-email@gmail.com';
$mail->Password = 'tu-password';
```

2. Para Gmail, usa una contraseña de aplicación

### Opción 2: Usar mail() de PHP

El sistema tiene un fallback que usa la función `mail()` de PHP.

## 🔔 Tipos de Alertas

### 📦 Stock Bajo
- **Cuándo se activa**: Cuando la cantidad disponible ≤ stock mínimo
- **Configuración**: Ajusta el stock mínimo en configuración
- **Acción recomendada**: Reabastecer inventario

### ⏰ Préstamos Vencidos
- **Cuándo se activa**: Cuando la fecha de devolución ya pasó
- **Configuración**: No requiere configuración adicional
- **Acción recomendada**: Contactar al prestatario

### ⚠️ Préstamos por Vencer
- **Cuándo se activa**: X días antes de la fecha de devolución
- **Configuración**: Ajusta los días en configuración
- **Acción recomendada**: Recordar al prestatario

### 🔧 Mantenimiento (Futuro)
- **Cuándo se activa**: Según programación de mantenimientos
- **Configuración**: Por implementar
- **Acción recomendada**: Programar mantenimiento

## 📊 Dashboard de Alertas

### Vista General
- **Total de alertas**: Número total de alertas activas
- **No leídas**: Alertas que aún no has revisado
- **Por tipo**: Desglose de alertas por categoría

### Filtros Disponibles
- **Tipo de alerta**: Stock bajo, préstamos, etc.
- **Estado**: Leídas/No leídas
- **Fecha**: Rango de fechas
- **Elemento**: Filtrar por producto específico

### Acciones Masivas
- **Marcar todas como leídas**: Procesar todas las alertas de una vez
- **Generar reporte**: Exportar alertas a Excel
- **Eliminar alertas**: Limpiar alertas antiguas

## 🔄 Generación Automática

### Cómo Funciona
1. El script `generar_alertas_automaticas.php` se ejecuta periódicamente
2. Verifica condiciones en el inventario y préstamos
3. Genera alertas solo si no existen alertas recientes (evita duplicados)
4. Envía emails si está habilitado
5. Limpia alertas antiguas (más de 30 días)

### Ejecución Manual
```bash
php generar_alertas_automaticas.php
```

### Logs
Los errores se registran en el log de PHP. Revisa:
```bash
tail -f /var/log/php_errors.log
```

## 📱 Notificaciones

### En el Dashboard
- Las alertas aparecen en la sección "🚨 Alertas Activas"
- Se actualizan automáticamente cada 30 segundos
- Muestran un resumen de las alertas más importantes

### Por Email
- Se envían a usuarios que tienen habilitadas las notificaciones por email
- Incluyen un resumen de las alertas recientes
- Contienen enlaces directos al sistema

### Personalización
Cada usuario puede configurar:
- Qué tipos de alertas recibir
- Umbrales personalizados
- Canales de notificación preferidos

## 🛠️ Mantenimiento

### Limpieza Automática
- Las alertas se eliminan automáticamente después de 30 días
- Puedes ajustar este período en el código

### Backup
- Las alertas se incluyen en el backup de la base de datos
- No requieren archivos adicionales

### Monitoreo
Revisa regularmente:
- Cantidad de alertas generadas
- Tiempo de respuesta a las alertas
- Efectividad de los umbrales configurados

## 🔧 Solución de Problemas

### Las alertas no se generan
1. Verifica que las tablas existan: `crear_tabla_alertas.php`
2. Revisa los logs de PHP
3. Ejecuta manualmente: `php generar_alertas_automaticas.php`

### Los emails no se envían
1. Verifica la configuración SMTP
2. Revisa que ALERT_EMAIL_ENABLED = true
3. Confirma que los usuarios tienen email configurado

### Alertas duplicadas
1. El sistema evita duplicados automáticamente
2. Si persisten, revisa la configuración de cron
3. Verifica que no haya múltiples ejecuciones

### Performance
- Las alertas se optimizan para no afectar el rendimiento
- Se usan índices en la base de datos
- La limpieza automática mantiene la base de datos ligera

## 📈 Métricas y Reportes

### Estadísticas Disponibles
- Total de alertas por período
- Tipos de alertas más comunes
- Tiempo promedio de respuesta
- Efectividad de los umbrales

### Reportes
- Exportar alertas a Excel
- Filtros por fecha, tipo, usuario
- Análisis de tendencias

## 🔮 Futuras Mejoras

### Próximas Funcionalidades
- **Alertas de mantenimiento**: Programación automática
- **Notificaciones push**: Para aplicaciones móviles
- **Alertas por WhatsApp**: Integración con WhatsApp Business API
- **Alertas por SMS**: Para casos críticos
- **Escalación automática**: Alertas que escalan si no se atienden
- **Dashboard ejecutivo**: Resumen para gerentes

### Integraciones
- **Slack**: Notificaciones en canales de trabajo
- **Microsoft Teams**: Integración con Teams
- **Zapier**: Automatizaciones avanzadas
- **API REST**: Para integraciones externas

## 📞 Soporte

Si tienes problemas con el sistema de alertas:

1. **Revisa los logs**: Busca errores en el log de PHP
2. **Ejecuta manualmente**: `php generar_alertas_automaticas.php`
3. **Verifica la configuración**: Revisa `config.php` y la configuración de usuarios
4. **Contacta al administrador**: Para problemas complejos

---

**¡El sistema de alertas está listo para mantener tu inventario bajo control! 🎉** 