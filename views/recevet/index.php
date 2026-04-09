<?php
$seccionConfig = 'recevet';
include __DIR__ . '/../config/_submenu.php';
?>

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
$req = $requisitos ?? [];
$faltantes = [];
if (!($req['curl'] ?? true)) $faltantes[] = 'cURL';
if (!($req['dom']  ?? true)) $faltantes[] = 'DOM';
?>
<?php if (!empty($faltantes)): ?>
<div class="alert-flash alert-error" style="margin-bottom:1rem">
    Extensiones PHP no disponibles: <strong><?= e(implode(', ', $faltantes)) ?></strong>. Contacta con tu hosting.
</div>
<?php endif; ?>

<?php
// Generar el bookmarklet
$uid   = $usuario['id'] ?? 0;
$token = \App\Controllers\RecevtController::bookmarkletToken($uid);
$callbackUrl = base_url('recevet/capturar-cookie') . '?uid=' . $uid . '&token=' . $token . '&cookie=';

// Código JS del bookmarklet (minificado inline)
$bookmarkletJs = "javascript:(function(){"
    . "var c=document.cookie;"
    . "if(!c){alert('No se encontraron cookies. Asegúrate de estar en recevet.es con sesión iniciada.');return;}"
    . "window.location.href='" . addslashes($callbackUrl) . "'+encodeURIComponent(c);"
    . "})();";
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem">

    <!-- Estado de sesión -->
    <div class="form-card">
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.75rem">
            Sesión en Recevet
        </div>

        <?php if ($sesionActiva): ?>
            <!-- Sesión activa -->
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem">
                <span style="width:10px;height:10px;border-radius:50%;background:#16a34a;flex-shrink:0"></span>
                <span style="font-weight:700;font-size:.88rem">Sesión activa</span>
            </div>
            <div style="font-size:.82rem;color:#6b7280;margin-bottom:.75rem">
                Usuario: <strong><?= e($usuario['recevet_usuario'] ?? '') ?></strong>
            </div>
            <form method="POST" action="<?= base_url('recevet/cerrar-sesion') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm"
                        onclick="return confirm('¿Cerrar sesión en Recevet?')">Cerrar sesión</button>
            </form>

        <?php else: ?>
            <!-- Sin sesión — mostrar bookmarklet -->
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem">
                <span style="width:10px;height:10px;border-radius:50%;background:#dc2626;flex-shrink:0"></span>
                <span style="font-weight:700;font-size:.88rem;color:#dc2626">Sin sesión activa</span>
            </div>

            <div style="font-size:.82rem;color:#374151;line-height:1.6;margin-bottom:1rem">
                Para conectar, sigue estos pasos <strong>una sola vez</strong>:
            </div>

            <!-- Paso 1: arrastrar bookmarklet -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:.75rem 1rem;margin-bottom:.75rem">
                <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:.5rem">
                    Paso 1 — Arrastra este botón a tu barra de favoritos:
                </div>
                <a href="<?= e($bookmarkletJs) ?>"
                   class="btn btn-secondary btn-sm"
                   style="cursor:grab;background:#fef3c7;border-color:#f59e0b;color:#92400e;font-weight:700"
                   onclick="alert('No hagas clic aquí. Arrastra este botón a tu barra de favoritos.');return false;">
                    📎 Conectar Recevet
                </a>
                <div style="font-size:.75rem;color:#6b7280;margin-top:.4rem">
                    ↑ Arrastra este botón amarillo a la barra de favoritos de tu navegador
                </div>
            </div>

            <!-- Paso 2: ir a recevet -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:.75rem 1rem;margin-bottom:.75rem">
                <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:.5rem">
                    Paso 2 — Inicia sesión en Recevet:
                </div>
                <a href="https://www.recevet.es" target="_blank" class="btn btn-primary btn-sm">
                    Abrir recevet.es →
                </a>
                <div style="font-size:.75rem;color:#6b7280;margin-top:.4rem">
                    Inicia sesión normal con tu usuario y contraseña (e introduce el código que te manden por email)
                </div>
            </div>

            <!-- Paso 3: clic en bookmarklet -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:.75rem 1rem">
                <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:.5rem">
                    Paso 3 — Una vez dentro de Recevet, haz clic en "📎 Conectar Recevet" de tus favoritos
                </div>
                <div style="font-size:.75rem;color:#6b7280">
                    Te redirigirá automáticamente de vuelta aquí con la sesión activa ✓
                </div>
            </div>

            <div style="font-size:.75rem;color:#9ca3af;margin-top:.75rem">
                La sesión dura ~10 días. Cuando caduque, repite solo los pasos 2 y 3.
            </div>
        <?php endif; ?>
    </div>

    <!-- Granjas conectadas -->
    <div class="form-card">
        <div style="font-weight:700;font-size:.88rem;margin-bottom:.75rem">
            Granjas vinculadas a Recevet
        </div>
        <?php if (empty($granjasRecevet)): ?>
            <div style="font-size:.82rem;color:#6b7280;margin-bottom:.75rem">
                Ninguna granja tiene configurado su código de explotación Recevet. Edita cada granja para añadirlo.
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
<?php if ($sesionActiva && !empty($granjasRecevet)): ?>
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
            Para cada tratamiento pendiente rellena automáticamente:
            <ul style="margin:.4rem 0 0 1rem;padding:0">
                <li><strong>Fecha inicio</strong> = Fecha de dispensación + 1 día</li>
                <li><strong>Fecha fin</strong> = Fecha inicio + días de tratamiento − 1</li>
            </ul>
        </div>

        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400;font-size:.85rem">
                <input type="checkbox" name="dry_run" value="1">
                <span><strong>Modo simulación</strong> — muestra qué haría sin enviar nada</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:.3rem"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Sincronizar ahora
            </button>
        </div>
    </form>
</div>
<?php elseif (!$sesionActiva): ?>
<div class="empty-state">
    Conecta tu sesión de Recevet para poder sincronizar.
</div>
<?php else: ?>
<div class="empty-state">
    Añade el código de explotación Recevet en al menos una <a href="<?= base_url('granjas') ?>">granja</a> para poder sincronizar.
</div>
<?php endif; ?>

<!-- Log -->
<?php if (!empty($logs)): ?>
<div style="margin-top:1.5rem">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:.5rem">
        Log de sincronización
    </div>
    <div class="list-card" style="font-size:.8rem;font-family:monospace;max-height:400px;overflow-y:auto">
        <?php foreach ($logs as $entry): ?>
            <?php
            $color = match($entry['type']) { 'success' => '#166534', 'error' => '#dc2626', default => '#374151' };
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
