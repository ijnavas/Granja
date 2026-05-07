<?php $seccionConfig = 'seed_test'; require __DIR__ . '/_submenu.php'; ?>

<div class="page-header">
    <h2>Datos de prueba</h2>
</div>

<?php if (!empty($success)): ?>
    <div class="alert-flash alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-flash alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="max-width:680px">
    <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:1.5rem;margin-bottom:1.5rem">
        <p style="margin:0 0 .5rem;color:#1e40af"><strong>Generador de datos para pruebas.</strong></p>
        <p style="margin:0 0 .5rem;color:#1e3a8a">Crea automáticamente:</p>
        <ul style="color:#1e3a8a;margin:0 0 .5rem;padding-left:1.25rem;line-height:1.7">
            <li><strong>Naves</strong>: D1, D2, D3, C7 (en tu primera granja)</li>
            <li><strong>Cuadras</strong>: D1 (1, 2, 3, 4, 5) · D2 (90, 91, 92, 93) · D3 (90, 91, 92, 93) · C7 (11, 21, 31, 41, 51, 61, 71, 81)</li>
            <li><strong>Lotes de destete</strong>: uno por cada jueves desde el 04/12/2025 hasta el 31/12/2026 (61 lotes), con cantidad aleatoria entre 401 y 600 animales</li>
            <li><strong>Movimientos de destete</strong> el jueves de cada lote (asignación a la primera cuadra D1 libre)</li>
            <li><strong>Traslados los miércoles</strong>: cuando D1 está lleno, mueve el lote más antiguo al primer hueco libre en D2 → D3 → C7 antes del siguiente destete</li>
            <li><strong>Auto-pesaje</strong> al alta de cada lote (7 kg/animal)</li>
        </ul>
        <p style="margin:.5rem 0 0;color:#1e3a8a;font-size:.85rem">
            Es idempotente con respecto a naves/cuadras (no las duplica si ya existen) pero <em>siempre</em> añade nuevos lotes y movimientos. Si vuelves a ejecutar, tendrás duplicados.
        </p>
    </div>

    <div class="form-card">
        <form method="POST" action="<?= base_url('configuracion/seed-test') ?>" onsubmit="return confirmarSeed('confirmacion','SEMBRAR')">
            <?= csrf_field() ?>
            <div class="form-group">
                <label style="font-weight:600">Para confirmar, escribe <code style="background:#dbeafe;padding:.1rem .4rem;border-radius:4px;color:#1e40af">SEMBRAR</code> en el campo:</label>
                <input type="text" name="confirmacion" id="confirmacion" autocomplete="off"
                       placeholder="Escribe SEMBRAR para confirmar" style="margin-top:.5rem">
            </div>
            <button type="submit" class="btn btn-primary">Sembrar datos básicos</button>
        </form>
    </div>

    <!-- ─────────────────────────────────────────────────────────── -->
    <!-- Sembrar bajas en lotes existentes                            -->
    <!-- ─────────────────────────────────────────────────────────── -->
    <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;padding:1.5rem;margin:1.5rem 0">
        <p style="margin:0 0 .5rem;color:#991b1b"><strong>Sembrar bajas aleatorias.</strong></p>
        <p style="margin:0 0 .5rem;color:#7f1d1d">Para cada lote existente del usuario:</p>
        <ul style="color:#7f1d1d;margin:0 0 .5rem;padding-left:1.25rem;line-height:1.7">
            <li>~10% de los lotes son <strong>outliers</strong> con mortalidad <strong>6-8%</strong> (destacarán en informes)</li>
            <li>El resto tendrá mortalidad entre <strong>1.5% y 2.5%</strong></li>
            <li>3-7 eventos de baja por lote, repartidos entre <em>fecha_entrada</em> y hoy (o fecha de cierre)</li>
            <li>Motivos aleatorios entre los configurados en Avisos</li>
            <li>Descuenta animales del lote y de su cuadra activa</li>
        </ul>
    </div>

    <div class="form-card">
        <form method="POST" action="<?= base_url('configuracion/seed-bajas') ?>" onsubmit="return confirmarSeed('confirmacion-bajas','BAJAS')">
            <?= csrf_field() ?>
            <div class="form-group">
                <label style="font-weight:600">Para confirmar, escribe <code style="background:#fee2e2;padding:.1rem .4rem;border-radius:4px;color:#991b1b">BAJAS</code> en el campo:</label>
                <input type="text" name="confirmacion" id="confirmacion-bajas" autocomplete="off"
                       placeholder="Escribe BAJAS para confirmar" style="margin-top:.5rem">
            </div>
            <button type="submit" class="btn btn-danger" style="background:#dc2626;color:#fff;border-color:#dc2626">
                Sembrar bajas
            </button>
        </form>
    </div>
</div>

<script>
function confirmarSeed(inputId, palabra) {
    if (document.getElementById(inputId).value.trim() !== palabra) {
        alert('Escribe ' + palabra + ' en el campo para confirmar.');
        return false;
    }
    return confirm('¿Continuar con el sembrado? Puede tardar unos segundos.');
}
</script>
