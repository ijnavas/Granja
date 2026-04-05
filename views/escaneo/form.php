<div class="page-header">
    <h2>Escanear cuaderno de control</h2>
    <a href="<?= base_url('movimientos') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:520px">
    <div style="font-size:.875rem;color:#6b7280;margin-bottom:1.25rem">
        Sube una foto del cuaderno de control diario. Claude analizará la imagen y extraerá automáticamente los movimientos, bajas y traslados para que los revises antes de confirmar.
    </div>

    <form method="POST" action="<?= base_url('escaneo/analizar') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Foto del cuaderno *</label>
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                   onchange="previewFoto(this)">
            <span class="form-hint">JPG, PNG o WEBP — máx. 10 MB</span>
        </div>

        <div id="preview" style="display:none;margin:.75rem 0">
            <img id="previewImg" src="" style="max-width:100%;border-radius:.5rem;border:1px solid #e5e7eb">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnAnalizar">Analizar imagen</button>
        </div>
    </form>
</div>

<script>
function previewFoto(input) {
    const preview = document.getElementById('preview');
    const img     = document.getElementById('previewImg');
    if (input.files && input.files[0]) {
        img.src = URL.createObjectURL(input.files[0]);
        preview.style.display = 'block';
    }
}

document.querySelector('form').addEventListener('submit', () => {
    const btn = document.getElementById('btnAnalizar');
    btn.disabled = true;
    btn.textContent = 'Analizando…';
});
</script>
