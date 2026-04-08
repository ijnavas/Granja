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
        <div class="form-group">
            <label>Email para pedidos de pienso</label>
            <input type="email" name="email_pedidos" value="<?= e($user['email_pedidos'] ?? '') ?>" placeholder="proveedor@ejemplo.com">
            <span class="form-hint">Se usará como destinatario al enviar pedidos desde los silos.</span>
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

<!-- Recevet -->
<div class="form-card" style="margin-top:1.5rem;max-width:900px">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Recevet — Libro de Tratamientos
    </div>
    <?php if (!empty($user['recevet_session_set'])): ?>
        <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;margin-bottom:.75rem">
            <span style="width:9px;height:9px;border-radius:50%;background:#16a34a;flex-shrink:0"></span>
            Sesión activa — usuario: <strong><?= e($user['recevet_usuario'] ?? '') ?></strong>
        </div>
    <?php else: ?>
        <div style="font-size:.85rem;color:#6b7280;margin-bottom:.75rem">
            Sin sesión activa. Inicia sesión desde la sección Recevet.
        </div>
    <?php endif; ?>
    <a href="<?= base_url('recevet') ?>" class="btn btn-secondary btn-sm">Ir a Recevet</a>
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
