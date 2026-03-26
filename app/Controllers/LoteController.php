<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Lote;
use App\Models\Nave;
use App\Models\Granja;
use App\Models\RazaPorcino;
use App\Core\Session;

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
        $lotes = $this->model->allByUsuario(Session::get('usuario_id'));
        $this->view('lotes/index', ['lotes' => $lotes, 'pageTitle' => 'Lotes']);
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

        $naveId   = $this->post('nave_id') ?: null;
        $granjaId = $this->post('granja_id') ?: null;

        $this->model->create([
            'nave_id'          => $naveId ? (int)$naveId : null,
            'granja_id'        => $granjaId ? (int)$granjaId : null,
            'tipo_animal_id'   => (int)$this->post('tipo_animal_id'),
            'raza_id'          => $razaId,
            'codigo'           => $codigo,
            'num_animales'     => (int)$this->post('num_animales', 0),
            'peso_entrada_kg'  => (float)$this->post('peso_entrada_kg', 0),
            'fecha_entrada'    => $this->postString('fecha_entrada') ?: date('Y-m-d'),
            'fecha_nacimiento' => $fechaNac,
            'observaciones'    => $this->postString('observaciones'),
        ]);

        $loteId = \App\Core\Database::getInstance()->lastInsertId();

        // Asignar cuadras si se distribuyó
        $cuadrasIds  = $_POST['cuadras_asig_id']  ?? [];
        $cuadrasNums = $_POST['cuadras_asig_num'] ?? [];
        if (!empty($cuadrasIds)) {
            $cuadraModel = new \App\Models\Cuadra();
            foreach ($cuadrasIds as $i => $cuadraId) {
                $num = (int)($cuadrasNums[$i] ?? 0);
                if ($num > 0) {
                    $cuadraModel->asignarLote((int)$cuadraId, (int)$loteId, $num, date('Y-m-d'));
                }
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

        $this->view('lotes/form', [
            'lote'                 => $lote,
            'naves'                => $this->naveModel->selectOptions($uid),
            'granjas'              => $granjas,
            'granjaPreseleccionada'=> null,
            'tipos'                => $this->model->tiposAnimal(),
            'tiposPorGranja'       => $tiposPorGranja,
            'razas'                => $this->razaModel->allParaUsuario($uid),
            'cuadrasAsig'          => $cuadrasAsig,
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

        $naveId   = $this->post('nave_id') ?: null;
        $granjaId = $this->post('granja_id') ?: null;
        $codigoManual = trim($this->postString('codigo_manual'));

        $this->model->update((int)$id, Session::get('usuario_id'), [
            'nave_id'          => $naveId ? (int)$naveId : null,
            'granja_id'        => $granjaId ? (int)$granjaId : null,
            'tipo_animal_id'   => (int)$this->post('tipo_animal_id'),
            'raza_id'          => $this->post('raza_id') ? (int)$this->post('raza_id') : null,
            'codigo'           => !empty($codigoManual) ? $codigoManual : null,
            'num_animales'     => (int)$this->post('num_animales', 0),
            'peso_entrada_kg'  => (float)$this->post('peso_entrada_kg', 0),
            'fecha_entrada'    => date('Y-m-d'),
            'fecha_nacimiento' => $this->postString('fecha_nacimiento') ?: null,
            'observaciones'    => $this->postString('observaciones'),
        ]);

        Session::flash('success', 'Lote actualizado.');
        $this->redirect('lotes');
    }

    public function ajustar(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('lotes');
        }
        $cantidad = abs((int)$this->post('cantidad', 0));
        $tipo     = $this->postString('tipo');
        if ($cantidad > 0 && in_array($tipo, ['añadir', 'reducir'])) {
            $this->model->ajustarAnimales((int)$id, $cantidad, $tipo);
            $accion = $tipo === 'añadir' ? 'añadidos' : 'reducidos';
            Session::flash('success', "{$cantidad} animales {$accion} correctamente.");
        }
        $this->redirect('lotes');
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
        $this->model->delete((int)$id, Session::get('usuario_id'));
        Session::flash('success', 'Lote cerrado.');
        $this->redirect('lotes');
    }
}