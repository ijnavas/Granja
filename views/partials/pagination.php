<?php
/**
 * Partial de paginación reutilizable.
 *
 * Requiere:
 *   $paginacion  — instancia de \App\Core\Paginator
 *   $baseUrl     — (opcional) base del enlace, default ''
 */
if (!isset($paginacion) || !$paginacion->hasPages()) return;

$p       = $paginacion;
$base    = $baseUrl ?? '';
$current = $p->page;
$last    = $p->totalPages;

// Rango de páginas a mostrar: current-2 .. current+2
$from = max(1, $current - 2);
$to   = min($last, $current + 2);
?>
<nav style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1rem;flex-wrap:wrap">
    <span style="font-size:.8rem;color:#6b7280">
        <?= number_format($p->total) ?> resultado<?= $p->total !== 1 ? 's' : '' ?>
        &middot; pág. <?= $current ?>/<?= $last ?>
    </span>

    <div style="display:flex;gap:.25rem;align-items:center">
        <?php if ($current > 1): ?>
        <a href="<?= \App\Core\Paginator::url(1, $base) ?>"
           style="padding:.3rem .6rem;font-size:.8rem;border:1px solid #d1d5db;border-radius:.35rem;color:#374151;text-decoration:none">&laquo;</a>
        <a href="<?= \App\Core\Paginator::url($current - 1, $base) ?>"
           style="padding:.3rem .6rem;font-size:.8rem;border:1px solid #d1d5db;border-radius:.35rem;color:#374151;text-decoration:none">&lsaquo;</a>
        <?php endif; ?>

        <?php if ($from > 1): ?>
        <span style="padding:.3rem .3rem;font-size:.8rem;color:#9ca3af">&hellip;</span>
        <?php endif; ?>

        <?php for ($i = $from; $i <= $to; $i++): ?>
        <?php if ($i === $current): ?>
        <span style="padding:.3rem .6rem;font-size:.8rem;border:1px solid #2563eb;border-radius:.35rem;background:#2563eb;color:#fff;font-weight:600"><?= $i ?></span>
        <?php else: ?>
        <a href="<?= \App\Core\Paginator::url($i, $base) ?>"
           style="padding:.3rem .6rem;font-size:.8rem;border:1px solid #d1d5db;border-radius:.35rem;color:#374151;text-decoration:none"><?= $i ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($to < $last): ?>
        <span style="padding:.3rem .3rem;font-size:.8rem;color:#9ca3af">&hellip;</span>
        <?php endif; ?>

        <?php if ($current < $last): ?>
        <a href="<?= \App\Core\Paginator::url($current + 1, $base) ?>"
           style="padding:.3rem .6rem;font-size:.8rem;border:1px solid #d1d5db;border-radius:.35rem;color:#374151;text-decoration:none">&rsaquo;</a>
        <a href="<?= \App\Core\Paginator::url($last, $base) ?>"
           style="padding:.3rem .6rem;font-size:.8rem;border:1px solid #d1d5db;border-radius:.35rem;color:#374151;text-decoration:none">&raquo;</a>
        <?php endif; ?>
    </div>
</nav>
