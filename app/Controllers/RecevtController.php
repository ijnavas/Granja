<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Granja;
use App\Models\Usuario;
use App\Services\RecevtService;
use App\Core\Session;

class RecevtController extends BaseController
{
    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        $requisitos = $this->checkRequisitos();

        try {
            $usuario = (new Usuario())->findById($uid);
            $granjas = (new Granja())->allByUsuario($uid);
        } catch (\Throwable $e) {
            error_log('RecevtController::index error: ' . $e->getMessage());
            $this->view('recevet/index', [
                'pageTitle'      => 'Recevet',
                'usuario'        => [],
                'granjas'        => [],
                'granjasRecevet' => [],
                'sesionActiva'   => false,
                'requisitos'     => $requisitos,
                'success'        => Session::getFlash('success'),
                'error'          => 'Error de base de datos: ' . $e->getMessage(),
                'esperandoCodigo' => false,
                'logs'           => [],
            ]);
            return;
        }

        $granjasRecevet  = array_values(array_filter($granjas, fn($g) => !empty($g['recevet_explotacion'])));
        $sesionActiva    = !empty($usuario['recevet_session_cookie']);
        $esperandoCodigo = !empty($_SESSION['_recevet_2fa_pending']);

        $this->view('recevet/index', [
            'pageTitle'       => 'Recevet',
            'usuario'         => $usuario,
            'granjas'         => $granjas,
            'granjasRecevet'  => $granjasRecevet,
            'sesionActiva'    => $sesionActiva,
            'requisitos'      => $requisitos,
            'success'         => Session::getFlash('success'),
            'error'           => Session::getFlash('error'),
            'esperandoCodigo' => $esperandoCodigo,
            'logs'            => (function () {
                $logs = $_SESSION['_recevet_logs'] ?? [];
                unset($_SESSION['_recevet_logs']);
                return $logs;
            })(),
        ]);
    }

    // ── Paso 1: Iniciar sesión (envía email con código) ────────────

    public function iniciarSesion(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('recevet');
        }

        $uid      = Session::get('usuario_id');
        $usuario  = $this->postString('recevet_usuario');
        $password = $this->postString('recevet_password');

        if (!$usuario || !$password) {
            Session::flash('error', 'Introduce usuario y contraseña de Recevet.');
            $this->redirect('recevet');
        }

        $service = new RecevtService($uid);
        $result  = $service->iniciarLogin($usuario, $password);
        $logs    = $service->getLogs();

        if ($result['status'] === 'ok') {
            // Login directo sin 2FA (dispositivo de confianza)
            $cookie = $service->extractSessionCookieString();
            (new Usuario())->updateRecevet($uid, $usuario, null, $cookie);
            unset($_SESSION['_recevet_2fa_pending']);
            $_SESSION['_recevet_logs'] = $logs;
            Session::flash('success', 'Sesión iniciada correctamente en Recevet ✓');
            $this->redirect('recevet');
        }

        if ($result['status'] === 'needs_2fa') {
            // Guardar estado para el paso 2
            $_SESSION['_recevet_2fa_pending'] = [
                'usuario'     => $usuario,
                'password'    => RecevtService::encryptPassword($password),
                'controlForm' => $result['controlForm'] ?? '',
            ];
            $_SESSION['_recevet_logs'] = $logs;
            $this->redirect('recevet');
        }

        // Error
        $_SESSION['_recevet_logs'] = $logs;
        Session::flash('error', $result['msg'] ?? 'Error al conectar con Recevet.');
        $this->redirect('recevet');
    }

    // ── Paso 2: Verificar código 2FA ──────────────────────────────

    public function verificarCodigo(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('recevet');
        }

        $pending = $_SESSION['_recevet_2fa_pending'] ?? null;
        if (!$pending) {
            Session::flash('error', 'Sesión de verificación expirada. Vuelve a iniciar.');
            $this->redirect('recevet');
        }

        $uid     = Session::get('usuario_id');
        $codigo  = trim($this->postString('codigo_2fa'));
        $seguro  = ($this->postString('seguro') === '1');

        if (!preg_match('/^\d{6}$/', $codigo)) {
            Session::flash('error', 'El código debe tener exactamente 6 dígitos.');
            $this->redirect('recevet');
        }

        $usuario  = $pending['usuario'];
        $password = RecevtService::decryptPassword($pending['password']);

        $service = new RecevtService($uid);
        $result  = $service->verificarCodigo2fa($usuario, $password, $codigo, $pending['controlForm'], $seguro);
        $logs    = $service->getLogs();

        $_SESSION['_recevet_logs'] = $logs;

        if ($result['status'] === 'ok') {
            (new Usuario())->updateRecevet($uid, $usuario, null, $result['cookie']);
            unset($_SESSION['_recevet_2fa_pending']);
            Session::flash('success', 'Sesión de Recevet iniciada correctamente ✓');
        } else {
            Session::flash('error', $result['msg'] ?? 'Código incorrecto o expirado.');
        }

        $this->redirect('recevet');
    }

    // ── Cancelar proceso de login 2FA ────────────────────────────

    public function cancelarLogin(): void
    {
        auth_required();
        unset($_SESSION['_recevet_2fa_pending']);
        $this->redirect('recevet');
    }

    // ── Recibir cookie desde el bookmarklet ───────────────────────

    /**
     * El bookmarklet ejecutado en recevet.es envía la cookie de sesión
     * a este endpoint junto con un token firmado (HMAC) para verificar
     * que la petición viene del usuario correcto.
     *
     * No requiere sesión PHP activa — el usuario viene desde recevet.es.
     * GET /recevet/capturar-cookie?uid=X&token=Y&cookie=Z
     */
    public function capturarCookie(): void
    {
        $uid    = (int)($_GET['uid']    ?? 0);
        $token  = (string)($_GET['token']  ?? '');
        $cookie = (string)($_GET['cookie'] ?? '');

        if (!$uid || !$token || !$cookie) {
            $this->capturarCookieError('Parámetros incompletos.');
            return;
        }

        // Verificar HMAC (evita que terceros inyecten cookies ajenas)
        if (!hash_equals(self::bookmarkletToken($uid), $token)) {
            $this->capturarCookieError('Token de seguridad inválido.');
            return;
        }

        $usuario = (new Usuario())->findById($uid);
        if (!$usuario) {
            $this->capturarCookieError('Usuario no encontrado.');
            return;
        }

        (new Usuario())->updateRecevet(
            $uid,
            $usuario['recevet_usuario'] ?? '',
            null,
            $cookie
        );

        // Restaurar sesión PHP del usuario para que vea el flash
        Session::set('usuario_id', $uid);
        Session::flash('success', 'Sesión de Recevet conectada correctamente ✓');
        $this->redirect('recevet');
    }

    private function capturarCookieError(string $msg): void
    {
        http_response_code(400);
        echo '<html><body style="font-family:sans-serif;padding:2rem">';
        echo '<h2 style="color:#dc2626">Error al capturar sesión</h2>';
        echo '<p>' . htmlspecialchars($msg) . '</p>';
        echo '<p><a href="javascript:history.back()">Volver</a></p>';
        echo '</body></html>';
        exit;
    }

    /**
     * Genera el token HMAC para el bookmarklet del usuario.
     * Se firma con la clave derivada de la config para que sea
     * único por instalación y no adivinable.
     */
    public static function bookmarkletToken(int $uid): string
    {
        // Preferimos RECEVET_KEY del .env. Fallback legacy a las creds de BD
        // para no invalidar bookmarklets ya distribuidos antes de la migración.
        $secret = \App\Core\Env::get('RECEVET_KEY', '');
        if ($secret === null || $secret === '') {
            $cfg    = require ROOT_PATH . '/config.php';
            $secret = ($cfg['db']['host'] ?? '') . ($cfg['db']['user'] ?? '') . ($cfg['db']['pass'] ?? '');
        }
        return hash_hmac('sha256', 'recevet_bookmarklet_' . $uid, $secret);
    }

    // ── Cerrar sesión Recevet ─────────────────────────────────────

    public function cerrarSesion(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('recevet');
        }

        $uid = Session::get('usuario_id');
        $u   = (new Usuario())->findById($uid);
        (new Usuario())->updateRecevet($uid, $u['recevet_usuario'] ?? '', null, '');
        unset($_SESSION['_recevet_2fa_pending']);

        Session::flash('success', 'Sesión de Recevet cerrada.');
        $this->redirect('recevet');
    }

    // ── Sincronizar ───────────────────────────────────────────────

    public function sincronizar(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('recevet');
        }

        $req = $this->checkRequisitos();
        if (!$req['curl']) {
            Session::flash('error', 'La extensión cURL no está disponible.');
            $this->redirect('recevet');
        }

        $uid = Session::get('usuario_id');

        try {
            $usuario = (new Usuario())->findById($uid);
        } catch (\Throwable $e) {
            Session::flash('error', 'Error de base de datos: ' . $e->getMessage());
            $this->redirect('recevet');
        }

        if (empty($usuario['recevet_session_cookie'])) {
            Session::flash('error', 'Inicia sesión en Recevet primero.');
            $this->redirect('recevet');
        }

        $granjaIds = $_POST['granjas'] ?? [];
        if (empty($granjaIds)) {
            Session::flash('error', 'Selecciona al menos una granja.');
            $this->redirect('recevet');
        }

        $dryRun  = ($this->postString('dry_run') === '1');
        $allLogs = [];

        try {
            $service  = new RecevtService(0, $usuario['recevet_session_cookie']);
            $loggedIn = $service->login();
            $allLogs  = array_merge($allLogs, $service->getLogs());

            if (!$loggedIn) {
                // Sesión caducada: limpiar cookie para que el usuario vuelva a loguear
                (new Usuario())->updateRecevet($uid, $usuario['recevet_usuario'] ?? '', null, '');
                $_SESSION['_recevet_logs'] = $allLogs;
                Session::flash('error', 'La sesión de Recevet ha caducado. Vuelve a iniciar sesión.');
                $this->redirect('recevet');
            }

            $todasGranjas = (new Granja())->allByUsuario($uid);

            foreach ($granjaIds as $granjaId) {
                $granja = null;
                foreach ($todasGranjas as $g) {
                    if ((int)$g['id'] === (int)$granjaId) { $granja = $g; break; }
                }
                if (!$granja || empty($granja['recevet_explotacion'])) continue;

                $allLogs[] = ['type' => 'info', 'msg' => "── Granja: {$granja['nombre']} ({$granja['recevet_explotacion']}) ──", 'ts' => date('H:i:s')];

                $service->sincronizar($granja['recevet_explotacion'], $dryRun);
                $allLogs = array_merge($allLogs, $service->getLogs());
            }
        } catch (\Throwable $e) {
            $allLogs[] = ['type' => 'error', 'msg' => 'Excepción: ' . $e->getMessage(), 'ts' => date('H:i:s')];
            error_log('RecevtController::sincronizar: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        $_SESSION['_recevet_logs'] = $allLogs;

        $errores = count(array_filter($allLogs, fn($l) => $l['type'] === 'error'));
        Session::flash(
            $errores > 0 ? 'error' : 'success',
            $errores > 0
                ? "Sincronización con {$errores} error(es). Revisa el log."
                : ($dryRun ? 'Simulación completada.' : 'Sincronización completada correctamente.')
        );

        $this->redirect('recevet');
    }

    private function checkRequisitos(): array
    {
        return [
            'curl'    => function_exists('curl_init'),
            'openssl' => function_exists('openssl_encrypt'),
            'dom'     => class_exists('\DOMDocument'),
        ];
    }
}
