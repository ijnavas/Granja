<?php
// Construir mapa de etiquetas a partir de los tipos definidos en BD.
// Backwards-compat: si aparece un tipo histórico que ya no existe en BD,
// se renderiza con el código y un color genérico.
$etiquetas = [];
$tiposMostrar = [];
foreach (($tipos ?? []) as $t) {
    $color = $t['color'] ?: '#374151';
    // Convertir a versiones más claras para fondo (mantenemos compat visual)
    $bgMap = [
        'traslado'    => ['#dbeafe', '#1e40af'],
        'transicion'  => ['#fef3c7', '#92400e'],
        're_creacion' => ['#f3e8ff', '#6b21a8'],
        're_consumo'  => ['#fce7f3', '#9d174d'],
        'venta'       => ['#d1fae5', '#065f46'],
        'baja'        => ['#fee2e2', '#991b1b'],
        'salida'      => ['#fef3c7', '#92400e'],
        'entrada'     => ['#dcfce7', '#166534'],
    ];
    [$bg, $tx] = $bgMap[$t['categoria']] ?? ['#f3f4f6', '#374151'];
    $etiquetas[$t['codigo']] = ['label' => $t['nombre'], 'color' => $bg, 'text' => $tx];
    if ((int)$t['activo'] === 1) {
        $tiposMostrar[$t['codigo']] = $t['nombre'];
    }
}
$filtros = $filtros ?? [];
$hayFiltros = !empty(array_filter($filtros));
?>

<div class="page-header">
    <h2>Movimientos</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <a href="<?= base_url('movimientos/export') . ($hayFiltros ? '?' . http_build_query($filtros) : '') ?>"
           class="btn btn-secondary btn-sm" title="Descargar CSV">CSV</a>
        <a href="<?= base_url('movimientos/crear') ?>" class="btn btn-primary btn-sm">+ Nuevo movimiento</a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- ── Filtros ──────────────────────────────────────────────── -->
<form method="GET" action="<?= base_url('movimientos') ?>"
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
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Tipo</label>
        <select name="tipo" style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:140px">
            <option value="">Todos</option>
            <?php foreach ($tiposMostrar as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= ($filtros['tipo'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:.2rem">
        <label style="font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Lote</label>
        <input type="text" name="lote" value="<?= e($filtros['lote'] ?? '') ?>"
               placeholder="Código de lote…"
               style="padding:.3rem .55rem;border:1px solid #d1d5db;border-radius:.35rem;font-size:.85rem;min-width:130px">
    </div>

    <div style="display:flex;gap:.4rem;align-self:flex-end">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if ($hayFiltros): ?>
        <a href="<?= base_url('movimientos') ?>" class="btn btn-secondary btn-sm">Limpiar</a>
        <?php endif; ?>
    </div>

    <div style="align-self:flex-end;font-size:.8rem;color:#6b7280;margin-left:auto">
        <?= number_format($paginacion->total) ?> resultado<?= $paginacion->total !== 1 ? 's' : '' ?>
    </div>
</form>

<div class="list-card">
<?php if (empty($movimientos)): ?>
    <div class="empty-state">No hay movimientos<?= $hayFiltros ? ' con esos filtros' : ' registrados todavía' ?>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Lote origen</th>
                <th>Lote destino</th>
                <th>Cantidad</th>
                <th>Detalle</th>
                <th>Usuario</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($movimientos as $m): ?>
            <?php $et = $etiquetas[$m['tipo']] ?? ['label' => $m['tipo'], 'color' => '#f3f4f6', 'text' => '#374151']; ?>
            <?php
                $cuadraOrig = trim(($m['nave_origen_nombre'] ?? '') . ($m['cuadra_origen_nombre'] ? ' · ' . $m['cuadra_origen_nombre'] : ''));
            ?>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("movimientos/{$m['id']}/editar") ?>'">
                <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                <td>
                    <span style="background:<?= $et['color'] ?>;color:<?= $et['text'] ?>;padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:600">
                        <?= $et['label'] ?>
                    </span>
                </td>
                <td>
                    <span style="font-family:monospace;font-weight:600"><?= e($m['lote_origen_codigo']) ?></span>
                    <?php $tagsM = $tagsByMov[$m['id']] ?? []; ?>
                    <?php if (!empty($tagsM)): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:.2rem;margin-top:.2rem">
                        <?php foreach ($tagsM as $t): ?>
                        <span style="background:<?= e($t['color']) ?>22;color:<?= e($t['color']) ?>;font-size:.66rem;font-weight:600;padding:.05rem .35rem;border-radius:99px;border:1px solid <?= e($t['color']) ?>44">
                            <?= e($t['nombre']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['lote_destino_codigo']): ?>
                        <span style="font-family:monospace"><?= e($m['lote_destino_codigo']) ?></span>
                    <?php elseif ($m['cuadra_destino_nombre']): ?>
                        <span style="color:#6b7280;font-size:.82rem"><?= e($m['nave_destino_nombre'] ?? '') ?> · <?= e($m['cuadra_destino_nombre']) ?></span>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td><?= number_format($m['num_animales']) ?></td>
                <td style="font-size:.82rem;color:#6b7280">
                    <?php if ($m['tipo'] === 'venta'): ?>
                        <?= $m['tipo_venta'] === 'matadero' ? '🏭 Matadero' : '👤 Tercero' ?>
                        <?php if ($cuadraOrig): ?> · <?= e($cuadraOrig) ?><?php endif; ?>
                        <?php if ($m['precio_eur']): ?> · <?= number_format($m['precio_eur'], 2) ?> €<?php endif; ?>
                    <?php elseif ($m['tipo'] === 'traslado_cuadra'): ?>
                        <?php $cuadraDest = trim(($m['nave_destino_nombre'] ?? '') . ($m['cuadra_destino_nombre'] ? ' · ' . $m['cuadra_destino_nombre'] : '')); ?>
                        <?= e($cuadraOrig) ?> → <?= e($cuadraDest) ?>
                    <?php elseif ($m['tipo'] === 'baja'): ?>
                        <?php if (!empty($m['motivo_baja'])): ?>
                            <?= $m['motivo_baja'] === 'enfermedad' ? '🤒 Enfermedad' : '⚰ Sacrificio' ?>
                        <?php endif; ?>
                        <?php if ($cuadraOrig): ?> · <?= e($cuadraOrig) ?><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem;color:#6b7280"><?= e($m['usuario_nombre']) ?></td>
                <td onclick="event.stopPropagation()">
                    <div class="actions">
                        <a href="<?= base_url("movimientos/{$m['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("movimientos/{$m['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar este movimiento?')">
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
