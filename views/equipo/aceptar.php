<div class="page-header">
    <h2>Aceptar invitación</h2>
</div>

<?php if (!empty($error)): ?><div class="alert-flash alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if (!$inv): ?>
    <div class="alert-flash alert-error">
        Esta invitación no existe, ya fue aceptada o ha caducado.
    </div>
<?php else: ?>
<div class="form-card" style="max-width:600px">
    <p>Has sido invitado/a a unirte a la organización <strong><?= e($inv['org_nombre']) ?></strong> con el rol <strong><?= e($inv['rol']) ?></strong>.</p>
    <p style="font-size:.85rem;color:#6b7280">La invitación es para <strong><?= e($inv['email']) ?></strong> y caduca el <?= date('d/m/Y', strtotime($inv['expira_at'])) ?>.</p>

    <?php if (!$logueado): ?>
        <?php if ($existeCuenta): ?>
            <div class="alert-flash" style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;margin:1rem 0">
                Ya existe una cuenta con el email <strong><?= e($inv['email']) ?></strong>.
                Inicia sesión para aceptar la invitación.
            </div>
            <a href="<?= base_url('login') ?>" class="btn btn-primary">Iniciar sesión</a>
        <?php else: ?>
            <div class="alert-flash" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;margin:1rem 0">
                Crea tu cuenta para unirte directamente a <strong><?= e($inv['org_nombre']) ?></strong>.
                Tu email <strong><?= e($inv['email']) ?></strong> ya está bloqueado para esta invitación.
            </div>
            <a href="<?= base_url('register?token=' . urlencode($token)) ?>" class="btn btn-primary">Crear cuenta y unirme</a>
            <span style="margin-left:.75rem;font-size:.85rem;color:#6b7280">¿Ya tienes cuenta? <a href="<?= base_url('login') ?>">Inicia sesión</a></span>
        <?php endif; ?>
    <?php else: ?>
        <form method="POST" action="<?= base_url('aceptar-invitacion/' . $token) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Aceptar invitación</button>
            <a href="<?= base_url('dashboard') ?>" class="btn btn-secondary" style="margin-left:.5rem">Cancelar</a>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>
