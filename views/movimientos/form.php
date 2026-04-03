<?php
$esEdicion = !is_null($movimiento);
$action    = $esEdicion
    ? base_url("movimientos/{$movimiento['id']}/actualizar")
    : base_url('movimientos');

$tipoActual = $tipo ?? $movimiento['tipo'] ?? 'traslado_cuadra';

$tipoLabels = [
    'traslado_cuadra'    => 'Traslado de cuadra',
    'entrada_cebo'       => 'Entrada a cebo',
    'entrada_reposicion' => 'Entrada a reposición',
    'entrada_madres'     => 'Entrada a madres',
    'venta'              => 'Venta',
    'baja'               => 'Baja',
];

// Filtrar lotes según tipo
$lotesReposicion = array_filter($lotes, fn($l) => str_ends_with(trim($l['codigo']), 'RE'));
?>

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('movimientos') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:740px">
<form method="POST" action="<?= $action ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="tipo" value="<?= e($tipoActual) ?>">

    <?php if ($esEdicion): ?>

    <!-- ══ MODO EDICIÓN: solo fecha y cantidad editables ══ -->

    <!-- Hidden inputs: conservar todos los valores originales -->
    <input type="hidden" name="lote_origen_id"    value="<?= (int)$movimiento['lote_origen_id'] ?>">
    <input type="hidden" name="lote_destino_id"   value="<?= (int)($movimiento['lote_destino_id'] ?? 0) ?: '' ?>">
    <input type="hidden" name="cuadra_origen_id"  value="<?= (int)($movimiento['cuadra_origen_id'] ?? 0) ?: '' ?>">
    <input type="hidden" name="cuadra_destino_id" value="<?= (int)($movimiento['cuadra_destino_id'] ?? 0) ?: '' ?>">
    <input type="hidden" name="peso_canal_kg"     value="<?= e($movimiento['peso_canal_kg'] ?? '') ?>">
    <input type="hidden" name="precio_eur"        value="<?= e($movimiento['precio_eur'] ?? '') ?>">
    <input type="hidden" name="tipo_venta"        value="<?= e($movimiento['tipo_venta'] ?? '') ?>">
    <input type="hidden" name="observaciones"     value="<?= e($movimiento['observaciones'] ?? '') ?>">

    <!-- Info de solo lectura -->
    <div class="form-section-title">Movimiento</div>
    <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem 2rem">
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.2rem">Tipo</div>
            <div style="font-weight:600"><?= e($tipoLabels[$tipoActual] ?? $tipoActual) ?></div>
        </div>
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.2rem">Lote origen</div>
            <div style="font-family:monospace;font-weight:700;color:#1d4ed8"><?= e($movimiento['lote_origen_codigo'] ?? '—') ?></div>
        </div>
        <?php if ($movimiento['cuadra_origen_nombre']): ?>
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.2rem">Cuadra origen</div>
            <div><?= e($movimiento['cuadra_origen_nombre']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($movimiento['cuadra_destino_nombre']): ?>
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.2rem">Cuadra destino</div>
            <div><?= e($movimiento['cuadra_destino_nombre']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($movimiento['lote_destino_codigo'] && $movimiento['lote_destino_codigo'] !== $movimiento['lote_origen_codigo']): ?>
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.2rem">Lote destino</div>
            <div style="font-family:monospace;font-weight:700;color:#1d4ed8"><?= e($movimiento['lote_destino_codigo']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Campos editables -->
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Fecha *</label>
            <input type="date" name="fecha" required value="<?= e($movimiento['fecha']) ?>">
        </div>
        <div class="form-group">
            <label>Cantidad de animales *</label>
            <input type="number" name="num_animales" min="1" required value="<?= e($movimiento['num_animales']) ?>">
        </div>
    </div>

    <?php else: ?>

    <!-- ══ MODO CREACIÓN ══ -->

    <!-- Selector de tipo -->
    <div class="form-section-title">Tipo de movimiento</div>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1.25rem">
        <?php foreach ($tipoLabels as $t => $label): ?>
        <a href="<?= base_url('movimientos/crear?tipo=' . $t) ?>"
           class="btn <?= $tipoActual === $t ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="form-grid">

        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Fecha *</label>
                <input type="date" name="fecha" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>Cantidad de animales *</label>
                <input type="number" name="num_animales" min="1" required placeholder="Nº de animales">
            </div>
        </div>

        <!-- TRASLADO DE CUADRA -->
        <?php if ($tipoActual === 'traslado_cuadra'): ?>
        <div class="form-section-title">Origen</div>
        <div class="form-grid form-grid-3">
            <div class="form-group">
                <label>Nave origen</label>
                <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                    <option value="">— Nave —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>"><?= e($n['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cuadra origen</label>
                <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen')">
                    <option value="">— Cuadra —</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lote</label>
                <select id="loteOrigen" name="lote_origen_id" required>
                    <option value="">— Lote —</option>
                </select>
            </div>
        </div>
        <div class="form-section-title">Destino</div>
        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Nave destino</label>
                <select id="naveDestino" onchange="cargarCuadras(this.value, 'cuadraDestino', null)">
                    <option value="">— Nave —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>"><?= e($n['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cuadra destino</label>
                <select id="cuadraDestino" name="cuadra_destino_id">
                    <option value="">— Cuadra —</option>
                </select>
            </div>
        </div>

        <!-- ENTRADA CEBO -->
        <?php elseif ($tipoActual === 'entrada_cebo'): ?>
        <div class="form-section-title">Lote que pasa a cebo</div>
        <div class="form-group">
            <label>Lote de lechones *</label>
            <select name="lote_origen_id" required>
                <option value="">— Selecciona lote —</option>
                <?php foreach ($lotes as $l): ?>
                    <?php if (($l['estado_animal'] ?? 'lechon') === 'lechon'): ?>
                    <option value="<?= $l['id'] ?>"><?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?> animales)</option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Todo el lote pasará al estado <strong>Cebo</strong></span>
        </div>

        <!-- ENTRADA REPOSICIÓN -->
        <?php elseif ($tipoActual === 'entrada_reposicion'): ?>
        <div class="form-section-title">Origen</div>
        <div class="form-grid form-grid-3">
            <div class="form-group">
                <label>Nave origen</label>
                <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                    <option value="">— Nave —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>"><?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cuadra origen</label>
                <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen')">
                    <option value="">— Cuadra —</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lote *</label>
                <select id="loteOrigen" name="lote_origen_id" required>
                    <option value="">— Lote —</option>
                </select>
            </div>
        </div>
        <span class="form-hint">Se creará un nuevo lote con sufijo <strong>RE</strong> con los animales indicados</span>

        <!-- ENTRADA MADRES -->
        <?php elseif ($tipoActual === 'entrada_madres'): ?>
        <div class="form-section-title">Lote de reposición de origen</div>
        <div class="form-grid form-grid-3">
            <div class="form-group">
                <label>Nave origen</label>
                <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                    <option value="">— Nave —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>"><?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cuadra origen</label>
                <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen', null, true)">
                    <option value="">— Cuadra —</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lote RE *</label>
                <select id="loteOrigen" name="lote_origen_id" required>
                    <option value="">— Lote RE —</option>
                </select>
            </div>
        </div>
        <span class="form-hint">Solo se muestran lotes con sufijo <strong>RE</strong>. Se creará un nuevo lote <strong>MA</strong></span>

        <!-- VENTA -->
        <?php elseif ($tipoActual === 'venta'): ?>
        <div class="form-section-title">Origen</div>
        <div class="form-grid form-grid-3">
            <div class="form-group">
                <label>Nave origen</label>
                <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                    <option value="">— Nave —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>"><?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cuadra origen</label>
                <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen')">
                    <option value="">— Cuadra —</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lote *</label>
                <select id="loteOrigen" name="lote_origen_id" required>
                    <option value="">— Lote —</option>
                </select>
            </div>
        </div>
        <div class="form-section-title" style="margin-top:.5rem">Datos de venta</div>
        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Destino de venta *</label>
                <select name="tipo_venta" required>
                    <option value="">— Selecciona —</option>
                    <option value="matadero">Matadero</option>
                    <option value="tercero">Tercero</option>
                </select>
            </div>
            <div class="form-group">
                <label>Precio total (€)</label>
                <input type="number" name="precio_eur" step="0.01" min="0" placeholder="0.00">
            </div>
        </div>
        <div class="form-group">
            <label>Peso canal total (kg)</label>
            <input type="number" name="peso_canal_kg" step="0.01" min="0" placeholder="0.00">
        </div>
        <!-- ═══════════════════════════════════════════════════
             BAJA
        ════════════════════════════════════════════════════════ -->
        <?php elseif ($tipoActual === 'baja'): ?>
        <?php if ($esEdicion && $movimiento): ?>
            <!-- Edición: lote/cuadra/motivo no editables -->
            <div style="background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1rem">
                <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.4rem">Lote y cuadra (no editables)</div>
                <div style="font-size:.9rem;color:#374151;font-weight:600"><?= e($movimiento['lote_origen_codigo']) ?></div>
                <?php if (!empty($movimiento['cuadra_origen_nombre'])): ?>
                    <div style="font-size:.82rem;color:#6b7280">Cuadra: <?= e($movimiento['cuadra_origen_nombre']) ?></div>
                <?php endif; ?>
                <?php if (!empty($movimiento['motivo_baja'])): ?>
                    <?php $motivoLabel = $movimiento['motivo_baja'] === 'enfermedad' ? 'Enfermedad' : 'Sacrificio'; ?>
                    <div style="font-size:.82rem;color:#6b7280;margin-top:.25rem">Motivo: <strong><?= $motivoLabel ?></strong></div>
                <?php endif; ?>
            </div>
            <input type="hidden" name="lote_origen_id"   value="<?= (int)$movimiento['lote_origen_id'] ?>">
            <input type="hidden" name="cuadra_origen_id" value="<?= e($movimiento['cuadra_origen_id'] ?? '') ?>">
            <input type="hidden" name="motivo_baja"      value="<?= e($movimiento['motivo_baja'] ?? '') ?>">
        <?php else: ?>
            <!-- Creación: cascada nave → cuadra → lote -->
            <div class="form-section-title">Lote afectado</div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Nave</label>
                    <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                        <option value="">— Nave —</option>
                        <?php foreach ($naves as $n): ?>
                            <option value="<?= $n['id'] ?>"><?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cuadra</label>
                    <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen')">
                        <option value="">— Cuadra —</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lote *</label>
                    <select id="loteOrigen" name="lote_origen_id" required>
                        <option value="">— Lote —</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Motivo *</label>
                <select name="motivo_baja" required>
                    <option value="">— Selecciona —</option>
                    <option value="enfermedad">Enfermedad</option>
                    <option value="sacrificio">Sacrificio</option>
                </select>
            </div>
        <?php endif; ?>

        <?php endif; ?>

        <!-- Observaciones -->
        <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones" rows="2"></textarea>
        </div>

    </div>

    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $esEdicion ? 'Guardar cambios' : 'Registrar movimiento' ?>
        </button>
        <a href="<?= base_url('movimientos') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
</div>

<?php if ($esEdicion && !empty($historial)): ?>
<div style="margin-top:2rem">
    <div class="form-section-title" style="margin-bottom:.75rem">Historial de cambios</div>
    <div class="list-card">
        <table class="list-table">
            <thead>
                <tr><th>Fecha</th><th>Acción</th><th>Usuario</th></tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                    <td>
                        <span style="background:<?= $h['accion'] === 'crear' ? '#d1fae5' : ($h['accion'] === 'eliminar' ? '#fee2e2' : '#dbeafe') ?>;
                                     color:<?= $h['accion'] === 'crear' ? '#065f46' : ($h['accion'] === 'eliminar' ? '#991b1b' : '#1e40af') ?>;
                                     padding:.15rem .5rem;border-radius:20px;font-size:.75rem;font-weight:600">
                            <?= ucfirst($h['accion']) ?>
                        </span>
                    </td>
                    <td><?= e($h['usuario_nombre']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
async function cargarCuadras(naveId, selectId, loteSelectId, valorSeleccionado = null) {
    const sel = document.getElementById(selectId);
    sel.innerHTML = '<option value="">Cargando...</option>';
    if (!naveId) { sel.innerHTML = '<option value="">— Cuadra —</option>'; return; }

    const res  = await fetch(`<?= base_url('movimientos/cuadras') ?>?nave_id=${naveId}`);
    const data = await res.json();

    sel.innerHTML = '<option value="">— Cuadra —</option>';
    data.forEach(c => {
        const info = c.lotes ? ` (${c.lotes})` : '';
        const selected = valorSeleccionado && c.id == valorSeleccionado ? 'selected' : '';
        sel.innerHTML += `<option value="${c.id}" ${selected}>${c.nombre}${info}</option>`;
    });

    if (loteSelectId) {
        document.getElementById(loteSelectId).innerHTML = '<option value="">— Lote —</option>';
    }
}

async function cargarLotesDeCuadra(cuadraId, loteSelectId, valorSeleccionado = null, soloRE = false) {
    const sel = document.getElementById(loteSelectId);
    sel.innerHTML = '<option value="">Cargando...</option>';
    if (!cuadraId) { sel.innerHTML = '<option value="">— Lote —</option>'; return; }

    const res  = await fetch(`<?= base_url('movimientos/lotes-cuadra') ?>?cuadra_id=${cuadraId}`);
    let data = await res.json();

    // Filtrar solo lotes RE si se pide
    if (soloRE) data = data.filter(l => l.codigo.trim().endsWith('RE'));

    sel.innerHTML = soloRE ? '<option value="">— Lote RE —</option>' : '<option value="">— Lote —</option>';
    data.forEach(l => {
        const selected = valorSeleccionado && l.id == valorSeleccionado ? 'selected' : '';
        sel.innerHTML += `<option value="${l.id}" ${selected}>${l.codigo} (${l.num_animales} animales)</option>`;
    });
    if (!valorSeleccionado && data.length === 1) sel.value = data[0].id;
}

<?php if ($esEdicion && $movimiento):
    $db = \App\Core\Database::getInstance();
    $naveOrigen = null;
    $naveDestino = null;
    if ($movimiento['cuadra_origen_id']) {
        $s = $db->prepare("SELECT nave_id FROM cuadras WHERE id = :id");
        $s->execute(['id' => $movimiento['cuadra_origen_id']]);
        $naveOrigen = $s->fetchColumn();
    }
    if ($movimiento['cuadra_destino_id']) {
        $s = $db->prepare("SELECT nave_id FROM cuadras WHERE id = :id");
        $s->execute(['id' => $movimiento['cuadra_destino_id']]);
        $naveDestino = $s->fetchColumn();
    }
?>
document.addEventListener('DOMContentLoaded', async () => {

    <?php if ($naveOrigen): ?>
    // Precargar origen
    const naveOrigenSel = document.getElementById('naveOrigen');
    if (naveOrigenSel) {
        naveOrigenSel.value = <?= (int)$naveOrigen ?>;
        await cargarCuadras(<?= (int)$naveOrigen ?>, 'cuadraOrigen', 'loteOrigen', <?= (int)$movimiento['cuadra_origen_id'] ?>);
        await cargarLotesDeCuadra(<?= (int)$movimiento['cuadra_origen_id'] ?>, 'loteOrigen', <?= (int)$movimiento['lote_origen_id'] ?>);
        // Bloquear origen (nave, cuadra, lote) — no se puede cambiar en edición
        ['naveOrigen','cuadraOrigen','loteOrigen'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.style.pointerEvents = 'none';
                el.style.background    = '#f3f4f6';
                el.style.color         = '#9ca3af';
            }
        });
    }
    <?php elseif ($movimiento['lote_origen_id']): ?>
    // Tipo sin cuadra — preseleccionar lote si el select tiene opciones estáticas
    const loteOrigenSel = document.getElementById('loteOrigen');
    if (loteOrigenSel) {
        loteOrigenSel.value = <?= (int)$movimiento['lote_origen_id'] ?>;
        // Si el select dinámico está vacío, añadir hidden input como respaldo
        if (loteOrigenSel.value === '' || loteOrigenSel.value === '0') {
            const h = document.createElement('input');
            h.type  = 'hidden';
            h.name  = 'lote_origen_id';
            h.value = '<?= (int)$movimiento['lote_origen_id'] ?>';
            loteOrigenSel.insertAdjacentElement('afterend', h);
            loteOrigenSel.name = '';
        }
    }
    <?php endif; ?>

    <?php if ($naveDestino): ?>
    // Precargar destino (editable)
    const naveDestinoSel = document.getElementById('naveDestino');
    if (naveDestinoSel) {
        naveDestinoSel.value = <?= (int)$naveDestino ?>;
        await cargarCuadras(<?= (int)$naveDestino ?>, 'cuadraDestino', null, <?= (int)$movimiento['cuadra_destino_id'] ?>);
    }
    <?php endif; ?>

});
<?php endif; ?>
</script>