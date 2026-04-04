<div class="page-header">
    <h2>Nuevo pesaje</h2>
    <a href="<?= base_url('pesajes') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:600px">
    <form method="POST" action="<?= base_url('pesajes') ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Lote *</label>
            <select name="lote_id" required onchange="cargarInfoLote(this.value)">
                <option value="">— Selecciona un lote —</option>
                <?php foreach ($lotes as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $loteId == $l['id'] ? 'selected' : '' ?>>
                        <?= e($l['codigo']) ?>
                        <?php if ($l['nave_nombre']): ?> · <?= e($l['nave_nombre']) ?><?php endif; ?>
                        (<?= $l['num_animales'] ?> animales, S<?= $l['semana_actual'] ?? '?' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Info del lote seleccionado -->
        <div id="infoLote" style="display:none;background:#f0f9ff;border:1px solid #bae6fd;border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#0369a1">
        </div>

        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Fecha del pesaje *</label>
                <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Nº animales pesados *</label>
                <input type="number" name="num_animales_pesados" required min="1" placeholder="Ej: 200">
            </div>
        </div>

        <div class="form-group">
            <label>Peso medio por animal (kg) *</label>
            <input type="number" name="peso_medio_kg" required min="0.001" step="0.001" placeholder="Ej: 32.500">
            <span class="form-hint">Peso medio de los animales pesados</span>
        </div>

        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Consumo pienso acumulado (kg)</label>
                <input type="number" name="consumo_pienso_kg" min="0" step="0.001" placeholder="Opcional">
            </div>
            <div class="form-group">
                <label>IC real</label>
                <input type="number" name="ic_real" min="0" step="0.001" placeholder="Ej: 2.450">
                <span class="form-hint">Índice de conversión real</span>
            </div>
        </div>

        <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones" rows="2" placeholder="Notas opcionales..."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar pesaje</button>
            <a href="<?= base_url('pesajes') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
const lotesData = <?= json_encode(array_values(array_map(fn($l) => [
    'id'           => $l['id'],
    'num_animales' => $l['num_animales'],
    'semana'       => $l['semana_actual'],
    'peso_tabla'   => $l['peso_tabla'],
    'nave'         => $l['nave_nombre'] ?? ($l['granja_nombre'] ?? ''),
], $lotes))) ?>;

function cargarInfoLote(id) {
    const div  = document.getElementById('infoLote');
    const lote = lotesData.find(l => l.id == id);
    if (!lote) { div.style.display = 'none'; return; }

    const pesoTabla = lote.peso_tabla ? parseFloat(lote.peso_tabla).toFixed(3) + ' kg' : '—';
    div.innerHTML = `<strong>Semana actual:</strong> S${lote.semana ?? '?'} &nbsp;·&nbsp;
                     <strong>Peso tabla:</strong> ${pesoTabla} &nbsp;·&nbsp;
                     <strong>Animales:</strong> ${lote.num_animales}`;
    div.style.display = 'block';

    // Prerellenar num animales pesados
    document.querySelector('input[name="num_animales_pesados"]').value = lote.num_animales;
}

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.querySelector('select[name="lote_id"]');
    if (sel && sel.value) cargarInfoLote(sel.value);
});
</script>
