<div class="page-header">
    <div>
        <h2>Clientes</h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem">
            Organizaciones de la plataforma. Solo visible para super-usuario.
        </div>
    </div>
    <a href="<?= base_url('clientes/nuevo') ?>" class="btn btn-primary">+ Nuevo cliente</a>
</div>

<?php if (!empty($success)): ?><div class="alert-flash alert-success"><?= $success ?></div><?php endif; ?>
<?php if (!empty($error)):   ?><div class="alert-flash alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="list-card">
    <table class="list-table">
        <thead>
            <tr>
                <th>Organización</th>
                <th>Owner</th>
                <th>Email owner</th>
                <th style="text-align:center">Miembros</th>
                <th style="text-align:center">Granjas</th>
                <th>Plan</th>
                <th>Creado</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($clientes)): ?>
            <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:1.5rem">Todavía no hay clientes.</td></tr>
        <?php else: ?>
            <?php foreach ($clientes as $c): ?>
            <tr>
                <td><strong><?= e($c['nombre']) ?></strong></td>
                <td><?= e($c['owner_nombre'] ?? '—') ?></td>
                <td style="color:#6b7280;font-size:.85rem"><?= e($c['owner_email'] ?? '—') ?></td>
                <td style="text-align:center"><?= (int)$c['num_miembros'] ?></td>
                <td style="text-align:center"><?= (int)$c['num_granjas'] ?></td>
                <td><span class="badge"><?= e($c['plan']) ?></span></td>
                <td style="color:#9ca3af;font-size:.78rem"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.badge { background:#e0e7ff; color:#3730a3; font-size:.72rem; font-weight:600; padding:.15rem .5rem; border-radius:99px; text-transform:capitalize }
</style>
