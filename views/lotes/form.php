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

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Fecha de nacimiento *</label>
                    <input type="date" name="fecha_nacimiento" id="fechaNac" required
                           value="<?= e($lote['fecha_nacimiento'] ?? date('Y-m-d')) ?>"
                           onchange="actualizarCodigo(); calcularValoracion()">
                </div>
                <div class="form-group">
                    <label>Código del lote</label>
                    <input type="text" name="codigo_manual" id="codigoManual"
                           value="<?= e($lote['codigo'] ?? '') ?>"
                           style="font-family:monospace;font-weight:700;color:#1d4ed8;font-size:1rem"
                           placeholder="L 13/26 IB">
                    <span class="form-hint">Se genera automáticamente pero puedes editarlo</span>
                </div>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Granja y especie</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Granja</label>
                    <select name="granja_id" id="granjaSelect" onchange="actualizarEspecie(this); actualizarNaves(this);">
                        <option value="">— Selecciona granja —</option>
                        <?php foreach ($granjas as $g): ?>
                            <option value="<?= $g['id'] ?>"
                                    data-especie="<?= e($g['especie'] ?? '') ?>"
                                    <?= ($lote['granja_id'] ?? $granjaPreseleccionada) == $g['id'] ? 'selected' : '' ?>>
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
                    <input type="hidden" name="especie" id="especieHidden">
                </div>
            </div>

            <!-- Tipo animal — se asigna automáticamente según la especie de la granja -->
            <input type="hidden" name="tipo_animal_id" id="tipoAnimalHidden"
                   value="<?= e($lote['tipo_animal_id'] ?? '') ?>">

            <div class="form-group" id="tipoAnimalDisplay" style="display:none">
                <label>Tipo de animal</label>
                <div style="padding:.6rem .85rem;background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:7px;font-size:.9rem;color:#374151" id="tipoAnimalTexto">—</div>
                <span class="form-hint">Asignado automáticamente según la especie de la granja</span>
            </div>

            <!-- Raza (solo porcino) -->
            <div class="form-group" id="razaGroup" style="display:none">
                <label>Raza porcina</label>
                <div class="razas-wrap">
                    <select name="raza_id" id="razaSelect" onchange="actualizarCodigo(); calcularValoracion()">
                        <option value="">— Sin especificar raza —</option>
                        <?php foreach ($razas as $r): ?>
                            <option value="<?= $r['id'] ?>"
                                    data-identificador="<?= e($r['identificador'] ?? '') ?>"
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
                <select name="nave_id" id="naveSelect" onchange="cargarCuadrasDistribucion(this.value)">
                    <option value="">— Sin asignar aún —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>"
                                data-granja="<?= e($n['granja_nombre']) ?>"
                                <?= ($lote['nave_id'] ?? '') == $n['id'] ? 'selected' : '' ?>>
                            <?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Datos de entrada</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Nº animales *</label>
                    <input type="number" name="num_animales" id="numAnimales" min="1" required
                           value="<?= e($lote['num_animales'] ?? '') ?>"
                           placeholder="200"
                           oninput="calcularPesoIndividual(); calcularValoracion(); if(cuadrasData.length) recalcularDistribucion();">
                </div>
                <div class="form-group">
                    <label>Peso medio entrada (kg)</label>
                    <input type="number" name="peso_entrada_kg" id="pesoEntrada" step="0.001" min="0"
                           value="<?= e($lote['peso_entrada_kg'] ?? '') ?>"
                           placeholder="0.000"
                           oninput="calcularPesoIndividual()">
                    <span class="form-hint" id="pesoIndividualHint">Según tabla de crecimiento</span>
                </div>
            </div>

            <!-- Valoración estimada del lote -->
            <div id="valoracionPanel" style="display:none;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;padding:1rem 1.25rem;margin-top:.5rem">
                <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#166534;margin-bottom:.5rem">
                    Valoración estimada del lote (semana actual)
                </div>
                <div style="display:flex;gap:2rem;flex-wrap:wrap">
                    <div>
                        <div style="font-size:1.5rem;font-weight:700;color:#166534" id="valorTotal">—</div>
                        <div style="font-size:.78rem;color:#15803d">Valor total del lote</div>
                    </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:600;color:#166534" id="valorPorAnimal">—</div>
                        <div style="font-size:.78rem;color:#15803d">Por animal</div>
                    </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:600;color:#166534" id="semanaTabla">—</div>
                        <div style="font-size:.78rem;color:#15803d">Semana desde destete</div>
                    </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:600;color:#166534" id="pesoTablaDisplay">—</div>
                        <div style="font-size:.78rem;color:#15803d">Peso tabla (kg)</div>
                    </div>
                </div>
            </div>

            <!-- Panel distribución en cuadras -->
            <div id="distribucionPanel" style="display:none;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:1.25rem;margin-top:1rem">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                    <span style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151">
                        Distribución en cuadras
                    </span>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="repartirIgual()">Repartir igual</button>
                    </div>
                </div>
                <div style="font-size:.78rem;color:#6b7280;margin-bottom:.75rem">
                    Desmarca las cuadras que no quieras usar. Edita la cantidad directamente si necesitas ajustar.
                </div>
                <div id="cuadrasGrid" style="display:grid;gap:.4rem"></div>
                <div id="distribucionResumen" style="margin-top:.75rem;padding:.6rem .85rem;background:#fff;border-radius:6px;border:1px solid #e5e7eb;font-size:.82rem;color:#374151"></div>
            </div>

            <!-- Campos ocultos para asignación de cuadras -->
            <div id="cuadrasHidden"></div>

            <div class="form-group" style="margin-top:1rem">
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
        <div class="form-group" style="margin-bottom:.85rem">
            <label>Porcentaje (opcional)</label>
            <input type="text" id="nuevaRazaPorcentaje" placeholder="50%, 75%, 100%..."
                   style="width:100%;padding:.6rem .85rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.9rem;font-family:inherit">
        </div>
        <div class="form-group" style="margin-bottom:1.25rem">
            <label>Identificador (opcional)</label>
            <input type="text" id="nuevaRazaIdentificador" maxlength="5"
                   placeholder="IB, DU, PT..."
                   oninput="this.value=this.value.toUpperCase()"
                   style="width:100%;padding:.6rem .85rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.9rem;font-family:monospace;font-weight:700;text-transform:uppercase">
            <span style="font-size:.75rem;color:#9ca3af">Siglas que aparecen al final del código del lote</span>
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
const tipos = <?= json_encode(array_values($tipos)) ?>;
const tiposPorGranja = <?= json_encode($tiposPorGranja) ?>;
const cuadrasAsig = <?= json_encode($cuadrasAsig ?? []) ?>;

function actualizarEspecie(sel) {
    const opt      = sel.options[sel.selectedIndex];
    const especie  = opt ? (opt.dataset.especie || '') : '';
    const granjaId = sel.value;
    const badge    = document.getElementById('especieBadge');
    const vacia    = document.getElementById('especieVacia');

    if (especie) {
        badge.textContent = especie.charAt(0).toUpperCase() + especie.slice(1);
        badge.style.display = 'inline-flex';
        vacia.style.display = 'none';
    } else {
        badge.style.display = 'none';
        vacia.style.display = '';
    }

    document.getElementById('especieHidden').value = especie;

    // Asignar tipo animal según el mapa granja → tipo resuelto en el servidor
    const hiddenTipo  = document.getElementById('tipoAnimalHidden');
    const displayTipo = document.getElementById('tipoAnimalDisplay');
    const textoTipo   = document.getElementById('tipoAnimalTexto');

    const tipoId = tiposPorGranja[granjaId] || null;
    if (tipoId) {
        hiddenTipo.value = tipoId;
        const tipoObj = tipos.find(t => t.id == tipoId);
        textoTipo.textContent = tipoObj ? tipoObj.nombre : '—';
        displayTipo.style.display = '';
    } else {
        hiddenTipo.value = '';
        displayTipo.style.display = 'none';
    }

    // Mostrar razas solo para porcino
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
        sufijo = (opt.dataset.identificador || '').trim().toUpperCase();
    }

    const codigo  = 'L ' + week + '/' + year + (sufijo ? ' ' + sufijo : '');
    const manual  = document.getElementById('codigoManual');
    // Solo actualizar si está vacío o si el usuario no lo ha modificado manualmente
    if (manual && (!manual.dataset.editado || manual.dataset.editado === 'false')) {
        manual.value = codigo;
    }
}

function calcularPesoIndividual() {
    const num   = parseFloat(document.getElementById('numAnimales').value) || 0;
    const peso  = parseFloat(document.getElementById('pesoEntrada').value) || 0;
    const hint  = document.getElementById('pesoIndividualHint');
    if (num > 0 && peso > 0) {
        const individual = (peso / num).toFixed(3);
        hint.textContent = 'Peso individual: ' + individual + ' kg/animal';
        hint.style.color = '#1d4ed8';
        hint.style.fontWeight = '600';
    } else {
        hint.textContent = 'Según tabla de crecimiento';
        hint.style.color = '';
        hint.style.fontWeight = '';
    }
}

async function calcularValoracion() {
    const razaSel  = document.getElementById('razaSelect');
    const fechaNac = document.getElementById('fechaNac');
    const numEl    = document.getElementById('numAnimales');
    const panel    = document.getElementById('valoracionPanel');

    if (!razaSel || !razaSel.value || !fechaNac || !fechaNac.value || !numEl || !numEl.value) {
        panel.style.display = 'none';
        return;
    }

    const semana = Math.ceil((new Date() - new Date(fechaNac.value)) / (7 * 24 * 3600 * 1000));
    const num    = parseInt(numEl.value) || 0;

    if (semana < 0 || num < 1) { panel.style.display = 'none'; return; }

    try {
        const res  = await fetch(`<?= base_url('lotes/tabla-semana') ?>?raza_id=${razaSel.value}&semana=${semana}`);
        const data = await res.json();

        if (data.ok && data.coste) {
            const total = (data.coste * num).toFixed(2);
            document.getElementById('valorTotal').textContent       = parseFloat(total).toLocaleString('es-ES', {minimumFractionDigits:2}) + ' €';
            document.getElementById('valorPorAnimal').textContent   = parseFloat(data.coste).toFixed(2) + ' €';
            document.getElementById('semanaTabla').textContent      = 'S' + semana;
            document.getElementById('pesoTablaDisplay').textContent = data.peso ? parseFloat(data.peso).toFixed(3) + ' kg' : '—';
            panel.style.display = '';
        } else {
            panel.style.display = 'none';
        }
    } catch(e) {
        panel.style.display = 'none';
    }
}

function getISOWeek(d) {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    date.setUTCDate(date.getUTCDate() + 4 - (date.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
}

async function guardarRaza() {
    const nombre        = document.getElementById('nuevaRazaNombre').value.trim();
    const porcentaje    = document.getElementById('nuevaRazaPorcentaje').value.trim();
    const identificador = document.getElementById('nuevaRazaIdentificador').value.trim().toUpperCase();
    const errEl         = document.getElementById('razaError');

    if (nombre.length < 2) { errEl.textContent = 'El nombre es obligatorio.'; errEl.style.display=''; return; }
    errEl.style.display = 'none';

    const fd = new FormData();
    fd.append('nombre', nombre);
    fd.append('porcentaje', porcentaje);
    fd.append('identificador', identificador);
    fd.append('csrf_token', document.querySelector('[name=csrf_token]').value);

    const res  = await fetch('<?= base_url('lotes/raza') ?>', { method:'POST', body: fd });
    const data = await res.json();

    if (!data.ok) { errEl.textContent = data.msg; errEl.style.display=''; return; }

    const sel = document.getElementById('razaSelect');
    const opt = document.createElement('option');
    opt.value = data.id;
    opt.dataset.identificador = data.identificador || '';
    opt.textContent = data.nombre + (data.porcentaje ? ' (' + data.porcentaje + ')' : '');
    opt.selected = true;
    sel.appendChild(opt);

    document.getElementById('modalRaza').style.display = 'none';
    document.getElementById('nuevaRazaNombre').value = '';
    document.getElementById('nuevaRazaPorcentaje').value = '';
    document.getElementById('nuevaRazaIdentificador').value = '';
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
    const granjasel = document.getElementById('granjaSelect');
    if (granjasel && granjasel.value) actualizarEspecie(granjasel);

    // En creación generar código inicial; en edición también actualizar si cambia
    actualizarCodigo();

    // Cargar cuadras si hay nave preseleccionada (edición)
    const navesel = document.getElementById('naveSelect');
    if (navesel && navesel.value) cargarCuadrasDistribucion(navesel.value);

    // Detectar edición manual del código
    const codigoManual = document.getElementById('codigoManual');
    if (codigoManual) {
        codigoManual.addEventListener('input', () => {
            codigoManual.dataset.editado = 'true';
        });
        // En edición solo marcamos como editado si el usuario toca el campo
        // NO lo marcamos al cargar la página
    }

    // Si solo hay una granja, ocultar el selector
    <?php if (!$esEdicion && count($granjas) === 1): ?>
    if (granjasel) granjasel.closest('.form-group').style.display = 'none';
    <?php endif; ?>

    // En edición, mostrar el tipo animal actual
    <?php if ($esEdicion && !empty($lote['tipo_animal_id'])): ?>
    const hiddenTipo  = document.getElementById('tipoAnimalHidden');
    const displayTipo = document.getElementById('tipoAnimalDisplay');
    const textoTipo   = document.getElementById('tipoAnimalTexto');
    const tipoActual  = tipos.find(t => t.id == <?= $lote['tipo_animal_id'] ?>);
    if (tipoActual) {
        textoTipo.textContent = tipoActual.nombre;
        displayTipo.style.display = '';
    }
    <?php endif; ?>
});

// ── Distribución en cuadras ──────────────────────────────────
let cuadrasData = [];

async function cargarCuadrasDistribucion(naveId) {
    const panel = document.getElementById('distribucionPanel');
    if (!naveId) { panel.style.display = 'none'; cuadrasData = []; return; }

    const res  = await fetch(`<?= base_url('movimientos/cuadras') ?>?nave_id=${naveId}`);
    cuadrasData = await res.json();

    if (!cuadrasData.length) { panel.style.display = 'none'; return; }

    panel.style.display = '';
    renderCuadras();
    recalcularDistribucion();
}

function renderCuadras() {
    const grid = document.getElementById('cuadrasGrid');
    grid.innerHTML = '';
    cuadrasData.forEach((c, i) => {
        const yaAsignado = cuadrasAsig[c.id] ?? null;
        const div = document.createElement('div');
        div.style.cssText = 'display:flex;align-items:center;gap:.75rem;padding:.5rem .75rem;background:#fff;border-radius:6px;border:1px solid #e5e7eb';
        div.innerHTML = `
            <input type="checkbox" id="cuadra_chk_${i}" checked
                   style="width:16px;height:16px;cursor:pointer"
                   onchange="alDesmarcar(${i})">
            <label for="cuadra_chk_${i}" style="flex:1;font-size:.875rem;font-weight:500;cursor:pointer;margin:0">
                ${c.nombre}
                <span style="font-size:.75rem;color:#9ca3af;font-weight:400"> · Cap. ${c.capacidad_maxima || '—'}</span>
                ${c.lotes ? `<span style="font-size:.75rem;color:#d97706;font-weight:400"> · ${c.lotes}</span>` : ''}
            </label>
            <input type="number" id="cuadra_num_${i}" min="0"
                   value="${yaAsignado !== null ? yaAsignado : ''}"
                   placeholder="0"
                   style="width:70px;padding:.35rem .5rem;border:1.5px solid #d1d5db;border-radius:6px;font-size:.875rem;font-family:inherit;text-align:right"
                   oninput="alEditarCuadra()">
        `;
        grid.appendChild(div);
    });
}

function alDesmarcar(idx) {
    const chk = document.getElementById(`cuadra_chk_${idx}`);
    const num = document.getElementById(`cuadra_num_${idx}`);
    if (!chk.checked) {
        // Guardar valor anterior y poner a 0
        num.dataset.prev = num.value;
        num.value = '0';
        num.disabled = true;
        num.style.background = '#f3f4f6';
        num.style.color = '#9ca3af';
    } else {
        num.disabled = false;
        num.style.background = '';
        num.style.color = '';
        num.value = num.dataset.prev || '';
    }
    recalcularResumen();
}

function alEditarCuadra() {
    recalcularResumen();
}

function recalcularDistribucion() {
    const numAnimales = parseInt(document.getElementById('numAnimales').value) || 0;
    if (!numAnimales || !cuadrasData.length) return;

    const seleccionadas = cuadrasData
        .map((c, i) => ({ ...c, idx: i }))
        .filter((c, i) => document.getElementById(`cuadra_chk_${i}`)?.checked);

    if (!seleccionadas.length) return;

    let restantes = numAnimales;
    seleccionadas.forEach(c => {
        const numEl = document.getElementById(`cuadra_num_${c.idx}`);
        if (restantes <= 0) {
            numEl.value = '0';
            return;
        }
        const cap  = c.capacidad_maxima || 9999;
        const asig = Math.min(restantes, cap);
        restantes -= asig;
        numEl.value = asig;
    });

    recalcularResumen();
}

function recalcularResumen() {
    const numAnimales = parseInt(document.getElementById('numAnimales').value) || 0;
    let totalAsignado = 0;
    const asignaciones = [];

    cuadrasData.forEach((c, i) => {
        const chk = document.getElementById(`cuadra_chk_${i}`);
        const num = parseInt(document.getElementById(`cuadra_num_${i}`)?.value) || 0;
        if (chk?.checked && num > 0) {
            totalAsignado += num;
            asignaciones.push({ cuadra_id: c.id, cantidad: num });
        }
    });

    const resumen = document.getElementById('distribucionResumen');
    const diff = numAnimales - totalAsignado;
    if (diff > 0) {
        resumen.innerHTML = `<span style="color:#d97706">⚠ Faltan <strong>${diff}</strong> animales por asignar (${totalAsignado} de ${numAnimales})</span>`;
    } else if (diff < 0) {
        resumen.innerHTML = `<span style="color:#dc2626">⚠ Exceso de <strong>${Math.abs(diff)}</strong> animales (${totalAsignado} de ${numAnimales})</span>`;
    } else if (totalAsignado > 0) {
        resumen.innerHTML = `<span style="color:#16a34a">✓ ${totalAsignado} animales distribuidos en ${asignaciones.length} cuadra${asignaciones.length !== 1 ? 's' : ''}</span>`;
    } else {
        resumen.innerHTML = '';
    }

    actualizarCamposOcultos(asignaciones);
}

function repartirIgual() {
    const numAnimales   = parseInt(document.getElementById('numAnimales').value) || 0;
    const seleccionadas = cuadrasData.filter((c, i) => document.getElementById(`cuadra_chk_${i}`)?.checked);
    if (!seleccionadas.length || !numAnimales) return;
    // Limpiar valores anteriores
    cuadrasData.forEach((c, i) => {
        const numEl = document.getElementById(`cuadra_num_${i}`);
        if (numEl) numEl.value = '';
    });
    recalcularDistribucion();
}
</script>