<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Nave;
use App\Models\Granja;
use App\Core\Session;

class NaveController extends BaseController
{
    private Nave  $model;
    private Granja $granjaModel;

    public function __construct()
    {
        $this->model       = new Nave();
        $this->granjaModel = new Granja();
    }

    public function index(): void
    {
        auth_required();
        $uid   = Session::get('usuario_id');
        $naves = $this->model->allByUsuario($uid);
        $this->view('naves/index', ['naves' => $naves, 'pageTitle' => 'Naves']);
    }

    public function create(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('naves/form', [
            'nave'      => null,
            'granjas'   => $this->granjaModel->selectOptions($uid),
            'pageTitle' => 'Nueva nave',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('naves/crear');
        }

        $nombre = $this->postString('nombre');
        if (strlen($nombre) < 1 || !$this->post('granja_id')) {
            Session::flash('error', 'Nombre y granja son obligatorios.');
            $this->redirect('naves/crear');
        }

        $this->model->create([
            'granja_id'        => (int)$this->post('granja_id'),
            'nombre'           => $nombre,
            'especie'          => $this->postString('especie'),
            'capacidad_maxima' => (int)$this->post('capacidad_maxima', 0),
            'ancho_m'          => $this->post('ancho_m') ?: null,
            'alto_m'           => $this->post('alto_m')  ?: null,
            'largo_m'          => $this->post('largo_m') ?: null,
            'descripcion'      => $this->postString('descripcion'),
        ]);

        Session::flash('success', 'Nave creada correctamente.');
        $this->redirect('naves');
    }

    public function edit(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $nave = $this->model->find((int)$id, $uid);
        if (!$nave) $this->redirect('naves');

        $this->view('naves/form', [
            'nave'      => $nave,
            'granjas'   => $this->granjaModel->selectOptions($uid),
            'pageTitle' => 'Editar nave',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("naves/{$id}/editar");
        }

        $this->model->update((int)$id, Session::get('usuario_id'), [
            'nombre'           => $this->postString('nombre'),
            'especie'          => $this->postString('especie'),
            'capacidad_maxima' => (int)$this->post('capacidad_maxima', 0),
            'ancho_m'          => $this->post('ancho_m') ?: null,
            'alto_m'           => $this->post('alto_m')  ?: null,
            'largo_m'          => $this->post('largo_m') ?: null,
            'descripcion'      => $this->postString('descripcion'),
        ]);

        Session::flash('success', 'Nave actualizada.');
        $this->redirect('naves');
    }

    public function delete(string $id): void
    {
        auth_required();
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Nave eliminada.');
        $this->redirect('naves');
    }
}
