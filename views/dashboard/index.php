<?php
/**
 * @var array $kpis
 * @var array $alertas
 * @var array $movimientos
 * @var array $naves
 * @var array $matadero
 */
?>
<style>
    .dashboard { display: flex; flex-direction: column; gap: 2rem; }

    /* KPIs */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
    .kpi-card {
        background: #fff;
        border-radius: 10px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 1px 6px rgba(0,0,0,.07);
        border-left: 4px solid #1a56db;
    }
    .kpi-card.warning { border-left-color: #d97706; }
    .kpi-card.danger  { border-left-color: #dc2626; }
    .kpi-card.success { border-left-color: #16a34a; }
    .kpi-label { font-size: .78rem; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; }
    .kpi-value { font-size: 1.9rem; font-weight: 700; color: #111827; line-height: 1; }
    .kpi-sub   { font-size: .8rem; color: #9ca3af; margin-top: .3rem; }

    /* Secciones */
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: .75rem;
        padding-bottom: .4rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .card {
        background: #fff;
        border-radius: 10px;
        padding: 1.5rem;
        box-shadow: 0 1px 6px rgba(0,0,0,.07);
    }

    /* Alertas */
    .alerta {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .7rem .9rem;
        border-radius: 7px;
        margin-bottom: .5rem;
        font-size: .875rem;
        line-height: 1.5;
    }
    .alerta:last-child { margin-bottom: 0; }
    .alerta.danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alerta.warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .alerta.info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .alerta-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
    .alerta-tipo {
        font-size: .7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: .15rem;
    }
    .no-alertas { color: #16a34a; font-size: .875rem; text-align: center; padding: 1rem 0; }

    /* Tabla */
    .tabla { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .tabla th {
        text-align: left;
        padding: .5rem .75rem;
        background: #f9fafb;
        color: #6b7280;
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #e5e7eb;
    }
    .tabla td { padding: .6rem .75rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
    .tabla tr:last-child td { border-bottom: none; }
    .tabla tr:hover td { background: #f9fafb; }

    /* Badge tipo movimiento */
    .badge {
        display: inline-block;
        padding: .15rem .55rem;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .badge-baja      { background: #fee2e2; color: #991b1b; }
    .badge-traslado  { background: #dbeafe; color: #1e40af; }
    .badge-matadero  { background: #f3e8ff; color: #6b21a8; }
    .badge-venta     { background: #d1fae5; color: #065f46; }

    /* Barra ocupación */
    .barra-wrap { background: #e5e7eb; border-radius: 99px; height: 8px; width: 100%; min-width: 80px; }
    .barra-fill { height: 8px; border-radius: 99px; transition: width .3s; }
    .barra-fill.ok      { background: #16a34a; }
    .barra-fill.medio   { background: #d97706; }
    .barra-fill.lleno   { background: #dc2626; }

    /* Matadero */
    .matadero-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .6rem 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: .875rem;
        gap: 1rem;
    }
    .matadero-item:last-child { border-bottom: none; }
    .matadero-pct {
        font-weight: 700;
        font-size: 1.1rem;
        min-width: 48px;
        text-align: right;
    }
    .pct-alto  { color: #16a34a; }
    .pct-medio { color: #d97706; }

    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }

    .empty { color: #9ca3af; font-size: .875rem; text-align: center; padding: 1.5rem 0; }
</style>

<div class="dashboard">

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Animales vivos</div>
            <div class="kpi-value"><?= number_format($kpis['total_animales']) ?></div>
            <div class="kpi-sub"><?= $kpis['total_lotes'] ?> lotes activos</div>
        </div>
        <div class="kpi-card <?= $kpis['bajas_semana'] > 0 ? 'warning' : 'success' ?>">
            <div class="kpi-label">Bajas esta semana</div>
            <div class="kpi-value"><?= $kpis['bajas_semana'] ?></div>
            <div class="kpi-sub"><?= $kpis['bajas_mes'] ?> en los últimos 30 días</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">IC medio</div>
            <div class="kpi-value"><?= $kpis['ic_medio'] ?></div>
            <div class="kpi-sub">Lotes activos con pesaje</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Granjas</div>
            <div class="kpi-value"><?= $kpis['total_granjas'] ?></div>
            <div class="kpi-sub">con lotes activos</div>
        </div>
    </div>

    <!-- Alertas -->
    <div class="card">
        <div class="section-title">Alertas</div>
        <?php if (empty($alertas)): ?>
            <div class="no-alertas">Sin alertas activas — todo en orden</div>
        <?php else: ?>
            <?php foreach ($alertas as $a): ?>
                <?php
                $icono = match($a['tipo']) {
                    'pienso' => '▼',
                    'baja'   => '!',
                    'peso'   => '~',
                    default  => '•',
                };
                $tipoLabel = match($a['tipo']) {
                    'pienso' => 'Pienso',
                    'baja'   => 'Mortalidad',
                    'peso'   => 'Peso',
                    default  => $a['tipo'],
                };
                ?>
                <div class="alerta <?= e($a['nivel']) ?>">
                    <span class="alerta-icon"><?= $icono ?></span>
                    <div>
                        <div class="alerta-tipo"><?= $tipoLabel ?></div>
                        <?= $a['mensaje'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Próximos a matadero + Ocupación naves -->
    <div class="two-col">

        <div class="card">
            <div class="section-title">Próximos a matadero</div>
            <?php if (empty($matadero)): ?>
                <div class="empty">No hay lotes cerca del peso objetivo</div>
            <?php else: ?>
                <?php foreach ($matadero as $m): ?>
                    <div class="matadero-item">
                        <div>
                            <strong><?= e($m['codigo']) ?></strong>
                            <div style="font-size:.78rem;color:#6b7280"><?= e($m['nave']) ?> · <?= e($m['granja']) ?></div>
                            <div style="font-size:.78rem;color:#6b7280"><?= e($m['num_animales']) ?> animales · <?= e($m['peso_medio_kg']) ?> kg</div>
                        </div>
                        <div class="matadero-pct <?= $m['pct_objetivo'] >= 95 ? 'pct-alto' : 'pct-medio' ?>">
                            <?= $m['pct_objetivo'] ?>%
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-title">Ocupación de naves</div>
            <?php if (empty($naves)): ?>
                <div class="empty">No hay naves registradas</div>
            <?php else: ?>
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Nave</th>
                            <th>Animales</th>
                            <th>Ocupación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($naves as $nave): ?>
                            <?php
                            $pct   = (int) ($nave['pct_ocupacion'] ?? 0);
                            $clase = $pct >= 90 ? 'lleno' : ($pct >= 60 ? 'medio' : 'ok');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($nave['nave']) ?></strong>
                                    <div style="font-size:.75rem;color:#9ca3af"><?= e($nave['granja']) ?></div>
                                </td>
                                <td><?= e($nave['ocupacion_actual']) ?> / <?= e($nave['capacidad_maxima']) ?></td>
                                <td style="min-width:100px">
                                    <div style="display:flex;align-items:center;gap:.5rem">
                                        <div class="barra-wrap">
                                            <div class="barra-fill <?= $clase ?>" style="width:<?= min($pct, 100) ?>%"></div>
                                        </div>
                                        <span style="font-size:.78rem;color:#6b7280;min-width:32px"><?= $pct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

    <!-- Últimos movimientos -->
    <div class="card">
        <div class="section-title">Últimos movimientos</div>
        <?php if (empty($movimientos)): ?>
            <div class="empty">No hay movimientos registrados</div>
        <?php else: ?>
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Lote</th>
                        <th>Nave / Granja</th>
                        <th>Animales</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($mov['fecha']))) ?></td>
                            <td><span class="badge badge-<?= e($mov['tipo']) ?>"><?= e($mov['tipo']) ?></span></td>
                            <td><?= e($mov['lote']) ?></td>
                            <td>
                                <?= e($mov['nave']) ?>
                                <span style="color:#9ca3af"> · <?= e($mov['granja']) ?></span>
                            </td>
                            <td><?= e($mov['num_animales']) ?></td>
                            <td style="color:#6b7280;font-size:.82rem">
                                <?php if ($mov['tipo'] === 'traslado' && $mov['nave_destino']): ?>
                                    → <?= e($mov['nave_destino']) ?>
                                <?php elseif ($mov['motivo']): ?>
                                    <?= e($mov['motivo']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
