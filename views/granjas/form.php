<?php
$esEdicion = !is_null($granja);
$action    = $esEdicion ? base_url("granjas/{$granja['id']}/actualizar") : base_url('granjas');
?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('granjas') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= $action ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section-title">Datos generales</div>

            <div class="form-group">
                <label>Nombre de la granja *</label>
                <input type="text" name="nombre" required value="<?= e($granja['nombre'] ?? '') ?>" placeholder="Granja El Pinar">
            </div>

            <div class="form-group">
                <label>Tipo de producción</label>
                <select name="tipo_produccion">
                    <option value="">— Sin especificar —</option>
                    <?php foreach (['Cebo', 'Ciclo cerrado', 'Maternidad', 'Recría', 'Mixta'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($granja['tipo_produccion'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Ubicación</div>

            <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion" value="<?= e($granja['direccion'] ?? '') ?>" placeholder="Calle, número, polígono...">
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Municipio</label>
                    <input type="text" name="municipio" value="<?= e($granja['municipio'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Provincia</label>
                    <input type="text" name="provincia" value="<?= e($granja['provincia'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group" style="max-width:160px">
                <label>Código postal</label>
                <input type="text" name="codigo_postal" maxlength="10" value="<?= e($granja['codigo_postal'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear granja' ?></button>
            <a href="<?= base_url('granjas') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
