<?php $pageTitle = 'Cuadras'; ?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

<div class="page-header">
    <h2>Cuadras</h2>
    <a href="<?= base_url('cuadras/crear') ?>" class="btn btn-primary">+ Nueva cuadra</a>
</div>

<?php if ($flash = \App\Core\Session::getFlash('success')): ?>
    <div class="alert-flash alert-success"><?= $flash ?></div>
<?php endif; ?>

<!-- Filtro por nave -->
<div style="margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem">
    <form method="GET" action="<?= base_url('cuadras') ?>" style="display:flex;gap:.5rem;align-items:center">
        <label style="font-size:.875rem;font-weight:500;color:#374151">Filtrar por nave:</label>
        <select name="nave" onchange="this.form.submit()"
                style="padding:.45rem .75rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.875rem;font-family:inherit">
            <option value="">Todas las naves</option>
            <?php foreach ($naves as $n): ?>
                <option value="<?= $n['id'] ?>" <?= $filtroNaveId == $n['id'] ? 'selected' : '' ?>>
                    <?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($filtroNaveId): ?>
            <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary btn-sm">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<div class="list-card">
<?php if (empty($cuadras)): ?>
    <div class="empty-state">No hay cuadras. <a href="<?= base_url('cuadras/crear') ?>">Crea la primera</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("cuadras/{$c['id']}") ?>'">
                <th>Cuadra</th>
                <th>Nave</th>
                <th>Granja</th>
                <th>Dimensiones (m)</th>
                <th>Capacidad</th>
                <th>Ocupación</th>
                <th>Lotes activos</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cuadras as $c): ?>
            <?php
                $pct   = $c['capacidad_maxima'] > 0 ? round(($c['ocupacion_actual'] / $c['capacidad_maxima']) * 100) : 0;
                $clase = $pct >= 90 ? 'stock-low' : ($pct >= 60 ? 'stock-warn' : 'stock-ok');
            ?>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("cuadras/{$c['id']}") ?>'">
                <td>
                    <a href="<?= base_url("cuadras/{$c['id']}") ?>"
                       style="font-weight:600;color:#1d4ed8;text-decoration:none">
                        <?= e($c['nombre']) ?>
                    </a>
                </td>
                <td><?= e($c['nave_nombre']) ?></td>
                <td><?= e($c['granja_nombre']) ?></td>
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
                        <span style="font-size:.78rem;color:#6b7280"><?= $c['ocupacion_actual'] ?> / <?= $c['capacidad_maxima'] ?></span>
                    </div>
                </td>
                <td>
                    <?php if ($c['num_lotes'] > 0): ?>
                        <span class="badge badge-activo"><?= $c['num_lotes'] ?> lote<?= $c['num_lotes'] > 1 ? 's' : '' ?></span>
                    <?php else: ?>
                        <span style="color:#d1d5db;font-size:.82rem">Vacía</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("cuadras/{$c['id']}") ?>" class="btn btn-secondary btn-sm">Ver</a>
                        <a href="<?= base_url("cuadras/{$c['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("cuadras/{$c['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar esta cuadra?')">
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
