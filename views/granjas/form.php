<?php
$esEdicion = !is_null($granja);
$action    = $esEdicion ? base_url("granjas/{$granja['id']}/actualizar") : base_url('granjas');
$lat       = $granja['latitud']  ?? 40.4168;
$lng       = $granja['longitud'] ?? -3.7038;
$tieneCoords = !empty($granja['latitud']) && !empty($granja['longitud']);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">


<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('granjas') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:780px">
    <form method="POST" action="<?= $action ?>">
        <?= csrf_field() ?>

        <div class="form-grid">

            <!-- DATOS GENERALES -->
            <div class="form-section-title">Datos generales</div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Nombre de la granja *</label>
                    <input type="text" name="nombre" required
                           value="<?= e($granja['nombre'] ?? '') ?>"
                           placeholder="Granja El Pinar">
                </div>
                <div class="form-group">
                    <label>Código REGA</label>
                    <input type="text" name="codigo_rega"
                           value="<?= e($granja['codigo_rega'] ?? '') ?>"
                           placeholder="ES140123450001"
                           maxlength="30">
                    <span class="form-hint">Registro de Explotaciones Agrarias</span>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Especie principal *</label>
                    <select name="especie" required>
                        <option value="">— Selecciona especie —</option>
                        <?php foreach (['porcino','aviar','vacuno','ovino','caprino','otro'] as $esp): ?>
                            <option value="<?= $esp ?>" <?= ($granja['especie'] ?? '') === $esp ? 'selected' : '' ?>>
                                <?= ucfirst($esp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo de producción</label>
                    <select name="tipo_produccion">
                        <option value="">— Sin especificar —</option>
                        <?php foreach (['Cebo','Ciclo cerrado','Maternidad','Recría','Mixta'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($granja['tipo_produccion'] ?? '') === $t ? 'selected' : '' ?>>
                                <?= $t ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="max-width:220px">
                <label>Capacidad máxima (animales)</label>
                <input type="number" name="capacidad_max" min="0"
                       value="<?= e($granja['capacidad_max'] ?? '') ?>"
                       placeholder="500">
            </div>

            <!-- UBICACIÓN -->
            <div class="form-section-title" style="margin-top:.5rem">Ubicación</div>

            <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion"
                       value="<?= e($granja['direccion'] ?? '') ?>"
                       placeholder="Calle, número, polígono...">
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Municipio</label>
                    <input type="text" name="municipio"
                           value="<?= e($granja['municipio'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Provincia</label>
                    <input type="text" name="provincia"
                           value="<?= e($granja['provincia'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group" style="max-width:160px">
                <label>Código postal</label>
                <input type="text" name="codigo_postal" maxlength="10"
                       value="<?= e($granja['codigo_postal'] ?? '') ?>">
            </div>

            <!-- MAPA -->
            <div class="form-section-title" style="margin-top:.5rem">Localización en mapa</div>

            <div class="form-group">
                <label>Buscar dirección en el mapa</label>
                <div class="search-row">
                    <input type="text" id="searchInput" placeholder="Escribe una dirección o nombre de lugar..." 
                           onkeydown="if(event.key==='Enter'){event.preventDefault();buscarDireccion();}">
                    <button type="button" onclick="buscarDireccion()">Buscar</button>
                </div>
                <div id="map"></div>
                <p class="map-hint">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Haz clic en el mapa para seleccionar la ubicación exacta de la granja
                </p>
            </div>

            <div class="coords-row">
                <div class="form-group">
                    <label>Latitud</label>
                    <input type="text" name="latitud" id="latInput"
                           value="<?= e($granja['latitud'] ?? '') ?>"
                           placeholder="40.4168" readonly>
                </div>
                <div class="form-group">
                    <label>Longitud</label>
                    <input type="text" name="longitud" id="lngInput"
                           value="<?= e($granja['longitud'] ?? '') ?>"
                           placeholder="-3.7038" readonly>
                </div>
                <button type="button" id="btnBorrarCoords" onclick="borrarCoords()">Borrar punto</button>
            </div>

        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $esEdicion ? 'Guardar cambios' : 'Crear granja' ?>
            </button>
            <a href="<?= base_url('granjas') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const initLat  = <?= $tieneCoords ? $lat : 40.4168 ?>;
const initLng  = <?= $tieneCoords ? $lng : -3.7038 ?>;
const initZoom = <?= $tieneCoords ? 14 : 6 ?>;

const map = L.map('map').setView([initLat, initLng], initZoom);

const capa_mapa = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
});

const capa_satelite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri — Esri, USGS, NOAA',
    maxZoom: 19,
});

const capa_hibrido = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
    attribution: '',
    maxZoom: 19,
    opacity: 0.85,
});

const sateliteConNombres = L.layerGroup([capa_satelite, capa_hibrido]);

capa_satelite.addTo(map);

L.control.layers(
    {
        'Mapa':     capa_mapa,
        'Satélite': capa_satelite,
        'Híbrido':  sateliteConNombres,
    },
    {},
    { position: 'topright', collapsed: false }
).addTo(map);

let marker = null;

<?php if ($tieneCoords): ?>
marker = L.marker([<?= $lat ?>, <?= $lng ?>]).addTo(map);
<?php endif; ?>

map.on('click', function(e) {
    const { lat, lng } = e.latlng;
    setMarker(lat, lng);
});

function setMarker(lat, lng) {
    if (marker) marker.setLatLng([lat, lng]);
    else marker = L.marker([lat, lng]).addTo(map);

    document.getElementById('latInput').value = lat.toFixed(7);
    document.getElementById('lngInput').value = lng.toFixed(7);
}

function borrarCoords() {
    if (marker) { map.removeLayer(marker); marker = null; }
    document.getElementById('latInput').value = '';
    document.getElementById('lngInput').value = '';
}

function buscarDireccion() {
    const q = document.getElementById('searchInput').value.trim();
    if (!q) return;

    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=1', {
        headers: { 'Accept-Language': 'es' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.length) { alert('No se encontró la dirección. Prueba con otro término.'); return; }
        const { lat, lon } = data[0];
        map.setView([parseFloat(lat), parseFloat(lon)], 14);
        setMarker(parseFloat(lat), parseFloat(lon));
    })
    .catch(() => alert('Error al buscar. Comprueba tu conexión.'));
}

// Autocapitalizar primera letra en inputs de texto
document.querySelectorAll('input[type=text]').forEach(input => {
    input.addEventListener('blur', function() {
        if (this.value.length > 0) {
            this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
        }
    });
});
</script>
