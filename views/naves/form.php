<?php
$esEdicion = !is_null($nave);
$action    = $esEdicion ? base_url("naves/{$nave['id']}/actualizar") : base_url('naves');
?>

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('naves') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= $action ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section-title">Datos de la nave</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Granja *</label>
                    <select name="granja_id" required>
                        <option value="">— Selecciona granja —</option>
                        <?php foreach ($granjas as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= ($nave['granja_id'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                                <?= e($g['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required value="<?= e($nave['nombre'] ?? '') ?>" placeholder="Nave 1, Cebo A...">
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Especie</label>
                    <select name="especie">
                        <?php foreach (['porcino','aviar','vacuno','ovino','caprino','mixta'] as $esp): ?>
                            <option value="<?= $esp ?>" <?= ($nave['especie'] ?? 'porcino') === $esp ? 'selected' : '' ?>>
                                <?= ucfirst($esp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Capacidad máxima (animales)</label>
                    <input type="number" name="capacidad_maxima" min="0" value="<?= e($nave['capacidad_maxima'] ?? 0) ?>">
                </div>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Dimensiones</div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Ancho (m)</label>
                    <input type="number" name="ancho_m" step="0.01" min="0" value="<?= e($nave['ancho_m'] ?? '') ?>" placeholder="12.5">
                </div>
                <div class="form-group">
                    <label>Alto (m)</label>
                    <input type="number" name="alto_m" step="0.01" min="0" value="<?= e($nave['alto_m'] ?? '') ?>" placeholder="3.5">
                </div>
                <div class="form-group">
                    <label>Largo (m)</label>
                    <input type="number" name="largo_m" step="0.01" min="0" value="<?= e($nave['largo_m'] ?? '') ?>" placeholder="80">
                </div>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="descripcion"><?= e($nave['descripcion'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear nave' ?></button>
            <a href="<?= base_url('naves') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
