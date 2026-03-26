<?php $pageTitle = 'Lotes'; ?>

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
                <th>Granja / Nave</th>
                <th>Animales</th>
                <th>Semana</th>
                <th>Peso tabla</th>
                <th>Valoración lote</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lotes as $l): ?>
            <?php
                $semana     = $l['semana_actual'] ?? null;
                $pesoTabla  = $l['peso_tabla']    ?? null;
                $costeTabla = $l['coste_tabla']   ?? null;
                $valoracion = ($costeTabla && $l['num_animales']) ? $costeTabla * $l['num_animales'] : null;
            ?>
            <tr style="cursor:pointer" onclick="window.location='<?= base_url("lotes/{$l['id']}/editar") ?>'">
                <td>
                    <strong style="font-family:monospace"><?= e($l['codigo']) ?></strong>
                    <?php if ($l['raza_nombre']): ?>
                        <div style="font-size:.75rem;color:#9ca3af"><?= e($l['raza_nombre']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($l['nave_nombre']): ?>
                        <?= e($l['nave_nombre']) ?>
                        <span style="color:#9ca3af;font-size:.8rem"> · <?= e($l['granja_nombre'] ?? '') ?></span>
                    <?php elseif ($l['granja_nombre']): ?>
                        <span style="color:#9ca3af"><?= e($l['granja_nombre']) ?></span>
                    <?php else: ?>
                        <span style="color:#d1d5db;font-style:italic">Sin asignar</span>
                    <?php endif; ?>
                </td>
                <td><?= number_format($l['num_animales']) ?></td>
                <td>
                    <?php if ($semana !== null): ?>
                        <span style="font-weight:600">S<?= $semana ?></span>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($pesoTabla): ?>
                        <?= number_format($pesoTabla, 3) ?> kg
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($valoracion): ?>
                        <div style="font-weight:600"><?= number_format($valoracion, 2) ?> €</div>
                        <div style="font-size:.75rem;color:#9ca3af"><?= number_format($costeTabla, 2) ?> €/animal</div>
                    <?php else: ?>
                        <span style="color:#d1d5db">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= e($l['estado']) ?>"><?= e($l['estado']) ?></span></td>
                <td onclick="event.stopPropagation()">
                    <div class="actions">
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