<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:900px">

<!-- Información personal -->
<div class="form-card">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:1rem">
        Información personal
    </div>
    <form method="POST" action="<?= base_url('perfil/info') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Nombre *</label>
            <input type="text" name="nombre" required value="<?= e($user['nombre'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Apellidos</label>
            <input type="text" name="apellidos" value="<?= e($user['apellidos'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" required value="<?= e($user['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Móvil</label>
            <input type="tel" name="movil" value="<?= e($user['movil'] ?? '') ?>" placeholder="+34 600 000 000">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>

<!-- Cambiar contraseña -->
<div class="form-card">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:1rem">
        Cambiar contraseña
    </div>
    <form method="POST" action="<?= base_url('perfil/password') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Contraseña actual *</label>
            <input type="password" name="password_actual" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label>Nueva contraseña *</label>
            <input type="password" name="password_nueva" required autocomplete="new-password"
                   minlength="8" oninput="checkMatch()">
            <span class="form-hint">Mínimo 8 caracteres</span>
        </div>
        <div class="form-group">
            <label>Confirmar nueva contraseña *</label>
            <input type="password" name="password_confirmar" required autocomplete="new-password"
                   id="passConfirmar" oninput="checkMatch()">
            <span id="matchHint" class="form-hint" style="display:none;color:#dc2626">Las contraseñas no coinciden</span>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnPass">Cambiar contraseña</button>
        </div>
    </form>
</div>

</div>

<script>
function checkMatch() {
    const nueva     = document.querySelector('input[name="password_nueva"]').value;
    const confirmar = document.getElementById('passConfirmar').value;
    const hint      = document.getElementById('matchHint');
    const btn       = document.getElementById('btnPass');
    const mismatch  = confirmar && nueva !== confirmar;
    hint.style.display = mismatch ? 'block' : 'none';
    btn.disabled = mismatch;
}
</script>
