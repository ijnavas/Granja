<?php
$iconos = [
    'alta'        => '🐷',
    'pesaje'      => '⚖️',
    'movimiento'  => '↔',
    'cierre'      => '🔒',
];
$colores = [
    'alta'        => '#15803d',
    'pesaje'      => '#0891b2',
    'cierre'      => '#374151',
];
?>

<div class="page-header">
    <div>
        <h2>Trazabilidad <span style="font-family:monospace;font-weight:700;color:#1d4ed8"><?= e($lote['codigo']) ?></span></h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem">
            <?= e($lote['granja_nombre'] ?? '—') ?>
            <?= !empty($lote['nave_nombre']) ? ' · ' . e($lote['nave_nombre']) : '' ?>
            <?= !empty($lote['raza_nombre'])  ? ' · ' . e($lote['raza_nombre'])  : '' ?>
            <?= !empty($lote['fecha_nacimiento']) ? ' · Nacimiento ' . date('d/m/Y', strtotime($lote['fecha_nacimiento'])) : '' ?>
        </div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= base_url('lotes') ?>" class="btn btn-secondary">Volver</a>
        <a href="<?= base_url("lotes/{$lote['id']}/editar") ?>" class="btn btn-secondary">Editar lote</a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">Imprimir</button>
    </div>
</div>

<?php if (!empty($etiquetasLote)): ?>
<div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:1rem">
    <?php foreach ($etiquetasLote as $t): ?>
    <span style="background:<?= e($t['color']) ?>22;color:<?= e($t['color']) ?>;font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:99px;border:1px solid <?= e($t['color']) ?>44">
        <?= e($t['nombre']) ?>
    </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
    <div class="kpi-card">
        <div class="kpi-label">Animales actuales</div>
        <div class="kpi-value"><?= number_format((int)$lote['num_animales']) ?></div>
        <div class="kpi-sub">de <?= number_format((int)($lote['num_animales_entrada'] ?? 0)) ?> al alta</div>
    </div>
    <?php if (!empty($kpis['dias_en_granja'])): ?>
    <div class="kpi-card">
        <div class="kpi-label">Días en granja</div>
        <div class="kpi-value"><?= (int)$kpis['dias_en_granja'] ?></div>
        <div class="kpi-sub"><?= round($kpis['dias_en_granja'] / 7) ?> semanas</div>
    </div>
    <?php endif; ?>
    <?php if ($kpis['total_bajas'] > 0): ?>
    <div class="kpi-card">
        <div class="kpi-label">Bajas</div>
        <div class="kpi-value" style="color:#dc2626"><?= number_format($kpis['total_bajas']) ?></div>
        <?php
            $entrada = (int)($lote['num_animales_entrada'] ?? 0);
            $pct = $entrada > 0 ? round($kpis['total_bajas'] / $entrada * 100, 1) : 0;
        ?>
        <div class="kpi-sub"><?= $pct ?>% del lote</div>
    </div>
    <?php endif; ?>
    <?php if ($kpis['total_ventas'] > 0): ?>
    <div class="kpi-card">
        <div class="kpi-label">Vendidos</div>
        <div class="kpi-value"><?= number_format($kpis['total_ventas']) ?></div>
        <div class="kpi-sub"><?= number_format($kpis['total_kg_vend'], 1) ?> kg canal</div>
    </div>
    <?php endif; ?>
    <?php if ($kpis['total_eur'] > 0): ?>
    <div class="kpi-card success">
        <div class="kpi-label">Ingresos</div>
        <div class="kpi-value"><?= number_format($kpis['total_eur'], 2) ?> €</div>
    </div>
    <?php endif; ?>
</div>

<!-- Cuadras actuales -->
<?php if (!empty($cuadrasActuales)): ?>
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem">
    Cuadras actuales
</div>
<div class="list-card" style="margin-bottom:1.5rem">
    <table class="list-table">
        <thead><tr><th>Cuadra</th><th>Nave</th><th style="text-align:right">Animales</th></tr></thead>
        <tbody>
            <?php foreach ($cuadrasActuales as $c): ?>
            <tr>
                <td><strong><?= e($c['cuadra_nombre']) ?></strong></td>
                <td><?= e($c['nave_nombre']) ?></td>
                <td style="text-align:right;font-weight:600"><?= number_format((int)$c['num_animales']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Timeline cronológica -->
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Línea de tiempo (<?= count($eventos) ?> eventos)
</div>

<?php if (empty($eventos)): ?>
    <div class="empty-state">No hay eventos registrados para este lote.</div>
<?php else: ?>
<div style="position:relative;padding-left:2rem;border-left:2px solid #e5e7eb">
    <?php foreach ($eventos as $i => $ev):
        $color = $ev['color'] ?? ($colores[$ev['tipo']] ?? '#6b7280');
        $icono = $iconos[$ev['tipo']] ?? '•';
    ?>
    <div style="position:relative;margin-bottom:1.25rem">
        <!-- Punto en la línea -->
        <div style="position:absolute;left:-2.55rem;top:.25rem;width:1.65rem;height:1.65rem;border-radius:50%;background:<?= e($color) ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;border:3px solid #fff;box-shadow:0 0 0 2px <?= e($color) ?>">
            <?= $icono ?>
        </div>
        <div style="background:#fff;border:1.5px solid <?= e($color) ?>33;border-radius:8px;padding:.65rem .9rem">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.25rem;gap:.5rem;flex-wrap:wrap">
                <strong style="color:<?= e($color) ?>;font-size:.95rem"><?= e($ev['titulo']) ?></strong>
                <span style="font-size:.78rem;color:#9ca3af;font-family:monospace">
                    <?= date('d/m/Y', strtotime($ev['fecha'])) ?>
                </span>
            </div>
            <?php foreach ($ev['detalle'] as $linea): if (!$linea) continue; ?>
            <div style="font-size:.82rem;color:#4b5563;margin-top:.15rem"><?= e($linea) ?></div>
            <?php endforeach; ?>
            <?php if (!empty($ev['usuario'])): ?>
            <div style="font-size:.7rem;color:#9ca3af;margin-top:.3rem">
                Por <?= e($ev['usuario']) ?>
                <?php if (!empty($ev['mov_id'])): ?>
                · <a href="<?= base_url("movimientos/{$ev['mov_id']}/editar") ?>" style="color:#1d4ed8">ver movimiento</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
@media print {
    nav, aside, .page-header .btn, [class*="sidebar"] { display: none !important; }
    body { font-size: 11px; }
}
</style>
