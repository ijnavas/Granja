<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Inventario;
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
        $uid   = Session::get('usuario_id');
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $tipo  = $_GET['tipo']  ?? 'cuadra';
        $lineas = $this->model->calcularLineas($uid, $fecha, $tipo);
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
        $tipo   = in_array($this->postString('tipo'), ['cuadra', 'global']) ? $this->postString('tipo') : 'cuadra';

        $lineas = $this->model->calcularLineas($uid, $fecha, $tipo);
        if (empty($lineas)) {
            Session::flash('error', 'No hay lotes activos para esa fecha.');
            $this->redirect('inventarios/crear');
        }

        $id = $this->model->create($uid, $fecha, $nombre, $tipo);
        foreach ($lineas as $l) {
            // Quitar claves de preview (_*)
            $linea = array_filter($l, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
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

        $this->view('inventarios/show', [
            'inventario' => $inv,
            'lineas'     => $this->model->lineas((int)$id),
            'pageTitle'  => 'Inventario ' . date('d/m/Y', strtotime($inv['fecha'])),
            'success'    => Session::getFlash('success'),
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
        $filename = 'inventario_' . $inv['fecha'] . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

        $cabeceras = ['Granja', 'Nave'];
        if ($esCuadra) $cabeceras[] = 'Cuadra';
        array_push($cabeceras, 'Lote', 'Estado', 'Semana', 'Animales', 'Peso/ud (kg)', 'Peso total (kg)', 'Coste/ud (€)', 'Valor total (€)');
        fputcsv($out, $cabeceras, ';');

        foreach ($lineas as $l) {
            $fila = [$l['granja_nombre'] ?? '', $l['nave_nombre'] ?? ''];
            if ($esCuadra) $fila[] = $l['cuadra_nombre'] ?? '';
            $fila[] = $l['lote_codigo'];
            $fila[] = $l['estado_animal'] ?? '';
            $fila[] = $l['semana_tabla'] ? 'S' . $l['semana_tabla'] : '';
            $fila[] = $l['num_animales'];
            $fila[] = $l['peso_kg']         ? number_format((float)$l['peso_kg'], 3, ',', '')         : '';
            $fila[] = $l['peso_total_kg']   ? number_format((float)$l['peso_total_kg'], 1, ',', '')   : '';
            $fila[] = $l['coste_eur']       ? number_format((float)$l['coste_eur'], 2, ',', '')       : '';
            $fila[] = $l['valor_total_eur'] ? number_format((float)$l['valor_total_eur'], 2, ',', '') : '';
            fputcsv($out, $fila, ';');
        }
        fclose($out);
        exit;
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

        $thStyle = 'padding:6px 10px;background:#1e3a5f;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.04em;';
        $tdStyle = 'padding:5px 10px;font-size:12px;border-bottom:1px solid #e5e7eb;';

        $cabTh = "<th style='{$thStyle}text-align:left'>Granja</th>
                  <th style='{$thStyle}text-align:left'>Nave" . ($esCuadra ? " · Cuadra" : "") . "</th>
                  <th style='{$thStyle}text-align:left'>Lote</th>
                  <th style='{$thStyle}text-align:left'>Estado</th>
                  <th style='{$thStyle}text-align:center'>Sem.</th>
                  <th style='{$thStyle}text-align:right'>Animales</th>
                  <th style='{$thStyle}text-align:right'>Peso total (kg)</th>
                  <th style='{$thStyle}text-align:right'>Valor total (€)</th>";

        $filas = '';
        foreach ($lineas as $i => $l) {
            $bg  = $i % 2 === 0 ? '#fff' : '#f9fafb';
            $ubi = $l['nave_nombre'] ?? '—';
            if ($esCuadra && $l['cuadra_nombre']) $ubi .= ' · ' . $l['cuadra_nombre'];
            $filas .= "<tr style='background:{$bg}'>
                <td style='{$tdStyle}'>" . htmlspecialchars($l['granja_nombre'] ?? '') . "</td>
                <td style='{$tdStyle}color:#6b7280'>" . htmlspecialchars($ubi) . "</td>
                <td style='{$tdStyle}font-family:monospace;font-weight:600;color:#1d4ed8'>" . htmlspecialchars($l['lote_codigo']) . "</td>
                <td style='{$tdStyle}'>" . htmlspecialchars($l['estado_animal'] ?? '') . "</td>
                <td style='{$tdStyle}text-align:center;color:#9ca3af'>" . ($l['semana_tabla'] ? 'S' . $l['semana_tabla'] : '—') . "</td>
                <td style='{$tdStyle}text-align:right;font-weight:600'>" . number_format((int)$l['num_animales']) . "</td>
                <td style='{$tdStyle}text-align:right'>" . ($l['peso_total_kg'] ? number_format((float)$l['peso_total_kg'], 1) . ' kg' : '—') . "</td>
                <td style='{$tdStyle}text-align:right;font-weight:600;color:#166534'>" . ($l['valor_total_eur'] ? number_format((float)$l['valor_total_eur'], 2) . ' €' : '—') . "</td>
            </tr>";
        }

        $html = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;color:#111'>
            <h2 style='color:#1e3a5f'>Inventario {$fecha}{$nombre}</h2>
            <p style='color:#6b7280;font-size:13px'>" . count($lineas) . " líneas &nbsp;·&nbsp; " . number_format($totalAnimales) . " animales &nbsp;·&nbsp; " . ($totalValor ? number_format($totalValor, 2) . " €" : "sin valor") . "</p>
            <table style='border-collapse:collapse;width:100%'>
                <thead><tr>{$cabTh}</tr></thead>
                <tbody>{$filas}</tbody>
            </table>
            <p style='font-size:11px;color:#9ca3af;margin-top:24px'>Generado por BALTAE · granja.baltae.com</p>
        </body></html>";

        $subject = "Inventario {$fecha}{$nombre}";
        $headers = "From: BALTAE <no-reply@baltae.com>\r\n"
                 . "Reply-To: no-reply@baltae.com\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "MIME-Version: 1.0\r\n";

        if (mail($to, $subject, $html, $headers)) {
            Session::flash('success', "Inventario enviado a {$to}.");
        } else {
            Session::flash('error', 'Error al enviar el email. Comprueba la configuración del servidor.');
        }
        $this->redirect("inventarios/{$id}");
    }

    // ── Eliminar ──────────────────────────────────────────────────
    public function delete(string $id): void
    {
        auth_required();
        $uid = Session::get('usuario_id');
        $inv = $this->model->find((int)$id, $uid);
        if ($inv) $this->model->delete((int)$id);
        Session::flash('success', 'Inventario eliminado.');
        $this->redirect('inventarios');
    }
}
