<?php
$filtros = $filtros ?? [];
$totalPages = (int) ceil(max(1, $total) / $perPage);
$seccionConfig = 'audit';
include __DIR__ . '/../config/_submenu.php';

$accionBadge = function (string $a): array {
    return match ($a) {
        'create'  => ['#d1fae5', '#065f46'],
        'update'  => ['#dbeafe', '#1e40af'],
        'delete'  => ['#fee2e2', '#991b1b'],
        'ajustar' => ['#fef3c7', '#92400e'],
        'cerrar'  => ['#f3e8ff', '#6b21a8'],
        default   => ['#f3f4f6', '#374151'],
    };
};
?>

<div class="page-header">
    <h2>Audit log</h2>
    <span style="color:#6b7280;font-size:.85rem"><?= number_format($total) ?> eventos</span>
</div>

<form method="GET" action="<?= base_url('admin/audit-log') ?>"
      style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-bottom:1rem;padding:.75rem 1rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem">

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase">Entidad</label>
        <select name="entidad" style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem">
            <option value="">Todas</option>
            <?php foreach ($entidades as $e): ?>
            <option value="<?= e($e) ?>" <?= $filtros['entidad'] === $e ? 'selected' : '' ?>><?= e($e) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase">Acción</label>
        <select name="accion" style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem">
            <option value="">Todas</option>
            <?php foreach ($acciones as $a): ?>
            <option value="<?= e($a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase">Usuario ID</label>
        <input type="number" name="user_id" value="<?= e((string)$filtros['user_id']) ?>"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;width:90px">
    </div>

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase">Entidad ID</label>
        <input type="number" name="entidad_id" value="<?= e((string)$filtros['entidad_id']) ?>"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;width:90px">
    </div>

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase">Desde</label>
        <input type="date" name="fecha_desde" value="<?= e((string)$filtros['fecha_desde']) ?>"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem">
    </div>

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase">Hasta</label>
        <input type="date" name="fecha_hasta" value="<?= e((string)$filtros['fecha_hasta']) ?>"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem">
    </div>

    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    <a href="<?= base_url('admin/audit-log') ?>" class="btn btn-secondary btn-sm">Limpiar</a>
</form>

<div class="list-card">
    <table class="list-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Entidad</th>
                <th>ID</th>
                <th>IP</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:1.5rem">Sin eventos.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php [$bg, $fg] = $accionBadge((string)$r['accion']); ?>
                <tr>
                    <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime((string)$r['created_at']))) ?></td>
                    <td>
                        <?php if ($r['usuario_nombre']): ?>
                            <?= e((string)$r['usuario_nombre']) ?>
                            <div style="font-size:.7rem;color:#9ca3af"><?= e((string)$r['usuario_email']) ?></div>
                        <?php else: ?>
                            <span style="color:#9ca3af">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:.15rem .5rem;border-radius:.3rem;font-size:.75rem;font-weight:600"><?= e((string)$r['accion']) ?></span>
                    </td>
                    <td><?= e((string)$r['entidad']) ?></td>
                    <td><?= $r['entidad_id'] !== null ? (int)$r['entidad_id'] : '—' ?></td>
                    <td style="font-family:monospace;font-size:.75rem"><?= e((string)($r['ip'] ?? '')) ?></td>
                    <td><a href="<?= base_url('admin/audit-log/' . (int)$r['id']) ?>" class="btn btn-secondary btn-sm">Ver</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:.5rem;justify-content:center;margin-top:1rem;flex-wrap:wrap">
    <?php
    $qs = $_GET; unset($qs['page']);
    $base = base_url('admin/audit-log') . (empty($qs) ? '?' : '?' . http_build_query($qs) . '&');
    ?>
    <?php if ($page > 1): ?>
        <a href="<?= e($base . 'page=' . ($page - 1)) ?>" class="btn btn-secondary btn-sm">« Anterior</a>
    <?php endif; ?>
    <span style="padding:.4rem .75rem;color:#6b7280;font-size:.85rem">Página <?= $page ?> de <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a href="<?= e($base . 'page=' . ($page + 1)) ?>" class="btn btn-secondary btn-sm">Siguiente »</a>
    <?php endif; ?>
</div>
<?php endif; ?>
