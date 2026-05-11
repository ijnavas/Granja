<div class="page-header">
    <div>
        <h2>Nuevo cliente</h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem">
            Crea una organización nueva y manda invitación al admin que la va a gestionar.
        </div>
    </div>
    <a href="<?= base_url('clientes') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?><div class="alert-flash alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:640px">
    <p style="margin-top:0;color:#374151;font-size:.92rem">
        Al crear el cliente, se generará una organización vacía y se enviará una invitación de
        <strong>owner</strong> al email indicado. La persona invitada podrá registrarse (o iniciar
        sesión si ya tenía cuenta) y quedará como propietaria de la nueva organización con todos los
        permisos sobre sus propias granjas.
    </p>

    <form method="POST" action="<?= base_url('clientes/nuevo') ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Nombre de la organización *</label>
            <input type="text" name="org_nombre" required maxlength="120"
                   placeholder="Ej: Ganadería La Vega"
                   value="<?= e($old['org_nombre'] ?? '') ?>">
            <span class="form-hint">Aparecerá en el panel del cliente como nombre de su organización.</span>
        </div>

        <div class="form-group">
            <label>Email del admin del cliente *</label>
            <input type="email" name="email" required maxlength="190"
                   placeholder="admin@cliente.com"
                   value="<?= e($old['email'] ?? '') ?>">
            <span class="form-hint">Recibirá un email con un enlace para activar la cuenta (válido 7 días).</span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Crear cliente y enviar invitación</button>
            <a href="<?= base_url('clientes') ?>" class="btn btn-secondary" style="margin-left:.5rem">Cancelar</a>
        </div>
    </form>
</div>
