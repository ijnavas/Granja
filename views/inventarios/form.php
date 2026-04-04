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

        <div class="form-group">
            <label>Fecha del inventario *</label>
            <input type="date" name="fecha" id="fechaInv" required
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
            <div style="display:flex;gap:1rem;margin-top:.25rem">
                <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400">
                    <input type="radio" name="tipo" value="cuadra" checked onchange="cargarPreview()">
                    Por cuadra
                </label>
                <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400">
                    <input type="radio" name="tipo" value="global" onchange="cargarPreview()">
                    Global (por lote)
                </label>
            </div>
            <span class="form-hint">Por cuadra: una fila por lote + cuadra. Global: una fila por lote.</span>
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

function getTipo() {
    const r = document.querySelector('input[name="tipo"]:checked');
    return r ? r.value : 'cuadra';
}

async function cargarPreview() {
    const fecha = document.getElementById('fechaInv').value;
    const tipo  = getTipo();
    if (!fecha) return;

    const panel  = document.getElementById('previewPanel');
    const btnG   = document.getElementById('btnGuardar');
    panel.innerHTML = '<div style="color:#9ca3af;font-size:.875rem">Cargando...</div>';
    btnG.disabled   = true;

    const res  = await fetch(`<?= base_url('inventarios/preview') ?>?fecha=${fecha}&tipo=${tipo}`);
    previewData = await res.json();

    if (!previewData.length) {
        panel.innerHTML = '<div style="color:#dc2626;font-size:.875rem">No hay lotes activos para esta fecha.</div>';
        return;
    }

    // Totales
    let totalAnim = 0, totalValor = 0;
    previewData.forEach(l => {
        totalAnim  += l.num_animales;
        totalValor += parseFloat(l.valor_total_eur) || 0;
    });

    // Cabeceras según tipo
    const esCuadra = tipo === 'cuadra';

    let html = '<div class="list-card" style="font-size:.82rem">';
    html += `<div style="padding:.75rem 1rem;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700;color:#166534">${previewData.length} línea${previewData.length !== 1 ? 's' : ''} · ${totalAnim.toLocaleString('es-ES')} animales</span>
        <span style="font-weight:700;color:#166534">${totalValor > 0 ? totalValor.toLocaleString('es-ES', {minimumFractionDigits:2}) + ' €' : '—'}</span>
    </div>`;

    html += '<table style="width:100%;border-collapse:collapse">';
    html += `<thead><tr style="background:#f9fafb;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#6b7280">
        <th style="padding:.5rem .75rem;text-align:left;font-weight:600">Lote</th>
        <th style="padding:.5rem .75rem;text-align:left;font-weight:600">${esCuadra ? 'Nave · Cuadra' : 'Naves'}</th>
        <th style="padding:.5rem .75rem;text-align:left;font-weight:600">Estado</th>
        <th style="padding:.5rem .75rem;text-align:center;font-weight:600">Sem.</th>
        <th style="padding:.5rem .75rem;text-align:right;font-weight:600">Animales</th>
        <th style="padding:.5rem .75rem;text-align:right;font-weight:600">Peso/ud</th>
        <th style="padding:.5rem .75rem;text-align:right;font-weight:600">Peso total</th>
        <th style="padding:.5rem .75rem;text-align:right;font-weight:600">Valor total</th>
    </tr></thead><tbody>`;

    previewData.forEach((l, i) => {
        const bg = i % 2 === 0 ? '#fff' : '#f9fafb';
        let ubicacion;
        if (esCuadra) {
            ubicacion = [l._nave_nombre, l._cuadra_nombre].filter(Boolean).join(' · ') || l._granja_nombre || '—';
        } else {
            ubicacion = l._nave_nombre || l._granja_nombre || '—';
        }
        const estadoLabel = {
            'lechon': 'Lechón', 'cebo': 'Cebo', 'reposicion': 'Reposición', 'madres': 'Madres'
        }[l.estado_animal] || (l.estado_animal || '—');
        const pesoUd    = l.peso_kg         ? parseFloat(l.peso_kg).toFixed(3) + ' kg'         : '—';
        const pesoTotal = l.peso_total_kg   ? parseFloat(l.peso_total_kg).toFixed(1) + ' kg'   : '—';
        const valor     = l.valor_total_eur ? parseFloat(l.valor_total_eur).toLocaleString('es-ES', {minimumFractionDigits:2}) + ' €' : '—';

        html += `<tr style="background:${bg};border-bottom:1px solid #f3f4f6">
            <td style="padding:.45rem .75rem;font-family:monospace;font-weight:600;color:#1d4ed8">${l._lote_codigo}</td>
            <td style="padding:.45rem .75rem;color:#6b7280">${ubicacion}</td>
            <td style="padding:.45rem .75rem;color:#374151">${estadoLabel}</td>
            <td style="padding:.45rem .75rem;text-align:center;color:#9ca3af">${l.semana_tabla !== null ? 'S' + l.semana_tabla : '—'}</td>
            <td style="padding:.45rem .75rem;text-align:right;font-weight:600">${l.num_animales.toLocaleString('es-ES')}</td>
            <td style="padding:.45rem .75rem;text-align:right">${pesoUd}</td>
            <td style="padding:.45rem .75rem;text-align:right">${pesoTotal}</td>
            <td style="padding:.45rem .75rem;text-align:right;font-weight:600;color:#166534">${valor}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    panel.innerHTML = html;
    btnG.disabled   = false;
}

document.addEventListener('DOMContentLoaded', () => {
    const f = document.getElementById('fechaInv');
    if (f && f.value) cargarPreview();
});
</script>
