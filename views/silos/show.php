<?php
$pct    = (int)($silo['pct_stock'] ?? 0);
$alerta = $silo['stock_actual_kg'] <= $silo['stock_minimo_kg'];
$color  = $alerta ? '#dc2626' : ($pct < 30 ? '#d97706' : '#16a34a');
?>

<div class="page-header">
    <h2>Detalle silo</h2>
    <a href="<?= base_url('silos') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($alerta): ?>
    <div class="alert-flash alert-error">
        Stock por debajo del mínimo — quedan <?= number_format($silo['stock_actual_kg'], 0) ?> kg
        (mínimo: <?= number_format($silo['stock_minimo_kg'], 0) ?> kg)
    </div>
<?php endif; ?>

<div class="detail-header">
    <div>
        <div class="detail-nombre"><?= e($silo['nombre']) ?></div>
        <div class="detail-sub"><?= e($silo['granja_nombre']) ?></div>
        <div class="detail-meta">
            <div class="meta-item">
                <strong><?= number_format($silo['stock_actual_kg'], 0) ?> kg</strong>
                Stock actual
            </div>
            <div class="meta-item">
                <strong><?= number_format($silo['capacidad_kg'], 0) ?> kg</strong>
                Capacidad total
            </div>
            <div class="meta-item">
                <strong><?= number_format($silo['stock_minimo_kg'], 0) ?> kg</strong>
                Stock mínimo
            </div>
            <?php if ($silo['naves_abastecidas']): ?>
            <div class="meta-item">
                <strong><?= e($silo['naves_abastecidas']) ?></strong>
                Naves abastecidas
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div style="text-align:center;min-width:140px">
        <div style="font-size:2.5rem;font-weight:700;color:<?= $color ?>;line-height:1"><?= $pct ?>%</div>
        <div style="font-size:.78rem;color:#9ca3af;margin:.3rem 0 .6rem">nivel actual</div>
        <div style="height:12px;background:#e5e7eb;border-radius:99px;overflow:hidden">
            <div style="height:12px;width:<?= min($pct,100) ?>%;background:<?= $color ?>;border-radius:99px;transition:width .4s"></div>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:.75rem">
            <a href="<?= base_url("silos/{$silo['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
            <?php if (es_director()): ?>
            <form method="POST" action="<?= base_url("silos/{$silo['id']}/eliminar") ?>"
                  onsubmit="return confirm('¿Eliminar este silo?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($silo['descripcion']): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="form-section-title" style="margin-bottom:.75rem">Observaciones</div>
    <p style="font-size:.875rem;color:#374151"><?= e($silo['descripcion']) ?></p>
</div>
<?php endif; ?>

<?php
// ── Proyección de consumo ──────────────────────────────────────
$proy         = $proyeccion;
$diasRestantes = $proy['dias_hasta_minimo'];
$alertaProy   = $diasRestantes !== null && $diasRestantes <= 7;
$colorProy    = $alertaProy ? '#dc2626' : ($diasRestantes !== null && $diasRestantes <= 14 ? '#d97706' : '#16a34a');
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">

<!-- Proyección de consumo -->
<div class="list-card">
    <div style="padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">
        Proyección de consumo
    </div>
    <?php if ($proy['consumo_diario_kg'] > 0): ?>
    <div style="padding:1rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="kpi-card">
            <div class="kpi-label">Consumo estimado</div>
            <div class="kpi-value"><?= number_format($proy['consumo_diario_kg'], 1) ?> kg</div>
            <div class="kpi-sub">por día</div>
        </div>
        <div class="kpi-card <?= $alertaProy ? 'danger' : '' ?>">
            <div class="kpi-label">Stock mínimo en</div>
            <div class="kpi-value" style="color:<?= $colorProy ?>"><?= $diasRestantes ?? '—' ?> días</div>
            <div class="kpi-sub"><?= $proy['fecha_minimo'] ? date('d/m/Y', strtotime($proy['fecha_minimo'])) : '—' ?></div>
        </div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:.8rem">
        <thead>
            <tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;color:#6b7280">
                <th style="padding:.4rem .75rem;text-align:left">Lote</th>
                <th style="padding:.4rem .75rem;text-align:left">Nave</th>
                <th style="padding:.4rem .75rem;text-align:center">Sem.</th>
                <th style="padding:.4rem .75rem;text-align:right">Animales</th>
                <th style="padding:.4rem .75rem;text-align:right">Consumo/día</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($proy['lotes'] as $i => $l): ?>
            <tr style="background:<?= $i%2===0?'#fff':'#f9fafb' ?>;border-bottom:1px solid #f3f4f6">
                <td style="padding:.35rem .75rem;font-family:monospace;font-weight:600;color:#1d4ed8"><?= e($l['codigo'] ?? '') ?></td>
                <td style="padding:.35rem .75rem;color:#6b7280"><?= e($l['nave_nombre'] ?? '—') ?></td>
                <td style="padding:.35rem .75rem;text-align:center;color:#9ca3af">S<?= $l['semana_actual'] ?? '?' ?></td>
                <td style="padding:.35rem .75rem;text-align:right"><?= number_format((int)$l['num_animales']) ?></td>
                <td style="padding:.35rem .75rem;text-align:right;font-weight:600"><?= number_format((float)($l['consumo_diario_kg']??0), 2) ?> kg</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state" style="font-size:.875rem">Sin lotes activos asignados a este silo o sin tabla de consumo.</div>
    <?php endif; ?>
</div>

<!-- Recarga + Pedido -->
<div style="display:flex;flex-direction:column;gap:1rem">

    <!-- Formulario recarga -->
    <div class="form-card">
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.75rem">
            Registrar recarga
        </div>
        <form method="POST" action="<?= base_url("silos/{$silo['id']}/recarga") ?>">
            <?= csrf_field() ?>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Cantidad (kg) *</label>
                    <input type="number" name="cantidad_kg" required min="1" step="0.1" placeholder="Ej: 10000">
                </div>
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Proveedor</label>
                <input type="text" name="proveedor" placeholder="Nombre del proveedor">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Añadir recarga</button>
            </div>
        </form>
    </div>

    <!-- Pedido por email -->
    <div class="form-card">
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.75rem">
            Enviar pedido por email
        </div>
        <?php $emailGuardado = $emailPedidos ?? ''; ?>
        <form method="POST" action="<?= base_url("silos/{$silo['id']}/pedido") ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Email del proveedor / destinatario *</label>
                <input type="email" name="email_pedido" required
                       value="<?= e($emailGuardado) ?>"
                       placeholder="proveedor@ejemplo.com">
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.4rem;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="guardar_email" value="1" <?= $emailGuardado ? 'checked' : '' ?>>
                    Recordar este email
                </label>
            </div>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:.4rem;padding:.6rem .75rem;font-size:.8rem;color:#0369a1;margin-bottom:.75rem">
                Se enviará un pedido con <strong><?= number_format(max(0, (float)$silo['capacidad_kg'] - (float)$silo['stock_actual_kg']), 0) ?> kg</strong>
                estimados para llenar el silo
                <?php if ($proy['fecha_minimo']): ?>
                    · Stock mínimo estimado el <strong><?= date('d/m/Y', strtotime($proy['fecha_minimo'])) ?></strong>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enviar pedido</button>
            </div>
        </form>
    </div>

</div>
</div>

<!-- Historial de recargas -->
<?php if (!empty($recargas)): ?>
<div class="list-card">
    <div style="padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">
        Historial de recargas
    </div>
    <table class="list-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th style="text-align:right">Cantidad</th>
                <th>Proveedor</th>
                <th>Registrado por</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recargas as $r): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                <td style="text-align:right;font-weight:600"><?= number_format((float)$r['cantidad_kg'], 0) ?> kg</td>
                <td style="color:#6b7280"><?= $r['proveedor'] ? e($r['proveedor']) : '<span style="color:#d1d5db">—</span>' ?></td>
                <td style="color:#6b7280;font-size:.875rem"><?= e($r['usuario_nombre']) ?></td>
                <td>
                    <form method="POST" action="<?= base_url("silos/{$silo['id']}/recarga/{$r['id']}/eliminar") ?>"
                          onsubmit="return confirm('¿Eliminar esta recarga? Se revertirá el stock.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>