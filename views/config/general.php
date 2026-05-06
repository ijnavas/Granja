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
