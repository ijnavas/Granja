<?php
$diasVida = $lote['fecha_entrada'] && $lote['fecha_cierre']
    ? (int)((strtotime($lote['fecha_cierre']) - strtotime($lote['fecha_entrada'])) / 86400)
    : null;
$mortalidad = $lote['num_animales_entrada']
    ? round($lote['total_bajas'] / $lote['num_animales_entrada'] * 100, 1)
    : null;
?>

<div class="page-header">
    <h2>
        <span style="font-family:monospace;color:#1d4ed8"><?= e($lote['codigo']) ?></span>
        <span style="font-weight:400;color:#6b7280;font-size:.9rem;margin-left:.5rem"><?= e($lote['granja_nombre'] ?? '') ?> <?= $lote['nave_nombre'] ? '· ' . e($lote['nave_nombre']) : '' ?></span>
    </h2>
    <a href="<?= base_url('lotes/historico') ?>" class="btn btn-secondary">Volver</a>
</div>

<!-- KPIs resumen -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem">
    <div class="kpi-card">
        <div class="kpi-label">Entrada</div>
        <div class="kpi-value" style="font-size:1.1rem"><?= $lote['fecha_entrada'] ? date('d/m/Y', strtotime($lote['fecha_entrada'])) : '—' ?></div>
        <div class="kpi-sub"><?= number_format((int)($lote['num_animales_entrada'] ?: $lote['num_animales'])) ?> animales</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Cierre</div>
        <div class="kpi-value" style="font-size:1.1rem"><?= $lote['fecha_cierre'] ? date('d/m/Y', strtotime($lote['fecha_cierre'])) : '—' ?></div>
        <?php if ($diasVida !== null): ?>
        <div class="kpi-sub"><?= $diasVida ?> días en granja</div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Vendidos</div>
        <div class="kpi-value" style="color:#16a34a"><?= number_format((int)$lote['total_vendidos']) ?></div>
        <div class="kpi-sub">animales</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Bajas</div>
        <div class="kpi-value" style="color:<?= $lote['total_bajas'] > 0 ? '#dc2626' : '#6b7280' ?>"><?= number_format((int)$lote['total_bajas']) ?></div>
        <?php if ($mortalidad !== null): ?>
        <div class="kpi-sub"><?= $mortalidad ?>% mortalidad</div>
        <?php endif; ?>
    </div>
    <?php if ($lote['peso_medio_venta_kg']): ?>
    <div class="kpi-card">
        <div class="kpi-label">Peso medio venta</div>
        <div class="kpi-value"><?= number_format((float)$lote['peso_medio_venta_kg'], 1) ?> kg</div>
        <div class="kpi-sub">canal</div>
    </div>
    <?php endif; ?>
    <?php if ($lote['precio_medio_eur']): ?>
    <div class="kpi-card">
        <div class="kpi-label">Precio medio</div>
        <div class="kpi-value"><?= number_format((float)$lote['precio_medio_eur'], 2) ?> €</div>
        <div class="kpi-sub">por animal</div>
    </div>
    <?php endif; ?>
    <?php if ($lote['ingreso_total'] > 0): ?>
    <div class="kpi-card">
        <div class="kpi-label">Ingreso total</div>
        <div class="kpi-value" style="color:#1d4ed8"><?= number_format((float)$lote['ingreso_total'], 0) ?> €</div>
        <div class="kpi-sub">ventas</div>
    </div>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

<!-- Movimientos -->
<div class="list-card">
    <div style="padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">
        Movimientos
    </div>
    <?php if (empty($lote['movimientos'])): ?>
    <div class="empty-state" style="font-size:.875rem">Sin movimientos registrados.</div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead>
            <tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;color:#6b7280">
                <th style="padding:.4rem .75rem;text-align:left">Fecha</th>
                <th style="padding:.4rem .75rem;text-align:left">Tipo</th>
                <th style="padding:.4rem .75rem;text-align:right">Animales</th>
                <th style="padding:.4rem .75rem;text-align:right">Peso canal</th>
                <th style="padding:.4rem .75rem;text-align:right">Precio</th>
                <th style="padding:.4rem .75rem;text-align:left">Detalle</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lote['movimientos'] as $i => $m):
            $badgeColor = match($m['tipo']) {
                'venta' => '#16a34a', 'baja' => '#dc2626',
                'entrada_cebo','entrada_reposicion','entrada_madres' => '#2563eb',
                default => '#6b7280'
            };
            $tipoLabel = match($m['tipo']) {
                'venta' => 'Venta', 'baja' => 'Baja',
                'traslado_cuadra' => 'Traslado', 'entrada_cebo' => 'Ent. cebo',
                'entrada_reposicion' => 'Reposición', 'entrada_madres' => 'Madres',
                default => ucfirst($m['tipo'])
            };
        ?>
        <tr style="background:<?= $i%2===0?'#fff':'#f9fafb' ?>;border-bottom:1px solid #f3f4f6">
            <td style="padding:.35rem .75rem;color:#6b7280"><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
            <td style="padding:.35rem .75rem">
                <span style="background:<?= $badgeColor ?>22;color:<?= $badgeColor ?>;padding:.1rem .45rem;border-radius:.3rem;font-size:.72rem;font-weight:700"><?= $tipoLabel ?></span>
            </td>
            <td style="padding:.35rem .75rem;text-align:right;font-weight:600"><?= number_format((int)$m['num_animales']) ?></td>
            <td style="padding:.35rem .75rem;text-align:right"><?= $m['peso_canal_kg'] ? number_format((float)$m['peso_canal_kg'], 1) . ' kg' : '—' ?></td>
            <td style="padding:.35rem .75rem;text-align:right"><?= $m['precio_eur'] ? number_format((float)$m['precio_eur'], 2) . ' €' : '—' ?></td>
            <td style="padding:.35rem .75rem;color:#6b7280;font-size:.78rem">
                <?php
                if ($m['tipo'] === 'venta' && $m['tipo_venta'])   echo e($m['tipo_venta']);
                elseif ($m['tipo'] === 'baja' && $m['motivo_baja']) echo e($m['motivo_baja']);
                elseif ($m['cuadra_destino_nombre'])               echo e($m['cuadra_destino_nombre'] . ($m['nave_destino_nombre'] ? ' · ' . $m['nave_destino_nombre'] : ''));
                elseif ($m['observaciones'])                        echo e($m['observaciones']);
                ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Pesajes -->
<div class="list-card">
    <div style="padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">
        Pesajes
    </div>
    <?php if (empty($lote['pesajes'])): ?>
    <div class="empty-state" style="font-size:.875rem">Sin pesajes registrados.</div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead>
            <tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;color:#6b7280">
                <th style="padding:.4rem .75rem;text-align:left">Fecha</th>
                <th style="padding:.4rem .75rem;text-align:right">Animales pesados</th>
                <th style="padding:.4rem .75rem;text-align:right">Peso medio</th>
                <th style="padding:.4rem .75rem;text-align:left">Registrado por</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lote['pesajes'] as $i => $p): ?>
        <tr style="background:<?= $i%2===0?'#fff':'#f9fafb' ?>;border-bottom:1px solid #f3f4f6">
            <td style="padding:.35rem .75rem;color:#6b7280"><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
            <td style="padding:.35rem .75rem;text-align:right"><?= number_format((int)$p['num_animales_pesados']) ?></td>
            <td style="padding:.35rem .75rem;text-align:right;font-weight:600"><?= number_format((float)$p['peso_medio_kg'], 2) ?> kg</td>
            <td style="padding:.35rem .75rem;color:#6b7280;font-size:.78rem"><?= e($p['usuario_nombre'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div>

<?php if ($lote['observaciones']): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="form-section-title" style="margin-bottom:.5rem">Observaciones del lote</div>
    <p style="font-size:.875rem;color:#374151"><?= e($lote['observaciones']) ?></p>
</div>
<?php endif; ?>
