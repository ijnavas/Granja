<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Informe;
use App\Models\Lote;
use App\Models\Nave;
use App\Core\Session;

class InformeController extends BaseController
{
    private Informe $informeModel;
    private Lote    $loteModel;
    private Nave    $naveModel;

    public function __construct()
    {
        $this->informeModel = new Informe();
        $this->loteModel    = new Lote();
        $this->naveModel    = new Nave();
    }

    public function index(): void
    {
        auth_required();
        $uid = (int) Session::get('usuario_id');

        $filtros = [
            'dimension'   => trim($_GET['dimension']   ?? 'tipo_movimiento'),
            'categoria'   => trim($_GET['categoria']   ?? 'todos'),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'estados'     => array_filter((array)($_GET['estados'] ?? [])),
            'naves'       => array_map('intval', array_filter((array)($_GET['naves'] ?? []))),
            'lotes'       => array_map('intval', array_filter((array)($_GET['lotes'] ?? []))),
        ];

        // Si no hay rango de fechas, por defecto últimos 90 días
        $tieneFiltros = !empty(array_filter([
            $filtros['fecha_desde'], $filtros['fecha_hasta'],
            $filtros['categoria'] !== 'todos' ? '1' : '',
            $filtros['estados'], $filtros['naves'], $filtros['lotes'],
        ], fn($v) => !empty($v)));

        $errorInforme = null;
        $resultado    = ['filas' => [], 'total' => [
            'num_movimientos' => 0, 'total_animales' => 0,
            'total_kg_canal' => 0, 'total_kg_real' => 0, 'total_eur' => 0,
        ]];
        try {
            $resultado = $this->informeModel->agregado($uid, $filtros);
        } catch (\Throwable $e) {
            error_log('Informe error: ' . $e->getMessage());
            $errorInforme = $e->getMessage();
        }

        $this->view('informes/index', [
            'pageTitle'    => 'Informes',
            'filtros'      => $filtros,
            'tieneFiltros' => $tieneFiltros,
            'dimensiones'  => Informe::DIMENSIONES,
            'categorias'   => Informe::CATEGORIAS_FILTRO,
            'estadosMap'   => Informe::ESTADOS_ANIMAL,
            'naves'        => $this->naveModel->allByUsuario($uid),
            'lotes'        => $this->loteModel->allByUsuario($uid),
            'filas'        => $resultado['filas'],
            'total'        => $resultado['total'],
            'errorInforme' => $errorInforme,
        ]);
    }
}
