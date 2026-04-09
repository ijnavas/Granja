<?php
$seccionConfig = 'audit';
include __DIR__ . '/../config/_submenu.php';
$fmt = function ($v): string {
    if ($v === null) return '<span style="color:#9ca3af">(vacío)</span>';
    return '<pre style="white-space:pre-wrap;word-break:break-word;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.35rem;padding:.75rem;font-size:.8rem;margin:0">'
         . e(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
         . '</pre>';
};
?>

<div class="page-header">
    <h2>Audit log #<?= (int)$row['id'] ?></h2>
    <a href="<?= base_url('admin/audit-log') ?>" class="btn btn-secondary">← Volver</a>
</div>

<div class="form-card" style="max-width:900px">
    <table style="width:100%;border-collapse:collapse;font-size:.88rem;margin-bottom:1rem">
        <tbody>
            <tr><td style="padding:.35rem 0;color:#6b7280;width:140px">Fecha</td><td><?= e(date('d/m/Y H:i:s', strtotime((string)$row['created_at']))) ?></td></tr>
            <tr><td style="padding:.35rem 0;color:#6b7280">Usuario</td>
                <td><?= $row['usuario_nombre'] ? e((string)$row['usuario_nombre']) . ' (' . e((string)$row['usuario_email']) . ')' : '<span style="color:#9ca3af">—</span>' ?></td></tr>
            <tr><td style="padding:.35rem 0;color:#6b7280">Acción</td><td><strong><?= e((string)$row['accion']) ?></strong></td></tr>
            <tr><td style="padding:.35rem 0;color:#6b7280">Entidad</td><td><?= e((string)$row['entidad']) ?> <?= $row['entidad_id'] !== null ? '#' . (int)$row['entidad_id'] : '' ?></td></tr>
            <tr><td style="padding:.35rem 0;color:#6b7280">IP</td><td style="font-family:monospace;font-size:.85rem"><?= e((string)($row['ip'] ?? '')) ?></td></tr>
            <tr><td style="padding:.35rem 0;color:#6b7280">Navegador</td><td style="font-size:.78rem;color:#6b7280"><?= e((string)($row['user_agent'] ?? '')) ?></td></tr>
        </tbody>
    </table>

    <h3 style="font-size:1rem;margin:1rem 0 .5rem">Datos antes</h3>
    <?= $fmt($row['datos_antes_decoded']) ?>

    <h3 style="font-size:1rem;margin:1.25rem 0 .5rem">Datos después</h3>
    <?= $fmt($row['datos_despues_decoded']) ?>
</div>
