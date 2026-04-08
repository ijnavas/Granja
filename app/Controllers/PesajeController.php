<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Pesaje;
use App\Models\Lote;
use App\Core\Session;

class PesajeController extends BaseController
{
    private Pesaje $model;
    private Lote   $loteModel;

    public function __construct()
    {
        $this->model     = new Pesaje();
        $this->loteModel = new Lote();
    }

    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('pesajes/index', [
            'pesajes'   => $this->model->allByUsuario($uid),
            'pageTitle' => 'Pesajes',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function create(): void
    {
        auth_required();
        $uid   = Session::get('usuario_id');
        $lotes = $this->loteModel->allByUsuario($uid);
        // Preseleccionar lote si viene por querystring
        $loteId = (int) ($_GET['lote_id'] ?? 0);

        $this->view('pesajes/form', [
            'lotes'     => array_filter($lotes, fn($l) => $l['estado'] === 'activo'),
            'loteId'    => $loteId,
            'pageTitle' => 'Nuevo pesaje',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('pesajes/crear');
        }

        $uid    = Session::get('usuario_id');
        $loteId = (int) $this->postString('lote_id');
        $fecha  = $this->postString('fecha');
        $peso   = (float) $this->postString('peso_medio_kg');
        $num    = (int) $this->postString('num_animales_pesados');

        if (!$loteId || !$fecha || !$peso || !$num) {
            Session::flash('error', 'Lote, fecha, peso y número de animales son obligatorios.');
            $this->redirect('pesajes/crear');
        }

        // ── IDOR guard: el lote debe pertenecer al usuario
        if (!$this->loteModel->find($loteId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Lote', 'target_id' => $loteId,
                'context' => 'pesajes/store',
            ]);
            Session::flash('error', 'Lote no válido.');
            $this->redirect('pesajes/crear');
        }

        $consumo = $this->postString('consumo_pienso_kg') !== '' ? (float) $this->postString('consumo_pienso_kg') : null;
        $ic      = $this->postString('ic_real')           !== '' ? (float) $this->postString('ic_real')           : null;
        $obs     = $this->postString('observaciones') ?: null;

        $this->model->create([
            'lote_id'             => $loteId,
            'fecha'               => $fecha,
            'peso_medio_kg'       => $peso,
            'num_animales_pesados'=> $num,
            'consumo_pienso_kg'   => $consumo,
            'ic_real'             => $ic,
            'observaciones'       => $obs,
            'usuario_id'          => $uid,
        ]);

        Session::flash('success', 'Pesaje registrado correctamente.');
        $this->redirect('pesajes');
    }

    public function edit(string $id): void
    {
        auth_required();
        $uid    = Session::get('usuario_id');
        $pesaje = $this->ownedPesajeOrAbort((int)$id, $uid);

        $lotes = $this->loteModel->allByUsuario($uid);

        $this->view('pesajes/edit', [
            'pesaje'    => $pesaje,
            'lotes'     => array_filter($lotes, fn($l) => $l['estado'] === 'activo' || $l['id'] == $pesaje['lote_id']),
            'pageTitle' => 'Editar pesaje',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("pesajes/{$id}/editar");
        }

        $uid = Session::get('usuario_id');
        // IDOR guard: el pesaje debe ser del usuario
        $this->ownedPesajeOrAbort((int)$id, $uid);

        $fecha  = $this->postString('fecha');
        $peso   = (float) $this->postString('peso_medio_kg');
        $num    = (int) $this->postString('num_animales_pesados');

        if (!$fecha || !$peso || !$num) {
            Session::flash('error', 'Fecha, peso y número de animales son obligatorios.');
            $this->redirect("pesajes/{$id}/editar");
        }

        $consumo = $this->postString('consumo_pienso_kg') !== '' ? (float) $this->postString('consumo_pienso_kg') : null;
        $ic      = $this->postString('ic_real')           !== '' ? (float) $this->postString('ic_real')           : null;
        $obs     = $this->postString('observaciones') ?: null;

        $this->model->update((int)$id, [
            'fecha'               => $fecha,
            'peso_medio_kg'       => $peso,
            'num_animales_pesados'=> $num,
            'consumo_pienso_kg'   => $consumo,
            'ic_real'             => $ic,
            'observaciones'       => $obs,
        ]);

        Session::flash('success', 'Pesaje actualizado.');
        $this->redirect('pesajes');
    }

    public function delete(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('pesajes');
        }
        $uid = Session::get('usuario_id');
        // IDOR guard: el pesaje debe ser del usuario
        $this->ownedPesajeOrAbort((int)$id, $uid);
        $this->model->delete((int)$id);
        Session::flash('success', 'Pesaje eliminado.');
        $this->redirect('pesajes');
    }

    /**
     * Verifica que el pesaje exista y pertenezca al usuario. Pesaje::find()
     * no filtra por usuario_id, por lo que validamos la columna aquí.
     */
    private function ownedPesajeOrAbort(int $id, int $uid): array
    {
        $pesaje = $this->model->find($id);
        if (!$pesaje || (int)($pesaje['usuario_id'] ?? 0) !== $uid) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Pesaje', 'target_id' => $id,
            ]);
            Session::flash('error', 'Pesaje no encontrado o sin permisos.');
            $this->redirect('pesajes');
        }
        return $pesaje;
    }
}
