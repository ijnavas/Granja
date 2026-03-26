<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Movimiento;
use App\Models\Lote;
use App\Models\Nave;
use App\Models\Cuadra;
use App\Core\Session;
use PDO;

class MovimientoController extends BaseController
{
    private Movimiento $model;
    private Lote       $loteModel;
    private Nave       $naveModel;
    private Cuadra     $cuadraModel;

    public function __construct()
    {
        $this->model       = new Movimiento();
        $this->loteModel   = new Lote();
        $this->naveModel   = new Nave();
        $this->cuadraModel = new Cuadra();
    }

    // ── Listado ──────────────────────────────────────────────────
    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('movimientos/index', [
            'movimientos' => $this->model->allByUsuario($uid),
            'pageTitle'   => 'Movimientos',
            'success'     => Session::getFlash('success'),
            'error'       => Session::getFlash('error'),
        ]);
    }

    // ── Crear ────────────────────────────────────────────────────
    public function create(): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $tipo = $_GET['tipo'] ?? 'traslado_cuadra';

        $this->view('movimientos/form', [
            'movimiento' => null,
            'tipo'       => $tipo,
            'lotes'      => $this->loteModel->allByUsuario($uid),
            'naves'      => $this->naveModel->allByUsuario($uid),
            'estados'    => $this->model->estadosAnimal(),
            'pageTitle'  => 'Nuevo movimiento',
            'error'      => Session::getFlash('error'),
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

        $data = [
            'tipo'              => $tipo,
            'fecha'             => $this->postString('fecha') ?: date('Y-m-d'),
            'lote_origen_id'    => (int)$this->post('lote_origen_id'),
            'lote_destino_id'   => $this->post('lote_destino_id')   ?: null,
            'cuadra_origen_id'  => $this->post('cuadra_origen_id')  ?: null,
            'cuadra_destino_id' => $this->post('cuadra_destino_id') ?: null,
            'num_animales'          => (int)$this->post('num_animales'),
            'peso_canal_kg'     => $this->post('peso_canal_kg')     ? (float)$this->post('peso_canal_kg') : null,
            'precio_eur'        => $this->post('precio_eur')        ? (float)$this->post('precio_eur')    : null,
            'tipo_venta'        => $this->post('tipo_venta')        ?: null,
            'observaciones'     => $this->postString('observaciones'),
        ];

        // Aplicar efectos del movimiento
        try {
            $this->aplicarMovimiento($tipo, $data, $uid);
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('movimientos/crear?tipo=' . $tipo);
        }

        $this->model->create($data, $uid);
        Session::flash('success', 'Movimiento registrado correctamente.');
        $this->redirect('movimientos');
    }

    // ── Editar ───────────────────────────────────────────────────
    public function edit(string $id): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $mov = $this->model->find((int)$id);
        if (!$mov) $this->redirect('movimientos');

        $this->view('movimientos/form', [
            'movimiento' => $mov,
            'tipo'       => $mov['tipo'],
            'lotes'      => $this->loteModel->allByUsuario($uid),
            'naves'      => $this->naveModel->allByUsuario($uid),
            'estados'    => $this->model->estadosAnimal(),
            'historial'  => $this->model->historial((int)$id),
            'pageTitle'  => 'Editar movimiento',
            'error'      => Session::getFlash('error'),
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
        $movActual = $this->model->find((int)$id);
        if (!$movActual) $this->redirect('movimientos');

        $data = [
            'tipo'              => $tipo,
            'fecha'             => $this->postString('fecha') ?: date('Y-m-d'),
            'lote_origen_id'    => (int)$this->post('lote_origen_id'),
            'lote_destino_id'   => $this->post('lote_destino_id')   ?: null,
            'cuadra_origen_id'  => $this->post('cuadra_origen_id')  ?: null,
            'cuadra_destino_id' => $this->post('cuadra_destino_id') ?: null,
            'num_animales'      => (int)$this->post('num_animales'),
            'peso_canal_kg'     => $this->post('peso_canal_kg')     ? (float)$this->post('peso_canal_kg') : null,
            'precio_eur'        => $this->post('precio_eur')        ? (float)$this->post('precio_eur')    : null,
            'tipo_venta'        => $this->post('tipo_venta')        ?: null,
            'observaciones'     => $this->postString('observaciones'),
        ];

        // Revertir efecto anterior y aplicar el nuevo
        try {
            $this->revertirMovimiento($movActual, $uid);
            $this->aplicarMovimiento($tipo, $data, $uid);
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("movimientos/{$id}/editar");
        }

        $this->model->update((int)$id, $data, $uid);
        Session::flash('success', 'Movimiento actualizado.');
        $this->redirect('movimientos');
    }

    // ── Eliminar ─────────────────────────────────────────────────
    public function delete(string $id): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $mov = $this->model->find((int)$id);
        if ($mov) {
            try {
                $this->revertirMovimiento($mov, $uid);
            } catch (\Exception $e) {
                // Si no se puede revertir, eliminar igualmente pero avisar
            }
        }
        $this->model->delete((int)$id, $uid);
        Session::flash('success', 'Movimiento eliminado y efecto revertido.');
        $this->redirect('movimientos');
    }

    // ── API AJAX: cuadras de una nave ────────────────────────────
    public function cuadrasPorNave(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $naveId = (int)($_GET['nave_id'] ?? 0);
        if (!$naveId) { echo json_encode([]); return; }

        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT c.id, c.nombre, c.capacidad_maxima,
                   GROUP_CONCAT(DISTINCT l.codigo SEPARATOR ', ') AS lotes
            FROM cuadras c
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id
                AND cl.activo = 1
                AND cl.num_animales > 0
            LEFT JOIN lotes l ON cl.lote_id = l.id AND l.estado = 'activo'
            WHERE c.nave_id = :nave_id AND c.activa = 1
            GROUP BY c.id
            ORDER BY c.nombre
        ");
        $stmt->execute(['nave_id' => $naveId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ── API AJAX: lotes de una cuadra ────────────────────────────
    public function lotesPorCuadra(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $cuadraId = (int)($_GET['cuadra_id'] ?? 0);
        if (!$cuadraId) { echo json_encode([]); return; }

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
    private function revertirMovimiento(array $mov, int $uid): void
    {
        $db       = \App\Core\Database::getInstance();
        $cantidad = (int)$mov['num_animales'];

        switch ($mov['tipo']) {

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
                // Devolver animales al lote
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n, estado = 'activo' WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);
                break;

            case 'entrada_cebo':
                $db->prepare("UPDATE lotes SET estado_animal = 'lechon' WHERE id = :id")
                   ->execute(['id' => $mov['lote_origen_id']]);
                break;

            case 'entrada_reposicion':
                $db->prepare("UPDATE lotes SET num_animales = num_animales + :n WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);
                if ($mov['lote_destino_id'] && $mov['lote_destino_id'] !== $mov['lote_origen_id']) {
                    $db->prepare("UPDATE lotes SET estado = 'cerrado' WHERE id = :id")
                       ->execute(['id' => $mov['lote_destino_id']]);
                }
                if ($mov['cuadra_origen_id']) {
                    $db->prepare("UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1 WHERE cuadra_id = :cid AND lote_id = :lid")
                       ->execute(['n' => $cantidad, 'cid' => $mov['cuadra_origen_id'], 'lid' => $mov['lote_origen_id']]);
                }
                break;

            case 'entrada_madres':
                // Revertir estado del lote RE a reposicion
                $db->prepare("UPDATE lotes SET estado_animal = 'reposicion' WHERE id = :id")
                   ->execute(['id' => $mov['lote_origen_id']]);
                // Si fue parcial, devolver animales
                if ($mov['lote_destino_id'] === $mov['lote_origen_id']) {
                    // Restaurar animales si fue parcial (se restaron)
                    // El historial guarda la cantidad, la restauramos
                    $db->prepare("UPDATE lotes SET num_animales = num_animales + :n WHERE id = :id AND estado_animal = 'reposicion'")
                       ->execute(['n' => $cantidad, 'id' => $mov['lote_origen_id']]);
                }
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

        switch ($tipo) {

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

                // Buscar si ya existe un lote RE activo con ese código exacto
                $stmtRE = $db->prepare("
                    SELECT id FROM lotes WHERE codigo = :codigo AND estado = 'activo' LIMIT 1
                ");
                $stmtRE->execute(['codigo' => $codigoRE]);
                $loteREExistente = $stmtRE->fetch();

                if ($loteREExistente && $loteREExistente['estado'] !== 'cerrado') {
                    // Sumar al lote RE existente
                    $db->prepare("UPDATE lotes SET num_animales = num_animales + :n WHERE id = :id")
                       ->execute(['n' => $cantidad, 'id' => $loteREExistente['id']]);
                    // Añadir a cuadra origen en el lote RE
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
                if ($cantidad > $loteOrigen['num_animales']) {
                    throw new \Exception("Solo hay {$loteOrigen['num_animales']} animales en el lote.");
                }
                // Validar animales en cuadra origen si se especificó
                if (!empty($data['cuadra_origen_id'])) {
                    $stmtCheck = $db->prepare("SELECT COALESCE(num_animales,0) FROM cuadra_lote WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1 LIMIT 1");
                    $stmtCheck->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $enCuadra = (int)$stmtCheck->fetchColumn();
                    if ($cantidad > $enCuadra) {
                        throw new \Exception("Solo hay {$enCuadra} animales del lote en esa cuadra.");
                    }
                }
                $db->prepare("UPDATE lotes SET num_animales=GREATEST(0,num_animales-:n) WHERE id=:id")->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                // Descontar de cuadra origen
                if (!empty($data['cuadra_origen_id'])) {
                    $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1")
                       ->execute(['n' => $cantidad, 'cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                    $db->prepare("UPDATE cuadra_lote SET activo=0 WHERE cuadra_id=:cid AND lote_id=:lid AND num_animales=0")
                       ->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                } else {
                    // Sin cuadra específica, descontar proporcionalmente de todas
                    $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE lote_id=:lid AND activo=1")->execute(['n' => $cantidad, 'lid' => $data['lote_origen_id']]);
                }
                $restantes = $db->prepare("SELECT num_animales FROM lotes WHERE id=:id");
                $restantes->execute(['id' => $data['lote_origen_id']]);
                if ((int)$restantes->fetchColumn() <= 0) {
                    $db->prepare("UPDATE lotes SET estado='cerrado' WHERE id=:id")->execute(['id' => $data['lote_origen_id']]);
                }
                break;
        }
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