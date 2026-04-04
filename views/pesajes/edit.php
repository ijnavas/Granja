<div class="page-header">
    <h2>Editar pesaje</h2>
    <a href="<?= base_url('pesajes') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:600px">
    <form method="POST" action="<?= base_url("pesajes/{$pesaje['id']}/actualizar") ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Lote</label>
            <?php
                $loteActual = null;
                foreach ($lotes as $l) {
                    if ($l['id'] == $pesaje['lote_id']) { $loteActual = $l; break; }
                }
            ?>
            <input type="text" disabled value="<?= e($loteActual['codigo'] ?? '—') ?><?= $loteActual && $loteActual['nave_nombre'] ? ' · ' . $loteActual['nave_nombre'] : '' ?>">
            <span class="form-hint">El lote no se puede cambiar en un pesaje existente.</span>
        </div>

        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Fecha del pesaje *</label>
                <input type="date" name="fecha" required value="<?= e($pesaje['fecha']) ?>">
            </div>
            <div class="form-group">
                <label>Nº animales pesados *</label>
                <input type="number" name="num_animales_pesados" required min="1"
                       value="<?= e($pesaje['num_animales_pesados']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Peso medio por animal (kg) *</label>
            <input type="number" name="peso_medio_kg" required min="0.001" step="0.001"
                   value="<?= e($pesaje['peso_medio_kg']) ?>">
        </div>

        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Consumo pienso acumulado (kg)</label>
                <input type="number" name="consumo_pienso_kg" min="0" step="0.001"
                       value="<?= e($pesaje['consumo_pienso_kg'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>IC real</label>
                <input type="number" name="ic_real" min="0" step="0.001"
                       value="<?= e($pesaje['ic_real'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones" rows="2"><?= e($pesaje['observaciones'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="<?= base_url('pesajes') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
