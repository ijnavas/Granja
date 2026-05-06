<?php $seccionConfig = "movimientos"; require __DIR__ . "/_submenu.php"; ?>

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('configuracion/movimientos') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php
$categorias = [
    'traslado'    => 'Traslado',
    'salida'      => 'Salida',
    'entrada'     => 'Entrada',
    'venta'       => 'Venta',
    'baja'        => 'Baja',
    'transicion'  => 'Transición de estado',
    're_creacion' => 'Creación lote RE',
    're_consumo'  => 'Consumo lote RE',
];
$esSistema = (int)$tipo['es_sistema'] === 1;
?>

<div class="form-card" style="max-width:680px">
    <form method="POST" action="<?= base_url("configuracion/movimientos/{$tipo['id']}/actualizar") ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required value="<?= e($tipo['nombre']) ?>">
                </div>
                <div class="form-group">
                    <label>Código <?= $esSistema ? '(no editable en tipos de sistema)' : '' ?></label>
                    <input type="text" name="codigo" required
                           value="<?= e($tipo['codigo']) ?>"
                           <?= $esSistema ? 'readonly style="background:#f3f4f6;font-family:monospace"' : 'pattern="[a-z0-9_]+" style="font-family:monospace"' ?>
                           oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'_')">
                </div>
            </div>
            <div class="form-group">
                <label>Categoría <?= $esSistema ? '(no editable en tipos de sistema)' : '' ?></label>
                <?php if ($esSistema): ?>
                    <input type="text" readonly value="<?= e($categorias[$tipo['categoria']] ?? $tipo['categoria']) ?>" style="background:#f3f4f6">
                    <input type="hidden" name="categoria" value="<?= e($tipo['categoria']) ?>">
                <?php else: ?>
                    <select name="categoria" required>
                        <?php foreach ($categorias as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $tipo['categoria'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Orden</label>
                    <input type="number" name="orden" value="<?= (int)$tipo['orden'] ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" value="<?= e($tipo['color'] ?: '#374151') ?>" style="height:42px">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label style="display:flex;align-items:center;gap:.5rem;padding:.6rem 0">
                        <input type="checkbox" name="activo" value="1" <?= (int)$tipo['activo'] === 1 ? 'checked' : '' ?>>
                        <span>Activo</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="<?= base_url('configuracion/movimientos') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
