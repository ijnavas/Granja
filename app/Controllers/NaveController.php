<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Nave;
use App\Models\Granja;
use App\Core\Session;

class NaveController extends BaseController
{
    private Nave   $model;
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
        $totales = $this->model->totales($uid);
        $this->view('naves/index', [
            'naves'     => $naves,
            'totales'   => $totales,
            'pageTitle' => 'Naves',
        ]);
    }

    public function show(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $nave = $this->model->find((int)$id, $uid);
        if (!$nave) $this->redirect('naves');
        $cuadras = $this->model->cuadrasDeNave((int)$id);
        $this->view('naves/show', [
            'nave'      => $nave,
            'cuadras'   => $cuadras,
            'pageTitle' => e($nave['nombre']),
            'success'   => Session::getFlash('success'),
        ]);
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
        $uid      = Session::get('usuario_id');
        $nombre   = capitalizar($this->postString('nombre'));
        $granjaId = (int)$this->post('granja_id');
        if (strlen($nombre) < 1 || !$granjaId) {
            Session::flash('error', 'Nombre y granja son obligatorios.');
            $this->redirect('naves/crear');
        }
        // ── IDOR guard: la granja debe ser del usuario
        if (!$this->granjaModel->find($granjaId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Granja', 'target_id' => $granjaId,
                'context' => 'naves/store',
            ]);
            Session::flash('error', 'Granja no válida.');
            $this->redirect('naves/crear');
        }
        $this->model->create([
            'granja_id'        => $granjaId,
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
            'nombre'           => capitalizar($this->postString('nombre')),
            'especie'          => $this->postString('especie'),
            'capacidad_maxima' => (int)$this->post('capacidad_maxima', 0),
            'ancho_m'          => $this->post('ancho_m') ?: null,
            'alto_m'           => $this->post('alto_m')  ?: null,
            'largo_m'          => $this->post('largo_m') ?: null,
            'descripcion'      => $this->postString('descripcion'),
        ]);
        Session::flash('success', 'Nave actualizada.');
        $this->redirect("naves/{$id}");
    }

    public function delete(string $id): void
    {
        auth_required();
        require_rol('director');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('naves');
        }

        // Bloquear si alguna cuadra de la nave tiene lotes activos
        $lotes = \App\Core\IntegridadCheck::lotesActivosEnNave((int)$id);
        if (!empty($lotes)) {
            $detalle = implode(', ', array_map(fn($r) => $r['codigo'] . ' (' . $r['cuadra'] . ')', array_slice($lotes, 0, 5)));
            if (count($lotes) > 5) $detalle .= ' …';
            Session::flash('error',
                'No se puede eliminar la nave: tiene ' . count($lotes)
                . ' lote(s) en sus cuadras: ' . $detalle
                . '. Vacía o mueve los lotes primero.'
            );
            $this->redirect('naves');
        }

        // delete() en el modelo ya filtra por usuario_id en el WHERE
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Nave eliminada.');
        $this->redirect('naves');
    }
}
