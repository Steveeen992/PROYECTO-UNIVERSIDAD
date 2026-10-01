<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar que se haya enviado una imagen
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió una imagen válida']);
    exit;
}

try {
    $imageFile = $_FILES['image'];
    
    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($imageFile['type'], $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten imágenes JPEG, PNG o GIF.');
    }
    
    // Validar tamaño (máximo 10MB)
    if ($imageFile['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo es demasiado grande. Máximo 10MB.');
    }
    
    // Leer la imagen y convertir a base64
    $imageData = file_get_contents($imageFile['tmp_name']);
    $base64Image = base64_encode($imageData);
    
    // Configuración de OpenAI (debes agregar tu API key en config.php)
    $openaiApiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : null;
    
    if (!$openaiApiKey) {
        // Si no hay API key configurada, usar datos simulados
        $extractedData = simulateInvoiceProcessing($imageFile['name']);
    } else {
        // Procesar con OpenAI GPT-4 Vision
        $extractedData = processWithOpenAI($base64Image, $openaiApiKey);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $extractedData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

function simulateInvoiceProcessing($filename) {
    // Simular procesamiento de factura
    $mockData = [
        'producto' => 'Pantalla Samsung 20" LED',
        'precio' => '150000',
        'cantidad' => '1',
        'proveedor' => 'Distribuidora ABC',
        'fecha' => date('Y-m-d'),
        'codigo' => 'SAMS20LED' . rand(100, 999),
        'descripcion' => 'Monitor LED de 20 pulgadas marca Samsung',
        'categoria' => 'Monitores',
        'ubicacion' => 'Almacén Principal'
    ];
    
    return $mockData;
}

function processWithOpenAI($base64Image, $apiKey) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $prompt = "Analiza esta imagen de factura y extrae los siguientes datos en formato JSON:
    - producto: nombre del producto
    - precio: precio unitario (solo números)
    - cantidad: cantidad comprada
    - proveedor: nombre del proveedor o empresa
    - fecha: fecha de la factura (formato YYYY-MM-DD)
    - codigo: código del producto si está visible
    - descripcion: descripción breve del producto
    - categoria: categoría que mejor describa el producto
    - ubicacion: ubicación sugerida para almacenamiento
    
    Responde SOLO con el JSON, sin texto adicional.";
    
    $data = [
        'model' => 'gpt-4o',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,' . $base64Image
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => 500,
        'temperature' => 0.1
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        // Guardar respuesta completa en un archivo de log para depuración
        file_put_contents('openai_api_error.log', date('Y-m-d H:i:s') . "\n" . $response . "\n\n", FILE_APPEND);
        throw new Exception('Error al procesar con OpenAI: ' . $response);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Respuesta inesperada de OpenAI');
    }
    
    $content = $result['choices'][0]['message']['content'];
    
    // Extraer JSON de la respuesta
    $jsonStart = strpos($content, '{');
    $jsonEnd = strrpos($content, '}');
    
    if ($jsonStart === false || $jsonEnd === false) {
        throw new Exception('No se pudo extraer datos JSON de la respuesta');
    }
    
    $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
    $extractedData = json_decode($jsonString, true);
    
    if (!$extractedData) {
        throw new Exception('Error al parsear JSON extraído');
    }
    
    return $extractedData;
}
?> 