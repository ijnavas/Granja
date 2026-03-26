<?php $seccionConfig = 'estados'; require __DIR__ . '/_submenu.php'; ?>

<div class="page-header">
    <h2>Editar estado</h2>
    <a href="<?= base_url('configuracion/estados') ?>" class="btn btn-secondary">Volver</a>
</div>

<div class="form-card">
    <form method="POST" action="<?= base_url("configuracion/estados/{$estado['id']}/actualizar") ?>">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" required value="<?= e($estado['nombre']) ?>">
            </div>
            <div class="form-group">
                <label>Código *</label>
                <input type="text" name="codigo" required value="<?= e($estado['codigo']) ?>"
                       style="font-family:monospace"
                       oninput="this.value=this.value.toLowerCase().replace(/\s+/g,'_')">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="<?= base_url('configuracion/estados') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>