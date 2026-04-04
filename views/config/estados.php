<?php $seccionConfig = 'estados'; require __DIR__ . '/_submenu.php'; ?>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="list-card" style="margin-bottom:2rem">
    <table class="list-table">
        <thead>
            <tr><th>Nombre</th><th>Código</th><th>Peso mínimo (kg)</th><th>Activo</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($estados as $est): ?>
            <tr>
                <td><strong><?= e($est['nombre']) ?></strong></td>
                <td><span style="font-family:monospace;background:#f3f4f6;padding:.15rem .5rem;border-radius:4px"><?= e($est['codigo']) ?></span></td>
                <td style="color:#6b7280">
                    <?= $est['peso_min_kg'] !== null ? number_format((float)$est['peso_min_kg'], 1) . ' kg' : '<span style="color:#d1d5db">—</span>' ?>
                </td>
                <td>
                    <span class="badge <?= $est['activo'] ? 'badge-activo' : 'badge-cerrado' ?>">
                        <?= $est['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("configuracion/estados/{$est['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("configuracion/estados/{$est['id']}/toggle") ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?= $est['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Nuevo estado
</div>
<div class="form-card">
    <form method="POST" action="<?= base_url('configuracion/estados') ?>">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" required placeholder="Ej: Cebo, Lechón...">
            </div>
            <div class="form-group">
                <label>Código *</label>
                <input type="text" name="codigo" required placeholder="cebo, lechon..."
                       style="font-family:monospace"
                       oninput="this.value=this.value.toLowerCase().replace(/\s+/g,'_')">
                <span class="form-hint">Sin espacios ni mayúsculas. Ej: entrada_cebo</span>
            </div>
            <div class="form-group">
                <label>Peso mínimo (kg)</label>
                <input type="number" name="peso_min_kg" min="0" step="0.1" placeholder="Ej: 22">
                <span class="form-hint">Peso a partir del cual los lechones pasan a este estado automáticamente.</span>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Crear estado</button>
        </div>
    </form>
</div>