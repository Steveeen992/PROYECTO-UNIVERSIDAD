<?php
require_once 'config.php';
requireLogin();

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat IA - Procesar Facturas - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .chat-container {
            max-width: 800px;
            margin: 20px auto;
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .chat-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .chat-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .chat-messages {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: var(--bg-light);
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .message.user {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
        }

        .message.user .message-avatar {
            background: var(--primary-color);
        }

        .message.ai .message-avatar {
            background: var(--success-color);
        }

        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 18px;
            max-width: 70%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .message.user .message-content {
            background: var(--primary-color);
            color: white;
        }

        .message.ai .message-content {
            background: white;
            color: var(--text-primary);
        }

        .chat-input {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-primary);
        }

        .input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .file-upload {
            position: relative;
            display: inline-block;
        }

        .file-upload input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-btn {
            background: var(--success-color);
            color: white;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .file-upload-btn:hover {
            background: var(--success-dark);
        }

        .chat-input input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        .chat-input button {
            padding: 12px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
        }

        .chat-input button:hover {
            background: var(--primary-dark);
        }

        .chat-input button:disabled {
            background: var(--text-light);
            cursor: not-allowed;
        }

        .loading {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .extracted-data {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-top: 10px;
        }

        .extracted-data h4 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .data-item {
            background: white;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .data-item strong {
            color: var(--text-primary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-item span {
            display: block;
            margin-top: 2px;
            color: var(--text-secondary);
        }

        .action-buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .upload-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--border-radius);
            margin: 10px 0;
        }

        @media (max-width: 768px) {
            .chat-container {
                margin: 10px;
            }
            
            .chat-messages {
                height: 300px;
            }
            
            .message-content {
                max-width: 85%;
            }
            
            .input-group {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* Estilos para botones de descarga en el chat */
        .message-content .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px 0;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .message-content .btn-primary {
            background: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
        }

        .message-content .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .message-content a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .message-content a:hover {
            text-decoration: underline;
        }

        /* Estilos para notificaciones */
        .notification {
            font-family: inherit;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Mejoras en el área de carga */
        .loading {
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
        }

        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mejoras en el área de preview */
        .upload-preview {
            max-width: 300px;
            max-height: 300px;
            border-radius: var(--border-radius);
            margin: 10px auto;
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Estilos para mensajes de error */
        .message-content[style*="background: #fff3cd"] {
            border-left: 4px solid #ffc107;
        }

        /* Mejoras en el input de archivo */
        .file-upload {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 30px;
            text-align: center;
            transition: var(--transition);
            background: var(--bg-light);
        }

        .file-upload:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
        }

        .file-upload input[type=file] {
            display: none;
        }

        .file-upload label {
            cursor: pointer;
            color: var(--primary-color);
            font-weight: 500;
            display: block;
            margin-bottom: 10px;
        }

        .file-upload .upload-icon {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php $nav_active = 'chat_ai'; include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Chat IA - Procesar Facturas</h1>
            <a href="inventario.php" class="btn btn-secondary">Volver al Inventario</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <div class="chat-container">
            <div class="chat-header">
                <h2>Asistente IA</h2>
                <p>Sube una foto de tu factura y te ayudo a extraer los datos automáticamente</p>
            </div>

            <div class="chat-messages" id="chatMessages">
                <div class="message ai">
                    <div class="message-avatar"></div>
                    <div class="message-content">
                        ¡Hola! Soy tu asistente IA. Puedo ayudarte a procesar facturas y extraer automáticamente los datos de tus productos. 
                        <br><br>
                        <strong>¿Cómo funciona?</strong>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Sube una foto de tu factura</li>
                            <li>Yo analizo la imagen y extraigo los datos</li>
                            <li>Te muestro los datos extraídos</li>
                            <li>Puedes confirmar y agregar al inventario</li>
                        </ul>
                        <br>
                        ¡Empecemos! Sube una foto de tu factura.
                    </div>
                </div>
            </div>

            <div class="chat-input">
                <div class="input-group">
                    <div class="file-upload">
                        <input type="file" id="facturaFile" accept="image/*,.pdf,.xls,.xlsx" onchange="handleFileUpload(this)">
                        <label for="facturaFile" class="file-upload-btn">
                            📷 Subir Factura
                        </label>
                    </div>
                    <input type="text" id="messageInput" placeholder="O escribe tu mensaje aquí..." onkeypress="handleKeyPress(event)">
                    <button onclick="sendMessage()" id="sendButton">Enviar</button>
                </div>
                <div id="uploadPreview"></div>
            </div>
        </div>
    </div>

    <script>
        let currentImage = null;
        let extractedData = null;

        function handleFileUpload(input) {
            const file = input.files[0];
            if (!file) {
                showError('No se seleccionó ningún archivo');
                return;
            }

            // Validar tipo de archivo
            if (!file.type.startsWith('image/')) {
                showError('Por favor selecciona solo archivos de imagen (JPG, PNG, GIF)');
                return;
            }

            // Validar tamaño (máximo 10MB)
            if (file.size > 10 * 1024 * 1024) {
                showError('El archivo es demasiado grande. Máximo 10MB.');
                return;
            }

            console.log('Archivo seleccionado:', file.name, 'Tipo:', file.type, 'Tamaño:', file.size);

            const reader = new FileReader();
            reader.onload = function(e) {
                currentImage = e.target.result;
                showImagePreview(e.target.result);
                processImageWithAI(e.target.result, file);
            };
            reader.onerror = function() {
                showError('Error al leer el archivo');
            };
            reader.readAsDataURL(file);
        }

        function showImagePreview(imageData) {
            const preview = document.getElementById('uploadPreview');
            preview.innerHTML = `
                <img src="${imageData}" alt="Factura" class="upload-preview">
                <div class="loading">
                    <div class="spinner"></div>
                    <span>Procesando factura con IA...</span>
                </div>
            `;
        }

        function processImageWithAI(imageData, file) {
            console.log('Iniciando procesamiento de imagen...');
            
            try {
                // Convertir imagen a blob para enviar al servidor
                fetch(imageData)
                    .then(res => {
                        if (!res.ok) {
                            throw new Error('Error al procesar la imagen');
                        }
                        return res.blob();
                    })
                    .then(blob => {
                        console.log('Blob creado:', blob.size, 'bytes');
                        
                        const formData = new FormData();
                        formData.append('image', blob, file.name || 'factura.jpg');
                        
                        // Mostrar loading
                        const preview = document.getElementById('uploadPreview');
                        preview.innerHTML = `
                            <img src="${imageData}" alt="Factura" class="upload-preview">
                            <div class="loading">
                                <div class="spinner"></div>
                                <span>Procesando factura con IA...</span>
                            </div>
                        `;
                        
                        console.log('Enviando imagen al servidor...');
                        
                        // Enviar a la API
                        return fetch('api_process_invoice.php', {
                            method: 'POST',
                            body: formData
                        });
                    })
                    .then(response => {
                        console.log('Respuesta del servidor:', response.status, response.statusText);
                        
                        if (!response.ok) {
                            throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
                        }
                        
                        return response.json();
                    })
                    .then(data => {
                        console.log('Datos recibidos:', data);
                        
                        if (data.success) {
                            showAIResponse(data.data);
                        } else {
                            showError('Error al procesar la imagen: ' + (data.error || 'Error desconocido'));
                        }
                    })
                    .catch(error => {
                        console.error('Error en el procesamiento:', error);
                        showError('Error de conexión: ' + error.message);
                    });
                    
            } catch (error) {
                console.error('Error en processImageWithAI:', error);
                showError('Error interno: ' + error.message);
            }
        }

        function showAIResponse(data) {
            const chatMessages = document.getElementById('chatMessages');
            const preview = document.getElementById('uploadPreview');
            
            // Limpiar preview
            preview.innerHTML = '';
            
            // Agregar respuesta de IA
            const aiMessage = document.createElement('div');
            aiMessage.className = 'message ai';
            aiMessage.innerHTML = `
                <div class="message-avatar"></div>
                <div class="message-content">
                    He analizado tu factura y extraje los siguientes datos:
                    <div class="extracted-data">
                        <h4>📋 Datos Extraídos:</h4>
                        <div class="data-grid">
                            <div class="data-item">
                                <strong>Producto</strong>
                                <span>${data.producto || 'No detectado'}</span>
                            </div>
                            <div class="data-item">
                                <strong>Precio</strong>
                                <span>$${data.precio || '0'}</span>
                            </div>
                            <div class="data-item">
                                <strong>Cantidad</strong>
                                <span>${data.cantidad || '1'}</span>
                            </div>
                            <div class="data-item">
                                <strong>Proveedor</strong>
                                <span>${data.proveedor || 'No detectado'}</span>
                            </div>
                            <div class="data-item">
                                <strong>Fecha</strong>
                                <span>${data.fecha || 'Hoy'}</span>
                            </div>
                            <div class="data-item">
                                <strong>Código</strong>
                                <span>${data.codigo || 'Auto-generado'}</span>
                            </div>
                            ${data.categoria ? `
                            <div class="data-item">
                                <strong>Categoría</strong>
                                <span>${data.categoria}</span>
                            </div>
                            ` : ''}
                            ${data.ubicacion ? `
                            <div class="data-item">
                                <strong>Ubicación</strong>
                                <span>${data.ubicacion}</span>
                            </div>
                            ` : ''}
                            ${data.descripcion ? `
                            <div class="data-item" style="grid-column: 1 / -1;">
                                <strong>Descripción</strong>
                                <span>${data.descripcion}</span>
                            </div>
                            ` : ''}
                        </div>
                        <div class="action-buttons">
                            <button class="btn btn-primary btn-sm" onclick="confirmData()">Confirmar y Agregar</button>
                            <button class="btn btn-secondary btn-sm" onclick="editData()">Editar Datos</button>
                            <button class="btn btn-light btn-sm" onclick="processNewImage()">Procesar Otra</button>
                        </div>
                    </div>
                </div>
            `;
            
            chatMessages.appendChild(aiMessage);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            extractedData = data;
        }

        function showError(message) {
            console.error('Error:', message);
            
            const chatMessages = document.getElementById('chatMessages');
            const preview = document.getElementById('uploadPreview');
            
            // Limpiar preview
            preview.innerHTML = '';
            
            // Agregar mensaje de error al chat
            const errorMessage = document.createElement('div');
            errorMessage.className = 'message ai';
            errorMessage.innerHTML = `
                <div class="message-avatar"></div>
                <div class="message-content" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                    <strong>Error:</strong> ${message}
                    <br><br>
                    <button class="btn btn-primary btn-sm" onclick="processNewImage()">Intentar de nuevo</button>
                </div>
            `;
            
            chatMessages.appendChild(errorMessage);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Mostrar notificación
            showNotification(message, 'error');
        }

        function showNotification(message, type = 'info') {
            // Crear notificación temporal
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                max-width: 300px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                font-weight: 500;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease-out;
            `;
            
            // Estilos según tipo
            if (type === 'error') {
                notification.style.background = '#dc3545';
            } else if (type === 'success') {
                notification.style.background = '#28a745';
            } else {
                notification.style.background = '#17a2b8';
            }
            
            document.body.appendChild(notification);
            
            // Remover después de 5 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }

        function confirmData() {
            // Aquí iría la lógica para agregar al inventario
            addMessage('user', 'Datos confirmados. Agregando al inventario...');
            
            setTimeout(() => {
                addMessage('ai', '¡Perfecto! El producto ha sido agregado exitosamente al inventario. ¿Necesitas procesar otra factura?');
            }, 1000);
        }

        function editData() {
            addMessage('user', 'Necesito editar los datos extraídos');
            addMessage('ai', 'Por supuesto. Puedes editar los datos en el formulario que aparecerá a continuación.');
        }

        function processNewImage() {
            addMessage('user', '🔄 Quiero procesar otra factura');
            addMessage('ai', '¡Perfecto! Sube otra foto de factura y la procesaré.');
            document.getElementById('facturaFile').value = '';
            currentImage = null;
            extractedData = null;
        }

        function addMessage(sender, content) {
            const chatMessages = document.getElementById('chatMessages');
            const message = document.createElement('div');
            message.className = `message ${sender}`;
            
            const avatar = sender === 'user' ? '👤' : '🤖';
            const avatarClass = sender === 'user' ? 'user' : 'ai';
            
            message.innerHTML = `
                <div class="message-avatar">${avatar}</div>
                <div class="message-content">${content}</div>
            `;
            
            chatMessages.appendChild(message);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Procesar enlaces de descarga si existen
            setTimeout(() => {
                const downloadLinks = message.querySelectorAll('a[href*="download_report.php"]');
                downloadLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = this.href;
                        
                        // Crear un enlace temporal y hacer clic
                        const tempLink = document.createElement('a');
                        tempLink.href = url;
                        tempLink.download = this.download || 'reporte.csv';
                        document.body.appendChild(tempLink);
                        tempLink.click();
                        document.body.removeChild(tempLink);
                        
                        // Mostrar notificación
                        showNotification('📥 Descargando reporte...', 'success');
                    });
                });
            }, 100);
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (message) {
                addMessage('user', message);
                input.value = '';
                
                // Enviar pregunta al servidor para procesamiento
                fetch('api_chat_response.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addMessage('ai', data.response);
                    } else {
                        addMessage('ai', 'Lo siento, no pude procesar tu pregunta. ¿Puedes intentar de nuevo?');
                    }
                })
                .catch(error => {
                    addMessage('ai', 'Error de conexión. ¿Puedes intentar de nuevo?');
                });
            }
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

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
    </script>
</body>
</html> 