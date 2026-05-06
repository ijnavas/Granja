<div class="page-header">
    <h2>Nuevo inventario</h2>
    <a href="<?= base_url('inventarios') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start">

<div class="form-card">
    <form method="POST" action="<?= base_url('inventarios') ?>" id="formInventario">
        <?= csrf_field() ?>

        <div class="form-group" id="grupoFecha">
            <label>Fecha del inventario *</label>
            <input type="date" name="fecha" id="fechaInv"
                   value="<?= date('Y-m-d') ?>"
                   onchange="cargarPreview()">
            <span class="form-hint">Normalmente a final de mes</span>
        </div>

        <div class="form-group">
            <label>Nombre (opcional)</label>
            <input type="text" name="nombre" placeholder="Ej: Cierre marzo 2026">
        </div>

        <div class="form-group">
            <label>Tipo de inventario</label>
            <div style="display:flex;gap:1rem;margin-top:.25rem;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400">
                    <input type="radio" name="tipo" value="cuadra" checked onchange="cargarPreview()">
                    Por cuadra
                </label>
                <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400">
                    <input type="radio" name="tipo" value="global" onchange="cargarPreview()">
                    Global (por lote)
                </label>
                <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400">
                    <input type="radio" name="tipo" value="pienso" onchange="cargarPreview()">
                    Pienso (silos)
                </label>
            </div>
            <span class="form-hint">Por cuadra: una fila por lote + cuadra. Global: una fila por lote. Pienso: snapshot de silos.</span>
        </div>

        <div class="form-actions" style="margin-top:1.5rem">
            <button type="submit" class="btn btn-primary" id="btnGuardar" disabled>
                Guardar inventario
            </button>
            <a href="<?= base_url('inventarios') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<!-- Panel previsualización -->
<div>
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.75rem">
        Previsualización
    </div>
    <div id="previewPanel">
        <div style="color:#9ca3af;font-size:.875rem">Selecciona una fecha para ver el inventario.</div>
    </div>
</div>

</div>

<script>
let previewData = [];
let sortCol = 'semana_tabla';
let sortDir = 1; // 1 asc, -1 desc

const COLS = [
    { key: 'semana_tabla',    label: () => 'Sem.',        align: 'center' },
    { key: '_lote_codigo',    label: () => 'Lote',        align: 'left'   },
    { key: 'ubicacion',       label: () => getTipo() === 'cuadra' ? 'Nave · Cuadra' : 'Naves', align: 'left', nosort: true },
    { key: 'estado_animal',   label: () => 'Estado',      align: 'left'   },
    { key: 'num_animales',    label: () => 'Animales',    align: 'right'  },
    { key: 'peso_kg',         label: () => 'Peso/ud',     align: 'right'  },
    { key: 'peso_real_kg',    label: () => 'Peso real',   align: 'right'  },
    { key: 'peso_total_kg',   label: () => 'Peso total',  align: 'right'  },
    { key: 'valor_total_eur', label: () => 'Valor total', align: 'right'  },
];

// Filtros por columna (lo escribe el usuario, se aplica antes de sortData)
const filtrosCol = {};

const estadoLabel = k => ({ lechon:'Lechón', cebo:'Cebo', reposicion:'Reposición', madres:'Madres' }[k] || k || '—');

function getTipo() {
    const r = document.querySelector('input[name="tipo"]:checked');
    return r ? r.value : 'cuadra';
}

// ── Fecha visible/oculta según tipo ─────────────────────────────
function toggleFechaField() {
    const tipo  = getTipo();
    const grupo = document.getElementById('grupoFecha');
    if (tipo === 'pienso') {
        grupo.style.display = 'none';
    } else {
        grupo.style.display = '';
    }
}

document.querySelectorAll('input[name="tipo"]').forEach(r => {
    r.addEventListener('change', toggleFechaField);
});

function aplicarFiltros(data) {
    const claves = Object.keys(filtrosCol).filter(k => filtrosCol[k] !== '' && filtrosCol[k] != null);
    if (!claves.length) return data;
    return data.filter(row => {
        for (const k of claves) {
            let v = row[k];
            if (k === 'ubicacion') {
                v = ([row._nave_nombre, row._cuadra_nombre].filter(Boolean).join(' · ') || row._granja_nombre || '');
            }
            if (k === 'estado_animal') {
                v = estadoLabel(row.estado_animal);
            }
            const txt = (v ?? '').toString().toLowerCase();
            if (!txt.includes(String(filtrosCol[k]).toLowerCase())) return false;
        }
        return true;
    });
}

function sortData(data) {
    return [...data].sort((a, b) => {
        let va = a[sortCol], vb = b[sortCol];
        if (va === null || va === undefined) va = sortDir > 0 ? Infinity : -Infinity;
        if (vb === null || vb === undefined) vb = sortDir > 0 ? Infinity : -Infinity;
        if (typeof va === 'string') return sortDir * va.localeCompare(vb);
        return sortDir * (parseFloat(va) - parseFloat(vb));
    });
}

let _focoFiltro = null;
function setFiltro(col, valor) {
    filtrosCol[col] = valor;
    _focoFiltro = col;
    renderTable();
    // Restaurar foco al input que estaba siendo escrito
    setTimeout(() => {
        const input = document.querySelector(`input[data-filtro-col="${_focoFiltro}"]`);
        if (input) {
            input.focus();
            const v = input.value;
            input.value = '';
            input.value = v;
        }
    }, 0);
}

function renderTable() {
    const esCuadra = getTipo() === 'cuadra';
    const filtrados = aplicarFiltros(previewData);
    const sorted    = sortData(filtrados);

    let totalAnim = 0, totalValor = 0;
    filtrados.forEach(l => {
        totalAnim  += l.num_animales;
        totalValor += parseFloat(l.valor_total_eur) || 0;
    });

    const thStyle = (col) => {
        const active  = sortCol === col.key;
        const cursor  = col.nosort ? 'default' : 'pointer';
        const color   = active ? '#111827' : '#6b7280';
        const align   = col.align === 'right' ? 'right' : col.align === 'center' ? 'center' : 'left';
        const arrow   = !col.nosort ? (active ? (sortDir > 0 ? ' ↑' : ' ↓') : ' ↕') : '';
        return `<th onclick="${col.nosort ? '' : `setSort('${col.key}')`}"
            style="padding:.5rem .75rem;text-align:${align};font-weight:600;cursor:${cursor};color:${color};user-select:none;white-space:nowrap">
            ${col.label()}${arrow}</th>`;
    };

    let html = '<div class="list-card" style="font-size:.82rem">';
    html += `<div style="padding:.75rem 1rem;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700;color:#166534">${filtrados.length}<span style="font-weight:400">/${previewData.length}</span> línea${filtrados.length !== 1 ? 's' : ''} · ${totalAnim.toLocaleString('es-ES')} animales</span>
        <span style="font-weight:700;color:#166534">${totalValor > 0 ? totalValor.toLocaleString('es-ES', {minimumFractionDigits:2}) + ' €' : '—'}</span>
    </div>`;

    html += '<table style="width:100%;border-collapse:collapse">';
    html += `<thead><tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">`;
    COLS.forEach(c => { html += thStyle(c); });
    html += `</tr>`;
    // Fila de filtros por columna
    html += `<tr style="background:#fafafa">`;
    COLS.forEach(c => {
        const v = filtrosCol[c.key] || '';
        html += `<th style="padding:.25rem .5rem">
            <input type="text" placeholder="Filtrar" data-filtro-col="${c.key}"
                   value="${v.replace(/"/g, '&quot;')}"
                   oninput="setFiltro('${c.key}', this.value)"
                   style="width:100%;padding:.18rem .35rem;border:1px solid #d1d5db;border-radius:.25rem;font-size:.72rem;font-weight:400">
        </th>`;
    });
    html += `</tr></thead><tbody>`;

    sorted.forEach((l, i) => {
        const bg = i % 2 === 0 ? '#fff' : '#f9fafb';
        const ubicacion = esCuadra
            ? ([l._nave_nombre, l._cuadra_nombre].filter(Boolean).join(' · ') || l._granja_nombre || '—')
            : (l._nave_nombre || l._granja_nombre || '—');
        const pesoUd    = l.peso_kg         ? parseFloat(l.peso_kg).toFixed(3) + ' kg'         : '—';
        const pesoReal  = l.peso_real_kg    ? `<span style="color:#1d4ed8">${parseFloat(l.peso_real_kg).toFixed(3)} kg</span>` : '<span style="color:#d1d5db">—</span>';
        const pesoTotal = l.peso_total_kg   ? parseFloat(l.peso_total_kg).toFixed(1) + ' kg'   : '—';
        const valor     = l.valor_total_eur ? parseFloat(l.valor_total_eur).toLocaleString('es-ES', {minimumFractionDigits:2}) + ' €' : '—';

        html += `<tr style="background:${bg};border-bottom:1px solid #f3f4f6">
            <td style="padding:.45rem .75rem;text-align:center;color:#9ca3af">${l.semana_tabla !== null ? 'S' + l.semana_tabla : '—'}</td>
            <td style="padding:.45rem .75rem;font-family:monospace;font-weight:600;color:#1d4ed8">${l._lote_codigo}</td>
            <td style="padding:.45rem .75rem;color:#6b7280">${ubicacion}</td>
            <td style="padding:.45rem .75rem;color:#374151">${estadoLabel(l.estado_animal)}</td>
            <td style="padding:.45rem .75rem;text-align:right;font-weight:600">${l.num_animales.toLocaleString('es-ES')}</td>
            <td style="padding:.45rem .75rem;text-align:right">${pesoUd}</td>
            <td style="padding:.45rem .75rem;text-align:right">${pesoReal}</td>
            <td style="padding:.45rem .75rem;text-align:right">${pesoTotal}</td>
            <td style="padding:.45rem .75rem;text-align:right;font-weight:600;color:#166534">${valor}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    document.getElementById('previewPanel').innerHTML = html;
}

function renderTablePienso() {
    let totalKg = 0;
    previewData.forEach(s => { totalKg += parseFloat(s.stock_kg) || 0; });

    let html = '<div class="list-card" style="font-size:.82rem">';
    html += `<div style="padding:.75rem 1rem;background:#fefce8;border-bottom:1px solid #fde68a;display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700;color:#92400e">${previewData.length} silo${previewData.length !== 1 ? 's' : ''}</span>
        <span style="font-weight:700;color:#92400e">${totalKg.toLocaleString('es-ES', {maximumFractionDigits:0})} kg en total</span>
    </div>`;
    html += '<table style="width:100%;border-collapse:collapse">';
    html += `<thead><tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">
        <th style="padding:.5rem .75rem;text-align:left;font-weight:600;color:#6b7280">Granja</th>
        <th style="padding:.5rem .75rem;text-align:left;font-weight:600;color:#6b7280">Silo</th>
        <th style="padding:.5rem .75rem;text-align:left;font-weight:600;color:#6b7280">Tipo pienso</th>
        <th style="padding:.5rem .75rem;text-align:right;font-weight:600;color:#6b7280">Stock actual</th>
        <th style="padding:.5rem .75rem;text-align:right;font-weight:600;color:#6b7280">Capacidad</th>
        <th style="padding:.5rem .75rem;text-align:right;font-weight:600;color:#6b7280">Mínimo</th>
        <th style="padding:.5rem .75rem;text-align:center;font-weight:600;color:#6b7280">% Lleno</th>
    </tr></thead><tbody>`;

    previewData.forEach((s, i) => {
        const bg    = i % 2 === 0 ? '#fff' : '#f9fafb';
        const pct   = parseInt(s.pct_stock) || 0;
        const alerta = parseFloat(s.stock_kg) <= parseFloat(s.stock_minimo_kg);
        const color  = alerta ? '#dc2626' : (pct < 30 ? '#d97706' : '#16a34a');
        const tipo   = s.tipo_pienso || '<span style="color:#d1d5db">—</span>';
        html += `<tr style="background:${bg};border-bottom:1px solid #f3f4f6">
            <td style="padding:.45rem .75rem;color:#6b7280;font-size:.8rem">${s.granja_nombre}</td>
            <td style="padding:.45rem .75rem;font-weight:600">${s.silo_nombre}</td>
            <td style="padding:.45rem .75rem;color:#374151">${tipo}</td>
            <td style="padding:.45rem .75rem;text-align:right;font-weight:600;color:${color}">${parseFloat(s.stock_kg).toLocaleString('es-ES', {maximumFractionDigits:0})} kg</td>
            <td style="padding:.45rem .75rem;text-align:right;color:#6b7280">${parseFloat(s.capacidad_kg).toLocaleString('es-ES', {maximumFractionDigits:0})} kg</td>
            <td style="padding:.45rem .75rem;text-align:right;color:#6b7280">${parseFloat(s.stock_minimo_kg).toLocaleString('es-ES', {maximumFractionDigits:0})} kg</td>
            <td style="padding:.45rem .75rem;text-align:center;font-weight:700;color:${color}">${pct}%</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    document.getElementById('previewPanel').innerHTML = html;
}

function setSort(col) {
    if (sortCol === col) {
        sortDir *= -1;
    } else {
        sortCol = col;
        sortDir = 1;
    }
    renderTable();
}

async function cargarPreview() {
    const fecha = document.getElementById('fechaInv').value;
    const tipo  = getTipo();

    // Para pienso no hace falta fecha
    if (tipo !== 'pienso' && !fecha) return;

    const panel = document.getElementById('previewPanel');
    const btnG  = document.getElementById('btnGuardar');
    panel.innerHTML = '<div style="color:#9ca3af;font-size:.875rem">Cargando...</div>';
    btnG.disabled   = true;

    const url  = `<?= base_url('inventarios/preview') ?>?fecha=${fecha}&tipo=${tipo}`;
    const res  = await fetch(url);
    previewData = await res.json();

    if (!previewData.length) {
        panel.innerHTML = '<div style="color:#dc2626;font-size:.875rem">' +
            (tipo === 'pienso' ? 'No hay silos activos configurados.' : 'No hay lotes activos para esta fecha.') +
            '</div>';
        return;
    }

    if (tipo === 'pienso') {
        renderTablePienso();
    } else {
        sortCol = 'semana_tabla';
        sortDir = 1;
        renderTable();
    }
    btnG.disabled = false;
}

document.addEventListener('DOMContentLoaded', () => {
    toggleFechaField();
    const f = document.getElementById('fechaInv');
    if (f && f.value) cargarPreview();
});
</script>
