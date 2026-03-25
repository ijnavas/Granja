<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cuadra;
use App\Models\Nave;
use App\Models\Lote;
use App\Core\Session;

class CuadraController extends BaseController
{
    private Cuadra $model;
    private Nave   $naveModel;
    private Lote   $loteModel;

    public function __construct()
    {
        $this->model     = new Cuadra();
        $this->naveModel = new Nave();
        $this->loteModel = new Lote();
    }

    // ── Listado ──────────────────────────────────────────────────
    public function index(): void
    {
        auth_required();
        $uid     = Session::get('usuario_id');
        $naveId  = isset($_GET['nave']) ? (int)$_GET['nave'] : null;
        $cuadras = $this->model->allByUsuario($uid, $naveId);
        $naves   = $this->naveModel->selectOptions($uid);

        $this->view('cuadras/index', [
            'cuadras'       => $cuadras,
            'naves'         => $naves,
            'filtroNaveId'  => $naveId,
            'pageTitle'     => 'Cuadras',
        ]);
    }

    // ── Detalle cuadra con sus lotes ─────────────────────────────
    public function show(string $id): void
    {
        auth_required();
        $uid    = Session::get('usuario_id');
        $cuadra = $this->model->find((int)$id, $uid);
        if (!$cuadra) $this->redirect('cuadras');

        $lotes         = $this->model->lotesEnCuadra((int)$id);
        $lotesDisponibles = $this->loteModel->allByUsuario($uid);

        $this->view('cuadras/show', [
            'cuadra'           => $cuadra,
            'lotes'            => $lotes,
            'lotesDisponibles' => array_filter($lotesDisponibles, fn($l) => $l['estado'] === 'activo'),
            'pageTitle'        => 'Cuadra ' . $cuadra['nombre'],
            'success'          => Session::getFlash('success'),
            'error'            => Session::getFlash('error'),
        ]);
    }

    // ── Crear ────────────────────────────────────────────────────
    public function create(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('cuadras/form', [
            'cuadra'    => null,
            'naves'     => $this->naveModel->selectOptions($uid),
            'pageTitle' => 'Nueva cuadra',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('cuadras/crear');
        }

        if (!$this->post('nave_id') || !$this->postString('nombre')) {
            Session::flash('error', 'Nombre y nave son obligatorios.');
            $this->redirect('cuadras/crear');
        }

        $this->model->create([
            'nave_id'          => (int)$this->post('nave_id'),
            'nombre'           => $this->postString('nombre'),
            'capacidad_maxima' => (int)$this->post('capacidad_maxima', 0),
            'ancho_m'          => $this->post('ancho_m') ?: null,
            'alto_m'           => $this->post('alto_m')  ?: null,
            'largo_m'          => $this->post('largo_m') ?: null,
            'descripcion'      => $this->postString('descripcion'),
        ]);

        Session::flash('success', 'Cuadra creada correctamente.');
        $this->redirect('cuadras');
    }

    // ── Editar ───────────────────────────────────────────────────
    public function edit(string $id): void
    {
        auth_required();
        $uid    = Session::get('usuario_id');
        $cuadra = $this->model->find((int)$id, $uid);
        if (!$cuadra) $this->redirect('cuadras');

        $this->view('cuadras/form', [
            'cuadra'    => $cuadra,
            'naves'     => $this->naveModel->selectOptions($uid),
            'pageTitle' => 'Editar cuadra',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("cuadras/{$id}/editar");
        }

        $this->model->update((int)$id, Session::get('usuario_id'), [
            'nave_id'          => (int)$this->post('nave_id'),
            'nombre'           => $this->postString('nombre'),
            'capacidad_maxima' => (int)$this->post('capacidad_maxima', 0),
            'ancho_m'          => $this->post('ancho_m') ?: null,
            'alto_m'           => $this->post('alto_m')  ?: null,
            'largo_m'          => $this->post('largo_m') ?: null,
            'descripcion'      => $this->postString('descripcion'),
        ]);

        Session::flash('success', 'Cuadra actualizada.');
        $this->redirect('cuadras');
    }

    // ── Eliminar ─────────────────────────────────────────────────
    public function delete(string $id): void
    {
        auth_required();
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Cuadra eliminada.');
        $this->redirect('cuadras');
    }

    // ── Asignar lote a cuadra ────────────────────────────────────
    public function asignarLote(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("cuadras/{$id}");
        }

        $loteId      = (int)$this->post('lote_id');
        $numAnimales = (int)$this->post('num_animales', 0);
        $fecha       = $this->postString('fecha_entrada') ?: date('Y-m-d');
        $obs         = $this->postString('observaciones');

        if (!$loteId || $numAnimales < 1) {
            Session::flash('error', 'Lote y número de animales son obligatorios.');
            $this->redirect("cuadras/{$id}");
        }

        $this->model->asignarLote((int)$id, $loteId, $numAnimales, $fecha, $obs);
        Session::flash('success', 'Lote asignado correctamente.');
        $this->redirect("cuadras/{$id}");
    }

    // ── Retirar lote de cuadra ───────────────────────────────────
    public function retirarLote(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("cuadras/{$id}");
        }

        $cuadraLoteId = (int)$this->post('cuadra_lote_id');
        if ($cuadraLoteId) {
            $this->model->retirarLote($cuadraLoteId);
            Session::flash('success', 'Lote retirado de la cuadra.');
        }
        $this->redirect("cuadras/{$id}");
    }
}
