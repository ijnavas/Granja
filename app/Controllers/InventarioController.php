<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Inventario;
use App\Core\Session;

class InventarioController extends BaseController
{
    private Inventario $model;

    public function __construct()
    {
        $this->model = new Inventario();
    }

    // ── Listado ──────────────────────────────────────────────────
    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('inventarios/index', [
            'inventarios' => $this->model->allByUsuario($uid),
            'pageTitle'   => 'Inventarios',
            'success'     => Session::getFlash('success'),
            'error'       => Session::getFlash('error'),
        ]);
    }

    // ── Crear (formulario + previsualización AJAX) ────────────────
    public function create(): void
    {
        auth_required();
        $this->view('inventarios/form', [
            'pageTitle' => 'Nuevo inventario',
            'error'     => Session::getFlash('error'),
        ]);
    }

    // ── API: previsualización de líneas por fecha ─────────────────
    public function preview(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid   = Session::get('usuario_id');
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $tipo  = $_GET['tipo']  ?? 'cuadra';
        $lineas = $this->model->calcularLineas($uid, $fecha, $tipo);
        echo json_encode($lineas);
    }

    // ── Guardar ───────────────────────────────────────────────────
    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('inventarios/crear');
        }

        $uid    = Session::get('usuario_id');
        $fecha  = $this->postString('fecha') ?: date('Y-m-d');
        $nombre = trim($this->postString('nombre')) ?: null;
        $tipo   = in_array($this->postString('tipo'), ['cuadra', 'global']) ? $this->postString('tipo') : 'cuadra';

        $lineas = $this->model->calcularLineas($uid, $fecha, $tipo);
        if (empty($lineas)) {
            Session::flash('error', 'No hay lotes activos para esa fecha.');
            $this->redirect('inventarios/crear');
        }

        $id = $this->model->create($uid, $fecha, $nombre, $tipo);
        foreach ($lineas as $l) {
            // Quitar claves de preview (_*)
            $linea = array_filter($l, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
            $this->model->insertLinea($id, $linea);
        }

        Session::flash('success', 'Inventario generado correctamente.');
        $this->redirect("inventarios/{$id}");
    }

    // ── Ver detalle ───────────────────────────────────────────────
    public function show(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $inv  = $this->model->find((int)$id, $uid);
        if (!$inv) $this->redirect('inventarios');

        $this->view('inventarios/show', [
            'inventario' => $inv,
            'lineas'     => $this->model->lineas((int)$id),
            'pageTitle'  => 'Inventario ' . date('d/m/Y', strtotime($inv['fecha'])),
            'success'    => Session::getFlash('success'),
        ]);
    }

    // ── Eliminar ──────────────────────────────────────────────────
    public function delete(string $id): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $inv = $this->model->find((int)$id, $uid);
        if ($inv) $this->model->delete((int)$id);
        Session::flash('success', 'Inventario eliminado.');
        $this->redirect('inventarios');
    }
}
