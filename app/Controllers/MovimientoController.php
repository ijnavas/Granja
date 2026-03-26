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

        $this->model->update((int)$id, $data, $uid);
        Session::flash('success', 'Movimiento actualizado.');
        $this->redirect('movimientos');
    }

    // ── Eliminar ─────────────────────────────────────────────────
    public function delete(string $id): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->model->delete((int)$id, $uid);
        Session::flash('success', 'Movimiento eliminado.');
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
            SELECT c.id, c.nombre,
                   GROUP_CONCAT(DISTINCT l.codigo SEPARATOR ', ') AS lotes
            FROM cuadras c
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id
            LEFT JOIN lotes l ON cl.lote_id = l.id AND l.estado = 'activo'
            WHERE c.nave_id = :nave_id
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
            JOIN cuadra_lote cl ON cl.lote_id = l.id AND cl.cuadra_id = :cuadra_id
            WHERE l.estado = 'activo'
        ");
        $stmt->execute(['cuadra_id' => $cuadraId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ── Lógica de efectos ────────────────────────────────────────
    private function aplicarMovimiento(string $tipo, array &$data, int $uid): void
    {
        $db         = \App\Core\Database::getInstance();
        $loteOrigen = $this->loteModel->find($data['lote_origen_id'], $uid);
        if (!$loteOrigen) throw new \Exception('Lote de origen no encontrado.');

        $cantidad = $data['num_animales'];

        switch ($tipo) {

            case 'traslado_cuadra':
                if (!$data['cuadra_destino_id']) throw new \Exception('Selecciona una cuadra destino.');

                // Restar animales de la cuadra origen
                if ($data['cuadra_origen_id']) {
                    $db->prepare("
                        UPDATE cuadra_lote
                        SET num_animales = GREATEST(0, num_animales - :n)
                        WHERE cuadra_id = :cid AND lote_id = :lid AND activo = 1
                    ")->execute(['n' => $cantidad, 'cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);

                    // Si quedan 0, desactivar
                    $db->prepare("
                        UPDATE cuadra_lote SET activo = 0
                        WHERE cuadra_id = :cid AND lote_id = :lid AND num_animales = 0
                    ")->execute(['cid' => $data['cuadra_origen_id'], 'lid' => $data['lote_origen_id']]);
                }

                // Obtener nave de la cuadra destino y actualizar nave del lote
                $naveDestino = $db->prepare("SELECT nave_id FROM cuadras WHERE id = :id");
                $naveDestino->execute(['id' => $data['cuadra_destino_id']]);
                $naveId = $naveDestino->fetchColumn();

                // Añadir animales a la cuadra destino
                $existeDestino = $db->prepare("
                    SELECT id FROM cuadra_lote
                    WHERE cuadra_id = :cid AND lote_id = :lid LIMIT 1
                ");
                $existeDestino->execute(['cid' => $data['cuadra_destino_id'], 'lid' => $data['lote_origen_id']]);
                $clId = $existeDestino->fetchColumn();

                if ($clId) {
                    $db->prepare("
                        UPDATE cuadra_lote SET num_animales = num_animales + :n, activo = 1
                        WHERE id = :id
                    ")->execute(['n' => $cantidad, 'id' => $clId]);
                } else {
                    $db->prepare("
                        INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada)
                        VALUES (:cid, :lid, :n, CURDATE())
                    ")->execute(['cid' => $data['cuadra_destino_id'], 'lid' => $data['lote_origen_id'], 'n' => $cantidad]);
                }

                // Solo actualizar nave del lote si ya no quedan animales en la nave origen
                if ($naveId && $loteOrigen['nave_id'] && $naveId != $loteOrigen['nave_id']) {
                    $stmtResto = $db->prepare("
                        SELECT COALESCE(SUM(cl.num_animales), 0)
                        FROM cuadra_lote cl
                        JOIN cuadras c ON cl.cuadra_id = c.id
                        WHERE cl.lote_id = :lid AND c.nave_id = :nid AND cl.activo = 1
                    ");
                    $stmtResto->execute(['lid' => $data['lote_origen_id'], 'nid' => $loteOrigen['nave_id']]);
                    if ((int)$stmtResto->fetchColumn() === 0) {
                        $db->prepare("UPDATE lotes SET nave_id = :nave WHERE id = :id")
                           ->execute(['nave' => $naveId, 'id' => $data['lote_origen_id']]);
                    }
                }
                break;

            case 'entrada_cebo':
                $db->prepare("UPDATE lotes SET estado_animal = 'cebo' WHERE id = :id")
                   ->execute(['id' => $data['lote_origen_id']]);
                break;

            case 'entrada_reposicion':
                $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                $codigoBase = preg_replace('/ [A-Z]+$/', '', $loteOrigen['codigo']) . ' RE';
                $nuevoId = $this->crearSubLote($loteOrigen, $codigoBase, $cantidad, 'reposicion', $uid);
                $data['lote_destino_id'] = $nuevoId;
                break;

            case 'entrada_madres':
                $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                $codigoBase = preg_replace('/ RE$/', '', $loteOrigen['codigo']) . ' MA';
                $nuevoId = $this->crearSubLote($loteOrigen, $codigoBase, $cantidad, 'madre', $uid);
                $data['lote_destino_id'] = $nuevoId;
                break;

            case 'venta':
                $db->prepare("UPDATE lotes SET num_animales = GREATEST(0, num_animales - :n) WHERE id = :id")
                   ->execute(['n' => $cantidad, 'id' => $data['lote_origen_id']]);
                // Reducir también en cuadras
                $db->prepare("
                    UPDATE cuadra_lote SET num_animales = GREATEST(0, num_animales - :n)
                    WHERE lote_id = :lid AND activo = 1
                ")->execute(['n' => $cantidad, 'lid' => $data['lote_origen_id']]);
                // Cerrar lote si no quedan animales
                $restantes = $db->prepare("SELECT num_animales FROM lotes WHERE id = :id");
                $restantes->execute(['id' => $data['lote_origen_id']]);
                if ((int)$restantes->fetchColumn() <= 0) {
                    $db->prepare("UPDATE lotes SET estado = 'cerrado' WHERE id = :id")
                       ->execute(['id' => $data['lote_origen_id']]);
                }
                break;
        }
    }

    private function crearSubLote(array $origen, string $codigo, int $numAnimales, string $estadoAnimal, int $uid): int
    {
        $db = \App\Core\Database::getInstance();
        // Evitar código duplicado
        $check = $db->prepare("SELECT COUNT(*) FROM lotes WHERE codigo = :c");
        $check->execute(['c' => $codigo]);
        if ((int)$check->fetchColumn() > 0) {
            $codigo .= '-' . date('mdH');
        }

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
        return (int) $db->lastInsertId();
    }
}