<?php $pageTitle = 'Naves'; ?>

<div class="page-header">
    <h2>Naves</h2>
    <a href="<?= base_url('naves/crear') ?>" class="btn btn-primary">+ Nueva nave</a>
</div>

<?php if ($flash = \App\Core\Session::getFlash('success')): ?>
    <div class="alert-flash alert-success"><?= $flash ?></div>
<?php endif; ?>

<!-- Totales -->
<?php if (!empty($totales)): ?>
<div style="display:flex;gap:1.5rem;margin-bottom:1.25rem;background:#fff;border-radius:10px;padding:1rem 1.5rem;box-shadow:0 1px 6px rgba(0,0,0,.07)">
    <div style="font-size:.82rem;color:#6b7280">
        <strong style="display:block;font-size:1.3rem;font-weight:700;color:#111827"><?= number_format($totales['capacidad_total']) ?></strong>
        Capacidad total
    </div>
    <div style="font-size:.82rem;color:#6b7280">
        <strong style="display:block;font-size:1.3rem;font-weight:700;color:#111827"><?= number_format($totales['ocupacion_total']) ?></strong>
        Animales actuales
    </div>
    <?php
        $pctTotal = $totales['capacidad_total'] > 0
            ? round(($totales['ocupacion_total'] / $totales['capacidad_total']) * 100) : 0;
    ?>
    <div style="font-size:.82rem;color:#6b7280">
        <strong style="display:block;font-size:1.3rem;font-weight:700;color:#111827"><?= $pctTotal ?>%</strong>
        Ocupación global
    </div>
</div>
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
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("naves/{$n['id']}") ?>'">
                <td><strong><?= e($n['nombre']) ?></strong></td>
                <td><?= e($n['granja_nombre']) ?></td>
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
                <td onclick="event.stopPropagation()">
                    <div class="actions">
                        <a href="<?= base_url("naves/{$n['id']}") ?>" class="btn btn-secondary btn-sm">Ver</a>
                        <a href="<?= base_url("naves/{$n['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:600">
                <td colspan="3" style="padding:.75rem 1rem;font-size:.82rem;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Total</td>
                <td style="padding:.75rem 1rem"><?= number_format($totales['capacidad_total']) ?></td>
                <td style="padding:.75rem 1rem;color:#6b7280;font-size:.875rem"><?= number_format($totales['ocupacion_total']) ?> animales</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
</div>
