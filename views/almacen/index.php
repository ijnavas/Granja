<?php
$hayAlertas = false;
foreach ($silos as $s) {
    if ($s['stock_actual_kg'] <= $s['stock_minimo_kg']) { $hayAlertas = true; break; }
}
$filtros    = $filtros ?? [];
$hayFiltros = !empty(array_filter($filtros));
?>

<div class="page-header">
    <h2>Almacén de pienso</h2>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($hayAlertas): ?>
    <div class="alert-flash alert-error" style="margin-bottom:1rem">Hay silos por debajo del stock mínimo</div>
<?php endif; ?>

<!-- ── Silos ──────────────────────────────────────────────────── -->
<?php if (empty($silos)): ?>
<div class="empty-state">No hay silos configurados. <a href="<?= base_url('silos/crear') ?>">Crear silo</a></div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;margin-bottom:2rem">
<?php foreach ($silos as $s):
    $pct    = (int)($s['pct_stock'] ?? 0);
    $alerta = $s['stock_actual_kg'] <= $s['stock_minimo_kg'];
    $color  = $alerta ? '#dc2626' : ($pct < 30 ? '#d97706' : '#16a34a');
?>
<a href="<?= base_url("almacen/{$s['id']}") ?>" class="list-card" style="display:block;text-decoration:none;color:inherit">
    <div style="padding:1rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem">
            <div>
                <div style="font-weight:700;font-size:.95rem"><?= e($s['nombre']) ?></div>
                <div style="font-size:.8rem;color:#6b7280"><?= e($s['granja_nombre']) ?></div>
                <?php if ($s['naves_abastecidas']): ?>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.2rem"><?= e($s['naves_abastecidas']) ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:right">
                <div style="font-size:1.5rem;font-weight:700;color:<?= $color ?>;line-height:1"><?= $pct ?>%</div>
                <div style="font-size:.7rem;color:#9ca3af">capacidad</div>
            </div>
        </div>
        <div style="height:8px;background:#e5e7eb;border-radius:99px;overflow:hidden;margin-bottom:.75rem">
            <div style="height:8px;width:<?= min($pct,100) ?>%;background:<?= $color ?>;border-radius:99px"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.8rem">
            <div>
                <div style="color:#6b7280">Stock actual</div>
                <div style="font-weight:600;color:<?= $alerta ? '#dc2626' : 'inherit' ?>"><?= number_format($s['stock_actual_kg'], 0) ?> kg</div>
            </div>
            <div>
                <div style="color:#6b7280">Stock mínimo</div>
                <div style="font-weight:600"><?= number_format($s['stock_minimo_kg'], 0) ?> kg</div>
            </div>
        </div>
        <?php if ($alerta): ?>
        <div style="margin-top:.6rem;font-size:.75rem;color:#dc2626;font-weight:600">Por debajo del mínimo</div>
        <?php endif; ?>
    </div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Histórico de recargas ─────────────────────────────────── -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
    <h3 style="margin:0;font-size:1rem;font-weight:700">Histórico de recargas</h3>
    <a href="<?= base_url('almacen/export') . ($hayFiltros ? '?' . http_build_query($filtros) : '') ?>"
       class="btn btn-secondary btn-sm" title="Descargar CSV">CSV</a>
</div>

<!-- Filtros -->
<form method="GET" action="<?= base_url('almacen') ?>"
      style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-bottom:1rem;padding:.75rem 1rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem">

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Desde</label>
        <input type="date" name="fecha_desde" value="<?= e($filtros['fecha_desde'] ?? '') ?>"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem">
    </div>
    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Hasta</label>
        <input type="date" name="fecha_hasta" value="<?= e($filtros['fecha_hasta'] ?? '') ?>"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem">
    </div>
    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Silo</label>
        <select name="silo_id" style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:130px">
            <option value="">Todos</option>
            <?php foreach ($silos as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($filtros['silo_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                <?= e($s['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Tipo pienso</label>
        <input type="text" name="tipo_pienso" value="<?= e($filtros['tipo_pienso'] ?? '') ?>"
               placeholder="Buscar…"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:130px">
    </div>
    <div style="display:flex;gap:.4rem;align-self:flex-end">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if ($hayFiltros): ?>
        <a href="<?= base_url('almacen') ?>" class="btn btn-secondary btn-sm">Limpiar</a>
        <?php endif; ?>
    </div>
    <div style="align-self:flex-end;font-size:.8rem;color:#6b7280;margin-left:auto">
        <?= number_format($paginacion->total) ?> recarga<?= $paginacion->total !== 1 ? 's' : '' ?>
    </div>
</form>

<div class="list-card">
<?php if (empty($recargas)): ?>
    <div class="empty-state">No hay recargas<?= $hayFiltros ? ' con esos filtros' : ' registradas todavía' ?>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Silo</th>
                <th>Tipo pienso</th>
                <th style="text-align:right">Cantidad (kg)</th>
                <th>Proveedor</th>
                <th>Albarán</th>
                <th>Usuario</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recargas as $r): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
            <td>
                <span style="font-weight:600"><?= e($r['silo_nombre']) ?></span>
                <div style="font-size:.75rem;color:#9ca3af"><?= e($r['granja_nombre']) ?></div>
            </td>
            <td><?= $r['tipo_pienso'] ? e($r['tipo_pienso']) : '<span style="color:#d1d5db">—</span>' ?></td>
            <td style="text-align:right;font-weight:600"><?= number_format((float)$r['cantidad_kg'], 0) ?></td>
            <td style="font-size:.82rem;color:#6b7280"><?= $r['proveedor'] ? e($r['proveedor']) : '—' ?></td>
            <td style="font-size:.82rem;color:#6b7280"><?= !empty($r['albaran']) ? e($r['albaran']) : '—' ?></td>
            <td style="font-size:.82rem;color:#6b7280"><?= e($r['usuario_nombre']) ?></td>
            <td>
                <div class="actions">
                    <a href="<?= base_url("almacen/recargas/{$r['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                    <form method="POST" action="<?= base_url("almacen/{$r['silo_id']}/recarga/{$r['id']}/eliminar") ?>"
                          onsubmit="return confirm('¿Eliminar esta recarga? Se restará del stock del silo.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php include __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
</div>
