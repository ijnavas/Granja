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

        // Raza 50% Ibérico (sin identificador, así el código del lote no
        // lleva sufijo). Si no existe, la crea.
        $stmt = $db->prepare("
            SELECT id FROM razas_porcino
            WHERE LOWER(CONCAT(nombre, ' ', COALESCE(porcentaje, ''))) LIKE '%iber%50%'
               OR LOWER(CONCAT(nombre, ' ', COALESCE(porcentaje, ''))) LIKE '%50%iber%'
            ORDER BY id LIMIT 1
        ");
        $stmt->execute();
        $razaId = $stmt->fetchColumn();
        if (!$razaId) {
            $db->prepare("
                INSERT INTO razas_porcino (usuario_id, nombre, porcentaje, identificador)
                VALUES (NULL, '50% Ibérico', '50%', NULL)
            ")->execute();
            $razaId = (int) $db->lastInsertId();
        }
        $razaId = (int) $razaId;

        // Helpers: prefiere naves/cuadras ACTIVAS (activa=1). Si solo existe
        // una soft-deleted con el mismo nombre, la reactiva en lugar de
        // crear una duplicada o de seguir usando una invisible.
        $naveOrCreate = function(string $nombre) use ($db, $granjaId): int {
            // 1) ¿existe activa?
            $s = $db->prepare("SELECT id FROM naves WHERE granja_id = :g AND nombre = :n AND activa = 1 ORDER BY id LIMIT 1");
            $s->execute(['g' => $granjaId, 'n' => $nombre]);
            $id = $s->fetchColumn();
            if ($id) return (int)$id;
            // 2) ¿existe soft-deleted? Reactivarla
            $s = $db->prepare("SELECT id FROM naves WHERE granja_id = :g AND nombre = :n ORDER BY id LIMIT 1");
            $s->execute(['g' => $granjaId, 'n' => $nombre]);
            $id = $s->fetchColumn();
            if ($id) {
                $db->prepare("UPDATE naves SET activa = 1 WHERE id = :id")->execute(['id' => $id]);
                return (int)$id;
            }
            // 3) Crear nueva
            $db->prepare("INSERT INTO naves (granja_id, nombre, capacidad_maxima, especie, activa) VALUES (:g, :n, 5000, 'porcino', 1)")
               ->execute(['g' => $granjaId, 'n' => $nombre]);
            return (int)$db->lastInsertId();
        };
        $cuadraOrCreate = function(int $naveId, string $nombre) use ($db): int {
            $s = $db->prepare("SELECT id FROM cuadras WHERE nave_id = :n AND nombre = :nm AND activa = 1 ORDER BY id LIMIT 1");
            $s->execute(['n' => $naveId, 'nm' => $nombre]);
            $id = $s->fetchColumn();
            if ($id) return (int)$id;
            $s = $db->prepare("SELECT id FROM cuadras WHERE nave_id = :n AND nombre = :nm ORDER BY id LIMIT 1");
            $s->execute(['n' => $naveId, 'nm' => $nombre]);
            $id = $s->fetchColumn();
            if ($id) {
                $db->prepare("UPDATE cuadras SET activa = 1 WHERE id = :id")->execute(['id' => $id]);
                return (int)$id;
            }
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

        // Generar todos los jueves desde 2025-12-01 hasta hoy
        $thursdays = [];
        $cur = new \DateTime('2025-12-01');
        $end = new \DateTime('today');
        while ($cur <= $end) {
            if ((int)$cur->format('N') === 4) $thursdays[] = $cur->format('Y-m-d');
            $cur->modify('+1 day');
        }

        // Estados de cada cuadra: ['lote_id', 'fecha', 'num_animales'] o null
        // (cada slot = una cuadra, máximo un lote — sin compartir)
        $d1 = array_fill(0, 5, null);   // D1 cuadras 1-5
        $d2 = array_fill(0, 4, null);   // D2 cuadras 90-93
        $d3 = array_fill(0, 4, null);   // D3 cuadras 90-93
        $c7 = array_fill(0, 8, null);   // C7 cuadras 11, 21, ..., 81

        $loteModel   = new \App\Models\Lote();
        $pesajeModel = new \App\Models\Pesaje();
        $movModel    = new \App\Models\Movimiento();

        // Helpers
        $findFree   = function(array $st) {
            foreach ($st as $i => $v) if ($v === null) return $i;
            return null;
        };
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
        mt_srand(20260507);  // determinismo razonable

        // Función auxiliar para registrar un traslado entre cuadras
        $registrarTraslado = function(int $loteId, int $cuadraOrigen, int $cuadraDestino, int $naveDestinoId, int $num, string $fecha, string $obs) use ($db, $movModel, $uid, &$hechos) {
            try {
                $movModel->create([
                    'tipo'              => 'traslado_cuadra',
                    'fecha'             => $fecha,
                    'lote_origen_id'    => $loteId,
                    'lote_destino_id'   => null,
                    'cuadra_origen_id'  => $cuadraOrigen,
                    'cuadra_destino_id' => $cuadraDestino,
                    'num_animales'      => $num,
                    'peso_canal_kg'     => null,
                    'peso_real_kg'      => null,
                    'precio_eur'        => null,
                    'tipo_venta'        => null,
                    'motivo_baja'       => null,
                    'observaciones'     => $obs,
                    'albaran_archivo'   => null,
                ], $uid);
                $hechos['movimientos']++;

                $db->prepare("UPDATE cuadra_lote SET activo=0, num_animales=0 WHERE cuadra_id=:c AND lote_id=:l")
                   ->execute(['c' => $cuadraOrigen, 'l' => $loteId]);
                $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada, activo) VALUES (:c,:l,:n,:f,1)")
                   ->execute(['c' => $cuadraDestino, 'l' => $loteId, 'n' => $num, 'f' => $fecha]);
                $db->prepare("UPDATE lotes SET nave_id = :nv WHERE id = :id")
                   ->execute(['nv' => $naveDestinoId, 'id' => $loteId]);
            } catch (\Throwable $e) {
                error_log("Seeder traslado FAIL lote={$loteId} de={$cuadraOrigen} a={$cuadraDestino}: " . $e->getMessage());
                throw $e;
            }
        };

        foreach ($thursdays as $thursday) {
            // ─────────────────────────────────────────────────────
            // 1) JUEVES: nuevo destete en D1 (si hay sitio)
            // ─────────────────────────────────────────────────────
            $freeD1 = $findFree($d1);
            if ($freeD1 === null) {
                // D1 lleno: el viernes anterior no pudo limpiar (todo el sistema saturado)
                $hechos['omitidos']++;
                continue;
            }

            // ~400 ± 10% → 360-440 animales
            $numAnimales = mt_rand(360, 440);
            $pesoIndividual = 7.0;
            $pesoTotal = round($numAnimales * $pesoIndividual, 3);

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

            $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada, activo) VALUES (:c,:l,:n,:f,1)")
               ->execute(['c' => $d1Cuadras[$freeD1], 'l' => $loteId, 'n' => $numAnimales, 'f' => $thursday]);

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

            $d1[$freeD1] = ['lote_id' => $loteId, 'fecha' => $thursday, 'num_animales' => $numAnimales];

            // ─────────────────────────────────────────────────────
            // 2) VIERNES (jueves+1): si D1 está lleno, mover oldest
            //    D1 → D2/D3 (con cascada a C7 si esas también llenas)
            // ─────────────────────────────────────────────────────
            if ($findFree($d1) !== null) continue;  // aún hay sitio en D1, no hay que limpiar

            $friday = (new \DateTime($thursday))->modify('+1 day')->format('Y-m-d');
            $oldestD1Idx = $findOldest($d1);
            $oldestD1    = $d1[$oldestD1Idx];

            // Buscar slot libre en D2 o D3
            $targetNave   = null;   // 'd2' | 'd3'
            $targetIdx    = null;
            $targetCuadras = null;
            $targetNaveId  = null;

            $tmp = $findFree($d2);
            if ($tmp !== null) {
                $targetNave = 'd2'; $targetIdx = $tmp;
                $targetCuadras = $d2Cuadras; $targetNaveId = $d2Id;
            } else {
                $tmp = $findFree($d3);
                if ($tmp !== null) {
                    $targetNave = 'd3'; $targetIdx = $tmp;
                    $targetCuadras = $d3Cuadras; $targetNaveId = $d3Id;
                }
            }

            if ($targetNave === null) {
                // D2 y D3 llenos → cascada: mover oldest de D2/D3 → C7
                $c7Free = $findFree($c7);
                if ($c7Free === null) {
                    // C7 también lleno: nada se puede hacer, D1 queda lleno
                    continue;
                }

                // Encontrar el oldest entre D2 + D3
                $oldestSrc    = null;  // 'd2' | 'd3'
                $oldestSrcIdx = null;
                $oldestSrcDate = null;
                foreach ($d2 as $i => $v) {
                    if ($v && ($oldestSrcDate === null || $v['fecha'] < $oldestSrcDate)) {
                        $oldestSrc = 'd2'; $oldestSrcIdx = $i; $oldestSrcDate = $v['fecha'];
                    }
                }
                foreach ($d3 as $i => $v) {
                    if ($v && ($oldestSrcDate === null || $v['fecha'] < $oldestSrcDate)) {
                        $oldestSrc = 'd3'; $oldestSrcIdx = $i; $oldestSrcDate = $v['fecha'];
                    }
                }
                $oldestD23     = ($oldestSrc === 'd2') ? $d2[$oldestSrcIdx] : $d3[$oldestSrcIdx];
                $srcCuadras    = ($oldestSrc === 'd2') ? $d2Cuadras : $d3Cuadras;
                $srcCuadraId   = $srcCuadras[$oldestSrcIdx];

                // Trasladar oldest D2/D3 → C7
                $registrarTraslado(
                    $oldestD23['lote_id'], $srcCuadraId, $c7Cuadras[$c7Free],
                    $c7Id, $oldestD23['num_animales'], $friday,
                    'Traslado ' . strtoupper($oldestSrc) . ' → C7 (test)'
                );
                $c7[$c7Free] = $oldestD23;
                if ($oldestSrc === 'd2') $d2[$oldestSrcIdx] = null;
                else                     $d3[$oldestSrcIdx] = null;

                // El slot recién liberado en D2/D3 será el destino del oldest D1
                $targetNave    = $oldestSrc;
                $targetIdx     = $oldestSrcIdx;
                $targetCuadras = $srcCuadras;
                $targetNaveId  = ($oldestSrc === 'd2') ? $d2Id : $d3Id;
            }

            // Trasladar oldest D1 → target (D2 o D3)
            $registrarTraslado(
                $oldestD1['lote_id'], $d1Cuadras[$oldestD1Idx], $targetCuadras[$targetIdx],
                $targetNaveId, $oldestD1['num_animales'], $friday,
                'Traslado D1 → ' . strtoupper($targetNave) . ' (test)'
            );
            if ($targetNave === 'd2') $d2[$targetIdx] = $oldestD1;
            else                      $d3[$targetIdx] = $oldestD1;
            $d1[$oldestD1Idx] = null;
        }

        // Diagnóstico: contar lotes asignados a cada nave en el estado en memoria
        $countNotNull = fn($arr) => count(array_filter($arr));
        $diag = sprintf(
            'D1=%d/5, D2=%d/4, D3=%d/4, C7=%d/8',
            $countNotNull($d1), $countNotNull($d2), $countNotNull($d3), $countNotNull($c7)
        );

        // Diagnóstico real: contar lotes con cuadra_lote activo en cada nave (BD)
        $stmt = $db->prepare("
            SELECT n.nombre, COALESCE(COUNT(DISTINCT cl.lote_id), 0) AS num_lotes,
                   COALESCE(SUM(cl.num_animales), 0) AS total_anim
            FROM naves n
            LEFT JOIN cuadras c ON c.nave_id = n.id
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id AND cl.activo = 1
            WHERE n.id IN (:d1, :d2, :d3, :c7)
            GROUP BY n.id, n.nombre
            ORDER BY n.nombre
        ");
        $stmt->execute(['d1' => $d1Id, 'd2' => $d2Id, 'd3' => $d3Id, 'c7' => $c7Id]);
        $bdRows = $stmt->fetchAll();
        $bdStr  = implode(', ', array_map(fn($r) => "{$r['nombre']}=" . (int)$r['num_lotes'] . " lotes/" . (int)$r['total_anim'] . "anim", $bdRows));

        Session::flash('success', sprintf(
            'Generados: %d lotes, %d movimientos. Saltados: %d. <br>Memoria final: %s. <br>BD final: %s',
            $hechos['lotes'], $hechos['movimientos'], $hechos['omitidos'], $diag, $bdStr
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

        // Diagnóstico post-bajas: ¿siguen activos los cuadra_lote de C7?
        $stmt = $db->prepare("
            SELECT n.nombre, COALESCE(COUNT(DISTINCT cl.lote_id), 0) AS num_lotes,
                   COALESCE(SUM(cl.num_animales), 0) AS total_anim
            FROM naves n
            JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN cuadras c ON c.nave_id = n.id
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id AND cl.activo = 1
            WHERE g.usuario_id = :uid AND n.activa = 1 AND n.nombre IN ('D1','D2','D3','C7')
            GROUP BY n.id, n.nombre
            ORDER BY n.nombre
        ");
        $stmt->execute(['uid' => $uid]);
        $diag = implode(', ', array_map(fn($r) => "{$r['nombre']}=" . (int)$r['num_lotes'] . " lotes/" . (int)$r['total_anim'] . "anim", $stmt->fetchAll()));

        Session::flash('success', sprintf(
            'Bajas: %d eventos en %d lotes (%d outliers), %d animales. <br>BD post-bajas: %s',
            $hechos['eventos'], $hechos['lotes_afectados'], $hechos['outliers'], $hechos['animales'], $diag
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