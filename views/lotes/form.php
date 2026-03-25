<?php
$esEdicion = !is_null($lote);
$action    = $esEdicion ? base_url("lotes/{$lote['id']}/actualizar") : base_url('lotes');
?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('lotes') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= $action ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section-title">Identificación del lote</div>

            <?php if (!$esEdicion): ?>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Fecha de nacimiento *</label>
                    <input type="date" name="fecha_nacimiento" id="fechaNac" required
                           onchange="actualizarCodigo(this.value)"
                           value="<?= date('Y-m-d') ?>">
                    <span class="form-hint">El código se genera automáticamente</span>
                </div>
                <div class="form-group">
                    <label>Código de lote</label>
                    <input type="text" id="codigoPreview" disabled value="<?= e($codigoAuto) ?>"
                           style="background:#f9fafb;font-family:monospace;font-weight:600;color:#1d4ed8">
                    <span class="form-hint">Año/semana ISO del nacimiento</span>
                </div>
            </div>
            <?php else: ?>
            <div class="form-group" style="max-width:200px">
                <label>Código de lote</label>
                <input type="text" value="<?= e($lote['codigo']) ?>" disabled
                       style="background:#f9fafb;font-family:monospace;font-weight:600;color:#1d4ed8">
            </div>
            <?php endif; ?>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Tipo de animal *</label>
                    <select name="tipo_animal_id" required>
                        <option value="">— Selecciona tipo —</option>
                        <?php
                        $especieActual = '';
                        foreach ($tipos as $t):
                            if ($t['especie'] !== $especieActual):
                                if ($especieActual) echo '</optgroup>';
                                echo '<optgroup label="' . ucfirst($t['especie']) . '">';
                                $especieActual = $t['especie'];
                            endif;
                        ?>
                            <option value="<?= $t['id'] ?>" <?= ($lote['tipo_animal_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($especieActual) echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nave (opcional)</label>
                    <select name="nave_id">
                        <option value="">— Sin asignar aún —</option>
                        <?php foreach ($naves as $n): ?>
                            <option value="<?= $n['id'] ?>" <?= ($lote['nave_id'] ?? '') == $n['id'] ? 'selected' : '' ?>>
                                <?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Datos de entrada</div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Nº animales *</label>
                    <input type="number" name="num_animales" min="1" required
                           value="<?= e($lote['num_animales'] ?? '') ?>" placeholder="200">
                </div>
                <div class="form-group">
                    <label>Peso medio entrada (kg)</label>
                    <input type="number" name="peso_entrada_kg" step="0.001" min="0"
                           value="<?= e($lote['peso_entrada_kg'] ?? '') ?>" placeholder="6.500">
                </div>
                <div class="form-group">
                    <label>Fecha entrada granja</label>
                    <input type="date" name="fecha_entrada"
                           value="<?= e($lote['fecha_entrada'] ?? date('Y-m-d')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones"><?= e($lote['observaciones'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear lote' ?></button>
            <a href="<?= base_url('lotes') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function actualizarCodigo(fecha) {
    if (!fecha) return;
    const d    = new Date(fecha);
    const year = String(d.getFullYear()).slice(-2);
    const week = getISOWeek(d).toString().padStart(2, '0');
    document.getElementById('codigoPreview').value = 'L ' + year + '/' + week;
}

function getISOWeek(d) {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    date.setUTCDate(date.getUTCDate() + 4 - (date.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
}

// Inicializar con fecha actual
document.addEventListener('DOMContentLoaded', () => {
    const fn = document.getElementById('fechaNac');
    if (fn && fn.value) actualizarCodigo(fn.value);
});
</script>
