<?php
$esEdicion = !is_null($cuadra);
$action    = $esEdicion ? base_url("cuadras/{$cuadra['id']}/actualizar") : base_url('cuadras');
?>

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= $action ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section-title">Datos de la cuadra</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Nave *</label>
                    <select name="nave_id" required>
                        <option value="">— Selecciona nave —</option>
                        <?php foreach ($naves as $n): ?>
                            <option value="<?= $n['id'] ?>"
                                <?= ($cuadra['nave_id'] ?? '') == $n['id'] ? 'selected' : '' ?>>
                                <?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nombre / número *</label>
                    <input type="text" name="nombre" required
                           value="<?= e($cuadra['nombre'] ?? '') ?>"
                           placeholder="Cuadra 1, Corral A...">
                </div>
            </div>

            <div class="form-group" style="max-width:220px">
                <label>Capacidad máxima (animales)</label>
                <input type="number" name="capacidad_maxima" min="0"
                       value="<?= e($cuadra['capacidad_maxima'] ?? 0) ?>">
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Dimensiones</div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Ancho (m)</label>
                    <input type="number" name="ancho_m" step="0.01" min="0"
                           value="<?= e($cuadra['ancho_m'] ?? '') ?>" placeholder="6">
                </div>
                <div class="form-group">
                    <label>Alto (m)</label>
                    <input type="number" name="alto_m" step="0.01" min="0"
                           value="<?= e($cuadra['alto_m'] ?? '') ?>" placeholder="2.5">
                </div>
                <div class="form-group">
                    <label>Largo (m)</label>
                    <input type="number" name="largo_m" step="0.01" min="0"
                           value="<?= e($cuadra['largo_m'] ?? '') ?>" placeholder="10">
                </div>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="descripcion"><?= e($cuadra['descripcion'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $esEdicion ? 'Guardar cambios' : 'Crear cuadra' ?>
            </button>
            <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
