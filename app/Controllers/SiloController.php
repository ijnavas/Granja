<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Silo;
use App\Models\Granja;
use App\Models\Nave;
use App\Core\Session;
use App\Core\AuditLog;

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

    public function show(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $silo = $this->model->find((int)$id, $uid);
        if (!$silo) $this->redirect('silos');

        $this->view('silos/show', [
            'silo'      => $silo,
            'navesAsig' => $this->model->navesAsignadas((int)$id),
            'pageTitle' => e($silo['nombre']),
            'success'   => Session::getFlash('success'),
        ]);
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

        $uid      = Session::get('usuario_id');
        $granjaId = (int)$this->post('granja_id');
        if (!$granjaId || !$this->postString('nombre')) {
            Session::flash('error', 'Nombre y granja son obligatorios.');
            $this->redirect('silos/crear');
        }
        // ── IDOR guard: la granja debe ser del usuario
        if (!$this->granjaModel->find($granjaId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Granja', 'target_id' => $granjaId,
                'context' => 'silos/store',
            ]);
            Session::flash('error', 'Granja no válida.');
            $this->redirect('silos/crear');
        }

        // Filtrar nave_ids[] dejando solo las del usuario
        $naveIds = $this->filterOwnedNaveIds($_POST['nave_ids'] ?? [], $uid);

        $datos = [
            'granja_id'       => $granjaId,
            'nombre'          => $this->postString('nombre'),
            'capacidad_kg'    => (float)$this->post('capacidad_kg', 0),
            'stock_actual_kg' => (float)$this->post('stock_actual_kg', 0),
            'stock_minimo_kg' => (float)$this->post('stock_minimo_kg', 0),
            'descripcion'     => $this->postString('descripcion'),
        ];
        $this->model->create($datos, $naveIds);
        $siloId = (int)\App\Core\Database::getInstance()->lastInsertId();
        AuditLog::log('silo', $siloId, 'create', null, $datos + ['nave_ids' => $naveIds]);

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

        $uid = Session::get('usuario_id');
        // IDOR guard: el silo debe ser del usuario (find filtra por uid)
        $antes = $this->model->find((int)$id, $uid);
        if (!$antes) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Silo', 'target_id' => (int)$id,
                'context' => 'silos/update',
            ]);
            $this->redirect('silos');
        }

        // Filtrar nave_ids[] dejando solo las del usuario
        $naveIds = $this->filterOwnedNaveIds($_POST['nave_ids'] ?? [], $uid);

        $datos = [
            'nombre'          => $this->postString('nombre'),
            'capacidad_kg'    => (float)$this->post('capacidad_kg', 0),
            'stock_actual_kg' => (float)$this->post('stock_actual_kg', 0),
            'stock_minimo_kg' => (float)$this->post('stock_minimo_kg', 0),
            'descripcion'     => $this->postString('descripcion'),
        ];
        $this->model->update((int)$id, $uid, $datos, $naveIds);
        $despues = $this->model->find((int)$id, $uid);
        AuditLog::log('silo', (int)$id, 'update', $antes, $despues);

        Session::flash('success', 'Silo actualizado.');
        $this->redirect('silos');
    }

    public function delete(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('silos');
        }
        // delete() en el modelo ya filtra por usuario_id en el WHERE
        $uid   = Session::get('usuario_id');
        $antes = $this->model->find((int)$id, $uid);
        $this->model->delete((int)$id, $uid);
        if ($antes) {
            AuditLog::log('silo', (int)$id, 'delete', $antes, null);
        }
        Session::flash('success', 'Silo eliminado.');
        $this->redirect('silos');
    }

    /**
     * Devuelve solo los IDs de nave que pertenecen al usuario.
     * Útil para filtrar arrays de nave_ids[] venidos de POST.
     */
    private function filterOwnedNaveIds(array $rawIds, int $uid): array
    {
        $out = [];
        foreach ($rawIds as $nid) {
            $nid = (int)$nid;
            if ($nid && $this->naveModel->find($nid, $uid)) {
                $out[] = $nid;
            } elseif ($nid) {
                \App\Core\SecurityLog::log('idor_attempt', [
                    'user_id' => $uid, 'resource' => 'Nave', 'target_id' => $nid,
                    'context' => 'silos/filterOwnedNaveIds',
                ]);
            }
        }
        return $out;
    }
}