<?php
$seccion = $seccionConfig ?? 'razas';
?>
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid #e5e7eb;padding-bottom:1rem">
    <a href="<?= base_url('configuracion/razas') ?>"
       class="btn <?= $seccion === 'razas' ? 'btn-primary' : 'btn-secondary' ?>">
        Razas porcinas
    </a>
    <a href="<?= base_url('configuracion/tablas') ?>"
       class="btn <?= $seccion === 'tablas' ? 'btn-primary' : 'btn-secondary' ?>">
        Tablas de crecimiento
    </a>
</div>