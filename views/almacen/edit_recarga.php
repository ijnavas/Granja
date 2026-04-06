<div class="page-header">
    <h2>Editar recarga</h2>
    <a href="<?= base_url('almacen') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:560px">
<form method="POST" action="<?= base_url("almacen/recargas/{$recarga['id']}/actualizar") ?>">
    <?= csrf_field() ?>

    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Fecha *</label>
            <input type="date" name="fecha" required value="<?= e($recarga['fecha']) ?>">
        </div>
        <div class="form-group">
            <label>Silo *</label>
            <select name="silo_id" required>
                <?php foreach ($silos as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $recarga['silo_id'] ? 'selected' : '' ?>>
                    <?= e($s['nombre']) ?> (<?= e($s['granja_nombre']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Tipo de pienso</label>
        <input type="text" name="tipo_pienso"
               value="<?= e($recarga['tipo_pienso'] ?? '') ?>"
               placeholder="Ej: Precrecimiento sopa, Crecimiento sopa…">
    </div>

    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Cantidad (kg) *</label>
            <input type="number" name="cantidad_kg" step="0.01" min="0.01" required
                   value="<?= e($recarga['cantidad_kg']) ?>">
        </div>
        <div class="form-group">
            <label>Proveedor</label>
            <input type="text" name="proveedor"
                   value="<?= e($recarga['proveedor'] ?? '') ?>"
                   placeholder="Nombre del proveedor">
        </div>
    </div>

    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Albarán</label>
            <input type="text" name="albaran"
                   value="<?= e($recarga['albaran'] ?? '') ?>"
                   placeholder="Nº de albarán">
        </div>
        <div class="form-group">
            <label>Observaciones</label>
            <input type="text" name="observaciones"
                   value="<?= e($recarga['observaciones'] ?? '') ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a href="<?= base_url('almacen') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
</div>
