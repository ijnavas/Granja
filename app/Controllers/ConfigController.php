<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RazaPorcino;
use App\Models\TablaCrecimiento;
use App\Models\EstadoAnimal;
use App\Core\Session;

class ConfigController extends BaseController
{
    private RazaPorcino      $razaModel;
    private TablaCrecimiento $tablaModel;
    private EstadoAnimal     $estadoModel;

    public function __construct()
    {
        $this->razaModel   = new RazaPorcino();
        $this->tablaModel  = new TablaCrecimiento();
        $this->estadoModel = new EstadoAnimal();
    }

    // ── Panel principal ──────────────────────────────────────────
    public function index(): void
    {
        auth_required();
        require_rol('admin');
        $this->redirect('configuracion/razas');
    }

    // ════════════════════════════════════════════════════════════
    // RAZAS
    // ════════════════════════════════════════════════════════════
    public function razas(): void
    {
        auth_required();
        require_rol('admin');
        $razas = $this->razaModel->allAdmin();
        $this->view('config/razas', [
            'razas'     => $razas,
            'pageTitle' => 'Configuración — Razas',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function crearRaza(): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/razas');
        }
        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect('configuracion/razas');
        }
        $this->razaModel->create([
            'usuario_id'    => null,
            'nombre'        => $nombre,
            'porcentaje'    => $this->postString('porcentaje') ?: null,
            'identificador' => strtoupper(trim($this->postString('identificador'))) ?: null,
        ]);
        Session::flash('success', "Raza \"{$nombre}\" creada.");
        $this->redirect('configuracion/razas');
    }

    public function editarRaza(string $id): void
    {
        auth_required();
        require_rol('admin');
        $raza = $this->razaModel->find((int)$id);
        if (!$raza) $this->redirect('configuracion/razas');
        $this->view('config/raza_form', [
            'raza'      => $raza,
            'pageTitle' => 'Editar raza',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function actualizarRaza(string $id): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("configuracion/razas/{$id}/editar");
        }
        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect("configuracion/razas/{$id}/editar");
        }
        $this->razaModel->update((int)$id, [
            'nombre'        => $nombre,
            'porcentaje'    => $this->postString('porcentaje') ?: null,
            'identificador' => strtoupper(trim($this->postString('identificador'))) ?: null,
        ]);
        Session::flash('success', 'Raza actualizada.');
        $this->redirect('configuracion/razas');
    }

    public function eliminarRaza(string $id): void
    {
        auth_required();
        require_rol('admin');
        $this->razaModel->delete((int)$id);
        Session::flash('success', 'Raza eliminada.');
        $this->redirect('configuracion/razas');
    }

    // ════════════════════════════════════════════════════════════
    // TABLAS DE CRECIMIENTO
    // ════════════════════════════════════════════════════════════
    public function tablas(): void
    {
        auth_required();
        require_rol('admin');
        $uid = Session::get('usuario_id');
        $this->view('config/tablas', [
            'tablas'    => $this->tablaModel->allByUsuario($uid),
            'pageTitle' => 'Configuración — Tablas de crecimiento',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function crearTabla(): void
    {
        auth_required();
        require_rol('admin');
        $uid = Session::get('usuario_id');
        $this->view('config/tabla_form', [
            'tabla'     => null,
            'lineas'    => [],
            'razas'     => $this->razaModel->allAdmin(),
            'razasAsig' => [],
            'pageTitle' => 'Nueva tabla de crecimiento',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function storeTabla(): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/tablas/crear');
        }
        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect('configuracion/tablas/crear');
        }
        $uid = Session::get('usuario_id');
        $id  = $this->tablaModel->create($uid, $nombre, $this->postString('descripcion') ?: null);

        // Guardar líneas
        $this->guardarLineas($id);

        // Asignar razas
        $razaIds = $_POST['raza_ids'] ?? [];
        $this->tablaModel->syncRazas($id, $razaIds);

        Session::flash('success', "Tabla \"{$nombre}\" creada correctamente.");
        $this->redirect('configuracion/tablas');
    }

    public function editarTabla(string $id): void
    {
        auth_required();
        require_rol('admin');
        $uid   = Session::get('usuario_id');
        $tabla = $this->tablaModel->find((int)$id, $uid);
        if (!$tabla) $this->redirect('configuracion/tablas');

        $this->view('config/tabla_form', [
            'tabla'     => $tabla,
            'lineas'    => $this->tablaModel->lineas((int)$id),
            'razas'     => $this->razaModel->allAdmin(),
            'razasAsig' => $this->tablaModel->razasAsignadas((int)$id),
            'pageTitle' => 'Editar tabla: ' . $tabla['nombre'],
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function actualizarTabla(string $id): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("configuracion/tablas/{$id}/editar");
        }
        $uid   = Session::get('usuario_id');
        $tabla = $this->tablaModel->find((int)$id, $uid);
        if (!$tabla) $this->redirect('configuracion/tablas');

        $this->tablaModel->update((int)$id,
            capitalizar($this->postString('nombre')),
            $this->postString('descripcion') ?: null
        );

        // Reemplazar líneas
        $this->tablaModel->deleteTodasLineas((int)$id);
        $this->guardarLineas((int)$id);

        // Asignar razas
        $razaIds = $_POST['raza_ids'] ?? [];
        $this->tablaModel->syncRazas((int)$id, $razaIds);

        Session::flash('success', 'Tabla actualizada correctamente.');
        $this->redirect('configuracion/tablas');
    }

    public function eliminarTabla(string $id): void
    {
        auth_required();
        require_rol('admin');
        $this->tablaModel->delete((int)$id);
        Session::flash('success', 'Tabla eliminada.');
        $this->redirect('configuracion/tablas');
    }

    // ════════════════════════════════════════════════════════════
    // ESTADOS DE ANIMAL
    // ════════════════════════════════════════════════════════════
    public function estados(): void
    {
        auth_required();
        require_rol('admin');
        $this->view('config/estados', [
            'estados'   => $this->estadoModel->all(),
            'pageTitle' => 'Configuración — Estados',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function crearEstado(): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/estados');
        }
        $nombre = capitalizar($this->postString('nombre'));
        $codigo = strtolower(trim($this->postString('codigo')));
        if (!$nombre || !$codigo) {
            Session::flash('error', 'Nombre y código son obligatorios.');
            $this->redirect('configuracion/estados');
        }
        $this->estadoModel->create($nombre, $codigo);
        Session::flash('success', "Estado \"{$nombre}\" creado.");
        $this->redirect('configuracion/estados');
    }

    public function editarEstado(string $id): void
    {
        auth_required();
        require_rol('admin');
        $estado = $this->estadoModel->find((int)$id);
        if (!$estado) $this->redirect('configuracion/estados');
        $this->view('config/estado_form', [
            'estado'    => $estado,
            'pageTitle' => 'Editar estado',
        ]);
    }

    public function actualizarEstado(string $id): void
    {
        auth_required();
        require_rol('admin');
        $this->estadoModel->update(
            (int)$id,
            capitalizar($this->postString('nombre')),
            $this->postString('codigo')
        );
        Session::flash('success', 'Estado actualizado.');
        $this->redirect('configuracion/estados');
    }

    public function toggleEstado(string $id): void
    {
        auth_required();
        require_rol('admin');
        $this->estadoModel->toggleActivo((int)$id);
        $this->redirect('configuracion/estados');
    }

    private function guardarLineas(int $tablaId): void
    {
        $semanas  = $_POST['semana']  ?? [];
        $pesos    = $_POST['peso']    ?? [];
        $consumos = $_POST['consumo'] ?? [];
        $costes   = $_POST['coste']   ?? [];

        foreach ($semanas as $i => $semana) {
            $semana = (int)$semana;
            if ($semana < 1) continue;
            $peso    = isset($pesos[$i])    ? (float)str_replace(',', '.', $pesos[$i])    : 0;
            $consumo = isset($consumos[$i]) && $consumos[$i] !== '' ? (int)$consumos[$i] : null;
            $coste   = isset($costes[$i])   && $costes[$i]   !== '' ? (float)str_replace(',', '.', $costes[$i]) : null;
            $this->tablaModel->upsertLinea($tablaId, $semana, $peso, $consumo, $coste);
        }
    }
}