<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RazaPorcino;
use App\Core\Session;

class ConfigController extends BaseController
{
    private RazaPorcino $razaModel;

    public function __construct()
    {
        $this->razaModel = new RazaPorcino();
    }

    // ── Panel configuración ──────────────────────────────────────
    public function index(): void
    {
        auth_required();
        require_rol('admin');
        $razas = $this->razaModel->allAdmin();
        $this->view('config/index', [
            'razas'     => $razas,
            'pageTitle' => 'Configuración',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    // ── Crear raza ───────────────────────────────────────────────
    public function crearRaza(): void
    {
        auth_required();
        require_rol('admin');

        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion');
        }

        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect('configuracion');
        }

        $identificador = strtoupper(trim($this->postString('identificador')));

        $this->razaModel->create([
            'usuario_id'    => null,
            'nombre'        => $nombre,
            'porcentaje'    => $this->postString('porcentaje') ?: null,
            'identificador' => $identificador ?: null,
        ]);

        Session::flash('success', "Raza \"{$nombre}\" creada correctamente.");
        $this->redirect('configuracion');
    }

    // ── Editar raza ──────────────────────────────────────────────
    public function editarRaza(string $id): void
    {
        auth_required();
        require_rol('admin');

        $raza = $this->razaModel->find((int)$id);
        if (!$raza) $this->redirect('configuracion');

        $this->view('config/raza_form', [
            'raza'      => $raza,
            'pageTitle' => 'Editar raza',
            'error'     => Session::getFlash('error'),
        ]);
    }

    // ── Actualizar raza ──────────────────────────────────────────
    public function actualizarRaza(string $id): void
    {
        auth_required();
        require_rol('admin');

        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("configuracion/razas/{$id}/editar");
        }

        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect("configuracion/razas/{$id}/editar");
        }

        $identificador = strtoupper(trim($this->postString('identificador')));

        $this->razaModel->update((int)$id, [
            'nombre'        => $nombre,
            'porcentaje'    => $this->postString('porcentaje') ?: null,
            'identificador' => $identificador ?: null,
        ]);

        Session::flash('success', 'Raza actualizada correctamente.');
        $this->redirect('configuracion');
    }

    // ── Eliminar raza ────────────────────────────────────────────
    public function eliminarRaza(string $id): void
    {
        auth_required();
        require_rol('admin');
        $this->razaModel->delete((int)$id);
        Session::flash('success', 'Raza eliminada.');
        $this->redirect('configuracion');
    }
}