<?php
/** @var array $u */
$pageTitle = 'Sin organización';
?>

<div class="auth-card" style="max-width:520px;text-align:center">
    <div style="width:64px;height:64px;background:#fef3c7;border-radius:50%;margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#92400e" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>

    <h1 style="font-size:1.4rem;font-weight:700;color:#111827;margin:0 0 .5rem">
        Hola <?= e($u['nombre'] ?? '') ?>
    </h1>

    <p style="font-size:.95rem;color:#374151;line-height:1.6;margin:0 0 1.25rem">
        Tu cuenta está creada pero todavía no perteneces a ninguna organización.
        Para empezar a usar BALTAE, necesitas que un administrador te envíe una
        invitación al email <strong><?= e($u['email'] ?? '') ?></strong>.
    </p>

    <div style="background:#f3f4f6;border-radius:8px;padding:1rem 1.25rem;font-size:.85rem;color:#4b5563;line-height:1.6;text-align:left;margin-bottom:1.5rem">
        <strong>¿Qué hacer?</strong>
        <ul style="margin:.5rem 0 0;padding-left:1.2rem">
            <li>Pídele al responsable de tu organización que te invite desde la sección <em>Equipo</em>.</li>
            <li>Recibirás un email con un enlace para aceptar la invitación.</li>
            <li>Una vez aceptada, tendrás acceso al panel.</li>
        </ul>
    </div>

    <form method="POST" action="<?= base_url('logout') ?>" style="margin:0">
        <?= csrf_field() ?>
        <button type="submit" class="btn-primary" style="background:#6b7280">Cerrar sesión</button>
    </form>
</div>
