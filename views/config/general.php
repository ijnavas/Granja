<?php $seccionConfig = "general"; require __DIR__ . "/_submenu.php"; ?>

<div class="page-header">
    <h2>Configuración</h2>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Avisos en movimientos
</div>

<div class="form-card" style="max-width:680px">
    <form method="POST" action="<?= base_url('configuracion/general') ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Días para advertencia de fecha del movimiento</label>
                <input type="number" name="dias_advertencia_movimiento" min="0" max="365"
                       value="<?= (int)$config['dias_advertencia_movimiento'] ?>" required>
                <span class="form-hint">
                    Si la fecha del movimiento dista más de N días respecto a hoy, se mostrará una advertencia
                    al guardar (no bloquea, solo avisa). 0 desactiva el aviso.
                </span>
            </div>

            <div class="form-group">
                <label>Desviación máxima del peso vs tabla (%)</label>
                <input type="number" name="pct_desviacion_peso_tabla" min="0" max="100"
                       value="<?= (int)$config['pct_desviacion_peso_tabla'] ?>" required>
                <span class="form-hint">
                    En ventas y bajas, si el peso introducido se aleja más del N% del peso esperado por la
                    tabla de crecimiento, se mostrará una advertencia. 0 desactiva el aviso.
                </span>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar configuración</button>
        </div>
    </form>
</div>

<!-- ────────────────────────────────────────────────────────────── -->
<!-- Motivos de baja                                                 -->
<!-- ────────────────────────────────────────────────────────────── -->
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin:2rem 0 .75rem">
    Motivos de baja
</div>

<div class="list-card" style="margin-bottom:1rem;max-width:680px">
    <table class="list-table">
        <thead>
            <tr>
                <th style="width:50px">Orden</th>
                <th>Nombre</th>
                <th>Código</th>
                <th style="text-align:center">Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($motivos ?? []) as $m): ?>
            <tr>
                <td style="color:#9ca3af"><?= (int)$m['orden'] ?></td>
                <td><strong><?= e($m['nombre']) ?></strong></td>
                <td>
                    <span style="font-family:monospace;font-size:.85rem;background:#f3f4f6;padding:.15rem .5rem;border-radius:4px">
                        <?= e($m['codigo']) ?>
                    </span>
                </td>
                <td style="text-align:center">
                    <?php if ((int)$m['activo'] === 1): ?>
                        <span class="badge badge-activo">Activo</span>
                    <?php else: ?>
                        <span class="badge" style="background:#fee2e2;color:#991b1b">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions">
                        <form method="POST" action="<?= base_url("configuracion/motivos/{$m['id']}/actualizar") ?>" style="display:flex;gap:.4rem;align-items:center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="codigo" value="<?= e($m['codigo']) ?>">
                            <input type="hidden" name="nombre" value="<?= e($m['nombre']) ?>">
                            <input type="hidden" name="orden"  value="<?= (int)$m['orden'] ?>">
                            <?php if ((int)$m['activo'] === 1): ?>
                                <button type="submit" class="btn btn-secondary btn-sm" title="Desactivar">Desactivar</button>
                            <?php else: ?>
                                <button type="submit" name="activo" value="1" class="btn btn-secondary btn-sm">Activar</button>
                            <?php endif; ?>
                        </form>
                        <form method="POST" action="<?= base_url("configuracion/motivos/{$m['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar este motivo? Las bajas que ya lo usan conservarán el código.')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="form-card" style="max-width:680px">
    <form method="POST" action="<?= base_url('configuracion/motivos') ?>">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-3">
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" required placeholder="Aplastamiento">
            </div>
            <div class="form-group">
                <label>Código *</label>
                <input type="text" name="codigo" required placeholder="aplastamiento"
                       pattern="[a-z0-9_]+" style="font-family:monospace;text-transform:lowercase"
                       oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'_')">
            </div>
            <div class="form-group">
                <label>Orden</label>
                <input type="number" name="orden" value="100" min="0">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Añadir motivo</button>
        </div>
    </form>
</div>
