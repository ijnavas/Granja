<?php
$pct   = $cuadra['capacidad_maxima'] > 0
    ? round((array_sum(array_column($lotes, 'num_animales')) / $cuadra['capacidad_maxima']) * 100)
    : 0;
$totalAnimales = array_sum(array_column($lotes, 'num_animales'));
?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">
<style>
    .cuadra-header {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 6px rgba(0,0,0,.07);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 1rem;
        align-items: start;
    }
    .cuadra-meta { display: flex; flex-wrap: wrap; gap: 1.5rem; margin-top: .75rem; }
    .meta-item { font-size: .82rem; color: #6b7280; }
    .meta-item strong { display: block; font-size: 1.1rem; color: #111827; font-weight: 600; }
    .ocupacion-grande {
        text-align: center;
        min-width: 120px;
    }
    .pct-num { font-size: 2rem; font-weight: 700; color: #111827; line-height: 1; }
    .pct-sub { font-size: .78rem; color: #9ca3af; }
    .barra-grande { height: 8px; background: #e5e7eb; border-radius: 99px; margin: .5rem 0; }
    .barra-grande-fill { height: 8px; border-radius: 99px; }

    .lote-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 6px rgba(0,0,0,.07);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .75rem;
    }
    .lote-codigo { font-family: monospace; font-size: 1rem; font-weight: 700; color: #1d4ed8; }
    .lote-info   { font-size: .82rem; color: #6b7280; margin-top: .2rem; }
    .lote-animales { font-size: 1.5rem; font-weight: 700; color: #111827; text-align: right; }
    .lote-animales span { display: block; font-size: .75rem; font-weight: 400; color: #9ca3af; }

    .panel-asignar {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 6px rgba(0,0,0,.07);
        padding: 1.5rem;
    }
</style>

<div class="page-header">
    <h2>Cuadra: <?= e($cuadra['nombre']) ?></h2>
    <div style="display:flex;gap:.5rem">
        <a href="<?= base_url("cuadras/{$cuadra['id']}/editar") ?>" class="btn btn-secondary">Editar</a>
        <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary">Volver</a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- Cabecera cuadra -->
<div class="cuadra-header">
    <div>
        <div style="font-size:.82rem;color:#6b7280;margin-bottom:.25rem">
            <?= e($cuadra['granja_nombre']) ?> › <?= e($cuadra['nave_nombre']) ?>
        </div>
        <div class="cuadra-meta">
            <?php if ($cuadra['ancho_m'] || $cuadra['largo_m'] || $cuadra['alto_m']): ?>
            <div class="meta-item">
                <strong>
                    <?php
                    $dims = array_filter([
                        $cuadra['ancho_m'] ? $cuadra['ancho_m'].'m' : null,
                        $cuadra['largo_m'] ? $cuadra['largo_m'].'m' : null,
                        $cuadra['alto_m']  ? $cuadra['alto_m'].'m'  : null,
                    ]);
                    echo implode(' × ', $dims);
                    ?>
                </strong>
                Dimensiones
            </div>
            <?php endif; ?>
            <div class="meta-item">
                <strong><?= number_format($cuadra['capacidad_maxima']) ?></strong>
                Capacidad máx.
            </div>
            <div class="meta-item">
                <strong><?= number_format($totalAnimales) ?></strong>
                Animales actuales
            </div>
            <div class="meta-item">
                <strong><?= count($lotes) ?></strong>
                Lotes activos
            </div>
        </div>
    </div>
    <div class="ocupacion-grande">
        <div class="pct-num"><?= $pct ?>%</div>
        <div class="pct-sub">ocupación</div>
        <div class="barra-grande">
            <?php $colorBarra = $pct >= 90 ? '#dc2626' : ($pct >= 60 ? '#d97706' : '#16a34a'); ?>
            <div class="barra-grande-fill" style="width:<?= min($pct,100) ?>%;background:<?= $colorBarra ?>"></div>
        </div>
    </div>
</div>

<!-- Lotes en esta cuadra -->
<h3 style="font-size:.875rem;font-weight:600;color:#374151;margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.04em">
    Lotes en esta cuadra
</h3>

<?php if (empty($lotes)): ?>
    <div style="background:#fff;border-radius:10px;padding:2rem;text-align:center;color:#9ca3af;font-size:.875rem;margin-bottom:1.5rem;box-shadow:0 1px 6px rgba(0,0,0,.07)">
        Esta cuadra está vacía. Asigna un lote desde abajo.
    </div>
<?php else: ?>
    <?php foreach ($lotes as $l): ?>
    <div class="lote-card">
        <div>
            <div class="lote-codigo"><?= e($l['codigo']) ?></div>
            <div class="lote-info">
                <?= e($l['tipo_animal']) ?> ·
                Entrada <?= e(date('d/m/Y', strtotime($l['fecha_entrada']))) ?>
                <?php if ($l['observaciones']): ?>
                    · <?= e($l['observaciones']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:1.5rem">
            <div class="lote-animales">
                <?= number_format($l['num_animales']) ?>
                <span>animales aquí</span>
            </div>
            <div style="font-size:.78rem;color:#9ca3af;text-align:right">
                <?= number_format($l['total_lote']) ?> total lote
            </div>
            <form method="POST" action="<?= base_url("cuadras/{$cuadra['id']}/retirar") ?>"
                  onsubmit="return confirm('¿Retirar este lote de la cuadra?')">
                <?= csrf_field() ?>
                <input type="hidden" name="cuadra_lote_id" value="<?= $l['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Retirar</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Panel asignar lote -->
<div class="panel-asignar" style="margin-top:1.5rem">
    <div class="form-section-title" style="margin-bottom:1rem">Asignar lote a esta cuadra</div>
    <form method="POST" action="<?= base_url("cuadras/{$cuadra['id']}/asignar") ?>">
        <?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:.75rem;align-items:end">
            <div class="form-group">
                <label>Lote</label>
                <select name="lote_id" required>
                    <option value="">— Selecciona lote —</option>
                    <?php foreach ($lotesDisponibles as $l): ?>
                        <option value="<?= $l['id'] ?>">
                            <?= e($l['codigo']) ?> · <?= e($l['tipo_animal_nombre']) ?> · <?= number_format($l['num_animales']) ?> animales
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Nº animales</label>
                <input type="number" name="num_animales" min="1" required placeholder="0">
            </div>
            <div class="form-group">
                <label>Fecha entrada</label>
                <input type="date" name="fecha_entrada" value="<?= date('Y-m-d') ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:0">Asignar</button>
        </div>
        <div class="form-group" style="margin-top:.75rem">
            <label>Observaciones (opcional)</label>
            <input type="text" name="observaciones" placeholder="Notas sobre esta asignación...">
        </div>
    </form>
</div>
