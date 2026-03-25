<?php $seccionConfig = "razas"; require __DIR__ . "/_submenu.php"; ?>

<div class="page-header">
    <h2>Configuración</h2>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- ── Razas porcinas ── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
    <h3 style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em">
        Razas porcinas
    </h3>
</div>

<div class="list-card" style="margin-bottom:2rem">
    <table class="list-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Porcentaje</th>
                <th>Identificador</th>
                <th>Origen</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($razas as $r): ?>
            <tr>
                <td><strong><?= e($r['nombre']) ?></strong></td>
                <td><?= e($r['porcentaje'] ?? '—') ?></td>
                <td>
                    <?php if ($r['identificador']): ?>
                        <span style="font-family:monospace;font-size:.9rem;font-weight:700;background:#f3f4f6;padding:.15rem .5rem;border-radius:4px">
                            <?= e($r['identificador']) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['usuario_id'] === null): ?>
                        <span class="badge badge-activo">Sistema</span>
                    <?php else: ?>
                        <span class="badge" style="background:#e0f2fe;color:#0369a1">
                            <?= e($r['creador'] ?? 'Usuario') ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("configuracion/razas/{$r['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("configuracion/razas/{$r['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar esta raza?')">
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

<!-- ── Formulario nueva raza ── -->
<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Nueva raza porcina
</div>

<div class="form-card">
    <form method="POST" action="<?= base_url('configuracion/razas') ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required placeholder="100% Ibérico">
                </div>
                <div class="form-group">
                    <label>Porcentaje</label>
                    <input type="text" name="porcentaje" placeholder="100%, 50%...">
                </div>
                <div class="form-group">
                    <label>Identificador</label>
                    <input type="text" name="identificador" maxlength="5"
                           placeholder="IB, DU..."
                           style="text-transform:uppercase"
                           oninput="this.value=this.value.toUpperCase()">
                    <span class="form-hint">Siglas para el código del lote. Opcional.</span>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Crear raza</button>
        </div>
    </form>
</div>