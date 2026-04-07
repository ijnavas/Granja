<?php
declare(strict_types=1);

namespace App\Services;

/**
 * RecevtService — automatiza el Libro de Tratamientos en recevet.es
 *
 * Flujo de autenticación (dos pasos):
 *  1. iniciarLogin()      → valida fingerprint → envía email con código 2FA
 *  2. verificarCodigo2fa() → valida código → hace login final → devuelve cookie
 *
 * Flujo de sincronización (una vez autenticado):
 *  1. login()       → verifica sesión con cookie guardada en BD
 *  2. sincronizar() → selecciona explotación → rellena fechas → acepta
 */
class RecevtService
{
    private const BASE_URL  = 'https://www.recevet.es';
    private const LOGIN_URL = 'https://www.recevet.es/index.php';
    private const LIBRO_URL = 'https://www.recevet.es/index.php?operacion=listadoLineasTratamientos';
    private const PC        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.361920x1080es-ESEurope/Madrid';
    private const TIMEOUT   = 30;

    private string $cookieFile;
    private bool   $persistCookie; // no borrar en __destruct (flujo 2FA inter-request)
    private array  $logs      = [];
    private bool   $loggedIn  = false;

    /**
     * @param int    $uid           ID del usuario (0 = sin persistencia entre requests)
     * @param string $sessionCookie Cookie guardada en BD para sincronización
     */
    public function __construct(int $uid = 0, string $sessionCookie = '')
    {
        if ($uid > 0) {
            // Archivo persistente entre los dos pasos del flujo 2FA
            $this->cookieFile    = sys_get_temp_dir() . '/recevet_uid_' . $uid . '.txt';
            $this->persistCookie = true;
        } else {
            $this->cookieFile    = sys_get_temp_dir() . '/recevet_' . session_id() . '.txt';
            $this->persistCookie = false;
        }

        if ($sessionCookie !== '') {
            $this->writeCookieToJar($sessionCookie);
            $this->persistCookie = false; // recreada desde BD, no necesita persistir
        }
    }

    public function __destruct()
    {
        if (!$this->persistCookie && file_exists($this->cookieFile)) {
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

    // ── Paso 1: Iniciar login (envía email con código 2FA) ─────────

    /**
     * Inicia el proceso de login en recevet.es.
     *
     * Retorna:
     *   ['status' => 'ok']              → sesión directa (fingerprint de confianza)
     *   ['status' => 'needs_2fa',
     *    'controlForm' => '...']        → se ha enviado el email con el código
     *   ['status' => 'error',
     *    'msg' => '...']                → fallo
     */
    public function iniciarLogin(string $usuario, string $password): array
    {
        $this->addLog('info', 'Conectando con recevet.es...');

        // 1. GET para obtener el token controlForm
        $html = $this->request('GET', self::LOGIN_URL);
        if ($html === null) {
            return ['status' => 'error', 'msg' => 'No se pudo conectar con recevet.es'];
        }

        // ¿Ya hay sesión activa? (cookie persistente del paso anterior)
        if ($this->esRespuestaLogueado($html)) {
            $this->addLog('success', 'Sesión ya activa.');
            return ['status' => 'ok'];
        }

        $controlForm = $this->extraerControlForm($html);
        $this->addLog('info', 'Comprobando dispositivo...');

        // 2. Comprobar si el fingerprint es de confianza
        $r = $this->request('POST', self::LOGIN_URL . '?operacion=validadorNavegador2fa', [
            'user'  => $usuario,
            'datos' => self::PC,
        ]);
        $trusted = ($r !== null && trim($r) === '{}');

        if ($trusted) {
            // Sin 2FA: login directo
            $this->addLog('info', 'Dispositivo de confianza. Iniciando sesión...');
            $result = $this->postLogin($controlForm, $usuario, $password, '');
            if ($result) return ['status' => 'ok'];
            return ['status' => 'error', 'msg' => 'Login fallido. Revisa usuario y contraseña.'];
        }

        // 3. Pedir envío del email con código 2FA
        $this->addLog('info', 'Solicitando envío de código 2FA...');
        $r2 = $this->request('POST', self::LOGIN_URL . '?operacion=buscaEmails2fa', [
            'user'                 => $usuario,
            'pass'                 => $password,
            'g-recaptcha-response' => '',
        ]);

        if ($r2 === null) {
            return ['status' => 'error', 'msg' => 'No se pudo contactar con recevet.es al pedir el código.'];
        }

        // Log de diagnóstico: mostrar la respuesta del servidor
        $r2trim = trim($r2);
        $this->addLog('info', 'Respuesta buscaEmails2fa: ' . substr($r2trim, 0, 200));

        // Si la respuesta parece HTML completo, el servidor rechazó la petición (reCAPTCHA)
        if (strlen($r2trim) > 500 || str_starts_with($r2trim, '<!')) {
            return [
                'status' => 'error',
                'msg'    => 'recevet.es bloqueó la petición (reCAPTCHA requerido). ' .
                            'Inicia sesión directamente en recevet.es desde tu navegador y vuelve a intentarlo.',
            ];
        }

        $this->addLog('success', 'Código enviado al email. Introdúcelo para continuar.');
        return ['status' => 'needs_2fa', 'controlForm' => $controlForm];
    }

    // ── Paso 2: Verificar código 2FA y completar login ─────────────

    /**
     * Valida el código de 6 dígitos recibido por email y completa el login.
     *
     * Retorna:
     *   ['status' => 'ok',    'cookie' => '...'] → login correcto, cookie para guardar en BD
     *   ['status' => 'error', 'msg'    => '...'] → código incorrecto o expirado
     */
    public function verificarCodigo2fa(
        string $usuario,
        string $password,
        string $codigo,
        string $controlForm,
        bool   $seguro = true
    ): array {
        $this->addLog('info', 'Verificando código 2FA...');

        // 1. Validar código con recevet.es
        $r = $this->request('POST', self::LOGIN_URL . '?operacion=validadorCodigo2fa', [
            'user'  => $usuario,
            '2fa'   => $codigo,
            'seguro' => $seguro ? '1' : '0',
        ]);

        if ($r === null) {
            return ['status' => 'error', 'msg' => 'Error de conexión al validar el código.'];
        }

        // Respuesta vacía ({}) = código correcto
        $decoded = json_decode(trim($r), true);
        if (!empty($decoded)) {
            $msg = is_array($decoded) ? (string)reset($decoded) : 'Código incorrecto o expirado.';
            $this->addLog('error', 'Código inválido: ' . $msg);
            return ['status' => 'error', 'msg' => $msg];
        }

        $this->addLog('info', 'Código correcto. Completando login...');

        // 2. POST de login final
        if (!$this->postLogin($controlForm, $usuario, $password, $codigo)) {
            return ['status' => 'error', 'msg' => 'El código fue correcto pero el login final falló.'];
        }

        // 3. Extraer cookie de sesión del jar para guardar en BD
        $cookie = $this->extractSessionCookieString();
        $this->addLog('success', 'Login completado. Sesión guardada ✓');

        // Ya no necesitamos el archivo temporal
        $this->persistCookie = false;

        return ['status' => 'ok', 'cookie' => $cookie];
    }

    // ── Verificar sesión para sincronización ──────────────────────

    /**
     * Verifica que la cookie guardada en BD sigue siendo válida.
     * Usada antes de cada sincronización.
     */
    public function login(string $usuario = '', string $password = ''): bool
    {
        $this->addLog('info', 'Verificando sesión en Recevet...');

        $html = $this->request('GET', self::LOGIN_URL . '?operacion=principal');

        if ($html !== null && $this->esRespuestaLogueado($html)) {
            $this->addLog('success', 'Sesión activa ✓');
            $this->loggedIn = true;
            return true;
        }

        $this->addLog('error', 'La sesión ha caducado. Ve a Recevet → Conectar para renovarla.');
        return false;
    }

    // ── Sincronización ────────────────────────────────────────────

    public function sincronizar(string $explotacion, bool $dryRun = false): bool
    {
        if (!$this->loggedIn) {
            $this->addLog('error', 'No hay sesión activa.');
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

        // Extraer operaciones del JS para diagnóstico
        $this->logOperacionesJs($html);

        // Los datos se cargan vía AJAX con operacion=dame_lineasTratamientos
        // Extraemos los campos necesarios del form para construir la petición
        $formFields = $this->extraerCamposFormDesdeHtml($html);

        $lineas = $this->obtenerLineasPendientesAjax($formFields);
        if (empty($lineas)) {
            $this->addLog('success', 'No hay líneas pendientes. Todo al día.');
            return true;
        }

        $this->addLog('info', count($lineas) . ' línea(s) pendientes.');

        $ok = $err = 0;
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

    // ── Helpers login ─────────────────────────────────────────────

    private function postLogin(string $controlForm, string $usuario, string $password, string $codigo): bool
    {
        $postData = [
            'controlForm'          => $controlForm,
            '2fa'                  => $codigo,
            'pc'                   => self::PC,
            'usuario'              => $usuario,
            'passUsuario'          => $password,
            'g-recaptcha-response' => '',
        ];

        $respuesta = $this->request('POST', self::LOGIN_URL . '?operacion=principal', $postData);

        if ($respuesta !== null && $this->esRespuestaLogueado($respuesta)) {
            $this->loggedIn = true;
            return true;
        }

        if ($respuesta !== null) {
            $this->addLog('info', 'Respuesta login: ' . $this->fragmento($respuesta));
        }
        return false;
    }

    private function extraerControlForm(string $html): string
    {
        $dom = $this->parseDom($html);
        if (!$dom) return '';
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//input[@name="controlForm"]');
        return $nodes->length > 0 ? (string)$nodes->item(0)->getAttribute('value') : '';
    }

    /**
     * Lee el archivo de cookies Netscape y devuelve el string
     * tipo "PHPSESSID=abc; otraCookie=val" para guardar en la BD.
     */
    public function extractSessionCookieString(): string
    {
        if (!file_exists($this->cookieFile)) return '';
        $lines  = file($this->cookieFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $pairs  = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '#')) continue;
            $parts = explode("\t", $line);
            if (count($parts) < 7) continue;
            $name  = trim($parts[5]);
            $value = trim($parts[6]);
            if ($name) $pairs[] = $name . '=' . $value;
        }
        return implode('; ', $pairs);
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
        return trim((string)preg_replace('/\s+/', ' ', substr(strip_tags($html), 0, 400)));
    }

    // ── Sincronización internals ──────────────────────────────────

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

    /**
     * Extrae todos los campos del form principal del HTML (myForm).
     */
    private function extraerCamposFormDesdeHtml(string $html): array
    {
        $dom = $this->parseDom($html);
        $fields = [];

        if ($dom) {
            $xpath = new \DOMXPath($dom);
            $forms = $xpath->query('//form[@id="myForm"] | //form[1]');
            if ($forms->length > 0) {
                $fields = $this->extraerCamposForm($forms->item(0));
            }

            // upSeleccionada puede tener solo id (no name) — buscarlo explícitamente
            if (!isset($fields['upSeleccionada'])) {
                $up = $xpath->query('//*[@id="upSeleccionada"]');
                if ($up->length > 0) {
                    $fields['upSeleccionada'] = $up->item(0)->getAttribute('value');
                }
            }
        }

        // filtros: extraer del JS (string hardcodeado en la inicialización de DataTables)
        if (preg_match("/d\\.filtros\\s*=\\s*'([^']+)'/", $html, $m)) {
            $fields['filtros'] = $m[1];
        }

        return $fields;
    }

    /**
     * Llama al endpoint AJAX dame_lineasTratamientos para obtener los
     * tratamientos pendientes (los que tienen fechaInicio vacía).
     *
     * recevet.es devuelve JSON con estructura DataTables:
     *   { "aaData": [ [...], [...] ], "iTotalRecords": N, ... }
     * Cada fila es un array de celdas HTML.
     */
    private function obtenerLineasPendientesAjax(array $formFields): array
    {
        $this->addLog('info', 'Llamando a dame_lineasTratamientos vía AJAX...');

        // Parámetros que envía DataTables + el formulario
        // filtros y upSeleccionada ya vienen en $formFields extraídos del JS/HTML
        $postData = array_merge($formFields, [
            'draw'                     => '1',
            'start'                    => '0',
            'length'                   => '1000',  // todas las líneas
            'mostrarLineasCompletadas' => '1',      // pedir TODAS; filtramos client-side
            'iDisplayStart'            => '0',
            'iDisplayLength'           => '1000',
            'sEcho'                    => '1',
        ]);

        $this->addLog('info', 'Params AJAX: filtros=' . (isset($postData['filtros']) ? 'sí' : 'no')
            . ' upSeleccionada=' . ($postData['upSeleccionada'] ?? '(vacío)'));

        $respuesta = $this->request('POST', self::LOGIN_URL . '?operacion=dame_lineasTratamientos', $postData);

        if ($respuesta === null) {
            $this->addLog('error', 'No se pudo obtener las líneas de tratamiento.');
            return [];
        }

        $this->addLog('info', 'Respuesta AJAX (' . strlen($respuesta) . ' bytes): ' . substr($respuesta, 0, 200));

        // Intentar parsear como JSON (DataTables)
        $json = json_decode($respuesta, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->parsearLineasDesdeJson($json);
        }

        // Si no es JSON, puede ser HTML directo
        return $this->parsearLineasPendientes($respuesta);
    }

    /**
     * Parsea la respuesta JSON de DataTables.
     * Cada elemento de aaData es un array de celdas HTML.
     * Buscamos las filas donde la celda de fecha_inicio esté vacía.
     */
    private function parsearLineasDesdeJson(array $json): array
    {
        $rows = $json['aaData'] ?? $json['data'] ?? [];
        $this->addLog('info', 'Filas recibidas del AJAX: ' . count($rows));

        if (empty($rows)) return [];

        // Log de la primera fila COMPLETA para ver todos los campos disponibles
        if (!empty($rows[0])) {
            $this->addLog('info', 'Campos de fila[0] (completo):');
            foreach ($rows[0] as $k => $v) {
                $this->addLog('info', '  ' . $k . ' = "' . substr(strip_tags((string)$v), 0, 80) . '"');
            }
        }

        // Contar cuántas filas tienen idRecetaLineaTratamiento vacío
        $pendientes = array_filter($rows, fn($r) => isset($r['idRecetaLineaTratamiento']) && $r['idRecetaLineaTratamiento'] === '');
        $this->addLog('info', 'Filas con idRecetaLineaTratamiento vacío (pendientes): ' . count($pendientes));

        $lineas = [];
        foreach ($rows as $row) {
            $linea = $this->extraerLineaDeFilaJson($row);
            if ($linea !== null) {
                $lineas[] = $linea;
            }
        }

        $this->addLog('info', 'Líneas pendientes encontradas: ' . count($lineas));
        return $lineas;
    }

    /**
     * Extrae los datos de una fila JSON (objeto con claves nombradas).
     * Solo procesa filas con idRecetaLineaTratamiento vacío (pendientes).
     */
    private function extraerLineaDeFilaJson(array $fila): ?array
    {
        // Solo líneas pendientes (sin tratamiento registrado todavía)
        if (!array_key_exists('idRecetaLineaTratamiento', $fila)) return null;
        if ($fila['idRecetaLineaTratamiento'] !== '') return null;

        // IDs necesarios para el POST
        $idReceta      = (string)($fila['idReceta']      ?? '');
        $idRecetaLinea = (string)($fila['idRecetaLinea'] ?? '');
        if (!$idReceta) return null; // idRecetaLinea puede ser vacío en algunas granjas

        // Fecha de dispensación — buscar en todos los campos
        $fechaDispensacion = null;
        foreach ($fila as $k => $v) {
            $kl = strtolower($k);
            if ((str_contains($kl, 'dispens') || str_contains($kl, 'fecha')) && $v !== '') {
                $fecha = $this->extraerFechaDeTexto(strip_tags((string)$v));
                if ($fecha) { $fechaDispensacion = $fecha; break; }
            }
        }

        // Días de tratamiento
        $diasTratamiento = 1;
        foreach ($fila as $k => $v) {
            $kl = strtolower($k);
            if (str_contains($kl, 'dias') || str_contains($kl, 'duraci') || str_contains($kl, 'tratamiento')) {
                $n = (int)preg_replace('/\D/', '', (string)$v);
                if ($n > 0) { $diasTratamiento = $n; break; }
            }
        }

        // Receta y medicamento
        $receta      = strip_tags((string)($fila['numReceta'] ?? $fila['receta'] ?? $idReceta));
        $medicamento  = strip_tags((string)($fila['medicamento'] ?? $fila['nombreMedicamento'] ?? '—'));

        return [
            'idReceta'           => $idReceta,
            'idRecetaLinea'      => $idRecetaLinea,
            'receta'             => trim($receta) ?: $idReceta,
            'medicamento'        => trim($medicamento) ?: '—',
            'fecha_dispensacion' => $fechaDispensacion ?? '',
            'dias_tratamiento'   => $diasTratamiento,
            'raw'                => $fila,  // guardamos fila completa para el POST
        ];
    }

    private function extraerFechaDeTexto(string $texto): ?string
    {
        if (preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $texto, $m)) {
            return $m[1][0];
        }
        return null;
    }

    private function extraerTextoColumna(array $celdas, int $idx): string
    {
        if (!isset($celdas[$idx])) return '—';
        return trim(preg_replace('/\s+/', ' ', strip_tags((string)$celdas[$idx]))) ?: '—';
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
            if ($linea !== null) $lineas[] = $linea;
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
            while ($parent && $parent->nodeName !== 'form') $parent = $parent->parentNode;
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
        if (preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $texto, $matches)) return $matches[1][0];
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
        return $celdas->length > 0 ? trim((string)$celdas->item(0)->textContent) : '—';
    }

    private function extraerMedicamento(\DOMElement $fila, \DOMDocument $dom): string
    {
        $xpath  = new \DOMXPath($dom);
        $celdas = $xpath->query('.//td', $fila);
        return $celdas->length > 1 ? trim((string)$celdas->item(1)->textContent) : '—';
    }

    private function completarLinea(array $linea, string $fechaInicio, ?string $fechaFin): bool
    {
        // Formato AJAX (idReceta/idRecetaLinea) → navegar a página de edición
        if (!isset($linea['form_method'])) {
            return $this->completarLineaConIDs($linea, $fechaInicio, $fechaFin);
        }

        // Formato HTML (form_fields/form_method ya extraídos)
        $fields                             = $linea['form_fields'];
        $fields[$linea['fecha_input_name']] = $fechaInicio;
        foreach (array_keys($fields) as $key) {
            $keyLow = strtolower($key);
            if ((str_contains($keyLow, 'fecha_fin') || str_contains($keyLow, 'fechafin')) && $fechaFin) {
                $fields[$key] = $fechaFin;
            }
        }
        return $this->request($linea['form_method'], $linea['form_action'], $fields) !== null;
    }

    /**
     * Llama a dame_fila_lineasTratamientos para obtener el HTML completo de la fila
     * (medicamento, dispensacion, fechas, acciones con el form del botón "Aceptar").
     * Devuelve array de 7 celdas HTML, o null si falla.
     */
    private function obtenerFilaCompleta(string $idReceta, string $idRecetaLinea, string $idRecetaLineaTratamiento = ''): ?array
    {
        $resp = $this->request('POST', self::BASE_URL . '/index.php?operacion=dame_fila_lineasTratamientos', [
            'idLineaTratamiento' => $idRecetaLineaTratamiento,
            'idReceta'           => $idReceta,
            'idRecetaLinea'      => $idRecetaLinea,
        ], true);

        if ($resp === null) return null;

        $json = json_decode($resp, true);
        if (!is_array($json)) return null;

        // La respuesta puede ser un array de celdas directamente o envuelto en 'data'
        $celdas = isset($json['data']) ? $json['data'] : $json;
        if (!is_array($celdas) || count($celdas) < 6) return null;

        return $celdas;
    }

    private function completarLineaConIDs(array $linea, string $fechaInicio, ?string $fechaFin): bool
    {
        $idReceta                 = $linea['idReceta']                 ?? '';
        $idRecetaLinea            = $linea['idRecetaLinea']            ?? '';
        $idRecetaLineaTratamiento = $linea['idRecetaLineaTratamiento'] ?? '';

        $celdas = $this->obtenerFilaCompleta($idReceta, $idRecetaLinea, $idRecetaLineaTratamiento);
        if ($celdas === null) {
            $this->addLog('error', "  dame_fila_lineasTratamientos falló (idRecetaLinea={$idRecetaLinea})");
            return false;
        }

        // ── Fecha dispensación desde celda[0] ──────────────────────────────
        // Formato: "(Fecha Dispensacion:</br>DD/MM/YYYY)"
        if (preg_match('/Fecha Dispensacion:(?:<[^>]+>|\s)+(\d{2}\/\d{2}\/\d{4})/i', (string)($celdas[0] ?? ''), $m)) {
            $fechaDispensacion = $m[1];
            $fechaInicio       = $this->calcularFechaInicio($fechaDispensacion);
            $this->addLog('info', "  Dispensación: {$fechaDispensacion} → Inicio: {$fechaInicio}");
        }

        // ── Token e idReceta (encoded) desde celda[6] ──────────────────────
        $cel6 = (string)($celdas[6] ?? '');
        if (!preg_match("/data-ra-idLineaTratamiento='([^']+)'/", $cel6, $mTok)) {
            $this->addLog('error', '  No se encontró data-ra-idLineaTratamiento en celda[6]. HTML: ' . substr($cel6, 0, 300));
            return false;
        }
        $token = $mTok[1];
        preg_match("/data-ra-receta='([^']+)'/", $cel6, $mRec);
        $recetaEncoded = $mRec[1] ?? '';

        // ── Campos de fecha desde celda[3] ────────────────────────────────
        $cel3 = (string)($celdas[3] ?? '');
        $dom3 = $this->parseDom('<div>' . $cel3 . '</div>');
        $fechaInicioField = null;
        $fechaFinField    = null;
        $diasField        = null;
        $diasValue        = 0;

        if ($dom3) {
            $xpath3 = new \DOMXPath($dom3);
            foreach ($xpath3->query('//input') as $inp) {
                $name = $inp->getAttribute('name');
                $nl   = strtolower($name);
                if (str_contains($nl, 'fechainiciotratamiento')) {
                    $fechaInicioField = $name;
                } elseif (str_contains($nl, 'fechafintratamiento')) {
                    $fechaFinField = $name;
                } elseif (str_contains($nl, 'diastratamiento') || str_contains($nl, 'dias_tratamiento')) {
                    $diasField  = $name;
                    $diasValue  = (int)$inp->getAttribute('value');
                }
            }
        }

        if (!$fechaInicioField) {
            // Fallback: nombre dinámico basado en el token
            $fechaInicioField = 'fechaInicioTratamiento' . $token;
            $this->addLog('info', '  Usando nombre fallback para fechaInicio: ' . $fechaInicioField);
        }
        if (!$fechaFinField) {
            $fechaFinField = 'fechaFinTratamiento' . $token;
        }

        // Recalcular fechaFin si tenemos días del formulario
        if ($diasValue > 1) {
            $fechaFin = $this->calcularFechaFin($fechaInicio, $diasValue);
        }
        if ($fechaFin) {
            $this->addLog('info', "  Fin: {$fechaFin}" . ($diasValue > 1 ? " ({$diasValue} días)" : ''));
        }

        // ── POST a actualizar_lineasTratamientos ──────────────────────────
        // Estructura igual que jQuery $.param({ lineasTratamiento: [obtenerLineaTratamiento()] })
        $campos  = ['fechaInicioTratamiento'];
        $valores = ['fechaInicioTratamiento' => $fechaInicio];
        if ($fechaFin) {
            $campos[]                        = 'fechaFinTratamiento';
            $valores['fechaFinTratamiento']  = $fechaFin;
        }

        $postData = [
            'lineasTratamiento' => [
                [
                    'identificador'     => $token,
                    'campos'            => $campos,
                    'valores'           => $valores,
                    'algunCampoRelleno' => '1',
                ],
            ],
        ];

        $respuesta = $this->request('POST', self::BASE_URL . '/index.php?operacion=actualizar_lineasTratamientos', $postData);
        if ($respuesta === null) {
            $this->addLog('error', '  Error HTTP al enviar actualizar_lineasTratamientos');
            return false;
        }

        $this->addLog('info', '  Respuesta: ' . substr(trim($respuesta), 0, 300));

        // La respuesta es JSON: {"TOKEN": {"campo": []}} donde [] vacío = sin errores
        $json = json_decode($respuesta, true);
        if (!is_array($json)) {
            $this->addLog('error', '  Respuesta no es JSON: ' . substr($respuesta, 0, 200));
            return false;
        }

        // Verificar errores: cada campo debe tener array vacío
        foreach ($json as $idLinea => $campos) {
            if (!is_array($campos)) continue;
            foreach ($campos as $campo => $errores) {
                if (!empty($errores)) {
                    $this->addLog('error', "  Error en campo {$campo}: " . implode(', ', (array)$errores));
                    return false;
                }
            }
        }

        return true;
    }

    private function logOperacionesJs(string $html): void
    {
        // Extraer todas las operaciones referenciadas en el JS de la página
        preg_match_all('/operacion=([a-zA-Z0-9_]+)/', $html, $m);
        $ops = array_unique($m[1] ?? []);
        if ($ops) {
            $this->addLog('info', 'Operaciones en JS/HTML: ' . implode(', ', $ops));
        }

        // Buscar en scripts inline
        preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $html, $scripts);
        foreach (($scripts[1] ?? []) as $script) {
            if (strpos($script, 'actualizarLineaTratamiento') !== false) {
                $pos   = strpos($script, 'actualizarLineaTratamiento');
                $start = max(0, $pos - 100);
                $this->addLog('info', 'JS inline actualizarLineaTratamiento: ' . substr($script, $start, 1200));
            }
            if (strpos($script, 'DataTable') !== false || strpos($script, 'dame_lineas') !== false) {
                $scriptTrim = trim($script);
                $offset = 0; $part = 1;
                while ($offset < strlen($scriptTrim)) {
                    $this->addLog('info', "Script DataTables (parte {$part}): " . substr($scriptTrim, $offset, 2000));
                    $offset += 2000; $part++;
                }
            }
        }

        // Log todos los scripts externos y buscar función
        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $extScripts);
        $srcList = $extScripts[1] ?? [];
        $this->addLog('info', 'Scripts externos (' . count($srcList) . '): ' . implode(' | ', $srcList));
        foreach ($srcList as $src) {
            if (strpos($src, 'libroTratamiento') === false) continue; // solo el relevante
            $url = (strpos($src, 'http') === 0) ? $src : self::BASE_URL . '/' . ltrim($src, '/');
            $jsContent = $this->request('GET', $url, []);
            if ($jsContent === null) continue;
            // Buscar función actualizarLineas
            foreach (['actualizarLineas', 'obtenerLineaTratamiento'] as $fn) {
                if (preg_match('/function\s+' . $fn . '[\s\(]/', $jsContent, $mm, PREG_OFFSET_CAPTURE)) {
                    $pos = $mm[0][1];
                    $this->addLog('info', "DEF {$fn} en [{$src}]: " . substr($jsContent, $pos, 1500));
                } elseif (($pos = strpos($jsContent, $fn)) !== false) {
                    $start = max(0, $pos - 20);
                    $this->addLog('info', "USE {$fn} en [{$src}]: " . substr($jsContent, $start, 800));
                }
            }
        }
    }

    // ── Fechas ────────────────────────────────────────────────────

    private function calcularFechaInicio(string $fechaDispensacion): string
    {
        $ts    = $this->esDateToTimestamp($fechaDispensacion);
        $inicio = $ts !== null ? $ts + 86400 : strtotime('+1 day');
        // No se pueden registrar fechas futuras: cap a hoy
        $today = mktime(0, 0, 0);
        return date('d/m/Y', min($inicio, $today));
    }

    private function calcularFechaFin(string $fechaInicio, int $diasTratamiento): ?string
    {
        if ($diasTratamiento <= 1) return null;
        $ts = $this->esDateToTimestamp($fechaInicio);
        return $ts !== null ? date('d/m/Y', $ts + ($diasTratamiento - 1) * 86400) : null;
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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

        return $this->toUtf8($response);
    }

    private function toUtf8(string $html): string
    {
        if (stripos($html, 'charset=ISO-8859-1') !== false) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
            $html = str_ireplace('charset=ISO-8859-1', 'charset=UTF-8', $html);
        }
        return $html;
    }

    private function writeCookieToJar(string $cookie): void
    {
        $lines    = ["# Netscape HTTP Cookie File\n"];
        $rawPairs = str_contains($cookie, '=') ? $cookie : 'PHPSESSID=' . $cookie;
        foreach (explode(';', $rawPairs) as $pair) {
            $pair  = trim($pair);
            $eqPos = strpos($pair, '=');
            if ($eqPos === false) continue;
            $name  = trim(substr($pair, 0, $eqPos));
            $value = trim(substr($pair, $eqPos + 1));
            if ($name === '') continue;
            $lines[] = ".recevet.es\tTRUE\t/\tFALSE\t0\t{$name}\t{$value}\n";
        }
        file_put_contents($this->cookieFile, implode('', $lines));
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
            if (!$name || in_array($type, ['submit', 'button', 'image'])) continue;
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
                if ($opts->length > 0) $fields[$name] = (string)$opts->item(0)->getAttribute('value');
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
        $l          = $this->logs;
        $this->logs = [];
        return $l;
    }
}
