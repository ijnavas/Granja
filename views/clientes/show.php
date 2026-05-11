<div class="page-header">
    <div>
        <h2><?= e($org['nombre']) ?></h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem">
            Cliente · plan <strong><?= e($org['plan']) ?></strong> · alta <?= date('d/m/Y', strtotime($org['created_at'])) ?>
        </div>
    </div>
    <a href="<?= base_url('clientes') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($success)): ?><div class="alert-flash alert-success"><?= $success ?></div><?php endif; ?>
<?php if (!empty($error)):   ?><div class="alert-flash alert-error"><?= e($error) ?></div><?php endif; ?>

<!-- ── Editar datos del cliente ────────────────────────────────── -->
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Datos del cliente
</div>
<div class="form-card" style="max-width:680px;margin-bottom:1.5rem">
    <form method="POST" action="<?= base_url('clientes/' . (int)$org['id'] . '/editar') ?>">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-2">
            <div class="form-group" style="grid-column:span 2">
                <label>Nombre de la organización *</label>
                <input type="text" name="nombre" required maxlength="120" value="<?= e($org['nombre']) ?>">
            </div>
            <div class="form-group">
                <label>Plan</label>
                <select name="plan">
                    <option value="free"       <?= $org['plan']==='free'?'selected':'' ?>>Free</option>
                    <option value="pro"        <?= $org['plan']==='pro'?'selected':'' ?>>Pro</option>
                    <option value="enterprise" <?= $org['plan']==='enterprise'?'selected':'' ?>>Enterprise</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
    <form method="POST" action="<?= base_url('clientes/' . (int)$org['id'] . '/borrar') ?>"
          onsubmit="return confirm('Vas a desactivar el cliente \&quot;<?= e(addslashes($org['nombre'])) ?>\&quot;. Los miembros perderán acceso (los datos NO se borran, solo se ocultan). ¿Continuar?')"
          style="margin-top:.75rem;padding-top:.75rem;border-top:1px dashed #e5e7eb">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger">Desactivar cliente</button>
        <span style="margin-left:.5rem;font-size:.78rem;color:#9ca3af">Soft-delete: los datos no se borran, solo se ocultan.</span>
    </form>
</div>

<!-- ── Miembros ────────────────────────────────────────────────── -->
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Miembros (<?= count($miembros) ?>)
</div>
<div class="list-card" style="margin-bottom:1.5rem">
    <table class="list-table">
        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Desde</th></tr></thead>
        <tbody>
        <?php if (empty($miembros)): ?>
            <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:1rem">Sin miembros todavía.</td></tr>
        <?php else: ?>
            <?php foreach ($miembros as $m): ?>
            <tr>
                <td><strong><?= e($m['nombre']) ?></strong></td>
                <td style="color:#6b7280;font-size:.85rem"><?= e($m['email']) ?></td>
                <td><span class="badge"><?= e($m['rol']) ?></span></td>
                <td style="color:#9ca3af;font-size:.78rem"><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── Granjas ─────────────────────────────────────────────────── -->
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Granjas (<?= count($granjas) ?>)
</div>
<div class="list-card" style="margin-bottom:1.5rem">
    <table class="list-table">
        <thead><tr><th>Nombre</th><th>Código REGA</th><th>Especie</th><th>Producción</th><th style="text-align:center">Naves</th><th style="text-align:right">Capacidad</th></tr></thead>
        <tbody>
        <?php if (empty($granjas)): ?>
            <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:1rem">El cliente aún no ha creado granjas.</td></tr>
        <?php else: ?>
            <?php foreach ($granjas as $g): ?>
            <tr>
                <td><strong><?= e($g['nombre']) ?></strong></td>
                <td style="color:#6b7280;font-size:.85rem"><?= e($g['codigo_rega'] ?? '—') ?></td>
                <td><?= e($g['especie'] ?? '—') ?></td>
                <td><?= e($g['tipo_produccion'] ?? '—') ?></td>
                <td style="text-align:center"><?= (int)$g['num_naves'] ?></td>
                <td style="text-align:right;color:#6b7280;font-size:.85rem"><?= $g['capacidad_max'] ? number_format((int)$g['capacidad_max'], 0, ',', '.') : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── Invitaciones pendientes ─────────────────────────────────── -->
<?php if (!empty($invsPendientes)): ?>
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Invitaciones pendientes
</div>
<div class="list-card" style="margin-bottom:1.5rem">
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

<style>
.badge { background:#e0e7ff; color:#3730a3; font-size:.72rem; font-weight:600; padding:.15rem .5rem; border-radius:99px; text-transform:capitalize }
</style>
