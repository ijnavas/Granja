<?php
$pct    = (int)($silo['pct_stock'] ?? 0);
$alerta = $silo['stock_actual_kg'] <= $silo['stock_minimo_kg'];
$color  = $alerta ? '#dc2626' : ($pct < 30 ? '#d97706' : '#16a34a');
?>

<div class="page-header">
    <h2>Detalle silo</h2>
    <a href="<?= base_url('silos') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($alerta): ?>
    <div class="alert-flash alert-error">
        Stock por debajo del mínimo — quedan <?= number_format($silo['stock_actual_kg'], 0) ?> kg
        (mínimo: <?= number_format($silo['stock_minimo_kg'], 0) ?> kg)
    </div>
<?php endif; ?>

<div class="detail-header">
    <div>
        <div class="detail-nombre"><?= e($silo['nombre']) ?></div>
        <div class="detail-sub"><?= e($silo['granja_nombre']) ?></div>
        <div class="detail-meta">
            <div class="meta-item">
                <strong><?= number_format($silo['stock_actual_kg'], 0) ?> kg</strong>
                Stock actual
            </div>
            <div class="meta-item">
                <strong><?= number_format($silo['capacidad_kg'], 0) ?> kg</strong>
                Capacidad total
            </div>
            <div class="meta-item">
                <strong><?= number_format($silo['stock_minimo_kg'], 0) ?> kg</strong>
                Stock mínimo
            </div>
            <?php if ($silo['naves_abastecidas']): ?>
            <div class="meta-item">
                <strong><?= e($silo['naves_abastecidas']) ?></strong>
                Naves abastecidas
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div style="text-align:center;min-width:140px">
        <div style="font-size:2.5rem;font-weight:700;color:<?= $color ?>;line-height:1"><?= $pct ?>%</div>
        <div style="font-size:.78rem;color:#9ca3af;margin:.3rem 0 .6rem">nivel actual</div>
        <div style="height:12px;background:#e5e7eb;border-radius:99px;overflow:hidden">
            <div style="height:12px;width:<?= min($pct,100) ?>%;background:<?= $color ?>;border-radius:99px;transition:width .4s"></div>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:.75rem">
            <a href="<?= base_url("silos/{$silo['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
            <?php if (es_director()): ?>
            <form method="POST" action="<?= base_url("silos/{$silo['id']}/eliminar") ?>"
                  onsubmit="return confirm('¿Eliminar este silo?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($silo['descripcion']): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="form-section-title" style="margin-bottom:.75rem">Observaciones</div>
    <p style="font-size:.875rem;color:#374151"><?= e($silo['descripcion']) ?></p>
</div>
<?php endif; ?>