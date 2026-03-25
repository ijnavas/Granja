<?php $pageTitle = 'Lotes'; ?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">

<div class="page-header">
    <h2>Lotes</h2>
    <a href="<?= base_url('lotes/crear') ?>" class="btn btn-primary">+ Nuevo lote</a>
</div>

<?php if ($flash = \App\Core\Session::getFlash('success')): ?>
    <div class="alert-flash alert-success"><?= $flash ?></div>
<?php endif; ?>

<div class="list-card">
<?php if (empty($lotes)): ?>
    <div class="empty-state">No hay lotes. <a href="<?= base_url('lotes/crear') ?>">Crea el primero</a>.</div>
<?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Especie</th>
                <th>Nave / Granja</th>
                <th>Animales</th>
                <th>Días</th>
                <th>Último peso</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lotes as $l): ?>
            <tr>
                <td><strong style="font-family:monospace"><?= e($l['codigo']) ?></strong></td>
                <td><span class="badge badge-<?= e($l['especie']) ?>"><?= e($l['tipo_animal_nombre']) ?></span></td>
                <td>
                    <?php if ($l['nave_nombre']): ?>
                        <?= e($l['nave_nombre']) ?>
                        <span style="color:#9ca3af;font-size:.8rem"> · <?= e($l['granja_nombre'] ?? '') ?></span>
                    <?php else: ?>
                        <span style="color:#9ca3af;font-style:italic">Sin asignar</span>
                    <?php endif; ?>
                </td>
                <td><?= number_format($l['num_animales']) ?></td>
                <td><?= $l['dias_en_granja'] ?></td>
                <td><?= $l['ultimo_peso'] ? number_format($l['ultimo_peso'], 2) . ' kg' : '—' ?></td>
                <td><span class="badge badge-<?= e($l['estado']) ?>"><?= e($l['estado']) ?></span></td>
                <td>
                    <div class="actions">
                        <!-- Ajustar animales -->
                        <button onclick="abrirAjuste(<?= $l['id'] ?>, <?= $l['num_animales'] ?>)" class="btn btn-secondary btn-sm">+/−</button>
                        <a href="<?= base_url("lotes/{$l['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("lotes/{$l['id']}/eliminar") ?>" onsubmit="return confirm('¿Cerrar este lote?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Cerrar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<!-- Modal ajuste de animales -->
<div id="modalAjuste" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:12px;padding:2rem;width:100%;max-width:360px;box-shadow:0 8px 32px rgba(0,0,0,.2)">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem">Ajustar animales del lote</h3>
        <form method="POST" id="formAjuste">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom:1rem">
                <label>Animales actuales</label>
                <input type="number" id="actualesInfo" disabled style="background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:7px;padding:.6rem .85rem;font-size:.9rem;font-family:inherit;color:#374151">
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label>Operación</label>
                <select name="tipo" style="padding:.6rem .85rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.9rem;font-family:inherit">
                    <option value="añadir">Añadir animales</option>
                    <option value="reducir">Reducir animales</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem">
                <label>Cantidad</label>
                <input type="number" name="cantidad" min="1" required value="1" style="padding:.6rem .85rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.9rem;font-family:inherit">
            </div>
            <div style="display:flex;gap:.75rem">
                <button type="submit" class="btn btn-primary">Confirmar</button>
                <button type="button" onclick="cerrarAjuste()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirAjuste(id, actuales) {
    document.getElementById('formAjuste').action = '<?= base_url('lotes/') ?>' + id + '/ajustar';
    document.getElementById('actualesInfo').value = actuales;
    document.getElementById('modalAjuste').style.display = 'flex';
}
function cerrarAjuste() {
    document.getElementById('modalAjuste').style.display = 'none';
}
</script>
