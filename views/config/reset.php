<?php
$seccionConfig = 'reset';
$pageTitle     = 'Configuración — Resetear datos';
include __DIR__ . '/_submenu.php';
?>

<div style="max-width:600px">

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:10px;padding:1.5rem;margin-bottom:1.5rem">
        <div style="font-size:1.1rem;font-weight:700;color:#9a3412;margin-bottom:.75rem">⚠ Atención — Esta acción es irreversible</div>
        <p style="color:#7c2d12;margin:0 0 .75rem">Se eliminarán permanentemente todos los datos operativos:</p>
        <ul style="color:#9a3412;margin:0 0 .75rem;padding-left:1.25rem;line-height:1.8">
            <li>Todos los <strong>lotes</strong></li>
            <li>Todas las <strong>asignaciones de animales a cuadras</strong></li>
            <li>Todos los <strong>movimientos</strong> y su historial</li>
            <li>Todos los <strong>pesajes</strong></li>
        </ul>
        <p style="color:#7c2d12;margin:0"><strong>Se conservarán:</strong> granjas, naves, cuadras, silos, razas, tablas de crecimiento, usuarios y configuración.</p>
    </div>

    <div class="form-card">
        <form method="POST" action="<?= base_url('configuracion/reset') ?>" onsubmit="return validarConfirmacion()">
            <?= csrf_field() ?>

            <div class="form-group">
                <label style="font-weight:600">Para confirmar, escribe <code style="background:#fee2e2;padding:.1rem .4rem;border-radius:4px;color:#dc2626">RESETEAR</code> en el campo:</label>
                <input type="text" id="confirmacion" name="confirmacion"
                       autocomplete="off"
                       placeholder="Escribe RESETEAR para confirmar"
                       style="margin-top:.5rem">
            </div>

            <button type="submit" class="btn btn-danger" style="background:#dc2626;color:#fff;border-color:#dc2626">
                Resetear todos los datos
            </button>
            <a href="<?= base_url('configuracion/razas') ?>" class="btn btn-secondary" style="margin-left:.5rem">Cancelar</a>
        </form>
    </div>
</div>

<script>
function validarConfirmacion() {
    const val = document.getElementById('confirmacion').value.trim();
    if (val !== 'RESETEAR') {
        alert('Debes escribir exactamente "RESETEAR" para confirmar.');
        return false;
    }
    return confirm('¿Estás completamente seguro? Esta acción no se puede deshacer.');
}
</script>
