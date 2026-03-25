<?php $pageTitle = 'Granjas'; ?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

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
                <th>Municipio</th>
                <th>Tipo producción</th>
                <th>Naves</th>
                <th>Silos</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($granjas as $g): ?>
            <tr>
                <td><strong><?= e($g['nombre']) ?></strong></td>
                <td><?= e($g['municipio'] ?? '—') ?><?= $g['provincia'] ? ', ' . e($g['provincia']) : '' ?></td>
                <td><?= e($g['tipo_produccion'] ?? '—') ?></td>
                <td><?= $g['num_naves'] ?></td>
                <td><?= $g['num_silos'] ?></td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("granjas/{$g['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("granjas/{$g['id']}/eliminar") ?>" onsubmit="return confirm('¿Eliminar esta granja?')">
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
