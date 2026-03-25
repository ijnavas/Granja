<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Silo;
use App\Models\Granja;
use App\Models\Nave;
use App\Core\Session;

class SiloController extends BaseController
{
    private Silo   $model;
    private Granja $granjaModel;
    private Nave   $naveModel;

    public function __construct()
    {
        $this->model       = new Silo();
        $this->granjaModel = new Granja();
        $this->naveModel   = new Nave();
    }

    public function index(): void
    {
        auth_required();
        $silos = $this->model->allByUsuario(Session::get('usuario_id'));
        $this->view('silos/index', ['silos' => $silos, 'pageTitle' => 'Silos']);
    }

    public function create(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('silos/form', [
            'silo'         => null,
            'navesAsig'    => [],
            'granjas'      => $this->granjaModel->selectOptions($uid),
            'naves'        => $this->naveModel->selectOptions($uid),
            'pageTitle'    => 'Nuevo silo',
            'error'        => Session::getFlash('error'),
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('silos/crear');
        }

        if (!$this->post('granja_id') || !$this->postString('nombre')) {
            Session::flash('error', 'Nombre y granja son obligatorios.');
            $this->redirect('silos/crear');
        }

        $naveIds = $_POST['nave_ids'] ?? [];

        $this->model->create([
            'granja_id'       => (int)$this->post('granja_id'),
            'nombre'          => $this->postString('nombre'),
            'capacidad_kg'    => (float)$this->post('capacidad_kg', 0),
            'stock_actual_kg' => (float)$this->post('stock_actual_kg', 0),
            'stock_minimo_kg' => (float)$this->post('stock_minimo_kg', 0),
            'descripcion'     => $this->postString('descripcion'),
        ], $naveIds);

        Session::flash('success', 'Silo creado correctamente.');
        $this->redirect('silos');
    }

    public function edit(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $silo = $this->model->find((int)$id, $uid);
        if (!$silo) $this->redirect('silos');

        $this->view('silos/form', [
            'silo'      => $silo,
            'navesAsig' => $this->model->navesAsignadas((int)$id),
            'granjas'   => $this->granjaModel->selectOptions($uid),
            'naves'     => $this->naveModel->selectOptions($uid),
            'pageTitle' => 'Editar silo',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("silos/{$id}/editar");
        }

        $naveIds = $_POST['nave_ids'] ?? [];

        $this->model->update((int)$id, Session::get('usuario_id'), [
            'nombre'          => $this->postString('nombre'),
            'capacidad_kg'    => (float)$this->post('capacidad_kg', 0),
            'stock_actual_kg' => (float)$this->post('stock_actual_kg', 0),
            'stock_minimo_kg' => (float)$this->post('stock_minimo_kg', 0),
            'descripcion'     => $this->postString('descripcion'),
        ], $naveIds);

        Session::flash('success', 'Silo actualizado.');
        $this->redirect('silos');
    }

    public function delete(string $id): void
    {
        auth_required();
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Silo eliminado.');
        $this->redirect('silos');
    }
}
