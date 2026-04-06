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

El cuaderno tiene:
- Fecha (arriba a la derecha, formato DD-MM-YY o DD-MM-YYYY)
- Sección izquierda con columnas: LOTE | BAJAS | NAVE/CUADRA | y posible motivo escrito debajo
  * Si en la columna LOTE aparece texto como "SIN BAJAS", "NO BAJAS", "0 BAJAS" o similar, significa que no hay bajas → devuelve bajas=[]
- Sección derecha con columnas: LOTE | ORIGEN | DESTINO | CANTIDAD (traslados)
- Sección inferior: PIENSO | NAVE/CUADRA

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
  "pienso": [
    {
      "tipo": "texto del pienso",
      "nave_cuadra": "nave/cuadra"
    }
  ]
}

Si una sección está vacía o indica explícitamente que no hay datos, devuelve array vacío [].
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
