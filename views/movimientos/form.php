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

    <!-- Selector de tipo -->
    <div class="form-section-title">Tipo de movimiento</div>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1.25rem">
        <?php foreach ($tipoLabels as $t => $label): ?>
        <a href="<?= base_url('movimientos/crear?tipo=' . $t) ?>"
           class="btn <?= $tipoActual === $t ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
           <?= $esEdicion ? 'style="pointer-events:none;opacity:.6"' : '' ?>>
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="form-grid">

        <!-- Fecha y cantidad siempre visibles -->
        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Fecha *</label>
                <input type="date" name="fecha" required
                       value="<?= e($movimiento['fecha'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>Cantidad de animales *</label>
                <input type="number" name="num_animales" min="1" required
                       value="<?= e($movimiento['num_animales'] ?? '') ?>"
                       placeholder="Nº de animales">
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             TRASLADO DE CUADRA
        ════════════════════════════════════════════════════════ -->
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

        <!-- ═══════════════════════════════════════════════════
             ENTRADA CEBO
        ════════════════════════════════════════════════════════ -->
        <?php elseif ($tipoActual === 'entrada_cebo'): ?>
        <div class="form-section-title">Lote que pasa a cebo</div>
        <div class="form-group">
            <label>Lote de lechones *</label>
            <select name="lote_origen_id" required>
                <option value="">— Selecciona lote —</option>
                <?php foreach ($lotes as $l): ?>
                    <?php if (($l['estado_animal'] ?? 'lechon') === 'lechon'): ?>
                    <option value="<?= $l['id'] ?>" <?= ($movimiento['lote_origen_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                        <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?> animales)
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Todo el lote pasará al estado <strong>Cebo</strong></span>
        </div>

        <!-- ═══════════════════════════════════════════════════
             ENTRADA REPOSICIÓN
        ════════════════════════════════════════════════════════ -->
        <?php elseif ($tipoActual === 'entrada_reposicion'): ?>
        <div class="form-section-title">Lote de origen</div>
        <div class="form-group">
            <label>Lote de lechones *</label>
            <select name="lote_origen_id" required>
                <option value="">— Selecciona lote —</option>
                <?php foreach ($lotes as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= ($movimiento['lote_origen_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                        <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?> animales)
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Se creará un nuevo lote con sufijo <strong>RE</strong> con los animales indicados</span>
        </div>

        <!-- ═══════════════════════════════════════════════════
             ENTRADA MADRES
        ════════════════════════════════════════════════════════ -->
        <?php elseif ($tipoActual === 'entrada_madres'): ?>
        <div class="form-section-title">Lote de reposición de origen</div>
        <div class="form-group">
            <label>Lote de reposición (RE) *</label>
            <select name="lote_origen_id" required>
                <option value="">— Selecciona lote RE —</option>
                <?php foreach ($lotesReposicion as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= ($movimiento['lote_origen_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                        <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?> animales)
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Se creará un nuevo lote con sufijo <strong>MA</strong></span>
        </div>

        <!-- ═══════════════════════════════════════════════════
             VENTA
        ════════════════════════════════════════════════════════ -->
        <?php elseif ($tipoActual === 'venta'): ?>
        <div class="form-section-title">Lote y datos de venta</div>
        <div class="form-group">
            <label>Lote que se vende *</label>
            <select name="lote_origen_id" required>
                <option value="">— Selecciona lote —</option>
                <?php foreach ($lotes as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= ($movimiento['lote_origen_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                        <?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?> animales)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Destino de venta *</label>
                <select name="tipo_venta" required>
                    <option value="">— Selecciona —</option>
                    <option value="matadero" <?= ($movimiento['tipo_venta'] ?? '') === 'matadero' ? 'selected' : '' ?>>Matadero</option>
                    <option value="tercero"  <?= ($movimiento['tipo_venta'] ?? '') === 'tercero'  ? 'selected' : '' ?>>Tercero</option>
                </select>
            </div>
            <div class="form-group">
                <label>Precio total (€)</label>
                <input type="number" name="precio_eur" step="0.01" min="0"
                       value="<?= e($movimiento['precio_eur'] ?? '') ?>" placeholder="0.00">
            </div>
        </div>
        <div class="form-group">
            <label>Peso canal total (kg)</label>
            <input type="number" name="peso_canal_kg" step="0.01" min="0"
                   value="<?= e($movimiento['peso_canal_kg'] ?? '') ?>" placeholder="0.00">
        </div>
        <?php endif; ?>

        <!-- Observaciones siempre -->
        <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones" rows="2"><?= e($movimiento['observaciones'] ?? '') ?></textarea>
        </div>

    </div>

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

async function cargarLotesDeCuadra(cuadraId, loteSelectId, valorSeleccionado = null) {
    const sel = document.getElementById(loteSelectId);
    sel.innerHTML = '<option value="">Cargando...</option>';
    if (!cuadraId) { sel.innerHTML = '<option value="">— Lote —</option>'; return; }

    const res  = await fetch(`<?= base_url('movimientos/lotes-cuadra') ?>?cuadra_id=${cuadraId}`);
    const data = await res.json();

    sel.innerHTML = '<option value="">— Lote —</option>';
    data.forEach(l => {
        const selected = valorSeleccionado && l.id == valorSeleccionado ? 'selected' : '';
        sel.innerHTML += `<option value="${l.id}" ${selected}>${l.codigo} (${l.num_animales} animales)</option>`;
    });
    if (!valorSeleccionado && data.length === 1) sel.value = data[0].id;
}

<?php if ($esEdicion && $tipoActual === 'traslado_cuadra' && $movimiento): ?>
// Precargar datos del movimiento en edición
document.addEventListener('DOMContentLoaded', async () => {
    <?php
    // Necesitamos la nave de la cuadra origen y destino
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

    <?php if ($naveOrigen): ?>
    document.getElementById('naveOrigen').value = <?= $naveOrigen ?>;
    await cargarCuadras(<?= $naveOrigen ?>, 'cuadraOrigen', 'loteOrigen', <?= $movimiento['cuadra_origen_id'] ?? 'null' ?>);
    <?php if ($movimiento['cuadra_origen_id']): ?>
    await cargarLotesDeCuadra(<?= $movimiento['cuadra_origen_id'] ?>, 'loteOrigen', <?= $movimiento['lote_origen_id'] ?? 'null' ?>);
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($naveDestino): ?>
    document.getElementById('naveDestino').value = <?= $naveDestino ?>;
    await cargarCuadras(<?= $naveDestino ?>, 'cuadraDestino', null, <?= $movimiento['cuadra_destino_id'] ?? 'null' ?>);
    <?php endif; ?>
});
<?php endif; ?>
</script>