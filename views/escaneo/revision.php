<?php
$bajas     = $datos['bajas']     ?? [];
$traslados = $datos['traslados'] ?? [];
$fecha     = $datos['fecha']     ?? date('Y-m-d');

// Mapas de búsqueda para sugerir lote por código
$lotesPorCodigo = [];
foreach ($lotes as $l) {
    // Intentar extraer número de lote del código (ej. "L 34/23" → "34")
    if (preg_match('/(\d+)/', $l['codigo'], $m)) {
        $lotesPorCodigo[$m[1]] = $l;
    }
    $lotesPorCodigo[trim($l['codigo'])] = $l;
}

function sugerirLote(string $codigoLeido, array $lotesPorCodigo): ?array {
    $key = trim($codigoLeido);
    if (isset($lotesPorCodigo[$key])) return $lotesPorCodigo[$key];
    // Buscar por número suelto
    if (preg_match('/(\d+)/', $key, $m) && isset($lotesPorCodigo[$m[1]])) {
        return $lotesPorCodigo[$m[1]];
    }
    return null;
}
?>

<div class="page-header">
    <h2>Revisar datos escaneados</h2>
    <a href="<?= base_url('escaneo') ?>" class="btn btn-secondary">Nueva foto</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;align-items:start">

<!-- Imagen -->
<div class="list-card" style="padding:1rem">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:.75rem">Imagen analizada</div>
    <img src="<?= base_url($imagen) ?>" style="width:100%;border-radius:.4rem;border:1px solid #e5e7eb">
</div>

<!-- Formulario revisión -->
<div>
<form method="POST" action="<?= base_url('escaneo/confirmar') ?>">
    <?= csrf_field() ?>

    <div class="form-group" style="margin-bottom:1.25rem">
        <label>Fecha del cuaderno *</label>
        <input type="date" name="fecha" required value="<?= e($fecha) ?>" style="max-width:200px">
    </div>

    <?php if (!empty($bajas)): ?>
    <div class="list-card" style="margin-bottom:1.25rem">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6b7280">
            Bajas detectadas
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead>
                <tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;color:#6b7280">
                    <th style="padding:.4rem .75rem;text-align:center;width:40px">✓</th>
                    <th style="padding:.4rem .75rem">Lote leído</th>
                    <th style="padding:.4rem .75rem">Lote sistema</th>
                    <th style="padding:.4rem .75rem;text-align:right">Cantidad</th>
                    <th style="padding:.4rem .75rem">Nave/Cuadra</th>
                    <th style="padding:.4rem .75rem">Motivo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bajas as $i => $b):
                $sugerido = sugerirLote((string)($b['lote'] ?? ''), $lotesPorCodigo);
                $idx = $i;
            ?>
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:.4rem .75rem;text-align:center">
                    <input type="checkbox" name="confirmar[<?= $idx ?>]" value="1" checked>
                    <input type="hidden" name="tipo[<?= $idx ?>]" value="baja">
                </td>
                <td style="padding:.4rem .75rem;font-family:monospace;color:#6b7280"><?= e((string)($b['lote'] ?? '—')) ?></td>
                <td style="padding:.4rem .75rem">
                    <select name="lote_id[<?= $idx ?>]" style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;width:100%">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($lotes as $l): if ((int)$l['num_animales'] <= 0) continue; ?>
                        <option value="<?= $l['id'] ?>" <?= $sugerido && $sugerido['id'] == $l['id'] ? 'selected' : '' ?>>
                            <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="padding:.4rem .75rem;text-align:right">
                    <input type="number" name="cantidad[<?= $idx ?>]" value="<?= (int)($b['cantidad'] ?? 0) ?>"
                           min="1" style="width:70px;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;font-size:.85rem;text-align:right">
                </td>
                <td style="padding:.4rem .75rem;font-size:.78rem;color:#6b7280"><?= e((string)($b['nave_cuadra'] ?? '—')) ?>
                    <input type="hidden" name="cuadra_origen_id[<?= $idx ?>]" value="">
                </td>
                <td style="padding:.4rem .75rem">
                    <select name="motivo[<?= $idx ?>]" style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem">
                        <option value="enfermedad" <?= stripos((string)($b['motivo'] ?? ''), 'enferm') !== false ? 'selected' : '' ?>>Enfermedad</option>
                        <option value="sacrificio" <?= stripos((string)($b['motivo'] ?? ''), 'sacri') !== false ? 'selected' : '' ?>>Sacrificio</option>
                        <option value="sacrificio" <?= stripos((string)($b['motivo'] ?? ''), 'canibal') !== false ? 'selected' : '' ?>>Sacrificio</option>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($traslados)): ?>
    <?php $offset = count($bajas); ?>
    <div class="list-card" style="margin-bottom:1.25rem">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6b7280">
            Traslados detectados
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead>
                <tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;color:#6b7280">
                    <th style="padding:.4rem .75rem;text-align:center;width:40px">✓</th>
                    <th style="padding:.4rem .75rem">Lote leído</th>
                    <th style="padding:.4rem .75rem">Lote sistema</th>
                    <th style="padding:.4rem .75rem;text-align:right">Cantidad</th>
                    <th style="padding:.4rem .75rem">Origen → Destino</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($traslados as $j => $t):
                $idx = $offset + $j;
                $sugerido = sugerirLote((string)($t['lote'] ?? ''), $lotesPorCodigo);
            ?>
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:.4rem .75rem;text-align:center">
                    <input type="checkbox" name="confirmar[<?= $idx ?>]" value="1" checked>
                    <input type="hidden" name="tipo[<?= $idx ?>]" value="traslado_cuadra">
                    <input type="hidden" name="motivo[<?= $idx ?>]" value="">
                </td>
                <td style="padding:.4rem .75rem;font-family:monospace;color:#6b7280"><?= e((string)($t['lote'] ?? '—')) ?></td>
                <td style="padding:.4rem .75rem">
                    <select name="lote_id[<?= $idx ?>]" style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;width:100%">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($lotes as $l): if ((int)$l['num_animales'] <= 0) continue; ?>
                        <option value="<?= $l['id'] ?>" <?= $sugerido && $sugerido['id'] == $l['id'] ? 'selected' : '' ?>>
                            <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="padding:.4rem .75rem;text-align:right">
                    <input type="number" name="cantidad[<?= $idx ?>]" value="<?= (int)($t['cantidad'] ?? 0) ?>"
                           min="1" style="width:70px;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;font-size:.85rem;text-align:right">
                </td>
                <td style="padding:.4rem .75rem;font-size:.78rem;color:#6b7280">
                    <?= e((string)($t['origen'] ?? '')) ?> → <?= e((string)($t['destino'] ?? '')) ?>
                    <input type="hidden" name="cuadra_origen_id[<?= $idx ?>]" value="">
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (empty($bajas) && empty($traslados)): ?>
    <div class="alert-flash alert-error">No se detectaron movimientos en la imagen. Prueba con una foto más nítida.</div>
    <?php else: ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Confirmar y registrar movimientos</button>
        <a href="<?= base_url('escaneo') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
    <?php endif; ?>

</form>
</div>
</div>
