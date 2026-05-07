<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RazaPorcino;
use App\Models\TablaCrecimiento;
use App\Models\EstadoAnimal;
use App\Models\TipoMovimiento;
use App\Models\MotivoBaja;
use App\Models\ConfiguracionGranja;
use App\Core\Session;

class ConfigController extends BaseController
{
    private RazaPorcino         $razaModel;
    private TablaCrecimiento    $tablaModel;
    private EstadoAnimal        $estadoModel;
    private TipoMovimiento      $tipoMovModel;
    private MotivoBaja          $motivoModel;
    private ConfiguracionGranja $configModel;

    public function __construct()
    {
        $this->razaModel    = new RazaPorcino();
        $this->tablaModel   = new TablaCrecimiento();
        $this->estadoModel  = new EstadoAnimal();
        $this->tipoMovModel = new TipoMovimiento();
        $this->motivoModel  = new MotivoBaja();
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

        // Borrar en orden para respetar claves foráneas: hijos antes que padres.
        // Movimientos / lotes / cuadras (datos animales)
        $db->exec('DELETE FROM movimientos_historial');
        $db->exec('DELETE FROM movimientos');
        $db->exec('DELETE FROM movimiento_cuadras');
        $db->exec('DELETE FROM pesajes');
        $db->exec('DELETE FROM cuadra_lote');
        $db->exec('DELETE FROM lotes');

        // Inventarios (lineas y silos cascadean en algunos sitios; por seguridad, manuales)
        $db->exec('DELETE FROM inventario_lineas');
        $db->exec('DELETE FROM inventario_silos');
        $db->exec('DELETE FROM inventarios');

        // Silos: recargas, calibraciones, histórico de stock y los silos en sí
        foreach (['silo_recargas', 'silo_calibraciones', 'silo_stock_historico', 'silos'] as $t) {
            try { $db->exec("DELETE FROM {$t}"); } catch (\Throwable $e) { /* tabla puede no existir */ }
        }

        // Reiniciar auto_increment de todas las tablas vaciadas
        $tablas = [
            'movimientos_historial', 'movimientos', 'movimiento_cuadras',
            'pesajes', 'cuadra_lote', 'lotes',
            'inventario_lineas', 'inventario_silos', 'inventarios',
            'silo_recargas', 'silo_calibraciones', 'silo_stock_historico', 'silos',
        ];
        foreach ($tablas as $tabla) {
            try { $db->exec("ALTER TABLE {$tabla} AUTO_INCREMENT = 1"); } catch (\Throwable $e) { /* ignore */ }
        }

        Session::flash('success', 'Todos los datos operativos (lotes, movimientos, inventarios, silos y recargas) han sido eliminados correctamente.');
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
            'motivos'   => $this->motivoModel->all(false),
            'pageTitle' => 'Configuración — Avisos',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    // ── Motivos de baja (subsección de Avisos) ──────────────────
    public function crearMotivo(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/general');
        }
        $codigo = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '_', $this->postString('codigo'))));
        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($codigo) < 2 || strlen($nombre) < 2) {
            Session::flash('error', 'Código y nombre son obligatorios.');
            $this->redirect('configuracion/general');
        }
        if ($this->motivoModel->findByCodigo($codigo)) {
            Session::flash('error', "Ya existe un motivo con código \"{$codigo}\".");
            $this->redirect('configuracion/general');
        }
        $this->motivoModel->create($codigo, $nombre, (int)$this->post('orden'), true);
        Session::flash('success', "Motivo \"{$nombre}\" creado.");
        $this->redirect('configuracion/general');
    }

    public function actualizarMotivo(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/general');
        }
        $codigo = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '_', $this->postString('codigo'))));
        $nombre = capitalizar($this->postString('nombre'));
        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect('configuracion/general');
        }
        $this->motivoModel->update(
            (int)$id,
            $codigo,
            $nombre,
            (int)$this->post('orden'),
            $this->post('activo') !== null
        );
        Session::flash('success', 'Motivo actualizado.');
        $this->redirect('configuracion/general');
    }

    public function eliminarMotivo(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('configuracion/general');
        }
        $this->motivoModel->delete((int)$id);
        Session::flash('success', 'Motivo eliminado.');
        $this->redirect('configuracion/general');
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

    // ════════════════════════════════════════════════════════════
    // SEED TEST DATA — un solo uso para crear datos de prueba
    // ════════════════════════════════════════════════════════════
    public function seedTestForm(): void
    {
        auth_required();
        require_rol('admin');
        $this->view('config/seed_test', [
            'pageTitle' => 'Configuración — Datos de prueba',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function seedTestRun(): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/seed-test');
        }
        if ($this->postString('confirmacion') !== 'SEMBRAR') {
            Session::flash('error', 'Confirmación incorrecta. Escribe SEMBRAR.');
            $this->redirect('configuracion/seed-test');
        }

        $uid = (int) Session::get('usuario_id');
        $db  = \App\Core\Database::getInstance();

        // Granja: la primera del usuario
        $stmt = $db->prepare("SELECT id, especie FROM granjas WHERE usuario_id = :uid ORDER BY id LIMIT 1");
        $stmt->execute(['uid' => $uid]);
        $granja = $stmt->fetch();
        if (!$granja) {
            Session::flash('error', 'Crea al menos una granja antes de sembrar datos.');
            $this->redirect('configuracion/seed-test');
        }
        $granjaId = (int)$granja['id'];

        // Tipo animal porcino (cebo)
        $tipoAnimalId = (int) $db->query(
            "SELECT id FROM tipos_animal WHERE especie = 'porcino' ORDER BY id LIMIT 1"
        )->fetchColumn();
        if (!$tipoAnimalId) {
            Session::flash('error', 'Falta tipo de animal porcino en la BD.');
            $this->redirect('configuracion/seed-test');
        }

        // Raza (la primera disponible, opcional)
        $razaId = $db->query("SELECT id FROM razas_porcino LIMIT 1")->fetchColumn() ?: null;

        // Helper inline: crea o devuelve nave/cuadra
        $naveOrCreate = function(string $nombre) use ($db, $granjaId): int {
            $s = $db->prepare("SELECT id FROM naves WHERE granja_id = :g AND nombre = :n");
            $s->execute(['g' => $granjaId, 'n' => $nombre]);
            $id = $s->fetchColumn();
            if ($id) return (int)$id;
            $db->prepare("INSERT INTO naves (granja_id, nombre, capacidad_maxima, especie, activa) VALUES (:g, :n, 5000, 'porcino', 1)")
               ->execute(['g' => $granjaId, 'n' => $nombre]);
            return (int)$db->lastInsertId();
        };
        $cuadraOrCreate = function(int $naveId, string $nombre) use ($db): int {
            $s = $db->prepare("SELECT id FROM cuadras WHERE nave_id = :n AND nombre = :nm");
            $s->execute(['n' => $naveId, 'nm' => $nombre]);
            $id = $s->fetchColumn();
            if ($id) return (int)$id;
            $db->prepare("INSERT INTO cuadras (nave_id, nombre, capacidad_maxima, activa) VALUES (:n, :nm, 1000, 1)")
               ->execute(['n' => $naveId, 'nm' => $nombre]);
            return (int)$db->lastInsertId();
        };

        // Crear naves y cuadras
        $d1Id = $naveOrCreate('D1');
        $d2Id = $naveOrCreate('D2');
        $d3Id = $naveOrCreate('D3');
        $c7Id = $naveOrCreate('C7');

        $d1Cuadras = [];
        foreach (['1','2','3','4','5'] as $n) $d1Cuadras[] = $cuadraOrCreate($d1Id, $n);
        $d2Cuadras = [];
        foreach (['90','91','92','93'] as $n) $d2Cuadras[] = $cuadraOrCreate($d2Id, $n);
        $d3Cuadras = [];
        foreach (['90','91','92','93'] as $n) $d3Cuadras[] = $cuadraOrCreate($d3Id, $n);
        $c7Cuadras = [];
        foreach (['11','21','31','41','51','61','71','81'] as $n) $c7Cuadras[] = $cuadraOrCreate($c7Id, $n);

        // Generar todos los jueves desde 2025-12-01 hasta hoy (no se
        // generan lotes con fecha en el futuro).
        $thursdays = [];
        $cur = new \DateTime('2025-12-01');
        $end = new \DateTime('today');
        while ($cur <= $end) {
            if ((int)$cur->format('N') === 4) $thursdays[] = $cur->format('Y-m-d');
            $cur->modify('+1 day');
        }

        // Estados de cada cuadra (índice → ['lote_id'=>X,'fecha'=>Y,'num_animales'=>N] o null)
        $d1State = array_fill(0, 5, null);
        $d2State = array_fill(0, 4, null);
        $d3State = array_fill(0, 4, null);
        $c7State = array_fill(0, 8, null);

        $loteModel   = new \App\Models\Lote();
        $pesajeModel = new \App\Models\Pesaje();
        $movModel    = new \App\Models\Movimiento();

        $findFree   = fn(array $st) => array_key_first(array_filter($st, fn($v) => $v === null)) ?? null;
        $findOldest = function(array $st) {
            $oldestIdx = null; $oldestFecha = null;
            foreach ($st as $i => $v) {
                if ($v === null) continue;
                if ($oldestFecha === null || $v['fecha'] < $oldestFecha) {
                    $oldestIdx = $i; $oldestFecha = $v['fecha'];
                }
            }
            return $oldestIdx;
        };

        $hechos = ['lotes' => 0, 'movimientos' => 0, 'omitidos' => 0];

        // Determinismo razonable
        mt_srand(20260507);

        foreach ($thursdays as $thursday) {
            // ── 1) Liberar D1 si está lleno (mover oldest D1 → D2/D3/C7) ──
            if ($findFree($d1State) === null) {
                $oldestD1 = $findOldest($d1State);
                $loteOldest = $d1State[$oldestD1];

                // Buscar destino: D2 → D3 → C7
                $tIdx = $findFree($d2State);
                $tCuadras = $d2Cuadras; $tNave = 'D2'; $tNaveId = $d2Id; $tStateRef = 'd2';
                if ($tIdx === null) {
                    $tIdx = $findFree($d3State);
                    $tCuadras = $d3Cuadras; $tNave = 'D3'; $tNaveId = $d3Id; $tStateRef = 'd3';
                }
                if ($tIdx === null) {
                    $tIdx = $findFree($c7State);
                    $tCuadras = $c7Cuadras; $tNave = 'C7'; $tNaveId = $c7Id; $tStateRef = 'c7';
                }

                if ($tIdx !== null) {
                    $wedDate = (new \DateTime($thursday))->modify('-1 day')->format('Y-m-d');
                    $cuadraOrigen  = $d1Cuadras[$oldestD1];
                    $cuadraDestino = $tCuadras[$tIdx];

                    // Crear movimiento traslado_cuadra
                    $movModel->create([
                        'tipo'              => 'traslado_cuadra',
                        'fecha'             => $wedDate,
                        'lote_origen_id'    => $loteOldest['lote_id'],
                        'lote_destino_id'   => null,
                        'cuadra_origen_id'  => $cuadraOrigen,
                        'cuadra_destino_id' => $cuadraDestino,
                        'num_animales'      => $loteOldest['num_animales'],
                        'peso_canal_kg'     => null,
                        'peso_real_kg'      => null,
                        'precio_eur'        => null,
                        'tipo_venta'        => null,
                        'motivo_baja'       => null,
                        'observaciones'     => "Traslado D1 → {$tNave} (test data)",
                        'albaran_archivo'   => null,
                    ], $uid);
                    $hechos['movimientos']++;

                    // Mover en cuadra_lote
                    $db->prepare("UPDATE cuadra_lote SET activo=0, num_animales=0 WHERE cuadra_id=:c AND lote_id=:l")
                       ->execute(['c' => $cuadraOrigen, 'l' => $loteOldest['lote_id']]);
                    $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada, activo) VALUES (:c,:l,:n,:f,1)")
                       ->execute(['c' => $cuadraDestino, 'l' => $loteOldest['lote_id'], 'n' => $loteOldest['num_animales'], 'f' => $wedDate]);

                    // Actualizar nave del lote
                    $db->prepare("UPDATE lotes SET nave_id = :nv WHERE id = :id")
                       ->execute(['nv' => $tNaveId, 'id' => $loteOldest['lote_id']]);

                    // Actualizar estado en memoria
                    if ($tStateRef === 'd2') $d2State[$tIdx] = $loteOldest;
                    elseif ($tStateRef === 'd3') $d3State[$tIdx] = $loteOldest;
                    else $c7State[$tIdx] = $loteOldest;
                    $d1State[$oldestD1] = null;
                } else {
                    // Todo lleno — saltar este destete
                    $hechos['omitidos']++;
                    continue;
                }
            }

            // ── 2) Crear lote de destete del jueves ──
            $numAnimales = mt_rand(401, 600);
            $pesoIndividual = 7.0; // kg/animal típico al destete
            $pesoTotal = round($numAnimales * $pesoIndividual, 3);

            // Generar código L WW/YY (ISO week + año 2 dígitos)
            $codigoBase = \App\Models\Lote::generarCodigo($thursday);
            $codigo = $codigoBase;
            $sufijo = 2;
            while ($loteModel->codigoExisteSimple($codigo)) {
                $codigo = $codigoBase . "-{$sufijo}";
                $sufijo++;
            }

            $loteId = $loteModel->create([
                'nave_id'         => $d1Id,
                'granja_id'       => $granjaId,
                'tipo_animal_id'  => $tipoAnimalId,
                'raza_id'         => $razaId ? (int)$razaId : null,
                'codigo'          => $codigo,
                'num_animales'    => $numAnimales,
                'peso_entrada_kg' => $pesoTotal,
                'fecha_entrada'   => $thursday,
                'fecha_nacimiento'=> $thursday,
                'observaciones'   => 'Lote de destete (test data)',
            ]);
            $hechos['lotes']++;

            // Auto-pesaje al alta
            $pesajeModel->create([
                'lote_id'              => $loteId,
                'cuadra_id'            => null,
                'fecha'                => $thursday,
                'peso_medio_kg'        => $pesoIndividual,
                'num_animales_pesados' => $numAnimales,
                'consumo_pienso_kg'    => null,
                'ic_real'              => null,
                'observaciones'        => 'Pesaje automático al alta del lote',
                'usuario_id'           => $uid,
            ]);

            // Asignar a primera cuadra D1 libre
            $freeIdx = $findFree($d1State);
            if ($freeIdx === null) { $hechos['omitidos']++; continue; }

            $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada, activo) VALUES (:c,:l,:n,:f,1)")
               ->execute(['c' => $d1Cuadras[$freeIdx], 'l' => $loteId, 'n' => $numAnimales, 'f' => $thursday]);

            // Movimiento destete
            $movModel->create([
                'tipo'              => 'destete',
                'fecha'             => $thursday,
                'lote_origen_id'    => $loteId,
                'lote_destino_id'   => null,
                'cuadra_origen_id'  => null,
                'cuadra_destino_id' => null,
                'num_animales'      => $numAnimales,
                'peso_canal_kg'     => null,
                'peso_real_kg'      => null,
                'precio_eur'        => null,
                'tipo_venta'        => null,
                'motivo_baja'       => null,
                'observaciones'     => 'Destete: alta del lote (test)',
                'albaran_archivo'   => null,
            ], $uid);
            $hechos['movimientos']++;

            $d1State[$freeIdx] = ['lote_id' => $loteId, 'fecha' => $thursday, 'num_animales' => $numAnimales];
        }

        Session::flash('success', sprintf(
            'Generados: %d lotes, %d movimientos. Saltados (sin sitio): %d.',
            $hechos['lotes'], $hechos['movimientos'], $hechos['omitidos']
        ));
        $this->redirect('configuracion/seed-test');
    }

    /**
     * Genera bajas aleatorias en los lotes existentes.
     * - Lotes "outlier" (10% de probabilidad) → mortalidad 6-8% (resaltan en informes)
     * - Resto → mortalidad 1.5-2.5%
     * - 3 a 7 eventos de baja por lote, distribuidos entre fecha_entrada y hoy
     * - Motivos aleatorios entre los configurados en motivos_baja
     */
    public function seedBajasRun(): void
    {
        auth_required();
        require_rol('admin');
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('configuracion/seed-test');
        }
        if ($this->postString('confirmacion') !== 'BAJAS') {
            Session::flash('error', 'Confirmación incorrecta. Escribe BAJAS.');
            $this->redirect('configuracion/seed-test');
        }

        $uid = (int) Session::get('usuario_id');
        $db  = \App\Core\Database::getInstance();

        // Lotes del usuario con animales > 0
        $stmt = $db->prepare("
            SELECT l.id, l.codigo, l.num_animales_entrada, l.num_animales, l.fecha_entrada, l.estado, l.fecha_cierre
            FROM lotes l
            JOIN granjas g ON l.granja_id = g.id
            WHERE g.usuario_id = :uid AND l.num_animales > 0 AND l.fecha_entrada IS NOT NULL
            ORDER BY l.fecha_entrada
        ");
        $stmt->execute(['uid' => $uid]);
        $lotes = $stmt->fetchAll();

        if (empty($lotes)) {
            Session::flash('error', 'No hay lotes a los que añadir bajas.');
            $this->redirect('configuracion/seed-test');
        }

        // Motivos de baja activos
        $motivosBd = $db->query("SELECT codigo FROM motivos_baja WHERE activo = 1")->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($motivosBd)) $motivosBd = ['enfermedad', 'sacrificio', 'otro'];

        $movModel = new \App\Models\Movimiento();
        mt_srand(20260507);

        $hechos = ['lotes_afectados' => 0, 'eventos' => 0, 'animales' => 0, 'outliers' => 0];

        foreach ($lotes as $l) {
            $entrada = (int)$l['num_animales_entrada'];
            $actual  = (int)$l['num_animales'];
            if ($entrada <= 0 || $actual <= 0) continue;

            // ¿Es un outlier?
            $isOutlier = (mt_rand(1, 10) === 1);
            // Mortalidad objetivo: outlier 6-8% / resto 1.5-2.5%
            $pct = $isOutlier
                ? mt_rand(60, 80) / 1000.0   // 0.060 - 0.080
                : mt_rand(15, 25) / 1000.0;  // 0.015 - 0.025
            $targetBajas = (int) round($entrada * $pct);
            // No puede pasar de los animales que quedan
            $targetBajas = min($targetBajas, $actual);
            if ($targetBajas <= 0) continue;

            // Rango de fechas: desde fecha_entrada (excl. el día mismo) hasta
            // hoy (o fecha_cierre si está cerrado, lo que sea menor).
            $startTs = strtotime($l['fecha_entrada']);
            $endTs   = strtotime('today');
            if ($l['estado'] === 'cerrado' && !empty($l['fecha_cierre'])) {
                $endTs = min($endTs, strtotime($l['fecha_cierre']));
            }
            if ($startTs >= $endTs) continue;

            // Número de eventos repartidos
            $numEventos = max(1, min(7, (int) round($targetBajas / 3)));
            $fechas = [];
            for ($i = 0; $i < $numEventos; $i++) {
                $fechas[] = $startTs + mt_rand(86400, max(86400, $endTs - $startTs));
            }
            sort($fechas);

            $remaining = $targetBajas;
            foreach ($fechas as $j => $ts) {
                if ($remaining <= 0) break;
                $isLast = ($j === count($fechas) - 1);
                // Reparto: las primeras un poco más para que el último cierre exacto
                $maxEvento = $isLast ? $remaining : (int) ceil($remaining / (count($fechas) - $j));
                $cantidad = $isLast ? $remaining : mt_rand(1, max(1, $maxEvento));
                $cantidad = min($cantidad, $remaining);
                if ($cantidad <= 0) continue;

                $fecha   = date('Y-m-d', $ts);
                $motivo  = $motivosBd[array_rand($motivosBd)];

                // Cuadra activa actual del lote
                $stmtC = $db->prepare("SELECT cuadra_id FROM cuadra_lote WHERE lote_id = :l AND activo = 1 ORDER BY id DESC LIMIT 1");
                $stmtC->execute(['l' => $l['id']]);
                $cuadraId = $stmtC->fetchColumn() ?: null;

                // Crear movimiento de baja
                $movModel->create([
                    'tipo'              => 'baja',
                    'fecha'             => $fecha,
                    'lote_origen_id'    => (int)$l['id'],
                    'lote_destino_id'   => null,
                    'cuadra_origen_id'  => $cuadraId ? (int)$cuadraId : null,
                    'cuadra_destino_id' => null,
                    'num_animales'      => $cantidad,
                    'peso_canal_kg'     => null,
                    'peso_real_kg'      => null,
                    'precio_eur'        => null,
                    'tipo_venta'        => null,
                    'motivo_baja'       => $motivo,
                    'observaciones'     => 'Baja (test data)' . ($isOutlier ? ' — lote outlier' : ''),
                    'albaran_archivo'   => null,
                ], $uid);
                $hechos['eventos']++;
                $hechos['animales'] += $cantidad;

                // Aplicar efecto: descontar de lote y cuadra
                $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $l['id']]);
                if ($cuadraId) {
                    $db->prepare("UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n) WHERE cuadra_id=:c AND lote_id=:l AND activo=1")
                       ->execute(['n' => $cantidad, 'c' => $cuadraId, 'l' => $l['id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo = 0 WHERE cuadra_id=:c AND lote_id=:l AND num_animales = 0")
                       ->execute(['c' => $cuadraId, 'l' => $l['id']]);
                }

                $remaining -= $cantidad;
            }

            $hechos['lotes_afectados']++;
            if ($isOutlier) $hechos['outliers']++;
        }

        Session::flash('success', sprintf(
            'Bajas generadas: %d eventos en %d lotes (%d outliers con %%alto), %d animales en total.',
            $hechos['eventos'], $hechos['lotes_afectados'], $hechos['outliers'], $hechos['animales']
        ));
        $this->redirect('configuracion/seed-test');
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