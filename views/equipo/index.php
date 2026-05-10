<div class="page-header">
    <div>
        <h2>Equipo</h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem">
            <?= e($org['nombre'] ?? '') ?> · tu rol: <strong><?= e($rolActual) ?></strong>
        </div>
    </div>
</div>

<?php if (!empty($success)): ?><div class="alert-flash alert-success"><?= $success ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert-flash alert-error"><?= e($error) ?></div><?php endif; ?>

<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Miembros
</div>

<div class="list-card" style="margin-bottom:1.5rem">
    <table class="list-table">
        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Desde</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($miembros as $m): ?>
        <tr>
            <td><strong><?= e($m['nombre']) ?></strong>
                <?php if ((int)$m['id'] === $usuarioActual): ?><span style="font-size:.7rem;color:#9ca3af"> (tú)</span><?php endif; ?>
            </td>
            <td style="color:#6b7280;font-size:.85rem"><?= e($m['email']) ?></td>
            <td>
                <?php if ($m['rol'] === 'owner' || (int)$m['id'] === $usuarioActual || !in_array($rolActual, ['owner','admin'], true)): ?>
                    <span class="badge"><?= e($m['rol']) ?></span>
                <?php else: ?>
                    <form method="POST" action="<?= base_url("equipo/{$m['id']}/rol") ?>" style="display:inline-flex;gap:.3rem">
                        <?= csrf_field() ?>
                        <select name="rol" onchange="this.form.submit()" style="font-size:.78rem;padding:.15rem .35rem;border:1px solid #d1d5db;border-radius:.25rem">
                            <option value="admin"    <?= $m['rol']==='admin'?'selected':'' ?>>admin</option>
                            <option value="operario" <?= $m['rol']==='operario'?'selected':'' ?>>operario</option>
                            <option value="lector"   <?= $m['rol']==='lector'?'selected':'' ?>>lector</option>
                        </select>
                    </form>
                <?php endif; ?>
            </td>
            <td style="color:#9ca3af;font-size:.78rem"><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
            <td style="white-space:nowrap">
                <?php if (in_array($m['rol'], ['operario','lector'], true) && in_array($rolActual, ['owner','admin'], true)): ?>
                    <a href="<?= base_url("equipo/{$m['id']}/granjas") ?>" class="btn btn-secondary btn-sm" style="margin-right:.3rem">Granjas</a>
                <?php endif; ?>
                <?php if ($m['rol'] !== 'owner' && (int)$m['id'] !== $usuarioActual && in_array($rolActual, ['owner','admin'], true)): ?>
                <form method="POST" action="<?= base_url("equipo/{$m['id']}/quitar") ?>" onsubmit="return confirm('¿Quitar a <?= e($m['nombre']) ?> de la organización?')" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Quitar</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (in_array($rolActual, ['owner','admin'], true)): ?>
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Invitar nuevo miembro
</div>

<div class="form-card" style="max-width:680px;margin-bottom:1.5rem">
    <form method="POST" action="<?= base_url('equipo/invitar') ?>">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-3">
            <div class="form-group" style="grid-column:span 2">
                <label>Email *</label>
                <input type="email" name="email" required placeholder="usuario@ejemplo.com">
            </div>
            <div class="form-group">
                <label>Rol *</label>
                <?php $superUser = (auth_rol() === 'admin'); ?>
                <select name="rol" required>
                    <option value="operario" selected>Operario</option>
                    <option value="lector">Lector (solo lectura)</option>
                    <?php if ($superUser): ?>
                    <option value="admin">Admin</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Crear invitación</button>
        </div>
        <span class="form-hint">Se generará un enlace de 7 días para compartir con esa persona. Si no tiene cuenta, deberá registrarse antes de aceptar.</span>
    </form>
</div>

<?php if (!empty($invsPendientes)): ?>
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Invitaciones pendientes
</div>
<div class="list-card">
    <table class="list-table">
        <thead><tr><th>Email</th><th>Rol</th><th>Caduca</th><th>Enlace</th></tr></thead>
        <tbody>
        <?php foreach ($invsPendientes as $i): ?>
        <tr>
            <td><?= e($i['email']) ?></td>
            <td><span class="badge"><?= e($i['rol']) ?></span></td>
            <td style="font-size:.8rem;color:#9ca3af"><?= date('d/m/Y', strtotime($i['expira_at'])) ?></td>
            <td>
                <code style="font-size:.7rem;background:#f3f4f6;padding:.2rem .4rem;border-radius:.25rem;word-break:break-all">
                    <?= e(base_url('aceptar-invitacion/' . $i['token'])) ?>
                </code>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.badge { background:#e0e7ff; color:#3730a3; font-size:.72rem; font-weight:600; padding:.15rem .5rem; border-radius:99px; text-transform:capitalize }
</style>
