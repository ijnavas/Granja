<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Lote;
use App\Models\Nave;
use App\Core\Session;

class LoteController extends BaseController
{
    private Lote $model;
    private Nave $naveModel;

    public function __construct()
    {
        $this->model     = new Lote();
        $this->naveModel = new Nave();
    }

    public function index(): void
    {
        auth_required();
        $lotes = $this->model->allByUsuario(Session::get('usuario_id'));
        $this->view('lotes/index', ['lotes' => $lotes, 'pageTitle' => 'Lotes']);
    }

    public function create(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('lotes/form', [
            'lote'       => null,
            'naves'      => $this->naveModel->selectOptions($uid),
            'tipos'      => $this->model->tiposAnimal(),
            'pageTitle'  => 'Nuevo lote',
            'codigoAuto' => '',
            'error'      => Session::getFlash('error'),
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('lotes/crear');
        }

        $fechaNac = $this->postString('fecha_nacimiento');
        if (!$fechaNac) {
            Session::flash('error', 'La fecha de nacimiento es obligatoria.');
            $this->redirect('lotes/crear');
        }

        $codigo = Lote::generarCodigo($fechaNac);

        // Si ya existe ese código, añadir sufijo
        if ($this->model->codigoExisteSimple($codigo)) {
            $sufijo = 2;
            while ($this->model->codigoExisteSimple($codigo . "-{$sufijo}")) {
                $sufijo++;
            }
            $codigo .= "-{$sufijo}";
        }

        $naveId = $this->post('nave_id') ?: null;

        $this->model->create([
            'nave_id'         => $naveId ? (int)$naveId : null,
            'tipo_animal_id'  => (int)$this->post('tipo_animal_id'),
            'codigo'          => $codigo,
            'num_animales'    => (int)$this->post('num_animales', 0),
            'peso_entrada_kg' => (float)$this->post('peso_entrada_kg', 0),
            'fecha_entrada'   => $this->postString('fecha_entrada') ?: date('Y-m-d'),
            'observaciones'   => $this->postString('observaciones'),
        ]);

        Session::flash('success', "Lote <strong>{$codigo}</strong> creado correctamente.");
        $this->redirect('lotes');
    }

    public function edit(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $lote = $this->model->find((int)$id, $uid);
        if (!$lote) $this->redirect('lotes');

        $this->view('lotes/form', [
            'lote'       => $lote,
            'naves'      => $this->naveModel->selectOptions($uid),
            'tipos'      => $this->model->tiposAnimal(),
            'pageTitle'  => 'Editar lote ' . $lote['codigo'],
            'codigoAuto' => $lote['codigo'],
            'error'      => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("lotes/{$id}/editar");
        }

        $naveId = $this->post('nave_id') ?: null;

        $this->model->update((int)$id, Session::get('usuario_id'), [
            'nave_id'         => $naveId ? (int)$naveId : null,
            'tipo_animal_id'  => (int)$this->post('tipo_animal_id'),
            'num_animales'    => (int)$this->post('num_animales', 0),
            'peso_entrada_kg' => (float)$this->post('peso_entrada_kg', 0),
            'fecha_entrada'   => $this->postString('fecha_entrada'),
            'observaciones'   => $this->postString('observaciones'),
        ]);

        Session::flash('success', 'Lote actualizado.');
        $this->redirect('lotes');
    }

    public function ajustar(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('lotes');
        }

        $cantidad = abs((int)$this->post('cantidad', 0));
        $tipo     = $this->postString('tipo'); // añadir | reducir

        if ($cantidad > 0 && in_array($tipo, ['añadir', 'reducir'])) {
            $this->model->ajustarAnimales((int)$id, $cantidad, $tipo);
            $accion = $tipo === 'añadir' ? 'añadidos' : 'reducidos';
            Session::flash('success', "{$cantidad} animales {$accion} correctamente.");
        }

        $this->redirect('lotes');
    }

    public function delete(string $id): void
    {
        auth_required();
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Lote cerrado.');
        $this->redirect('lotes');
    }
}
