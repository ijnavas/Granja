<?php
$esEdicion = !is_null($movimiento);
$action    = $esEdicion
    ? base_url("movimientos/{$movimiento['id']}/actualizar")
    : base_url('movimientos');

$tipoActual = $tipo ?? $movimiento['tipo'] ?? '';

// Fallback de tipos "legacy" para que los movimientos creados con los
// 6 tipos originales (que el usuario ya borró) sigan siendo editables.
// No aparecen en el selector de creación, pero sí se reconocen para
// mostrar su nombre/categoría en edición.
$LEGACY_TIPOS = [
    'traslado_cuadra'    => ['codigo'=>'traslado_cuadra',    'nombre'=>'Traslado cuadra (legado)',     'categoria'=>'traslado',    'color'=>'#1d4ed8', 'activo'=>0, 'es_legacy'=>1],
    'entrada_cebo'       => ['codigo'=>'entrada_cebo',       'nombre'=>'Entrada cebo (legado)',        'categoria'=>'transicion',  'color'=>'#92400e', 'activo'=>0, 'es_legacy'=>1],
    'entrada_reposicion' => ['codigo'=>'entrada_reposicion', 'nombre'=>'Entrada reposición (legado)',  'categoria'=>'re_creacion', 'color'=>'#6b21a8', 'activo'=>0, 'es_legacy'=>1],
    'entrada_madres'     => ['codigo'=>'entrada_madres',     'nombre'=>'Entrada madres (legado)',      'categoria'=>'re_consumo',  'color'=>'#9d174d', 'activo'=>0, 'es_legacy'=>1],
    'venta'              => ['codigo'=>'venta',              'nombre'=>'Venta (legado)',               'categoria'=>'venta',       'color'=>'#065f46', 'activo'=>0, 'es_legacy'=>1],
    'baja'               => ['codigo'=>'baja',               'nombre'=>'Baja (legado)',                'categoria'=>'baja',        'color'=>'#991b1b', 'activo'=>0, 'es_legacy'=>1],
];

// Tipos cargados desde DB. Los activos van al selector; añadimos los
// legacy al lookup para que el form los entienda al editar.
$tipoMap = $LEGACY_TIPOS;
foreach (($tipos ?? []) as $t) {
    $tipoMap[$t['codigo']] = $t;
}

// Agrupar tipos activos por "grupo" visible (Entradas/Salidas/Bajas/Traslados)
$gruposVisibles = [
    'entradas'  => ['titulo' => 'Entradas',  'cats' => ['entrada','transicion','re_creacion','re_consumo','destete'], 'color' => '#15803d', 'tipos' => []],
    'salidas'   => ['titulo' => 'Salidas',   'cats' => ['salida','venta'],                                            'color' => '#b45309', 'tipos' => []],
    'bajas'     => ['titulo' => 'Bajas',     'cats' => ['baja'],                                                      'color' => '#dc2626', 'tipos' => []],
    'traslados' => ['titulo' => 'Traslados', 'cats' => ['traslado','traslado_lote'],                                  'color' => '#1d4ed8', 'tipos' => []],
];
foreach (($tipos ?? []) as $t) {
    if ((int)($t['activo'] ?? 0) !== 1) continue;
    foreach ($gruposVisibles as $gk => &$g) {
        if (in_array($t['categoria'], $g['cats'], true)) {
            $g['tipos'][] = $t;
            break;
        }
    }
    unset($g);
}

// Si no hay tipo seleccionado pero hay grupos con tipos, usar el primero del primer grupo no vacío
if (!$tipoActual) {
    foreach ($gruposVisibles as $g) {
        if (!empty($g['tipos'])) { $tipoActual = $g['tipos'][0]['codigo']; break; }
    }
}

// Categoría del tipo actual (para decidir qué fields renderizar)
$categoriaActual = $tipoMap[$tipoActual]['categoria'] ?? 'salida';
$nombreActual    = $tipoMap[$tipoActual]['nombre'] ?? ucfirst(str_replace('_', ' ', $tipoActual));

// Umbrales de aviso (configurables en Configuración → Avisos)
$diasAvisoFecha = (int)($config['dias_advertencia_movimiento'] ?? 7);
$pctAvisoPeso   = (int)($config['pct_desviacion_peso_tabla']   ?? 15);

// Filtrar lotes según tipo (solo para tipos que necesitan filtros especiales)
$lotesReposicion = array_filter($lotes, fn($l) => str_ends_with(trim($l['codigo']), 'RE'));
?>

<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <a href="<?= base_url('movimientos') ?>" class="btn btn-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:780px">
<form method="POST" action="<?= $action ?>" id="frmMov" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="tipo" id="tipoHidden" value="<?= e($tipoActual) ?>">
    <input type="hidden" name="confirmar_inventarios" id="confirmarInv" value="">

    <?php if (!$esEdicion): ?>
    <!-- Selector de tipo agrupado por categoría -->
    <div class="form-section-title">Tipo de movimiento</div>
    <?php
    // Detectar si hay algún tipo en cualquier grupo
    $hayTipos = false;
    foreach ($gruposVisibles as $g) if (!empty($g['tipos'])) { $hayTipos = true; break; }
    ?>
    <?php if (!$hayTipos): ?>
        <div class="alert-flash alert-error" style="margin-bottom:1rem">
            No hay tipos de movimiento creados.
            <a href="<?= base_url('configuracion/movimientos') ?>"><strong>Crea uno en Configuración → Movimientos</strong></a>.
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.6rem;margin-bottom:1.25rem">
        <?php foreach ($gruposVisibles as $gk => $g):
            if (empty($g['tipos'])) continue;
            $abierto = false;
            foreach ($g['tipos'] as $t) if ($t['codigo'] === $tipoActual) { $abierto = true; break; }
        ?>
            <details <?= $abierto ? 'open' : '' ?>
                     style="border:2px solid <?= e($g['color']) ?>33;border-radius:8px;background:<?= e($g['color']) ?>0d;overflow:hidden">
                <summary style="cursor:pointer;list-style:none;padding:.6rem .9rem;font-weight:700;color:<?= e($g['color']) ?>;display:flex;justify-content:space-between;align-items:center;user-select:none">
                    <span><?= e($g['titulo']) ?></span>
                    <span style="font-size:.72rem;background:#fff;color:<?= e($g['color']) ?>;padding:.1rem .5rem;border-radius:99px;border:1px solid <?= e($g['color']) ?>33"><?= count($g['tipos']) ?></span>
                </summary>
                <div style="display:flex;flex-direction:column;gap:.3rem;padding:.5rem .6rem .7rem">
                    <?php foreach ($g['tipos'] as $t): ?>
                    <a href="<?= base_url('movimientos/crear?tipo=' . $t['codigo']) ?>"
                       class="btn <?= $tipoActual === $t['codigo'] ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
                       style="text-align:left">
                        <?= e($t['nombre']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($esEdicion): ?>
    <!-- ── EDICIÓN: tipo y datos básicos editables ── -->
    <?php $colorActual = $tipoMap[$tipoActual]['color'] ?? null; ?>
    <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af">Tipo</div>
        <div style="font-weight:700;color:<?= $colorActual ? e($colorActual) : '#374151' ?>">
            <?= e($nombreActual) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fecha + Cantidad (común a todos los tipos) -->
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Fecha *</label>
            <input type="date" name="fecha" id="fechaMov" required
                   value="<?= e($esEdicion ? $movimiento['fecha'] : date('Y-m-d')) ?>">
            <div id="avisoFecha" style="display:none;margin-top:.4rem;font-size:.78rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:6px;padding:.45rem .65rem"></div>
        </div>
        <div class="form-group">
            <label>Cantidad de animales *</label>
            <input type="number" name="num_animales" id="numAnimales" min="1" required
                   value="<?= e($esEdicion ? $movimiento['num_animales'] : '') ?>"
                   placeholder="Nº de animales" oninput="actualizarPesoEstimado()">
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         RAMAS POR CATEGORÍA
    ═════════════════════════════════════════════════════════ -->

    <?php if ($categoriaActual === 'traslado'): ?>
    <!-- TRASLADO -->
    <div class="form-section-title">Origen</div>
    <div class="form-grid form-grid-3">
        <div class="form-group">
            <label>Nave origen</label>
            <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                <option value="">— Nave —</option>
                <?php foreach ($naves as $n): ?>
                    <option value="<?= $n['id'] ?>"><?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Cuadra origen</label>
            <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen')">
                <option value="">— Cuadra —</option>
            </select>
        </div>
        <div class="form-group">
            <label>Lote</label>
            <select id="loteOrigen" name="lote_origen_id" required onchange="actualizarPesoEstimado()">
                <option value="">— Lote —</option>
            </select>
        </div>
    </div>
    <div class="form-section-title">Destino</div>
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Nave destino</label>
            <select id="naveDestino" onchange="cargarCuadras(this.value, 'cuadraDestino', null)">
                <option value="">— Nave —</option>
                <?php foreach ($naves as $n): ?>
                    <option value="<?= $n['id'] ?>"><?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Cuadra destino</label>
            <select id="cuadraDestino" name="cuadra_destino_id">
                <option value="">— Cuadra —</option>
            </select>
        </div>
    </div>

    <?php elseif ($categoriaActual === 'traslado_lote'): ?>
    <!-- TRASLADO ENTRE LOTES -->
    <div class="form-section-title">Origen</div>
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Lote origen *</label>
            <select id="loteOrigen" name="lote_origen_id" required onchange="cargarCuadrasParaLoteOrigen(this.value); rellenarLoteDestino(this.value); actualizarPesoEstimado()">
                <option value="">— Selecciona —</option>
                <?php foreach ($lotes as $l): if ((int)$l['num_animales'] <= 0) continue; ?>
                <option value="<?= $l['id'] ?>"
                        <?= ($movimiento['lote_origen_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                    <?= e($l['codigo']) ?> · <?= e($l['granja_nombre'] ?? '') ?> (<?= number_format($l['num_animales']) ?> animales)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Cuadra origen (opcional)</label>
            <select id="cuadraOrigen" name="cuadra_origen_id">
                <option value="">— Cualquiera del lote —</option>
            </select>
            <span class="form-hint">Si no eliges, descuenta del lote sin tocar cuadras</span>
        </div>
    </div>
    <div class="form-section-title">Destino</div>
    <div class="form-group">
        <label>Lote destino *</label>
        <select id="loteDestino" name="lote_destino_id" required>
            <option value="">— Selecciona —</option>
            <?php foreach ($lotes as $l): ?>
            <option value="<?= $l['id'] ?>"
                    data-origen="<?= (int)$l['id'] ?>"
                    <?= ($movimiento['lote_destino_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                <?= e($l['codigo']) ?> · <?= e($l['granja_nombre'] ?? '') ?> (<?= number_format($l['num_animales']) ?> animales)
            </option>
            <?php endforeach; ?>
        </select>
        <span class="form-hint">Los animales se moverán al lote destino sin asignación de cuadra</span>
    </div>

    <?php elseif ($categoriaActual === 'destete'): ?>
    <!-- DESTETE: en realidad se redirige antes; este branch solo se ve si llegan editando un destete -->
    <div class="form-section-title">Destete</div>
    <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;padding:1rem;margin-bottom:1rem">
        <p style="margin:0 0 .5rem;color:#166534">
            El destete crea un nuevo lote. Para registrar uno nuevo, usa el formulario de creación de lote en modo destete:
        </p>
        <a href="<?= base_url('lotes/crear?modo=destete') ?>" class="btn btn-primary btn-sm">
            Abrir formulario de destete
        </a>
    </div>

    <?php elseif ($categoriaActual === 'transicion'): ?>
    <!-- ENTRADA CEBO (transición de estado) -->
    <div class="form-section-title">Lote que pasa a cebo</div>
    <div class="form-group">
        <label>Lote de lechones *</label>
        <select name="lote_origen_id" required onchange="actualizarPesoEstimado()">
            <option value="">— Selecciona lote —</option>
            <?php foreach ($lotes as $l): if (($l['estado_animal'] ?? 'lechon') !== 'lechon') continue; ?>
            <option value="<?= $l['id'] ?>"><?= e($l['codigo']) ?> (<?= number_format($l['num_animales']) ?> animales)</option>
            <?php endforeach; ?>
        </select>
        <span class="form-hint">Todo el lote pasará al estado <strong>Cebo</strong></span>
    </div>

    <?php elseif ($categoriaActual === 're_creacion'): ?>
    <!-- ENTRADA REPOSICIÓN -->
    <div class="form-section-title">Origen</div>
    <div class="form-grid form-grid-3">
        <div class="form-group">
            <label>Nave origen</label>
            <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                <option value="">— Nave —</option>
                <?php foreach ($naves as $n): ?>
                    <option value="<?= $n['id'] ?>"><?= e($n['granja_nombre'] ?? '') ?> · <?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Cuadra origen</label>
            <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen')">
                <option value="">— Cuadra —</option>
            </select>
        </div>
        <div class="form-group">
            <label>Lote *</label>
            <select id="loteOrigen" name="lote_origen_id" required onchange="actualizarPesoEstimado()">
                <option value="">— Lote —</option>
            </select>
        </div>
    </div>
    <span class="form-hint">Se creará un nuevo lote con sufijo <strong>RE</strong> con los animales indicados</span>

    <?php elseif ($categoriaActual === 're_consumo'): ?>
    <!-- ENTRADA MADRES -->
    <div class="form-section-title">Lote de reposición de origen</div>
    <div class="form-grid form-grid-3">
        <div class="form-group">
            <label>Nave origen</label>
            <select id="naveOrigen" onchange="cargarCuadras(this.value, 'cuadraOrigen', 'loteOrigen')">
                <option value="">— Nave —</option>
                <?php foreach ($naves as $n): ?>
                    <option value="<?= $n['id'] ?>"><?= e($n['granja_nombre'] ?? '') ?> · <?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Cuadra origen</label>
            <select id="cuadraOrigen" name="cuadra_origen_id" onchange="cargarLotesDeCuadra(this.value, 'loteOrigen', null, true)">
                <option value="">— Cuadra —</option>
            </select>
        </div>
        <div class="form-group">
            <label>Lote RE *</label>
            <select id="loteOrigen" name="lote_origen_id" required onchange="actualizarPesoEstimado()">
                <option value="">— Lote RE —</option>
            </select>
        </div>
    </div>
    <span class="form-hint">Solo se muestran lotes con sufijo <strong>RE</strong>.</span>

    <?php elseif ($categoriaActual === 'venta'): ?>
    <!-- VENTA -->
    <div class="form-section-title">Lote a vender</div>
    <div class="form-group">
        <label>Lote *</label>
        <select id="loteOrigenVenta" name="lote_origen_id" required onchange="cargarCuadrasDelLote(this.value, 'venta'); actualizarPesoEstimado()">
            <option value="">— Selecciona lote —</option>
            <?php foreach ($lotes as $l): if ((int)$l['num_animales'] <= 0) continue; ?>
            <option value="<?= $l['id'] ?>" <?= ($movimiento['lote_origen_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                <?= e($l['codigo']) ?> · <?= e($l['granja_nombre'] ?? '') ?><?= $l['nave_nombre'] ? ' · ' . e($l['nave_nombre']) : '' ?> (<?= number_format($l['num_animales']) ?> animales)
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="cuadrasVentaWrap" style="display:none;margin-bottom:1rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <div class="form-section-title" style="margin:0">Cuadras de origen</div>
            <button type="button" onclick="seleccionarLoteEntero('venta')" class="btn btn-secondary btn-sm">Lote entero</button>
        </div>
        <div id="cuadrasVentaCards" style="display:flex;flex-wrap:wrap;gap:.6rem"></div>
        <div id="cuadrasVentaInputs"></div>
    </div>
    <div class="form-section-title" style="margin-top:.5rem">Datos de venta</div>
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Destino de venta *</label>
            <select name="tipo_venta" required>
                <option value="">— Selecciona —</option>
                <option value="matadero" <?= ($movimiento['tipo_venta'] ?? '') === 'matadero' ? 'selected' : '' ?>>Matadero</option>
                <option value="tercero"  <?= ($movimiento['tipo_venta'] ?? '') === 'tercero'  ? 'selected' : '' ?>>Tercero</option>
            </select>
        </div>
        <div class="form-group">
            <label>Precio total (€)</label>
            <input type="number" name="precio_eur" step="0.01" min="0"
                   value="<?= e($movimiento['precio_eur'] ?? '') ?>"
                   placeholder="0.00">
        </div>
    </div>
    <div class="form-group">
        <label>Peso canal total (kg)</label>
        <input type="number" name="peso_canal_kg" id="pesoCanal" step="0.01" min="0"
               value="<?= e($movimiento['peso_canal_kg'] ?? '') ?>"
               placeholder="0.00" oninput="validarPeso()">
        <div id="avisoPeso" style="display:none;margin-top:.4rem;font-size:.78rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:6px;padding:.45rem .65rem"></div>
    </div>

    <!-- Albarán / foto con peso del camión -->
    <div class="form-group">
        <label>Albarán / foto del peso de camión</label>
        <?php if (!empty($movimiento['albaran_archivo'])): ?>
        <div style="margin-bottom:.5rem;padding:.5rem .75rem;background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:6px;display:flex;align-items:center;justify-content:space-between;gap:.5rem">
            <span style="font-size:.85rem;color:#1d4ed8;display:flex;align-items:center;gap:.4rem">
                📎 Albarán adjunto
            </span>
            <a href="<?= base_url($movimiento['albaran_archivo']) ?>" target="_blank"
               style="font-size:.82rem;color:#1d4ed8;font-weight:600;text-decoration:none">
                Ver archivo →
            </a>
        </div>
        <span class="form-hint">Subir uno nuevo lo reemplaza:</span>
        <?php endif; ?>
        <input type="file" name="albaran_archivo"
               accept="image/jpeg,image/png,image/webp,application/pdf"
               capture="environment">
        <span class="form-hint">JPG/PNG/WEBP o PDF (máx 10 MB). En móvil puedes hacer foto directa.</span>
    </div>

    <?php elseif ($categoriaActual === 'baja' || $categoriaActual === 'salida'): ?>
    <!-- BAJA / SALIDA: nave → lote → cuadras -->
    <div class="form-section-title">Origen</div>
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Nave</label>
            <select id="naveBaja" onchange="cargarLotesDeNave(this.value)">
                <option value="">— Todas las naves —</option>
                <?php foreach ($naves as $n): ?>
                    <option value="<?= $n['id'] ?>"><?= e($n['granja_nombre'] ?? '') ?> · <?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Filtra los lotes por nave (opcional)</span>
        </div>
        <div class="form-group">
            <label>Lote *</label>
            <select id="loteOrigenBaja" name="lote_origen_id" required
                    onchange="cargarCuadrasDelLote(this.value, 'baja'); actualizarPesoEstimado()">
                <option value="">— Selecciona lote —</option>
                <?php
                // En edición permitir mostrar el lote actual aunque tenga 0 animales
                $loteActualId = (int)($movimiento['lote_origen_id'] ?? 0);
                foreach ($lotes as $l):
                    if ((int)$l['num_animales'] <= 0 && (int)$l['id'] !== $loteActualId) continue;
                ?>
                <option value="<?= $l['id'] ?>"
                        data-nave="<?= (int)($l['nave_id'] ?? 0) ?>"
                        <?= $loteActualId === (int)$l['id'] ? 'selected' : '' ?>>
                    <?= e($l['codigo']) ?> · <?= e($l['granja_nombre'] ?? '') ?><?= $l['nave_nombre'] ? ' · ' . e($l['nave_nombre']) : '' ?> (<?= number_format($l['num_animales']) ?> animales)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div id="cuadrasBajaWrap" style="display:none;margin-bottom:1rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <div class="form-section-title" style="margin:0">Cuadra de origen</div>
            <button type="button" onclick="seleccionarLoteEntero('baja')" class="btn btn-secondary btn-sm">Lote entero</button>
        </div>
        <div id="cuadrasBajaCards" style="display:flex;flex-wrap:wrap;gap:.6rem"></div>
        <div id="cuadrasBajaInputs"></div>
    </div>

    <?php if ($categoriaActual === 'baja'): ?>
    <?php $motivoActual = $movimiento['motivo_baja'] ?? ''; ?>
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Motivo *</label>
            <select name="motivo_baja" required>
                <option value="">— Selecciona —</option>
                <?php foreach (($motivos ?? []) as $mot): ?>
                <option value="<?= e($mot['codigo']) ?>" <?= $motivoActual === $mot['codigo'] ? 'selected' : '' ?>>
                    <?= e($mot['nombre']) ?>
                </option>
                <?php endforeach; ?>
                <?php
                // Si el motivo guardado no existe en la BD (p.ej. fue borrado),
                // mostrarlo igualmente para no perder el dato.
                $codigosBd = array_column($motivos ?? [], 'codigo');
                if ($motivoActual && !in_array($motivoActual, $codigosBd, true)):
                ?>
                <option value="<?= e($motivoActual) ?>" selected><?= e(ucfirst($motivoActual)) ?> (legado)</option>
                <?php endif; ?>
            </select>
            <span class="form-hint">Edita los motivos en <a href="<?= base_url('configuracion/general') ?>">Configuración → Avisos</a></span>
        </div>
        <div class="form-group">
            <label>Peso real medio (kg/animal)</label>
            <input type="number" name="peso_real_kg" id="pesoReal" step="0.001" min="0"
                   value="<?= e($movimiento['peso_real_kg'] ?? '') ?>"
                   placeholder="Opcional" oninput="validarPeso()">
            <span class="form-hint">Si se conoce, peso medio individual de los animales dados de baja</span>
        </div>
    </div>
    <div id="avisoPeso" style="display:none;margin-top:.4rem;font-size:.78rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:6px;padding:.45rem .65rem"></div>
    <?php endif; ?>

    <?php elseif ($categoriaActual === 'entrada'): ?>
    <!-- COMPRA / ENTRADA: lote existente + cuadra opcional -->
    <div class="form-section-title">Lote receptor</div>
    <div class="form-grid form-grid-2">
        <div class="form-group">
            <label>Lote *</label>
            <select name="lote_origen_id" required onchange="actualizarPesoEstimado()">
                <option value="">— Selecciona lote —</option>
                <?php foreach ($lotes as $l): ?>
                <option value="<?= $l['id'] ?>" <?= ($movimiento['lote_origen_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                    <?= e($l['codigo']) ?> · <?= e($l['granja_nombre'] ?? '') ?><?= $l['nave_nombre'] ? ' · ' . e($l['nave_nombre']) : '' ?> (<?= number_format($l['num_animales']) ?> animales)
                </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Los animales se sumarán a este lote</span>
        </div>
        <div class="form-group">
            <label>Cuadra (opcional)</label>
            <select name="cuadra_origen_id">
                <option value="">— Sin cuadra específica —</option>
            </select>
        </div>
    </div>

    <?php endif; ?>

    <!-- Panel peso estimado (visible cuando hay lote+fecha) -->
    <div id="panelPeso" style="display:none;background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;padding:.85rem 1rem;margin-top:.5rem">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#1e40af;margin-bottom:.4rem">
            Peso estimado del lote
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:1.25rem;font-size:.85rem">
            <div><span style="color:#6b7280">Semana:</span> <strong id="pesoSemana">—</strong></div>
            <div><span style="color:#6b7280">Tabla:</span> <strong id="pesoTabla">—</strong> kg/ud</div>
            <div><span style="color:#6b7280">Real proyectado:</span> <strong id="pesoReal2">—</strong> kg/ud</div>
            <div><span style="color:#6b7280">Total estimado:</span> <strong id="pesoTotal">—</strong></div>
        </div>
    </div>

    <!-- Observaciones -->
    <div class="form-group" style="margin-top:1rem">
        <label>Observaciones</label>
        <textarea name="observaciones" rows="2"><?= e($movimiento['observaciones'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $esEdicion ? 'Guardar cambios' : 'Registrar movimiento' ?>
        </button>
        <a href="<?= base_url('movimientos') ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
</div>

<?php if ($esEdicion && !empty($historial)): ?>
<div style="margin-top:2rem">
    <div class="form-section-title" style="margin-bottom:.75rem">Historial de cambios</div>
    <div class="list-card">
        <table class="list-table">
            <thead>
                <tr><th>Fecha</th><th>Acción</th><th>Usuario</th></tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                    <td>
                        <span style="background:<?= $h['accion'] === 'crear' ? '#d1fae5' : ($h['accion'] === 'eliminar' ? '#fee2e2' : '#dbeafe') ?>;
                                     color:<?= $h['accion'] === 'crear' ? '#065f46' : ($h['accion'] === 'eliminar' ? '#991b1b' : '#1e40af') ?>;
                                     padding:.15rem .5rem;border-radius:20px;font-size:.75rem;font-weight:600">
                            <?= ucfirst($h['accion']) ?>
                        </span>
                    </td>
                    <td><?= e($h['usuario_nombre']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const BASE_URL  = '<?= base_url('') ?>';
const TIPO      = '<?= e($tipoActual) ?>';
const CATEGORIA = '<?= e($categoriaActual) ?>';
const CFG_DIAS  = <?= (int)$diasAvisoFecha ?>;
const CFG_PCT   = <?= (int)$pctAvisoPeso ?>;
const ES_EDICION = <?= $esEdicion ? 'true' : 'false' ?>;
const MOV_ID     = <?= $esEdicion ? (int)$movimiento['id'] : 'null' ?>;
const LOTE_ORIGINAL_ID = <?= $esEdicion ? (int)$movimiento['lote_origen_id'] : 'null' ?>;

// ── Tipos comunes a varias ramas ────────────────────────────
async function cargarCuadras(naveId, selectId, loteSelectId, valorSeleccionado = null) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">Cargando...</option>';
    if (!naveId) { sel.innerHTML = '<option value="">— Cuadra —</option>'; return; }

    const res  = await fetch(`${BASE_URL}movimientos/cuadras?nave_id=${naveId}`);
    const data = await res.json();

    sel.innerHTML = '<option value="">— Cuadra —</option>';
    data.forEach(c => {
        const info = c.lotes ? ` (${c.lotes})` : '';
        const selected = valorSeleccionado && c.id == valorSeleccionado ? 'selected' : '';
        sel.innerHTML += `<option value="${c.id}" ${selected}>${c.nombre}${info}</option>`;
    });

    if (loteSelectId) {
        document.getElementById(loteSelectId).innerHTML = '<option value="">— Lote —</option>';
    }
}

let cuadrasCache = {};

async function cargarCuadrasDelLote(loteId, tipo) {
    const wrap   = document.getElementById(tipo === 'venta' ? 'cuadrasVentaWrap'   : 'cuadrasBajaWrap');
    const cards  = document.getElementById(tipo === 'venta' ? 'cuadrasVentaCards'  : 'cuadrasBajaCards');
    const inputs = document.getElementById(tipo === 'venta' ? 'cuadrasVentaInputs' : 'cuadrasBajaInputs');
    if (!wrap || !cards || !inputs) return;

    cards.innerHTML  = '';
    inputs.innerHTML = '';
    cuadrasCache[tipo] = [];

    if (!loteId) { wrap.style.display = 'none'; return; }

    const res  = await fetch(`${BASE_URL}movimientos/cuadras-lote?lote_id=${loteId}`);
    const data = await res.json();

    if (data.length === 0) {
        wrap.style.display = 'none';
        const numField = document.querySelector('input[name="num_animales"]');
        if (numField) { numField.readOnly = false; numField.style.background = ''; }
        return;
    }

    cuadrasCache[tipo] = data;
    data.forEach(c => {
        const card = document.createElement('div');
        card.className  = 'cuadra-card-multi';
        card.dataset.id = c.id;
        card.dataset.max = c.num_animales;
        card.innerHTML  = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                <div>
                    <div style="font-weight:700;font-size:.88rem">${c.nombre}</div>
                    <div style="font-size:.72rem;color:#6b7280">${c.nave_nombre}</div>
                    <div style="font-size:.8rem;color:#374151;margin-top:.15rem"><strong>${c.num_animales}</strong> animales</div>
                </div>
                <div style="text-align:right;min-width:70px">
                    <input type="number" class="cuadra-qty-input" min="0" max="${c.num_animales}"
                           value="0" placeholder="0"
                           style="width:70px;padding:.25rem .4rem;border:1.5px solid #d1d5db;border-radius:.35rem;font-size:.85rem;text-align:right"
                           oninput="onQtyChange(this,'${tipo}')">
                    <div style="font-size:.68rem;color:#9ca3af;margin-top:.15rem">máx ${c.num_animales}</div>
                </div>
            </div>`;
        card.style.cssText = 'padding:.65rem .85rem;border:2px solid #e5e7eb;border-radius:.5rem;min-width:200px;background:#fff;transition:border-color .15s';
        cards.appendChild(card);
    });

    wrap.style.display = 'block';
    actualizarTotalAnimales(tipo);
}

function onQtyChange(input, tipo) {
    const card = input.closest('.cuadra-card-multi');
    const max  = parseInt(card.dataset.max);
    let val    = parseInt(input.value) || 0;
    if (val < 0) val = 0;
    if (val > max) { val = max; input.value = max; }
    card.style.borderColor = val > 0 ? '#2563eb' : '#e5e7eb';
    card.style.background  = val > 0 ? '#eff6ff' : '#fff';
    actualizarTotalAnimales(tipo);
}

function actualizarTotalAnimales(tipo) {
    const cards  = document.getElementById(tipo === 'venta' ? 'cuadrasVentaCards'  : 'cuadrasBajaCards');
    const inputs = document.getElementById(tipo === 'venta' ? 'cuadrasVentaInputs' : 'cuadrasBajaInputs');
    const numField = document.querySelector('input[name="num_animales"]');

    let total = 0;
    const hidden = [];
    cards.querySelectorAll('.cuadra-card-multi').forEach(card => {
        const qty = parseInt(card.querySelector('.cuadra-qty-input').value) || 0;
        if (qty > 0) {
            total += qty;
            hidden.push({ id: card.dataset.id, num: qty });
        }
    });

    if (numField) { numField.value = total; numField.readOnly = true; numField.style.background = '#f3f4f6'; }
    inputs.innerHTML = hidden.map(h =>
        `<input type="hidden" name="cuadras_origen_ids[]" value="${h.id}">
         <input type="hidden" name="cuadras_origen_nums[]" value="${h.num}">`
    ).join('');

    actualizarPesoEstimado();
}

function seleccionarLoteEntero(tipo) {
    const cards = document.getElementById(tipo === 'venta' ? 'cuadrasVentaCards' : 'cuadrasBajaCards');
    cards.querySelectorAll('.cuadra-card-multi').forEach(card => {
        const input = card.querySelector('.cuadra-qty-input');
        input.value = card.dataset.max;
        card.style.borderColor = '#2563eb';
        card.style.background  = '#eff6ff';
    });
    actualizarTotalAnimales(tipo);
}

async function cargarLotesDeCuadra(cuadraId, loteSelectId, valorSeleccionado = null, soloRE = false) {
    const sel = document.getElementById(loteSelectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">Cargando...</option>';
    if (!cuadraId) { sel.innerHTML = '<option value="">— Lote —</option>'; return; }

    const res  = await fetch(`${BASE_URL}movimientos/lotes-cuadra?cuadra_id=${cuadraId}`);
    let data = await res.json();
    if (soloRE) data = data.filter(l => l.codigo.trim().endsWith('RE'));

    sel.innerHTML = soloRE ? '<option value="">— Lote RE —</option>' : '<option value="">— Lote —</option>';
    data.forEach(l => {
        const selected = valorSeleccionado && l.id == valorSeleccionado ? 'selected' : '';
        sel.innerHTML += `<option value="${l.id}" ${selected}>${l.codigo} (${l.num_animales} animales)</option>`;
    });
    if (!valorSeleccionado && data.length === 1) sel.value = data[0].id;
    actualizarPesoEstimado();
}

// ── Traslado entre lotes ─────────────────────────────────────
async function cargarCuadrasParaLoteOrigen(loteId) {
    const sel = document.getElementById('cuadraOrigen');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Cualquiera del lote —</option>';
    if (!loteId) return;
    const res  = await fetch(`${BASE_URL}movimientos/cuadras-lote?lote_id=${loteId}`);
    const data = await res.json();
    data.forEach(c => {
        sel.innerHTML += `<option value="${c.id}">${c.nombre} · ${c.nave_nombre} (${c.num_animales} animales)</option>`;
    });
}

function rellenarLoteDestino(origenId) {
    const sel = document.getElementById('loteDestino');
    if (!sel) return;
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) return; // mantener placeholder
        opt.disabled = (opt.value === origenId);
    });
    if (sel.value === origenId) sel.value = '';
}

// ── Bajas/salida: nave → lote ────────────────────────────────
async function cargarLotesDeNave(naveId) {
    const sel = document.getElementById('loteOrigenBaja');
    if (!sel) return;
    sel.innerHTML = '<option value="">Cargando...</option>';
    const res  = await fetch(`${BASE_URL}movimientos/lotes-de-nave?nave_id=${naveId || 0}`);
    const data = await res.json();
    sel.innerHTML = '<option value="">— Selecciona lote —</option>';
    data.forEach(l => {
        sel.innerHTML += `<option value="${l.id}">${l.codigo} (${l.num_animales} animales)</option>`;
    });

    // Limpiar cuadras
    const wrap = document.getElementById('cuadrasBajaWrap');
    if (wrap) wrap.style.display = 'none';
}

// ── Peso estimado (vivo) ──────────────────────────────────────
let _pesoTimer = null;
function actualizarPesoEstimado() {
    if (_pesoTimer) clearTimeout(_pesoTimer);
    _pesoTimer = setTimeout(_actualizarPesoEstimadoNow, 200);
}

async function _actualizarPesoEstimadoNow() {
    const loteSel = document.querySelector('select[name="lote_origen_id"]');
    const fechaEl = document.getElementById('fechaMov');
    const numEl   = document.getElementById('numAnimales');
    const panel   = document.getElementById('panelPeso');
    if (!panel) return;

    if (!loteSel || !loteSel.value || !fechaEl || !fechaEl.value) {
        panel.style.display = 'none';
        return;
    }

    const url = `${BASE_URL}movimientos/peso-estimado?lote_id=${loteSel.value}&fecha=${fechaEl.value}`;
    const res = await fetch(url);
    const data = await res.json();
    if (!data.ok || data.peso_tabla === null) { panel.style.display = 'none'; return; }

    const num = parseInt(numEl?.value || '0') || 0;
    const pesoUd = data.peso_real_proyectado ?? data.peso_tabla;

    document.getElementById('pesoSemana').textContent = data.semana ? 'S' + data.semana : '—';
    document.getElementById('pesoTabla').textContent  = data.peso_tabla.toFixed(3);
    document.getElementById('pesoReal2').textContent  = data.peso_real_proyectado !== null ? data.peso_real_proyectado.toFixed(3) : '—';
    document.getElementById('pesoTotal').textContent  = num > 0 ? (pesoUd * num).toFixed(2) + ' kg' : '—';
    panel.style.display = '';

    // Comparar con peso introducido (canal o real) si lo hay → mostrar warning
    validarPeso(data);
}

// ── Aviso fecha lejana ───────────────────────────────────────
function validarFecha() {
    const aviso = document.getElementById('avisoFecha');
    const fecha = document.getElementById('fechaMov');
    if (!aviso || !fecha || !fecha.value || CFG_DIAS <= 0) {
        if (aviso) aviso.style.display = 'none';
        return;
    }
    const hoy   = new Date(); hoy.setHours(0,0,0,0);
    const sel   = new Date(fecha.value);
    const dias  = Math.abs(Math.round((sel - hoy) / 86400000));
    if (dias > CFG_DIAS) {
        const direccion = sel < hoy ? 'pasado' : 'futuro';
        aviso.textContent = `⚠ La fecha está ${dias} días en el ${direccion} (umbral: ${CFG_DIAS}).`;
        aviso.style.display = '';
    } else {
        aviso.style.display = 'none';
    }
}

// ── Aviso peso fuera de tabla ────────────────────────────────
let _pesoEstimadoCache = null;
function validarPeso(dataInput) {
    if (dataInput) _pesoEstimadoCache = dataInput;
    const aviso = document.getElementById('avisoPeso');
    if (!aviso || CFG_PCT <= 0 || !_pesoEstimadoCache || !_pesoEstimadoCache.peso_tabla) {
        if (aviso) aviso.style.display = 'none';
        return;
    }

    let pesoUdInput = null;
    const numEl = document.getElementById('numAnimales');
    const num   = parseInt(numEl?.value || '0') || 0;

    if (CATEGORIA === 'venta') {
        const pc = parseFloat(document.getElementById('pesoCanal')?.value || '0');
        if (pc > 0 && num > 0) pesoUdInput = pc / num;
    } else if (CATEGORIA === 'baja') {
        const pr = parseFloat(document.getElementById('pesoReal')?.value || '0');
        if (pr > 0) pesoUdInput = pr;
    }

    if (pesoUdInput === null) { aviso.style.display = 'none'; return; }

    const pesoEsperado = _pesoEstimadoCache.peso_real_proyectado ?? _pesoEstimadoCache.peso_tabla;
    const desv = Math.abs(pesoUdInput - pesoEsperado) / pesoEsperado * 100;
    if (desv > CFG_PCT) {
        const dir = pesoUdInput > pesoEsperado ? 'mayor' : 'menor';
        aviso.textContent = `⚠ El peso introducido (${pesoUdInput.toFixed(2)} kg/ud) es ${desv.toFixed(0)}% ${dir} que el esperado por la tabla (${pesoEsperado.toFixed(2)} kg/ud). Umbral: ${CFG_PCT}%.`;
        aviso.style.display = '';
    } else {
        aviso.style.display = 'none';
    }
}

// ── Confirmar inventarios afectados al cambiar lote en edición ─
async function antesDeEnviar(e) {
    if (!ES_EDICION || !MOV_ID) return true;
    const loteSel = document.querySelector('select[name="lote_origen_id"]');
    if (!loteSel) return true;
    const nuevoLote = parseInt(loteSel.value || '0');
    if (!nuevoLote || nuevoLote === LOTE_ORIGINAL_ID) return true;

    const res = await fetch(`${BASE_URL}movimientos/${MOV_ID}/inventarios-afectados?lote_id=${nuevoLote}`);
    const data = await res.json();
    if ((data.count || 0) === 0) return true;

    e.preventDefault();
    const ok = confirm(`Hay ${data.count} inventario(s) con fecha posterior a este movimiento que se verán afectados al cambiar el lote.\n\n¿Continuar?`);
    if (ok) {
        document.getElementById('confirmarInv').value = '1';
        document.getElementById('frmMov').submit();
    }
    return false;
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('fechaMov')?.addEventListener('change', () => { validarFecha(); actualizarPesoEstimado(); });
    document.getElementById('frmMov')?.addEventListener('submit', antesDeEnviar);
    validarFecha();
    actualizarPesoEstimado();

    <?php if ($esEdicion && $movimiento && $categoriaActual === 'baja'): ?>
    // Precargar cuadras del lote en edición de baja
    if (LOTE_ORIGINAL_ID) cargarCuadrasDelLote(LOTE_ORIGINAL_ID, 'baja');
    <?php elseif ($esEdicion && $movimiento && $categoriaActual === 'venta'): ?>
    if (LOTE_ORIGINAL_ID) cargarCuadrasDelLote(LOTE_ORIGINAL_ID, 'venta');
    <?php endif; ?>
});
</script>
