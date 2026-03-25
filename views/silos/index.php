<?php $pageTitle = 'Silos'; ?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

<div class="page-header">
    <h2>Silos de pienso</h2>
    <a href="<?= base_url('silos/crear') ?>" class="btn btn-primary">+ Nuevo silo</a>
</div>

<?php if ($flash = \App\Core\Session::getFlash('success')): ?>
    <div class="alert-flash alert-success"><?= $flash ?></div>
<?php endif; ?>

<div class="list-card">
<?php if (empty($silos)): ?>
    <div class="empty-state">No hay silos. <a href="<?= base_url('silos/crear') ?>">Crea el primero</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("silos/{$s['id']}") ?>'">
                <th>Silo</th>
                <th>Granja</th>
                <th>Stock actual</th>
                <th>Capacidad</th>
                <th>Nivel</th>
                <th>Naves abastecidas</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($silos as $s): ?>
            <?php
                $pct   = (int)($s['pct_stock'] ?? 0);
                $alerta = $s['stock_actual_kg'] <= $s['stock_minimo_kg'];
                $clase  = $alerta ? 'stock-low' : ($pct < 30 ? 'stock-warn' : 'stock-ok');
            ?>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("silos/{$s['id']}") ?>'">
                <td>
                    <strong><?= e($s['nombre']) ?></strong>
                    <?php if ($alerta): ?>
                        <span class="badge" style="background:#fee2e2;color:#dc2626;margin-left:.4rem">Stock bajo</span>
                    <?php endif; ?>
                </td>
                <td><?= e($s['granja_nombre']) ?></td>
                <td><?= number_format($s['stock_actual_kg'], 0) ?> kg</td>
                <td><?= number_format($s['capacidad_kg'], 0) ?> kg</td>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div class="stock-bar-wrap">
                            <div class="stock-bar-fill <?= $clase ?>" style="width:<?= min($pct,100) ?>%"></div>
                        </div>
                        <span style="font-size:.78rem;color:#6b7280"><?= $pct ?>%</span>
                    </div>
                </td>
                <td style="font-size:.82rem;color:#6b7280"><?= e($s['naves_abastecidas'] ?? '—') ?></td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("silos/{$s['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("silos/{$s['id']}/eliminar") ?>" onsubmit="return confirm('¿Eliminar este silo?')">
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
