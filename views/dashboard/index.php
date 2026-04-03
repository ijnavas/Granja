<?php
/**
 * @var array $kpis
 * @var array $alertas
 * @var array $movimientos
 * @var array $naves
 * @var array $matadero
 */
?>

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
            <div class="section-title">Próximos a matadero <span style="font-size:.75rem;font-weight:400;color:#9ca3af">(previsión · 3% bajas est.)</span></div>
            <?php if (empty($matadero)): ?>
                <div class="empty">No hay lotes con tabla de crecimiento asignada</div>
            <?php else: ?>
                <table class="tabla" style="font-size:.82rem">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Ubicación</th>
                            <th style="text-align:right">Animales est.</th>
                            <th style="text-align:center">Semana</th>
                            <th style="text-align:center">Previsión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matadero as $m):
                            $sem = (int) $m['semanas_restantes'];
                            if ($sem <= 0) {
                                $badge = '<span style="background:#dcfce7;color:#166534;padding:.15rem .5rem;border-radius:20px;font-size:.72rem;font-weight:700">Listo</span>';
                            } elseif ($sem <= 4) {
                                $badge = '<span style="background:#fef3c7;color:#92400e;padding:.15rem .5rem;border-radius:20px;font-size:.72rem;font-weight:700">en ' . $sem . ' sem.</span>';
                            } else {
                                $badge = '<span style="background:#dbeafe;color:#1e40af;padding:.15rem .5rem;border-radius:20px;font-size:.72rem;font-weight:400">en ' . $sem . ' sem.</span>';
                            }
                        ?>
                        <tr>
                            <td><strong><?= e($m['codigo']) ?></strong></td>
                            <td style="color:#6b7280;font-size:.78rem"><?= e($m['nave']) ?> · <?= e($m['granja']) ?></td>
                            <td style="text-align:right;font-weight:600"><?= number_format($m['animales_estimados']) ?></td>
                            <td style="text-align:center;color:#6b7280">S<?= e($m['semana_matadero']) ?></td>
                            <td style="text-align:center">
                                <?= $badge ?>
                                <div style="font-size:.72rem;color:#9ca3af;margin-top:.1rem"><?= e($m['fecha_estimada']) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
