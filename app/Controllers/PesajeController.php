<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Pesaje;
use App\Models\Lote;
use App\Models\Granja;
use App\Core\Session;
use App\Core\Paginator;
use App\Core\AuditLog;

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

        $filtros = array_filter([
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'lote'        => trim($_GET['lote']        ?? ''),
            'granja_id'   => trim($_GET['granja_id']   ?? ''),
        ]);

        $page       = max(1, (int)($_GET['page'] ?? 1));
        $total      = $this->model->countByUsuario($uid, $filtros);
        $paginacion = new Paginator($total, $page, 50);

        $this->view('pesajes/index', [
            'pesajes'    => $this->model->allByUsuario($uid, $filtros, $paginacion->perPage, $paginacion->offset),
            'filtros'    => $filtros,
            'paginacion' => $paginacion,
            'granjas'    => (new Granja())->selectOptions($uid),
            'pageTitle'  => 'Pesajes',
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ]);
    }

    public function export(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        $filtros = array_filter([
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'lote'        => trim($_GET['lote']        ?? ''),
            'granja_id'   => trim($_GET['granja_id']   ?? ''),
        ]);

        $pesajes = $this->model->allByUsuario($uid, $filtros);
        $N       = \App\Core\CsvExport::class;

        $rows = [];
        foreach ($pesajes as $p) {
            $rows[] = [
                date('d/m/Y', strtotime($p['fecha'])),
                $p['lote_codigo'],
                $p['nave_nombre'] ?? '',
                $p['granja_nombre'] ?? '',
                $p['semana_pesaje'] ? 'S' . $p['semana_pesaje'] : '',
                $p['num_animales_pesados'],
                $N::num((float)$p['peso_medio_kg'], 3),
                $p['peso_tabla_pesaje'] ? $N::num((float)$p['peso_tabla_pesaje'], 3) : '',
                $p['peso_proyectado_hoy'] ? $N::num((float)$p['peso_proyectado_hoy'], 3) : '',
                $p['peso_tabla_hoy'] ? $N::num((float)$p['peso_tabla_hoy'], 3) : '',
                $p['ic_real'] ? $N::num((float)$p['ic_real'], 3) : '',
            ];
        }

        $N::download(
            'pesajes_' . date('Y-m-d') . '.csv',
            ['Fecha', 'Lote', 'Nave', 'Granja', 'Semana', 'Animales', 'Peso real (kg)', 'Peso tabla pesaje (kg)', 'Peso proyectado hoy (kg)', 'Peso tabla hoy (kg)', 'IC real'],
            $rows
        );
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

        $datos = [
            'lote_id'             => $loteId,
            'fecha'               => $fecha,
            'peso_medio_kg'       => $peso,
            'num_animales_pesados'=> $num,
            'consumo_pienso_kg'   => $consumo,
            'ic_real'             => $ic,
            'observaciones'       => $obs,
            'usuario_id'          => $uid,
        ];
        $this->model->create($datos);
        $pesajeId = (int)\App\Core\Database::getInstance()->lastInsertId();
        AuditLog::log('pesaje', $pesajeId, 'create', null, $datos);

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
        $antes = $this->ownedPesajeOrAbort((int)$id, $uid);

        $fecha  = $this->postString('fecha');
        $peso   = (float) $this->postString('peso_medio_kg');
        $num    = (int) $this->postString('num_animales_pesados');

        if (!$fecha || !$peso || !$num) {
            Session::flash('error', 'Fecha, peso y número de animales son obligatorios.');
            $this->redirect("pesajes/{$id}/editar");
        }

        // Bloquear si hay inventarios posteriores que dependen de este lote
        $fechaParaCheck = min($antes['fecha'], $fecha);
        $invs = \App\Core\IntegridadCheck::inventariosDeLoteDesde((int)$antes['lote_id'], $fechaParaCheck, $uid);
        if (!empty($invs)) {
            Session::flash('error', \App\Core\IntegridadCheck::mensajeInventarios($invs, 'editar este pesaje'));
            $this->redirect("pesajes/{$id}/editar");
        }

        $consumo = $this->postString('consumo_pienso_kg') !== '' ? (float) $this->postString('consumo_pienso_kg') : null;
        $ic      = $this->postString('ic_real')           !== '' ? (float) $this->postString('ic_real')           : null;
        $obs     = $this->postString('observaciones') ?: null;

        $datos = [
            'fecha'               => $fecha,
            'peso_medio_kg'       => $peso,
            'num_animales_pesados'=> $num,
            'consumo_pienso_kg'   => $consumo,
            'ic_real'             => $ic,
            'observaciones'       => $obs,
        ];
        $this->model->update((int)$id, $datos);
        AuditLog::log('pesaje', (int)$id, 'update', $antes, $datos);

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
        $antes = $this->ownedPesajeOrAbort((int)$id, $uid);

        // Bloquear si hay inventarios posteriores que dependen de este lote
        $invs = \App\Core\IntegridadCheck::inventariosDeLoteDesde((int)$antes['lote_id'], $antes['fecha'], $uid);
        if (!empty($invs)) {
            Session::flash('error', \App\Core\IntegridadCheck::mensajeInventarios($invs, 'borrar este pesaje'));
            $this->redirect('pesajes');
        }

        $this->model->delete((int)$id);
        AuditLog::log('pesaje', (int)$id, 'delete', $antes, null);
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
