<?php
/** @var string|null $error   */
/** @var string|null $success */
$pageTitle = 'Iniciar sesión';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<form method="POST" action="<?= base_url('login') ?>" novalidate>
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

    <div class="form-group">
        <label for="password">Contraseña</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            autocomplete="current-password"
            placeholder="••••••••"
        >
    </div>

    <button type="submit" class="btn-primary">Entrar</button>
</form>

<div class="auth-footer" style="text-align:center;margin-top:.75rem;">
    <a href="<?= base_url('forgot-password') ?>">¿Olvidaste tu contraseña?</a>
</div>

<div class="auth-footer">
    ¿No tienes cuenta? <a href="<?= base_url('register') ?>">Regístrate</a>
</div>
