<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Movimiento;
use App\Models\Lote;
use App\Models\Nave;
use App\Models\Cuadra;
use App\Models\TipoMovimiento;
use App\Models\MotivoBaja;
use App\Models\ConfiguracionGranja;
use App\Models\Etiqueta;
use App\Core\Session;
use App\Core\Paginator;
use App\Core\AuditLog;
use PDO;

class MovimientoController extends BaseController
{
    private Movimiento          $model;
    private Lote                $loteModel;
    private Nave                $naveModel;
    private Cuadra              $cuadraModel;
    private TipoMovimiento      $tipoMovModel;
    private MotivoBaja          $motivoModel;
    private ConfiguracionGranja $configModel;

    public function __construct()
    {
        $this->model        = new Movimiento();
        $this->loteModel    = new Lote();
        $this->naveModel    = new Nave();
        $this->cuadraModel  = new Cuadra();
        $this->tipoMovModel = new TipoMovimiento();
        $this->motivoModel  = new MotivoBaja();
        $this->configModel  = new ConfiguracionGranja();
    }

    // ── Listado ──────────────────────────────────────────────────
    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        $filtros = array_filter([
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'tipo'        => trim($_GET['tipo']        ?? ''),
            'lote'        => trim($_GET['lote']        ?? ''),
        ]);

        $page       = max(1, (int)($_GET['page'] ?? 1));
        $total      = $this->model->countByUsuario($uid, $filtros);
        $paginacion = new Paginator($total, $page, 50);

        $movimientos = $this->model->allByUsuario($uid, $filtros, $paginacion->perPage, $paginacion->offset);
        $tagsByMov   = (new Etiqueta())->indexedByMovimientoIds(array_column($movimientos, 'id'));

        $this->view('movimientos/index', [
            'movimientos' => $movimientos,
            'tagsByMov'   => $tagsByMov,
            'filtros'     => $filtros,
            'paginacion'  => $paginacion,
            'tipos'       => $this->tipoMovModel->all(false),
            'pageTitle'   => 'Movimientos',
            'success'     => Session::getFlash('success'),
            'error'       => Session::getFlash('error'),
        ]);
    }

    public function export(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        $filtros = array_filter([
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'tipo'        => trim($_GET['tipo']        ?? ''),
            'lote'        => trim($_GET['lote']        ?? ''),
        ]);

        $movimientos = $this->model->allByUsuario($uid, $filtros);

        $etiquetas = [
            'traslado_cuadra'    => 'Traslado cuadra',
            'entrada_cebo'       => 'Entrada cebo',
            'entrada_reposicion' => 'Reposicion',
            'entrada_madres'     => 'Entrada madres',
            'venta'              => 'Venta',
            'baja'               => 'Baja',
        ];

        $rows = [];
        foreach ($movimientos as $m) {
            $rows[] = [
                date('d/m/Y', strtotime($m['fecha'])),
                $etiquetas[$m['tipo']] ?? $m['tipo'],
                $m['lote_origen_codigo'],
                $m['lote_destino_codigo'] ?? '',
                $m['num_animales'],
                $m['nave_origen_nombre'] ?? '',
                $m['cuadra_origen_nombre'] ?? '',
                $m['nave_destino_nombre'] ?? '',
                $m['cuadra_destino_nombre'] ?? '',
                $m['peso_canal_kg'] ? \App\Core\CsvExport::num((float)$m['peso_canal_kg']) : '',
                $m['precio_eur'] ? \App\Core\CsvExport::num((float)$m['precio_eur']) : '',
                $m['tipo_venta'] ?? '',
                $m['motivo_baja'] ?? '',
                $m['usuario_nombre'],
                $m['observaciones'] ?? '',
            ];
        }

        \App\Core\CsvExport::download(
            'movimientos_' . date('Y-m-d') . '.csv',
            ['Fecha', 'Tipo', 'Lote origen', 'Lote destino', 'Animales', 'Nave origen', 'Cuadra origen', 'Nave destino', 'Cuadra destino', 'Peso canal (kg)', 'Precio (EUR)', 'Tipo venta', 'Motivo baja', 'Usuario', 'Observaciones'],
            $rows
        );
    }

    // ── Crear ────────────────────────────────────────────────────
    public function create(): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $tipo = $_GET['tipo'] ?? '';

        // Atajo: el "destete" es una alta de lote — el formulario es el de
        // nuevo lote, así que redirigimos. El controller de lote registra
        // automáticamente el movimiento al guardar cuando ?modo=destete.
        if ($tipo === 'destete') {
            $this->redirect('lotes/crear?modo=destete');
        }

        // Recuperar datos anteriores si hubo error de validación
        $old = $_SESSION['_old_input'] ?? null;
        unset($_SESSION['_old_input']);
        $oldNaveOrigen  = null;
        $oldNaveDestino = null;
        if ($old) {
            $db = \App\Core\Database::getInstance();
            if (!empty($old['cuadra_origen_id'])) {
                $s = $db->prepare("SELECT nave_id FROM cuadras WHERE id = :id");
                $s->execute(['id' => (int)$old['cuadra_origen_id']]);
                $oldNaveOrigen = $s->fetchColumn() ?: null;
            }
            if (!empty($old['cuadra_destino_id'])) {
                $s = $db->prepare("SELECT nave_id FROM cuadras WHERE id = :id");
                $s->execute(['id' => (int)$old['cuadra_destino_id']]);
                $oldNaveDestino = $s->fetchColumn() ?: null;
            }
        }

        $this->view('movimientos/form', [
            'movimiento'     => null,
            'tipo'           => $tipo,
            'lotes'          => $this->loteModel->allByUsuario($uid),
            'naves'          => $this->naveModel->allByUsuario($uid),
            'estados'        => $this->model->estadosAnimal(),
            'tipos'          => $this->tipoMovModel->all(true),
            'motivos'        => $this->motivoModel->all(true),
            'config'         => $this->configModel->get($uid),
            'pageTitle'      => 'Nuevo movimiento',
            'error'          => Session::getFlash('error'),
            'old'            => $old,
            'oldNaveOrigen'  => $oldNaveOrigen,
            'oldNaveDestino' => $oldNaveDestino,
        ]);
    }

    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('movimientos/crear');
        }

        $uid  = Session::get('usuario_id');
        $tipo = $this->postString('tipo');

        // ── IDOR guards: todos los IDs deben pertenecer al usuario ──
        $loteOrigenId    = (int)$this->post('lote_origen_id');
        if ($loteOrigenId && !$this->loteModel->find($loteOrigenId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Lote', 'target_id' => $loteOrigenId,
                'context' => 'movimientos/store',
            ]);
            Session::flash('error', 'Lote de origen no válido.');
            $this->redirect('movimientos/crear?tipo=' . $tipo);
        }
        $loteDestinoId   = $this->ownedIdOrNull($this->loteModel, (int)$this->post('lote_destino_id') ?: null);
        $cuadraOrigenId  = $this->ownedIdOrNull($this->cuadraModel, (int)$this->post('cuadra_origen_id') ?: null);
        $cuadraDestinoId = $this->ownedIdOrNull($this->cuadraModel, (int)$this->post('cuadra_destino_id') ?: null);

        // Múltiples cuadras origen (venta/baja multi-cuadra)
        $cuadrasOrigenIds  = $_POST['cuadras_origen_ids']  ?? [];
        $cuadrasOrigenNums = $_POST['cuadras_origen_nums'] ?? [];
        $cuadrasOrigen = [];
        foreach ($cuadrasOrigenIds as $i => $cid) {
            $num = (int)($cuadrasOrigenNums[$i] ?? 0);
            if (!$cid || $num <= 0) continue;
            // IDOR: cada cuadra debe ser del usuario
            $cidOk = $this->ownedIdOrNull($this->cuadraModel, (int)$cid);
            if (!$cidOk) continue;
            $cuadrasOrigen[] = ['cuadra_id' => $cidOk, 'num' => $num];
        }
        $totalAnimales = !empty($cuadrasOrigen)
            ? array_sum(array_column($cuadrasOrigen, 'num'))
            : (int)$this->post('num_animales');

        $data = [
            'tipo'              => $tipo,
            'fecha'             => $this->postString('fecha') ?: date('Y-m-d'),
            'lote_origen_id'    => $loteOrigenId,
            'lote_destino_id'   => $loteDestinoId,
            'cuadra_origen_id'  => $cuadraOrigenId,
            'cuadra_destino_id' => $cuadraDestinoId,
            'num_animales'      => $totalAnimales,
            'peso_canal_kg'     => $this->post('peso_canal_kg')     ? (float)$this->post('peso_canal_kg') : null,
            'peso_real_kg'      => $this->post('peso_real_kg')      ? (float)$this->post('peso_real_kg')  : null,
            'precio_eur'        => $this->post('precio_eur')        ? (float)$this->post('precio_eur')    : null,
            'tipo_venta'        => $this->post('tipo_venta')        ?: null,
            'motivo_baja'       => $this->postString('motivo_baja') ?: null,
            'observaciones'     => $this->postString('observaciones'),
            'albaran_archivo'   => $this->guardarAlbaran($_FILES['albaran_archivo'] ?? null),
            'cuadras_origen'    => $cuadrasOrigen,
        ];

        // Aplicar efectos del movimiento
        try {
            $this->aplicarMovimiento($tipo, $data, $uid);
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
            $_SESSION['_old_input'] = $_POST;   // conservar datos del formulario
            $this->redirect('movimientos/crear?tipo=' . $tipo);
        }

        $cuadrasOrigenGuardar = $data['cuadras_origen'];
        unset($data['cuadras_origen']);
        $movId = $this->model->create($data, $uid);
        AuditLog::log('movimiento', (int)$movId, 'create', null, $data + ['cuadras_origen' => $cuadrasOrigenGuardar]);

        // Sincronizar etiquetas
        $tagInput = $this->postString('etiquetas');
        if ($tagInput !== '') {
            (new Etiqueta())->syncForMovimiento((int)$movId, $uid, Etiqueta::parseInput($tagInput));
        }

        // Guardar cuadras de origen para poder revertir después
        if (!empty($cuadrasOrigenGuardar)) {
            $db = \App\Core\Database::getInstance();
            $stmtMC = $db->prepare("INSERT INTO movimiento_cuadras (movimiento_id, cuadra_id, num_animales) VALUES (:mid, :cid, :n)");
            foreach ($cuadrasOrigenGuardar as $co) {
                $stmtMC->execute(['mid' => $movId, 'cid' => $co['cuadra_id'], 'n' => $co['num']]);
            }
        }
        Session::flash('success', 'Movimiento registrado correctamente.');
        $this->redirect('movimientos');
    }

    // ── Editar ───────────────────────────────────────────────────
    public function edit(string $id): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $mov = $this->ownedMovimientoOrAbort((int)$id, $uid);

        $this->view('movimientos/form', [
            'movimiento'      => $mov,
            'tipo'            => $mov['tipo'],
            'lotes'           => $this->loteModel->allByUsuario($uid),
            'naves'           => $this->naveModel->allByUsuario($uid),
            'estados'         => $this->model->estadosAnimal(),
            'tipos'           => $this->tipoMovModel->all(true),
            'motivos'         => $this->motivoModel->all(true),
            'config'          => $this->configModel->get($uid),
            'historial'       => $this->model->historial((int)$id),
            'etiquetasMov'    => (new Etiqueta())->forMovimiento((int)$id),
            'pageTitle'       => 'Editar movimiento',
            'error'           => Session::getFlash('error'),
        ]);
    }

    public function update(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("movimientos/{$id}/editar");
        }

        $uid       = Session::get('usuario_id');
        $tipo      = $this->postString('tipo');
        $movActual = $this->ownedMovimientoOrAbort((int)$id, $uid);

        // ── IDOR guards: nuevos IDs deben ser del usuario ───────────
        $loteOrigenId = (int)$this->post('lote_origen_id');
        if ($loteOrigenId && !$this->loteModel->find($loteOrigenId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Lote', 'target_id' => $loteOrigenId,
                'context' => 'movimientos/update',
            ]);
            Session::flash('error', 'Lote de origen no válido.');
            $this->redirect("movimientos/{$id}/editar");
        }
        $loteDestinoId   = $this->ownedIdOrNull($this->loteModel, (int)$this->post('lote_destino_id') ?: null);
        $cuadraOrigenId  = $this->ownedIdOrNull($this->cuadraModel, (int)$this->post('cuadra_origen_id') ?: null);
        $cuadraDestinoId = $this->ownedIdOrNull($this->cuadraModel, (int)$this->post('cuadra_destino_id') ?: null);

        // Albaran: si se sube nuevo archivo, reemplaza al anterior; si no,
        // se conserva el ya existente (lo lee el modelo del valor actual).
        $albaranNuevo = $this->guardarAlbaran($_FILES['albaran_archivo'] ?? null);

        $data = [
            'tipo'              => $tipo,
            'fecha'             => $this->postString('fecha') ?: date('Y-m-d'),
            'lote_origen_id'    => $loteOrigenId,
            'lote_destino_id'   => $loteDestinoId,
            'cuadra_origen_id'  => $cuadraOrigenId,
            'cuadra_destino_id' => $cuadraDestinoId,
            'num_animales'      => (int)$this->post('num_animales'),
            'peso_canal_kg'     => $this->post('peso_canal_kg')     ? (float)$this->post('peso_canal_kg') : null,
            'peso_real_kg'      => $this->post('peso_real_kg')      ? (float)$this->post('peso_real_kg')  : null,
            'precio_eur'        => $this->post('precio_eur')        ? (float)$this->post('precio_eur')    : null,
            'tipo_venta'        => $this->post('tipo_venta')        ?: null,
            'motivo_baja'       => $this->postString('motivo_baja') ?: null,
            'observaciones'     => $this->postString('observaciones'),
        ];
        if ($albaranNuevo !== null) {
            $data['albaran_archivo'] = $albaranNuevo;
        }

        // Si cambia el lote_origen y hay inventarios afectados, exigir confirmación.
        $loteCambiado = (int)$movActual['lote_origen_id'] !== $loteOrigenId;
        if ($loteCambiado && empty($_POST['confirmar_inventarios'])) {
            $afectados = $this->countInventariosAfectados((int)$movActual['lote_origen_id'], $loteOrigenId, $movActual['fecha'], $uid);
            if ($afectados > 0) {
                Session::flash('error', "Hay {$afectados} inventario(s) cuya fecha es posterior al movimiento y se verán afectados al cambiar el lote. Vuelve a guardar marcando la confirmación.");
                $_SESSION['_old_input'] = $_POST;
                $this->redirect("movimientos/{$id}/editar");
            }
        }

        // Revertir efecto anterior y aplicar el nuevo
        try {
            $this->revertirMovimiento($movActual, $uid);
            $this->aplicarMovimiento($tipo, $data, $uid);
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("movimientos/{$id}/editar");
        }

        $this->model->update((int)$id, $data, $uid);
        AuditLog::log('movimiento', (int)$id, 'update', $movActual, $data);

        // Sincronizar etiquetas
        $tagInput = $this->postString('etiquetas');
        (new Etiqueta())->syncForMovimiento((int)$id, $uid, Etiqueta::parseInput($tagInput));

        Session::flash('success', 'Movimiento actualizado.');
        $this->redirect('movimientos');
    }

    /**
     * Cuenta los inventarios del usuario con fecha >= movimiento.fecha donde
     * cualquiera de los dos lotes (origen anterior o nuevo) tiene una línea.
     * Sirve como advertencia: cambiar el lote tras un inventario altera la
     * foto histórica que ese inventario congeló.
     */
    private function countInventariosAfectados(int $loteAntiguoId, int $loteNuevoId, string $fechaMov, int $uid): int
    {
        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT COUNT(DISTINCT i.id)
            FROM inventarios i
            JOIN inventario_lineas il ON il.inventario_id = i.id
            WHERE i.usuario_id = :uid
              AND i.fecha >= :fecha
              AND (il.lote_id = :lote_a OR il.lote_id = :lote_n)
        ");
        $stmt->execute([
            'uid'     => $uid,
            'fecha'   => $fechaMov,
            'lote_a'  => $loteAntiguoId,
            'lote_n'  => $loteNuevoId,
        ]);
        return (int)$stmt->fetchColumn();
    }

    /** AJAX: cuenta inventarios afectados por un cambio de lote en un movimiento. */
    public function inventariosAfectados(string $id): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid = (int)Session::get('usuario_id');
        $mov = $this->ownedMovimientoOrAbort((int)$id, $uid);
        $loteNuevo = (int)($_GET['lote_id'] ?? 0);
        if (!$loteNuevo || $loteNuevo === (int)$mov['lote_origen_id']) {
            echo json_encode(['count' => 0]); return;
        }
        // IDOR guard del lote nuevo
        if (!$this->loteModel->find($loteNuevo, $uid)) {
            echo json_encode(['count' => 0]); return;
        }
        $count = $this->countInventariosAfectados((int)$mov['lote_origen_id'], $loteNuevo, $mov['fecha'], $uid);
        echo json_encode(['count' => $count]);
    }

    /** AJAX: lotes activos en una nave (con animales > 0). Soporta nave_id o lista vacía → todos. */
    public function lotesPorNave(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid    = (int)Session::get('usuario_id');
        $naveId = (int)($_GET['nave_id'] ?? 0);

        if ($naveId) {
            // IDOR guard: la nave debe ser del usuario
            $check = \App\Core\Database::getInstance()->prepare("
                SELECT 1 FROM naves n JOIN granjas g ON n.granja_id = g.id
                WHERE n.id = :id AND g.usuario_id = :uid
            ");
            $check->execute(['id' => $naveId, 'uid' => $uid]);
            if (!$check->fetchColumn()) { echo json_encode([]); return; }

            // Lotes con presencia en CUALQUIER cuadra de la nave (no solo lotes.nave_id)
            $stmt = \App\Core\Database::getInstance()->prepare("
                SELECT DISTINCT l.id, l.codigo, l.num_animales,
                       COALESCE(SUM(cl.num_animales), l.num_animales) AS animales_en_nave
                FROM lotes l
                LEFT JOIN cuadra_lote cl ON cl.lote_id = l.id AND cl.activo = 1 AND cl.num_animales > 0
                LEFT JOIN cuadras c ON cl.cuadra_id = c.id
                WHERE l.estado = 'activo'
                  AND l.num_animales > 0
                  AND (l.nave_id = :nid OR c.nave_id = :nid2)
                GROUP BY l.id
                ORDER BY l.codigo
            ");
            $stmt->execute(['nid' => $naveId, 'nid2' => $naveId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            return;
        }

        // Sin nave: todos los lotes activos del usuario
        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT l.id, l.codigo, l.num_animales
            FROM lotes l
            JOIN granjas g ON l.granja_id = g.id
            WHERE g.usuario_id = :uid AND l.estado = 'activo' AND l.num_animales > 0
            ORDER BY l.codigo
        ");
        $stmt->execute(['uid' => $uid]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** AJAX: peso esperado (tabla y proyección con último pesaje) para un lote a una fecha. */
    public function pesoEstimado(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid    = (int)Session::get('usuario_id');
        $loteId = (int)($_GET['lote_id'] ?? 0);
        $fecha  = trim($_GET['fecha'] ?? date('Y-m-d'));
        if (!$loteId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            echo json_encode(['ok' => false]); return;
        }
        // IDOR guard
        if (!$this->loteModel->find($loteId, $uid)) {
            echo json_encode(['ok' => false]); return;
        }

        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT
                l.codigo,
                l.num_animales,
                l.fecha_nacimiento,
                CEIL(DATEDIFF(:f1, l.fecha_nacimiento) / 7) AS semana,
                tcl.peso_kg     AS peso_tabla,
                tcl.coste_eur   AS coste_tabla,
                ult_p.peso_medio_kg  AS ultimo_peso_real,
                ult_p.fecha          AS ultimo_pesaje_fecha,
                tcl_p.peso_kg        AS peso_tabla_en_pesaje,
                ROUND(ult_p.peso_medio_kg + COALESCE(tcl.peso_kg,0) - COALESCE(tcl_p.peso_kg,0), 3) AS peso_real_proyectado
            FROM lotes l
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            LEFT JOIN tablas_crecimiento tc  ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl
                ON tcl.tabla_id = tc.id
                AND tcl.semana  = CEIL(DATEDIFF(:f2, l.fecha_nacimiento) / 7)
            LEFT JOIN pesajes ult_p ON ult_p.id = (
                SELECT id FROM pesajes WHERE lote_id = l.id AND fecha <= :f3 ORDER BY fecha DESC LIMIT 1
            )
            LEFT JOIN tablas_crecimiento_lineas tcl_p
                ON tcl_p.tabla_id = tc.id
                AND tcl_p.semana  = CEIL(DATEDIFF(ult_p.fecha, l.fecha_nacimiento) / 7)
            WHERE l.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $loteId, 'f1' => $fecha, 'f2' => $fecha, 'f3' => $fecha]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['ok' => false]); return; }

        echo json_encode([
            'ok'                   => true,
            'codigo'               => $row['codigo'],
            'num_animales'         => (int)$row['num_animales'],
            'semana'               => $row['semana'] !== null ? (int)$row['semana'] : null,
            'peso_tabla'           => $row['peso_tabla']           !== null ? (float)$row['peso_tabla']           : null,
            'coste_tabla'          => $row['coste_tabla']          !== null ? (float)$row['coste_tabla']          : null,
            'ultimo_peso_real'     => $row['ultimo_peso_real']     !== null ? (float)$row['ultimo_peso_real']     : null,
            'peso_real_proyectado' => $row['peso_real_proyectado'] !== null ? (float)$row['peso_real_proyectado'] : null,
        ]);
    }

    // ── Eliminar ─────────────────────────────────────────────────
    public function delete(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('movimientos');
        }
        $uid = Session::get('usuario_id');
        // IDOR guard: el movimiento debe ser del usuario
        $mov = $this->ownedMovimientoOrAbort((int)$id, $uid);
        try {
            $this->revertirMovimiento($mov, $uid);
        } catch (\Exception $e) {
            // Si no se puede revertir, eliminar igualmente
        }
        \App\Core\Database::getInstance()
            ->prepare("DELETE FROM movimiento_cuadras WHERE movimiento_id = :id")
            ->execute(['id' => (int)$id]);
        $this->model->delete((int)$id, $uid);
        AuditLog::log('movimiento', (int)$id, 'delete', $mov, null);
        Session::flash('success', 'Movimiento eliminado y efecto revertido.');
        $this->redirect('movimientos');
    }

    /**
     * Devuelve el movimiento si pertenece al usuario; redirige y loguea
     * idor_attempt en caso contrario. Movimiento::find() no filtra por
     * usuario_id (lo haría falta refactor del modelo), así que validamos
     * aquí.
     */
    private function ownedMovimientoOrAbort(int $id, int $uid): array
    {
        $mov = $this->model->find($id);
        if (!$mov || (int)($mov['usuario_id'] ?? 0) !== $uid) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Movimiento', 'target_id' => $id,
            ]);
            Session::flash('error', 'Movimiento no encontrado o sin permisos.');
            $this->redirect('movimientos');
        }
        return $mov;
    }

    // ── API AJAX: cuadras de una o varias naves ──────────────────
    public function cuadrasPorNave(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid = (int) Session::get('usuario_id');

        // Soporta ?nave_id=X (legacy) o ?nave_ids[]=X&nave_ids[]=Y
        $naveIds = [];
        if (isset($_GET['nave_ids']) && is_array($_GET['nave_ids'])) {
            $naveIds = array_values(array_filter(array_map('intval', $_GET['nave_ids'])));
        } elseif (!empty($_GET['nave_id'])) {
            $naveIds = [(int)$_GET['nave_id']];
        }
        if (empty($naveIds)) { echo json_encode([]); return; }

        // IDOR guard: las naves deben pertenecer al usuario
        $ph0 = implode(',', array_fill(0, count($naveIds), '?'));
        $check = \App\Core\Database::getInstance()->prepare("
            SELECT n.id FROM naves n
            JOIN granjas g ON n.granja_id = g.id
            WHERE n.id IN ({$ph0}) AND g.usuario_id = ?
        ");
        $check->execute([...$naveIds, $uid]);
        $naveIds = array_map('intval', $check->fetchAll(\PDO::FETCH_COLUMN));
        if (empty($naveIds)) { echo json_encode([]); return; }

        $ph   = implode(',', array_fill(0, count($naveIds), '?'));
        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT c.id, c.nombre, c.capacidad_maxima,
                   COALESCE(SUM(cl.num_animales), 0)             AS ocupados,
                   GROUP_CONCAT(DISTINCT l.codigo SEPARATOR ', ') AS lotes
            FROM cuadras c
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id
                AND cl.activo = 1
                AND cl.num_animales > 0
            LEFT JOIN lotes l ON cl.lote_id = l.id AND l.estado = 'activo'
            WHERE c.nave_id IN ({$ph}) AND c.activa = 1
            GROUP BY c.id
            ORDER BY LENGTH(c.nombre), c.nombre
        ");
        $stmt->execute($naveIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['disponible'] = $r['capacidad_maxima'] !== null
                ? max(0, (int)$r['capacidad_maxima'] - (int)$r['ocupados'])
                : null;
        }
        echo json_encode($rows);
    }

    // ── API AJAX: lotes de una cuadra ────────────────────────────
    public function cuadrasPorLote(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid    = (int) Session::get('usuario_id');
        $loteId = (int)($_GET['lote_id'] ?? 0);
        if (!$loteId) { echo json_encode([]); return; }
        // IDOR guard: el lote debe ser del usuario
        if (!$this->loteModel->find($loteId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Lote', 'target_id' => $loteId,
                'context' => 'movimientos/cuadras-lote',
            ]);
            echo json_encode([]); return;
        }

        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT c.id, c.nombre, cl.num_animales,
                   n.nombre AS nave_nombre
            FROM cuadra_lote cl
            JOIN cuadras c ON c.id = cl.cuadra_id
            JOIN naves n   ON c.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE cl.lote_id = :lote_id
              AND cl.activo = 1
              AND cl.num_animales > 0
              AND g.usuario_id = :uid
            ORDER BY n.nombre, LENGTH(c.nombre), c.nombre
        ");
        $stmt->execute(['lote_id' => $loteId, 'uid' => $uid]);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function todasLasCuadras(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid = \App\Core\Session::get('usuario_id');

        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT c.id, c.nombre, n.nombre AS nave_nombre,
                   COALESCE(SUM(CASE WHEN cl.activo=1 THEN cl.num_animales ELSE 0 END), 0) AS num_animales
            FROM cuadras c
            JOIN naves n   ON c.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id
            WHERE g.usuario_id = :uid
            GROUP BY c.id, c.nombre, n.nombre
            ORDER BY n.nombre, LENGTH(c.nombre), c.nombre
        ");
        $stmt->execute(['uid' => $uid]);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function lotesPorCuadra(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid      = (int) Session::get('usuario_id');
        $cuadraId = (int)($_GET['cuadra_id'] ?? 0);
        if (!$cuadraId) { echo json_encode([]); return; }
        // IDOR guard: la cuadra debe ser del usuario
        if (!$this->cuadraModel->find($cuadraId, $uid)) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Cuadra', 'target_id' => $cuadraId,
                'context' => 'movimientos/lotes-cuadra',
            ]);
            echo json_encode([]); return;
        }

        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT l.id, l.codigo, cl.num_animales
            FROM lotes l
            JOIN cuadra_lote cl ON cl.lote_id = l.id
                AND cl.cuadra_id = :cuadra_id
                AND cl.activo = 1
                AND cl.num_animales > 0
            WHERE l.estado = 'activo'
            ORDER BY l.codigo
        ");
        $stmt->execute(['cuadra_id' => $cuadraId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ── Lógica de efectos ────────────────────────────────────────
    /**
     * Resuelve la "rama de efecto" a aplicar: para los 6 tipos del sistema
     * usamos su código directamente (mantienen lógica especial). Para tipos
     * personalizados, mapeamos la categoría al efecto equivalente:
     *   venta → 'venta', baja → 'baja', salida → 'baja' (sin motivo),
     *   entrada → 'entrada', traslado → 'traslado_cuadra',
     *   transicion / re_creacion / re_consumo: requieren tipo del sistema.
     */
    private function ramaEfecto(string $tipo): string
    {
        static $sistema = ['traslado_cuadra','entrada_cebo','entrada_reposicion','entrada_madres','venta','baja'];
        if (in_array($tipo, $sistema, true)) return $tipo;
        $cat = $this->tipoMovModel->categoriaDe($tipo);
        return match ($cat) {
            'venta'         => 'venta',
            'baja'          => 'baja',
            'salida'        => 'baja',     // resta animales sin motivo
            'entrada'       => 'entrada',  // suma animales al lote existente
            'traslado'      => 'traslado_cuadra',
            'traslado_lote' => 'traslado_lote',
            'transicion'    => 'entrada_cebo',
            're_creacion'   => 'entrada_reposicion',
            're_consumo'    => 'entrada_madres',
            'destete'       => 'destete',
            default         => 'baja',     // fallback seguro: tratar como salida
        };
    }

    private function revertirMovimiento(array $mov, int $uid): void
    {
        $db       = \App\Core\Database::getInstance();
        $cantidad = (int)$mov['num_animales'];

        switch ($this->ramaEfecto($mov['tipo'])) {

            case 'traslado_cuadra':
                // Devolver animales a la cuadra origen
                if ($mov['cuadra_origen_id'] && $mov['lote_origen_id']) {
                    $stmt = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id = :cid AND lote_id = :lid LIMIT 1");
                    $stmt->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                    $clId = $stmt->fetchColumn();
                    if ($clId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE id = :id")
                           ->execute(['n' => $cantidad, 'id' => $clId]);
                    } else {
                        $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid, :lid, :n, CURDATE())")
                           ->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id'], 'n' => $cantidad]);
                    }
                }
                // Quitar animales de la cuadra destino
                if ($mov['cuadra_destino_id'] && $mov['lote_origen_id']) {
                    $db->prepare("
                        UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n)
                        WHERE cuadra_id = :cid AND lote_id = :lid AND activo = 1
                    ")->execute(['n' => $cantidad, 'cid' => $mov['cuadra_destino_id'], 'lid' => $mov['lote_origen_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo = 0 WHERE cuadra_id = :cid AND lote_id = :lid AND num_animales = 0")
                       ->execute(['cid' => $mov['cuadra_destino_id'], 'lid' => $mov['lote_origen_id']]);
                }
                break;

            case 'venta':
            case 'baja':
                // Devolver animales al lote
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo' WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);

                // Restaurar cuadras: primero buscar en movimiento_cuadras (multi-cuadra)
                $stmtMC = $db->prepare("SELECT cuadra_id, num_animales FROM movimiento_cuadras WHERE movimiento_id = :mid");
                $stmtMC->execute(['mid' => $mov['id']]);
                $cuadrasOrigen = $stmtMC->fetchAll();

                if (!empty($cuadrasOrigen)) {
                    foreach ($cuadrasOrigen as $co) {
                        $stmtCL = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id = :cid AND lote_id = :lid LIMIT 1");
                        $stmtCL->execute(['cid' => $co['cuadra_id'], 'lid' => $mov['lote_origen_id']]);
                        $clId = $stmtCL->fetchColumn();
                        if ($clId) {
                            $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE id = :id")
                               ->execute(['n' => $co['num_animales'], 'id' => $clId]);
                        } else {
                            $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid, :lid, :n, CURDATE())")
                               ->execute(['cid' => $co['cuadra_id'], 'lid' => $mov['lote_origen_id'], 'n' => $co['num_animales']]);
                        }
                    }
                } elseif ($mov['cuadra_origen_id']) {
                    // Fallback: cuadra única guardada en el movimiento
                    $stmtCL = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id = :cid AND lote_id = :lid LIMIT 1");
                    $stmtCL->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                    $clId = $stmtCL->fetchColumn();
                    if ($clId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE id = :id")
                           ->execute(['n' => $cantidad, 'id' => $clId]);
                    } else {
                        $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid, :lid, :n, CURDATE())")
                           ->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id'], 'n' => $cantidad]);
                    }
                }
                break;

            case 'entrada_cebo':
                $db->prepare("UPDATE lotes SET estado_animal = 'lechon' WHERE id = :id")
                   ->execute(['id' => $mov['lote_origen_id']]);
                break;

            case 'entrada_reposicion':
                // Devolver animales al lote origen
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo' WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);
                // Devolver animales a la cuadra origen
                if ($mov['cuadra_origen_id']) {
                    $stmt = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id = :cid AND lote_id = :lid LIMIT 1");
                    $stmt->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                    $clId = $stmt->fetchColumn();
                    if ($clId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE id = :id")
                           ->execute(['n' => $cantidad, 'id' => $clId]);
                    } else {
                        $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid, :lid, :n, CURDATE())")
                           ->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id'], 'n' => $cantidad]);
                    }
                }
                // Cerrar el lote RE destino y limpiar sus cuadras
                if ($mov['lote_destino_id'] && $mov['lote_destino_id'] !== $mov['lote_origen_id']) {
                    $db->prepare("UPDATE lotes SET estado = 'cerrado', num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                       ->execute(['n' => $cantidad, 'id' => $mov['lote_destino_id']]);
                    $db->prepare("UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n) WHERE lote_id = :lid AND activo = 1")
                       ->execute(['n' => $cantidad, 'lid' => $mov['lote_destino_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo = 0 WHERE lote_id = :lid AND num_animales = 0")
                       ->execute(['lid' => $mov['lote_destino_id']]);
                }
                break;

            case 'entrada_madres':
                // Restaurar animales al lote RE y reactivarlo
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo', estado_animal = 'reposicion' WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);
                // Restaurar en cuadra origen
                if ($mov['cuadra_origen_id']) {
                    $stmt = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id = :cid AND lote_id = :lid LIMIT 1");
                    $stmt->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                    $clId = $stmt->fetchColumn();
                    if ($clId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE id = :id")
                           ->execute(['n' => $cantidad, 'id' => $clId]);
                    } else {
                        $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid, :lid, :n, CURDATE())")
                           ->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id'], 'n' => $cantidad]);
                    }
                }
                break;

            case 'entrada':
                // Compra/entrada genérica: restar animales del lote para revertir el alta.
                $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);
                if ($mov['cuadra_origen_id']) {
                    $db->prepare("UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n)
                                  WHERE cuadra_id = :cid AND lote_id = :lid AND activo = 1")
                       ->execute(['n' => $cantidad, 'cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo = 0 WHERE cuadra_id = :cid AND lote_id = :lid AND num_animales = 0")
                       ->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                }
                break;

            case 'traslado_lote':
                // Devolver animales al lote origen y quitarlos del destino
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo' WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);
                if ($mov['lote_destino_id']) {
                    $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                       ->execute(['n' => $cantidad, 'id' => $mov['lote_destino_id']]);
                }
                if ($mov['cuadra_origen_id']) {
                    $stmt = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id = :cid AND lote_id = :lid LIMIT 1");
                    $stmt->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                    $clId = $stmt->fetchColumn();
                    if ($clId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE id = :id")
                           ->execute(['n' => $cantidad, 'id' => $clId]);
                    } else {
                        $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid, :lid, :n, CURDATE())")
                           ->execute(['cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id'], 'n' => $cantidad]);
                    }
                }
                break;

            case 'destete':
                // El destete creó un lote nuevo. Revertir el destete = no
                // tocar el lote (el usuario debería borrar el lote a mano si
                // quiere deshacer realmente). Solo el registro del movimiento
                // se elimina, el lote permanece intacto.
                break;
        }
    }

    private function aplicarMovimiento(string $tipo, array &$data, int $uid): void
    {
        $db         = \App\Core\Database::getInstance();
        $loteOrigen = $this->loteModel->find($data['lote_origen_id'], $uid);
        if (!$loteOrigen) throw new \Exception('Lote de origen no encontrado.');

        $cantidad = $data['num_animales'];
        if ($cantidad < 1) throw new \Exception('La cantidad debe ser mayor que 0.');

        switch ($this->ramaEfecto($tipo)) {

            case 'traslado_cuadra':
                if (!$data['cuadra_destino_id']) throw new \Exception('Selecciona una cuadra destino.');
                if (!$data['cuadra_origen_id'])  throw new \Exception('Selecciona una cuadra origen.');

                // Validar animales en cuadra origen
                $stmtCheck = $db->prepare("SELECT COALESCE(num_animales,0) FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1 LIMIT 1");
                $stmtCheck->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                $enCuadra = (int)$stmtCheck->fetchColumn();
                if ($cantidad > $enCuadra) {
                    throw new \Exception("Solo hay {$enCuadra} animales del lote en esa cuadra. No puedes trasladar {$cantidad}.");
                }

                // Validar capacidad cuadra destino
                $stmtCap = $db->prepare("SELECT c.capacidad_maxima, COALESCE(SUM(cl.num_animales),0) AS ocupados FROM cuadras c LEFT JOIN cuadra_lote cl ON cl.cuadra_id=c.id AND cl.activo=1 WHERE c.id=:id GROUP BY c.id");
                $stmtCap->execute(['id' => $data['cuadra_destino_id']]);
                $cuadraDestino = $stmtCap->fetch();
                if ($cuadraDestino && $cuadraDestino['capacidad_maxima']) {
                    $libre = $cuadraDestino['capacidad_maxima'] - $cuadraDestino['ocupados'];
                    if ($cantidad > $libre) {
                        throw new \Exception("La cuadra destino solo tiene {$libre} plazas libres.");
                    }
                }

                // Restar de cuadra origen
                $db->prepare("UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n) WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1")
                   ->execute(['n' => $cantidad, 'cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                $db->prepare("UPDATE cuadra_lote SET activo=0 WHERE cuadra_id=:cid AND lote_id=:lid AND num_animales=0")
                   ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);

                // Obtener nave destino
                $stmtNave = $db->prepare("SELECT nave_id FROM cuadras WHERE id=:id");
                $stmtNave->execute(['id' => $data['cuadra_destino_id']]);
                $naveId = $stmtNave->fetchColumn();

                // Sumar en cuadra destino
                $stmtExiste = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid LIMIT 1");
                $stmtExiste->execute(['cid' => $data['cuadra_destino_id'], 'lid' => $data['lote_origen_id']]);
                $clId = $stmtExiste->fetchColumn();
                if ($clId) {
                    $db->prepare("UPDATE cuadra_lote SET num_animales=num_animales+:n, activo=1 WHERE id=:id")
                       ->execute(['n' => $cantidad, 'id' => $clId]);
                } else {
                    $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid,:lid,:n,CURDATE())")
                       ->execute(['cid' => $data['cuadra_destino_id'], 'lid' => $data['lote_origen_id'], 'n' => $cantidad]);
                }

                // Actualizar nave del lote solo si no quedan animales en nave origen
                if ($naveId && $loteOrigen['nave_id'] && $naveId != $loteOrigen['nave_id']) {
                    $stmtResto = $db->prepare("SELECT COALESCE(SUM(cl.num_animales),0) FROM cuadra_lote cl JOIN cuadras c ON cl.cuadra_id=c.id WHERE cl.lote_id=:lid AND c.nave_id=:nid AND cl.activo=1");
                    $stmtResto->execute(['lid' => $data['lote_origen_id'], 'nid' => $loteOrigen['nave_id']]);
                    if ((int)$stmtResto->fetchColumn() === 0) {
                        $db->prepare("UPDATE lotes SET nave_id=:nave WHERE id=:id")->execute(['nave' => $naveId, 'id' => $data['lote_origen_id']]);
                    }
                }
                break;

            case 'entrada_cebo':
                $db->prepare("UPDATE lotes SET estado_animal='cebo' WHERE id=:id")->execute(['id' => $data['lote_origen_id']]);
                break;

            case 'entrada_reposicion':
                if ($cantidad > $loteOrigen['num_animales']) {
                    throw new \Exception("Solo hay {$loteOrigen['num_animales']} animales en el lote.");
                }
                // Restar animales del lote origen
                $db->prepare("UPDATE lotes SET num_animales=GREATEST(0,num_animales-:n) WHERE id=:id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                // Restar de cuadra origen
                if (!empty($data['cuadra_origen_id'])) {
                    $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1")
                       ->execute(['n' => $cantidad, 'cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo=0 WHERE cuadra_id=:cid AND lote_id=:lid AND num_animales=0")
                       ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                }
                // Código RE = parte base del código sin identificador de raza
                // L 13/26 IB → L 13/26 RE
                $codigoRE = preg_replace('/^(L \d+\/\d+).*$/', '$1 RE', trim($loteOrigen['codigo']));

                // Buscar lote RE existente (activo o cerrado) con ese código
                $stmtRE = $db->prepare("
                    SELECT id, estado FROM lotes WHERE codigo = :codigo
                    AND granja_id = :gid ORDER BY id DESC LIMIT 1
                ");
                $stmtRE->execute(['codigo' => $codigoRE, 'gid' => $loteOrigen['granja_id']]);
                $loteREExistente = $stmtRE->fetch();

                if ($loteREExistente) {
                    // Reutilizar — reactivar si estaba cerrado y sumar animales
                    $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo' WHERE id = :id")
                       ->execute(['n' => $cantidad, 'id' => $loteREExistente['id']]);
                    // Añadir a cuadra origen
                    if (!empty($data['cuadra_origen_id'])) {
                        $stmtCL = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid LIMIT 1");
                        $stmtCL->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $loteREExistente['id']]);
                        $clId = $stmtCL->fetchColumn();
                        if ($clId) {
                            $db->prepare("UPDATE cuadra_lote SET num_animales=num_animales+:n, activo=1 WHERE id=:id")
                               ->execute(['n' => $cantidad, 'id' => $clId]);
                        } else {
                            $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid,:lid,:n,CURDATE())")
                               ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $loteREExistente['id'], 'n' => $cantidad]);
                        }
                    }
                    $data['lote_destino_id'] = $loteREExistente['id'];
                } else {
                    // Crear nuevo lote RE
                    $nuevoId = $this->crearSubLote($loteOrigen, $codigoRE, $cantidad, 'reposicion', $uid, !empty($data['cuadra_origen_id']) ? (int)$data['cuadra_origen_id'] : null);
                    $data['lote_destino_id'] = $nuevoId;
                }
                break;

            case 'entrada_madres':
                if ($cantidad > $loteOrigen['num_animales']) {
                    throw new \Exception("Solo hay {$loteOrigen['num_animales']} animales en el lote RE.");
                }
                // Restar animales del lote RE siempre
                $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                // Descontar de cuadra origen si se especificó
                if (!empty($data['cuadra_origen_id'])) {
                    $db->prepare("UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n) WHERE cuadra_id = :cid AND lote_id = :lid AND activo = 1")
                       ->execute(['n' => $cantidad, 'cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo = 0 WHERE cuadra_id = :cid AND lote_id = :lid AND num_animales = 0")
                       ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                }
                // Si el lote RE queda vacío, cerrarlo
                $restantesRE = $db->prepare("SELECT num_animales FROM lotes WHERE id = :id");
                $restantesRE->execute(['id' => $data['lote_origen_id']]);
                if ((int)$restantesRE->fetchColumn() <= 0) {
                    $db->prepare("UPDATE lotes SET estado = 'cerrado' WHERE id = :id")
                       ->execute(['id' => $data['lote_origen_id']]);
                }
                // No se crea lote destino
                $data['lote_destino_id'] = $data['lote_origen_id'];
                break;

            case 'venta':
            case 'baja':
                if ($cantidad > $loteOrigen['num_animales']) {
                    throw new \Exception("Solo hay {$loteOrigen['num_animales']} animales en el lote.");
                }

                if (!empty($data['cuadras_origen'])) {
                    // Multi-cuadra: descontar de cada cuadra indicada
                    foreach ($data['cuadras_origen'] as $co) {
                        $stmtChk = $db->prepare("SELECT COALESCE(num_animales,0) FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1 LIMIT 1");
                        $stmtChk->execute(['cid' => $co['cuadra_id'], 'lid' => $data['lote_origen_id']]);
                        $enCuadra = (int)$stmtChk->fetchColumn();
                        if ($co['num'] > $enCuadra) {
                            throw new \Exception("Cuadra {$co['cuadra_id']}: solo hay {$enCuadra} animales, no se pueden sacar {$co['num']}.");
                        }
                        $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1")
                           ->execute(['n' => $co['num'], 'cid' => $co['cuadra_id'], 'lid' => $data['lote_origen_id']]);
                        $db->prepare("UPDATE cuadra_lote SET activo=0 WHERE cuadra_id=:cid AND lote_id=:lid AND num_animales=0")
                           ->execute(['cid' => $co['cuadra_id'], 'lid' => $data['lote_origen_id']]);
                    }
                } elseif (!empty($data['cuadra_origen_id'])) {
                    // Cuadra única
                    $stmtChk = $db->prepare("SELECT COALESCE(num_animales,0) FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1 LIMIT 1");
                    $stmtChk->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $enCuadra = (int)$stmtChk->fetchColumn();
                    if ($cantidad > $enCuadra) {
                        throw new \Exception("Solo hay {$enCuadra} animales del lote en esa cuadra.");
                    }
                    $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1")
                       ->execute(['n' => $cantidad, 'cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo=0 WHERE cuadra_id=:cid AND lote_id=:lid AND num_animales=0")
                       ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                } else {
                    // Sin cuadra: descontar de todas proporcionalmente
                    $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE lote_id=:lid AND activo=1")
                       ->execute(['n' => $cantidad, 'lid' => $data['lote_origen_id']]);
                }

                $db->prepare("UPDATE lotes SET num_animales=GREATEST(0,num_animales-:n) WHERE id=:id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);

                $restantes = $db->prepare("SELECT num_animales FROM lotes WHERE id=:id");
                $restantes->execute(['id' => $data['lote_origen_id']]);
                if ((int)$restantes->fetchColumn() <= 0) {
                    $db->prepare("UPDATE lotes SET estado='cerrado', fecha_cierre=CURDATE() WHERE id=:id")->execute(['id' => $data['lote_origen_id']]);
                }
                break;

            case 'entrada':
                // Compra/entrada genérica: sumar animales al lote existente y a la cuadra si se indica.
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo' WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                if (!empty($data['cuadra_origen_id'])) {
                    $stmtCL = $db->prepare("SELECT id FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid LIMIT 1");
                    $stmtCL->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $clId = $stmtCL->fetchColumn();
                    if ($clId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE id = :id")
                           ->execute(['n' => $cantidad, 'id' => $clId]);
                    } else {
                        $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada) VALUES (:cid, :lid, :n, CURDATE())")
                           ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id'], 'n' => $cantidad]);
                    }
                }
                break;

            case 'traslado_lote':
                // Mueve animales de un lote a otro. Se descuentan del origen
                // (lote y opcionalmente cuadra) y se suman al destino sin
                // forzar asignación de cuadra (el usuario lo hará después).
                if (empty($data['lote_destino_id'])) throw new \Exception('Selecciona un lote destino.');
                if ((int)$data['lote_destino_id'] === (int)$data['lote_origen_id']) {
                    throw new \Exception('El lote destino debe ser distinto del origen.');
                }
                $loteDestino = $this->loteModel->find((int)$data['lote_destino_id'], $uid);
                if (!$loteDestino) throw new \Exception('Lote destino no válido.');
                if ($cantidad > $loteOrigen['num_animales']) {
                    throw new \Exception("Solo hay {$loteOrigen['num_animales']} animales en el lote origen.");
                }
                // Descontar del lote origen
                $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                // Si se indicó cuadra origen, descontar también de cuadra_lote
                if (!empty($data['cuadra_origen_id'])) {
                    $stmtChk = $db->prepare("SELECT COALESCE(num_animales,0) FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1 LIMIT 1");
                    $stmtChk->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $enCuadra = (int)$stmtChk->fetchColumn();
                    if ($cantidad > $enCuadra) {
                        throw new \Exception("Solo hay {$enCuadra} animales del lote en esa cuadra.");
                    }
                    $db->prepare("UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n) WHERE cuadra_id = :cid AND lote_id = :lid AND activo = 1")
                       ->execute(['n' => $cantidad, 'cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo = 0 WHERE cuadra_id = :cid AND lote_id = :lid AND num_animales = 0")
                       ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                }
                // Si el origen queda en 0, marcar como cerrado
                $rest = $db->prepare("SELECT num_animales FROM lotes WHERE id = :id");
                $rest->execute(['id' => $data['lote_origen_id']]);
                if ((int)$rest->fetchColumn() <= 0) {
                    $db->prepare("UPDATE lotes SET estado='cerrado', fecha_cierre=CURDATE() WHERE id=:id")
                       ->execute(['id' => $data['lote_origen_id']]);
                }
                // Sumar al lote destino (sin tocar cuadra_lote — queda libre
                // para que el usuario lo asigne manualmente con un traslado
                // de cuadra si lo necesita).
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo' WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_destino_id']]);
                break;

            case 'destete':
                // El movimiento de destete se crea automáticamente al guardar
                // un lote desde /lotes/crear?modo=destete. No aplica efectos
                // adicionales (el lote ya quedó creado con sus animales).
                break;
        }
    }

    /**
     * Guarda un archivo subido como albarán (foto JPG/PNG/WEBP o PDF) en
     * /uploads/albaranes/. Devuelve la ruta relativa para almacenar en BD,
     * o null si no se subió archivo. Lanza excepción si el archivo es
     * inválido (tipo no permitido, demasiado grande, etc.).
     *
     * Reutiliza la misma defensa-en-profundidad del escáner: valida MIME
     * real (magic bytes) y tamaño, no la extensión.
     */
    private function guardarAlbaran(?array $file): ?string
    {
        if (!$file || !isset($file['error'])) return null;
        if ($file['error'] === UPLOAD_ERR_NO_FILE)  return null;
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Error al subir el archivo del albarán.');
        }

        $maxBytes = 10 * 1024 * 1024; // 10 MB
        if (!is_uploaded_file($file['tmp_name']) || $file['size'] > $maxBytes) {
            throw new \Exception('El albarán supera 10 MB.');
        }

        $allowedMime = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!isset($allowedMime[$mime])) {
            throw new \Exception('Solo se permiten imágenes JPG/PNG/WEBP o PDF.');
        }

        $uploadDir = ROOT_PATH . '/uploads/albaranes/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);

        // .htaccess que impide ejecución y listado en /uploads
        $htUploads = ROOT_PATH . '/uploads/.htaccess';
        if (!is_file($htUploads)) {
            @file_put_contents($htUploads,
                "<FilesMatch \"\\.(php|phtml|phar|pl|py|jsp|asp|sh|cgi)$\">\n" .
                "    Require all denied\n" .
                "</FilesMatch>\n" .
                "Options -ExecCGI -Indexes\n"
            );
        }

        $ext      = $allowedMime[$mime];
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \Exception('No se pudo guardar el albarán.');
        }
        @chmod($destPath, 0640);

        return 'uploads/albaranes/' . $filename;
    }

    private function crearSubLote(array $origen, string $codigo, int $numAnimales, string $estadoAnimal, int $uid, ?int $cuadraOrigenId = null): int
    {
        $db = \App\Core\Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO lotes (granja_id, nave_id, tipo_animal_id, raza_id, codigo, num_animales,
                               peso_entrada_kg, fecha_entrada, fecha_nacimiento, estado, estado_animal, observaciones)
            VALUES (:granja_id, :nave_id, :tipo_animal_id, :raza_id, :codigo, :num_animales,
                    :peso_entrada_kg, CURDATE(), :fecha_nacimiento, 'activo', :estado_animal, :observaciones)
        ");
        $stmt->execute([
            'granja_id'       => $origen['granja_id'],
            'nave_id'         => $origen['nave_id'],
            'tipo_animal_id'  => $origen['tipo_animal_id'],
            'raza_id'         => $origen['raza_id'],
            'codigo'          => $codigo,
            'num_animales'    => $numAnimales,
            'peso_entrada_kg' => $origen['peso_entrada_kg'],
            'fecha_nacimiento'=> $origen['fecha_nacimiento'],
            'estado_animal'   => $estadoAnimal,
            'observaciones'   => "Creado desde lote {$origen['codigo']}",
        ]);
        $nuevoId = (int) $db->lastInsertId();

        // Asignar solo a la cuadra origen especificada
        if ($cuadraOrigenId) {
            $db->prepare("
                INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada)
                VALUES (:cid, :lid, :n, CURDATE())
            ")->execute(['cid' => $cuadraOrigenId, 'lid' => $nuevoId, 'n' => $numAnimales]);
        }

        return $nuevoId;
    }
}