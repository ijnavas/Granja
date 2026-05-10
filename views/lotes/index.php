<?php
$pageTitle  = 'Lotes';
$filtros    = $filtros ?? [];
$hayFiltros = !empty(array_filter($filtros));
?>

<style>
/* Filas compactas en /lotes */
.list-card table.list-table.lotes-compact td,
.list-card table.list-table.lotes-compact th {
    padding-top: .3rem;
    padding-bottom: .3rem;
    padding-left: .55rem;
    padding-right: .55rem;
    font-size: .82rem;
    line-height: 1.25;
}
.list-card table.list-table.lotes-compact .actions { gap: .2rem; flex-wrap: nowrap }
.list-card table.list-table.lotes-compact .actions .btn-sm { padding: .1rem .45rem; font-size: .7rem }
</style>

<div class="page-header">
    <h2>Lotes</h2>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <form method="POST" action="<?= base_url('lotes/actualizar-estado-animal') ?>"
              onsubmit="return confirm('¿Aplicar transición lechón → cebo en los lotes que han alcanzado el peso de tabla?')"
              style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm" title="Pasar lotes lechón a cebo según peso de tabla">
                Lechón → cebo
            </button>
        </form>
        <a href="<?= base_url('lotes/export') . ($hayFiltros ? '?' . http_build_query($filtros) : '') ?>"
           class="btn btn-secondary btn-sm" title="Descargar CSV">CSV</a>
        <a href="<?= base_url('lotes/historico') ?>" class="btn btn-secondary">Historico</a>
        <a href="<?= base_url('lotes/crear?modo=destete') ?>" class="btn btn-primary">+ Destete (alta de lote)</a>
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
    <div class="empty-state">No hay lotes<?= $hayFiltros ? ' con esos filtros' : '' ?>. <a href="<?= base_url('lotes/crear?modo=destete') ?>">Da de alta el primero (destete)</a>.</div>
<?php else: ?>
    <table class="list-table lotes-compact" id="tablaLotes">
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Nave / Cuadra</th>
                <th style="text-align:right">Animales</th>
                <th style="text-align:center">Semana</th>
                <th style="text-align:right">P. tabla</th>
                <th style="text-align:right">Peso real</th>
                <th style="text-align:right">Valoración</th>
                <th>Estado</th>
                <th></th>
            </tr>
            <!-- Fila de filtros por columna (cliente) -->
            <tr class="filtros-fila" style="background:#fafafa">
                <?php for ($i = 0; $i < 8; $i++): ?>
                <th style="padding:.25rem .4rem">
                    <input type="text" oninput="filtrarLotes()" data-col="<?= $i ?>"
                           placeholder="Filtrar"
                           style="width:100%;padding:.18rem .35rem;border:1px solid #d1d5db;border-radius:.25rem;font-size:.72rem;font-weight:400">
                </th>
                <?php endfor; ?>
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
                    <?php $tags = $tagsByLote[$l['id']] ?? []; ?>
                    <?php if (!empty($tags)): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:.2rem;margin-top:.2rem">
                        <?php foreach ($tags as $t): ?>
                        <span style="background:<?= e($t['color']) ?>22;color:<?= e($t['color']) ?>;font-size:.65rem;font-weight:600;padding:.02rem .35rem;border-radius:99px;border:1px solid <?= e($t['color']) ?>44">
                            <?= e($t['nombre']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                        $cuadras = $cuadrasByLote[$l['id']] ?? [];
                        $cuadraStr = !empty($cuadras)
                            ? implode(', ', $cuadras)
                            : ($l['nave_nombre'] ? $l['nave_nombre'] : '—');
                    ?>
                    <span style="font-family:monospace;font-size:.82rem"><?= e($cuadraStr) ?></span>
                </td>
                <td style="text-align:right"><?= number_format($l['num_animales']) ?></td>
                <td style="text-align:center">
                    <?php if ($semana !== null): ?>
                        <span style="font-weight:600">S<?= $semana ?></span>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap">
                    <?php if ($pesoTabla): ?>
                        <?= number_format($pesoTabla, 0) ?> kg
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;text-align:right">
                    <?php if ($pesoReal): ?>
                        <div style="white-space:nowrap">
                            <span style="font-weight:600;color:#1d4ed8"><?= number_format($pesoReal, 0) ?> kg</span>
                            <?php if ($desv !== null): ?>
                            <span style="font-size:.78rem;font-weight:600;color:<?= $desv >= 0 ? '#16a34a' : '#dc2626' ?>;margin-left:.25rem">
                                <?= $desv >= 0 ? '+' : '' ?><?= $desv ?>%
                            </span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:.7rem;color:#9ca3af;display:block">
                            Peso <?= date('d/m/y', strtotime($ultimoPesaje)) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#d1d5db">Sin peso</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap">
                    <?php if ($valoracion): ?>
                        <div style="font-weight:600"><?= number_format($valoracion, 0) ?> €</div>
                        <div style="font-size:.7rem;color:#9ca3af"><?= number_format($costeTabla, 2) ?> €/ud</div>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= e($l['estado']) ?>"><?= e($l['estado']) ?></span></td>
                <td onclick="event.stopPropagation()">
                    <div class="actions">
                        <a href="<?= base_url("lotes/{$l['id']}/trazabilidad") ?>" class="btn btn-secondary btn-sm">Trazabilidad</a>
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

// Filtrado cliente por columna en /lotes
function filtrarLotes() {
    const filtros = Array.from(document.querySelectorAll('#tablaLotes .filtros-fila input'))
        .map(i => (i.value || '').trim().toLowerCase());
    const tbody = document.querySelector('#tablaLotes tbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(tr => {
        const tds = tr.querySelectorAll('td');
        let visible = true;
        tds.forEach((td, idx) => {
            if (idx >= filtros.length) return; // celda de acciones
            const f = filtros[idx];
            if (!f) return;
            const txt = (td.textContent || '').trim().toLowerCase();
            if (!txt.includes(f)) visible = false;
        });
        tr.style.display = visible ? '' : 'none';
    });
}
</script>
