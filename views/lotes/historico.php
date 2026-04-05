<div class="page-header">
    <h2>Histórico de lotes</h2>
    <a href="<?= base_url('lotes') ?>" class="btn btn-secondary">Lotes activos</a>
</div>

<?php if (empty($lotes)): ?>
<div class="empty-state">No hay lotes cerrados todavía.</div>
<?php else: ?>
<div class="list-card">
    <table class="list-table">
        <thead>
            <tr>
                <th>Lote</th>
                <th>Granja · Nave</th>
                <th style="text-align:right">Entrada</th>
                <th style="text-align:right">Cierre</th>
                <th style="text-align:right">Animales</th>
                <th style="text-align:right">Vendidos</th>
                <th style="text-align:right">Bajas</th>
                <th style="text-align:right">Peso medio venta</th>
                <th style="text-align:right">Ingreso total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lotes as $l): ?>
            <tr>
                <td>
                    <span style="font-family:monospace;font-weight:700;color:#1d4ed8"><?= e($l['codigo']) ?></span>
                    <?php if ($l['raza_nombre']): ?>
                    <br><span style="font-size:.75rem;color:#9ca3af"><?= e($l['raza_nombre']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="color:#6b7280;font-size:.875rem">
                    <?= e($l['granja_nombre'] ?? '—') ?>
                    <?php if ($l['nave_nombre']): ?>· <?= e($l['nave_nombre']) ?><?php endif; ?>
                </td>
                <td style="text-align:right;font-size:.875rem"><?= $l['fecha_entrada'] ? date('d/m/Y', strtotime($l['fecha_entrada'])) : '—' ?></td>
                <td style="text-align:right;font-size:.875rem"><?= $l['fecha_cierre'] ? date('d/m/Y', strtotime($l['fecha_cierre'])) : '—' ?></td>
                <td style="text-align:right"><?= number_format((int)$l['num_animales_entrada'] ?: (int)$l['num_animales']) ?></td>
                <td style="text-align:right;font-weight:600;color:#16a34a"><?= number_format((int)$l['total_vendidos']) ?></td>
                <td style="text-align:right;color:<?= $l['total_bajas'] > 0 ? '#dc2626' : '#6b7280' ?>">
                    <?= number_format((int)$l['total_bajas']) ?>
                </td>
                <td style="text-align:right">
                    <?= $l['peso_medio_venta_kg'] ? number_format((float)$l['peso_medio_venta_kg'], 1) . ' kg' : '—' ?>
                </td>
                <td style="text-align:right;font-weight:600">
                    <?= $l['ingreso_total'] > 0 ? number_format((float)$l['ingreso_total'], 2) . ' €' : '—' ?>
                </td>
                <td>
                    <a href="<?= base_url("lotes/{$l['id']}/historico") ?>" class="btn btn-secondary btn-sm">Ver</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
