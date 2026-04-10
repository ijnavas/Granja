<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Inventario;
use App\Models\Silo;
use App\Core\Session;

class InventarioController extends BaseController
{
    private Inventario $model;

    public function __construct()
    {
        $this->model = new Inventario();
    }

    // ── Listado ──────────────────────────────────────────────────
    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $this->view('inventarios/index', [
            'inventarios' => $this->model->allByUsuario($uid),
            'pageTitle'   => 'Inventarios',
            'success'     => Session::getFlash('success'),
            'error'       => Session::getFlash('error'),
        ]);
    }

    // ── Crear (formulario + previsualización AJAX) ────────────────
    public function create(): void
    {
        auth_required();
        $this->view('inventarios/form', [
            'pageTitle' => 'Nuevo inventario',
            'error'     => Session::getFlash('error'),
        ]);
    }

    // ── API: previsualización de líneas por fecha ─────────────────
    public function preview(): void
    {
        auth_required();
        header('Content-Type: application/json');
        $uid  = Session::get('usuario_id');
        $tipo = $_GET['tipo'] ?? 'cuadra';

        if ($tipo === 'pienso') {
            $lineas = $this->model->calcularLineasPienso($uid);
        } else {
            $fecha  = $_GET['fecha'] ?? date('Y-m-d');
            $lineas = $this->model->calcularLineas($uid, $fecha, $tipo);
        }
        echo json_encode($lineas);
    }

    // ── Guardar ───────────────────────────────────────────────────
    public function store(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('inventarios/crear');
        }

        $uid    = Session::get('usuario_id');
        $fecha  = $this->postString('fecha') ?: date('Y-m-d');
        $nombre = trim($this->postString('nombre')) ?: null;
        $tipo   = in_array($this->postString('tipo'), ['cuadra', 'global', 'pienso'])
                    ? $this->postString('tipo') : 'cuadra';

        if ($tipo === 'pienso') {
            $silos = $this->model->calcularLineasPienso($uid);
            if (empty($silos)) {
                Session::flash('error', 'No hay silos activos configurados.');
                $this->redirect('inventarios/crear');
            }
            $id = $this->model->create($uid, $fecha, $nombre, 'pienso');
            $colsSilo = ['silo_id','silo_nombre','granja_nombre','tipo_pienso',
                         'stock_kg','capacidad_kg','stock_minimo_kg','pct_stock'];
            foreach ($silos as $s) {
                $linea = array_intersect_key($s, array_flip($colsSilo));
                $this->model->insertLineaSilo($id, $linea);
            }
            Session::flash('success', 'Inventario de pienso generado correctamente.');
            $this->redirect("inventarios/{$id}");
        }

        $lineas = $this->model->calcularLineas($uid, $fecha, $tipo);
        if (empty($lineas)) {
            Session::flash('error', 'No hay lotes activos para esa fecha.');
            $this->redirect('inventarios/crear');
        }

        // Columnas reales de inventario_lineas (excluir claves de preview)
        $colsPermitidas = ['lote_id','cuadra_id','nave_id','granja_id','estado_animal',
                           'num_animales','peso_kg','peso_total_kg','coste_eur','valor_total_eur','semana_tabla'];

        $id = $this->model->create($uid, $fecha, $nombre, $tipo);
        foreach ($lineas as $l) {
            // Quitar claves de preview (_* y campos auxiliares no almacenados)
            $linea = array_intersect_key($l, array_flip($colsPermitidas));
            $this->model->insertLinea($id, $linea);
        }

        Session::flash('success', 'Inventario generado correctamente.');
        $this->redirect("inventarios/{$id}");
    }

    // ── Ver detalle ───────────────────────────────────────────────
    public function show(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $inv  = $this->model->find((int)$id, $uid);
        if (!$inv) $this->redirect('inventarios');

        $esPienso = ($inv['tipo'] ?? '') === 'pienso';
        $this->view('inventarios/show', [
            'inventario'   => $inv,
            'lineas'       => $esPienso ? [] : $this->model->lineas((int)$id),
            'lineas_silos' => $esPienso ? $this->model->lineasSilos((int)$id) : [],
            'pageTitle'    => 'Inventario ' . date('d/m/Y', strtotime($inv['fecha'])),
            'success'      => Session::getFlash('success'),
        ]);
    }

    // ── Exportar Excel (CSV) ──────────────────────────────────────
    public function excel(string $id): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $inv = $this->model->find((int)$id, $uid);
        if (!$inv) $this->redirect('inventarios');

        $lineas   = $this->model->lineas((int)$id);
        $esCuadra = ($inv['tipo'] ?? 'cuadra') === 'cuadra';
        $N        = \App\Core\CsvExport::class;

        $cabeceras = ['Granja', 'Nave'];
        if ($esCuadra) $cabeceras[] = 'Cuadra';
        array_push($cabeceras, 'Lote', 'Estado', 'Semana', 'Animales', 'Peso/ud (kg)', 'Peso total (kg)', 'Coste/ud (EUR)', 'Valor total (EUR)');

        $rows = [];
        foreach ($lineas as $l) {
            $fila = [$l['granja_nombre'] ?? '', $l['nave_nombre'] ?? ''];
            if ($esCuadra) $fila[] = $l['cuadra_nombre'] ?? '';
            $fila[] = $l['lote_codigo'];
            $fila[] = $l['estado_animal'] ?? '';
            $fila[] = $l['semana_tabla'] ? 'S' . $l['semana_tabla'] : '';
            $fila[] = $l['num_animales'];
            $fila[] = $l['peso_kg']         ? $N::num((float)$l['peso_kg'], 3) : '';
            $fila[] = $l['peso_total_kg']   ? $N::num((float)$l['peso_total_kg'], 1) : '';
            $fila[] = $l['coste_eur']       ? $N::num((float)$l['coste_eur']) : '';
            $fila[] = $l['valor_total_eur'] ? $N::num((float)$l['valor_total_eur']) : '';
            $rows[] = $fila;
        }

        $N::download('inventario_' . $inv['fecha'] . '.csv', $cabeceras, $rows);
    }

    // ── Enviar por email ──────────────────────────────────────────
    public function email(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("inventarios/{$id}");
        }

        $uid = Session::get('usuario_id');
        $inv = $this->model->find((int)$id, $uid);
        if (!$inv) $this->redirect('inventarios');

        $to = filter_var(trim($this->postString('email')), FILTER_VALIDATE_EMAIL);
        if (!$to) {
            Session::flash('error', 'Dirección de email no válida.');
            $this->redirect("inventarios/{$id}");
        }

        $lineas   = $this->model->lineas((int)$id);
        $esCuadra = ($inv['tipo'] ?? 'cuadra') === 'cuadra';
        $fecha    = date('d/m/Y', strtotime($inv['fecha']));
        $nombre   = $inv['nombre'] ? ' — ' . $inv['nombre'] : '';

        $totalAnimales = array_sum(array_column($lineas, 'num_animales'));
        $totalValor    = array_sum(array_column($lineas, 'valor_total_eur'));

        $T = \App\Core\EmailTemplate::class;

        $subtitulo = count($lineas) . ' lineas &middot; '
            . number_format($totalAnimales) . ' animales &middot; '
            . ($totalValor ? number_format($totalValor, 2) . ' EUR' : 'sin valor');

        $bodyHtml = $T::title("Inventario {$fecha}{$nombre}", $subtitulo);

        // Tabla de lineas
        $headers_t = ['Granja', 'Nave' . ($esCuadra ? ' / Cuadra' : ''), 'Lote', 'Estado', 'Sem.', 'Animales', 'Peso (kg)', 'Valor (EUR)'];
        $aligns    = ['left', 'left', 'left', 'left', 'center', 'right', 'right', 'right'];
        $rows      = [];
        foreach ($lineas as $l) {
            $ubi = $l['nave_nombre'] ?? '—';
            if ($esCuadra && $l['cuadra_nombre']) $ubi .= ' · ' . $l['cuadra_nombre'];
            $rows[] = [
                e($l['granja_nombre'] ?? ''),
                '<span style="color:#6b7280">' . e($ubi) . '</span>',
                '<span style="font-family:monospace;font-weight:600;color:#1d4ed8">' . e($l['lote_codigo']) . '</span>',
                e($l['estado_animal'] ?? ''),
                $l['semana_tabla'] ? 'S' . $l['semana_tabla'] : '—',
                '<strong>' . number_format((int)$l['num_animales']) . '</strong>',
                $l['peso_total_kg'] ? number_format((float)$l['peso_total_kg'], 1) : '—',
                $l['valor_total_eur']
                    ? '<strong style="color:#166534">' . number_format((float)$l['valor_total_eur'], 2) . '</strong>'
                    : '—',
            ];
        }
        $bodyHtml .= $T::table($headers_t, $rows, $aligns);

        $html    = $T::build($bodyHtml, 'blue');
        $subject = "Inventario {$fecha}{$nombre}";

        try {
            $ok = (new \App\Core\Mailer())->send($to, $subject, $html, true);
        } catch (\Throwable $e) {
            error_log("[InventarioController] email error: " . $e->getMessage());
            $ok = false;
        }

        if ($ok) {
            Session::flash('success', "Inventario enviado a {$to}.");
        } else {
            Session::flash('error', 'Error al enviar el email. Comprueba la configuracion del servidor.');
        }
        $this->redirect("inventarios/{$id}");
    }

    // ── Eliminar ──────────────────────────────────────────────────
    public function delete(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('inventarios');
        }
        $uid = Session::get('usuario_id');
        // IDOR guard
        $inv = $this->model->find((int)$id, $uid);
        if (!$inv) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Inventario', 'target_id' => (int)$id,
            ]);
            $this->redirect('inventarios');
        }
        $this->model->delete((int)$id);
        Session::flash('success', 'Inventario eliminado.');
        $this->redirect('inventarios');
    }
}
