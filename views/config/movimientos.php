<?php $seccionConfig = "movimientos"; require __DIR__ . "/_submenu.php"; ?>

<div class="page-header">
    <h2>Configuración</h2>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php
$categorias = [
    'traslado'    => 'Traslado',
    'salida'      => 'Salida',
    'entrada'     => 'Entrada',
    'venta'       => 'Venta',
    'baja'        => 'Baja',
    'transicion'  => 'Transición de estado',
    're_creacion' => 'Creación lote RE',
    're_consumo'  => 'Consumo lote RE',
];
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
    <h3 style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em">
        Tipos de movimiento
    </h3>
</div>

<div class="list-card" style="margin-bottom:2rem">
    <table class="list-table">
        <thead>
            <tr>
                <th style="width:50px">Orden</th>
                <th>Nombre</th>
                <th>Código</th>
                <th>Categoría</th>
                <th style="text-align:center">Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tipos as $t): ?>
            <tr>
                <td style="color:#9ca3af"><?= (int)$t['orden'] ?></td>
                <td>
                    <strong style="color:<?= $t['color'] ? e($t['color']) : '#374151' ?>">
                        <?= e($t['nombre']) ?>
                    </strong>
                </td>
                <td>
                    <span style="font-family:monospace;font-size:.85rem;background:#f3f4f6;padding:.15rem .5rem;border-radius:4px">
                        <?= e($t['codigo']) ?>
                    </span>
                </td>
                <td><?= e($categorias[$t['categoria']] ?? $t['categoria']) ?></td>
                <td style="text-align:center">
                    <?php if ((int)$t['activo'] === 1): ?>
                        <span class="badge badge-activo">Activo</span>
                    <?php else: ?>
                        <span class="badge" style="background:#fee2e2;color:#991b1b">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("configuracion/movimientos/{$t['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("configuracion/movimientos/{$t['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar este tipo? Los movimientos antiguos conservarán su código pero no podrá usarse para nuevos registros.')">
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

<div style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
    Nuevo tipo de movimiento
</div>

<div class="form-card">
    <form method="POST" action="<?= base_url('configuracion/movimientos') ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required placeholder="Venta cebo">
                </div>
                <div class="form-group">
                    <label>Código *</label>
                    <input type="text" name="codigo" required placeholder="venta_cebo"
                           pattern="[a-z0-9_]+"
                           style="font-family:monospace;text-transform:lowercase"
                           oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'_')">
                    <span class="form-hint">Identificador interno (minúsculas, dígitos y guiones bajos)</span>
                </div>
                <div class="form-group">
                    <label>Categoría *</label>
                    <select name="categoria" required>
                        <?php foreach ($categorias as $code => $label): ?>
                        <option value="<?= e($code) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Determina qué efecto tiene en lotes/cuadras</span>
                </div>
            </div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Orden</label>
                    <input type="number" name="orden" value="100" min="0">
                </div>
                <div class="form-group">
                    <label>Color (opcional)</label>
                    <input type="color" name="color" value="#374151" style="height:42px">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label style="display:flex;align-items:center;gap:.5rem;padding:.6rem 0">
                        <input type="checkbox" name="activo" value="1" checked>
                        <span>Activo</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Crear tipo</button>
        </div>
    </form>
</div>
