<?php
$estadoLabels = [
    'lechon'     => 'Lechón',
    'cebo'       => 'Cebo',
    'reposicion' => 'Reposición',
    'madres'     => 'Madres',
];

$esCuadra = ($inventario['tipo'] ?? 'cuadra') === 'cuadra';

// Totales
$totalAnimales = array_sum(array_column($lineas, 'num_animales'));
$totalPeso     = array_sum(array_column($lineas, 'peso_total_kg'));
$totalValor    = array_sum(array_column($lineas, 'valor_total_eur'));
?>

<div class="page-header">
    <div>
        <h2><?= e($pageTitle) ?></h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem;display:flex;gap:.75rem;align-items:center">
            <?php if ($inventario['nombre']): ?>
                <span><?= e($inventario['nombre']) ?></span>
                <span style="color:#d1d5db">·</span>
            <?php endif; ?>
            <span style="background:<?= $esCuadra ? '#eff6ff' : '#f0fdf4' ?>;color:<?= $esCuadra ? '#1d4ed8' : '#166534' ?>;font-size:.72rem;font-weight:700;padding:.2rem .5rem;border-radius:.25rem;text-transform:uppercase;letter-spacing:.05em">
                <?= $esCuadra ? 'Por cuadra' : 'Global' ?>
            </span>
        </div>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="<?= base_url('inventarios') ?>" class="btn btn-secondary">Volver</a>
        <form method="POST" action="<?= base_url("inventarios/{$inventario['id']}/eliminar") ?>"
              onsubmit="return confirm('¿Eliminar este inventario permanentemente?')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Eliminar</button>
        </form>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>

<!-- KPIs resumen -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="kpi-card">
        <div class="kpi-label">Total animales</div>
        <div class="kpi-value"><?= number_format($totalAnimales) ?></div>
        <div class="kpi-sub"><?= count($lineas) ?> líneas</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Peso total estimado</div>
        <div class="kpi-value"><?= $totalPeso ? number_format($totalPeso / 1000, 1) . ' t' : '—' ?></div>
        <div class="kpi-sub"><?= $totalPeso ? number_format($totalPeso, 0) . ' kg' : 'Sin tabla asignada' ?></div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-label">Valor total estimado</div>
        <div class="kpi-value"><?= $totalValor ? number_format($totalValor, 2) . ' €' : '—' ?></div>
        <div class="kpi-sub">Según tabla de crecimiento</div>
    </div>
</div>

<!-- Tabla detalle -->
<div class="list-card">
    <div style="padding:.75rem 1rem;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">
        <?= $esCuadra ? 'Detalle por lote y cuadra' : 'Detalle global por lote' ?>
    </div>
    <?php if (empty($lineas)): ?>
        <div class="empty-state">Sin líneas registradas.</div>
    <?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Granja</th>
                <th><?= $esCuadra ? 'Nave · Cuadra' : 'Naves' ?></th>
                <th>Lote</th>
                <th>Estado</th>
                <th style="text-align:center">Semana</th>
                <th style="text-align:right">Animales</th>
                <th style="text-align:right">Peso/ud (kg)</th>
                <th style="text-align:right">Peso total (kg)</th>
                <th style="text-align:right">Valor/ud (€)</th>
                <th style="text-align:right">Valor total (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $granjaActual = null;
            foreach ($lineas as $l):
                $esNuevaGranja = $l['granja_nombre'] !== $granjaActual;
                if ($esNuevaGranja) $granjaActual = $l['granja_nombre'];
            ?>
            <?php if ($esNuevaGranja): ?>
            <tr>
                <td colspan="10" style="background:#f0f9ff;font-weight:700;font-size:.8rem;color:#0369a1;padding:.4rem .75rem;border-bottom:1px solid #bae6fd">
                    <?= e($l['granja_nombre']) ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="color:#9ca3af;font-size:.78rem"></td>
                <td style="font-size:.82rem;color:#6b7280">
                    <?php if ($esCuadra): ?>
                        <?= e($l['nave_nombre'] ?? '—') ?>
                        <?php if ($l['cuadra_nombre']): ?>
                            <span style="color:#d1d5db"> · </span><?= e($l['cuadra_nombre']) ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?= e($l['nave_nombre'] ?? '—') ?>
                    <?php endif; ?>
                </td>
                <td><span style="font-family:monospace;font-weight:600;color:#1d4ed8"><?= e($l['lote_codigo']) ?></span></td>
                <td>
                    <?php $est = $estadoLabels[$l['estado_animal']] ?? ($l['estado_animal'] ?? '—'); ?>
                    <span style="font-size:.78rem;color:#374151"><?= e($est) ?></span>
                </td>
                <td style="text-align:center;color:#9ca3af;font-size:.82rem">
                    <?= $l['semana_tabla'] ? 'S' . $l['semana_tabla'] : '—' ?>
                </td>
                <td style="text-align:right;font-weight:600"><?= number_format($l['num_animales']) ?></td>
                <td style="text-align:right"><?= $l['peso_kg']         ? number_format((float)$l['peso_kg'], 3)         : '—' ?></td>
                <td style="text-align:right"><?= $l['peso_total_kg']   ? number_format((float)$l['peso_total_kg'], 1)   : '—' ?></td>
                <td style="text-align:right"><?= $l['coste_eur']       ? number_format((float)$l['coste_eur'], 2)       : '—' ?></td>
                <td style="text-align:right;font-weight:600;color:#166534"><?= $l['valor_total_eur'] ? number_format((float)$l['valor_total_eur'], 2) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700;border-top:2px solid #e5e7eb">
                <td colspan="5" style="padding:.6rem .75rem;font-size:.82rem;color:#374151">TOTAL</td>
                <td style="text-align:right;padding:.6rem .75rem"><?= number_format($totalAnimales) ?></td>
                <td></td>
                <td style="text-align:right;padding:.6rem .75rem"><?= $totalPeso   ? number_format($totalPeso, 1)   : '—' ?></td>
                <td></td>
                <td style="text-align:right;padding:.6rem .75rem;color:#166534"><?= $totalValor ? number_format($totalValor, 2) . ' €' : '—' ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>
