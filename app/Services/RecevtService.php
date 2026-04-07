<?php
declare(strict_types=1);

namespace App\Services;

/**
 * RecevtService — automatiza el Libro de Tratamientos en recevet.es
 *
 * Flujo:
 *  1. login()       → GET login → parsea form → POST credenciales
 *  2. sincronizar() → GET libro → selecciona explotación → rellena fechas → acepta
 */
class RecevtService
{
    private const BASE_URL  = 'https://www.recevet.es';
    private const LOGIN_URL = 'https://www.recevet.es/index.php';
    private const LIBRO_URL = 'https://www.recevet.es/index.php?operacion=listadoLineasTratamientos';
    private const TIMEOUT   = 30;

    private string $cookieFile;
    private array  $logs          = [];
    private bool   $loggedIn      = false;
    private string $sessionCookie = '';

    public function __construct(string $sessionCookie = '')
    {
        $this->cookieFile    = sys_get_temp_dir() . '/recevet_' . session_id() . '.txt';
        $this->sessionCookie = $sessionCookie;

        // Pre-inyectar la cookie en el jar para que cURL la use desde el primer request
        if ($sessionCookie !== '') {
            $this->writeCookieToJar($sessionCookie);
        }
    }

    /**
     * Escribe la cookie de sesión en formato Netscape (que entiende cURL).
     * El string puede ser el valor completo del header Cookie:
     *   "PHPSESSID=abc123; otra=val"
     * o solo el valor de PHPSESSID.
     */
    private function writeCookieToJar(string $cookie): void
    {
        $lines   = ["# Netscape HTTP Cookie File\n"];
        $rawPairs = str_contains($cookie, '=') ? $cookie : 'PHPSESSID=' . $cookie;

        foreach (explode(';', $rawPairs) as $pair) {
            $pair  = trim($pair);
            $eqPos = strpos($pair, '=');
            if ($eqPos === false) continue;
            $name  = trim(substr($pair, 0, $eqPos));
            $value = trim(substr($pair, $eqPos + 1));
            if ($name === '') continue;
            // dominio  httpOnly  path  secure  expiry  name  value
            $lines[] = ".recevet.es\tTRUE\t/\tFALSE\t0\t{$name}\t{$value}\n";
        }

        file_put_contents($this->cookieFile, implode('', $lines));
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    // ── Cifrado ───────────────────────────────────────────────────

    public static function encryptPassword(string $plain): string
    {
        $key = self::deriveKey();
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($plain, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    public static function decryptPassword(string $encrypted): string
    {
        try {
            $data = base64_decode($encrypted, true);
            if ($data === false || strlen($data) < 17) return '';
            $key = self::deriveKey();
            $iv  = substr($data, 0, 16);
            $enc = substr($data, 16);
            $dec = openssl_decrypt($enc, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $dec !== false ? $dec : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function deriveKey(): string
    {
        $cfg = require ROOT_PATH . '/config.php';
        return substr(hash('sha256', ($cfg['db']['host'] ?? '') . ($cfg['db']['user'] ?? '') . ($cfg['db']['pass'] ?? '')), 0, 16);
    }

    // ── Login ─────────────────────────────────────────────────────

    /**
     * Login usando cookie de sesión (método principal).
     *
     * recevet.es protege su login con reCAPTCHA v3 + device fingerprint,
     * lo que impide el login automático con cURL. La solución es que el usuario
     * se loguee una vez en su navegador y pegue aquí la cookie de sesión.
     *
     * Si no hay cookie configurada, muestra instrucciones claras.
     */
    public function login(string $usuario, string $password): bool
    {
        $this->addLog('info', 'Verificando sesión en Recevet...');

        if ($this->sessionCookie === '') {
            $this->addLog('error',
                'No hay cookie de sesión configurada. ' .
                'Ve a Perfil → Credenciales Recevet y sigue las instrucciones para obtenerla.'
            );
            return false;
        }

        // Verificar que la sesión sigue activa con un GET a la página principal
        $html = $this->request('GET', self::LOGIN_URL . '?operacion=principal');

        if ($html !== null && $this->esRespuestaLogueado($html)) {
            $this->addLog('success', 'Sesión activa en Recevet ✓');
            $this->loggedIn = true;
            return true;
        }

        $this->addLog('error',
            'La cookie de sesión ha caducado o no es válida. ' .
            'Ve a recevet.es, inicia sesión y actualiza la cookie en tu perfil.'
        );
        if ($html !== null) {
            $this->addLog('info', 'Respuesta: ' . $this->fragmento($html));
        }
        return false;
    }

    private function esRespuestaLogueado(string $html): bool
    {
        return str_contains($html, 'Cerrar sesi') ||
               str_contains($html, 'cerrarSesion') ||
               str_contains($html, 'Libro de tratamientos') ||
               str_contains($html, 'listadoLineasTratamientos') ||
               (str_contains($html, 'Su p') && str_contains($html, 'gina principal'));
    }

    private function fragmento(string $html): string
    {
        $texto = substr(strip_tags($html), 0, 400);
        return trim((string)preg_replace('/\s+/', ' ', $texto));
    }

    // ── Sincronización ────────────────────────────────────────────

    public function sincronizar(string $explotacion, bool $dryRun = false): bool
    {
        if (!$this->loggedIn) {
            $this->addLog('error', 'No se ha iniciado sesión.');
            return false;
        }

        $this->addLog('info', "Abriendo Libro de Tratamientos: {$explotacion}");

        $html = $this->request('GET', self::LIBRO_URL);
        if ($html === null) {
            $this->addLog('error', 'No se pudo acceder al Libro de Tratamientos');
            return false;
        }

        $html = $this->seleccionarExplotacion($html, $explotacion);
        if ($html === null) return false;

        $lineas = $this->parsearLineasPendientes($html);
        if (empty($lineas)) {
            $this->addLog('success', 'No hay líneas pendientes. Todo al día.');
            return true;
        }

        $this->addLog('info', count($lineas) . ' línea(s) pendientes.');

        $ok  = 0;
        $err = 0;
        foreach ($lineas as $i => $linea) {
            $num         = $i + 1;
            $fechaInicio = $this->calcularFechaInicio($linea['fecha_dispensacion']);
            $fechaFin    = $this->calcularFechaFin($fechaInicio, $linea['dias_tratamiento']);

            $this->addLog('info', "Línea {$num}: {$linea['receta']} · {$linea['medicamento']} · Dispensado: {$linea['fecha_dispensacion']}");
            $this->addLog('info', "  → Inicio: {$fechaInicio}" . ($fechaFin ? " · Fin: {$fechaFin}" : ''));

            if ($dryRun) {
                $this->addLog('info', '  [SIMULACIÓN] No se envía.');
                continue;
            }

            if ($this->completarLinea($linea, $fechaInicio, $fechaFin)) {
                $this->addLog('success', "  ✓ Línea {$num} completada.");
                $ok++;
            } else {
                $this->addLog('error', "  ✗ Error al completar línea {$num}.");
                $err++;
            }

            usleep(500000);
        }

        $this->addLog('info', "Finalizado: {$ok} completadas, {$err} errores.");
        return $err === 0;
    }

    // ── Privados ──────────────────────────────────────────────────

    private function seleccionarExplotacion(string $html, string $explotacion): ?string
    {
        $dom = $this->parseDom($html);
        if (!$dom) {
            $this->addLog('error', 'Error al parsear HTML del libro de tratamientos');
            return null;
        }

        $xpath   = new \DOMXPath($dom);
        $selects = $xpath->query("//select[contains(@name,'explotacion') or contains(@id,'explotacion') or contains(@name,'Explotacion')]");

        if ($selects->length === 0) {
            $selects = $xpath->query("//select[.//option[contains(., '" . substr($explotacion, 0, 8) . "')]]");
        }

        if ($selects->length === 0) {
            if (str_contains($html, $explotacion)) {
                $this->addLog('info', 'Explotación ya seleccionada.');
                return $html;
            }
            $this->addLog('error', "No se encontró el desplegable de explotaciones para '{$explotacion}'.");
            return null;
        }

        $select     = $selects->item(0);
        $selectName = $select->getAttribute('name');

        $form = $select;
        while ($form && $form->nodeName !== 'form') {
            $form = $form->parentNode;
        }
        if (!$form || $form->nodeName !== 'form') {
            $this->addLog('error', 'No se encontró el formulario de selección de explotación');
            return null;
        }

        $fields              = $this->extraerCamposForm($form);
        $fields[$selectName] = $explotacion;

        $botones = $xpath->query(".//button[@type='submit'] | .//input[@type='submit']", $form);
        if ($botones->length > 0) {
            $b     = $botones->item(0);
            $bName = $b->getAttribute('name');
            $bVal  = $b->getAttribute('value');
            if ($bName) $fields[$bName] = $bVal;
        }

        $action    = $form->getAttribute('action') ?: self::LIBRO_URL;
        $method    = strtoupper($form->getAttribute('method') ?: 'POST');
        $respuesta = $this->request($method, $this->absoluteUrl($action), $fields);

        if ($respuesta === null) {
            $this->addLog('error', 'Error al seleccionar la explotación');
            return null;
        }

        $this->addLog('info', 'Explotación seleccionada.');
        return $respuesta;
    }

    private function parsearLineasPendientes(string $html): array
    {
        $lineas = [];
        $dom    = $this->parseDom($html);
        if (!$dom) return $lineas;

        $xpath = new \DOMXPath($dom);
        $filas = $xpath->query("//table//tr[.//input[@type='text' and (contains(@name,'fecha') or contains(@id,'fecha'))] | .//input[@type='date' and (contains(@name,'fecha') or contains(@id,'fecha'))]]");

        if ($filas->length === 0) {
            $filas = $xpath->query("//tr[.//button[contains(., 'Aceptar')] or .//input[@value='Aceptar']]");
        }

        foreach ($filas as $fila) {
            $linea = $this->extraerDatosLinea($fila, $dom);
            if ($linea !== null) {
                $lineas[] = $linea;
            }
        }

        return $lineas;
    }

    private function extraerDatosLinea(\DOMElement $fila, \DOMDocument $dom): ?array
    {
        $xpath     = new \DOMXPath($dom);
        $textoFila = $fila->textContent;

        $forms       = $xpath->query('.//form', $fila);
        $formElement = $forms->length > 0 ? $forms->item(0) : null;

        if (!$formElement) {
            $parent = $fila;
            while ($parent && $parent->nodeName !== 'form') {
                $parent = $parent->parentNode;
            }
            $formElement = ($parent && $parent->nodeName === 'form') ? $parent : null;
        }

        $fields = $formElement ? $this->extraerCamposForm($formElement) : [];
        $action = $formElement ? ($formElement->getAttribute('action') ?: self::LIBRO_URL) : self::LIBRO_URL;
        $method = $formElement ? strtoupper($formElement->getAttribute('method') ?: 'POST') : 'POST';

        $inputsFecha = $xpath->query(".//input[contains(@name,'fecha_inicio') or contains(@id,'fecha_inicio') or contains(@name,'fechaInicio') or contains(@name,'fecha')]", $fila);
        if ($inputsFecha->length === 0) return null;

        $inputFecha     = $inputsFecha->item(0);
        $fechaInputName = $inputFecha->getAttribute('name') ?: 'fecha_inicio';
        $fechaActual    = trim((string)$inputFecha->getAttribute('value'));
        if ($fechaActual !== '') return null;

        $fechaDispensacion = $this->extraerFechaDispensacion($textoFila, $fila, $dom);
        if (!$fechaDispensacion) return null;

        return [
            'receta'             => $this->extraerReceta($fila, $dom),
            'medicamento'        => $this->extraerMedicamento($fila, $dom),
            'fecha_dispensacion' => $fechaDispensacion,
            'dias_tratamiento'   => $this->extraerDiasTratamiento($textoFila),
            'fecha_input_name'   => $fechaInputName,
            'form_action'        => $this->absoluteUrl($action),
            'form_method'        => $method,
            'form_fields'        => $fields,
        ];
    }

    private function extraerFechaDispensacion(string $texto, \DOMElement $fila, \DOMDocument $dom): ?string
    {
        $xpath  = new \DOMXPath($dom);
        $celdas = $xpath->query(".//td[contains(., 'Dispensaci')]", $fila);
        if ($celdas->length > 0) {
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $celdas->item(0)->textContent, $m)) return $m[1];
        }

        if (preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $texto, $matches)) {
            return $matches[1][0];
        }

        $hiddens = $xpath->query(".//input[@type='hidden' and (contains(@name,'fecha') or contains(@name,'dispensa'))]", $fila);
        foreach ($hiddens as $h) {
            $val = (string)$h->getAttribute('value');
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $val, $m)) return $m[1];
            if (preg_match('/(\d{4}-\d{2}-\d{2})/',   $val, $m)) return $this->isoToEs($m[1]);
        }

        return null;
    }

    private function extraerDiasTratamiento(string $texto): int
    {
        if (preg_match('/(\d+)\s*d[ií]as?\s+tratamiento/i', $texto, $m)) return (int)$m[1];
        if (preg_match('/tratamiento[:\s]+(\d+)/i',          $texto, $m)) return (int)$m[1];
        if (preg_match('/duraci[oó]n[:\s]+(\d+)/i',          $texto, $m)) return (int)$m[1];
        return 1;
    }

    private function extraerReceta(\DOMElement $fila, \DOMDocument $dom): string
    {
        $xpath  = new \DOMXPath($dom);
        $celdas = $xpath->query('.//td', $fila);
        if ($celdas->length > 0) return trim((string)$celdas->item(0)->textContent);
        return '—';
    }

    private function extraerMedicamento(\DOMElement $fila, \DOMDocument $dom): string
    {
        $xpath  = new \DOMXPath($dom);
        $celdas = $xpath->query('.//td', $fila);
        if ($celdas->length > 1) return trim((string)$celdas->item(1)->textContent);
        return '—';
    }

    private function completarLinea(array $linea, string $fechaInicio, ?string $fechaFin): bool
    {
        $fields                             = $linea['form_fields'];
        $fields[$linea['fecha_input_name']] = $fechaInicio;

        foreach (array_keys($fields) as $key) {
            $keyLow = strtolower($key);
            if ((str_contains($keyLow, 'fecha_fin') || str_contains($keyLow, 'fechafin')) && $fechaFin) {
                $fields[$key] = $fechaFin;
            }
        }

        $respuesta = $this->request($linea['form_method'], $linea['form_action'], $fields);
        return $respuesta !== null;
    }

    // ── Fechas ────────────────────────────────────────────────────

    private function calcularFechaInicio(string $fechaDispensacion): string
    {
        $ts = $this->esDateToTimestamp($fechaDispensacion);
        if ($ts === null) return date('d/m/Y', strtotime('+1 day'));
        return date('d/m/Y', $ts + 86400);
    }

    private function calcularFechaFin(string $fechaInicio, int $diasTratamiento): ?string
    {
        if ($diasTratamiento <= 1) return null;
        $ts = $this->esDateToTimestamp($fechaInicio);
        if ($ts === null) return null;
        return date('d/m/Y', $ts + ($diasTratamiento - 1) * 86400);
    }

    // ── HTTP ──────────────────────────────────────────────────────

    private function request(string $method, string $url, array $data = []): ?string
    {
        if (!function_exists('curl_init')) {
            $this->addLog('error', 'cURL no disponible.');
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9',
                'Accept-Encoding: identity',
                'Referer: https://www.recevet.es/index.php',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            // recevet.es espera ISO-8859-1 en los POSTs
            $postData = http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error) {
            $this->addLog('error', "cURL error: {$error}");
            return null;
        }
        if ($httpCode >= 400) {
            $this->addLog('error', "HTTP {$httpCode} en {$url}");
            return null;
        }

        // recevet.es sirve ISO-8859-1 — convertir a UTF-8 para parseo consistente
        return $this->toUtf8($response);
    }

    /**
     * Convierte la respuesta de recevet.es (ISO-8859-1) a UTF-8.
     * Si ya es UTF-8 o no tiene meta charset ISO, la devuelve tal cual.
     */
    private function toUtf8(string $html): string
    {
        if (stripos($html, 'charset=ISO-8859-1') !== false ||
            stripos($html, 'charset=iso-8859-1') !== false) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
            $html = str_ireplace('charset=ISO-8859-1', 'charset=UTF-8', $html);
        }
        return $html;
    }

    // ── DOM ───────────────────────────────────────────────────────

    private function extraerCamposForm(\DOMElement $form): array
    {
        $fields = [];
        $doc    = $form->ownerDocument;
        $xpath  = new \DOMXPath($doc);

        foreach ($xpath->query('.//input', $form) as $input) {
            $type  = strtolower((string)($input->getAttribute('type') ?: 'text'));
            $name  = (string)$input->getAttribute('name');
            $value = (string)$input->getAttribute('value');
            if (!$name) continue;
            if (in_array($type, ['submit', 'button', 'image'])) continue;
            if ($type === 'radio' || $type === 'checkbox') {
                if ($input->getAttribute('checked')) $fields[$name] = $value;
                continue;
            }
            $fields[$name] = $value;
        }

        foreach ($xpath->query('.//select', $form) as $select) {
            $name = (string)$select->getAttribute('name');
            if (!$name) continue;
            $selected = $xpath->query('.//option[@selected]', $select);
            if ($selected->length > 0) {
                $fields[$name] = (string)$selected->item(0)->getAttribute('value');
            } else {
                $opts = $xpath->query('.//option', $select);
                if ($opts->length > 0) {
                    $fields[$name] = (string)$opts->item(0)->getAttribute('value');
                }
            }
        }

        foreach ($xpath->query('.//textarea', $form) as $ta) {
            $name = (string)$ta->getAttribute('name');
            if ($name) $fields[$name] = (string)$ta->textContent;
        }

        return $fields;
    }

    private function parseDom(string $html): ?\DOMDocument
    {
        if (!$html) return null;
        // toUtf8() ya habrá convertido la respuesta; nos aseguramos igualmente
        $html = $this->toUtf8($html);
        $dom  = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $dom;
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) return $url;
        if (str_starts_with($url, '/'))   return self::BASE_URL . $url;
        if (str_starts_with($url, '?'))   return self::BASE_URL . '/index.php' . $url;
        return self::BASE_URL . '/' . $url;
    }

    private function esDateToTimestamp(string $fecha): ?int
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return mktime(12, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return mktime(12, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        }
        return null;
    }

    private function isoToEs(string $iso): string
    {
        $p = explode('-', $iso);
        return $p[2] . '/' . $p[1] . '/' . $p[0];
    }

    // ── Log ───────────────────────────────────────────────────────

    private function addLog(string $type, string $msg): void
    {
        $this->logs[] = ['type' => $type, 'msg' => $msg, 'ts' => date('H:i:s')];
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
