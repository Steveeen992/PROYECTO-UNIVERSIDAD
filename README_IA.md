# 🤖 Configuración del Chat IA para Procesar Facturas

## ¿Qué hace esta funcionalidad?

El sistema de Chat IA permite:
- 📷 Subir fotos de facturas
- 🤖 Procesar automáticamente con IA
- 📋 Extraer datos como: producto, precio, proveedor, fecha, etc.
- ✅ Confirmar y agregar al inventario automáticamente

## Configuración

### Opción 1: Usar datos simulados (Recomendado para pruebas)
Por defecto, el sistema funciona con datos simulados. No necesitas configurar nada adicional.

### Opción 2: Usar OpenAI GPT-4 Vision (Para producción)

1. **Obtener API Key de OpenAI:**
   - Ve a https://platform.openai.com/
   - Crea una cuenta o inicia sesión
   - Ve a "API Keys" en tu dashboard
   - Crea una nueva API key

2. **Configurar en el sistema:**
   - Abre el archivo `config.php`
   - Busca la línea: `define('OPENAI_API_KEY', '');`
   - Reemplaza con tu API key:
   ```php
   define('OPENAI_API_KEY', 'sk-tu-api-key-aqui');
   ```

3. **Verificar configuración:**
   - Ve a `http://localhost/Sofware_inventario/chat_ai.php`
   - Sube una foto de factura
   - El sistema procesará con IA real

## Cómo usar

1. **Acceder al Chat IA:**
   - Desde el menú principal: "Chat IA"
   - Desde inventario: Botón "🤖 Procesar Factura con IA"

2. **Procesar una factura:**
   - Haz clic en "📷 Subir Factura"
   - Selecciona la imagen de tu factura
   - Espera a que la IA procese (2-5 segundos)
   - Revisa los datos extraídos
   - Confirma o edita según necesites

3. **Datos que extrae la IA:**
   - ✅ Nombre del producto
   - ✅ Precio unitario
   - ✅ Cantidad
   - ✅ Proveedor
   - ✅ Fecha de compra
   - ✅ Código del producto
   - ✅ Descripción
   - ✅ Categoría sugerida
   - ✅ Ubicación sugerida

## Tipos de facturas compatibles

La IA puede procesar:
- 📄 Facturas de proveedores
- 🧾 Recibos de compra
- 📋 Órdenes de compra
- 🏷️ Etiquetas de productos
- 📱 Fotos tomadas con el celular

## Consejos para mejores resultados

1. **Calidad de imagen:**
   - Usa buena iluminación
   - Enfoca bien la factura
   - Evita sombras y reflejos

2. **Contenido de la factura:**
   - Asegúrate que el texto sea legible
   - Incluye el nombre del proveedor
   - Que se vea el precio claramente

3. **Formato recomendado:**
   - JPEG o PNG
   - Máximo 10MB
   - Resolución mínima: 800x600

## Solución de problemas

### Error: "No se pudo procesar la imagen"
- Verifica que la imagen sea clara
- Asegúrate que sea un formato válido (JPG, PNG)
- Intenta con otra foto

### Error: "Error de conexión"
- Verifica tu conexión a internet
- Si usas OpenAI, verifica tu API key
- Revisa que el servidor esté funcionando

### Datos incorrectos extraídos
- La IA puede cometer errores
- Siempre revisa los datos antes de confirmar
- Usa el botón "✏️ Editar Datos" para corregir

## Costos (solo si usas OpenAI)

- **Datos simulados:** Gratis
- **OpenAI GPT-4 Vision:** ~$0.01-0.03 por imagen
- **Uso típico:** $1-5 por mes para uso normal

## Seguridad

- ✅ Las imágenes se procesan temporalmente
- ✅ No se almacenan en el servidor
- ✅ Los datos extraídos son seguros
- ✅ Solo usuarios autenticados pueden usar la función

## Soporte

Si tienes problemas:
1. Verifica la configuración
2. Revisa los logs del servidor
3. Prueba con datos simulados primero
4. Contacta al administrador del sistema

---

**¡Listo! Tu sistema de inventario ahora tiene IA para procesar facturas automáticamente.** 🚀 