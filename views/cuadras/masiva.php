<?php
$navePreseleccionada = $_GET['nave'] ?? null;
?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">
<style>
    .preview-list { border:1.5px solid #d1d5db; border-radius:7px; padding:.75rem 1rem; background:#f9fafb; font-size:.875rem; color:#374151; min-height:48px; max-height:200px; overflow-y:auto; }
    .preview-item { padding:.2rem 0; border-bottom:1px solid #f0f0f0; }
    .preview-item:last-child { border-bottom:none; }
</style>

<div class="page-header">
    <h2>Crear cuadras en lote</h2>
    <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:600px">
    <form method="POST" action="<?= base_url('cuadras/masiva') ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section-title">Nave y patrón de nombres</div>

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

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Prefijo</label>
                    <input type="text" name="prefijo" id="prefijo" value="Cuadra"
                           oninput="actualizarPreview()" placeholder="Cuadra, Corral, C...">
                    <span class="form-hint">Texto antes del número</span>
                </div>
                <div class="form-group">
                    <label>Relleno de ceros</label>
                    <select name="ceros" id="ceros" onchange="actualizarPreview()">
                        <option value="0">Sin relleno (1, 2, 3...)</option>
                        <option value="2">2 dígitos (01, 02, 03...)</option>
                        <option value="3">3 dígitos (001, 002, 003...)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Número inicial</label>
                    <input type="number" name="inicio" id="inicio" value="1" min="1"
                           oninput="actualizarPreview()">
                </div>
                <div class="form-group">
                    <label>Cantidad a crear *</label>
                    <input type="number" name="cantidad" id="cantidad" value="10" min="1" max="100" required
                           oninput="actualizarPreview()">
                    <span class="form-hint">Máximo 100 por vez</span>
                </div>
            </div>

            <div class="form-group">
                <label>Vista previa de nombres</label>
                <div class="preview-list" id="preview">...</div>
            </div>

            <div class="form-section-title" style="margin-top:.5rem">Dimensiones y capacidad (iguales para todas)</div>

            <div class="form-group">
                <label>Capacidad máxima (animales)</label>
                <input type="number" name="capacidad_maxima" min="0" value="0" placeholder="0">
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
            <button type="submit" class="btn btn-primary">Crear cuadras</button>
            <a href="<?= base_url('cuadras') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function actualizarPreview() {
    const prefijo  = document.getElementById('prefijo').value.trim();
    const inicio   = parseInt(document.getElementById('inicio').value) || 1;
    const cantidad = Math.min(parseInt(document.getElementById('cantidad').value) || 0, 100);
    const ceros    = parseInt(document.getElementById('ceros').value) || 0;
    const preview  = document.getElementById('preview');

    if (cantidad < 1) { preview.innerHTML = '—'; return; }

    let html = '';
    const mostrar = Math.min(cantidad, 8);
    for (let i = inicio; i < inicio + mostrar; i++) {
        const num    = ceros > 0 ? String(i).padStart(ceros, '0') : String(i);
        const nombre = (prefijo + ' ' + num).trim();
        html += `<div class="preview-item">${nombre}</div>`;
    }
    if (cantidad > mostrar) {
        html += `<div class="preview-item" style="color:#9ca3af;font-style:italic">... y ${cantidad - mostrar} más</div>`;
    }
    preview.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', actualizarPreview);
</script>