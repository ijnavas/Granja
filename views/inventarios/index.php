<div class="page-header">
    <h2>Inventarios</h2>
    <a href="<?= base_url('inventarios/crear') ?>" class="btn btn-primary">+ Nuevo inventario</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="list-card">
<?php if (empty($inventarios)): ?>
    <div class="empty-state">No hay inventarios todavía. Genera el primero.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Nombre</th>
                <th style="text-align:right">Lotes</th>
                <th style="text-align:right">Animales</th>
                <th style="text-align:right">Valor total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inventarios as $inv): ?>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("inventarios/{$inv['id']}") ?>'">
                <td><strong><?= date('d/m/Y', strtotime($inv['fecha'])) ?></strong></td>
                <td style="color:#6b7280">
                    <?= $inv['nombre'] ? e($inv['nombre']) : '<span style="color:#d1d5db">—</span>' ?>
                    <?php $esCuadra = ($inv['tipo'] ?? 'cuadra') === 'cuadra'; ?>
                    <span style="margin-left:.4rem;background:<?= $esCuadra ? '#eff6ff' : '#f0fdf4' ?>;color:<?= $esCuadra ? '#1d4ed8' : '#166534' ?>;font-size:.68rem;font-weight:700;padding:.1rem .4rem;border-radius:.25rem;text-transform:uppercase;letter-spacing:.04em;vertical-align:middle">
                        <?= $esCuadra ? 'Cuadra' : 'Global' ?>
                    </span>
                </td>
                <td style="text-align:right"><?= number_format($inv['num_lineas']) ?></td>
                <td style="text-align:right;font-weight:600"><?= number_format($inv['total_animales']) ?></td>
                <td style="text-align:right;font-weight:600;color:#166534">
                    <?= $inv['valor_total'] ? number_format((float)$inv['valor_total'], 2) . ' €' : '—' ?>
                </td>
                <td onclick="event.stopPropagation()">
                    <div class="actions">
                        <a href="<?= base_url("inventarios/{$inv['id']}") ?>" class="btn btn-secondary btn-sm">Ver</a>
                        <form method="POST" action="<?= base_url("inventarios/{$inv['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar este inventario?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
