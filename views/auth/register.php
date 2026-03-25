<?php
/** @var string|null $error   */
/** @var string|null $success */
/** @var array       $old     */
$old       = $old ?? [];
$pageTitle = 'Crear cuenta';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<form method="POST" action="<?= base_url('register') ?>" novalidate>
    <?= csrf_field() ?>

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
            value="<?= e($old['email'] ?? '') ?>"
        >
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
