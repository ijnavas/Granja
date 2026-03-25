<?php
$tieneCoords = !empty($granja['latitud']) && !empty($granja['longitud']);
?>
<link rel="stylesheet" href="<?= base_url('css/crud.css') ?>">
<?php if ($tieneCoords): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<?php endif; ?>
<style>
    .detail-header {
        background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.07);
        padding:1.75rem; margin-bottom:1.5rem;
        display:grid; grid-template-columns:1fr auto; gap:1.5rem; align-items:start;
    }
    .detail-nombre { font-size:1.4rem; font-weight:700; color:#111827; margin-bottom:.3rem; }
    .detail-rega   { font-size:.8rem; font-family:monospace; color:#6b7280; }
    .detail-meta   { display:flex; flex-wrap:wrap; gap:1.5rem; margin-top:1rem; }
    .meta-item     { font-size:.82rem; color:#6b7280; }
    .meta-item strong { display:block; font-size:1rem; font-weight:600; color:#111827; }
    .detail-actions { display:flex; gap:.5rem; flex-shrink:0; }
    #mini-map { height:200px; border-radius:8px; border:1px solid #e5e7eb; width:280px; }
    .badge-especie { display:inline-block; padding:.2rem .75rem; border-radius:20px; font-size:.78rem; font-weight:600; }
</style>

<div class="page-header">
    <h2>Detalle granja</h2>
    <a href="<?= base_url('granjas') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="detail-header">
    <div>
        <div class="detail-nombre"><?= e($granja['nombre']) ?></div>
        <?php if ($granja['codigo_rega']): ?>
            <div class="detail-rega">REGA: <?= e($granja['codigo_rega']) ?></div>
        <?php endif; ?>
        <div class="detail-meta">
            <?php if ($granja['especie']): ?>
            <div class="meta-item">
                <strong><?= ucfirst(e($granja['especie'])) ?></strong>
                Especie
            </div>
            <?php endif; ?>
            <?php if ($granja['tipo_produccion']): ?>
            <div class="meta-item">
                <strong><?= e($granja['tipo_produccion']) ?></strong>
                Tipo producción
            </div>
            <?php endif; ?>
            <?php if ($granja['capacidad_max']): ?>
            <div class="meta-item">
                <strong><?= number_format($granja['capacidad_max']) ?></strong>
                Capacidad máx.
            </div>
            <?php endif; ?>
            <?php if ($granja['municipio']): ?>
            <div class="meta-item">
                <strong><?= e($granja['municipio']) ?><?= $granja['provincia'] ? ', ' . e($granja['provincia']) : '' ?></strong>
                Ubicación
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <div class="detail-actions" style="margin-bottom:.75rem">
            <a href="<?= base_url("granjas/{$granja['id']}/editar") ?>" class="btn btn-secondary btn-sm">Editar</a>
            <?php if (es_admin()): ?>
            <form method="POST" action="<?= base_url("granjas/{$granja['id']}/eliminar") ?>"
                  onsubmit="return confirm('¿Eliminar esta granja? Esta acción no se puede deshacer.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($tieneCoords): ?>
            <div id="mini-map"></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($tieneCoords): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const map = L.map('mini-map', { zoomControl: true, scrollWheelZoom: false })
    .setView([<?= $granja['latitud'] ?>, <?= $granja['longitud'] ?>], 15);
L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri', maxZoom: 19
}).addTo(map);
L.marker([<?= $granja['latitud'] ?>, <?= $granja['longitud'] ?>]).addTo(map);
</script>
<?php endif; ?>
