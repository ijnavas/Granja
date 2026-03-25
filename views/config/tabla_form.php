<?php
$esEdicion = !is_null($tabla);
$action    = $esEdicion
    ? base_url("configuracion/tablas/{$tabla['id']}/actualizar")
    : base_url('configuracion/tablas');
$seccionConfig = 'tablas';
require __DIR__ . '/_submenu.php';
?>

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('configuracion/tablas') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= $action ?>">
    <?= csrf_field() ?>

    <!-- Cabecera tabla -->
    <div class="form-card" style="max-width:100%;margin-bottom:1.5rem">
        <div class="form-grid">
            <div class="form-section-title">Datos generales</div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Nombre de la tabla *</label>
                    <input type="text" name="nombre" required
                           value="<?= e($tabla['nombre'] ?? '') ?>"
                           placeholder="Ej: Ibérico extensivo 2024">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion"
                           value="<?= e($tabla['descripcion'] ?? '') ?>"
                           placeholder="Notas sobre esta tabla...">
                </div>
            </div>

            <div class="form-group">
                <label>Razas asignadas</label>
                <div class="checkbox-list">
                    <?php if (empty($razas)): ?>
                        <span style="font-size:.82rem;color:#9ca3af">No hay razas disponibles</span>
                    <?php else: ?>
                        <?php foreach ($razas as $r): ?>
                            <label>
                                <input type="checkbox" name="raza_ids[]" value="<?= $r['id'] ?>"
                                    <?= in_array($r['id'], $razasAsig) ? 'checked' : '' ?>>
                                <?= e($r['nombre']) ?><?= $r['porcentaje'] ? ' (' . $r['porcentaje'] . ')' : '' ?>
                                <?= $r['identificador'] ? '<span style="font-family:monospace;font-size:.75rem;background:#f3f4f6;padding:.1rem .4rem;border-radius:3px;margin-left:.3rem">' . e($r['identificador']) . '</span>' : '' ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Editor de líneas -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
        <span style="font-size:.875rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em">
            Semanas (<?= count($lineas) ?> filas)
        </span>
        <div style="display:flex;gap:.5rem">
            <button type="button" class="btn btn-secondary btn-sm" onclick="añadirFila()">+ Añadir fila</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="añadirBloque(10)">+ 10 filas</button>
        </div>
    </div>

    <div class="list-card" style="margin-bottom:1.5rem;overflow:visible">
        <table class="list-table" id="tablaLineas">
            <thead>
                <tr>
                    <th style="width:80px">Semana</th>
                    <th style="width:130px">Peso (kg)</th>
                    <th style="width:160px">Consumo acum. (g)</th>
                    <th style="width:130px">Coste (€)</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody id="lineasBody">
                <?php foreach ($lineas as $i => $l): ?>
                <tr>
                    <td><input type="number" name="semana[]" value="<?= $l['semana'] ?>" min="1" class="input-linea" required></td>
                    <td><input type="number" name="peso[]" value="<?= $l['peso_kg'] ?>" step="0.001" min="0" class="input-linea"></td>
                    <td><input type="number" name="consumo[]" value="<?= $l['consumo_acumulado_g'] ?? '' ?>" min="0" class="input-linea" placeholder="—"></td>
                    <td><input type="number" name="coste[]" value="<?= $l['coste_eur'] ?? '' ?>" step="0.01" min="0" class="input-linea" placeholder="—"></td>
                    <td><button type="button" onclick="eliminarFila(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem .4rem">×</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear tabla' ?></button>
        <a href="<?= base_url('configuracion/tablas') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<style>
.input-linea {
    width: 100%;
    padding: .35rem .6rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 6px;
    font-size: .875rem;
    font-family: inherit;
    outline: none;
    background: #fff;
}
.input-linea:focus { border-color: #1d4ed8; }
#tablaLineas td { padding: .3rem .75rem; }
</style>

<script>
let nextSemana = <?= $lineas ? (max(array_column($lineas, 'semana')) + 1) : 1 ?>;

function crearFila(semana) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="number" name="semana[]" value="${semana}" min="1" class="input-linea" required></td>
        <td><input type="number" name="peso[]" step="0.001" min="0" class="input-linea"></td>
        <td><input type="number" name="consumo[]" min="0" class="input-linea" placeholder="—"></td>
        <td><input type="number" name="coste[]" step="0.01" min="0" class="input-linea" placeholder="—"></td>
        <td><button type="button" onclick="eliminarFila(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem .4rem">×</button></td>
    `;
    document.getElementById('lineasBody').appendChild(tr);
    return semana + 1;
}

function añadirFila() {
    nextSemana = crearFila(nextSemana);
}

function añadirBloque(n) {
    for (let i = 0; i < n; i++) {
        nextSemana = crearFila(nextSemana);
    }
}

function eliminarFila(btn) {
    btn.closest('tr').remove();
}

// Autocapitalizar nombre
document.querySelector('[name=nombre]')?.addEventListener('blur', function() {
    if (this.value) this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
});
</script>