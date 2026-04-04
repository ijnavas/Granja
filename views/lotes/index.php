<?php $pageTitle = 'Lotes'; ?>

<div class="page-header">
    <h2>Lotes</h2>
    <div style="display:flex;gap:.75rem;align-items:center">
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;color:#6b7280;cursor:pointer;font-weight:400">
            <input type="checkbox" id="mostrarCerrados" onchange="toggleCerrados()"
                   style="width:15px;height:15px;cursor:pointer">
            Mostrar cerrados
        </label>
        <a href="<?= base_url('lotes/crear') ?>" class="btn btn-primary">+ Nuevo lote</a>
    </div>
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
                <th>Peso real</th>
                <th>Valoración lote</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lotes as $l): ?>
            <?php
                $semana          = $l['semana_actual']        ?? null;
                $pesoTabla       = $l['peso_tabla']           ?? null;
                $costeTabla      = $l['coste_tabla']          ?? null;
                $pesoReal        = $l['peso_real_proyectado'] ?? null;
                $ultimoPesaje    = $l['ultimo_pesaje_fecha']  ?? null;
                $valoracion      = ($costeTabla && $l['num_animales']) ? $costeTabla * $l['num_animales'] : null;
                $desv = ($pesoTabla && $pesoReal)
                    ? round((($pesoReal - $pesoTabla) / $pesoTabla) * 100, 1)
                    : null;
            ?>
            <tr style="cursor:pointer<?= $l['estado'] === 'cerrado' ? ';opacity:.5' : '' ?>"
                class="fila-lote<?= $l['estado'] === 'cerrado' ? ' fila-cerrada' : '' ?>"
                onclick="window.location='<?= base_url("lotes/{$l['id']}/editar") ?>'">
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
                    <?php if ($pesoReal): ?>
                        <span style="font-weight:600;color:#1d4ed8"><?= number_format($pesoReal, 3) ?> kg</span>
                        <?php if ($desv !== null): ?>
                            <span style="font-size:.72rem;color:<?= $desv >= 0 ? '#16a34a' : '#dc2626' ?>;display:block">
                                <?= $desv >= 0 ? '+' : '' ?><?= $desv ?>% vs tabla
                            </span>
                        <?php endif; ?>
                        <span style="font-size:.7rem;color:#9ca3af;display:block">
                            Pesaje <?= date('d/m/Y', strtotime($ultimoPesaje)) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#d1d5db">Sin pesaje</span>
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
                        <a href="<?= base_url("pesajes/crear?lote_id={$l['id']}") ?>" class="btn btn-secondary btn-sm">Pesar</a>
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

<script>
// Ocultar cerrados por defecto al cargar
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".fila-cerrada").forEach(r => r.style.display = "none");
});

function toggleCerrados() {
    const mostrar = document.getElementById("mostrarCerrados").checked;
    document.querySelectorAll(".fila-cerrada").forEach(r => {
        r.style.display = mostrar ? "" : "none";
    });
}
</script>