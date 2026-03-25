<?php $pageTitle = 'Granjas'; ?>

<div class="page-header">
    <h2>Granjas</h2>
    <a href="<?= base_url('granjas/crear') ?>" class="btn btn-primary">+ Nueva granja</a>
</div>

<?php if ($flash = \App\Core\Session::getFlash('success')): ?>
    <div class="alert-flash alert-success"><?= $flash ?></div>
<?php endif; ?>

<div class="list-card">
<?php if (empty($granjas)): ?>
    <div class="empty-state">No tienes granjas. <a href="<?= base_url('granjas/crear') ?>">Crea la primera</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Código REGA</th>
                <th>Especie</th>
                <th>Municipio</th>
                <th>Tipo</th>
                <th>Capacidad</th>
                <th>Naves</th>
                <th>Ubicación</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($granjas as $g): ?>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("granjas/{$g['id']}") ?>'">
                <td><strong><?= e($g['nombre']) ?></strong></td>
                <td><span style="font-family:monospace;font-size:.82rem"><?= e($g['codigo_rega'] ?? '—') ?></span></td>
                <td><?= $g['especie'] ? '<span class="badge badge-' . e($g['especie']) . '">' . ucfirst(e($g['especie'])) . '</span>' : '<span style="color:#d1d5db">—</span>' ?></td>
                <td><?= e($g['municipio'] ?? '—') ?><?= $g['provincia'] ? ', ' . e($g['provincia']) : '' ?></td>
                <td><?= e($g['tipo_produccion'] ?? '—') ?></td>
                <td><?= $g['capacidad_max'] ? number_format($g['capacidad_max']) : '—' ?></td>
                <td><?= $g['num_naves'] ?></td>
                <td>
                    <?php if ($g['latitud'] && $g['longitud']): ?>
                        <span onclick="event.stopPropagation()">
                        <a href="https://www.google.com/maps?q=<?= $g['latitud'] ?>,<?= $g['longitud'] ?>"
                           target="_blank"
                           style="font-size:.78rem;color:#1d4ed8;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            Ver mapa
                        </a>
                        </span>
                    <?php else: ?>
                        <span style="color:#d1d5db;font-size:.82rem">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
