<?php
$esEdicion = !is_null($silo);
$action    = $esEdicion ? base_url("silos/{$silo['id']}/actualizar") : base_url('silos');
?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('silos') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= $action ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section-title">Datos del silo</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Granja *</label>
                    <select name="granja_id" required>
                        <option value="">— Selecciona granja —</option>
                        <?php foreach ($granjas as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= ($silo['granja_id'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                                <?= e($g['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nombre / identificador *</label>
                    <input type="text" name="nombre" required value="<?= e($silo['nombre'] ?? '') ?>" placeholder="Silo 1, Silo Norte...">
                </div>
            </div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Capacidad total (kg)</label>
                    <input type="number" name="capacidad_kg" step="0.01" min="0" value="<?= e($silo['capacidad_kg'] ?? '') ?>" placeholder="15000">
                </div>
                <div class="form-group">
                    <label>Stock actual (kg)</label>
                    <input type="number" name="stock_actual_kg" step="0.01" min="0" value="<?= e($silo['stock_actual_kg'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>Stock mínimo (kg)</label>
                    <input type="number" name="stock_minimo_kg" step="0.01" min="0" value="<?= e($silo['stock_minimo_kg'] ?? 0) ?>">
                    <span class="form-hint">Alerta si baja de este nivel</span>
                </div>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="descripcion"><?= e($silo['descripcion'] ?? '') ?></textarea>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Naves abastecidas</div>

            <div class="form-group">
                <label>Selecciona las naves que abastece este silo</label>
                <div class="checkbox-list">
                    <?php if (empty($naves)): ?>
                        <span style="font-size:.82rem;color:#9ca3af">No hay naves disponibles</span>
                    <?php else: ?>
                        <?php foreach ($naves as $n): ?>
                            <label>
                                <input type="checkbox" name="nave_ids[]" value="<?= $n['id'] ?>"
                                    <?= in_array($n['id'], $navesAsig) ? 'checked' : '' ?>>
                                <?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear silo' ?></button>
            <a href="<?= base_url('silos') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
