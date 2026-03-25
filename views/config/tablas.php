<?php $seccionConfig = 'tablas'; require __DIR__ . '/_submenu.php'; ?>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
    <span style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em">
        Tablas de crecimiento
    </span>
    <a href="<?= base_url('configuracion/tablas/crear') ?>" class="btn btn-primary">+ Nueva tabla</a>
</div>

<div class="list-card">
<?php if (empty($tablas)): ?>
    <div class="empty-state">No hay tablas. <a href="<?= base_url('configuracion/tablas/crear') ?>">Crea la primera</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Semanas</th>
                <th>Razas asignadas</th>
                <th>Descripción</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tablas as $t): ?>
            <tr>
                <td><strong><?= e($t['nombre']) ?></strong></td>
                <td><?= $t['num_semanas'] ?></td>
                <td style="font-size:.82rem;color:#6b7280"><?= e($t['razas'] ?? '—') ?></td>
                <td style="font-size:.82rem;color:#6b7280"><?= e($t['descripcion'] ?? '—') ?></td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("configuracion/tablas/{$t['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("configuracion/tablas/{$t['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar esta tabla?')">
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