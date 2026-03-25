<?php
$esEdicion = !is_null($lote);
$action    = $esEdicion ? base_url("lotes/{$lote['id']}/actualizar") : base_url('lotes');
?>

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('lotes') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= $action ?>" id="formLote">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section-title">Identificación</div>

            <?php if (!$esEdicion): ?>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Fecha de nacimiento *</label>
                    <input type="date" name="fecha_nacimiento" id="fechaNac" required
                           value="<?= date('Y-m-d') ?>"
                           onchange="actualizarCodigo()">
                </div>
                <div class="form-group">
                    <label>Código generado</label>
                    <input type="text" id="codigoPreview" disabled
                           style="background:#f9fafb;font-family:monospace;font-weight:700;color:#1d4ed8;font-size:1rem">
                    <span class="form-hint">Se genera automáticamente</span>
                </div>
            </div>
            <?php else: ?>
            <div class="form-group" style="max-width:220px">
                <label>Código</label>
                <input type="text" value="<?= e($lote['codigo']) ?>" disabled
                       style="background:#f9fafb;font-family:monospace;font-weight:700;color:#1d4ed8;font-size:1rem">
            </div>
            <?php endif; ?>

            <div class="form-section-title" style="margin-top:.5rem">Granja y especie</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Granja</label>
                    <select name="granja_id" id="granjaSelect" onchange="actualizarEspecie(this); actualizarNaves(this);">
                        <option value="">— Selecciona granja —</option>
                        <?php foreach ($granjas as $g): ?>
                            <option value="<?= $g['id'] ?>"
                                    data-especie="<?= e($g['especie'] ?? '') ?>"
                                    <?= ($lote['granja_id'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                                <?= e($g['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Especie</label>
                    <div id="especieDisplay" style="padding:.6rem 0">
                        <span id="especieBadge" class="especie-badge" style="display:none"></span>
                        <span id="especieVacia" style="color:#9ca3af;font-size:.875rem">Selecciona una granja</span>
                    </div>
                    <!-- Campo oculto para enviar especie -->
                    <input type="hidden" name="especie" id="especieHidden">
                    <input type="hidden" name="tipo_animal_id" id="tipoAnimalHidden">
                </div>
            </div>

            <!-- Raza (solo porcino) -->
            <div class="form-group" id="razaGroup" style="display:none">
                <label>Raza porcina</label>
                <div class="razas-wrap">
                    <select name="raza_id" id="razaSelect" onchange="actualizarCodigo()">
                        <option value="">— Sin especificar raza —</option>
                        <?php foreach ($razas as $r): ?>
                            <option value="<?= $r['id'] ?>"
                                    data-sufijo="<?= e(strtoupper(substr($r['nombre'], 0, 2))) ?>"
                                    <?= ($lote['raza_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                                <?= e($r['nombre']) ?><?= $r['porcentaje'] ? ' (' . $r['porcentaje'] . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-nueva-raza" onclick="document.getElementById('modalRaza').style.display='flex'">
                        + Nueva raza
                    </button>
                </div>
                <span class="form-hint">Solo para granjas porcinas</span>
            </div>

            <div class="form-group">
                <label>Nave (opcional)</label>
                <select name="nave_id" id="naveSelect">
                    <option value="">— Sin asignar aún —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>" <?= ($lote['nave_id'] ?? '') == $n['id'] ? 'selected' : '' ?>>
                            <?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Datos de entrada</div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Nº animales *</label>
                    <input type="number" name="num_animales" min="1" required
                           value="<?= e($lote['num_animales'] ?? '') ?>" placeholder="200">
                </div>
                <div class="form-group">
                    <label>Peso medio entrada (kg)</label>
                    <input type="number" name="peso_entrada_kg" step="0.001" min="0"
                           value="<?= e($lote['peso_entrada_kg'] ?? '') ?>" placeholder="6.500">
                    <span class="form-hint">Según tabla de crecimiento</span>
                </div>
                <div class="form-group">
                    <label>Fecha entrada granja</label>
                    <input type="date" name="fecha_entrada"
                           value="<?= e($lote['fecha_entrada'] ?? date('Y-m-d')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones"><?= e($lote['observaciones'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear lote' ?></button>
            <a href="<?= base_url('lotes') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<!-- Modal nueva raza -->
<div id="modalRaza" class="modal-overlay">
    <div class="modal-box">
        <h3>Nueva raza porcina</h3>
        <div class="form-group" style="margin-bottom:.85rem">
            <label>Nombre *</label>
            <input type="text" id="nuevaRazaNombre" placeholder="Ej: 100% Ibérico, Duroc..."
                   style="width:100%;padding:.6rem .85rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.9rem;font-family:inherit">
        </div>
        <div class="form-group" style="margin-bottom:1.25rem">
            <label>Porcentaje (opcional)</label>
            <input type="text" id="nuevaRazaPorcentaje" placeholder="50%, 75%, 100%..."
                   style="width:100%;padding:.6rem .85rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.9rem;font-family:inherit">
        </div>
        <div style="display:flex;gap:.75rem">
            <button type="button" class="btn btn-primary" onclick="guardarRaza()">Guardar</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalRaza').style.display='none'">Cancelar</button>
        </div>
        <p id="razaError" style="color:#dc2626;font-size:.82rem;margin-top:.5rem;display:none"></p>
    </div>
</div>

<!-- Datos de granjas para JS -->
<script>
const granjas = <?= json_encode(array_values($granjas)) ?>;
const navesPorGranja = {};

function actualizarEspecie(sel) {
    const opt     = sel.options[sel.selectedIndex];
    const especie = opt ? (opt.dataset.especie || '') : '';
    const badge   = document.getElementById('especieBadge');
    const vacia   = document.getElementById('especieVacia');

    if (especie) {
        badge.textContent = especie.charAt(0).toUpperCase() + especie.slice(1);
        badge.style.display = 'inline-flex';
        vacia.style.display = 'none';
    } else {
        badge.style.display = 'none';
        vacia.style.display = '';
    }

    document.getElementById('especieHidden').value = especie;
    const razaGroup = document.getElementById('razaGroup');
    razaGroup.style.display = especie === 'porcino' ? '' : 'none';
    actualizarCodigo();
}

function actualizarNaves(sel) {
    const granjaId = sel.value;
    const navesSel = document.getElementById('naveSelect');
    const actual   = navesSel.value;
    // Filtrar opciones de nave por granja (si hay datos)
    // Por simplicidad, mantener todas las naves — filtrado avanzado se puede añadir
}

function actualizarCodigo() {
    const fn = document.getElementById('fechaNac');
    if (!fn || !fn.value) return;

    const d    = new Date(fn.value);
    const year = String(d.getFullYear()).slice(-2);
    const week = String(getISOWeek(d)).padStart(2, '0');

    const razaSel = document.getElementById('razaSelect');
    let sufijo = '';
    if (razaSel && razaSel.value) {
        const opt = razaSel.options[razaSel.selectedIndex];
        const nombre = opt.text.split('(')[0].trim();
        sufijo = nombre.substring(0, 2).toUpperCase();
    }

    const codigo = 'L ' + year + '/' + week + (sufijo ? ' ' + sufijo : '');
    const preview = document.getElementById('codigoPreview');
    if (preview) preview.value = codigo;
}

function getISOWeek(d) {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    date.setUTCDate(date.getUTCDate() + 4 - (date.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
}

async function guardarRaza() {
    const nombre     = document.getElementById('nuevaRazaNombre').value.trim();
    const porcentaje = document.getElementById('nuevaRazaPorcentaje').value.trim();
    const errEl      = document.getElementById('razaError');

    if (nombre.length < 2) { errEl.textContent = 'El nombre es obligatorio.'; errEl.style.display=''; return; }
    errEl.style.display = 'none';

    const fd = new FormData();
    fd.append('nombre', nombre);
    fd.append('porcentaje', porcentaje);
    fd.append('csrf_token', document.querySelector('[name=csrf_token]').value);

    const res  = await fetch('<?= base_url('lotes/raza') ?>', { method:'POST', body: fd });
    const data = await res.json();

    if (!data.ok) { errEl.textContent = data.msg; errEl.style.display=''; return; }

    const sel = document.getElementById('razaSelect');
    const opt = document.createElement('option');
    opt.value = data.id;
    opt.textContent = data.nombre + (data.porcentaje ? ' (' + data.porcentaje + ')' : '');
    opt.selected = true;
    sel.appendChild(opt);

    document.getElementById('modalRaza').style.display = 'none';
    document.getElementById('nuevaRazaNombre').value = '';
    document.getElementById('nuevaRazaPorcentaje').value = '';
    actualizarCodigo();
}

// Autocapitalizar
document.querySelectorAll('input[type=text], textarea').forEach(el => {
    el.addEventListener('blur', function() {
        if (this.value.length > 0) {
            this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    actualizarCodigo();
    const granjasel = document.getElementById('granjaSelect');
    if (granjasel && granjasel.value) actualizarEspecie(granjasel);
});
</script>
