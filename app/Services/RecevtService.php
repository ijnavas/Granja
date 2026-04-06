<?php
declare(strict_types=1);

namespace App\Services;

/**
 * RecevtService — automatiza la cumplimentación del Libro de Tratamientos en recevet.es
 *
 * Flujo:
 *  1. login()           → POST credenciales, mantiene sesión con cookie jar
 *  2. sincronizar()     → navega al Libro de Tratamientos, selecciona explotación,
 *                         rellena fecha inicio (dispensación + 1 día) y acepta
 */
class RecevtService
{
    private const BASE_URL   = 'https://www.recevet.es';
    private const LOGIN_URL  = 'https://www.recevet.es/index.php';
    private const LIBRO_URL  = 'https://www.recevet.es/index.php?operacion=listadoLineasTratamientos';
    private const TIMEOUT    = 30;

    private string  $cookieFile;
    private array   $log = [];
    private bool    $loggedIn = false;

    public function __construct()
    {
        $this->cookieFile = sys_get_temp_dir() . '/recevet_' . session_id() . '.txt';
    }

    public function __destruct()
    {
        // Limpiar cookie jar al terminar
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    // ── Cifrado / descifrado de contraseña ────────────────────────

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
        // Deriva 16 bytes de clave a partir de las credenciales de BD (ya secretas)
        return substr(hash('sha256', ($cfg['db']['host'] ?? '') . ($cfg['db']['user'] ?? '') . ($cfg['db']['pass'] ?? '')), 0, 16);
    }

    // ── Login ─────────────────────────────────────────────────────

    public function login(string $usuario, string $password): bool
    {
        $this->log('info', 'Iniciando sesión en Recevet...');

        // 1. GET página de login para obtener campos ocultos / cookies iniciales
        $html = $this->request('GET', self::LOGIN_URL);
        if ($html === null) {
            $this->log('error', 'No se pudo conectar con recevet.es');
            return false;
        }

        // 2. Aceptar cookies si hay un banner
        $this->aceptarCookies($html);

        // 3. Parsear formulario de login
        $form = $this->parseLoginForm($html);
        if ($form === null) {
            // Puede que ya estemos logueados o que la estructura haya cambiado
            if (str_contains($html, 'operacion=principal') || str_contains($html, 'Cerrar sesión') || str_contains($html, 'Cerrar sesi')) {
                $this->log('success', 'Ya estaba logueado en Recevet.');
                $this->loggedIn = true;
                return true;
            }
            $this->log('error', 'No se encontró el formulario de login en recevet.es');
            return false;
        }

        // 4. POST con credenciales
        $form['fields']['usuario']    = $usuario;
        $form['fields']['password']   = $password;
        // Algunos sitios usan 'pass', 'passwd', 'clave'...
        foreach (['pass', 'passwd', 'clave', 'contrasena', 'contraseña'] as $f) {
            if (isset($form['fields'][$f])) {
                $form['fields'][$f] = $password;
            }
        }

        $respuesta = $this->request('POST', $form['action'] ?: self::LOGIN_URL, $form['fields']);
        if ($respuesta === null) {
            $this->log('error', 'Error al enviar credenciales');
            return false;
        }

        // 5. Verificar login exitoso
        if (str_contains($respuesta, 'Cerrar sesión') || str_contains($respuesta, 'Cerrar sesi') ||
            str_contains($respuesta, 'operacion=principal') || str_contains($respuesta, 'Libro de tratamientos') ||
            str_contains($respuesta, 'Su página principal') || str_contains($respuesta, 'página principal')) {
            $this->log('success', 'Login correcto en Recevet.');
            $this->loggedIn = true;
            return true;
        }

        if (str_contains($respuesta, 'incorrecto') || str_contains($respuesta, 'inválido') ||
            str_contains($respuesta, 'no válido') || str_contains($respuesta, 'error')) {
            $this->log('error', 'Credenciales incorrectas en Recevet.');
        } else {
            $this->log('error', 'Login fallido — respuesta inesperada de recevet.es');
        }
        return false;
    }

    // ── Sincronización ─────────────────────────────────────────────

    /**
     * @param string $explotacion  Código de explotación (ej: ES410040000003)
     * @param bool   $dryRun       Si true, muestra qué haría pero no envía
     */
    public function sincronizar(string $explotacion, bool $dryRun = false): bool
    {
        if (!$this->loggedIn) {
            $this->log('error', 'No se ha iniciado sesión. Llama primero a login().');
            return false;
        }

        $this->log('info', "Abriendo Libro de Tratamientos para explotación: {$explotacion}");

        // 1. GET libro de tratamientos
        $html = $this->request('GET', self::LIBRO_URL);
        if ($html === null) {
            $this->log('error', 'No se pudo acceder al Libro de Tratamientos');
            return false;
        }

        // 2. Seleccionar explotación en el desplegable (POST o GET con parámetro)
        $html = $this->seleccionarExplotacion($html, $explotacion);
        if ($html === null) {
            return false;
        }

        // 3. Parsear líneas pendientes de completar
        $lineas = $this->parsearLineasPendientes($html);
        if (empty($lineas)) {
            $this->log('success', 'No hay líneas pendientes de completar. Todo al día.');
            return true;
        }

        $this->log('info', count($lineas) . ' línea(s) pendientes de completar.');

        // 4. Completar cada línea
        $ok = 0;
        $err = 0;
        foreach ($lineas as $i => $linea) {
            $num = $i + 1;
            $fechaInicio = $this->calcularFechaInicio($linea['fecha_dispensacion']);
            $fechaFin    = $this->calcularFechaFin($fechaInicio, $linea['dias_tratamiento']);

            $desc = "Receta {$linea['receta']} · {$linea['medicamento']} · Dispensado: {$linea['fecha_dispensacion']}";
            $this->log('info', "Línea {$num}: {$desc}");
            $this->log('info', "  → Inicio: {$fechaInicio}" . ($fechaFin ? " · Fin: {$fechaFin}" : ''));

            if ($dryRun) {
                $this->log('info', "  [SIMULACIÓN] No se envía.");
                continue;
            }

            $result = $this->completarLinea($linea, $fechaInicio, $fechaFin);
            if ($result) {
                $this->log('success', "  ✓ Línea {$num} completada correctamente.");
                $ok++;
            } else {
                $this->log('error', "  ✗ Error al completar línea {$num}.");
                $err++;
            }

            // Pequeña pausa para no sobrecargar el servidor
            usleep(500000); // 0.5 segundos
        }

        $this->log('info', "Sincronización finalizada: {$ok} completadas, {$err} errores.");
        return $err === 0;
    }

    // ── Helpers privados ───────────────────────────────────────────

    private function aceptarCookies(string $html): void
    {
        // Intentar detectar y aceptar banner de cookies
        if (!str_contains($html, 'cookie') && !str_contains($html, 'Cookie')) {
            return;
        }

        // Opción 1: formulario con botón "Aceptar" cookies
        $dom = $this->parseDom($html);
        if (!$dom) return;

        $xpath = new \DOMXPath($dom);

        // Buscar botones/links de aceptar cookies
        $botones = $xpath->query("//button[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'aceptar') and (contains(@class,'cookie') or contains(@id,'cookie') or ancestor::*[contains(@class,'cookie') or contains(@id,'cookie')])]");

        if ($botones->length > 0) {
            // Buscar el form que contiene el botón
            $boton = $botones->item(0);
            $form  = $boton;
            while ($form && $form->nodeName !== 'form') {
                $form = $form->parentNode;
            }
            if ($form && $form->nodeName === 'form') {
                $fields = $this->extraerCamposForm($form);
                $action = $form->getAttribute('action') ?: self::LOGIN_URL;
                $this->request('POST', $this->absoluteUrl($action), $fields);
                $this->log('info', 'Banner de cookies aceptado.');
                return;
            }
        }

        // Opción 2: simplemente establecer la cookie de consentimiento
        // (muchos sitios solo comprueban si existe la cookie)
        // Esto se maneja automáticamente con el cookie jar de cURL
    }

    private function seleccionarExplotacion(string $html, string $explotacion): ?string
    {
        // Buscar el formulario de selección de explotación
        $dom = $this->parseDom($html);
        if (!$dom) {
            $this->log('error', 'Error al parsear HTML del libro de tratamientos');
            return null;
        }

        $xpath = new \DOMXPath($dom);

        // Buscar select con la lista de explotaciones
        $selects = $xpath->query("//select[contains(@name,'explotacion') or contains(@id,'explotacion') or contains(@name,'Explotacion')]");

        if ($selects->length === 0) {
            // Intentar con otro patrón — a veces está en el name del option
            $selects = $xpath->query("//select[.//option[contains(., '" . substr($explotacion, 0, 8) . "')]]");
        }

        if ($selects->length === 0) {
            // Si ya está seleccionada la explotación, continuar
            if (str_contains($html, $explotacion)) {
                $this->log('info', 'Explotación ya seleccionada.');
                return $html;
            }
            $this->log('error', "No se encontró el desplegable de explotaciones. ¿Está configurado el código '{$explotacion}'?");
            return null;
        }

        $select = $selects->item(0);
        $selectName = $select->getAttribute('name');

        // Obtener el form que contiene el select
        $form = $select;
        while ($form && $form->nodeName !== 'form') {
            $form = $form->parentNode;
        }

        if (!$form || $form->nodeName !== 'form') {
            $this->log('error', 'No se encontró el formulario de selección de explotación');
            return null;
        }

        $fields = $this->extraerCamposForm($form);
        $fields[$selectName] = $explotacion;

        // Buscar el botón de generar/filtrar
        $botones = $xpath->query(".//button[@type='submit'] | .//input[@type='submit']", $form);
        if ($botones->length > 0) {
            $b = $botones->item(0);
            $bName = $b->getAttribute('name');
            $bVal  = $b->getAttribute('value');
            if ($bName) $fields[$bName] = $bVal;
        }

        $action = $form->getAttribute('action') ?: self::LIBRO_URL;
        $method = strtoupper($form->getAttribute('method') ?: 'POST');

        $respuesta = $this->request($method, $this->absoluteUrl($action), $fields);
        if ($respuesta === null) {
            $this->log('error', 'Error al seleccionar la explotación');
            return null;
        }

        $this->log('info', 'Explotación seleccionada correctamente.');
        return $respuesta;
    }

    private function parsearLineasPendientes(string $html): array
    {
        $lineas = [];
        $dom    = $this->parseDom($html);
        if (!$dom) return $lineas;

        $xpath = new \DOMXPath($dom);

        // Buscar filas de la tabla del libro de tratamientos
        // Buscamos filas que tengan un input de fecha vacío (la que hay que rellenar)
        $filas = $xpath->query("//table//tr[.//input[@type='text' and (contains(@name,'fecha') or contains(@id,'fecha'))] | .//input[@type='date' and (contains(@name,'fecha') or contains(@id,'fecha'))]]");

        if ($filas->length === 0) {
            // Intentar con un patrón más amplio: filas con botón "Aceptar"
            $filas = $xpath->query("//tr[.//button[contains(., 'Aceptar')] or .//input[@value='Aceptar']]");
        }

        foreach ($filas as $fila) {
            $xpathFila = new \DOMXPath($dom);
            $celdas = $xpathFila->query('.//td', $fila);

            // Extraer datos de la fila
            $linea = $this->extraerDatosLinea($fila, $dom);
            if ($linea !== null) {
                $lineas[] = $linea;
            }
        }

        return $lineas;
    }

    private function extraerDatosLinea(\DOMElement $fila, \DOMDocument $dom): ?array
    {
        $xpath = new \DOMXPath($dom);

        // Texto completo de la fila para buscar datos
        $textoFila = $fila->textContent;

        // Buscar el form dentro de la fila (o el form más cercano)
        $forms = $xpath->query('.//form', $fila);
        $formElement = $forms->length > 0 ? $forms->item(0) : null;

        // Si no hay form en la fila, buscar el form padre
        if (!$formElement) {
            $parent = $fila;
            while ($parent && $parent->nodeName !== 'form') {
                $parent = $parent->parentNode;
            }
            $formElement = ($parent && $parent->nodeName === 'form') ? $parent : null;
        }

        // Extraer campos del form
        $fields = $formElement ? $this->extraerCamposForm($formElement) : [];
        $action = $formElement ? ($formElement->getAttribute('action') ?: self::LIBRO_URL) : self::LIBRO_URL;
        $method = $formElement ? strtoupper($formElement->getAttribute('method') ?: 'POST') : 'POST';

        // Buscar campo de fecha inicio (que esté vacío o sea el que hay que rellenar)
        $inputsFecha = $xpath->query(".//input[contains(@name,'fecha_inicio') or contains(@id,'fecha_inicio') or contains(@name,'fechaInicio') or contains(@name,'fecha')]", $fila);
        if ($inputsFecha->length === 0) return null;

        $inputFecha = $inputsFecha->item(0);
        $fechaInputName = $inputFecha->getAttribute('name') ?: 'fecha_inicio';

        // Si ya tiene fecha, no está pendiente
        $fechaActual = trim($inputFecha->getAttribute('value') ?? '');
        if ($fechaActual !== '') return null;

        // Buscar fecha de dispensación en el texto de la fila
        // Patrones: "Fecha Dispensacion: DD/MM/YYYY" o en el HTML de la fila
        $fechaDispensacion = $this->extraerFechaDispensacion($textoFila, $fila, $dom);

        // Buscar días de tratamiento
        $diasTratamiento = $this->extraerDiasTratamiento($textoFila);

        // Extraer número de receta
        $receta = $this->extraerReceta($textoFila, $fila, $dom);

        // Extraer medicamento
        $medicamento = $this->extraerMedicamento($textoFila, $fila, $dom);

        if (!$fechaDispensacion) {
            // Si no podemos determinar la fecha de dispensación, saltar esta línea
            return null;
        }

        return [
            'receta'             => $receta,
            'medicamento'        => $medicamento,
            'fecha_dispensacion' => $fechaDispensacion,
            'dias_tratamiento'   => $diasTratamiento,
            'fecha_input_name'   => $fechaInputName,
            'form_action'        => $this->absoluteUrl($action),
            'form_method'        => $method,
            'form_fields'        => $fields,
        ];
    }

    private function extraerFechaDispensacion(string $texto, \DOMElement $fila, \DOMDocument $dom): ?string
    {
        // Buscar en el texto de la fila patrones de fecha DD/MM/YYYY
        // La fecha de dispensación suele estar en la celda "Datos Dispensación"
        $xpath = new \DOMXPath($dom);

        // Buscar celdas que contengan "Dispensaci"
        $celdas = $xpath->query(".//td[contains(., 'Dispensaci')]", $fila);
        if ($celdas->length > 0) {
            $txt = $celdas->item(0)->textContent;
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $txt, $m)) {
                return $m[1];
            }
        }

        // Buscar cualquier fecha en la fila
        if (preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $texto, $matches)) {
            // Tomar la primera fecha encontrada
            return $matches[1][0];
        }

        // Buscar en inputs ocultos que contengan fecha
        $hiddens = $xpath->query(".//input[@type='hidden' and (contains(@name,'fecha') or contains(@name,'dispensa'))]", $fila);
        foreach ($hiddens as $h) {
            $val = $h->getAttribute('value');
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $val, $m)) return $m[1];
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $val, $m)) return $this->isoToEs($m[1]);
        }

        return null;
    }

    private function extraerDiasTratamiento(string $texto): int
    {
        // Buscar patrones: "7 días", "7 Días", "Duración: 7"
        if (preg_match('/(\d+)\s*d[ií]as?\s+tratamiento/i', $texto, $m)) return (int)$m[1];
        if (preg_match('/tratamiento[:\s]+(\d+)/i', $texto, $m)) return (int)$m[1];
        if (preg_match('/duraci[oó]n[:\s]+(\d+)/i', $texto, $m)) return (int)$m[1];
        // Valor por defecto: 1 día
        return 1;
    }

    private function extraerReceta(string $texto, \DOMElement $fila, \DOMDocument $dom): string
    {
        $xpath = new \DOMXPath($dom);
        // Primera celda suele ser la receta
        $celdas = $xpath->query('.//td', $fila);
        if ($celdas->length > 0) {
            return trim($celdas->item(0)->textContent);
        }
        if (preg_match('/([A-Z]{2,3}\d{5,})/i', $texto, $m)) return $m[1];
        return '—';
    }

    private function extraerMedicamento(string $texto, \DOMElement $fila, \DOMDocument $dom): string
    {
        $xpath = new \DOMXPath($dom);
        $celdas = $xpath->query('.//td', $fila);
        if ($celdas->length > 1) {
            return trim($celdas->item(1)->textContent);
        }
        return '—';
    }

    private function completarLinea(array $linea, string $fechaInicio, ?string $fechaFin): bool
    {
        $fields = $linea['form_fields'];

        // Poner fecha inicio en el campo correcto
        $fields[$linea['fecha_input_name']] = $fechaInicio;

        // Si hay campo de fecha fin, rellenarlo también
        foreach (array_keys($fields) as $key) {
            if (str_contains(strtolower($key), 'fecha_fin') || str_contains(strtolower($key), 'fechafin')) {
                if ($fechaFin) $fields[$key] = $fechaFin;
            }
        }

        // Simular click en botón "Aceptar"
        // Buscar el botón submit del form
        $respuesta = $this->request($linea['form_method'], $linea['form_action'], $fields);

        if ($respuesta === null) return false;

        // Verificar éxito: la fila debería desaparecer o mostrar la fecha
        return !str_contains($respuesta, 'Error') && !str_contains($respuesta, 'error');
    }

    // ── Cálculo de fechas ─────────────────────────────────────────

    private function calcularFechaInicio(string $fechaDispensacion): string
    {
        // fecha_dispensacion en formato DD/MM/YYYY
        $ts = $this->esDateToTimestamp($fechaDispensacion);
        if ($ts === null) return date('d/m/Y', strtotime('+1 day'));
        return date('d/m/Y', $ts + 86400); // +1 día
    }

    private function calcularFechaFin(string $fechaInicio, int $diasTratamiento): ?string
    {
        if ($diasTratamiento <= 1) return null; // sin fecha fin si 1 día
        $ts = $this->esDateToTimestamp($fechaInicio);
        if ($ts === null) return null;
        return date('d/m/Y', $ts + ($diasTratamiento - 1) * 86400);
    }

    // ── HTTP helpers ──────────────────────────────────────────────

    private function request(string $method, string $url, array $data = []): ?string
    {
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
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9',
                'Accept-Encoding: identity',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error) {
            $this->log('error', "cURL error: {$error}");
            return null;
        }

        if ($httpCode >= 400) {
            $this->log('error', "HTTP {$httpCode} al acceder a {$url}");
            return null;
        }

        return $response;
    }

    // ── DOM / form helpers ────────────────────────────────────────

    private function parseLoginForm(string $html): ?array
    {
        $dom = $this->parseDom($html);
        if (!$dom) return null;

        $xpath = new \DOMXPath($dom);

        // Buscar form de login: que contenga campos usuario y contraseña
        $forms = $xpath->query("//form");
        foreach ($forms as $form) {
            $fields = $this->extraerCamposForm($form);
            $hasUser = false;
            $hasPass = false;
            foreach (array_keys($fields) as $name) {
                $nameLow = strtolower($name);
                if (str_contains($nameLow, 'user') || str_contains($nameLow, 'login') || $nameLow === 'usuario') $hasUser = true;
                if (str_contains($nameLow, 'pass') || str_contains($nameLow, 'clave') || str_contains($nameLow, 'pwd'))   $hasPass = true;
            }
            // Si no encontramos por nombre, buscar por tipo
            $passInputs = $xpath->query('.//input[@type="password"]', $form);
            if ($passInputs->length > 0) $hasPass = true;
            $textInputs = $xpath->query('.//input[@type="text" or @type="email" or not(@type)]', $form);
            if ($textInputs->length > 0) $hasUser = true;

            if ($hasUser && $hasPass) {
                // Ajustar nombres de campo para usuario y contraseña
                foreach ($xpath->query('.//input[@type="password"]', $form) as $input) {
                    $name = $input->getAttribute('name');
                    if ($name) $fields[$name] = ''; // placeholder, se reemplazará
                }
                // Buscar el campo de usuario (primer texto/email)
                foreach ($xpath->query('.//input[@type="text" or @type="email" or not(@type)]', $form) as $input) {
                    $name = $input->getAttribute('name');
                    if ($name && !isset($fields['usuario'])) $fields['usuario_real_field'] = $name;
                }

                $action = $form->getAttribute('action') ?: self::LOGIN_URL;
                return [
                    'action' => $this->absoluteUrl($action),
                    'method' => strtoupper($form->getAttribute('method') ?: 'POST'),
                    'fields' => $fields,
                ];
            }
        }

        return null;
    }

    private function extraerCamposForm(\DOMElement $form): array
    {
        $fields = [];
        $doc    = $form->ownerDocument;
        $xpath  = new \DOMXPath($doc);

        // Inputs (hidden, text, radio checked, checkbox checked)
        foreach ($xpath->query('.//input', $form) as $input) {
            $type  = strtolower($input->getAttribute('type') ?: 'text');
            $name  = $input->getAttribute('name');
            $value = $input->getAttribute('value');
            if (!$name) continue;
            if ($type === 'submit' || $type === 'button' || $type === 'image') continue;
            if ($type === 'radio' || $type === 'checkbox') {
                if ($input->getAttribute('checked')) $fields[$name] = $value;
                continue;
            }
            $fields[$name] = $value;
        }

        // Selects (valor seleccionado)
        foreach ($xpath->query('.//select', $form) as $select) {
            $name     = $select->getAttribute('name');
            if (!$name) continue;
            $selected = $xpath->query('.//option[@selected]', $select);
            if ($selected->length > 0) {
                $fields[$name] = $selected->item(0)->getAttribute('value');
            } else {
                $opts = $xpath->query('.//option', $select);
                if ($opts->length > 0) {
                    $fields[$name] = $opts->item(0)->getAttribute('value');
                }
            }
        }

        // Textareas
        foreach ($xpath->query('.//textarea', $form) as $ta) {
            $name = $ta->getAttribute('name');
            if ($name) $fields[$name] = $ta->textContent;
        }

        return $fields;
    }

    private function parseDom(string $html): ?\DOMDocument
    {
        if (empty($html)) return null;
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $dom;
    }

    private function absoluteUrl(string $url): string
    {
        if (strpos($url, 'http') === 0) return $url;
        if (strpos($url, '/') === 0)   return self::BASE_URL . $url;
        return self::BASE_URL . '/' . $url;
    }

    private function esDateToTimestamp(string $fecha): ?int
    {
        // DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return mktime(12, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
        }
        // YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return mktime(12, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        }
        return null;
    }

    private function isoToEs(string $iso): string
    {
        [$y, $m, $d] = explode('-', $iso);
        return "{$d}/{$m}/{$y}";
    }

    // ── Log ───────────────────────────────────────────────────────

    private function log(string $type, string $msg): void
    {
        $this->log[] = ['type' => $type, 'msg' => $msg, 'ts' => date('H:i:s')];
    }

    public function getLogs(): array
    {
        return $this->log;
    }
}
