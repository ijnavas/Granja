<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">
<style>
    .detail-header { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.07); padding:1.75rem; margin-bottom:1.5rem; display:grid; grid-template-columns:1fr auto; gap:1.5rem; align-items:start; }
    .detail-nombre { font-size:1.3rem; font-weight:700; color:#111827; margin-bottom:.25rem; }
    .detail-sub    { font-size:.85rem; color:#6b7280; margin-bottom:.75rem; }
    .detail-meta   { display:flex; flex-wrap:wrap; gap:1.5rem; }
    .meta-item     { font-size:.82rem; color:#6b7280; }
    .meta-item strong { display:block; font-size:1rem; font-weight:600; color:#111827; }
    .totales-bar   { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.07); padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; gap:2rem; align-items:center; flex-wrap:wrap; }
    .total-item    { font-size:.82rem; color:#6b7280; }
    .total-item strong { display:block; font-size:1.4rem; font-weight:700; color:#111827; }
</style>

<div class="page-header">
    <h2>Detalle nave</h2>
    <a href="<?= base_url('naves') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="detail-header">
    <div>
        <div class="detail-nombre"><?= e($nave['nombre']) ?></div>
        <div class="detail-sub"><?= e($nave['granja_nombre']) ?></div>
        <div class="detail-meta">
            <div class="meta-item">
                <strong><?= number_format($nave['capacidad_maxima']) ?></strong>
                Capacidad máx.
            </div>
            <?php if ($nave['ancho_m'] || $nave['largo_m'] || $nave['alto_m']): ?>
            <div class="meta-item">
                <strong>
                <?php
                    $dims = array_filter([
                        $nave['ancho_m'] ? $nave['ancho_m'].'m' : null,
                        $nave['largo_m'] ? $nave['largo_m'].'m' : null,
                        $nave['alto_m']  ? $nave['alto_m'].'m'  : null,
                    ]);
                    echo implode(' × ', $dims);
                ?>
                </strong>
                Dimensiones
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:.5rem;flex-shrink:0">
        <a href="<?= base_url("naves/{$nave['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
        <?php if (es_director()): ?>
        <form method="POST" action="<?= base_url("naves/{$nave['id']}/eliminar") ?>"
              onsubmit="return confirm('¿Eliminar esta nave?')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Cuadras de esta nave -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
    <h3 style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em">
        Cuadras (<?= count($cuadras) ?>)
    </h3>
    <div style="display:flex;gap:.5rem">
        <a href="<?= base_url('cuadras/masiva?nave=' . $nave['id']) ?>" class="btn btn-secondary btn-sm">+ Crear varias cuadras</a>
        <a href="<?= base_url('cuadras/crear?nave=' . $nave['id']) ?>" class="btn btn-primary btn-sm">+ Nueva cuadra</a>
    </div>
</div>

<div class="list-card">
<?php if (empty($cuadras)): ?>
    <div class="empty-state">Esta nave no tiene cuadras aún.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Cuadra</th>
                <th>Dimensiones</th>
                <th>Capacidad</th>
                <th>Ocupación</th>
                <th>Lotes activos</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalCap = 0; $totalOcup = 0;
            foreach ($cuadras as $c):
                $totalCap  += $c['capacidad_maxima'];
                $totalOcup += $c['ocupacion_actual'];
                $pct   = $c['capacidad_maxima'] > 0 ? round(($c['ocupacion_actual'] / $c['capacidad_maxima']) * 100) : 0;
                $clase = $pct >= 90 ? 'stock-low' : ($pct >= 60 ? 'stock-warn' : 'stock-ok');
            ?>
            <tr>
                <td>
                    <a href="<?= base_url("cuadras/{$c['id']}") ?>"
                       style="font-weight:600;color:#1d4ed8;text-decoration:none">
                        <?= e($c['nombre']) ?>
                    </a>
                </td>
                <td style="font-size:.82rem;color:#6b7280">
                    <?php
                        $dims = array_filter([
                            $c['ancho_m'] ? $c['ancho_m'].'a' : null,
                            $c['alto_m']  ? $c['alto_m'].'h'  : null,
                            $c['largo_m'] ? $c['largo_m'].'l' : null,
                        ]);
                        echo $dims ? implode(' × ', $dims) : '—';
                    ?>
                </td>
                <td><?= number_format($c['capacidad_maxima']) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div class="stock-bar-wrap">
                            <div class="stock-bar-fill <?= $clase ?>" style="width:<?= min($pct,100) ?>%"></div>
                        </div>
                        <span style="font-size:.78rem;color:#6b7280"><?= $c['ocupacion_actual'] ?></span>
                    </div>
                </td>
                <td><?= $c['num_lotes'] > 0 ? '<span class="badge badge-activo">' . $c['num_lotes'] . '</span>' : '<span style="color:#d1d5db">—</span>' ?></td>
                <td>
                    <a href="<?= base_url("cuadras/{$c['id']}") ?>" class="btn btn-secondary btn-sm">Ver</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9fafb;font-weight:600">
                <td colspan="2" style="padding:.75rem 1rem;font-size:.82rem;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Total nave</td>
                <td style="padding:.75rem 1rem"><?= number_format($totalCap) ?></td>
                <td style="padding:.75rem 1rem"><?= number_format($totalOcup) ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
</div>
