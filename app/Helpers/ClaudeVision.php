<?php
declare(strict_types=1);

namespace App\Helpers;

class ClaudeVision
{
    private string $apiKey;

    public function __construct()
    {
        $cfg = require ROOT_PATH . '/config.php';
        $this->apiKey = $cfg['anthropic']['api_key'] ?? '';
    }

    /**
     * Analiza una imagen de un cuaderno de control de granja.
     * Devuelve array con: fecha, bajas[], traslados[], pienso[]
     */
    public function analizarCuaderno(string $imagePath): array
    {
        if (!$this->apiKey) {
            throw new \Exception('API key de Anthropic no configurada.');
        }

        $imageData   = base64_encode(file_get_contents($imagePath));
        $mimeType    = mime_content_type($imagePath);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            $mimeType = 'image/jpeg';
        }

        $prompt = <<<PROMPT
Analiza esta imagen de un cuaderno de control de granja porcina. Extrae todos los datos que veas escritos.

El cuaderno puede tener algunas o todas estas secciones:
- Fecha (arriba, formato DD-MM-YY o DD-MM-YYYY)
- BAJAS: columnas LOTE | CANTIDAD | NAVE/CUADRA | MOTIVO
  * Si aparece texto como "SIN BAJAS", "NO BAJAS", "0 BAJAS" → devuelve bajas=[]
- TRASLADOS: columnas LOTE | ORIGEN | DESTINO | CANTIDAD
- PESAJES: columnas LOTE | CUADRA | PESO MEDIO (kg) | Nº ANIMALES PESADOS
- PIENSO/SILOS: columnas SILO (nombre o número) | KG | PROVEEDOR | ALBARÁN

Responde ÚNICAMENTE con un JSON válido con esta estructura exacta, sin texto adicional:
{
  "fecha": "YYYY-MM-DD o null si no se ve",
  "bajas": [
    {
      "lote": "código numérico del lote, ej: 34",
      "cantidad": número,
      "nave_cuadra": "texto de nave/cuadra, ej: C7-74",
      "motivo": "enfermedad, canibalismo, sacrificio, aplastamiento u otro según lo escrito, o null"
    }
  ],
  "traslados": [
    {
      "lote": "código numérico del lote",
      "origen": "nave/cuadra origen, ej: C7-61",
      "destino": "nave/cuadra destino, ej: C7-58",
      "cantidad": número
    }
  ],
  "pesajes": [
    {
      "lote": "código numérico del lote",
      "cuadra": "nave/cuadra, ej: C7-58 o null",
      "peso_medio_kg": número con decimales,
      "num_animales": número entero o null
    }
  ],
  "reposiciones_silo": [
    {
      "silo": "nombre o número del silo tal como aparece escrito",
      "cantidad_kg": número,
      "proveedor": "nombre del proveedor o null",
      "albaran": "número de albarán o null"
    }
  ]
}

Si una sección está vacía o no aparece en el documento, devuelve array vacío [].
Si no puedes leer un valor con certeza escribe null.
PROMPT;

        $payload = json_encode([
            'model'      => 'claude-opus-4-6',
            'max_tokens' => 1024,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    [
                        'type'  => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $mimeType,
                            'data'       => $imageData,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                ],
            ]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            throw new \Exception("Error al llamar a la API de Anthropic (HTTP {$httpCode}).");
        }

        $body = json_decode($response, true);
        $text = $body['content'][0]['text'] ?? '';

        // Extraer JSON de la respuesta
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $data = json_decode($matches[0], true);
            if ($data !== null) {
                return $data;
            }
        }

        throw new \Exception('No se pudo extraer datos estructurados de la imagen.');
    }
}
