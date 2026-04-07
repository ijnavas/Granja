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

<!-- Credenciales Recevet -->
<div class="form-card" style="margin-top:1.5rem;max-width:900px">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Credenciales Recevet
    </div>
    <form method="POST" action="<?= base_url('perfil/recevet') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Usuario Recevet</label>
            <input type="text" name="recevet_usuario"
                   value="<?= e($user['recevet_usuario'] ?? '') ?>"
                   placeholder="Tu usuario de recevet.es"
                   autocomplete="username" style="max-width:320px">
        </div>

        <!-- Cookie de sesión -->
        <div class="form-group" style="margin-top:.75rem">
            <label>Cookie de sesión <span style="font-weight:400;color:#dc2626">*requerida para sincronizar</span></label>
            <textarea name="recevet_session_cookie" rows="3"
                      placeholder="PHPSESSID=abc123xyz; otra_cookie=valor ..."
                      style="font-family:monospace;font-size:.8rem;width:100%;resize:vertical"><?= e($user['recevet_session_cookie'] ?? '') ?></textarea>
            <?php if (!empty($user['recevet_session_cookie'])): ?>
                <span class="form-hint" style="color:#16a34a">✓ Cookie guardada</span>
            <?php endif; ?>
        </div>

        <!-- Instrucciones paso a paso -->
        <details style="margin:.5rem 0 1rem;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
            <summary style="cursor:pointer;font-weight:600;font-size:.85rem;color:#374151">
                ¿Cómo obtener la cookie? — instrucciones paso a paso
            </summary>
            <ol style="margin:.75rem 0 0 1.2rem;padding:0;font-size:.83rem;line-height:1.8;color:#374151">
                <li>Abre <a href="https://www.recevet.es" target="_blank" style="color:#2563eb">www.recevet.es</a> e inicia sesión con tu usuario y contraseña.</li>
                <li>Una vez dentro, pulsa <kbd style="background:#f3f4f6;border:1px solid #d1d5db;padding:1px 5px;border-radius:3px;font-size:.8rem">F12</kbd> para abrir las herramientas de desarrollador.</li>
                <li>Ve a la pestaña <strong>Red / Network</strong>.</li>
                <li>Recarga la página (<kbd style="background:#f3f4f6;border:1px solid #d1d5db;padding:1px 5px;border-radius:3px;font-size:.8rem">F5</kbd>).</li>
                <li>Haz clic en cualquier petición a <code>recevet.es</code> de la lista.</li>
                <li>En el panel derecho, busca <strong>Cabeceras de solicitud / Request Headers</strong>.</li>
                <li>Copia el valor completo del campo <strong>Cookie:</strong> y pégalo aquí.</li>
            </ol>
            <p style="margin:.6rem 0 0;font-size:.8rem;color:#6b7280;background:#fffbeb;border:1px solid #fde68a;border-radius:.375rem;padding:.5rem .75rem">
                La cookie caduca cuando cierras sesión o después de varios días de inactividad.
                Cuando la sincronización falle por sesión caducada, repite este proceso.
            </p>
        </details>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar configuración Recevet</button>
            <a href="<?= base_url('recevet') ?>" class="btn btn-secondary">Ir a Recevet</a>
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
