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
        $uid  = Session::get('usuario_id');

        // Comprobar extensiones necesarias
        $requisitos = $this->checkRequisitos();

        try {
            $usuario = (new Usuario())->findById($uid);
            $granjas = (new Granja())->allByUsuario($uid);
        } catch (\Throwable $e) {
            // Probablemente las columnas SQL aún no existen
            error_log('RecevtController::index error: ' . $e->getMessage());
            $this->view('recevet/index', [
                'pageTitle'      => 'Recevet',
                'usuario'        => [],
                'granjas'        => [],
                'granjasRecevet' => [],
                'credencialesOk' => false,
                'requisitos'     => $requisitos,
                'success'        => Session::getFlash('success'),
                'error'          => 'Error de base de datos: ' . $e->getMessage() . ' — ¿Has ejecutado las migraciones SQL?',
                'logs'           => [],
            ]);
            return;
        }

        $granjasRecevet = array_filter($granjas, fn($g) => !empty($g['recevet_explotacion']));
        $cookieOk       = !empty($usuario['recevet_session_cookie']);
        $credencialesOk = !empty($usuario['recevet_usuario']) && $cookieOk;

        $this->view('recevet/index', [
            'pageTitle'      => 'Recevet',
            'usuario'        => $usuario,
            'granjas'        => $granjas,
            'granjasRecevet' => array_values($granjasRecevet),
            'credencialesOk' => $credencialesOk,
            'cookieOk'       => $cookieOk,
            'requisitos'     => $requisitos,
            'success'        => Session::getFlash('success'),
            'error'          => Session::getFlash('error'),
            'logs'           => (function() {
                $logs = $_SESSION['_recevet_logs'] ?? [];
                unset($_SESSION['_recevet_logs']);
                return $logs;
            })(),
        ]);
    }

    public function sincronizar(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('recevet');
        }

        // Verificar extensiones
        $req = $this->checkRequisitos();
        if (!$req['curl']) {
            Session::flash('error', 'La extensión cURL no está disponible en este servidor. Contacta con el hosting.');
            $this->redirect('recevet');
        }

        $uid = Session::get('usuario_id');

        try {
            $usuario = (new Usuario())->findById($uid);
        } catch (\Throwable $e) {
            Session::flash('error', 'Error de base de datos: ' . $e->getMessage() . ' — Ejecuta las migraciones SQL primero.');
            $this->redirect('recevet');
        }

        if (empty($usuario['recevet_session_cookie'])) {
            Session::flash('error', 'Configura primero la cookie de sesión de Recevet en el perfil.');
            $this->redirect('recevet');
        }

        $granjaIds = $_POST['granjas'] ?? [];
        if (empty($granjaIds)) {
            Session::flash('error', 'Selecciona al menos una granja.');
            $this->redirect('recevet');
        }

        $dryRun        = ($this->postString('dry_run') === '1');
        $sessionCookie = $usuario['recevet_session_cookie'];

        $allLogs = [];

        try {
            $service  = new RecevtService($sessionCookie);
            $loggedIn = $service->login(
                $usuario['recevet_usuario'] ?? '',
                ''  // password ya no se necesita con cookie
            );
            $allLogs  = array_merge($allLogs, $service->getLogs());

            if (!$loggedIn) {
                $_SESSION['_recevet_logs'] = $allLogs;
                Session::flash('error', 'No se pudo iniciar sesión en Recevet. Revisa las credenciales en tu perfil.');
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
            error_log('RecevtController::sincronizar error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        $_SESSION['_recevet_logs'] = $allLogs;

        $errores = count(array_filter($allLogs, fn($l) => $l['type'] === 'error'));
        if ($errores > 0) {
            Session::flash('error', "Sincronización con {$errores} error(es). Revisa el log.");
        } else {
            Session::flash('success', $dryRun
                ? 'Simulación completada. Revisa el log.'
                : 'Sincronización completada correctamente.');
        }

        $this->redirect('recevet');
    }

    // ── Comprueba si las extensiones PHP necesarias están disponibles ──

    private function checkRequisitos(): array
    {
        return [
            'curl'    => function_exists('curl_init'),
            'openssl' => function_exists('openssl_encrypt'),
            'dom'     => class_exists('\DOMDocument'),
        ];
    }
}
