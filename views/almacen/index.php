<?php
$hayAlertas = false;
foreach ($silos as $s) {
    if ($s['stock_actual_kg'] <= $s['stock_minimo_kg']) { $hayAlertas = true; break; }
}
?>

<div class="page-header">
    <h2>Almacén de pienso</h2>
</div>

<?php if ($hayAlertas): ?>
<div class="alert-flash alert-error" style="margin-bottom:1rem">
    Hay silos por debajo del stock mínimo
</div>
<?php endif; ?>

<?php if (empty($silos)): ?>
<div class="empty-state">No hay silos configurados. <a href="<?= base_url('silos/crear') ?>">Crear silo</a></div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem">
<?php foreach ($silos as $s):
    $pct    = (int)($s['pct_stock'] ?? 0);
    $alerta = $s['stock_actual_kg'] <= $s['stock_minimo_kg'];
    $color  = $alerta ? '#dc2626' : ($pct < 30 ? '#d97706' : '#16a34a');
?>
<a href="<?= base_url("almacen/{$s['id']}") ?>" class="list-card" style="display:block;text-decoration:none;color:inherit">
    <div style="padding:1rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem">
            <div>
                <div style="font-weight:700;font-size:.95rem"><?= e($s['nombre']) ?></div>
                <div style="font-size:.8rem;color:#6b7280"><?= e($s['granja_nombre']) ?></div>
                <?php if ($s['naves_abastecidas']): ?>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.2rem"><?= e($s['naves_abastecidas']) ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:right">
                <div style="font-size:1.5rem;font-weight:700;color:<?= $color ?>;line-height:1"><?= $pct ?>%</div>
                <div style="font-size:.7rem;color:#9ca3af">capacidad</div>
            </div>
        </div>
        <div style="height:8px;background:#e5e7eb;border-radius:99px;overflow:hidden;margin-bottom:.75rem">
            <div style="height:8px;width:<?= min($pct,100) ?>%;background:<?= $color ?>;border-radius:99px"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.8rem">
            <div>
                <div style="color:#6b7280">Stock actual</div>
                <div style="font-weight:600;color:<?= $alerta ? '#dc2626' : 'inherit' ?>"><?= number_format($s['stock_actual_kg'], 0) ?> kg</div>
            </div>
            <div>
                <div style="color:#6b7280">Stock mínimo</div>
                <div style="font-weight:600"><?= number_format($s['stock_minimo_kg'], 0) ?> kg</div>
            </div>
        </div>
        <?php if ($alerta): ?>
        <div style="margin-top:.6rem;font-size:.75rem;color:#dc2626;font-weight:600">Por debajo del mínimo</div>
        <?php endif; ?>
    </div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
