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
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
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
        <div style="display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap;justify-content:center">
            <a href="<?= base_url("silos/{$silo['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
            <button type="button" class="btn btn-sm" style="background:#7c3aed;color:#fff;border:none"
                    onclick="document.getElementById('tara-panel').style.display = document.getElementById('tara-panel').style.display === 'none' ? 'block' : 'none'">
                Tarar silo
            </button>
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
<div class="card" style="margin-top:1.5rem">
    <div class="form-section-title" style="margin-bottom:.75rem">Observaciones</div>
    <p style="font-size:.875rem;color:#374151"><?= e($silo['descripcion']) ?></p>
</div>
<?php endif; ?>

<!-- ── Panel de tara (oculto por defecto) ──────────────────── -->
<div id="tara-panel" class="card" style="display:none;margin-top:1.5rem;border-left:4px solid #7c3aed">
    <div class="form-section-title" style="margin-bottom:.75rem">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" style="vertical-align:middle;margin-right:.4rem"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        Tarar silo — ajustar stock real
    </div>
    <p style="font-size:.82rem;color:#6b7280;margin-bottom:1rem">
        Usa esta función cuando el stock real del silo sea diferente al calculado (por ejemplo, pienso mojado, pérdida, rotura…).
        El stock se fijará al valor que introduzcas y los días siguientes se calcularán a partir de él.
    </p>
    <form method="POST" action="<?= base_url("silos/{$silo['id']}/tarar") ?>"
          onsubmit="return confirm('¿Confirmar tara a ' + this.stock_kg.value + ' kg?')">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-2" style="max-width:600px">
            <div class="form-group">
                <label>Stock real medido (kg) *</label>
                <input type="number" name="stock_kg" required min="0" step="0.01"
                       placeholder="<?= number_format($silo['stock_actual_kg'], 0) ?>"
                       style="font-size:1.1rem;font-weight:600">
                <span class="form-hint">Stock actual calculado: <?= number_format($silo['stock_actual_kg'], 0) ?> kg</span>
            </div>
            <div class="form-group">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>">
                <span class="form-hint">Fecha en la que se mide el stock real</span>
            </div>
        </div>
        <div class="form-group" style="max-width:600px">
            <label>Motivo</label>
            <input type="text" name="motivo" placeholder="Pienso mojado por lluvia, rotura de saco, ajuste tras revisión..."
                   maxlength="255">
            <span class="form-hint">Opcional pero recomendable para trazabilidad</span>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:.75rem">
            <button type="submit" class="btn btn-primary btn-sm">Confirmar tara</button>
            <button type="button" class="btn btn-secondary btn-sm"
                    onclick="document.getElementById('tara-panel').style.display='none'">Cancelar</button>
        </div>
    </form>
</div>

<!-- ── Histórico de calibraciones ──────────────────────────── -->
<?php if (!empty($calibraciones)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="form-section-title" style="margin-bottom:.75rem">
        Histórico de taras
        <span style="font-size:.75rem;color:#9ca3af;font-weight:400;margin-left:.5rem">(<?= count($calibraciones) ?>)</span>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th style="text-align:right">Stock fijado</th>
                    <th>Motivo</th>
                    <th>Usuario</th>
                    <th>Registrado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($calibraciones as $cal): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($cal['fecha'])) ?></td>
                    <td style="text-align:right;font-weight:600"><?= number_format((float)$cal['stock_kg'], 0) ?> kg</td>
                    <td style="font-size:.82rem;color:#374151"><?= $cal['motivo'] ? e($cal['motivo']) : '<span style="color:#9ca3af">—</span>' ?></td>
                    <td style="font-size:.82rem"><?= e($cal['usuario_nombre'] ?? '—') ?></td>
                    <td style="font-size:.78rem;color:#9ca3af"><?= date('d/m/Y H:i', strtotime($cal['created_at'])) ?></td>
                    <td>
                        <?php if (es_director()): ?>
                        <form method="POST" action="<?= base_url("silos/{$silo['id']}/calibraciones/{$cal['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar esta calibración? El stock se recalculará sin ella.')"
                              style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm" style="padding:.15rem .5rem;font-size:.72rem">Eliminar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div style="margin-top:1.5rem">
    <a href="<?= base_url("almacen/{$silo['id']}") ?>" class="btn btn-primary">
        Ver en Almacén (stock, recargas, proyección)
    </a>
</div>
