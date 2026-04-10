<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Lote;
use App\Models\Nave;
use App\Models\Granja;
use App\Models\RazaPorcino;
use App\Core\Session;
use App\Core\Paginator;
use App\Core\AuditLog;

class LoteController extends BaseController
{
    private Lote        $model;
    private Nave        $naveModel;
    private Granja      $granjaModel;
    private RazaPorcino $razaModel;

    public function __construct()
    {
        $this->model       = new Lote();
        $this->naveModel   = new Nave();
        $this->granjaModel = new Granja();
        $this->razaModel   = new RazaPorcino();
    }

    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->model->actualizarEstadoLechonACebo($uid);

        $filtros = array_filter([
            'estado'    => trim($_GET['estado']    ?? ''),
            'granja_id' => trim($_GET['granja_id'] ?? ''),
            'raza_id'   => trim($_GET['raza_id']   ?? ''),
            'codigo'    => trim($_GET['codigo']    ?? ''),
        ]);

        $page       = max(1, (int)($_GET['page'] ?? 1));
        $total      = $this->model->countByUsuario($uid, $filtros);
        $paginacion = new Paginator($total, $page, 50);

        $this->view('lotes/index', [
            'lotes'      => $this->model->allByUsuario($uid, $filtros, $paginacion->perPage, $paginacion->offset),
            'filtros'    => $filtros,
            'paginacion' => $paginacion,
            'granjas'    => $this->granjaModel->selectOptions($uid),
            'razas'      => $this->razaModel->allParaUsuario($uid),
            'pageTitle'  => 'Lotes',
        ]);
    }

    public function create(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        $granjas = $this->granjaModel->selectOptions($uid);

        // Preseleccionar granja si solo hay una
        $granjaPreseleccionada = count($granjas) === 1 ? $granjas[0]['id'] : null;

        $tiposPorGranja = [];
        foreach ($granjas as $g) {
            if ($g['especie']) {
                $tiposPorGranja[$g['id']] = $this->model->tipoAnimalParaGranja($g['especie'], $g['tipo_produccion'] ?? null);
            }
        }

        $this->view('lotes/form', [
            'lote'                 => null,
            'naves'                => $this->naveModel->selectOptions($uid),
            'granjas'              => $granjas,
            'granjaPreseleccionada'=> $granjaPreseleccionada,
            'tipos'                => $this->model->tiposAnimal(),
            'tiposPorGranja'       => $tiposPorGranja,
            'razas'                => $this->razaModel->allParaUsuario($uid),
            'cuadrasAsig'          => [],
            'cuadrasDelLote'       => [],
            'historialMovimientos' => [],
            'pageTitle'            => 'Nuevo lote',
            'codigoAuto'           => '',
            'error'                => Session::getFlash('error'),
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('lotes/crear');
        }

        $uid = Session::get('usuario_id');

        $fechaNac = $this->postString('fecha_nacimiento');
        if (!$fechaNac) {
            Session::flash('error', 'La fecha de nacimiento es obligatoria.');
            $this->redirect('lotes/crear');
        }

        $razaId  = $this->post('raza_id') ? (int)$this->post('raza_id') : null;
        $codigoManual = trim($this->postString('codigo_manual'));
        $codigo = !empty($codigoManual) ? $codigoManual : Lote::generarCodigo($fechaNac, $razaId ? $this->razaModel->sufijoCodigo($razaId) : null);

        if ($this->model->codigoExisteSimple($codigo)) {
            $sufijo = 2;
            while ($this->model->codigoExisteSimple($codigo . "-{$sufijo}")) $sufijo++;
            $codigo .= "-{$sufijo}";
        }

        // Soporta multiselect (nave_ids[]) en creación y campo único (nave_id) en edición
        $naveIdsSel = $_POST['nave_ids'] ?? null;
        if ($naveIdsSel && is_array($naveIdsSel)) {
            $naveId = !empty($naveIdsSel[0]) ? (int)$naveIdsSel[0] : null;
        } else {
            $naveId = $this->post('nave_id') ?: null;
        }
        $granjaId = $this->post('granja_id') ?: null;

        // ── IDOR guard: granja y nave deben pertenecer al usuario ───
        $naveId   = $this->guardOwnedNave($naveId ? (int)$naveId : null, $uid);
        $granjaId = $this->guardOwnedGranja($granjaId ? (int)$granjaId : null, $uid);

        $datosNuevo = [
            'nave_id'          => $naveId,
            'granja_id'        => $granjaId,
            'tipo_animal_id'   => (int)$this->post('tipo_animal_id'),
            'raza_id'          => $razaId,
            'codigo'           => $codigo,
            'num_animales'     => (int)$this->post('num_animales', 0),
            'peso_entrada_kg'  => (float)$this->post('peso_entrada_kg', 0),
            'fecha_entrada'    => $this->postString('fecha_entrada') ?: date('Y-m-d'),
            'fecha_nacimiento' => $fechaNac,
            'observaciones'    => $this->postString('observaciones'),
        ];
        $this->model->create($datosNuevo);

        $loteId = (int)\App\Core\Database::getInstance()->lastInsertId();
        AuditLog::log('lote', $loteId, 'create', null, $datosNuevo);

        // Asignar cuadras si se distribuyó
        $cuadrasIds  = $_POST['cuadras_asig_id']  ?? [];
        $cuadrasNums = $_POST['cuadras_asig_num'] ?? [];
        if (!empty($cuadrasIds)) {
            $cuadraModel = new \App\Models\Cuadra();
            foreach ($cuadrasIds as $i => $cuadraId) {
                $num = (int)($cuadrasNums[$i] ?? 0);
                if ($num <= 0) continue;
                // IDOR guard: la cuadra debe ser del usuario
                if (!$cuadraModel->find((int)$cuadraId, $uid)) continue;
                $cuadraModel->asignarLote((int)$cuadraId, (int)$loteId, $num, date('Y-m-d'));
            }
        }

        Session::flash('success', "Lote <strong>{$codigo}</strong> creado correctamente.");
        $this->redirect('lotes');
    }

    public function edit(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $lote = $this->model->find((int)$id, $uid);
        if (!$lote) $this->redirect('lotes');

        $granjas = $this->granjaModel->selectOptions($uid);
        $tiposPorGranja = [];
        foreach ($granjas as $g) {
            if ($g['especie']) {
                $tiposPorGranja[$g['id']] = $this->model->tipoAnimalParaGranja($g['especie'], $g['tipo_produccion'] ?? null);
            }
        }

        // Cuadras ya asignadas al lote
        $cuadraModel    = new \App\Models\Cuadra();
        $cuadrasDelLote = $cuadraModel->cuadrasDelLote((int)$id);
        $cuadrasAsig    = array_column($cuadrasDelLote, 'num_animales', 'cuadra_id');

        // Historial de movimientos del lote
        $db = \App\Core\Database::getInstance();
        $stmtMov = $db->prepare("
            SELECT m.*, m.fecha,
                   lo.codigo AS lote_origen_codigo,
                   ld.codigo AS lote_destino_codigo,
                   co.nombre AS cuadra_origen_nombre,
                   cd.nombre AS cuadra_destino_nombre,
                   no.nombre AS nave_origen_nombre,
                   nd.nombre AS nave_destino_nombre,
                   u.nombre  AS usuario_nombre
            FROM movimientos m
            JOIN lotes lo ON m.lote_origen_id = lo.id
            LEFT JOIN lotes ld   ON m.lote_destino_id  = ld.id
            LEFT JOIN cuadras co ON m.cuadra_origen_id  = co.id
            LEFT JOIN cuadras cd ON m.cuadra_destino_id = cd.id
            LEFT JOIN naves no   ON co.nave_id = no.id
            LEFT JOIN naves nd   ON cd.nave_id = nd.id
            JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.lote_origen_id = :id OR m.lote_destino_id = :id2
            ORDER BY m.fecha DESC, m.created_at DESC
            LIMIT 20
        ");
        $stmtMov->execute(['id' => (int)$id, 'id2' => (int)$id]);
        $historialMovimientos = $stmtMov->fetchAll();

        $this->view('lotes/form', [
            'lote'                 => $lote,
            'naves'                => $this->naveModel->selectOptions($uid),
            'granjas'              => $granjas,
            'granjaPreseleccionada'=> null,
            'tipos'                => $this->model->tiposAnimal(),
            'tiposPorGranja'       => $tiposPorGranja,
            'razas'                => $this->razaModel->allParaUsuario($uid),
            'cuadrasAsig'          => $cuadrasAsig,
            'cuadrasDelLote'       => $cuadrasDelLote,
            'historialMovimientos' => $historialMovimientos,
            'pageTitle'            => 'Editar lote ' . $lote['codigo'],
            'codigoAuto'           => $lote['codigo'],
            'error'                => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("lotes/{$id}/editar");
        }

        $uid = Session::get('usuario_id');

        // El propio lote debe pertenecer al usuario
        $antes = $this->model->find((int)$id, $uid);
        if (!$antes) {
            $this->redirect('lotes');
        }

        $naveId   = $this->post('nave_id') ?: null;
        $granjaId = $this->post('granja_id') ?: null;
        $codigoManual = trim($this->postString('codigo_manual'));

        // ── IDOR guard: granja y nave deben pertenecer al usuario ───
        $naveId   = $this->guardOwnedNave($naveId ? (int)$naveId : null, $uid);
        $granjaId = $this->guardOwnedGranja($granjaId ? (int)$granjaId : null, $uid);

        $datosUpdate = [
            'nave_id'          => $naveId,
            'granja_id'        => $granjaId,
            'tipo_animal_id'   => (int)$this->post('tipo_animal_id'),
            'raza_id'          => $this->post('raza_id') ? (int)$this->post('raza_id') : null,
            'codigo'           => !empty($codigoManual) ? $codigoManual : null,
            'num_animales'     => (int)$this->post('num_animales', 0),
            'peso_entrada_kg'  => (float)$this->post('peso_entrada_kg', 0),
            'fecha_entrada'    => date('Y-m-d'),
            'fecha_nacimiento' => $this->postString('fecha_nacimiento') ?: null,
            'observaciones'    => $this->postString('observaciones'),
        ];
        $this->model->update((int)$id, $uid, $datosUpdate);
        $despues = $this->model->find((int)$id, $uid);
        AuditLog::log('lote', (int)$id, 'update', $antes, $despues);

        // Sync cuadras: borrar asignaciones anteriores y crear las nuevas
        $cuadrasIds  = $_POST['cuadras_asig_id']  ?? [];
        $cuadrasNums = $_POST['cuadras_asig_num'] ?? [];
        if (!empty($cuadrasIds)) {
            $db = \App\Core\Database::getInstance();
            // Borrar asignaciones activas del lote
            $db->prepare("UPDATE cuadra_lote SET activo = 0 WHERE lote_id = :lid")
               ->execute(['lid' => (int)$id]);
            // Crear las nuevas, validando ownership de cada cuadra
            $cuadraModel = new \App\Models\Cuadra();
            foreach ($cuadrasIds as $i => $cuadraId) {
                $num = (int)($cuadrasNums[$i] ?? 0);
                if ($num <= 0) continue;
                if (!$cuadraModel->find((int)$cuadraId, $uid)) continue;
                $cuadraModel->asignarLote((int)$cuadraId, (int)$id, $num, date('Y-m-d'));
            }
        }

        Session::flash('success', 'Lote actualizado.');
        $this->redirect('lotes');
    }

    public function ajustar(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('lotes');
        }
        $uid = Session::get('usuario_id');
        // IDOR guard: el lote debe pertenecer al usuario
        $antes = $this->model->find((int)$id, $uid);
        if (!$antes) {
            $this->redirect('lotes');
        }
        $cantidad = abs((int)$this->post('cantidad', 0));
        $tipo     = $this->postString('tipo');
        if ($cantidad > 0 && in_array($tipo, ['añadir', 'reducir'])) {
            $this->model->ajustarAnimales((int)$id, $cantidad, $tipo);
            $despues = $this->model->find((int)$id, $uid);
            AuditLog::log('lote', (int)$id, 'ajustar', $antes, $despues);
            $accion = $tipo === 'añadir' ? 'añadidos' : 'reducidos';
            Session::flash('success', "{$cantidad} animales {$accion} correctamente.");
        }
        $this->redirect('lotes');
    }

    // ── Helpers de autorización (IDOR) ───────────────────────────
    /**
     * Devuelve $naveId si pertenece al usuario; null en otro caso.
     */
    private function guardOwnedNave(?int $naveId, int $uid): ?int
    {
        if (!$naveId) return null;
        return $this->naveModel->find($naveId, $uid) ? $naveId : null;
    }

    /**
     * Devuelve $granjaId si pertenece al usuario; null en otro caso.
     */
    private function guardOwnedGranja(?int $granjaId, int $uid): ?int
    {
        if (!$granjaId) return null;
        return $this->granjaModel->find($granjaId, $uid) ? $granjaId : null;
    }

    // ── Crear raza personalizada (AJAX) ──────────────────────────
    public function tablaSemana(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $razaId = (int)($_GET['raza_id'] ?? 0);
        $semana = (int)($_GET['semana']  ?? 0);

        if (!$razaId || $semana < 0) {
            echo json_encode(['ok' => false]);
            return;
        }

        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT tcl.peso_kg, tcl.coste_eur, tcl.consumo_acumulado_g
            FROM tabla_raza tr
            JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            JOIN tablas_crecimiento_lineas tcl ON tcl.tabla_id = tc.id AND tcl.semana = :semana
            WHERE tr.raza_id = :raza_id
            LIMIT 1
        ");
        $stmt->execute(['raza_id' => $razaId, 'semana' => $semana]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['ok' => false]);
            return;
        }

        echo json_encode([
            'ok'      => true,
            'peso'    => $row['peso_kg'],
            'coste'   => $row['coste_eur'],
            'consumo' => $row['consumo_acumulado_g'],
        ]);
    }

    public function crearRaza(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid           = Session::get('usuario_id');
        $nombre        = trim($_POST['nombre'] ?? '');
        $porcentaje    = trim($_POST['porcentaje'] ?? '');
        $identificador = strtoupper(trim($_POST['identificador'] ?? ''));

        if (strlen($nombre) < 2) {
            echo json_encode(['ok' => false, 'msg' => 'Nombre demasiado corto']);
            return;
        }

        $id = $this->razaModel->create([
            'usuario_id'    => $uid,
            'nombre'        => capitalizar($nombre),
            'porcentaje'    => $porcentaje ?: null,
            'identificador' => $identificador ?: null,
        ]);

        echo json_encode([
            'ok'            => true,
            'id'            => $id,
            'nombre'        => capitalizar($nombre),
            'porcentaje'    => $porcentaje ?: null,
            'identificador' => $identificador ?: null,
        ]);
    }

    public function delete(string $id): void
    {
        auth_required();
        // CSRF: el Router ya valida en POST, pero defensa en profundidad.
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('lotes');
        }
        $uid = Session::get('usuario_id');
        $antes = $this->model->find((int)$id, $uid);
        // cerrar() ya filtra por usuario_id en el WHERE; aquí solo redirigimos.
        $this->model->cerrar((int)$id, $uid);
        if ($antes) {
            AuditLog::log('lote', (int)$id, 'cerrar', $antes, null);
        }
        Session::flash('success', 'Lote cerrado y guardado en histórico.');
        $this->redirect('lotes');
    }

    public function borrar(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('lotes');
        }
        try {
            $uid   = Session::get('usuario_id');
            $antes = $this->model->find((int)$id, $uid);
            $ok    = $this->model->eliminarCompleto((int)$id, $uid);
            if ($ok) {
                AuditLog::log('lote', (int)$id, 'delete', $antes, null);
                Session::flash('success', 'Lote eliminado junto con todos sus movimientos, pesajes y cuadras.');
            } else {
                Session::flash('error', 'No se encontró el lote o no tienes permisos para eliminarlo.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al eliminar: ' . $e->getMessage());
        }
        $this->redirect('lotes');
    }

    public function historico(): void
    {
        auth_required();
        $lotes = $this->model->allCerradosByUsuario(Session::get('usuario_id'));
        $this->view('lotes/historico', ['lotes' => $lotes, 'pageTitle' => 'Histórico de lotes']);
    }

    public function historicoShow(string $id): void
    {
        auth_required();
        $lote = $this->model->historicoDetalle((int)$id, Session::get('usuario_id'));
        if (!$lote) $this->redirect('lotes/historico');
        $this->view('lotes/historico_show', ['lote' => $lote, 'pageTitle' => 'Histórico · ' . e($lote['codigo'])]);
    }
}