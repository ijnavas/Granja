<?php
$navePreseleccionada = $_GET['nave'] ?? null;
?>

<div class="page-header">
    <h2>Crear cuadras en lote</h2>
    <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:640px">
    <form method="POST" action="<?= base_url('cuadras/masiva') ?>" onsubmit="return prepararEnvio()">
        <?= csrf_field() ?>
        <!-- campos ocultos calculados por JS -->
        <input type="hidden" name="inicio"   id="hInicio">
        <input type="hidden" name="cantidad" id="hCantidad">

        <div class="form-grid">
            <div class="form-section-title">Nave</div>

            <div class="form-group">
                <label>Nave *</label>
                <select name="nave_id" id="naveId" required>
                    <option value="">— Selecciona nave —</option>
                    <?php foreach ($naves as $n): ?>
                        <option value="<?= $n['id'] ?>"
                            <?= $navePreseleccionada == $n['id'] ? 'selected' : '' ?>>
                            <?= e($n['granja_nombre']) ?> · <?= e($n['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Numeración</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Prefijo</label>
                    <input type="text" id="prefijo" value="C"
                           oninput="actualizarPreview()" placeholder="C, Cuadra, Corral…">
                    <span class="form-hint">Texto antes del número</span>
                </div>
                <div class="form-group">
                    <label>Formato de número</label>
                    <select id="ceros" name="ceros" onchange="actualizarPreview()">
                        <option value="0">Sin relleno (1, 2, 3…)</option>
                        <option value="2" selected>2 dígitos (01, 02, 03…)</option>
                        <option value="3">3 dígitos (001, 002, 003…)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Desde (número inicial) *</label>
                    <input type="number" id="desde" value="1" min="1" max="9999" required
                           oninput="actualizarPreview()">
                </div>
                <div class="form-group">
                    <label>Hasta (número final) *</label>
                    <input type="number" id="hasta" value="80" min="1" max="9999" required
                           oninput="actualizarPreview()">
                    <span class="form-hint" id="hintCantidad">80 cuadras</span>
                </div>
            </div>

            <!-- Vista previa -->
            <div class="form-group">
                <label>Vista previa</label>
                <div id="preview" style="display:flex;flex-wrap:wrap;gap:.35rem;padding:.6rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.4rem;min-height:2.5rem">…</div>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Capacidad (igual para todas)</div>

            <div class="form-group">
                <label>Capacidad máxima (animales por cuadra)</label>
                <input type="number" name="capacidad_maxima" id="capacidad" min="0" value="40" placeholder="40">
                <span class="form-hint">Se aplicará a todas las cuadras creadas</span>
            </div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Ancho (m)</label>
                    <input type="number" name="ancho_m" step="0.01" min="0" placeholder="6">
                </div>
                <div class="form-group">
                    <label>Alto (m)</label>
                    <input type="number" name="alto_m" step="0.01" min="0" placeholder="2.5">
                </div>
                <div class="form-group">
                    <label>Largo (m)</label>
                    <input type="number" name="largo_m" step="0.01" min="0" placeholder="10">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnCrear">Crear cuadras</button>
            <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function pad(n, zeros) {
    return zeros > 0 ? String(n).padStart(zeros, '0') : String(n);
}

function actualizarPreview() {
    const prefijo  = document.getElementById('prefijo').value.trim();
    const desde    = parseInt(document.getElementById('desde').value) || 1;
    const hasta    = parseInt(document.getElementById('hasta').value) || 1;
    const ceros    = parseInt(document.getElementById('ceros').value) || 0;
    const preview  = document.getElementById('preview');
    const hint     = document.getElementById('hintCantidad');
    const btn      = document.getElementById('btnCrear');

    if (hasta < desde) {
        preview.innerHTML = '<span style="color:#ef4444;font-size:.82rem">⚠ "Hasta" debe ser mayor o igual que "Desde"</span>';
        hint.textContent  = '—';
        btn.disabled      = true;
        return;
    }

    const total = hasta - desde + 1;
    if (total > 500) {
        preview.innerHTML = '<span style="color:#ef4444;font-size:.82rem">⚠ Máximo 500 cuadras por operación</span>';
        hint.textContent  = total + ' cuadras (demasiadas)';
        btn.disabled      = true;
        return;
    }

    btn.disabled = false;
    hint.textContent = total + (total === 1 ? ' cuadra' : ' cuadras');

    const mostrar = Math.min(total, 12);
    let html = '';
    for (let i = desde; i < desde + mostrar; i++) {
        const nombre = (prefijo + pad(i, ceros)).trim();
        html += `<span style="background:#e0e7ff;color:#3730a3;font-size:.78rem;padding:.2rem .5rem;border-radius:.3rem;font-family:monospace">${nombre}</span>`;
    }
    if (total > mostrar) {
        html += `<span style="color:#9ca3af;font-size:.78rem;padding:.2rem .4rem">… y ${total - mostrar} más</span>`;
    }
    preview.innerHTML = html;
}

function prepararEnvio() {
    const desde   = parseInt(document.getElementById('desde').value) || 1;
    const hasta   = parseInt(document.getElementById('hasta').value) || 1;
    const total   = hasta - desde + 1;

    if (hasta < desde || total < 1 || total > 500) return false;

    // Pasar prefijo al campo hidden no es necesario porque "prefijo" no tiene name,
    // añadimos un input oculto con el valor del prefijo visible
    document.getElementById('hInicio').value   = desde;
    document.getElementById('hCantidad').value = total;

    // Añadir campo prefijo al form
    let pf = document.querySelector('input[name="prefijo"]');
    if (!pf) {
        pf = document.createElement('input');
        pf.type = 'hidden';
        pf.name = 'prefijo';
        document.getElementById('hInicio').parentNode.appendChild(pf);
    }
    pf.value = document.getElementById('prefijo').value.trim();

    const confirmMsg = `¿Crear ${total} cuadras en esta nave?`;
    return confirm(confirmMsg);
}

document.addEventListener('DOMContentLoaded', actualizarPreview);
</script>
