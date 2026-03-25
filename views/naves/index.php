<?php $pageTitle = 'Naves'; ?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

<div class="page-header">
    <h2>Naves</h2>
    <a href="<?= base_url('naves/crear') ?>" class="btn btn-primary">+ Nueva nave</a>
</div>

<?php if ($flash = \App\Core\Session::getFlash('success')): ?>
    <div class="alert-flash alert-success"><?= $flash ?></div>
<?php endif; ?>

<div class="list-card">
<?php if (empty($naves)): ?>
    <div class="empty-state">No hay naves. <a href="<?= base_url('naves/crear') ?>">Crea la primera</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Nave</th>
                <th>Granja</th>
                <th>Especie</th>
                <th>Dimensiones (m)</th>
                <th>Capacidad</th>
                <th>Ocupación</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($naves as $n): ?>
            <?php
                $pct   = $n['capacidad_maxima'] > 0 ? round(($n['ocupacion_actual'] / $n['capacidad_maxima']) * 100) : 0;
                $clase = $pct >= 90 ? 'stock-low' : ($pct >= 60 ? 'stock-warn' : 'stock-ok');
            ?>
            <tr>
                <td><strong><?= e($n['nombre']) ?></strong></td>
                <td><?= e($n['granja_nombre']) ?></td>
                <td><span class="badge badge-<?= e($n['especie']) ?>"><?= e($n['especie']) ?></span></td>
                <td style="font-size:.82rem;color:#6b7280">
                    <?php
                        $dims = array_filter([
                            $n['ancho_m'] ? $n['ancho_m'].'a' : null,
                            $n['alto_m']  ? $n['alto_m'].'h'  : null,
                            $n['largo_m'] ? $n['largo_m'].'l' : null,
                        ]);
                        echo $dims ? implode(' × ', $dims) : '—';
                    ?>
                </td>
                <td><?= number_format($n['capacidad_maxima']) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div class="stock-bar-wrap">
                            <div class="stock-bar-fill <?= $clase ?>" style="width:<?= min($pct,100) ?>%"></div>
                        </div>
                        <span style="font-size:.78rem;color:#6b7280"><?= $n['ocupacion_actual'] ?> / <?= $n['capacidad_maxima'] ?></span>
                    </div>
                </td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("naves/{$n['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("naves/{$n['id']}/eliminar") ?>" onsubmit="return confirm('¿Eliminar esta nave?')">
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
