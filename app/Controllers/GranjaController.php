<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Granja;
use App\Core\Session;

class GranjaController extends BaseController
{
    private Granja $model;

    public function __construct()
    {
        $this->model = new Granja();
    }

    public function index(): void
    {
        auth_required();
        $granjas = $this->model->allByUsuario(Session::get('usuario_id'));
        $this->view('granjas/index', ['granjas' => $granjas, 'pageTitle' => 'Granjas']);
    }

    public function create(): void
    {
        auth_required();
        $this->view('granjas/form', [
            'granja'    => null,
            'pageTitle' => 'Nueva granja',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('granjas/crear');
        }

        $nombre = $this->postString('nombre');
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect('granjas/crear');
        }

        $this->model->create([
            'usuario_id'      => Session::get('usuario_id'),
            'nombre'          => $nombre,
            'codigo_rega'     => $this->postString('codigo_rega'),
            'capacidad_max'   => $this->post('capacidad_max') ? (int)$this->post('capacidad_max') : null,
            'direccion'       => $this->postString('direccion'),
            'municipio'       => $this->postString('municipio'),
            'provincia'       => $this->postString('provincia'),
            'codigo_postal'   => $this->postString('codigo_postal'),
            'tipo_produccion' => $this->postString('tipo_produccion'),
            'latitud'         => $this->post('latitud')  ? (float)$this->post('latitud')  : null,
            'longitud'        => $this->post('longitud') ? (float)$this->post('longitud') : null,
        ]);

        Session::flash('success', 'Granja creada correctamente.');
        $this->redirect('granjas');
    }

    public function edit(string $id): void
    {
        auth_required();
        $granja = $this->model->find((int)$id, Session::get('usuario_id'));
        if (!$granja) $this->redirect('granjas');

        $this->view('granjas/form', [
            'granja'    => $granja,
            'pageTitle' => 'Editar granja',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("granjas/{$id}/editar");
        }

        $nombre = $this->postString('nombre');
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect("granjas/{$id}/editar");
        }

        $this->model->update((int)$id, Session::get('usuario_id'), [
            'nombre'          => $nombre,
            'codigo_rega'     => $this->postString('codigo_rega'),
            'capacidad_max'   => $this->post('capacidad_max') ? (int)$this->post('capacidad_max') : null,
            'direccion'       => $this->postString('direccion'),
            'municipio'       => $this->postString('municipio'),
            'provincia'       => $this->postString('provincia'),
            'codigo_postal'   => $this->postString('codigo_postal'),
            'tipo_produccion' => $this->postString('tipo_produccion'),
            'latitud'         => $this->post('latitud')  ? (float)$this->post('latitud')  : null,
            'longitud'        => $this->post('longitud') ? (float)$this->post('longitud') : null,
        ]);

        Session::flash('success', 'Granja actualizada correctamente.');
        $this->redirect('granjas');
    }

    public function delete(string $id): void
    {
        auth_required();
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Granja eliminada.');
        $this->redirect('granjas');
    }
}
