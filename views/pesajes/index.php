<div class="page-header">
    <h2>Pesajes</h2>
    <a href="<?= base_url('pesajes/crear') ?>" class="btn btn-primary">+ Nuevo pesaje</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="list-card" style="overflow-x:auto">
<?php if (empty($pesajes)): ?>
    <div class="empty-state">No hay pesajes registrados. <a href="<?= base_url('pesajes/crear') ?>">Registra el primero</a>.</div>
<?php else: ?>
    <table class="list-table" style="white-space:nowrap">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Lote · Nave</th>
                <th style="text-align:center">Sem.</th>
                <th style="text-align:right">Animales</th>
                <th style="text-align:right">Peso real (pesaje)</th>
                <th style="text-align:right">Peso tabla (ese día)</th>
                <th style="text-align:right">Peso real proyectado hoy</th>
                <th style="text-align:right">Peso tabla hoy</th>
                <th style="text-align:right">IC real</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pesajes as $p): ?>
            <?php
                $desv = null;
                if ($p['peso_tabla_pesaje'] && $p['peso_tabla_pesaje'] > 0) {
                    $desv = round((($p['peso_medio_kg'] - $p['peso_tabla_pesaje']) / $p['peso_tabla_pesaje']) * 100, 1);
                }
            ?>
            <tr>
                <td style="white-space:nowrap"><strong><?= date('d/m/Y', strtotime($p['fecha'])) ?></strong></td>
                <td style="white-space:nowrap">
                    <span style="font-family:monospace;font-weight:600;color:#1d4ed8"><?= e($p['lote_codigo']) ?></span>
                    <?php if ($p['nave_nombre'] ?? $p['granja_nombre'] ?? null): ?>
                        <span style="color:#9ca3af;font-size:.8rem"> · <?= e($p['nave_nombre'] ?? $p['granja_nombre']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;color:#6b7280">
                    <?= $p['semana_pesaje'] ? 'S' . $p['semana_pesaje'] : '—' ?>
                </td>
                <td style="text-align:right"><?= number_format($p['num_animales_pesados']) ?></td>
                <td style="text-align:right;font-weight:600;white-space:nowrap">
                    <?= number_format((float)$p['peso_medio_kg'], 3) ?> kg
                    <?php if ($desv !== null): ?>
                        <span style="font-size:.72rem;color:<?= $desv >= 0 ? '#16a34a' : '#dc2626' ?>">
                            <?= $desv >= 0 ? '+' : '' ?><?= $desv ?>%
                        </span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;color:#6b7280;white-space:nowrap">
                    <?= $p['peso_tabla_pesaje'] ? number_format((float)$p['peso_tabla_pesaje'], 3) . ' kg' : '—' ?>
                </td>
                <td style="text-align:right;font-weight:600;color:#1d4ed8;white-space:nowrap">
                    <?= $p['peso_proyectado_hoy'] ? number_format((float)$p['peso_proyectado_hoy'], 3) . ' kg' : '—' ?>
                </td>
                <td style="text-align:right;color:#6b7280;white-space:nowrap">
                    <?= $p['peso_tabla_hoy'] ? number_format((float)$p['peso_tabla_hoy'], 3) . ' kg' : '—' ?>
                </td>
                <td style="text-align:right;color:#6b7280">
                    <?= $p['ic_real'] ? number_format((float)$p['ic_real'], 3) : '—' ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="<?= base_url("pesajes/{$p['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
                        <form method="POST" action="<?= base_url("pesajes/{$p['id']}/eliminar") ?>"
                              onsubmit="return confirm('¿Eliminar este pesaje?')">
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
