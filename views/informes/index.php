<?php
$f = $filtros ?? [];
$dimActual = $f['dimension'] ?? 'tipo_movimiento';
$catActual = $f['categoria'] ?? 'todos';

// Etiqueta de la dimensión y métricas relevantes según categoría elegida
$dimLabel = $dimensiones[$dimActual] ?? $dimActual;
$mostrarKgReal  = in_array($catActual, ['baja','salida','todos'], true);
$mostrarKgCanal = in_array($catActual, ['venta','todos'], true);
$mostrarEur     = in_array($catActual, ['venta','entrada','todos'], true);
?>

<div class="page-header">
    <h2>Informes</h2>
</div>

<?php if (!empty($errorInforme)): ?>
<div class="alert-flash alert-error" style="margin-bottom:1rem">
    <strong>Error al generar el informe:</strong><br>
    <code style="font-size:.8rem"><?= e($errorInforme) ?></code>
</div>
<?php endif; ?>

<?php
// Accesos rápidos: presets de filtros para informes habituales
$presets = [
    ['Bajas por mes',           ['categoria' => 'baja',     'dimension' => 'mes']],
    ['Bajas por tipo',          ['categoria' => 'baja',     'dimension' => 'tipo_movimiento']],
    ['Bajas por motivo',        ['categoria' => 'baja',     'dimension' => 'motivo_baja']],
    ['Bajas por estado animal', ['categoria' => 'baja',     'dimension' => 'estado_animal']],
    ['Ventas por mes',          ['categoria' => 'venta',    'dimension' => 'mes']],
    ['Ventas por tipo',         ['categoria' => 'venta',    'dimension' => 'tipo_movimiento']],
    ['Compras por mes',         ['categoria' => 'entrada',  'dimension' => 'mes']],
    ['Movimientos por nave',    ['categoria' => 'todos',    'dimension' => 'nave']],
    ['Movimientos por semana',  ['categoria' => 'todos',    'dimension' => 'semana_edad']],
];
?>

<div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1rem">
    <span style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;align-self:center;margin-right:.4rem">
        Acceso rápido
    </span>
    <?php foreach ($presets as [$label, $params]): ?>
        <?php $active = $params['categoria'] === $catActual && $params['dimension'] === $dimActual; ?>
        <a href="<?= base_url('informes?' . http_build_query($params)) ?>"
           class="btn <?= $active ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ────────────────────────────────────────────────────────────── -->
<!-- Filtros                                                          -->
<!-- ────────────────────────────────────────────────────────────── -->
<form method="GET" action="<?= base_url('informes') ?>"
      style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin-bottom:1.25rem">

    <div class="form-grid form-grid-3">
        <div class="form-group">
            <label>Tipo de informe</label>
            <select name="categoria">
                <?php foreach ($categorias as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $catActual === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Agrupar por</label>
            <select name="dimension">
                <?php foreach ($dimensiones as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $dimActual === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <div style="display:flex;gap:.5rem">
                <button type="submit" class="btn btn-primary">Generar</button>
                <a href="<?= base_url('informes') ?>" class="btn btn-secondary">Limpiar</a>
            </div>
        </div>
    </div>

    <div class="form-grid form-grid-2" style="margin-top:.4rem">
        <div class="form-group">
            <label>Fecha desde</label>
            <input type="date" name="fecha_desde" value="<?= e($f['fecha_desde']) ?>">
        </div>
        <div class="form-group">
            <label>Fecha hasta</label>
            <input type="date" name="fecha_hasta" value="<?= e($f['fecha_hasta']) ?>">
        </div>
    </div>

    <div class="form-grid form-grid-3" style="margin-top:.4rem">
        <div class="form-group">
            <label>Estados animal (multi)</label>
            <select name="estados[]" multiple size="4" style="min-height:90px">
                <?php foreach ($estadosMap as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= in_array($code, $f['estados'], true) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Ctrl/Cmd + clic para varios</span>
        </div>
        <div class="form-group">
            <label>Naves (multi)</label>
            <select name="naves[]" multiple size="4" style="min-height:90px">
                <?php foreach ($naves as $n): ?>
                <option value="<?= (int)$n['id'] ?>" <?= in_array((int)$n['id'], $f['naves'], true) ? 'selected' : '' ?>>
                    <?= e($n['granja_nombre'] ?? '') ?> · <?= e($n['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Lotes (multi)</label>
            <select name="lotes[]" multiple size="4" style="min-height:90px">
                <?php foreach ($lotes as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= in_array((int)$l['id'], $f['lotes'], true) ? 'selected' : '' ?>>
                    <?= e($l['codigo']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<!-- ────────────────────────────────────────────────────────────── -->
<!-- Resultados: KPIs                                                 -->
<!-- ────────────────────────────────────────────────────────────── -->
<?php if (empty($filas)): ?>
    <div class="empty-state">
        No hay movimientos para los filtros seleccionados.
    </div>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.25rem">
    <div class="kpi-card">
        <div class="kpi-label">Movimientos</div>
        <div class="kpi-value"><?= number_format($total['num_movimientos']) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Animales</div>
        <div class="kpi-value"><?= number_format($total['total_animales']) ?></div>
    </div>
    <?php if ($mostrarKgCanal): ?>
    <div class="kpi-card">
        <div class="kpi-label">Peso canal (kg)</div>
        <div class="kpi-value"><?= number_format($total['total_kg_canal'], 1) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($mostrarKgReal): ?>
    <div class="kpi-card">
        <div class="kpi-label">Peso real (kg)</div>
        <div class="kpi-value"><?= number_format($total['total_kg_real'], 1) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($mostrarEur): ?>
    <div class="kpi-card success">
        <div class="kpi-label">Total €</div>
        <div class="kpi-value"><?= number_format($total['total_eur'], 2) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- ────────────────────────────────────────────────────────────── -->
<!-- Gráfico                                                          -->
<!-- ────────────────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin-bottom:1.25rem">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
        <h3 style="font-size:.875rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin:0">
            <?= e($categorias[$catActual] ?? '') ?> agrupado por <?= e(strtolower($dimLabel)) ?>
        </h3>
        <select id="metricSel" onchange="renderChart()" style="font-size:.85rem;padding:.3rem .5rem;border:1px solid #d1d5db;border-radius:.35rem">
            <option value="total_animales">Animales</option>
            <option value="num_movimientos">Nº movimientos</option>
            <?php if ($mostrarKgCanal): ?><option value="total_kg_canal">Peso canal (kg)</option><?php endif; ?>
            <?php if ($mostrarKgReal): ?> <option value="total_kg_real">Peso real (kg)</option><?php endif; ?>
            <?php if ($mostrarEur): ?>    <option value="total_eur">Total €</option><?php endif; ?>
        </select>
    </div>
    <div style="position:relative;height:340px">
        <canvas id="chartInforme"></canvas>
    </div>
</div>

<!-- ────────────────────────────────────────────────────────────── -->
<!-- Tabla detalle                                                    -->
<!-- ────────────────────────────────────────────────────────────── -->
<div class="list-card">
    <table class="list-table">
        <thead>
            <tr>
                <th><?= e($dimLabel) ?></th>
                <th style="text-align:right">Movimientos</th>
                <th style="text-align:right">Animales</th>
                <?php if ($mostrarKgCanal): ?><th style="text-align:right">Peso canal (kg)</th><?php endif; ?>
                <?php if ($mostrarKgReal): ?><th style="text-align:right">Peso real (kg)</th><?php endif; ?>
                <?php if ($mostrarEur): ?><th style="text-align:right">Total €</th><?php endif; ?>
                <th style="text-align:right">Periodo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $r): ?>
            <tr>
                <td><strong><?= e($r['dimension_label'] ?? '—') ?></strong></td>
                <td style="text-align:right"><?= number_format((int)$r['num_movimientos']) ?></td>
                <td style="text-align:right;font-weight:600"><?= number_format((int)$r['total_animales']) ?></td>
                <?php if ($mostrarKgCanal): ?>
                <td style="text-align:right"><?= (float)$r['total_kg_canal'] > 0 ? number_format((float)$r['total_kg_canal'], 1) : '—' ?></td>
                <?php endif; ?>
                <?php if ($mostrarKgReal): ?>
                <td style="text-align:right"><?= (float)$r['total_kg_real'] > 0 ? number_format((float)$r['total_kg_real'], 1) : '—' ?></td>
                <?php endif; ?>
                <?php if ($mostrarEur): ?>
                <td style="text-align:right;color:#166534;font-weight:600"><?= (float)$r['total_eur'] > 0 ? number_format((float)$r['total_eur'], 2) : '—' ?></td>
                <?php endif; ?>
                <td style="text-align:right;color:#9ca3af;font-size:.8rem">
                    <?php if ($r['primera_fecha']): ?>
                        <?= date('d/m/Y', strtotime($r['primera_fecha'])) ?>
                        <?php if ($r['primera_fecha'] !== $r['ultima_fecha']): ?>
                            → <?= date('d/m/Y', strtotime($r['ultima_fecha'])) ?>
                        <?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700;border-top:2px solid #e5e7eb">
                <td>TOTAL</td>
                <td style="text-align:right"><?= number_format($total['num_movimientos']) ?></td>
                <td style="text-align:right"><?= number_format($total['total_animales']) ?></td>
                <?php if ($mostrarKgCanal): ?>
                <td style="text-align:right"><?= number_format($total['total_kg_canal'], 1) ?></td>
                <?php endif; ?>
                <?php if ($mostrarKgReal): ?>
                <td style="text-align:right"><?= number_format($total['total_kg_real'], 1) ?></td>
                <?php endif; ?>
                <?php if ($mostrarEur): ?>
                <td style="text-align:right;color:#166534"><?= number_format($total['total_eur'], 2) ?></td>
                <?php endif; ?>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- ────────────────────────────────────────────────────────────── -->
<!-- Chart.js                                                          -->
<!-- ────────────────────────────────────────────────────────────── -->
<script src="<?= base_url('lib/chartjs/chart.umd.min.js') ?>"></script>
<script>
const FILAS = <?= json_encode($filas, JSON_UNESCAPED_UNICODE) ?>;
let chart = null;

const METRICAS = {
    'total_animales':  { label: 'Animales',          color: '#1d4ed8' },
    'num_movimientos': { label: 'Nº movimientos',    color: '#0891b2' },
    'total_kg_canal':  { label: 'Peso canal (kg)',   color: '#9333ea' },
    'total_kg_real':   { label: 'Peso real (kg)',    color: '#0e7490' },
    'total_eur':       { label: 'Total €',           color: '#166534' },
};

function renderChart() {
    const sel  = document.getElementById('metricSel');
    const key  = sel.value;
    const m    = METRICAS[key];
    const ctx  = document.getElementById('chartInforme').getContext('2d');

    const labels = FILAS.map(f => f.dimension_label || '—');
    const data   = FILAS.map(f => parseFloat(f[key]) || 0);

    if (chart) chart.destroy();
    chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: m.label,
                data,
                backgroundColor: m.color + 'cc',
                borderColor:     m.color,
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${m.label}: ${ctx.parsed.y.toLocaleString('es-ES')}`,
                    }
                },
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('es-ES') } },
                x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: false } },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', renderChart);
</script>

<?php endif; ?>
