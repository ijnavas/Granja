<div class="page-header">
    <div>
        <h2>Granjas visibles para <?= e($miembro['nombre']) ?></h2>
        <div style="font-size:.875rem;color:#6b7280;margin-top:.15rem">
            <?= e($miembro['email']) ?> · rol <strong><?= e($rolDeM) ?></strong>
        </div>
    </div>
    <a href="<?= base_url('equipo') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($success)): ?><div class="alert-flash alert-success"><?= $success ?></div><?php endif; ?>
<?php if (!empty($error)):   ?><div class="alert-flash alert-error"><?= e($error) ?></div><?php endif; ?>

<?php
    $tieneRestriccion = !empty($asignadas);
?>

<div class="form-card" style="max-width:720px">
    <p style="margin-top:0;color:#374151">
        Por defecto, los miembros con rol <code>operario</code> o <code>lector</code> ven
        <strong>todas</strong> las granjas de la organización. Aquí puedes restringir qué
        granjas verá <strong><?= e($miembro['nombre']) ?></strong>.
    </p>

    <form method="POST" action="<?= base_url("equipo/{$miembro['id']}/granjas") ?>">
        <?= csrf_field() ?>

        <div style="margin:1rem 0">
            <label style="display:flex;align-items:center;gap:.5rem;padding:.6rem .8rem;border:1px solid #e5e7eb;border-radius:.4rem;margin-bottom:.4rem;cursor:pointer">
                <input type="radio" name="modo" value="todas" <?= !$tieneRestriccion ? 'checked' : '' ?> onchange="document.getElementById('lista-granjas').style.display='none'">
                <span><strong>Acceso total</strong> — ve todas las granjas de la organización (recomendado por defecto).</span>
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;padding:.6rem .8rem;border:1px solid #e5e7eb;border-radius:.4rem;cursor:pointer">
                <input type="radio" name="modo" value="restringir" <?= $tieneRestriccion ? 'checked' : '' ?> onchange="document.getElementById('lista-granjas').style.display='block'">
                <span><strong>Restringir a granjas concretas</strong> — sólo verá las que selecciones abajo.</span>
            </label>
        </div>

        <div id="lista-granjas" style="display:<?= $tieneRestriccion ? 'block' : 'none' ?>;background:#f9fafb;padding:.8rem 1rem;border-radius:.4rem;margin:1rem 0">
            <?php if (empty($granjas)): ?>
                <p style="color:#9ca3af;margin:0">La organización no tiene granjas todavía.</p>
            <?php else: ?>
                <?php foreach ($granjas as $g): ?>
                    <label style="display:flex;align-items:center;gap:.4rem;padding:.3rem 0">
                        <input type="checkbox" name="granja_ids[]" value="<?= (int)$g['id'] ?>"
                            <?= in_array((int)$g['id'], $asignadas, true) ? 'checked' : '' ?>>
                        <span><?= e($g['nombre']) ?></span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar acceso</button>
        </div>
    </form>
</div>
