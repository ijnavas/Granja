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
        <div class="alert-flash" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;margin:1rem 0">
            Debes iniciar sesión con la cuenta <strong><?= e($inv['email']) ?></strong> antes de aceptar.
            <br>¿No tienes cuenta? <a href="<?= base_url('register') ?>">Regístrate primero</a>.
        </div>
        <a href="<?= base_url('login') ?>" class="btn btn-primary">Iniciar sesión</a>
    <?php else: ?>
        <form method="POST" action="<?= base_url('aceptar-invitacion/' . $token) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Aceptar invitación</button>
            <a href="<?= base_url('dashboard') ?>" class="btn btn-secondary" style="margin-left:.5rem">Cancelar</a>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>
