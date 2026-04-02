<?php
/** @var string|null $error   */
/** @var string|null $success */
$pageTitle = 'Recuperar contraseña';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!$success): ?>
<p style="color:#555;font-size:.95rem;margin-bottom:1.25rem;">
    Introduce tu email y te enviaremos un enlace para restablecer tu contraseña.
</p>

<form method="POST" action="<?= base_url('forgot-password') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            required
            autocomplete="email"
            placeholder="tucorreo@ejemplo.com"
        >
    </div>

    <button type="submit" class="btn-primary">Enviar enlace</button>
</form>
<?php endif; ?>

<div class="auth-footer">
    <a href="<?= base_url('login') ?>">← Volver al inicio de sesión</a>
</div>
