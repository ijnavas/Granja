<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Silo;
use App\Models\Usuario;
use App\Core\Session;
use App\Core\Paginator;
use App\Core\AuditLog;

class AlmacenController extends BaseController
{
    private Silo $model;

    public function __construct()
    {
        $this->model = new Silo();
    }

    public function index(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        $filtros = array_filter([
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'silo_id'     => trim($_GET['silo_id']     ?? ''),
            'tipo_pienso' => trim($_GET['tipo_pienso'] ?? ''),
        ]);

        $page       = max(1, (int)($_GET['page'] ?? 1));
        $total      = $this->model->countRecargasUsuario($uid, $filtros);
        $paginacion = new Paginator($total, $page, 50);

        $this->view('almacen/index', [
            'silos'      => $this->model->allByUsuario($uid),
            'recargas'   => $this->model->allRecargasUsuario($uid, $filtros, $paginacion->perPage, $paginacion->offset),
            'filtros'    => $filtros,
            'paginacion' => $paginacion,
            'pageTitle'  => 'Almacén de pienso',
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ]);
    }

    public function show(string $id): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $silo = $this->model->find((int)$id, $uid);
        if (!$silo) $this->redirect('almacen');

        $usuario = (new Usuario())->findById($uid);

        $this->view('almacen/show', [
            'silo'         => $silo,
            'recargas'     => $this->model->recargas((int)$id),
            'proyeccion'   => $this->model->proyeccionConsumo((int)$id),
            'emailPedidos' => $usuario['email_pedidos'] ?? '',
            'pageTitle'    => e($silo['nombre']) . ' — Almacén',
            'success'      => Session::getFlash('success'),
            'error'        => Session::getFlash('error'),
        ]);
    }

    public function storeRecarga(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("almacen/{$id}");
        }

        $uid  = Session::get('usuario_id');
        $silo = $this->model->find((int)$id, $uid);
        if (!$silo) $this->redirect('almacen');

        $cantidad   = (float) $this->postString('cantidad_kg');
        $fecha      = $this->postString('fecha') ?: date('Y-m-d');
        $tipoPienso = $this->postString('tipo_pienso') ?: null;
        $proveedor  = $this->postString('proveedor') ?: null;
        $obs        = $this->postString('observaciones') ?: null;

        if ($cantidad <= 0) {
            Session::flash('error', 'La cantidad debe ser mayor que 0.');
            $this->redirect("almacen/{$id}");
        }

        // Validación de capacidad: stock_real (tras replay) + nueva recarga no debe
        // superar la capacidad del silo. Margen del 5% para tolerar pequeñas
        // discrepancias de medición/tabla de consumo.
        $capacidad = (float)($silo['capacidad_kg'] ?? 0);
        if ($capacidad > 0) {
            // $silo['stock_actual_kg'] ya viene enriquecido con el stock real
            // (ver Silo::withStockReal).
            $stockReal = (float)$silo['stock_actual_kg'];
            $margen    = $capacidad * 1.05;
            if ($stockReal + $cantidad > $margen) {
                $disponible = max(0, $capacidad - $stockReal);
                Session::flash(
                    'error',
                    'La cantidad supera la capacidad del silo. '
                    . 'Stock actual: ' . number_format($stockReal, 0) . ' kg · '
                    . 'Capacidad: ' . number_format($capacidad, 0) . ' kg · '
                    . 'Disponible: ' . number_format($disponible, 0) . ' kg.'
                );
                $this->redirect("almacen/{$id}");
            }
        }

        $this->model->addRecarga((int)$id, $cantidad, $fecha, $proveedor, $obs, $uid, $tipoPienso);
        $recargaId = (int)\App\Core\Database::getInstance()->lastInsertId();
        AuditLog::log('recarga', $recargaId, 'create', null, [
            'silo_id'       => (int)$id,
            'cantidad_kg'   => $cantidad,
            'fecha'         => $fecha,
            'tipo_pienso'   => $tipoPienso,
            'proveedor'     => $proveedor,
            'observaciones' => $obs,
        ]);
        Session::flash('success', number_format($cantidad, 0) . ' kg añadidos al silo.');
        $this->redirect("almacen/{$id}");
    }

    public function editRecarga(string $recargaId): void
    {
        auth_required();
        $uid     = Session::get('usuario_id');
        $recarga = $this->model->findRecarga((int)$recargaId);
        if (!$recarga) $this->redirect('almacen');

        // Verificar que pertenece al usuario
        $silo = $this->model->find((int)$recarga['silo_id'], $uid);
        if (!$silo) $this->redirect('almacen');

        $this->view('almacen/edit_recarga', [
            'recarga'   => $recarga,
            'silos'     => $this->model->allByUsuario($uid),
            'pageTitle' => 'Editar recarga',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function updateRecarga(string $recargaId): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('almacen');
        }

        $uid     = Session::get('usuario_id');
        $recarga = $this->model->findRecarga((int)$recargaId);
        if (!$recarga) $this->redirect('almacen');

        $silo = $this->model->find((int)$recarga['silo_id'], $uid);
        if (!$silo) $this->redirect('almacen');

        $nuevoSiloId = (int)$this->post('silo_id');
        // Verificar que el nuevo silo también pertenece al usuario
        $nuevoSilo = $this->model->find($nuevoSiloId, $uid);
        if (!$nuevoSilo) {
            Session::flash('error', 'Silo no válido.');
            $this->redirect("almacen/recargas/{$recargaId}/editar");
        }

        $cantidad = (float)str_replace(',', '.', $this->postString('cantidad_kg'));
        if ($cantidad <= 0) {
            Session::flash('error', 'La cantidad debe ser mayor que 0.');
            $this->redirect("almacen/recargas/{$recargaId}/editar");
        }

        // Albarán: la BD no tiene columna dedicada, se prefija en observaciones.
        $albaran      = $this->postString('albaran');
        $observaciones = $this->postString('observaciones') ?: null;
        if ($albaran) {
            $observaciones = "Albarán: {$albaran}" . ($observaciones ? " · {$observaciones}" : '');
        }

        $datos = [
            'silo_id'      => $nuevoSiloId,
            'fecha'        => $this->postString('fecha') ?: date('Y-m-d'),
            'cantidad_kg'  => $cantidad,
            'tipo_pienso'  => $this->postString('tipo_pienso') ?: null,
            'proveedor'    => $this->postString('proveedor') ?: null,
            'observaciones'=> $observaciones,
        ];
        $this->model->updateRecarga((int)$recargaId, $datos, (float)$recarga['cantidad_kg'], (int)$recarga['silo_id']);
        AuditLog::log('recarga', (int)$recargaId, 'update', $recarga, $datos);

        Session::flash('success', 'Recarga actualizada correctamente.');
        $this->redirect('almacen');
    }

    public function deleteRecarga(string $id, string $recargaId): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('almacen');
        }
        $uid  = Session::get('usuario_id');
        // IDOR guard: el silo debe ser del usuario
        $silo = $this->model->find((int)$id, $uid);
        if (!$silo) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'Silo', 'target_id' => (int)$id,
                'context' => 'almacen/deleteRecarga',
            ]);
            $this->redirect('almacen');
        }
        // Y la recarga debe pertenecer a ese silo
        $recarga = $this->model->findRecarga((int)$recargaId);
        if (!$recarga || (int)$recarga['silo_id'] !== (int)$id) {
            \App\Core\SecurityLog::log('idor_attempt', [
                'user_id' => $uid, 'resource' => 'SiloRecarga', 'target_id' => (int)$recargaId,
                'context' => 'almacen/deleteRecarga',
            ]);
            $this->redirect("almacen/{$id}");
        }

        $this->model->deleteRecarga((int)$recargaId, (int)$id);
        AuditLog::log('recarga', (int)$recargaId, 'delete', $recarga, null);
        Session::flash('success', 'Recarga eliminada y stock revertido.');
        $this->redirect("almacen/{$id}");
    }

    public function pedido(string $id): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect("almacen/{$id}");
        }

        $uid  = Session::get('usuario_id');
        $silo = $this->model->find((int)$id, $uid);
        if (!$silo) $this->redirect('almacen');

        $usuario   = (new Usuario())->findById($uid);
        $emailDest = filter_var(trim($this->postString('email_pedido')), FILTER_VALIDATE_EMAIL);

        if (!$emailDest) {
            Session::flash('error', 'Email de destino no válido.');
            $this->redirect("almacen/{$id}");
        }

        $proy        = $this->model->proyeccionConsumo((int)$id);
        $cantidadSug = max(0, (float)$silo['capacidad_kg'] - (float)$silo['stock_actual_kg']);
        $fechaMinStr = $proy['fecha_minimo'] ? date('d/m/Y', strtotime($proy['fecha_minimo'])) : '—';

        $asunto = "Pedido pienso — Silo {$silo['nombre']} ({$silo['granja_nombre']})";

        $filasLotes = '';
        foreach ($proy['lotes'] as $l) {
            $filasLotes .= "<tr style='border-bottom:1px solid #e5e7eb'>
                <td style='padding:5px 10px'>" . htmlspecialchars($l['codigo'] ?? '') . "</td>
                <td style='padding:5px 10px'>" . htmlspecialchars($l['nave_nombre'] ?? '') . "</td>
                <td style='padding:5px 10px;text-align:center'>S" . ($l['semana_actual'] ?? '?') . "</td>
                <td style='padding:5px 10px;text-align:right'>" . number_format((int)$l['num_animales']) . "</td>
                <td style='padding:5px 10px;text-align:right'>" . number_format((float)($l['consumo_diario_kg'] ?? 0), 2) . " kg/día</td>
            </tr>";
        }

        $html = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;color:#111'>
            <h2 style='color:#1e3a5f'>Pedido de pienso</h2>
            <table style='border-collapse:collapse;margin-bottom:1.5rem'>
                <tr><td style='padding:4px 12px 4px 0;color:#6b7280'>Silo:</td><td style='font-weight:600'>" . htmlspecialchars($silo['nombre']) . "</td></tr>
                <tr><td style='padding:4px 12px 4px 0;color:#6b7280'>Granja:</td><td>" . htmlspecialchars($silo['granja_nombre']) . "</td></tr>
                <tr><td style='padding:4px 12px 4px 0;color:#6b7280'>Stock actual:</td><td style='font-weight:600'>" . number_format((float)$silo['stock_actual_kg'], 0) . " kg</td></tr>
                <tr><td style='padding:4px 12px 4px 0;color:#6b7280'>Stock mínimo:</td><td>" . number_format((float)$silo['stock_minimo_kg'], 0) . " kg</td></tr>
                <tr><td style='padding:4px 12px 4px 0;color:#6b7280'>Consumo estimado:</td><td>" . number_format((float)$proy['consumo_diario_kg'], 0) . " kg/día</td></tr>
                <tr><td style='padding:4px 12px 4px 0;color:#6b7280'>Fecha estimada mínimo:</td><td style='color:#dc2626;font-weight:600'>{$fechaMinStr}</td></tr>
                <tr><td style='padding:4px 12px 4px 0;color:#6b7280'>Cantidad sugerida:</td><td style='font-weight:700;color:#1e3a5f'>" . number_format($cantidadSug, 0) . " kg</td></tr>
            </table>
            <h3 style='color:#374151;font-size:14px'>Lotes en consumo</h3>
            <table style='border-collapse:collapse;width:100%;font-size:12px'>
                <thead><tr style='background:#1e3a5f;color:#fff'>
                    <th style='padding:6px 10px;text-align:left'>Lote</th>
                    <th style='padding:6px 10px;text-align:left'>Nave</th>
                    <th style='padding:6px 10px;text-align:center'>Sem.</th>
                    <th style='padding:6px 10px;text-align:right'>Animales</th>
                    <th style='padding:6px 10px;text-align:right'>Consumo</th>
                </tr></thead>
                <tbody>{$filasLotes}</tbody>
            </table>
            <p style='font-size:11px;color:#9ca3af;margin-top:24px'>Generado por BALTAE · granja.baltae.com</p>
        </body></html>";

        $headers = "From: BALTAE <no-reply@baltae.com>\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "MIME-Version: 1.0\r\n";

        if (mail($emailDest, $asunto, $html, $headers)) {
            if ($this->postString('guardar_email') === '1') {
                (new Usuario())->updateEmailPedidos($uid, $emailDest);
            }
            Session::flash('success', "Pedido enviado a {$emailDest}.");
        } else {
            Session::flash('error', 'Error al enviar el email.');
        }

        $this->redirect("almacen/{$id}");
    }
}
