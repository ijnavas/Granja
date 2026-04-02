<?php
/** @var string      $token */
/** @var string|null $error */
$pageTitle = 'Nueva contraseña';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<p style="color:#555;font-size:.95rem;margin-bottom:1.25rem;">
    Elige una nueva contraseña para tu cuenta.
</p>

<form method="POST" action="<?= base_url('reset-password/' . e($token)) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="password">Nueva contraseña</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            autocomplete="new-password"
            placeholder="••••••••"
        >
        <small style="color:#777;">Mínimo 8 caracteres, una mayúscula y un número.</small>
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

    <button type="submit" class="btn-primary">Guardar nueva contraseña</button>
</form>

<div class="auth-footer">
    <a href="<?= base_url('login') ?>">← Volver al inicio de sesión</a>
</div>
