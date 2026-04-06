<?php
$bajas     = $datos['bajas']     ?? [];
$traslados = $datos['traslados'] ?? [];
$fecha     = $datos['fecha']     ?? date('Y-m-d');

// Mapas de búsqueda para sugerir lote por código
$lotesPorCodigo = [];
foreach ($lotes as $l) {
    if (preg_match('/(\d+)/', $l['codigo'], $m)) {
        $lotesPorCodigo[$m[1]] = $l;
    }
    $lotesPorCodigo[trim($l['codigo'])] = $l;
}

function sugerirLote(string $codigoLeido, array $lotesPorCodigo): ?array {
    $key = trim($codigoLeido);
    if (isset($lotesPorCodigo[$key])) return $lotesPorCodigo[$key];
    if (preg_match('/(\d+)/', $key, $m) && isset($lotesPorCodigo[$m[1]])) {
        return $lotesPorCodigo[$m[1]];
    }
    return null;
}

// Datos de lotes para validación JS (solo los activos con animales)
$lotesJs = [];
foreach ($lotes as $l) {
    if ((int)$l['num_animales'] <= 0) continue;
    $lotesJs[$l['id']] = [
        'num_animales'  => (int)$l['num_animales'],
        'fecha_entrada' => $l['fecha_entrada'] ?? null,
        'codigo'        => $l['codigo'],
    ];
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
<form method="POST" action="<?= base_url('escaneo/confirmar') ?>" id="frmEscaneo">
    <?= csrf_field() ?>

    <div class="form-group" style="margin-bottom:1.25rem">
        <label>Fecha del cuaderno *</label>
        <input type="date" name="fecha" id="fechaCuaderno" required value="<?= e($fecha) ?>" style="max-width:200px">
    </div>

    <?php if (!empty($bajas)): ?>
    <div class="list-card" style="margin-bottom:1.25rem">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6b7280">
            Bajas detectadas
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead>
                <tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;color:#6b7280">
                    <th style="padding:.4rem .75rem;text-align:center;width:36px">✓</th>
                    <th style="padding:.4rem .75rem">Lote leído</th>
                    <th style="padding:.4rem .75rem">Lote sistema</th>
                    <th style="padding:.4rem .75rem;text-align:right;width:70px">Cant.</th>
                    <th style="padding:.4rem .75rem">Cuadra</th>
                    <th style="padding:.4rem .75rem">Motivo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bajas as $i => $b):
                $sugerido = sugerirLote((string)($b['lote'] ?? ''), $lotesPorCodigo);
                $idx = $i;
            ?>
            <tr style="border-bottom:1px solid #f3f4f6" id="fila-<?= $idx ?>">
                <td style="padding:.4rem .75rem;text-align:center">
                    <input type="checkbox" name="confirmar[<?= $idx ?>]" value="1" checked>
                    <input type="hidden" name="tipo[<?= $idx ?>]" value="baja">
                </td>
                <td style="padding:.4rem .75rem;font-family:monospace;color:#6b7280">
                    <?= e((string)($b['lote'] ?? '—')) ?>
                    <?php if (!empty($b['nave_cuadra'])): ?>
                        <div style="font-size:.72rem;color:#9ca3af"><?= e($b['nave_cuadra']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:.4rem .75rem">
                    <select name="lote_id[<?= $idx ?>]" id="lote-<?= $idx ?>"
                            onchange="onLoteCambio(<?= $idx ?>)"
                            style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;width:100%">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($lotes as $l): if ((int)$l['num_animales'] <= 0) continue; ?>
                        <option value="<?= $l['id'] ?>"
                                data-animales="<?= (int)$l['num_animales'] ?>"
                                data-entrada="<?= e($l['fecha_entrada'] ?? '') ?>"
                                <?= $sugerido && $sugerido['id'] == $l['id'] ? 'selected' : '' ?>>
                            <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="padding:.4rem .75rem;text-align:right">
                    <input type="number" name="cantidad[<?= $idx ?>]" id="cant-<?= $idx ?>"
                           value="<?= (int)($b['cantidad'] ?? 1) ?>"
                           min="1"
                           style="width:65px;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;font-size:.85rem;text-align:right"
                           oninput="validarCantidad(<?= $idx ?>)">
                </td>
                <td style="padding:.4rem .75rem">
                    <select name="cuadra_origen_id[<?= $idx ?>]" id="cuadra-<?= $idx ?>"
                            onchange="validarCantidad(<?= $idx ?>)"
                            style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;width:100%">
                        <option value="">— Todas —</option>
                    </select>
                </td>
                <td style="padding:.4rem .75rem">
                    <select name="motivo[<?= $idx ?>]" style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem">
                        <?php
                        $motLeido = strtolower((string)($b['motivo'] ?? ''));
                        $motivos = [
                            'enfermedad' => 'Enfermedad',
                            'sacrificio' => 'Sacrificio',
                            'canibalismo'=> 'Canibalismo',
                            'aplastamiento' => 'Aplastamiento',
                            'otro'       => 'Otro',
                        ];
                        foreach ($motivos as $val => $label):
                            $sel = (str_contains($motLeido, substr($val, 0, 5))) ? 'selected' : '';
                        ?>
                        <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                        <?php endforeach; ?>
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
                    <th style="padding:.4rem .75rem;text-align:center;width:36px">✓</th>
                    <th style="padding:.4rem .75rem">Lote leído</th>
                    <th style="padding:.4rem .75rem">Lote sistema</th>
                    <th style="padding:.4rem .75rem;text-align:right;width:65px">Cant.</th>
                    <th style="padding:.4rem .75rem">Cuadra origen</th>
                    <th style="padding:.4rem .75rem">Cuadra destino</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($traslados as $j => $t):
                $idx = $offset + $j;
                $sugerido = sugerirLote((string)($t['lote'] ?? ''), $lotesPorCodigo);
            ?>
            <tr style="border-bottom:1px solid #f3f4f6" id="fila-<?= $idx ?>">
                <td style="padding:.4rem .75rem;text-align:center">
                    <input type="checkbox" name="confirmar[<?= $idx ?>]" value="1" checked>
                    <input type="hidden" name="tipo[<?= $idx ?>]" value="traslado_cuadra">
                    <input type="hidden" name="motivo[<?= $idx ?>]" value="">
                </td>
                <td style="padding:.4rem .75rem;font-family:monospace;color:#6b7280">
                    <?= e((string)($t['lote'] ?? '—')) ?>
                    <?php if (!empty($t['origen']) || !empty($t['destino'])): ?>
                        <div style="font-size:.72rem;color:#9ca3af">
                            <?= e((string)($t['origen'] ?? '')) ?> → <?= e((string)($t['destino'] ?? '')) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="padding:.4rem .75rem">
                    <select name="lote_id[<?= $idx ?>]" id="lote-<?= $idx ?>"
                            onchange="onLoteCambio(<?= $idx ?>)"
                            style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;width:100%">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($lotes as $l): if ((int)$l['num_animales'] <= 0) continue; ?>
                        <option value="<?= $l['id'] ?>"
                                data-animales="<?= (int)$l['num_animales'] ?>"
                                data-entrada="<?= e($l['fecha_entrada'] ?? '') ?>"
                                <?= $sugerido && $sugerido['id'] == $l['id'] ? 'selected' : '' ?>>
                            <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="padding:.4rem .75rem;text-align:right">
                    <input type="number" name="cantidad[<?= $idx ?>]" id="cant-<?= $idx ?>"
                           value="<?= (int)($t['cantidad'] ?? 0) ?>"
                           min="1"
                           style="width:65px;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;font-size:.85rem;text-align:right"
                           oninput="validarCantidad(<?= $idx ?>)">
                </td>
                <td style="padding:.4rem .75rem">
                    <select name="cuadra_origen_id[<?= $idx ?>]" id="cuadra-<?= $idx ?>"
                            onchange="validarCantidad(<?= $idx ?>)"
                            style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;width:100%">
                        <option value="">— Cargando —</option>
                    </select>
                </td>
                <td style="padding:.4rem .75rem">
                    <select name="cuadra_destino_id[<?= $idx ?>]" id="cuadra-destino-<?= $idx ?>"
                            data-texto-leido="<?= e((string)($t['destino'] ?? '')) ?>"
                            style="font-size:.82rem;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;width:100%">
                        <option value="">— Cargando —</option>
                    </select>
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
        <button type="submit" class="btn btn-primary" onclick="return validarFormulario()">Confirmar y registrar movimientos</button>
        <a href="<?= base_url('escaneo') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
    <?php endif; ?>

</form>
</div>
</div>

<script>
const LOTES_DATA = <?= json_encode($lotesJs, JSON_UNESCAPED_UNICODE) ?>;
const BASE_URL   = '<?= base_url('') ?>';

// Cache de todas las cuadras (para destino de traslados)
let _todasCuadras = null;
function getTodasCuadras() {
    if (_todasCuadras) return Promise.resolve(_todasCuadras);
    return fetch(BASE_URL + 'movimientos/todas-cuadras')
        .then(r => r.json())
        .then(data => { _todasCuadras = data; return data; });
}

// Normaliza texto para comparar (quita espacios, puntos, guiones, minúsculas)
function normalizar(s) {
    return (s || '').toLowerCase().replace(/[\s.\-]/g, '');
}

// Rellena un <select> con cuadras y pre-selecciona la que coincida con textoLeido
function rellenarCuadras(sel, cuadras, textoLeido, placeholder) {
    const prev = sel.value;
    sel.innerHTML = '<option value="">' + placeholder + '</option>';
    const norm = normalizar(textoLeido);
    let sugerida = null;
    cuadras.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        const info = c.num_animales > 0 ? ' (' + c.num_animales + ')' : '';
        opt.textContent = c.nombre + ' · ' + c.nave_nombre + info;
        opt.dataset.animales = c.num_animales;
        sel.appendChild(opt);
        if (norm && normalizar(c.nombre).includes(norm)) sugerida = c.id;
    });
    // Restaurar selección previa o sugerir
    if (prev && [...sel.options].some(o => o.value === prev)) {
        sel.value = prev;
    } else if (sugerida) {
        sel.value = sugerida;
    }
}

// Cargar cuadras cuando cambia el lote
function onLoteCambio(idx) {
    const loteId   = document.getElementById('lote-' + idx)?.value || '';
    const selOrigen  = document.getElementById('cuadra-' + idx);
    const selDestino = document.getElementById('cuadra-destino-' + idx);

    // Cargar cuadras origen (solo las del lote)
    if (selOrigen) {
        selOrigen.innerHTML = '<option value="">— Todas —</option>';
        if (loteId) {
            fetch(BASE_URL + 'movimientos/cuadras-lote?lote_id=' + loteId)
                .then(r => r.json())
                .then(cuadras => {
                    rellenarCuadras(selOrigen, cuadras, selOrigen.dataset.textoLeido || '', '— Todas —');
                    validarCantidad(idx);
                });
        }
    }

    // Cargar cuadras destino (todas las del sistema)
    if (selDestino) {
        selDestino.innerHTML = '<option value="">— Selecciona —</option>';
        const textoDestino = selDestino.dataset.textoLeido || '';
        getTodasCuadras().then(cuadras => {
            rellenarCuadras(selDestino, cuadras, textoDestino, '— Selecciona —');
        });
    }

    validarCantidad(idx);
}

// Validar que la cantidad no supere el máximo del lote o la cuadra
function validarCantidad(idx) {
    const loteEl  = document.getElementById('lote-' + idx);
    const cantEl  = document.getElementById('cant-' + idx);
    const cuadSel = document.getElementById('cuadra-' + idx);
    if (!loteEl || !cantEl) return;

    const loteId   = loteEl.value;
    const loteData = LOTES_DATA[loteId];
    if (!loteData) { cantEl.max = ''; cantEl.style.borderColor = '#d1d5db'; return; }

    let maxAnimales = loteData.num_animales;
    if (cuadSel && cuadSel.value) {
        const optCuadra = cuadSel.options[cuadSel.selectedIndex];
        const cuadAnim = parseInt(optCuadra.dataset.animales || '0');
        if (cuadAnim > 0) maxAnimales = Math.min(maxAnimales, cuadAnim);
    }

    cantEl.max = maxAnimales;
    const val = parseInt(cantEl.value || '0');
    cantEl.style.borderColor = (val > maxAnimales) ? '#ef4444' : '#d1d5db';
}

// Validar todo el formulario antes de enviar
function validarFormulario() {
    const fechaVal = document.getElementById('fechaCuaderno').value;
    const checks   = document.querySelectorAll('input[name^="confirmar["]');
    let errores    = [];

    checks.forEach(chk => {
        if (!chk.checked) return;
        const idx     = chk.name.match(/\[(\d+)\]/)[1];
        const loteEl  = document.getElementById('lote-' + idx);
        const cantEl  = document.getElementById('cant-' + idx);
        const cuadSel = document.getElementById('cuadra-' + idx);
        const filaNum = parseInt(idx) + 1;

        if (!loteEl || !loteEl.value) {
            errores.push('Fila ' + filaNum + ': selecciona el lote en el desplegable.');
            return;
        }

        const loteData = LOTES_DATA[loteEl.value];

        // Validar fecha >= fecha_entrada del lote
        if (loteData && loteData.fecha_entrada && fechaVal) {
            if (fechaVal < loteData.fecha_entrada) {
                errores.push('Fila ' + filaNum + ': la fecha ' + fechaVal + ' es anterior a la entrada del lote '
                    + loteData.codigo + ' (' + loteData.fecha_entrada + ').');
            }
        }

        // Validar cantidad
        if (loteData && cantEl) {
            const cant = parseInt(cantEl.value || '0');
            if (cant <= 0) {
                errores.push('Fila ' + filaNum + ': la cantidad debe ser mayor que 0.');
            } else {
                if (cant > loteData.num_animales) {
                    errores.push('Fila ' + filaNum + ': la cantidad (' + cant + ') supera los animales del lote (' + loteData.num_animales + ').');
                }
                if (cuadSel && cuadSel.value) {
                    const opt = cuadSel.options[cuadSel.selectedIndex];
                    const cuadAnim = parseInt(opt.dataset.animales || '0');
                    if (cuadAnim > 0 && cant > cuadAnim) {
                        errores.push('Fila ' + filaNum + ': la cantidad (' + cant + ') supera los animales de la cuadra origen (' + cuadAnim + ').');
                    }
                }
            }
        }
    });

    if (errores.length > 0) {
        alert('Por favor corrige lo siguiente:\n\n' + errores.join('\n'));
        return false;
    }
    return true;
}

// Al cargar la página, inicializar cuadras para los lotes preseleccionados
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[id^="lote-"]').forEach(sel => {
        const idx = sel.id.replace('lote-', '');
        if (sel.value) onLoteCambio(parseInt(idx));
    });
});
</script>
