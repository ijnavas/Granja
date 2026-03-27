<?php
$pct   = $cuadra['capacidad_maxima'] > 0
    ? round((array_sum(array_column($lotes, 'num_animales')) / $cuadra['capacidad_maxima']) * 100)
    : 0;
$totalAnimales = array_sum(array_column($lotes, 'num_animales'));
?>

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
    <div class="lote-card" style="cursor:pointer" onclick="window.location='<?= base_url("lotes/{$l['lote_id']}/editar") ?>'"
         onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,.12)'" onmouseout="this.style.boxShadow=''">
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
                  onsubmit="event.stopPropagation(); return confirm('¿Retirar este lote de la cuadra?')">
                <?= csrf_field() ?>
                <input type="hidden" name="cuadra_lote_id" value="<?= $l['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" onclick="event.stopPropagation()">Retirar</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Panel asignar lote -->