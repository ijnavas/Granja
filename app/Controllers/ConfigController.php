<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RazaPorcino;
use App\Models\TablaCrecimiento;
use App\Models\EstadoAnimal;
use App\Models\TipoMovimiento;
use App\Models\ConfiguracionGranja;
use App\Core\Session;

class ConfigController extends BaseController
{
    private RazaPorcino         $razaModel;
    private TablaCrecimiento    $tablaModel;
    private EstadoAnimal        $estadoModel;
    private TipoMovimiento      $tipoMovModel;
    private ConfiguracionGranja $configModel;

    public function __construct()
    {
        $this->razaModel    = new RazaPorcino();
        $this->tablaModel   = new TablaCrecimiento();
        $this->estadoModel  = new EstadoAnimal();
        $this->tipoMovModel = new TipoMovimiento();
        $this->configModel  = new ConfiguracionGranja();
    }

    // ── Panel principal ──────────────────────────────────────────
    public function index(): void
    {
        auth_required();
        // Admins entran por razas (la sección original); resto por avisos.
        if (es_admin()) $this->redirect('configuracion/razas');
        $this->redirect('configuracion/general');
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
        $pesoMin = $this->postString('peso_min_kg') !== '' ? (float) $this->postString('peso_min_kg') : null;
        $this->estadoModel->create($nombre, $codigo, $pesoMin);
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
        $pesoMin = $this->postString('peso_min_kg') !== '' ? (float) $this->postString('peso_min_kg') : null;
        $this->estadoModel->update(
            (int)$id,
            capitalizar($this->postString('nombre')),
            $this->postString('codigo'),
            $pesoMin
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

    // ════════════════════════════════════════════════════════════
    // RESETEAR DATOS
    // ════════════════════════════════════════════════════════════
    public function resetForm(): void
    {
        auth_required();
        require_rol('admin');
        $this->view('config/reset', [
            'pageTitle' => 'Configuración — Resetear datos',
            'success'   => Session::getFlash('success'),
        ]);
    }

    public function resetConfirm(): void
    {
        auth_required();
        require_rol('admin');

        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/reset');
        }

        if ($this->postString('confirmacion') !== 'RESETEAR') {
            Session::flash('error', 'Confirmación incorrecta.');
            $this->redirect('configuracion/reset');
        }

        $db = \App\Core\Database::getInstance();

        // Borrar en orden para respetar claves foráneas
        $db->exec('DELETE FROM movimientos_historial');
        $db->exec('DELETE FROM movimientos');
        $db->exec('DELETE FROM pesajes');
        $db->exec('DELETE FROM cuadra_lote');
        $db->exec('DELETE FROM lotes');

        // Reiniciar auto_increment
        foreach (['movimientos_historial', 'movimientos', 'pesajes', 'cuadra_lote', 'lotes'] as $tabla) {
            $db->exec("ALTER TABLE {$tabla} AUTO_INCREMENT = 1");
        }

        Session::flash('success', 'Todos los datos operativos han sido eliminados correctamente.');
        $this->redirect('configuracion/reset');
    }

    // ════════════════════════════════════════════════════════════
    // TIPOS DE MOVIMIENTO
    // ════════════════════════════════════════════════════════════
    public function tiposMovimiento(): void
    {
        auth_required();
        require_rol('admin');
        $this->view('config/movimientos', [
            'tipos'     => $this->tipoMovModel->all(false),
            'pageTitle' => 'Configuración — Tipos de movimiento',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function crearTipoMovimiento(): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/movimientos');
        }
        $codigo = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '_', $this->postString('codigo'))));
        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($codigo) < 2 || strlen($nombre) < 2) {
            Session::flash('error', 'Código y nombre son obligatorios.');
            $this->redirect('configuracion/movimientos');
        }
        if ($this->tipoMovModel->findByCodigo($codigo)) {
            Session::flash('error', "Ya existe un tipo con código \"{$codigo}\".");
            $this->redirect('configuracion/movimientos');
        }
        $this->tipoMovModel->create([
            'codigo'    => $codigo,
            'nombre'    => $nombre,
            'categoria' => $this->postString('categoria') ?: 'salida',
            'activo'    => $this->post('activo') !== null ? 1 : 0,
            'orden'     => (int)$this->post('orden'),
            'color'     => $this->postString('color') ?: null,
        ]);
        Session::flash('success', "Tipo \"{$nombre}\" creado.");
        $this->redirect('configuracion/movimientos');
    }

    public function editarTipoMovimiento(string $id): void
    {
        auth_required();
        require_rol('admin');
        $tipo = $this->tipoMovModel->find((int)$id);
        if (!$tipo) $this->redirect('configuracion/movimientos');
        $this->view('config/movimiento_form', [
            'tipo'      => $tipo,
            'pageTitle' => 'Editar tipo de movimiento',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function actualizarTipoMovimiento(string $id): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("configuracion/movimientos/{$id}/editar");
        }
        $codigo = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '_', $this->postString('codigo'))));
        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect("configuracion/movimientos/{$id}/editar");
        }
        // Si el código cambia, comprobar que no choca con otro
        $actual = $this->tipoMovModel->find((int)$id);
        if ($actual && $codigo !== $actual['codigo']) {
            $otro = $this->tipoMovModel->findByCodigo($codigo);
            if ($otro && (int)$otro['id'] !== (int)$id) {
                Session::flash('error', "Ya existe un tipo con código \"{$codigo}\".");
                $this->redirect("configuracion/movimientos/{$id}/editar");
            }
        }
        $this->tipoMovModel->update((int)$id, [
            'codigo'    => $codigo,
            'nombre'    => $nombre,
            'categoria' => $this->postString('categoria') ?: 'salida',
            'activo'    => $this->post('activo') !== null ? 1 : 0,
            'orden'     => (int)$this->post('orden'),
            'color'     => $this->postString('color') ?: null,
        ]);
        Session::flash('success', 'Tipo actualizado.');
        $this->redirect('configuracion/movimientos');
    }

    public function eliminarTipoMovimiento(string $id): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('configuracion/movimientos');
        }
        $ok = $this->tipoMovModel->delete((int)$id);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Tipo eliminado.' : 'No se puede eliminar un tipo del sistema.');
        $this->redirect('configuracion/movimientos');
    }

    // ════════════════════════════════════════════════════════════
    // CONFIGURACIÓN GENERAL (umbrales de avisos)
    // ════════════════════════════════════════════════════════════
    public function general(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('config/general', [
            'config'    => $this->configModel->get($uid),
            'pageTitle' => 'Configuración — Avisos',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function actualizarGeneral(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/general');
        }
        $uid = Session::get('usuario_id');
        $this->configModel->save(
            $uid,
            (int)$this->post('dias_advertencia_movimiento'),
            (int)$this->post('pct_desviacion_peso_tabla')
        );
        Session::flash('success', 'Configuración guardada.');
        $this->redirect('configuracion/general');
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