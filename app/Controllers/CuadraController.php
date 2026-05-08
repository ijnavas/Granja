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

    public function index(): void
    {
        auth_required();
        $uid     = Session::get('usuario_id');
        $naveId  = isset($_GET['nave']) ? (int)$_GET['nave'] : null;
        $cuadras = $this->model->allByUsuario($uid, $naveId);
        $naves   = $this->naveModel->selectOptions($uid);
        $this->view('cuadras/index', [
            'cuadras'      => $cuadras,
            'naves'        => $naves,
            'filtroNaveId' => $naveId,
            'pageTitle'    => 'Cuadras',
            'success'      => Session::getFlash('success'),
        ]);
    }

    public function show(string $id): void
    {
        auth_required();
        $uid    = Session::get('usuario_id');
        $cuadra = $this->model->find((int)$id, $uid);
        if (!$cuadra) $this->redirect('cuadras');
        $lotes            = $this->model->lotesEnCuadra((int)$id);
        $lotesDisponibles = array_filter(
            $this->loteModel->allByUsuario($uid),
            fn($l) => $l['estado'] === 'activo'
        );
        $this->view('cuadras/show', [
            'cuadra'           => $cuadra,
            'lotes'            => $lotes,
            'lotesDisponibles' => $lotesDisponibles,
            'pageTitle'        => 'Cuadra ' . $cuadra['nombre'],
            'success'          => Session::getFlash('success'),
            'error'            => Session::getFlash('error'),
        ]);
    }

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
        $uid    = Session::get('usuario_id');
        $naveId = (int)$this->post('nave_id');
        if (!$naveId || !$this->postString('nombre')) {
            Session::flash('error', 'Nombre y nave son obligatorios.');
            $this->redirect('cuadras/crear');
        }
        // ── IDOR guard: la nave debe pertenecer al usuario
        if (!$this->naveModel->find($naveId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Nave', 'target_id' => $naveId,
                'context' => 'cuadras/store',
            ]);
            Session::flash('error', 'Nave no válida.');
            $this->redirect('cuadras/crear');
        }
        $this->model->create([
            'nave_id'          => $naveId,
            'nombre'           => capitalizar($this->postString('nombre')),
            'capacidad_maxima' => (int)$this->post('capacidad_maxima', 0),
            'ancho_m'          => $this->post('ancho_m') ?: null,
            'alto_m'           => $this->post('alto_m')  ?: null,
            'largo_m'          => $this->post('largo_m') ?: null,
            'descripcion'      => $this->postString('descripcion'),
        ]);
        Session::flash('success', 'Cuadra creada correctamente.');
        $this->redirect('cuadras');
    }

    // ── Creación masiva ──────────────────────────────────────────
    public function createMasiva(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('cuadras/masiva', [
            'naves'     => $this->naveModel->selectOptions($uid),
            'pageTitle' => 'Crear cuadras en lote',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function storeMasiva(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('cuadras/masiva');
        }

        $uid       = Session::get('usuario_id');
        $naveId    = (int)$this->post('nave_id');
        // ── IDOR guard: la nave debe pertenecer al usuario
        if ($naveId && !$this->naveModel->find($naveId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Nave', 'target_id' => $naveId,
                'context' => 'cuadras/storeMasiva',
            ]);
            Session::flash('error', 'Nave no válida.');
            $this->redirect('cuadras/masiva');
        }
        $inicio    = (int)$this->post('inicio', 1);
        $cantidad  = (int)$this->post('cantidad', 0);
        $prefijo   = $this->postString('prefijo');   // ya viene sin capitalizar raro
        $capacidad = (int)$this->post('capacidad_maxima', 0);
        $ancho     = $this->post('ancho_m') ?: null;
        $alto      = $this->post('alto_m')  ?: null;
        $largo     = $this->post('largo_m') ?: null;
        $ceros     = (int)$this->post('ceros', 0);

        if (!$naveId || $cantidad < 1 || $cantidad > 500) {
            Session::flash('error', 'Nave obligatoria y rango entre 1 y 500 cuadras.');
            $this->redirect('cuadras/masiva');
        }

        $creadas = 0;
        for ($i = $inicio; $i < $inicio + $cantidad; $i++) {
            $numero = $ceros > 0 ? str_pad((string)$i, $ceros, '0', STR_PAD_LEFT) : (string)$i;
            $nombre = trim($prefijo . $numero);
            $this->model->create([
                'nave_id'          => $naveId,
                'nombre'           => $nombre,
                'capacidad_maxima' => $capacidad,
                'ancho_m'          => $ancho,
                'alto_m'           => $alto,
                'largo_m'          => $largo,
                'descripcion'      => null,
            ]);
            $creadas++;
        }

        Session::flash('success', "Se han creado {$creadas} cuadras correctamente.");
        $this->redirect('cuadras?nave=' . $naveId);
    }

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
        $uid = Session::get('usuario_id');
        // IDOR guard: la cuadra debe ser del usuario
        if (!$this->model->find((int)$id, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Cuadra', 'target_id' => (int)$id,
                'context' => 'cuadras/update',
            ]);
            $this->redirect('cuadras');
        }
        $naveId = (int)$this->post('nave_id');
        if ($naveId && !$this->naveModel->find($naveId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Nave', 'target_id' => $naveId,
                'context' => 'cuadras/update',
            ]);
            Session::flash('error', 'Nave no válida.');
            $this->redirect("cuadras/{$id}/editar");
        }
        $this->model->update((int)$id, $uid, [
            'nave_id'          => $naveId,
            'nombre'           => capitalizar($this->postString('nombre')),
            'capacidad_maxima' => (int)$this->post('capacidad_maxima', 0),
            'ancho_m'          => $this->post('ancho_m') ?: null,
            'alto_m'           => $this->post('alto_m')  ?: null,
            'largo_m'          => $this->post('largo_m') ?: null,
            'descripcion'      => $this->postString('descripcion'),
        ]);
        Session::flash('success', 'Cuadra actualizada.');
        $this->redirect("cuadras/{$id}");
    }

    public function delete(string $id): void
    {
        auth_required();
        require_rol('director');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('cuadras');
        }

        // Bloquear si la cuadra tiene lotes activos (cuadra_lote.activo=1
        // con num_animales > 0). El soft-delete dejaría a esos lotes
        // apuntando a una cuadra invisible — caso C7 que ya nos quemó.
        $lotes = \App\Core\IntegridadCheck::lotesActivosEnCuadra((int)$id);
        if (!empty($lotes)) {
            $codigos = implode(', ', array_map(fn($r) => $r['codigo'] . ' (' . $r['num_animales'] . ' anim.)', $lotes));
            Session::flash('error',
                'No se puede eliminar la cuadra: tiene ' . count($lotes) . ' lote(s) asignado(s): '
                . $codigos . '. Vacíala primero (mueve o vende los animales).'
            );
            $this->redirect('cuadras');
        }

        // delete() en el modelo ya filtra por usuario_id en el WHERE
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Cuadra eliminada.');
        $this->redirect('cuadras');
    }

    public function asignarLote(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("cuadras/{$id}");
        }
        $uid = Session::get('usuario_id');
        // IDOR guard: la cuadra debe ser del usuario
        if (!$this->model->find((int)$id, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Cuadra', 'target_id' => (int)$id,
                'context' => 'cuadras/asignarLote',
            ]);
            $this->redirect('cuadras');
        }
        $loteId      = (int)$this->post('lote_id');
        $numAnimales = (int)$this->post('num_animales', 0);
        $fecha       = $this->postString('fecha_entrada') ?: date('Y-m-d');
        $obs         = $this->postString('observaciones');
        if (!$loteId || $numAnimales < 1) {
            Session::flash('error', 'Lote y número de animales son obligatorios.');
            $this->redirect("cuadras/{$id}");
        }
        // IDOR guard: el lote debe ser del usuario
        if (!$this->loteModel->find($loteId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Lote', 'target_id' => $loteId,
                'context' => 'cuadras/asignarLote',
            ]);
            Session::flash('error', 'Lote no válido.');
            $this->redirect("cuadras/{$id}");
        }
        $this->model->asignarLote((int)$id, $loteId, $numAnimales, $fecha, $obs);
        Session::flash('success', 'Lote asignado correctamente.');
        $this->redirect("cuadras/{$id}");
    }

    public function retirarLote(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("cuadras/{$id}");
        }
        $uid = Session::get('usuario_id');
        // IDOR guard: la cuadra debe ser del usuario
        if (!$this->model->find((int)$id, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Cuadra', 'target_id' => (int)$id,
                'context' => 'cuadras/retirarLote',
            ]);
            $this->redirect('cuadras');
        }
        $cuadraLoteId = (int)$this->post('cuadra_lote_id');
        if ($cuadraLoteId) {
            // IDOR: verificar que el cuadra_lote pertenece a esta cuadra del usuario
            $db   = \App\Core\Database::getInstance();
            $stmt = $db->prepare("SELECT 1 FROM cuadra_lote WHERE id = :id AND cuadra_id = :cid");
            $stmt->execute(['id' => $cuadraLoteId, 'cid' => (int)$id]);
            if (!$stmt->fetchColumn()) {
                \App\Core\SecurityLog::log('idor_attempt', [
                    'user_id' => $uid, 'resource' => 'CuadraLote', 'target_id' => $cuadraLoteId,
                    'context' => 'cuadras/retirarLote',
                ]);
                $this->redirect("cuadras/{$id}");
            }
            $this->model->retirarLote($cuadraLoteId);
            Session::flash('success', 'Lote retirado de la cuadra.');
        }
        $this->redirect("cuadras/{$id}");
    }
}
