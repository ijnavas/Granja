<?php
$tieneCoords = !empty($granja['latitud']) && !empty($granja['longitud']);
?>
<?php if ($tieneCoords): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<?php endif; ?>

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
