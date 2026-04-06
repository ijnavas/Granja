<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ClaudeVision;
use App\Models\Lote;
use App\Models\Pesaje;
use App\Models\Silo;
use App\Core\Session;

class EscaneoController extends BaseController
{
    public function form(): void
    {
        auth_required();
        $this->view('escaneo/form', [
            'pageTitle' => 'Escanear cuaderno',
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function analizar(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('escaneo');
        }

        $file = $_FILES['foto'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'No se ha subido ninguna imagen.');
            $this->redirect('escaneo');
        }

        // Validar tipo
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMime)) {
            Session::flash('error', 'Solo se permiten imágenes JPG, PNG o WEBP.');
            $this->redirect('escaneo');
        }

        // Guardar imagen
        $uploadDir = ROOT_PATH . '/uploads/escaneos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Session::flash('error', 'Error al guardar la imagen.');
            $this->redirect('escaneo');
        }

        // Analizar con Claude Vision
        try {
            $vision  = new ClaudeVision();
            $datos   = $vision->analizarCuaderno($destPath);
        } catch (\Exception $e) {
            Session::flash('error', 'Error al analizar la imagen: ' . $e->getMessage());
            $this->redirect('escaneo');
        }

        // Cargar lotes y silos para el formulario de revisión
        $uid   = Session::get('usuario_id');
        $lotes = (new Lote())->allByUsuario($uid);
        $silos = (new Silo())->allByUsuario($uid);

        $this->view('escaneo/revision', [
            'pageTitle' => 'Revisar datos escaneados',
            'datos'     => $datos,
            'imagen'    => 'uploads/escaneos/' . $filename,
            'lotes'     => $lotes,
            'silos'     => $silos,
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function confirmar(): void
    {
        auth_required();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('escaneo');
        }

        $uid    = Session::get('usuario_id');
        $fecha  = $this->postString('fecha') ?: date('Y-m-d');
        $tipos          = $_POST['tipo']              ?? [];
        $loteIds        = $_POST['lote_id']           ?? [];
        $cantidades     = $_POST['cantidad']          ?? [];
        $motivos        = $_POST['motivo']            ?? [];
        $cuadraIds      = $_POST['cuadra_origen_id']  ?? [];
        $cuadraDestinoIds = $_POST['cuadra_destino_id'] ?? [];

        $registrados = 0;
        $errores     = [];

        $movModel = new \App\Models\Movimiento();
        $loteModel = new \App\Models\Lote();

        foreach ($loteIds as $i => $loteId) {
            $loteId   = (int)$loteId;
            $cantidad = (int)($cantidades[$i] ?? 0);
            $tipo     = $tipos[$i] ?? '';
            $cuadraId = (int)($cuadraIds[$i] ?? 0) ?: null;

            // Omitir si el checkbox de confirmar no está marcado
            if (!isset($_POST['confirmar'][$i])) continue;

            if (!$loteId) {
                $errores[] = "Fila " . ((int)$i + 1) . ": no se seleccionó ningún lote.";
                continue;
            }
            if ($cantidad <= 0 || !$tipo) continue;

            $cuadraDestinoId = (int)($cuadraDestinoIds[$i] ?? 0) ?: null;

            $data = [
                'tipo'              => $tipo,
                'fecha'             => $fecha,
                'lote_origen_id'    => $loteId,
                'lote_destino_id'   => null,
                'cuadra_origen_id'  => $cuadraId,
                'cuadra_destino_id' => $cuadraDestinoId,
                'num_animales'      => $cantidad,
                'peso_canal_kg'     => null,
                'precio_eur'        => null,
                'tipo_venta'        => null,
                'motivo_baja'       => $motivos[$i] ?? null,
                'observaciones'     => 'Registrado desde escaneo de cuaderno',
            ];

            try {
                $db = \App\Core\Database::getInstance();

                if ($tipo === 'baja') {
                    // Descuenta del lote
                    $db->prepare("UPDATE lotes SET num_animales=GREATEST(0,num_animales-:n) WHERE id=:id")
                       ->execute(['n' => $cantidad, 'id' => $loteId]);
                    // Descuenta de la cuadra origen
                    if ($cuadraId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1")
                           ->execute(['n' => $cantidad, 'cid' => $cuadraId, 'lid' => $loteId]);
                        $db->prepare("UPDATE cuadra_lote SET activo=0 WHERE cuadra_id=:cid AND lote_id=:lid AND num_animales=0")
                           ->execute(['cid' => $cuadraId, 'lid' => $loteId]);
                    }
                    // Cerrar lote si se queda vacío
                    $rest = $db->prepare("SELECT num_animales FROM lotes WHERE id=:id");
                    $rest->execute(['id' => $loteId]);
                    if ((int)$rest->fetchColumn() <= 0) {
                        $db->prepare("UPDATE lotes SET estado='cerrado', fecha_cierre=CURDATE() WHERE id=:id")
                           ->execute(['id' => $loteId]);
                    }

                } elseif ($tipo === 'traslado_cuadra') {
                    // El total del lote no cambia, solo mueve entre cuadras
                    if ($cuadraId) {
                        $db->prepare("UPDATE cuadra_lote SET num_animales=GREATEST(0,num_animales-:n) WHERE cuadra_id=:cid AND lote_id=:lid AND activo=1")
                           ->execute(['n' => $cantidad, 'cid' => $cuadraId, 'lid' => $loteId]);
                        $db->prepare("UPDATE cuadra_lote SET activo=0 WHERE cuadra_id=:cid AND lote_id=:lid AND num_animales=0")
                           ->execute(['cid' => $cuadraId, 'lid' => $loteId]);
                    }
                    if ($cuadraDestinoId) {
                        // Upsert en cuadra destino
                        $db->prepare("INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, activo)
                                      VALUES (:cid, :lid, :n, 1)
                                      ON DUPLICATE KEY UPDATE num_animales=num_animales+:n2, activo=1")
                           ->execute(['cid' => $cuadraDestinoId, 'lid' => $loteId, 'n' => $cantidad, 'n2' => $cantidad]);
                    }
                }

                $movModel->create($data, $uid);
                $registrados++;
            } catch (\Exception $e) {
                $errores[] = "Fila {$i}: " . $e->getMessage();
            }
        }

        // ── Pesajes ──────────────────────────────────────────────
        $pesajeModel    = new Pesaje();
        $loteIdsPesaje  = $_POST['lote_id_p']   ?? [];
        $cuadraIdsPesaje= $_POST['cuadra_id_p'] ?? [];
        $pesosKg        = $_POST['peso_kg']      ?? [];
        $numAnimalesPes = $_POST['num_anim_p']   ?? [];
        $pesajesGuardados = 0;

        foreach ($loteIdsPesaje as $j => $loteId) {
            if (!isset($_POST['confirmar_p'][$j])) continue;
            $loteId  = (int)$loteId;
            $pesoKg  = (float)str_replace(',', '.', $pesosKg[$j] ?? '0');
            $numAnim = (int)($numAnimalesPesaje[$j] ?? 0);
            $cuadraId = (int)($cuadraIdsPesaje[$j] ?? 0) ?: null;
            if (!$loteId || $pesoKg <= 0) continue;
            try {
                $pesajeModel->create([
                    'lote_id'              => $loteId,
                    'cuadra_id'            => $cuadraId,
                    'fecha'                => $fecha,
                    'peso_medio_kg'        => $pesoKg,
                    'num_animales_pesados'  => $numAnim ?: 0,
                    'consumo_pienso_kg'    => null,
                    'ic_real'              => null,
                    'observaciones'        => 'Registrado desde escaneo',
                    'usuario_id'           => $uid,
                ]);
                $pesajesGuardados++;
            } catch (\Exception $e) {
                $errores[] = "Pesaje fila {$j}: " . $e->getMessage();
            }
        }

        // ── Recargas de silo ─────────────────────────────────────
        $siloModel     = new Silo();
        $siloIds       = $_POST['silo_id']       ?? [];
        $cantidadesKg  = $_POST['cantidad_kg']   ?? [];
        $tiposPienso   = $_POST['tipo_pienso_s'] ?? [];
        $proveedores   = $_POST['proveedor_s']   ?? [];
        $albaranes     = $_POST['albaran_s']     ?? [];
        $silosGuardados = 0;

        foreach ($siloIds as $k => $siloId) {
            if (!isset($_POST['confirmar_s'][$k])) continue;
            $siloId    = (int)$siloId;
            $cantKg    = (float)str_replace(',', '.', $cantidadesKg[$k] ?? '0');
            $tipoPienso= trim($tiposPienso[$k] ?? '') ?: null;
            $proveedor = trim($proveedores[$k] ?? '') ?: null;
            $albaran   = trim($albaranes[$k]   ?? '') ?: null;
            if (!$siloId || $cantKg <= 0) continue;
            $obs = $albaran ? "Albarán: {$albaran}" : null;
            try {
                $siloModel->addRecarga($siloId, $cantKg, $fecha, $proveedor, $obs, $uid, $tipoPienso);
                $silosGuardados++;
            } catch (\Exception $e) {
                $errores[] = "Silo fila {$k}: " . $e->getMessage();
            }
        }

        // ── Resumen ───────────────────────────────────────────────
        $partes = [];
        if ($registrados)     $partes[] = "{$registrados} movimiento(s)";
        if ($pesajesGuardados) $partes[] = "{$pesajesGuardados} pesaje(s)";
        if ($silosGuardados)  $partes[] = "{$silosGuardados} recarga(s) de silo";

        if (!empty($partes)) {
            Session::flash('success', implode(', ', $partes) . ' registrado(s) correctamente.');
        } elseif (empty($errores)) {
            Session::flash('error', 'No se procesó nada. Asegúrate de seleccionar los desplegables.');
        }
        if (!empty($errores)) {
            Session::flash('error', implode(' | ', $errores));
        }

        $this->redirect('movimientos');
    }
}
