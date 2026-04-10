<?php
$pageTitle  = 'Lotes';
$filtros    = $filtros ?? [];
$hayFiltros = !empty(array_filter($filtros));
?>

<div class="page-header">
    <h2>Lotes</h2>
    <div style="display:flex;gap:.75rem;align-items:center">
        <a href="<?= base_url('lotes/historico') ?>" class="btn btn-secondary">Historico</a>
        <a href="<?= base_url('lotes/crear') ?>" class="btn btn-primary">+ Nuevo lote</a>
    </div>
</div>

<?php if ($flash = \App\Core\Session::getFlash('success')): ?>
    <div class="alert-flash alert-success"><?= $flash ?></div>
<?php endif; ?>

<!-- Filtros -->
<form method="GET" action="<?= base_url('lotes') ?>"
      style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-bottom:1rem;padding:.75rem 1rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem">

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Estado</label>
        <select name="estado" style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:120px">
            <option value="">Todos</option>
            <option value="activo" <?= ($filtros['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
            <option value="cerrado" <?= ($filtros['estado'] ?? '') === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Codigo</label>
        <input type="text" name="codigo" value="<?= e($filtros['codigo'] ?? '') ?>"
               placeholder="Buscar..."
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

    <?php if (!empty($razas) && count($razas) > 1): ?>
    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Raza</label>
        <select name="raza_id" style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:130px">
            <option value="">Todas</option>
            <?php foreach ($razas as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($filtros['raza_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:.4rem;align-self:flex-end">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if ($hayFiltros): ?>
        <a href="<?= base_url('lotes') ?>" class="btn btn-secondary btn-sm">Limpiar</a>
        <?php endif; ?>
    </div>

    <div style="align-self:flex-end;font-size:.8rem;color:#6b7280;margin-left:auto">
        <?= number_format($paginacion->total) ?> lote<?= $paginacion->total !== 1 ? 's' : '' ?>
    </div>
</form>

<div class="list-card">
<?php if (empty($lotes)): ?>
    <div class="empty-state">No hay lotes<?= $hayFiltros ? ' con esos filtros' : '' ?>. <a href="<?= base_url('lotes/crear') ?>">Crea el primero</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Granja / Nave</th>
                <th>Animales</th>
                <th>Semana</th>
                <th>Peso tabla</th>
                <th>Peso real</th>
                <th>Valoracion lote</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lotes as $l): ?>
            <?php
                $semana          = $l['semana_actual']        ?? null;
                $pesoTabla       = $l['peso_tabla']           ?? null;
                $costeTabla      = $l['coste_tabla']          ?? null;
                $pesoReal        = $l['peso_real_proyectado'] ?? null;
                $ultimoPesaje    = $l['ultimo_pesaje_fecha']  ?? null;
                $valoracion      = ($costeTabla && $l['num_animales']) ? $costeTabla * $l['num_animales'] : null;
                $desv = ($pesoTabla && $pesoReal)
                    ? round((($pesoReal - $pesoTabla) / $pesoTabla) * 100, 1)
                    : null;
            ?>
            <tr style="cursor:pointer<?= $l['estado'] === 'cerrado' ? ';opacity:.5' : '' ?>"
                onclick="window.location='<?= base_url("lotes/{$l['id']}/editar") ?>'">
                <td>
                    <strong style="font-family:monospace"><?= e($l['codigo']) ?></strong>
                    <?php if ($l['raza_nombre']): ?>
                        <div style="font-size:.75rem;color:#9ca3af"><?= e($l['raza_nombre']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($l['nave_nombre']): ?>
                        <?= e($l['nave_nombre']) ?>
                        <span style="color:#9ca3af;font-size:.8rem"> · <?= e($l['granja_nombre'] ?? '') ?></span>
                    <?php elseif ($l['granja_nombre']): ?>
                        <span style="color:#9ca3af"><?= e($l['granja_nombre']) ?></span>
                    <?php else: ?>
                        <span style="color:#d1d5db;font-style:italic">Sin asignar</span>
                    <?php endif; ?>
                </td>
                <td><?= number_format($l['num_animales']) ?></td>
                <td>
                    <?php if ($semana !== null): ?>
                        <span style="font-weight:600">S<?= $semana ?></span>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($pesoTabla): ?>
                        <?= number_format($pesoTabla, 3) ?> kg
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($pesoReal): ?>
                        <span style="font-weight:600;color:#1d4ed8"><?= number_format($pesoReal, 3) ?> kg</span>
                        <?php if ($desv !== null): ?>
                            <span style="font-size:.72rem;color:<?= $desv >= 0 ? '#16a34a' : '#dc2626' ?>;display:block">
                                <?= $desv >= 0 ? '+' : '' ?><?= $desv ?>% vs tabla
                            </span>
                        <?php endif; ?>
                        <span style="font-size:.7rem;color:#9ca3af;display:block">
                            Pesaje <?= date('d/m/Y', strtotime($ultimoPesaje)) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#d1d5db">Sin pesaje</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($valoracion): ?>
                        <div style="font-weight:600"><?= number_format($valoracion, 2) ?> EUR</div>
                        <div style="font-size:.75rem;color:#9ca3af"><?= number_format($costeTabla, 2) ?> EUR/animal</div>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= e($l['estado']) ?>"><?= e($l['estado']) ?></span></td>
                <td onclick="event.stopPropagation()">
                    <div class="actions">
                        <a href="<?= base_url("lotes/{$l['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <a href="<?= base_url("pesajes/crear?lote_id={$l['id']}") ?>" class="btn btn-secondary btn-sm">Pesar</a>
                        <?php if ($l['estado'] === 'activo'): ?>
                        <form method="POST" action="<?= base_url("lotes/{$l['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Cerrar el lote <?= e($l['codigo']) ?>?\nSe guardara en el historico.')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary btn-sm">Cerrar</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= base_url("lotes/{$l['id']}/borrar") ?>"
                              onsubmit="return confirmarEliminar('<?= e($l['codigo']) ?>')">
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

<script>
function confirmarEliminar(codigo) {
    return confirm(
        'ELIMINAR LOTE ' + codigo + '\n\n' +
        'Esta accion NO se puede deshacer.\n' +
        'Se borraran:\n' +
        '  - Todos los movimientos (bajas, ventas, traslados)\n' +
        '  - Todos los pesajes\n' +
        '  - La asignacion de cuadras\n' +
        '  - El propio lote\n\n' +
        '¿Estas seguro?'
    );
}
</script>
