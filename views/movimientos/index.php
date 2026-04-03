<?php
$etiquetas = [
    'traslado_cuadra'    => ['label' => 'Traslado cuadra',   'color' => '#dbeafe', 'text' => '#1e40af'],
    'entrada_cebo'       => ['label' => 'Entrada cebo',      'color' => '#fef3c7', 'text' => '#92400e'],
    'entrada_reposicion' => ['label' => 'Reposición',        'color' => '#f3e8ff', 'text' => '#6b21a8'],
    'entrada_madres'     => ['label' => 'Entrada madres',    'color' => '#fce7f3', 'text' => '#9d174d'],
    'venta'              => ['label' => 'Venta',             'color' => '#d1fae5', 'text' => '#065f46'],
    'baja'               => ['label' => 'Baja',              'color' => '#fee2e2', 'text' => '#991b1b'],
];
?>

<div class="page-header">
    <h2>Movimientos</h2>
    <div style="display:flex;gap:.5rem">
        <?php foreach ($etiquetas as $tipo => $e): ?>
        <a href="<?= base_url('movimientos/crear?tipo=' . $tipo) ?>" class="btn btn-secondary btn-sm">
            + <?= $e['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="list-card">
<?php if (empty($movimientos)): ?>
    <div class="empty-state">No hay movimientos registrados todavía.</div>
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
            <?php $e = $etiquetas[$m['tipo']] ?? ['label' => $m['tipo'], 'color' => '#f3f4f6', 'text' => '#374151']; ?>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("movimientos/{$m['id']}/editar") ?>'">
                <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                <td>
                    <span style="background:<?= $e['color'] ?>;color:<?= $e['text'] ?>;padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:600">
                        <?= $e['label'] ?>
                    </span>
                </td>
                <td><span style="font-family:monospace;font-weight:600"><?= e($m['lote_origen_codigo']) ?></span></td>
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
                        <?php if ($m['precio_eur']): ?> · <?= number_format($m['precio_eur'], 2) ?> €<?php endif; ?>
                    <?php elseif ($m['tipo'] === 'traslado_cuadra'): ?>
                        <?= e($m['nave_origen_nombre'] ?? '') ?> → <?= e($m['nave_destino_nombre'] ?? '') ?>
                    <?php elseif ($m['tipo'] === 'baja' && !empty($m['motivo_baja'])): ?>
                        <?= $m['motivo_baja'] === 'enfermedad' ? '🤒 Enfermedad' : '⚰ Sacrificio' ?>
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
<?php endif; ?>
</div>