<?php
$seccion = $seccionConfig ?? 'razas';
?>
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:1rem;flex-wrap:wrap">
    <a href="<?= base_url('configuracion/general') ?>"
       class="btn <?= $seccion === 'general' ? 'btn-primary' : 'btn-secondary' ?>">
        Avisos
    </a>
    <?php if (es_admin()): ?>
    <a href="<?= base_url('configuracion/razas') ?>"
       class="btn <?= $seccion === 'razas' ? 'btn-primary' : 'btn-secondary' ?>">
        Razas porcinas
    </a>
    <a href="<?= base_url('configuracion/tablas') ?>"
       class="btn <?= $seccion === 'tablas' ? 'btn-primary' : 'btn-secondary' ?>">
        Tablas de crecimiento
    </a>
    <a href="<?= base_url('configuracion/estados') ?>"
       class="btn <?= $seccion === 'estados' ? 'btn-primary' : 'btn-secondary' ?>">
        Estados animal
    </a>
    <a href="<?= base_url('configuracion/movimientos') ?>"
       class="btn <?= $seccion === 'movimientos' ? 'btn-primary' : 'btn-secondary' ?>">
        Movimientos
    </a>
    <?php endif; ?>
    <a href="<?= base_url('recevet') ?>"
       class="btn <?= $seccion === 'recevet' ? 'btn-primary' : 'btn-secondary' ?>">
        Recevet
    </a>
    <?php if (es_admin()): ?>
    <a href="<?= base_url('admin/audit-log') ?>"
       class="btn <?= $seccion === 'audit' ? 'btn-primary' : 'btn-secondary' ?>">
        Audit log
    </a>
    <a href="<?= base_url('configuracion/reset') ?>"
       class="btn <?= $seccion === 'reset' ? 'btn-danger' : 'btn-secondary' ?>"
       style="<?= $seccion === 'reset' ? '' : 'color:#dc2626;border-color:#fca5a5' ?>">
        ⚠ Resetear datos
    </a>
    <?php endif; ?>
    <a href="<?= base_url('perfil') ?>"
       class="btn btn-secondary" style="margin-left:auto">
        Mi perfil
    </a>
</div>
