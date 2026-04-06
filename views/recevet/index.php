<div class="page-header">
    <h2>Recevet — Libro de Tratamientos</h2>
    <a href="https://www.recevet.es" target="_blank" class="btn btn-secondary" style="display:flex;align-items:center;gap:.4rem">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Abrir Recevet
    </a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php
// Aviso de requisitos faltantes
$req = $requisitos ?? [];
$faltantes = [];
if (!($req['curl']    ?? true)) $faltantes[] = 'cURL (necesario para conectar con recevet.es)';
if (!($req['openssl'] ?? true)) $faltantes[] = 'OpenSSL (necesario para cifrar la contraseña)';
if (!($req['dom']     ?? true)) $faltantes[] = 'DOM (necesario para procesar el HTML de recevet.es)';
?>
<?php if (!empty($faltantes)): ?>
<div class="alert-flash alert-error" style="margin-bottom:1rem">
    <strong>Extensiones PHP no disponibles en este servidor:</strong>
    <ul style="margin:.4rem 0 0 1.2rem;padding:0">
        <?php foreach ($faltantes as $f): ?>
        <li><?= e($f) ?></li>
        <?php endforeach; ?>
    </ul>
    Contacta con tu hosting para habilitarlas.
</div>
<?php endif; ?>

<!-- Estado de configuración -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem">

    <!-- Credenciales -->
    <div class="form-card">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem">
            <?php if ($credencialesOk): ?>
                <span style="width:10px;height:10px;border-radius:50%;background:#16a34a;flex-shrink:0"></span>
                <span style="font-weight:700;font-size:.88rem">Credenciales configuradas</span>
            <?php else: ?>
                <span style="width:10px;height:10px;border-radius:50%;background:#dc2626;flex-shrink:0"></span>
                <span style="font-weight:700;font-size:.88rem;color:#dc2626">Credenciales no configuradas</span>
            <?php endif; ?>
        </div>
        <div style="font-size:.82rem;color:#6b7280;margin-bottom:.75rem">
            <?php if ($credencialesOk): ?>
                Usuario: <strong><?= e($usuario['recevet_usuario']) ?></strong>
            <?php else: ?>
                Configura tu usuario y contraseña de Recevet en tu perfil para poder sincronizar.
            <?php endif; ?>
        </div>
        <a href="<?= base_url('perfil') ?>" class="btn btn-secondary btn-sm">
            <?= $credencialesOk ? 'Cambiar credenciales' : 'Configurar credenciales' ?>
        </a>
    </div>

    <!-- Granjas conectadas -->
    <div class="form-card">
        <div style="font-weight:700;font-size:.88rem;margin-bottom:.75rem">
            Granjas vinculadas a Recevet
        </div>
        <?php if (empty($granjasRecevet)): ?>
            <div style="font-size:.82rem;color:#6b7280;margin-bottom:.75rem">
                Ninguna granja tiene configurado su código de explotación Recevet.
                Edita cada granja para añadirlo.
            </div>
            <a href="<?= base_url('granjas') ?>" class="btn btn-secondary btn-sm">Ir a Granjas</a>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:.75rem">
                <?php foreach ($granjasRecevet as $g): ?>
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                    <span style="font-weight:600"><?= e($g['nombre']) ?></span>
                    <span style="color:#6b7280;font-family:monospace"><?= e($g['recevet_explotacion']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= base_url('granjas') ?>" class="btn btn-secondary btn-sm">Gestionar granjas</a>
        <?php endif; ?>
    </div>
</div>

<!-- Formulario de sincronización -->
<?php if ($credencialesOk && !empty($granjasRecevet)): ?>
<div class="form-card" style="max-width:600px">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:1rem">
        Sincronizar Libro de Tratamientos
    </div>

    <form method="POST" action="<?= base_url('recevet/sincronizar') ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Granjas a sincronizar</label>
            <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.25rem">
                <?php foreach ($granjasRecevet as $g): ?>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:400">
                    <input type="checkbox" name="granjas[]" value="<?= $g['id'] ?>" checked>
                    <span style="font-weight:600"><?= e($g['nombre']) ?></span>
                    <span style="font-size:.75rem;color:#9ca3af;font-family:monospace"><?= e($g['recevet_explotacion']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#92400e">
            <strong>¿Qué hace la sincronización?</strong><br>
            Para cada tratamiento pendiente de completar en Recevet, rellena automáticamente:
            <ul style="margin:.4rem 0 0 1rem;padding:0">
                <li><strong>Fecha inicio</strong> = Fecha de dispensación + 1 día</li>
                <li><strong>Fecha fin</strong> = Fecha inicio + días de tratamiento − 1 (si aplica)</li>
            </ul>
            A continuación hace clic en "Aceptar" por cada línea.
        </div>

        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400;font-size:.85rem">
                <input type="checkbox" name="dry_run" value="1" id="dryRun">
                <span><strong>Modo simulación</strong> — muestra qué haría sin enviar nada</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnSync">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:.3rem"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Sincronizar ahora
            </button>
        </div>
    </form>
</div>
<?php elseif (!$credencialesOk): ?>
<div class="empty-state">
    Configura tus credenciales de Recevet en <a href="<?= base_url('perfil') ?>">tu perfil</a> para poder sincronizar.
</div>
<?php else: ?>
<div class="empty-state">
    Añade el código de explotación Recevet en al menos una <a href="<?= base_url('granjas') ?>">granja</a> para poder sincronizar.
</div>
<?php endif; ?>

<!-- Log de última sincronización -->
<?php if (!empty($logs)): ?>
<div style="margin-top:1.5rem">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.5rem">
        Log de sincronización
    </div>
    <div class="list-card" style="font-size:.8rem;font-family:monospace;max-height:400px;overflow-y:auto">
        <?php foreach ($logs as $entry): ?>
            <?php
            $color = $entry['type'] === 'success' ? '#166534' : ($entry['type'] === 'error' ? '#dc2626' : '#374151');
            $bg    = $entry['type'] === 'error' ? '#fff1f2' : 'transparent';
            ?>
            <div style="padding:.3rem .75rem;background:<?= $bg ?>;border-bottom:1px solid #f3f4f6;color:<?= $color ?>">
                <span style="color:#9ca3af;margin-right:.5rem"><?= $entry['ts'] ?></span>
                <?= e($entry['msg']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
