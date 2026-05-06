<?php
$estadoLabels = [
    'lechon'     => 'Lechón',
    'cebo'       => 'Cebo',
    'reposicion' => 'Reposición',
    'madres'     => 'Madres',
];

$tipo     = $inventario['tipo'] ?? 'cuadra';
$esPienso = $tipo === 'pienso';
$esCuadra = $tipo === 'cuadra';

// Totales animales
$totalAnimales = array_sum(array_column($lineas, 'num_animales'));
$totalPeso     = array_sum(array_column($lineas, 'peso_total_kg'));
$totalValor    = array_sum(array_column($lineas, 'valor_total_eur'));

// Totales pienso
$totalKgSilos = array_sum(array_column($lineas_silos ?? [], 'stock_kg'));
?>

<div class="page-header">
    <div>
        <h2><?= e($pageTitle) ?></h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem;display:flex;gap:.75rem;align-items:center">
            <?php if ($inventario['nombre']): ?>
                <span><?= e($inventario['nombre']) ?></span>
                <span style="color:#d1d5db">·</span>
            <?php endif; ?>
            <?php
                $badgeBg    = $esPienso ? '#fefce8' : ($esCuadra ? '#eff6ff' : '#f0fdf4');
                $badgeColor = $esPienso ? '#92400e'  : ($esCuadra ? '#1d4ed8' : '#166534');
                $badgeLabel = $esPienso ? 'Pienso'   : ($esCuadra ? 'Por cuadra' : 'Global');
            ?>
            <span style="background:<?= $badgeBg ?>;color:<?= $badgeColor ?>;font-size:.72rem;font-weight:700;padding:.2rem .5rem;border-radius:.25rem;text-transform:uppercase;letter-spacing:.05em">
                <?= $badgeLabel ?>
            </span>
        </div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= base_url('inventarios') ?>" class="btn btn-secondary">Volver</a>
        <a href="<?= base_url("inventarios/{$inventario['id']}/excel") ?>" class="btn btn-secondary">
            Exportar Excel
        </a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">
            Exportar PDF
        </button>
        <button type="button" onclick="document.getElementById('modalEmail').style.display='flex'" class="btn btn-secondary">
            Enviar por email
        </button>
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
<?php if ($esPienso): ?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="kpi-card">
        <div class="kpi-label">Silos</div>
        <div class="kpi-value"><?= count($lineas_silos) ?></div>
        <div class="kpi-sub">activos en granja</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Stock total</div>
        <div class="kpi-value"><?= number_format($totalKgSilos, 0) ?> kg</div>
        <div class="kpi-sub">suma de todos los silos</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Silos bajo mínimo</div>
        <?php $bajoMinimo = count(array_filter($lineas_silos, fn($s) => (float)$s['stock_kg'] <= (float)$s['stock_minimo_kg'])); ?>
        <div class="kpi-value" style="color:<?= $bajoMinimo > 0 ? '#dc2626' : '#16a34a' ?>"><?= $bajoMinimo ?></div>
        <div class="kpi-sub"><?= $bajoMinimo > 0 ? 'requieren atención' : 'todos en nivel correcto' ?></div>
    </div>
</div>
<?php else: ?>
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
<?php endif; ?>

<!-- Tabla detalle -->
<?php if ($esPienso): ?>
<div class="list-card">
    <div style="padding:.75rem 1rem;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">
        Snapshot de silos
    </div>
    <?php if (empty($lineas_silos)): ?>
        <div class="empty-state">Sin silos registrados.</div>
    <?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Granja</th>
                <th>Silo</th>
                <th>Tipo pienso</th>
                <th style="text-align:right">Stock (kg)</th>
                <th style="text-align:right">Capacidad (kg)</th>
                <th style="text-align:right">Mínimo (kg)</th>
                <th style="text-align:center">% Lleno</th>
                <th style="text-align:center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $granjaActual = null;
            foreach ($lineas_silos as $s):
                $esNuevaGranja = $s['granja_nombre'] !== $granjaActual;
                if ($esNuevaGranja) $granjaActual = $s['granja_nombre'];
                $pct    = (int)($s['pct_stock'] ?? 0);
                $alerta = (float)$s['stock_kg'] <= (float)$s['stock_minimo_kg'];
                $color  = $alerta ? '#dc2626' : ($pct < 30 ? '#d97706' : '#16a34a');
            ?>
            <?php if ($esNuevaGranja): ?>
            <tr>
                <td colspan="8" style="background:#fefce8;font-weight:700;font-size:.8rem;color:#92400e;padding:.4rem .75rem;border-bottom:1px solid #fde68a">
                    <?= e($s['granja_nombre']) ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="color:#9ca3af;font-size:.78rem"></td>
                <td style="font-weight:600"><?= e($s['silo_nombre']) ?></td>
                <td style="color:#374151"><?= $s['tipo_pienso'] ? e($s['tipo_pienso']) : '<span style="color:#d1d5db">—</span>' ?></td>
                <td style="text-align:right;font-weight:600;color:<?= $color ?>"><?= number_format((float)$s['stock_kg'], 0) ?></td>
                <td style="text-align:right;color:#6b7280"><?= number_format((float)$s['capacidad_kg'], 0) ?></td>
                <td style="text-align:right;color:#6b7280"><?= number_format((float)$s['stock_minimo_kg'], 0) ?></td>
                <td style="text-align:center">
                    <div style="display:inline-flex;align-items:center;gap:.4rem">
                        <div style="width:60px;height:6px;background:#e5e7eb;border-radius:99px;overflow:hidden">
                            <div style="height:6px;width:<?= min($pct,100) ?>%;background:<?= $color ?>;border-radius:99px"></div>
                        </div>
                        <span style="font-weight:700;color:<?= $color ?>;font-size:.8rem"><?= $pct ?>%</span>
                    </div>
                </td>
                <td style="text-align:center">
                    <?php if ($alerta): ?>
                        <span style="font-size:.72rem;font-weight:700;color:#dc2626;background:#fee2e2;padding:.15rem .5rem;border-radius:.25rem">Bajo mínimo</span>
                    <?php else: ?>
                        <span style="font-size:.72rem;color:#16a34a;background:#dcfce7;padding:.15rem .5rem;border-radius:.25rem">OK</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:700;border-top:2px solid #e5e7eb">
                <td colspan="3" style="padding:.6rem .75rem;font-size:.82rem;color:#374151">TOTAL</td>
                <td style="text-align:right;padding:.6rem .75rem"><?= number_format($totalKgSilos, 0) ?> kg</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="list-card">
    <div style="padding:.75rem 1rem;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">
        <?= $esCuadra ? 'Detalle por lote y cuadra' : 'Detalle global por lote' ?>
    </div>
    <?php if (empty($lineas)): ?>
        <div class="empty-state">Sin líneas registradas.</div>
    <?php else: ?>
    <table class="list-table" id="tablaInventario">
        <thead>
            <tr>
                <th>Granja</th>
                <th><?= $esCuadra ? 'Nave · Cuadra' : 'Naves' ?></th>
                <th>Lote</th>
                <th>Estado</th>
                <th style="text-align:center">Semana</th>
                <th style="text-align:right">Animales</th>
                <th style="text-align:right">Peso/ud (kg)</th>
                <th style="text-align:right">Peso real (kg/ud)</th>
                <th style="text-align:right">Peso total (kg)</th>
                <th style="text-align:right">Valor/ud (€)</th>
                <th style="text-align:right">Valor total (€)</th>
            </tr>
            <!-- Filtros por columna -->
            <tr class="filtros-fila" style="background:#fafafa">
                <?php for ($i = 0; $i < 11; $i++): ?>
                <th style="padding:.3rem .5rem">
                    <input type="text" oninput="filtrarTabla()" data-col="<?= $i ?>"
                           placeholder="Filtrar"
                           style="width:100%;padding:.2rem .35rem;border:1px solid #d1d5db;border-radius:.25rem;font-size:.75rem;font-weight:400">
                </th>
                <?php endfor; ?>
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
                <td style="text-align:right;color:#1d4ed8;font-weight:500"><?= !empty($l['peso_real_kg']) ? number_format((float)$l['peso_real_kg'], 3) : '<span style="color:#d1d5db">—</span>' ?></td>
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
                <td></td>
                <td style="text-align:right;padding:.6rem .75rem"><?= $totalPeso   ? number_format($totalPeso, 1)   : '—' ?></td>
                <td></td>
                <td style="text-align:right;padding:.6rem .75rem;color:#166534"><?= $totalValor ? number_format($totalValor, 2) . ' €' : '—' ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>

<script>
function filtrarTabla() {
    const filtros = Array.from(document.querySelectorAll('#tablaInventario .filtros-fila input'))
        .map(i => (i.value || '').trim().toLowerCase());
    const tbody = document.querySelector('#tablaInventario tbody');
    if (!tbody) return;
    let visibles = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
        // Saltar filas separadoras (cabecera de granja con colspan)
        const tds = tr.querySelectorAll('td');
        if (tds.length === 1 && tds[0].hasAttribute('colspan')) return;
        let show = true;
        tds.forEach((td, idx) => {
            const f = filtros[idx];
            if (!f) return;
            const txt = (td.textContent || '').trim().toLowerCase();
            if (!txt.includes(f)) show = false;
        });
        tr.style.display = show ? '' : 'none';
        if (show) visibles++;
    });
}
</script>
<?php endif; ?>

<!-- Modal email -->
<div id="modalEmail" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:.75rem;padding:1.5rem;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.2)">
        <h3 style="margin:0 0 1rem;font-size:1rem">Enviar inventario por email</h3>
        <form method="POST" action="<?= base_url("inventarios/{$inventario['id']}/email") ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Dirección de email *</label>
                <input type="email" name="email" required autofocus placeholder="destinatario@ejemplo.com">
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem">
                <button type="submit" class="btn btn-primary">Enviar</button>
                <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('modalEmail').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    nav, aside, .page-header .btn, .page-header form, #modalEmail,
    [class*="sidebar"], [class*="nav"], [class*="header"] { display: none !important; }
    .kpi-card { border: 1px solid #e5e7eb !important; box-shadow: none !important; }
    body { font-size: 11px; }
}
</style>
