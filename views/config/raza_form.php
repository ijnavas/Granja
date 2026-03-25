<div class="page-header">
    <h2>Editar raza porcina</h2>
    <a href="<?= base_url('configuracion') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= base_url("configuracion/razas/{$raza['id']}/actualizar") ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" required value="<?= e($raza['nombre']) ?>">
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Porcentaje</label>
                    <input type="text" name="porcentaje" value="<?= e($raza['porcentaje'] ?? '') ?>" placeholder="100%, 50%...">
                </div>
                <div class="form-group">
                    <label>Identificador</label>
                    <input type="text" name="identificador" maxlength="5"
                           value="<?= e($raza['identificador'] ?? '') ?>"
                           placeholder="IB, DU..."
                           style="text-transform:uppercase;font-family:monospace;font-weight:700"
                           oninput="this.value=this.value.toUpperCase()">
                    <span class="form-hint">Siglas que aparecen al final del código del lote. Puede estar vacío.</span>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="<?= base_url('configuracion') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>