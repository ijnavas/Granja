<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;

/**
 * Páginas legales públicas: aviso legal, privacidad, política de cookies.
 * Accesibles sin login. Usan el layout que tenga el usuario:
 *  - main si está logueado
 *  - auth si no
 */
class LegalController extends BaseController
{
    private function layout(): string
    {
        return Session::has('usuario_id') ? 'main' : 'auth';
    }

    public function aviso(): void
    {
        $this->view('legal/aviso', [
            'pageTitle' => 'Aviso legal',
        ], $this->layout());
    }

    public function privacidad(): void
    {
        $this->view('legal/privacidad', [
            'pageTitle' => 'Política de privacidad',
        ], $this->layout());
    }

    public function cookies(): void
    {
        $this->view('legal/cookies', [
            'pageTitle' => 'Política de cookies',
        ], $this->layout());
    }
}
