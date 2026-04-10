<?php
$filtros    = $filtros ?? [];
$hayFiltros = !empty(array_filter($filtros));
?>

<div class="page-header">
    <h2>Pesajes</h2>
    <div style="display:flex;gap:.75rem;align-items:center">
        <a href="<?= base_url('pesajes/export') . ($hayFiltros ? '?' . http_build_query($filtros) : '') ?>"
           class="btn btn-secondary btn-sm" title="Descargar CSV">CSV</a>
        <a href="<?= base_url('pesajes/crear') ?>" class="btn btn-primary">+ Nuevo pesaje</a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- Filtros -->
<form method="GET" action="<?= base_url('pesajes') ?>"
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
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Lote</label>
        <input type="text" name="lote" value="<?= e($filtros['lote'] ?? '') ?>"
               placeholder="Codigo..."
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:120px">
    </div>

    <?php if (!empty($granjas) && count($granjas) > 1): ?>
    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Granja</label>
        <select name="granja_id" style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:130px">
            <option value="">Todas</option>
            <?php foreach ($granjas as $g): ?>
            <option value="<?= $g['id'] ?>" <?= ($filtros['granja_id'] ?? '') == $g['id'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:.4rem;align-self:flex-end">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if ($hayFiltros): ?>
        <a href="<?= base_url('pesajes') ?>" class="btn btn-secondary btn-sm">Limpiar</a>
        <?php endif; ?>
    </div>

    <?php if ($hayFiltros): ?>
    <div style="align-self:flex-end;font-size:.8rem;color:#6b7280;margin-left:auto">
        <?= number_format($paginacion->total) ?> resultado<?= $paginacion->total !== 1 ? 's' : '' ?>
    </div>
    <?php endif; ?>
</form>

<div class="list-card">
<?php if (empty($pesajes)): ?>
    <div class="empty-state">No hay pesajes<?= $hayFiltros ? ' con esos filtros' : ' registrados' ?>. <a href="<?= base_url('pesajes/crear') ?>">Registra el primero</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr style="font-size:.72rem">
                <th>Fecha</th>
                <th>Lote · Nave</th>
                <th style="text-align:center">Sem.</th>
                <th style="text-align:right">Animales</th>
                <th style="text-align:right">Peso real<br>(pesaje)</th>
                <th style="text-align:right">Peso tabla<br>(ese dia)</th>
                <th style="text-align:right">Peso real<br>proyectado hoy</th>
                <th style="text-align:right">Peso tabla<br>hoy</th>
                <th style="text-align:right">IC real</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pesajes as $p): ?>
            <?php
                $desv = null;
                if ($p['peso_tabla_pesaje'] && $p['peso_tabla_pesaje'] > 0) {
                    $desv = round((($p['peso_medio_kg'] - $p['peso_tabla_pesaje']) / $p['peso_tabla_pesaje']) * 100, 1);
                }
            ?>
            <tr>
                <td style="white-space:nowrap"><strong><?= date('d/m/Y', strtotime($p['fecha'])) ?></strong></td>
                <td style="white-space:nowrap">
                    <span style="font-family:monospace;font-weight:600;color:#1d4ed8"><?= e($p['lote_codigo']) ?></span>
                    <?php if ($p['nave_nombre'] ?? $p['granja_nombre'] ?? null): ?>
                        <span style="color:#9ca3af;font-size:.8rem"> · <?= e($p['nave_nombre'] ?? $p['granja_nombre']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;color:#6b7280">
                    <?= $p['semana_pesaje'] ? 'S' . $p['semana_pesaje'] : '—' ?>
                </td>
                <td style="text-align:right"><?= number_format($p['num_animales_pesados']) ?></td>
                <td style="text-align:right;font-weight:600">
                    <?= number_format((float)$p['peso_medio_kg'], 3) ?> kg
                    <?php if ($desv !== null): ?>
                        <span style="font-size:.72rem;color:<?= $desv >= 0 ? '#16a34a' : '#dc2626' ?>">
                            <?= $desv >= 0 ? '+' : '' ?><?= $desv ?>%
                        </span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;color:#6b7280">
                    <?= $p['peso_tabla_pesaje'] ? number_format((float)$p['peso_tabla_pesaje'], 3) . ' kg' : '—' ?>
                </td>
                <td style="text-align:right;font-weight:600;color:#1d4ed8">
                    <?= $p['peso_proyectado_hoy'] ? number_format((float)$p['peso_proyectado_hoy'], 3) . ' kg' : '—' ?>
                </td>
                <td style="text-align:right;color:#6b7280">
                    <?= $p['peso_tabla_hoy'] ? number_format((float)$p['peso_tabla_hoy'], 3) . ' kg' : '—' ?>
                </td>
                <td style="text-align:right;color:#6b7280">
                    <?= $p['ic_real'] ? number_format((float)$p['ic_real'], 3) : '—' ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("pesajes/{$p['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("pesajes/{$p['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar este pesaje?')">
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
