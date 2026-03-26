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
            Semanas (<span id="contadorFilas"><?= count($lineas) ?></span> filas)
        </span>
        <div style="display:flex;gap:.5rem">
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleImport()">⬆ Importar Excel</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="añadirFila()">+ Añadir fila</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="añadirBloque(10)">+ 10 filas</button>
        </div>
    </div>

    <!-- Panel importación Excel -->
    <div id="importPanel" style="display:none;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:1.25rem;margin-bottom:1rem">
        <div style="font-size:.8rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem">
            Pegar datos desde Excel
        </div>
        <p style="font-size:.82rem;color:#6b7280;margin-bottom:.75rem">
            Copia las columnas de tu Excel (Ctrl+C) y pégalas aquí. Luego asigna qué columna corresponde a cada campo.
            La columna <strong>Semana</strong> siempre debe ser la primera columna que pegues.
        </p>
        <div class="form-group" style="margin-bottom:.75rem">
            <label style="font-size:.8rem;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.04em">Pegar datos (Ctrl+V aquí)</label>
            <textarea id="pasteArea" rows="6"
                      placeholder="Pega aquí los datos copiados de Excel..."
                      style="width:100%;padding:.6rem .85rem;border:1.5px solid #d1d5db;border-radius:7px;font-size:.82rem;font-family:monospace;resize:vertical"
                      oninput="analizarPegado()"></textarea>
        </div>
        <div id="mapeoColumnas" style="display:none">
            <div style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.5rem">Asignar columnas detectadas:</div>
            <div id="mapeoGrid" style="display:grid;gap:.5rem;margin-bottom:.75rem"></div>
            <button type="button" class="btn btn-primary btn-sm" onclick="aplicarImportacion()">Aplicar importación</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleImport()">Cancelar</button>
        </div>
        <div id="previewInfo" style="font-size:.82rem;color:#6b7280;margin-top:.5rem"></div>
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

// Actualizar contador de filas
function actualizarContador() {
    const n = document.getElementById('lineasBody').querySelectorAll('tr').length;
    document.getElementById('contadorFilas').textContent = n;
}

// Importación desde Excel
let datosImport = [];

function toggleImport() {
    const p = document.getElementById('importPanel');
    p.style.display = p.style.display === 'none' ? '' : 'none';
}

function analizarPegado() {
    const texto = document.getElementById('pasteArea').value.trim();
    if (!texto) {
        document.getElementById('mapeoColumnas').style.display = 'none';
        return;
    }

    const filas = texto.split('\n').filter(f => f.trim());
    datosImport = filas.map(f => f.split('\t').map(c => c.trim()));

    const numCols = Math.max(...datosImport.map(f => f.length));
    document.getElementById('previewInfo').textContent =
        `${filas.length} filas detectadas, ${numCols} columnas por fila.`;

    // Generar selectores de mapeo para columnas 2 en adelante (col 0 = semana siempre)
    const grid = document.getElementById('mapeoGrid');
    grid.innerHTML = '';
    grid.style.gridTemplateColumns = `repeat(${Math.min(numCols, 5)}, 1fr)`;

    const opciones = ['— ignorar —', 'peso_kg', 'consumo_g', 'coste_eur'];
    const etiquetas = ['Semana (fija)', 'Peso (kg)', 'Consumo acum. (g)', 'Coste (€)'];
    const defaults  = ['semana', 'peso_kg', 'consumo_g', 'coste_eur'];

    for (let i = 0; i < numCols; i++) {
        const div = document.createElement('div');
        div.style.cssText = 'display:flex;flex-direction:column;gap:.25rem';

        const ejemplo = datosImport.slice(0, 3).map(f => f[i] || '').join(', ');
        div.innerHTML = `
            <label style="font-size:.75rem;font-weight:600;color:#6b7280">Col ${i+1}: ${ejemplo}...</label>
            <select id="col_${i}" style="padding:.4rem .6rem;border:1.5px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:inherit">
                ${i === 0
                    ? '<option value="semana" selected>Semana (automático)</option>'
                    : opciones.map((o, oi) => `<option value="${o}" ${defaults[i] === o ? 'selected' : ''}>${etiquetas[oi] || o}</option>`).join('')
                }
            </select>
        `;
        if (i === 0) div.querySelector('select').disabled = true;
        grid.appendChild(div);
    }

    document.getElementById('mapeoColumnas').style.display = '';
}

function aplicarImportacion() {
    const numCols = datosImport[0]?.length || 0;
    const mapa = {};
    for (let i = 0; i < numCols; i++) {
        const sel = document.getElementById(`col_${i}`);
        if (sel && sel.value && sel.value !== '— ignorar —') {
            mapa[i] = sel.value;
        }
    }

    // Limpiar filas existentes
    document.getElementById('lineasBody').innerHTML = '';

    let filasSaltadas = 0;
    datosImport.forEach((fila, idx) => {
        // Saltar filas de cabecera (primera columna no numérica)
        const primerVal = fila[0]?.replace(',', '.').trim();
        if (isNaN(parseFloat(primerVal)) || primerVal === '') {
            filasSaltadas++;
            return;
        }

        const semana = parseInt(primerVal) || 0;
        if (semana < 1) return;

        let peso = '', consumo = '', coste = '';
        for (const [col, campo] of Object.entries(mapa)) {
            const val = (fila[parseInt(col)] || '').replace(',', '.').trim();
            if (campo === 'peso_kg')    peso    = val;
            if (campo === 'consumo_g')  consumo = val;
            if (campo === 'coste_eur')  coste   = val;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="number" name="semana[]" value="${semana}" min="1" class="input-linea" required></td>
            <td><input type="number" name="peso[]" value="${peso}" step="0.001" min="0" class="input-linea"></td>
            <td><input type="number" name="consumo[]" value="${consumo}" min="0" class="input-linea" placeholder="—"></td>
            <td><input type="number" name="coste[]" value="${coste}" step="0.01" min="0" class="input-linea" placeholder="—"></td>
            <td><button type="button" onclick="eliminarFila(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem .4rem">×</button></td>
        `;
        document.getElementById('lineasBody').appendChild(tr);
    });

    actualizarContador();

    const total = document.getElementById('lineasBody').querySelectorAll('tr').length;
    document.getElementById('previewInfo').textContent = '';

    // Cerrar panel
    document.getElementById('importPanel').style.display = 'none';
    document.getElementById('pasteArea').value = '';
    document.getElementById('mapeoColumnas').style.display = 'none';
    nextSemana = total + 1;

    // Scroll a la tabla
    document.getElementById('tablaLineas').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>