<?php
/** @var string|null $error       */
/** @var string|null $success     */
/** @var array       $old         */
/** @var string      $emailLocked */
/** @var string      $orgNombre   */
$old         = $old ?? [];
$emailLocked = $emailLocked ?? '';
$orgNombre   = $orgNombre ?? '';
$pageTitle   = $emailLocked !== '' ? 'Unirse a ' . $orgNombre : 'Crear cuenta';
?>

<?php if ($emailLocked !== ''): ?>
    <div class="alert alert-success" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0">
        <strong>Te unes a <?= e($orgNombre) ?></strong><br>
        <span style="font-size:.82rem">Crea tu cuenta para aceptar la invitación de <strong><?= e($emailLocked) ?></strong>.</span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= nl2br(e($error)) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<form method="POST" action="<?= base_url('register') ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($emailLocked !== ''): ?>
        <input type="hidden" name="invitation_token" value="<?= e($_GET['token'] ?? '') ?>">
    <?php endif; ?>

    <div class="form-group">
        <label for="nombre">Nombre completo</label>
        <input
            type="text"
            id="nombre"
            name="nombre"
            required
            autocomplete="name"
            placeholder="Juan García"
            value="<?= e($old['nombre'] ?? '') ?>"
        >
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            required
            autocomplete="email"
            placeholder="tucorreo@ejemplo.com"
            value="<?= e($emailLocked !== '' ? $emailLocked : ($old['email'] ?? '')) ?>"
            <?= $emailLocked !== '' ? 'readonly style="background:#f3f4f6;color:#6b7280;cursor:not-allowed"' : '' ?>
        >
        <?php if ($emailLocked !== ''): ?>
            <p class="password-hint">Email bloqueado por la invitación. <a href="<?= base_url('register') ?>" style="color:#1a56db">Cancelar invitación</a></p>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="password">Contraseña</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            autocomplete="new-password"
            placeholder="••••••••"
        >
        <p class="password-hint">Mínimo 8 caracteres, una mayúscula y un número.</p>
    </div>

    <div class="form-group">
        <label for="password_confirm">Confirmar contraseña</label>
        <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            required
            autocomplete="new-password"
            placeholder="••••••••"
        >
    </div>

    <button type="submit" class="btn-primary">Crear cuenta</button>
</form>

<div class="auth-footer">
    ¿Ya tienes cuenta? <a href="<?= base_url('login') ?>">Inicia sesión</a>
</div>
